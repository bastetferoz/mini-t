<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Obtiene el tipo de cambio BNA para una fecha dada.
     * Usa api.bluelytics.com.ar
     */
    public static function getBnaRate(string $date): ?float
    {
        try {
            $response = Http::timeout(5)
                ->get("https://api.bluelytics.com.ar/v2/historical", [
                    'day' => $date, // formato YYYY-MM-DD
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return (float) $data['oficial']['value_sell'] ?? null;
            }
        } catch (\Exception $e) {
            Log::warning("ExchangeRateService: no se pudo obtener cotización para {$date}: " . $e->getMessage());
        }

        return null;
    }
}