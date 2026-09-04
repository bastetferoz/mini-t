<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'provider',
        'company',
        'project',
        'service',
        'reference',
        'amount',
        'currency',
        'exchange_rate',
        'amount_usd',
        'invoice_date',
        'period',
        'month',
        'year',
        'invoice_number',
        'file_path',
        'notes',
    ];

    protected $casts = [
    'amount'        => 'decimal:2',
    'amount_usd'    => 'decimal:2',
    'exchange_rate' => 'decimal:4',
    'invoice_date'  => 'date',
];

    protected static function booted(): void
    {
        // Mantener month/year sincronizados con la FECHA DE EMISIÓN (invoice_date).
        // El mes lo determina la fecha de emisión, no el "period" que interpreta la IA.
        // Excepción: si el proveedor factura a MES VENCIDO (is_arrears), se cuenta en
        // el mes anterior a la emisión (ej: Microsoft emite el 02/08 el servicio de julio).
        // El análisis suma por month/year. Si no hay invoice_date, se usa el period.
        static::saving(function (Invoice $invoice) {
            $isArrears = self::providerIsArrears($invoice->provider);

            [$year, $month] = self::deriveMonthYear(
                $invoice->period,
                $invoice->invoice_date instanceof \DateTimeInterface
                    ? $invoice->invoice_date->format('Y-m-d')
                    : $invoice->invoice_date,
                $isArrears,
            );

            $invoice->year = $year;
            $invoice->month = $month;
        });
    }

    /**
     * ¿El proveedor (por slug) factura a mes vencido?
     */
    public static function providerIsArrears(?string $providerSlug): bool
    {
        if (blank($providerSlug)) {
            return false;
        }

        return (bool) InvoiceProvider::where('slug', $providerSlug)
            ->value('is_arrears');
    }

    /**
     * Deriva [año, mes] priorizando la FECHA DE EMISIÓN (invoice_date).
     * Si $isArrears es true, resta un mes (proveedor que factura a mes vencido).
     * Solo cae al period (YYYY-MM) si no hay una fecha de emisión válida.
     */
    public static function deriveMonthYear(?string $period, ?string $invoiceDate, bool $isArrears = false): array
    {
        if ($invoiceDate && preg_match('/^(\d{4})-(\d{2})-\d{2}/', $invoiceDate, $d)) {
            $year = (int) $d[1];
            $month = (int) $d[2];

            if ($isArrears) {
                // Restar un mes: el servicio corresponde al mes anterior a la emisión.
                $date = \Carbon\Carbon::create($year, $month, 1)->subMonth();
                return [(int) $date->year, (int) $date->month];
            }

            return [$year, $month];
        }

        if ($period && preg_match('/^(\d{4})-(\d{1,2})$/', trim($period), $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [(int) now()->year, (int) now()->month];
    }
}
