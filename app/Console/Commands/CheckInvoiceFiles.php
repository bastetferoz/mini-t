<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckInvoiceFiles extends Command
{
    protected $signature = 'invoices:check-files {--fix : Vacía el file_path de las facturas cuyo archivo no existe}';

    protected $description = 'Verifica qué facturas apuntan a un archivo (file_path) que no existe en el disco. Con --fix, limpia esos file_path.';

    public function handle(): int
    {
        $conArchivo = Invoice::whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get(['id', 'provider', 'invoice_number', 'file_path', 'year', 'month']);

        $faltantes = $conArchivo->filter(
            fn (Invoice $i) => ! Storage::disk('public')->exists($i->file_path)
        );

        $this->info("Facturas con archivo adjunto: {$conArchivo->count()}");
        $this->info("Facturas cuyo archivo NO existe: {$faltantes->count()}");

        if ($faltantes->isEmpty()) {
            $this->info('✓ Todos los archivos existen.');
            return self::SUCCESS;
        }

        $this->newLine();
        $rows = $faltantes->map(fn (Invoice $i) => [
            $i->id,
            $i->provider,
            $i->invoice_number ?? '—',
            sprintf('%d-%02d', (int) $i->year, (int) $i->month),
            $i->file_path,
        ])->toArray();

        $this->table(['ID', 'Proveedor', 'Nº', 'Período', 'file_path (inexistente)'], $rows);

        // Resumen por proveedor
        $this->newLine();
        $this->info('Resumen por proveedor:');
        foreach ($faltantes->groupBy('provider')->map->count()->sortDesc() as $prov => $cant) {
            $this->line("  {$prov} => {$cant}");
        }

        if ($this->option('fix')) {
            $this->newLine();
            if ($this->confirm("¿Vaciar el file_path de estas {$faltantes->count()} facturas? (la factura se conserva, solo se quita el enlace al archivo)")) {
                foreach ($faltantes as $i) {
                    $i->update(['file_path' => null]);
                }
                $this->info("✓ Se limpiaron {$faltantes->count()} file_path.");
            } else {
                $this->warn('Cancelado. No se modificó nada.');
            }
        } else {
            $this->newLine();
            $this->comment('Para limpiar los file_path rotos: php artisan invoices:check-files --fix');
        }

        return self::SUCCESS;
    }
}
