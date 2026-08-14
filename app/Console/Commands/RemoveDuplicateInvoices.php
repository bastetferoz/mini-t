<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateInvoices extends Command
{
    protected $signature = 'invoices:remove-duplicates';

    protected $description = 'Elimina facturas duplicadas (mismo invoice_number + provider). Conserva la primera cargada.';

    public function handle(): void
    {
        // Buscar duplicados por invoice_number + provider
        $duplicates = Invoice::select('invoice_number', 'provider', DB::raw('COUNT(*) as total'), DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->groupBy('invoice_number', 'provider')
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No hay facturas duplicadas.');
            return;
        }

        $deleted = 0;

        foreach ($duplicates as $dup) {
            $toDelete = Invoice::where('invoice_number', $dup->invoice_number)
                ->where('provider', $dup->provider)
                ->where('id', '!=', $dup->keep_id)
                ->get();

            foreach ($toDelete as $invoice) {
                $this->line("  Eliminando: #{$invoice->id} | {$invoice->provider} | Nº {$invoice->invoice_number} | \${$invoice->amount}");
                $invoice->delete();
                $deleted++;
            }
        }

        $this->info("✓ {$deleted} factura(s) duplicada(s) eliminada(s).");
    }
}
