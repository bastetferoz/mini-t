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
                'date' => $event['fecha'] ?? null,
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
            'last_update' => $lastEvent['fecha'] ?? now()->toIso8601String(),
            'events' => $events,
        ];
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
