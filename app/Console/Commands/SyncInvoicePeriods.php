<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class SyncInvoicePeriods extends Command
{
    protected $signature = 'invoices:sync-periods {--dry-run : Solo muestra los cambios sin aplicarlos}';

    protected $description = 'Resincroniza month/year de las facturas según su período de servicio (period) o, si no hay, su fecha de emisión. Corrige facturas mal ubicadas en el análisis.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $invoices = Invoice::all();
        $fixed = 0;

        foreach ($invoices as $invoice) {
            $invoiceDate = $invoice->invoice_date instanceof \DateTimeInterface
                ? $invoice->invoice_date->format('Y-m-d')
                : $invoice->invoice_date;

            [$year, $month] = Invoice::deriveMonthYear($invoice->period, $invoiceDate);

            if ((int) $invoice->year === $year && (int) $invoice->month === $month) {
                continue; // ya está sincronizada
            }

            $this->line(sprintf(
                '  #%d | %s | Nº %s | period=%s emisión=%s | %d-%02d → %d-%02d',
                $invoice->id,
                $invoice->provider,
                $invoice->invoice_number ?? '—',
                $invoice->period ?? '—',
                $invoiceDate ?? '—',
                (int) $invoice->year,
                (int) $invoice->month,
                $year,
                $month,
            ));

            if (! $dryRun) {
                $invoice->year = $year;
                $invoice->month = $month;
                $invoice->save();
            }

            $fixed++;
        }

        if ($fixed === 0) {
            $this->info('Todas las facturas ya están sincronizadas con su período.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? "[dry-run] " : "✓ ") . "{$fixed} factura(s) " . ($dryRun ? 'a corregir.' : 'corregida(s).'));

        return self::SUCCESS;
    }
}
