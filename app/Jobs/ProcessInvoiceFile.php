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

    // Muchos reintentos: los límites de Gemini (429/503) son temporales,
    // así que preferimos esperar y reintentar antes que perder la factura.
    public int $tries = 8;
    public int $timeout = 120;

    // Cuánto esperar (segundos) antes de reintentar tras un límite/saturación de IA.
    private const AI_RETRY_DELAY = 60;

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

            // ¿Es un límite/saturación temporal de la IA? (429 rate limit, 5xx del servidor)
            // En ese caso NO perdemos la factura: reencolamos el job con espera para
            // reintentar más tarde. Así, aunque entren muchas de golpe, todas terminan
            // procesándose sin que Gemini se queje de forma terminal.
            $esLimiteTemporal = (bool) preg_match('/\b(429|500|502|503|504)\b/', $error)
                || stripos($error, 'quota') !== false
                || stripos($error, 'rate') !== false
                || stripos($error, 'timed out') !== false
                || stripos($error, 'overloaded') !== false
                || stripos($error, 'unavailable') !== false;

            if ($esLimiteTemporal && $this->attempts() < $this->tries) {
                Log::warning("ProcessInvoiceFile: Límite temporal de IA en {$this->filePath} (intento {$this->attempts()}/{$this->tries}). Reintentando en " . self::AI_RETRY_DELAY . "s. Error: {$error}");
                // Reencolar este mismo job con espera; no cuenta como fallo definitivo.
                $this->release(self::AI_RETRY_DELAY);
                return;
            }

            Log::error("ProcessInvoiceFile: Error al analizar {$this->filePath}: {$error}");
            ActivityLogger::facturacion("❌ Cola: Error al cargar factura: " . basename($this->filePath) . " - {$error}");
            return;
        }

        $provider = InvoiceParserService::normalizeProvider($parsed['provider'] ?? null);
        $period = $parsed['period'] ?? now()->format('Y-m');

        // Determinar mes y año. Se prioriza el PERÍODO DE SERVICIO que declara la
        // factura (period), y si no hay uno válido se usa la fecha de emisión.
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

        // Organizar archivo usando el provider/year/month REALES de la factura
        // (no los que dedujo el parser), para que quede en la carpeta correcta.
        $finalPath = InvoiceParserService::organizeFile($this->filePath, $parsed, $provider, $year, $month);

        // NO crear la factura si el archivo no quedó guardado: evita registros
        // huérfanos que apuntan a un PDF inexistente (403 al abrirlos).
        if (! $finalPath) {
            $error = InvoiceParserService::$lastError ?? 'No se pudo guardar el archivo';
            Log::error("ProcessInvoiceFile: {$this->filePath} no se guardó, no se crea la factura: {$error}");
            ActivityLogger::facturacion("❌ Cola: No se pudo guardar el archivo de " . basename($this->filePath) . " - {$error}");
            return;
        }

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
