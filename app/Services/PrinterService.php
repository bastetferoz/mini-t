<?php

namespace App\Services;

use App\Models\Printer;
use Illuminate\Support\Facades\Log;

class PrinterService
{
    // ─── OIDs estándar (RFC 1213 / RFC 3805 Printer MIB) ───

    /** sysDescr: descripción del sistema (marca + modelo suele venir acá) */
    private const OID_SYS_DESCR = '1.3.6.1.2.1.1.1.0';

    /** hrDeviceDescr (primer dispositivo): nombre del dispositivo */
    private const OID_HR_DEVICE_DESCR = '1.3.6.1.2.1.25.3.2.1.3.1';

    /** prtGeneralPrinterName */
    private const OID_PRT_NAME = '1.3.6.1.2.1.43.5.1.1.16.1';

    /** prtGeneralSerialNumber */
    private const OID_SERIAL = '1.3.6.1.2.1.43.5.1.1.17.1';

    /** prtMarkerLifeCount: contador total de páginas impresas */
    private const OID_PAGE_COUNT = '1.3.6.1.2.1.43.10.2.1.4.1.1';

    /**
     * Verifica una impresora: hace ping, consulta SNMP y actualiza el modelo.
     * Retorna un array con el resultado del sondeo.
     */
    public static function probe(Printer $printer, string $source = 'manual'): array
    {
        $online = self::ping($printer->ip);

        $result = [
            'online'     => $online,
            'brand'      => null,
            'model'      => null,
            'serial'     => null,
            'page_count' => null,
            'snmp'       => false,
        ];

        if ($online) {
            $snmp = self::querySnmp($printer->ip, $printer->snmp_community ?: 'public');
            $result = array_merge($result, $snmp);
        }

        // Actualizar el modelo con lo obtenido
        $printer->status = $online ? 'online' : 'offline';

        if ($online) {
            $printer->last_seen_at = now();
        }

        if (! empty($result['brand'])) {
            $printer->brand = $result['brand'];
        }
        if (! empty($result['model'])) {
            $printer->model = $result['model'];
        }
        if (! empty($result['serial'])) {
            $printer->serial = $result['serial'];
        }

        if ($result['page_count'] !== null) {
            $printer->page_count = $result['page_count'];
            $printer->page_count_at = now();

            // Guardar en el historial
            $printer->readings()->create([
                'page_count' => $result['page_count'],
                'read_at'    => now(),
                'source'     => $source,
            ]);
        }

        $printer->save();

        return $result;
    }

