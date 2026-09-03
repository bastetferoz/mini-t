<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\ActivityLogger;
use App\Services\ExchangeRateService;
use App\Services\InvoiceParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessInvoiceFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 15;
    public int $timeout = 120;

    public function __construct(
        public string $filePath,
    ) {}

    public function handle(): void
    {
        Log::info("ProcessInvoiceFile: Procesando {$this->filePath}");

        // Verificar que el archivo existe
        if (! Storage::disk('public')->exists($this->filePath)) {
            Log::error("ProcessInvoiceFile: Archivo no encontrado: {$this->filePath}");
            return;
        }

        // Analizar con IA
        $parsed = InvoiceParserService::parse($this->filePath);

        if (! $parsed) {
            $error = InvoiceParserService::$lastError ?? 'Error desconocido';
            Log::error("ProcessInvoiceFile: Error al analizar {$this->filePath}: {$error}");
            ActivityLogger::facturacion("❌ Cola: Error al cargar factura: " . basename($this->filePath) . " - {$error}");
            return;
        }

        $provider = InvoiceParserService::normalizeProvider($parsed['provider'] ?? null);
        $period = $parsed['period'] ?? now()->format('Y-m');

        // Determinar mes y año a partir de la FECHA DE EMISIÓN (invoice_date).
        // Criterio único para todas las facturas: el mes lo fija la fecha de emisión,
        // no el "period" que interpreta la IA (inconsistente entre facturas iguales).
        // Si no hay fecha de emisión, se usa el period como respaldo.
        $invoiceDate = $parsed['invoice_date'] ?? null;
        [$year, $month] = Invoice::deriveMonthYear($period, $invoiceDate);

        // Verificar duplicado por número de factura
        $invoiceNumber = $parsed['invoice_number'] ?? null;
        if ($invoiceNumber) {
            $duplicate = Invoice::where('invoice_number', $invoiceNumber)
                ->where('provider', $provider)
                ->exists();

            if ($duplicate) {
                Log::info("ProcessInvoiceFile: Duplicada omitida: {$provider} Nº {$invoiceNumber}");
                ActivityLogger::facturacion("⚠️ Cola: Factura duplicada omitida: {$provider} Nº {$invoiceNumber}");
                // Borrar el archivo temporal
                Storage::disk('public')->delete($this->filePath);
                return;
            }
        }

        // Organizar archivo
        $finalPath = InvoiceParserService::organizeFile($this->filePath, $parsed);

        // Crear factura
        $invoice = Invoice::create([
            'provider' => $provider,
            'company' => $parsed['company'] ?? null,
            'service' => $parsed['service'] ?? null,
            'reference' => $parsed['reference'] ?? null,
            'amount' => $parsed['amount'] ?? 0,
            'currency' => $parsed['currency'] ?? 'ARS',
            'invoice_date' => $invoiceDate ?? now()->toDateString(),
            'period' => $period,
            'month' => $month,
            'year' => $year,
            'invoice_number' => $invoiceNumber,
            'file_path' => $finalPath,
            'notes' => 'Cargada automáticamente con IA (cola)',
        ]);

        // Tipo de cambio
        if ($invoice->currency === 'ARS') {
            $rate = ExchangeRateService::getBnaRate($invoice->invoice_date->format('Y-m-d'));
            if ($rate) {
                $invoice->update([
                    'exchange_rate' => $rate,
                    'amount_usd' => round($invoice->amount / $rate, 2),
                ]);
            }
        } elseif ($invoice->currency === 'USD') {
            $invoice->update([
                'exchange_rate' => 1,
                'amount_usd' => $invoice->amount,
            ]);
        }

        Log::info("ProcessInvoiceFile: ✓ {$provider} | \${$parsed['amount']} | {$period}");
        ActivityLogger::facturacion("✓ Cola: Factura cargada: {$provider} | \${$parsed['amount']} | {$period}", $invoice);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ProcessInvoiceFile: Job falló para {$this->filePath}: " . $e->getMessage());
        ActivityLogger::facturacion("❌ Cola: Falló al procesar " . basename($this->filePath) . ": " . $e->getMessage());
    }
}
