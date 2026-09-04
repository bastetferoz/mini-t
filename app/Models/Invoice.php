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
     * Máxima diferencia (en meses) tolerada entre el period y la fecha de emisión.
     * Si el period se aleja más que esto de la emisión, se considera que la IA lo
     * leyó mal y se usa la fecha de emisión en su lugar.
     */
    public const MAX_PERIOD_DRIFT_MONTHS = 2;

    /**
     * Deriva [año, mes] priorizando el PERÍODO DE SERVICIO (period, YYYY-MM).
     * Validación: si el period difiere de la fecha de emisión en más de
     * MAX_PERIOD_DRIFT_MONTHS meses, se descarta el period (probable error de la IA)
     * y se usa la fecha de emisión. Si no hay period válido, también cae a la emisión.
     */
    public static function deriveMonthYear(?string $period, ?string $invoiceDate): array
    {
        $periodYm = null;
        if ($period && preg_match('/^(\d{4})-(\d{1,2})$/', trim($period), $m)) {
            $periodYm = [(int) $m[1], (int) $m[2]];
        }

        $emisionYm = null;
        if ($invoiceDate && preg_match('/^(\d{4})-(\d{2})-\d{2}/', $invoiceDate, $d)) {
            $emisionYm = [(int) $d[1], (int) $d[2]];
        }

        // Con ambos datos: usar el period salvo que se aleje demasiado de la emisión.
        if ($periodYm && $emisionYm) {
            $driftMeses = abs(
                (($periodYm[0] * 12) + $periodYm[1]) - (($emisionYm[0] * 12) + $emisionYm[1])
            );

            if ($driftMeses > self::MAX_PERIOD_DRIFT_MONTHS) {
                return $emisionYm; // period sospechoso → gana la fecha de emisión
            }

            return $periodYm;
        }

        if ($periodYm) {
            return $periodYm;
        }

        if ($emisionYm) {
            return $emisionYm;
        }

        return [(int) now()->year, (int) now()->month];
    }
}
