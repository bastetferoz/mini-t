<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Cliente XML-RPC para Odoo 15.
 *
 * Autentica contra /xmlrpc/2/common y ejecuta métodos de modelos vía
 * /xmlrpc/2/object. Implementa el (de)serializado XML-RPC a mano para no
 * depender de la extensión php-xmlrpc (removida del core moderno).
 *
 * Config en config/services.php → 'odoo' (ODOO_URL, ODOO_DB, ODOO_USERNAME, ODOO_PASSWORD).
 */
class OdooService
{
    public static ?string $lastError = null;

    protected string $url;
    protected string $db;
    protected string $username;
    protected string $password;
    protected ?int $uid = null;

    public function __construct()
    {
        $this->url = rtrim((string) config('services.odoo.url'), '/');
        $this->db = (string) config('services.odoo.db');
        $this->username = (string) config('services.odoo.username');
        $this->password = (string) config('services.odoo.password');
    }

    public function isConfigured(): bool
    {
        // La DB no es obligatoria: si está vacía, se autodetecta desde el servidor.
        return $this->url !== '' && $this->username !== '' && $this->password !== '';
    }

    /** Devuelve la versión del servidor Odoo (no requiere credenciales). */
    public function version(): ?array
    {
        return $this->call("{$this->url}/xmlrpc/2/common", 'version', []);
    }

    /** Lista las bases de datos del servidor Odoo (no requiere credenciales). */
    public function listDatabases(): array
    {
        $res = $this->call("{$this->url}/xmlrpc/2/db", 'list', []);
        return is_array($res) ? $res : [];
    }

    /**
     * Devuelve la base a usar. Si ODOO_DB está vacío, la autodetecta:
     * si el servidor tiene una sola base, usa esa.
     */
    protected function resolveDb(): ?string
    {
        if ($this->db !== '') {
            return $this->db;
        }

        $dbs = $this->listDatabases();
        if (count($dbs) === 1) {
            $this->db = (string) $dbs[0];
            return $this->db;
        }

        if (count($dbs) === 0) {
            self::$lastError = 'No se pudo obtener la base de datos de Odoo (¿URL correcta?).';
        } else {
            self::$lastError = 'El servidor Odoo tiene varias bases (' . implode(', ', $dbs) . '). Especificá ODOO_DB en el .env.';
        }
        return null;
    }

    /** Autentica y guarda el uid. Devuelve el uid o null si falla. */
    public function authenticate(): ?int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        if (! $this->isConfigured()) {
            self::$lastError = 'Odoo no está configurado (ODOO_URL, ODOO_USERNAME, ODOO_PASSWORD).';
            return null;
        }

        $db = $this->resolveDb();
        if (! $db) {
            return null; // lastError ya seteado
        }

        $result = $this->call("{$this->url}/xmlrpc/2/common", 'authenticate', [
            $db, $this->username, $this->password, [],
        ]);

        // authenticate devuelve el uid (int) o false si las credenciales son inválidas.
        if (is_int($result) && $result > 0) {
            $this->uid = $result;
            return $this->uid;
        }

