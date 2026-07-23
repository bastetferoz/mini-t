<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    protected string $baseUrl = 'https://api.enviopack.com/tracking';

    /**
     * Consulta el estado de un envío por número de tracking.
     * Endpoint público, no requiere credenciales.
     */
    public function track(string $trackingNumber): ?array
    {
        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/{$trackingNumber}");

            if (! $response->successful()) {
                Log::warning("TrackingService: Error al consultar {$trackingNumber}", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $data = $response->json();

            // La API devuelve un array, tomamos el primer resultado
            if (is_array($data) && isset($data[0])) {
                $data = $data[0];
            }

            if (empty($data) || ! isset($data['tracking'])) {
                return null;
            }

            return $this->normalizeResponse($data);
        } catch (\Throwable $e) {
            Log::error("TrackingService: Excepción al consultar {$trackingNumber}", [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normaliza la respuesta de EnvíoPack.
     *
     * Formato de entrada:
     * {
     *   "tracking_number": "2035EP013090165R",
     *   "correo": {"id": "urbano", "nombre": "Urbano"},
     *   "localidad": "Mar del Plata",
     *   "provincia": "Buenos Aires",
     *   "tracking": [
     *     {"fecha": "14 de julio 10:20", "mensaje": "...", "codigo": "INLD"},
     *     ...
     *   ],
     *   "fecha_estimada_de_entrega": "05/08/2026"
     * }
     */
    protected function normalizeResponse(array $data): array
    {
        $events = [];
        $rawEvents = $data['tracking'] ?? [];

        foreach ($rawEvents as $event) {
            $events[] = [
                'date' => $this->parseEnviopackDate($event['fecha'] ?? null),
                'status' => $event['mensaje'] ?? $event['codigo'] ?? 'Actualización',
                'location' => $data['localidad'] ?? null,
                'code' => $event['codigo'] ?? null,
            ];
        }

        // El último evento es el estado actual
        $lastEvent = end($rawEvents);
        $currentCode = $lastEvent['codigo'] ?? null;

        return [
            'status' => $this->normalizeStatus($currentCode, $lastEvent['mensaje'] ?? null),
            'status_raw' => $lastEvent['mensaje'] ?? $currentCode,
            'carrier' => $data['correo']['nombre'] ?? 'EnvíoPack',
            'destination' => trim(($data['localidad'] ?? '') . ', ' . ($data['provincia'] ?? ''), ', '),
            'estimated_delivery' => $data['fecha_estimada_de_entrega'] ?? null,
            'last_update' => $this->parseEnviopackDate($lastEvent['fecha'] ?? null) ?? now()->toIso8601String(),
            'events' => $events,
        ];
    }

    /**
     * Convierte fechas en formato español de EnvíoPack al formato ISO 8601.
     *
     * EnvíoPack devuelve fechas como:
     *   - "14 de julio 10:20"       → sin año, asume año actual
     *   - "14 de julio 2025 10:20"  → con año explícito
     *   - "05/08/2026"              → fecha simple sin hora
     *
     * Retorna null si el valor es nulo o no puede parsearse.
     */
    protected function parseEnviopackDate(?string $fecha): ?string
    {
        if (empty($fecha)) {
            return null;
        }

        static $months = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03',
            'abril' => '04', 'mayo' => '05', 'junio' => '06',
            'julio' => '07', 'agosto' => '08', 'septiembre' => '09',
            'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
        ];

        // Formato: "14 de julio 10:20" o "14 de julio 2025 10:20"
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)(?:\s+(\d{4}))?\s+(\d{1,2}:\d{2})/iu', $fecha, $m)) {
            $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = $months[mb_strtolower($m[2])] ?? null;
            $year  = $m[3] ?: now()->year;
            $time  = $m[4];

            if ($month) {
                return "{$year}-{$month}-{$day} {$time}:00";
            }
        }

        // Formato: "14 de julio 2025" (sin hora)
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)\s+(\d{4})/iu', $fecha, $m)) {
            $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = $months[mb_strtolower($m[2])] ?? null;
            $year  = $m[3];

            if ($month) {
                return "{$year}-{$month}-{$day} 00:00:00";
            }
        }

        // Formato: "05/08/2026" (dd/mm/yyyy)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]} 00:00:00";
        }

        // Último recurso: intentar Carbon con silencio
        try {
            return \Carbon\Carbon::parse($fecha)->toIso8601String();
        } catch (\Throwable) {
            Log::warning("TrackingService: no se pudo parsear fecha '{$fecha}'");
            return null;
        }
    }

    /**
     * Mapea códigos de EnvíoPack a estados internos.
     *
     * Códigos conocidos:
     * INLD = Ingresado / Listo para despacho
     * COLE = Colectado (el correo lo tiene)
     * RETI = Retirado
     * ENTR = Entregado
     * DEVU = Devuelto
     * ENVI = Enviado / En tránsito
     * REPO = En reparto
     */
    protected function normalizeStatus(?string $code, ?string $message): string
    {
        if ($code) {
            return match (strtoupper($code)) {
                'INLD' => 'pending_tracking',
                'COLE' => 'picked_up',
                'RETI' => 'picked_up',
                'ENVI' => 'in_transit',
                'REPO' => 'in_transit',
                'ENTR' => 'delivered',
                'DEVU' => 'returned',
                default => 'in_transit',
            };
        }

        // Fallback por mensaje
        if ($message) {
            $msg = mb_strtolower($message);
            return match (true) {
                str_contains($msg, 'entregado') => 'delivered',
                str_contains($msg, 'retirado') => 'picked_up',
                str_contains($msg, 'tránsito'), str_contains($msg, 'reparto') => 'in_transit',
                str_contains($msg, 'devuelto') => 'returned',
                default => 'in_transit',
            };
        }

        return 'pending_tracking';
    }

    /**
     * Label en español del estado.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_tracking' => 'Pendiente',
            'pickup_scheduled' => 'Retiro programado',
            'picked_up' => 'Retirado',
            'in_transit' => 'En tránsito',
            'delivered' => 'Entregado',
            'returned' => 'Devuelto',
            'cancelled' => 'Cancelado',
            default => ucfirst($status),
        };
    }

    /**
     * Color badge para Filament.
     */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending_tracking' => 'gray',
            'pickup_scheduled' => 'info',
            'picked_up' => 'info',
            'in_transit' => 'warning',
            'delivered' => 'success',
            'returned' => 'danger',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /**
     * No requiere credenciales — endpoint público.
     */
    public function isConfigured(): bool
    {
        return true;
    }
}
