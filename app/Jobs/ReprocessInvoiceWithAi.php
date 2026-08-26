<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\ActivityLogger;
use App\Services\InvoiceParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReprocessInvoiceWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 15;
    public int $timeout = 120;

    public function __construct(
        public int $invoiceId,
        public bool $reidentifyOnly = false,
    ) {}

    public function handle(): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || ! $invoice->file_path) {
            return;
        }

        Log::info("ReprocessInvoiceWithAi: Reprocesando factura #{$invoice->id} ({$invoice->provider})");

        if ($this->reidentifyOnly) {
            // Solo re-identificar proveedor (para facturas en "otro")
            $newProvider = InvoiceParserService::reidentifyProvider($invoice->file_path);

            if ($newProvider && $newProvider !== 'otro' && $newProvider !== $invoice->provider) {
                $invoice->update(['provider' => $newProvider]);
                Log::info("ReprocessInvoiceWithAi: #{$invoice->id} reclasificada a '{$newProvider}'");
            }
        } else {
            // Reprocesar datos completos (etapa 1 + 2)
            $parsed = InvoiceParserService::parse($invoice->file_path);

            if ($parsed) {
                $updates = array_filter([
                    'service' => $parsed['service'] ?? null,
                    'reference' => $parsed['reference'] ?? null,
                    'company' => $parsed['company'] ?? null,
                ], fn ($v) => $v !== null);

                if (! empty($updates)) {
                    $invoice->update($updates);
                    Log::info("ReprocessInvoiceWithAi: #{$invoice->id} actualizada: " . json_encode($updates));
                }
            } else {
                Log::warning("ReprocessInvoiceWithAi: #{$invoice->id} sin resultado: " . InvoiceParserService::$lastError);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ReprocessInvoiceWithAi: Job falló para factura #{$this->invoiceId}: " . $e->getMessage());
    }
}