        self::$lastError = 'Autenticación con Odoo fallida. Revisá usuario/contraseña/base.';
        return null;
    }

    /**
     * Ejecuta un método sobre un modelo de Odoo (execute_kw).
     * Ej: execute('res.partner', 'search_read', [[['supplier_rank','>',0]]], ['fields'=>['name'],'limit'=>5])
     */
    public function execute(string $model, string $method, array $args = [], array $kwargs = [])
    {
        $uid = $this->authenticate();
        if (! $uid) {
            return null;
        }

        return $this->call("{$this->url}/xmlrpc/2/object", 'execute_kw', [
            $this->db,
            $uid,
            $this->password,
            $model,
            $method,
            $args,
            $kwargs,
        ]);
    }

    /** Atajo para search_read. */
    public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 0): ?array
    {
        $kwargs = ['fields' => $fields];
        if ($limit > 0) {
            $kwargs['limit'] = $limit;
        }

        $res = $this->execute($model, 'search_read', [$domain], $kwargs);

        return is_array($res) ? $res : null;
    }

    /** Devuelve la definición de campos de un modelo (para entender el mapeo). */
    public function fields(string $model, array $attributes = ['string', 'type', 'required']): ?array
    {
        $res = $this->execute($model, 'fields_get', [], ['attributes' => $attributes]);

        return is_array($res) ? $res : null;
    }

    /** Crea un registro. Devuelve el id creado o null si falla. */
    public function create(string $model, array $values): ?int
    {
        $res = $this->execute($model, 'create', [$values]);

        return is_int($res) ? $res : null;
    }

    /** Lee registros por id. */
    public function read(string $model, array $ids, array $fields = []): ?array
    {
        $res = $this->execute($model, 'read', [$ids], ['fields' => $fields]);

        return is_array($res) ? $res : null;
    }

    /**
     * Busca en Odoo una factura de proveedor ya existente que corresponda a esta
     * factura de Mini-T, matcheando por número de documento (ref o
     * l10n_latam_document_number) y, si el proveedor tiene partner configurado,
     * también por partner. Devuelve el id del account.move o null.
     */
    public function findExistingMove(\App\Models\Invoice $invoice): ?int
    {
        $numero = trim((string) $invoice->invoice_number);
        if ($numero === '') {
            return null;
        }

        // Partner del proveedor (si está configurado), para acotar la búsqueda.
        $provider = \App\Models\InvoiceProvider::where('slug', $invoice->provider)->first();
        $partnerId = $provider?->odoo_partner_id;

        // Buscar facturas de proveedor cuyo número (ref o document_number) coincida.
        $domain = [
            ['move_type', '=', 'in_invoice'],
            '|',
            ['ref', '=', $numero],
            ['l10n_latam_document_number', '=', $numero],
        ];
        if ($partnerId) {
            $domain[] = ['partner_id', '=', (int) $partnerId];
        }

        $res = $this->searchRead('account.move', $domain, ['id'], 1);

        return $res[0]['id'] ?? null;
    }

    /** Diario de compras (facturas de proveedor) por defecto. */
    public const PURCHASE_JOURNAL_ID = 11;

    // ─── Opciones para los desplegables de configuración (id => nombre) ───

    /** Productos (para el desplegable). Filtra por texto opcional. */
    public function productOptions(string $search = '', int $limit = 40): array
    {
        $domain = [['sale_ok', '=', false]]; // no filtramos duro; traemos varios
        if ($search !== '') {
            $domain = ['|', ['name', 'ilike', $search], ['default_code', 'ilike', $search]];
        }
        $res = $this->searchRead('product.product', $domain, ['id', 'name', 'default_code'], $limit) ?? [];
        $out = [];
        foreach ($res as $p) {
            $code = $p['default_code'] ? "[{$p['default_code']}] " : '';
            $out[$p['id']] = $code . $p['name'];
        }
        return $out;
    }

    /** Impuestos de compra (para el desplegable). */
    public function purchaseTaxOptions(): array
    {
        $res = $this->searchRead('account.tax', [['type_tax_use', '=', 'purchase']], ['id', 'name', 'amount'], 80) ?? [];
        $out = [];
        foreach ($res as $t) {
            $out[$t['id']] = $t['name'];
        }
        return $out;
    }

    /** Tipos de documento (para el desplegable). */
    public function documentTypeOptions(): array
    {
        $res = $this->searchRead('l10n_latam.document.type', [], ['id', 'name'], 80) ?? [];
        $out = [];
        foreach ($res as $d) {
            $out[$d['id']] = $d['name'];
        }
        return $out;
    }

    /** Búsqueda de proveedores (para el desplegable con búsqueda). */
    public function partnerOptions(string $search = '', int $limit = 30): array
    {
        $domain = [['supplier_rank', '>', 0]];
        if ($search !== '') {
            $domain = ['&', ['supplier_rank', '>', 0], ['name', 'ilike', $search]];
        }
        $res = $this->searchRead('res.partner', $domain, ['id', 'name', 'vat'], $limit) ?? [];
        $out = [];
        foreach ($res as $p) {
            $vat = $p['vat'] ? " ({$p['vat']})" : '';
            $out[$p['id']] = $p['name'] . $vat;
        }
        return $out;
    }

    /** Etiqueta de un partner por id (para mostrar el ya seleccionado). */
    public function partnerLabel(int $id): ?string
    {
        $res = $this->read('res.partner', [$id], ['name']);
        return $res[0]['name'] ?? null;
    }

    /** Búsqueda de cuentas contables (para el desplegable). */
    public function accountOptions(string $search = '', int $limit = 40): array
    {
        $domain = [];
        if ($search !== '') {
            $domain = ['|', ['name', 'ilike', $search], ['code', 'ilike', $search]];
        }
        $res = $this->searchRead('account.account', $domain, ['id', 'name', 'code'], $limit) ?? [];
        $out = [];
        foreach ($res as $a) {
            $out[$a['id']] = trim(($a['code'] ? $a['code'] . ' ' : '') . $a['name']);
        }
        return $out;
    }

    /** Etiqueta de una cuenta por id (para mostrar la ya seleccionada). */
    public function accountLabel(int $id): ?string
    {
        $res = $this->read('account.account', [$id], ['name', 'code']);
        if (! $res) {
            return null;
        }
        return trim(($res[0]['code'] ? $res[0]['code'] . ' ' : '') . $res[0]['name']);
    }

    /** Devuelve el id de una moneda por su código (ej: USD, ARS). */
    public function currencyId(string $code): ?int
    {
        $res = $this->searchRead('res.currency', [['name', '=', strtoupper($code)]], ['id'], 1);

        return $res[0]['id'] ?? null;
    }

    /**
     * Crea en Odoo (borrador) la factura de proveedor correspondiente a una
     * factura de Mini-T, usando la config Odoo de su proveedor.
     * Devuelve el id de account.move creado o null (con lastError seteado).
     */
    /**
     * Resuelve la referencia que se enviará a Odoo para una factura, según la
     * plantilla configurada en su proveedor (texto libre con variables).
     * Si el proveedor no tiene plantilla, usa el número de factura.
     */
    public static function resolveReference(\App\Models\Invoice $invoice, ?\App\Models\InvoiceProvider $provider = null): string
    {
        $provider = $provider ?: \App\Models\InvoiceProvider::where('slug', $invoice->provider)->first();

        $plantilla = trim((string) ($provider->odoo_ref_source ?? ''));

        if ($plantilla === '') {
            return $invoice->invoice_number ?: ($provider->name ?? $invoice->provider);
        }

        return strtr($plantilla, [
            '{numero}'     => (string) ($invoice->invoice_number ?? ''),
            '{servicio}'   => (string) ($invoice->service ?? ''),
            '{referencia}' => (string) ($invoice->reference ?? ''),
            '{dominio}'    => (string) ($invoice->reference ?? ''),
            '{periodo}'    => (string) ($invoice->period ?? ''),
            '{proveedor}'  => (string) ($provider->name ?? $invoice->provider),
        ]);
    }

    public function pushInvoice(\App\Models\Invoice $invoice): ?int
    {
        self::$lastError = null;

        // Config del proveedor
        $provider = \App\Models\InvoiceProvider::where('slug', $invoice->provider)->first();

        if (! $provider) {
            self::$lastError = "El proveedor '{$invoice->provider}' no está configurado en Mini-T.";
            return null;
        }

        if (! $provider->odoo_partner_id || ! $provider->odoo_product_id) {
            self::$lastError = "Falta configurar Odoo para '{$provider->name}' (proveedor y producto en Odoo).";
            return null;
        }

        // Moneda
        $currencyId = $this->currencyId($invoice->currency ?: 'ARS');
        if (! $currencyId) {
            self::$lastError = "No se encontró la moneda '{$invoice->currency}' en Odoo.";
            return null;
        }

        // Fecha de factura = fecha de emisión. Fecha contable = hoy (día de carga).
        $fechaEmision = $invoice->invoice_date
            ? $invoice->invoice_date->format('Y-m-d')
            : now()->toDateString();
        $fechaContable = now()->format('Y-m-d');

        // Línea con impuestos (uno o varios). Prioriza la lista odoo_tax_ids;
        // si no hay, cae al campo viejo odoo_tax_id por compatibilidad.
        $taxIds = [];
        if (! empty($provider->odoo_tax_ids) && is_array($provider->odoo_tax_ids)) {
            $taxIds = array_map('intval', $provider->odoo_tax_ids);
        } elseif ($provider->odoo_tax_id) {
            $taxIds = [(int) $provider->odoo_tax_id];
        }

        $line = [
            'product_id' => (int) $provider->odoo_product_id,
            'quantity'   => 1,
            'price_unit' => (float) $invoice->amount,
        ];
        if (! empty($taxIds)) {
            $line['tax_ids'] = [[6, 0, $taxIds]];
        }
        // Cuenta contable: solo si el proveedor la definió; si no, Odoo usa la del producto.
        if ($provider->odoo_account_id) {
            $line['account_id'] = (int) $provider->odoo_account_id;
        }

        // Referencia que irá a Odoo (según la plantilla del proveedor).
        $ref = self::resolveReference($invoice, $provider);

        $values = [
            'move_type'    => 'in_invoice',
            'partner_id'   => (int) $provider->odoo_partner_id,
            'journal_id'   => self::PURCHASE_JOURNAL_ID,
            'currency_id'  => $currencyId,
            'invoice_date' => $fechaEmision,           // Fecha de factura (emisión)
            'date'         => $fechaContable,          // Fecha contable (día de carga)
            'ref'          => $ref,
            'invoice_line_ids' => [[0, 0, $line]],
        ];

        // Número de documento = número de la factura. Se deja el tipo de documento
        // por defecto de Odoo (no se fuerza).
        if ($invoice->invoice_number) {
            $values['l10n_latam_document_number'] = $invoice->invoice_number;
        }

        $id = $this->create('account.move', $values);

        if (! $id) {
            // lastError ya viene seteado por create/call
            return null;
        }

        // Guardar el vínculo en Mini-T
        $invoice->update([
            'odoo_move_id'   => $id,
            'odoo_synced_at' => now(),
        ]);

        // Adjuntar la copia de la factura (PDF) a la factura de Odoo, si existe.
        $this->attachInvoiceFile($invoice, $id);

        return $id;
    }

    /**
     * Sube el archivo (PDF/imagen) de la factura de Mini-T como adjunto de la
     * factura de Odoo (ir.attachment vinculado al account.move).
     */
    protected function attachInvoiceFile(\App\Models\Invoice $invoice, int $moveId): void
    {
        if (! $invoice->file_path) {
            return;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($invoice->file_path)) {
            return;
        }

        try {
            $content = $disk->get($invoice->file_path);
            $filename = basename($invoice->file_path);

            $this->create('ir.attachment', [
                'name'      => $filename,
                'res_model' => 'account.move',
                'res_id'    => $moveId,
                'type'      => 'binary',
                'datas'     => base64_encode($content),
                'mimetype'  => str_ends_with(strtolower($filename), '.pdf') ? 'application/pdf' : 'application/octet-stream',
            ]);
        } catch (\Throwable $e) {
            \Log::warning("OdooService: no se pudo adjuntar el archivo de la factura #{$invoice->id}: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  XML-RPC de bajo nivel (sin extensión php-xmlrpc)
    // ─────────────────────────────────────────────────────────────

    protected function call(string $endpoint, string $method, array $params)
    {
        self::$lastError = null;

        $body = $this->buildRequest($method, $params);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            self::$lastError = "Error de conexión con Odoo: {$err}";
            Log::error('OdooService: ' . self::$lastError);
            return null;
        }

        if ($code !== 200) {
            self::$lastError = "Odoo devolvió HTTP {$code}";
            Log::error('OdooService: ' . self::$lastError . ' | ' . substr($raw, 0, 300));
            return null;
        }

        // ¿Es una respuesta de fault (error de Odoo)?
        if (str_contains($raw, '<fault>')) {
            $msg = $this->extractFaultString($raw);
            self::$lastError = "Odoo fault: {$msg}";
            Log::error('OdooService: ' . self::$lastError);
            return null;
        }

        return $this->parseResponse($raw);
    }

    protected function buildRequest(string $method, array $params): string
    {
        $paramsXml = '';
        foreach ($params as $p) {
            $paramsXml .= '<param>' . $this->encodeValue($p) . '</param>';
        }

        return '<?xml version="1.0"?><methodCall><methodName>'
            . htmlspecialchars($method)
            . '</methodName><params>' . $paramsXml . '</params></methodCall>';
    }

    protected function encodeValue($v): string
    {
        if (is_int($v)) {
            return "<value><int>{$v}</int></value>";
        }
        if (is_bool($v)) {
            return '<value><boolean>' . ($v ? '1' : '0') . '</boolean></value>';
        }
        if (is_float($v)) {
            return "<value><double>{$v}</double></value>";
        }
        if (is_array($v)) {
            // Array asociativo → struct; array secuencial → array
            $isAssoc = array_keys($v) !== range(0, count($v) - 1);
            if ($isAssoc) {
                $members = '';
                foreach ($v as $k => $val) {
                    $members .= '<member><name>' . htmlspecialchars((string) $k) . '</name>'
                        . $this->encodeValue($val) . '</member>';
                }
                return "<value><struct>{$members}</struct></value>";
            }
            $items = '';
            foreach ($v as $val) {
                $items .= $this->encodeValue($val);
            }
            return "<value><array><data>{$items}</data></array></value>";
        }

        return '<value><string>' . htmlspecialchars((string) $v) . '</string></value>';
    }

    protected function parseResponse(string $raw)
    {
        $xml = @simplexml_load_string($raw);
        if ($xml === false) {
            self::$lastError = 'Respuesta XML-RPC inválida de Odoo.';
            return null;
        }

        $valueNode = $xml->params->param->value ?? null;
        if ($valueNode === null) {
            return null;
        }

        return $this->decodeValue($valueNode);
    }

    protected function decodeValue(\SimpleXMLElement $value)
    {
        // El nodo <value> tiene un hijo tipado (int, string, struct, array, etc.)
        foreach ($value->children() as $child) {
            $type = $child->getName();

            switch ($type) {
                case 'int':
                case 'i4':
                    return (int) $child;
                case 'boolean':
                    return ((string) $child) === '1';
                case 'double':
                    return (float) $child;
                case 'string':
                    return (string) $child;
                case 'array':
                    $out = [];
                    foreach ($child->data->value as $item) {
                        $out[] = $this->decodeValue($item);
                    }
                    return $out;
                case 'struct':
                    $out = [];
                    foreach ($child->member as $member) {
                        $out[(string) $member->name] = $this->decodeValue($member->value);
                    }
                    return $out;
            }
        }

        // <value> sin tipo explícito = string
        return (string) $value;
    }

    protected function extractFaultString(string $raw): string
    {
        if (preg_match('/<name>faultString<\/name>\s*<value>\s*<string>(.*?)<\/string>/s', $raw, $m)) {
            return trim(html_entity_decode($m[1]));
        }
        return 'error desconocido';
    }
}
