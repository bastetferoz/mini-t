<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationItem extends Model
{
    protected $fillable = [
        'reconciliation_id',
        'description',
        'amount',
        'currency',
        'status',
        'invoice_info',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function reconciliation()
    {
        return $this->belongsTo(Reconciliation::class);
    }
}
