<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class AuditInvoices extends Command
{
    protected $signature = 'invoices:audit {provider? : Slug del proveedor a auditar (ej: sector-copier). Vacío = todos}';

    protected $description = 'Audita las facturas mostrando period, fecha de emisión y mes/año asignado. Solo lectura, no modifica nada.';

    public function handle(): int
    {
        $providerSlug = $this->argument('provider');

        $query = Invoice::query()->orderBy('provider')->orderBy('year')->orderBy('month');

        if ($providerSlug) {
            $query->where('provider', $providerSlug);
        }

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            $this->warn('No hay facturas' . ($providerSlug ? " para '{$providerSlug}'." : '.'));
            return self::SUCCESS;
        }

        $rows = $invoices->map(function (Invoice $i) {
            $invoiceDate = $i->invoice_date instanceof \DateTimeInterface
                ? $i->invoice_date->format('Y-m-d')
                : ($i->invoice_date ?? '—');

            // Qué mes debería tener según el criterio actual
            [$cy, $cm] = Invoice::deriveMonthYear($i->period, $invoiceDate === '—' ? null : $invoiceDate);
            $asignado = sprintf('%d-%02d', (int) $i->year, (int) $i->month);
            $deberia  = sprintf('%d-%02d', $cy, $cm);

            return [
                $i->id,
                $i->provider,
                $i->invoice_number ?? '—',
                $i->period ?? '—',
                $invoiceDate,
                $asignado,
                $deberia,
                $asignado === $deberia ? '' : '⚠️',
                number_format((float) $i->amount, 2, ',', '.') . ' ' . $i->currency,
            ];
        })->toArray();

        $this->table(
            ['ID', 'Proveedor', 'Nº', 'period', 'Emisión', 'Asignado', 'Debería', '!', 'Monto'],
            $rows
        );

        // Resumen por mes (para detectar acumulación)
        $this->newLine();
        $this->info('Resumen por mes asignado (year-month => cantidad | suma):');
        $byMonth = $invoices->groupBy(fn ($i) => sprintf('%d-%02d', (int) $i->year, (int) $i->month))
            ->map(fn ($g) => [
                'cant' => $g->count(),
                'suma' => number_format((float) $g->sum('amount'), 2, ',', '.'),
            ])
            ->sortKeys();

        foreach ($byMonth as $month => $data) {
            $this->line("  {$month} => {$data['cant']} factura(s) | {$data['suma']}");
        }

        return self::SUCCESS;
    }
}
