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
        // Criterio único para todas las facturas: el mes lo determina la fecha de
        // emisión, no el "period" que interpreta la IA (que es inconsistente entre
        // facturas del mismo proveedor). El análisis suma por month/year.
        // Si no hay invoice_date, se usa el period como respaldo.
        static::saving(function (Invoice $invoice) {
            [$year, $month] = self::deriveMonthYear(
                $invoice->period,
                $invoice->invoice_date instanceof \DateTimeInterface
                    ? $invoice->invoice_date->format('Y-m-d')
                    : $invoice->invoice_date,
            );

            $invoice->year = $year;
            $invoice->month = $month;
        });
    }

    /**
     * Deriva [año, mes] priorizando la FECHA DE EMISIÓN (invoice_date).
     * Solo cae al period (YYYY-MM) si no hay una fecha de emisión válida.
     */
    public static function deriveMonthYear(?string $period, ?string $invoiceDate): array
    {
        if ($invoiceDate && preg_match('/^(\d{4})-(\d{2})-\d{2}/', $invoiceDate, $d)) {
            return [(int) $d[1], (int) $d[2]];
        }

        if ($period && preg_match('/^(\d{4})-(\d{1,2})$/', trim($period), $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [(int) now()->year, (int) now()->month];
    }
}
