<?php

namespace App\Jobs;

use App\Models\ReturnShipment;
use App\Services\TrackingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateTrackingStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Execute the job.
     *
     * Consulta la API de EnvíoPack para todos los envíos activos
     * y actualiza el estado de tracking en la base de datos.
     */
    public function handle(TrackingService $trackingService): void
    {
        if (! $trackingService->isConfigured()) {
            Log::info('UpdateTrackingStatus: Credenciales de EnvíoPack no configuradas, salteando.');
            return;
        }

        // Obtener todos los envíos que todavía no están en estado final
        $shipments = ReturnShipment::query()
            ->where('logistics_method', 'enviopack')
            ->whereNotNull('tracking_number')
            ->whereNotIn('tracking_status', ['delivered', 'returned', 'cancelled'])
            ->get();

        if ($shipments->isEmpty()) {
            Log::info('UpdateTrackingStatus: No hay envíos pendientes de actualización.');
            return;
        }

        $updated = 0;
        $errors = 0;

        foreach ($shipments as $shipment) {
            try {
                $result = $trackingService->track($shipment->tracking_number);

                if (! $result) {
                    $errors++;
                    continue;
                }

                $shipment->update([
                    'tracking_status' => $result['status'],
                    'tracking_payload' => [
                        'events' => $result['events'],
                        'status_raw' => $result['status_raw'] ?? null,
                    ],
                    'last_update' => now(),
                ]);

                $updated++;

                // Pequeña pausa entre requests para no saturar la API
                usleep(500_000); // 500ms
            } catch (\Throwable $e) {
                $errors++;
                Log::warning("UpdateTrackingStatus: Error al actualizar envío #{$shipment->id}", [
                    'tracking_number' => $shipment->tracking_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("UpdateTrackingStatus: Finalizó. Actualizados: {$updated}, Errores: {$errors}, Total: {$shipments->count()}");
    }
}
