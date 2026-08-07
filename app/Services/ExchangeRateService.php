<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Obtiene el tipo de cambio BNA (venta) para una fecha específica.
     * Usa api.bluelytics.com.ar que trae datos oficiales del BNA.
     */
    public static function getBnaRate(string $date): ?float
    {
        try {
            $response = Http::timeout(5)
                ->get("https://api.bluelytics.com.ar/v2/historical", [
                    'day' => $date,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return (float) ($data['oficial']['value_sell'] ?? 0) ?: null;
            }
        } catch (\Throwable $e) {
            Log::warning("ExchangeRateService: error para {$date}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Obtiene el tipo de cambio BNA (venta) para un mes específico.
     * Usa el día 15 del mes como referencia. Cachea por 24 horas.
     *
     * @param int $year Ej: 2026
     * @param int $month Ej: 3
     * @return float|null
     */
    public static function getMonthlyRate(int $year, int $month): ?float
    {
        $cacheKey = "bna_rate_{$year}_{$month}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($year, $month) {
            // Intentar día 15, si falla probar otros días
            $attempts = [15, 14, 16, 13, 17, 10, 20, 1];

            foreach ($attempts as $day) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $rate = self::getBnaRate($date);

                if ($rate && $rate > 0) {
                    return $rate;
                }
            }

            return null;
        });
    }

    /**
     * Convierte un monto de ARS a USD usando la cotización del mes.
     */
    public static function convertToUsd(float $amountArs, int $year, int $month): ?float
    {
        $rate = self::getMonthlyRate($year, $month);

        if (! $rate || $rate <= 0) {
            return null;
        }

        return round($amountArs / $rate, 2);
    }
}
