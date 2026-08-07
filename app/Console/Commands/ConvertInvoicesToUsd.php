<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class ConvertInvoicesToUsd extends Command
{
    protected $signature = 'invoices:convert-usd';

    protected $description = 'Convierte facturas ARS a USD usando cotización BNA y guarda el resultado.';

    public function handle(): void
    {
        // Facturas ARS sin conversión guardada
        $invoices = Invoice::where('currency', 'ARS')
            ->whereNull('amount_usd')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No hay facturas ARS pendientes de conversión.');
            return;
        }

        $this->info("Convirtiendo {$invoices->count()} facturas...");
        $converted = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $rate = ExchangeRateService::getMonthlyRate($invoice->year, $invoice->month);

            if (! $rate) {
                $this->warn("  ✗ {$invoice->provider} ({$invoice->year}-{$invoice->month}): no se pudo obtener cotización");
                $failed++;
                continue;
            }

            $amountUsd = round($invoice->amount / $rate, 2);

            $invoice->update([
                'exchange_rate' => $rate,
                'amount_usd' => $amountUsd,
            ]);

            $converted++;
            $this->line("  ✓ {$invoice->provider} | \${$invoice->amount} ARS ÷ {$rate} = USD {$amountUsd}");
        }

        // Las que son USD y no tienen amount_usd, simplemente copiar
        $usdInvoices = Invoice::where('currency', 'USD')
            ->whereNull('amount_usd')
            ->get();

        foreach ($usdInvoices as $invoice) {
            $invoice->update([
                'exchange_rate' => 1,
                'amount_usd' => $invoice->amount,
            ]);
            $converted++;
        }

        $this->info("Listo. Convertidas: {$converted} | Fallidas: {$failed}");
    }
}
