<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    protected string $apiKey;
    protected string $secretKey;
    protected string $baseUrl = 'https://api.enviopack.com';
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->apiKey = (string) config('services.enviopack.api_key');
        $this->secretKey = (string) config('services.enviopack.secret_key');
    }

    /**
     * Obtiene un access_token mediante OAuth (API Key + Secret Key).
     */
    protected function authenticate(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        // Intentar obtener del cache
        $cached = Cache::get('enviopack_access_token');
        if ($cached) {
            $this->accessToken = $cached;
            return $this->accessToken;
        }

        if (empty($this->apiKey) || empty($this->secretKey)) {
            Log::warning('TrackingService: Credenciales de EnvíoPack no configuradas.');
            return null;
        }

        try {
            $response = Http::asForm()->post("{$this->baseUrl}/auth", [
                'api-key' => $this->apiKey,
                'secret-key' => $this->secretKey,
            ]);

            if ($response->successful()) {
                $token = $response->json('token');
                // Cachear por 50 minutos (el token dura 60 min normalmente)
                Cache::put('enviopack_access_token', $token, now()->addMinutes(50));
                $this->accessToken = $token;
                return $this->accessToken;
            }

            Log::error('TrackingService: Error de autenticación EnvíoPack', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('TrackingService: Excepción al autenticar', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Consulta el estado de un envío por número de tracking.
     *
     * Retorna un array normalizado:
     * [
     *   'status' => 'En tránsito',
     *   'last_update' => Carbon,
     *   'events' => [
     *     ['date' => '...', 'status' => '...', 'location' => '...'],
     *   ],
     * ]
     */
    public function track(string $trackingNumber): ?array
    {
        $token = $this->authenticate();

        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->timeout(15)->get("{$this->baseUrl}/envios/{$trackingNumber}");

            if (!$response->successful()) {
                Log::warning("TrackingService: Error al consultar envío {$trackingNumber}", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $data = $response->json();

            return $this->normalizeResponse($data);
        } catch (\Throwable $e) {
            Log::error("TrackingService: Excepción al consultar envío {$trackingNumber}", [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normaliza la respuesta de la API de EnvíoPack a nuestro formato interno.
     */
    protected function normalizeResponse(array $data): array
    {
        $events = [];

        // EnvíoPack devuelve los eventos en un array "tracking" o "historial"
        $rawEvents = $data['tracking'] ?? $data['historial'] ?? $data['eventos'] ?? [];

        foreach ($rawEvents as $event) {
            $events[] = [
                'date' => $event['fecha'] ?? $event['date'] ?? $event['created_at'] ?? null,
                'status' => $event['estado'] ?? $event['status'] ?? $event['descripcion'] ?? 'Actualización',
                'location' => $event['ubicacion'] ?? $event['location'] ?? $event['sucursal'] ?? null,
            ];
        }

        // Determinar el estado actual
        $currentStatus = $data['estado'] ?? $data['status'] ?? null;

        if (!$currentStatus && !empty($events)) {
            $currentStatus = $events[0]['status'] ?? 'Desconocido';
        }

        // Normalizar el estado a nuestros valores internos
        $normalizedStatus = $this->normalizeStatus($currentStatus);

        return [
            'status' => $normalizedStatus,
            'status_raw' => $currentStatus,
            'last_update' => $data['ultima_actualizacion'] ?? $data['updated_at'] ?? now()->toIso8601String(),
            'events' => $events,
        ];
    }

    /**
     * Mapea estados de EnvíoPack a estados internos del sistema.
     */
    protected function normalizeStatus(?string $status): string
    {
        if (!$status) {
            return 'pending_tracking';
        }

        $status = mb_strtolower(trim($status));

        // Mapeo de estados comunes de EnvíoPack
        return match (true) {
            str_contains($status, 'entregado') => 'delivered',
            str_contains($status, 'en camino'),
            str_contains($status, 'en tránsito'),
            str_contains($status, 'transito'),
            str_contains($status, 'en viaje') => 'in_transit',
            str_contains($status, 'en planta'),
            str_contains($status, 'en sucursal'),
            str_contains($status, 'en distribución') => 'in_transit',
            str_contains($status, 'retirado'),
            str_contains($status, 'retiro') => 'picked_up',
            str_contains($status, 'devuelto'),
            str_contains($status, 'devolucion') => 'returned',
            str_contains($status, 'cancelado') => 'cancelled',
            str_contains($status, 'pendiente'),
            str_contains($status, 'creado') => 'pending_tracking',
            default => 'in_transit',
        };
    }

    /**
     * Retorna el label en español del estado normalizado.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_tracking' => 'Pendiente',
            'picked_up' => 'Retirado',
            'in_transit' => 'En tránsito',
            'delivered' => 'Entregado',
            'returned' => 'Devuelto',
            'cancelled' => 'Cancelado',
            default => ucfirst($status),
        };
    }

    /**
     * Retorna el color badge para Filament.
     */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending_tracking' => 'gray',
            'picked_up' => 'info',
            'in_transit' => 'warning',
            'delivered' => 'success',
            'returned' => 'danger',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Verifica si las credenciales están configuradas.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->secretKey);
    }
}