    /**
     * Hace ping a una IP. Retorna true si responde.
     */
    public static function ping(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // -c 1: un paquete | -W 2: timeout 2s. escapeshellarg evita inyección.
        $command = sprintf('ping -c 1 -W 2 %s 2>&1', escapeshellarg($ip));
        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    /**
     * Consulta los OIDs SNMP de la impresora.
     * Usa la extensión php-snmp si está disponible, si no cae al binario snmpget.
     */
    public static function querySnmp(string $ip, string $community = 'public'): array
    {
        $descr  = self::snmpGet($ip, $community, self::OID_SYS_DESCR);
        $serial = self::snmpGet($ip, $community, self::OID_SERIAL);

        // Fallbacks para el nombre/modelo si sysDescr no ayuda
        if (! $descr) {
            $descr = self::snmpGet($ip, $community, self::OID_PRT_NAME)
                ?: self::snmpGet($ip, $community, self::OID_HR_DEVICE_DESCR);
        }

        // Fallback de serie (Pantum expone la serie real en su rama privada)
        if (! $serial || ! self::looksLikeSerial($serial)) {
            $altSerial = self::snmpGet($ip, $community, '1.3.6.1.4.1.40093.1.1.1.5');
            if ($altSerial) {
                $serial = $altSerial;
            }
        }

        // Contador de páginas: probar los OIDs candidatos hasta hallar un número > 0
        $pages = null;
        foreach (self::pageCountCandidates() as $candidate) {
            $value = self::snmpGet($ip, $community, $candidate['oid']);
            if (is_numeric($value) && (int) $value > 0) {
                $pages = (int) $value;
                break;
            }
        }

        [$brand, $model] = self::parseBrandModel($descr);

        return [
            'brand'      => $brand,
            'model'      => $model,
            'serial'     => $serial ? trim($serial) : null,
            'page_count' => $pages,
            'snmp'       => $descr !== null || $pages !== null,
        ];
    }

    /**
     * Heurística simple: ¿el valor parece un número de serie real y no un hex vacío?
     */
    private static function looksLikeSerial(?string $value): bool
    {
        if (! $value) {
            return false;
        }
        $value = trim($value);
        // Descartar valores hex vacíos tipo "Hex-STRING: 00 00 00 00" o "00 00 00 00"
        if (preg_match('/^(Hex-STRING:)?\s*(00\s*)+$/i', $value)) {
            return false;
        }
        // Descartar cualquier cosa que quede como hex crudo sin contenido útil
        if (stripos($value, 'Hex-STRING') !== false) {
            return false;
        }
        return strlen($value) >= 4;
    }

    /**
     * SNMP GET de un único OID. Devuelve el valor como string o null.
     */
    private static function snmpGet(string $ip, string $community, string $oid): ?string
    {
        // Método 1: extensión php-snmp
        if (function_exists('snmpget')) {
            try {
                // Silenciamos warnings de timeout/host inalcanzable
                $value = @snmpget($ip, $community, $oid, 2_000_000, 1);
                if ($value !== false) {
                    return self::cleanSnmpValue($value);
                }
            } catch (\Throwable $e) {
                Log::debug("PrinterService: snmpget ext falló para {$ip} {$oid}: " . $e->getMessage());
            }
            return null;
        }

        // Método 2: binario snmpget (net-snmp)
        if (self::commandExists('snmpget')) {
            $command = sprintf(
                'snmpget -v2c -c %s -t 2 -r 1 -Oqv %s %s 2>/dev/null',
                escapeshellarg($community),
                escapeshellarg($ip),
                escapeshellarg($oid)
            );
            exec($command, $output, $exitCode);

            if ($exitCode === 0 && ! empty($output[0])) {
                return self::cleanSnmpValue($output[0]);
            }
        }

        return null;
    }

    /**
     * Limpia el valor SNMP (quita comillas, tipos, espacios).
     */
    private static function cleanSnmpValue(string $value): string
    {
        // Quitar prefijos de tipo tipo "STRING: " o "INTEGER: "
        $value = preg_replace('/^\w+:\s*/', '', $value);
        return trim($value, " \t\n\r\0\x0B\"");
    }

    /**
     * Intenta separar marca y modelo de la descripción SNMP.
     */
    private static function parseBrandModel(?string $descr): array
    {
        if (! $descr) {
            return [null, null];
        }

        $descr = trim($descr);

        $brands = ['HP', 'Hewlett-Packard', 'Hewlett Packard', 'Canon', 'Epson', 'Brother',
            'Lexmark', 'Xerox', 'Ricoh', 'Kyocera', 'Samsung', 'Konica Minolta',
            'Konica', 'OKI', 'Sharp', 'Toshiba', 'Zebra', 'Dell', 'Panasonic', 'Pantum'];

        $foundBrand = null;
        foreach ($brands as $b) {
            if (stripos($descr, $b) !== false) {
                $foundBrand = str_ireplace(['Hewlett-Packard', 'Hewlett Packard'], 'HP', $b);
                break;
            }
        }

        // Si viene en formato IEEE 1284 (MFG:...;MDL:...;) extraer solo el modelo.
        $model = $descr;
        if (preg_match('/MDL:([^;]+)/i', $descr, $m)) {
            $model = trim($m[1]);
        }
        if (! $foundBrand && preg_match('/MFG:([^;]+)/i', $descr, $mf)) {
            $foundBrand = trim($mf[1]);
        }

        return [$foundBrand, $model];
    }

    private static function commandExists(string $command): bool
    {
        $which = shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
        return ! empty($which);
    }

    // ─── DIAGNÓSTICO ───

    /**
     * Lista de OIDs candidatos para el contador de páginas, por marca.
     * Se prueban todos en el diagnóstico para encontrar cuál "emboca".
     */
    public static function pageCountCandidates(): array
    {
        return [
            ['label' => 'Pantum — páginas impresas',                    'oid' => '1.3.6.1.4.1.40093.1.1.3.3'],
            ['label' => 'Estándar (Printer MIB) — prtMarkerLifeCount', 'oid' => '1.3.6.1.2.1.43.10.2.1.4.1.1'],
            ['label' => 'HP — total impresas',                          'oid' => '1.3.6.1.2.1.43.10.2.1.4.1.1'],
            ['label' => 'HP (jetdirect) total engine',                  'oid' => '1.3.6.1.4.1.11.2.3.9.4.2.1.4.1.2.5.0'],
            ['label' => 'Brother — page counter',                       'oid' => '1.3.6.1.4.1.2435.2.3.9.4.2.1.5.5.1.1.0'],
            ['label' => 'Kyocera — total pages',                        'oid' => '1.3.6.1.4.1.1347.42.2.1.1.1.6.1.1'],
            ['label' => 'Canon — total pages',                          'oid' => '1.3.6.1.4.1.1602.1.11.1.3.1.4.1'],
            ['label' => 'Xerox — total impressions',                    'oid' => '1.3.6.1.4.1.253.8.53.13.2.1.6.1.20.1'],
            ['label' => 'Ricoh — total counter',                        'oid' => '1.3.6.1.4.1.367.3.2.1.2.19.5.1.9.1'],
            ['label' => 'Lexmark — page count',                         'oid' => '1.3.6.1.4.1.641.6.4.2.1.6.1.5.1'],
            ['label' => 'Samsung — total pages',                        'oid' => '1.3.6.1.4.1.236.11.5.1.1.1.1.0'],
            ['label' => 'Epson — total pages',                          'oid' => '1.3.6.1.2.1.43.10.2.1.4.1.1'],
        ];
    }

    /**
     * Diagnóstico completo: ping + sysDescr + prueba todos los OIDs de contador.
     * Devuelve un array listo para mostrar en el panel de diagnóstico.
     */
    public static function diagnose(string $ip, string $community = 'public'): array
    {
        $online = self::ping($ip);

        $out = [
            'ip'          => $ip,
            'community'   => $community,
            'online'      => $online,
            'snmp_ext'    => function_exists('snmpget'),
            'snmp_bin'    => self::commandExists('snmpget'),
            'sys_descr'   => null,
            'sys_name'    => null,
            'serial'      => null,
            'candidates'  => [],
        ];

        if (! $online) {
            return $out;
        }

        $out['sys_descr'] = self::snmpGet($ip, $community, self::OID_SYS_DESCR);
        $out['sys_name']  = self::snmpGet($ip, $community, '1.3.6.1.2.1.1.5.0'); // sysName
        $out['serial']    = self::snmpGet($ip, $community, self::OID_SERIAL);

        // Probar cada OID candidato de contador
        foreach (self::pageCountCandidates() as $candidate) {
            $value = self::snmpGet($ip, $community, $candidate['oid']);

            $out['candidates'][] = [
                'label'   => $candidate['label'],
                'oid'     => $candidate['oid'],
                'value'   => $value,
                'numeric' => is_numeric($value),
            ];
        }

        return $out;
    }

    /**
     * SNMP WALK de un subárbol. Devuelve pares [oid => valor].
     * Útil para explorar qué expone la impresora.
     */
    public static function walk(string $ip, string $community, string $baseOid = '1.3.6.1.2.1.43'): array
    {
        $results = [];

        // Método 1: extensión php-snmp
        if (function_exists('snmprealwalk')) {
            try {
                $raw = @snmprealwalk($ip, $community, $baseOid, 2_000_000, 1);
                if (is_array($raw)) {
                    foreach ($raw as $oid => $value) {
                        $results[$oid] = self::cleanSnmpValue((string) $value);
                    }
                }
                return $results;
            } catch (\Throwable $e) {
                Log::debug("PrinterService: walk ext falló: " . $e->getMessage());
            }
        }

        // Método 2: binario snmpwalk
        if (self::commandExists('snmpwalk')) {
            $command = sprintf(
                'snmpwalk -v2c -c %s -t 2 -r 1 -On %s %s 2>/dev/null',
                escapeshellarg($community),
                escapeshellarg($ip),
                escapeshellarg($baseOid)
            );
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                foreach ($output as $line) {
                    // Formato: .1.3.6.1... = STRING: valor
                    if (str_contains($line, '=')) {
                        [$oid, $value] = explode('=', $line, 2);
                        $results[trim($oid)] = self::cleanSnmpValue(trim($value));
                    }
                }
            }
        }

        return $results;
    }

    /**
     * SNMP GET público, para usar desde el panel de diagnóstico con un OID arbitrario.
     */
    public static function get(string $ip, string $community, string $oid): ?string
    {
        return self::snmpGet($ip, $community, $oid);
    }
}
