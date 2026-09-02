<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class SyncInvoicePeriods extends Command
{
    protected $signature = 'invoices:sync-periods {--dry-run : Solo muestra los cambios sin aplicarlos}';

    protected $description = 'Resincroniza month/year de las facturas con su period (YYYY-MM). Corrige facturas donde el mes del análisis no coincide con el período.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $invoices = Invoice::whereNotNull('period')->get();
        $fixed = 0;

        foreach ($invoices as $invoice) {
            if (! preg_match('/^(\d{4})-(\d{1,2})$/', trim($invoice->period), $m)) {
                continue;
            }

            $year = (int) $m[1];
            $month = (int) $m[2];

            if ((int) $invoice->year === $year && (int) $invoice->month === $month) {
                continue; // ya está sincronizada
            }

            $this->line(sprintf(
                '  #%d | %s | Nº %s | period=%s | %d-%02d → %d-%02d',
                $invoice->id,
                $invoice->provider,
                $invoice->invoice_number ?? '—',
                $invoice->period,
                (int) $invoice->year,
                (int) $invoice->month,
                $year,
                $month,
            ));

            if (! $dryRun) {
                // saveQuietly evita disparar el hook saving() (que ya haría lo mismo),
                // pero seteamos explícito para dejar claro el intento.
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
