<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RemoveOtherProviderDuplicates extends Command
{
    protected $signature = 'invoices:remove-other-duplicates {--dry-run : Solo mostrar qué se borraría, sin borrar}';

    protected $description = 'Elimina facturas cargadas como "otro" cuando la misma factura ya existe con un proveedor identificado (mismo invoice_number + monto).';

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');

        // Facturas caídas en "otro" con número de factura
        $orphans = Invoice::where('provider', 'otro')
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No hay facturas en "otro" para revisar.');
            return;
        }

        $deleted = 0;

        foreach ($orphans as $orphan) {
            // ¿Existe la misma factura con un proveedor identificado (distinto de "otro")?
            $match = Invoice::where('provider', '!=', 'otro')
                ->where('invoice_number', $orphan->invoice_number)
                ->where('amount', $orphan->amount)
                ->first();

            if (! $match) {
                continue;
            }

            $this->line("  Duplicada en 'otro' #{$orphan->id} | Nº {$orphan->invoice_number} | \${$orphan->amount} → ya existe en '{$match->provider}' #{$match->id}");

            if ($dryRun) {
                $deleted++;
                continue;
            }

            // Borrar archivo físico si existe
            if ($orphan->file_path && Storage::disk('public')->exists($orphan->file_path)) {
                Storage::disk('public')->delete($orphan->file_path);
            }

            $orphan->delete();
            $deleted++;
        }

        if ($dryRun) {
            $this->info("[dry-run] {$deleted} factura(s) en 'otro' se borrarían.");
        } else {
            $this->info("✓ {$deleted} factura(s) duplicada(s) en 'otro' eliminada(s).");
        }
    }
}
