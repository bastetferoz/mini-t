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
        // Mantener month/year sincronizados con period (formato YYYY-MM).
        // El análisis suma por month/year, así que deben reflejar siempre el período.
        static::saving(function (Invoice $invoice) {
            if ($invoice->period && preg_match('/^(\d{4})-(\d{1,2})$/', trim($invoice->period), $m)) {
                $invoice->year = (int) $m[1];
                $invoice->month = (int) $m[2];
            }
        });
    }

    /**
     * Deriva [año, mes] a partir del period (YYYY-MM), con fallback a invoice_date.
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
