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
        // Determinar month/year para el análisis. Criterio:
        // 1) El PERÍODO DE SERVICIO que declara la factura (campo period, YYYY-MM).
        //    Es lo más confiable (ej: Google dice "Summary for Mar 1 - Mar 31").
        // 2) Si no hay period válido, se usa el mes de la FECHA DE EMISIÓN (invoice_date).
        // El análisis suma por month/year.
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
     * Deriva [año, mes] priorizando el PERÍODO DE SERVICIO (period, YYYY-MM).
     * Solo cae a la fecha de emisión (invoice_date) si no hay un period válido.
     */
    public static function deriveMonthYear(?string $period, ?string $invoiceDate): array
    {
        if ($period && preg_match('/^(\d{4})-(\d{1,2})$/', trim($period), $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        if ($invoiceDate && preg_match('/^(\d{4})-(\d{2})-\d{2}/', $invoiceDate, $d)) {
            return [(int) $d[1], (int) $d[2]];
        }

        return [(int) now()->year, (int) now()->month];
    }
}
