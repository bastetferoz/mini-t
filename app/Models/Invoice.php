<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'provider',
        'service',
        'amount',
        'currency',
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
}
