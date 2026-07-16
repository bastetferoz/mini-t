<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnShipment extends Model
{
    protected $fillable = [
        'return_process_id',
        'logistics_method',
        'carrier',
        'tracking_number',
        'tracking_status',
        'tracking_payload',
        'notes',
        'pickup_scheduled_at',
        'pickup_contact',
        'last_update',
    ];

    protected $casts = [
        'tracking_payload' => 'array',
        'pickup_scheduled_at' => 'datetime',
        'last_update' => 'datetime',
    ];

    public function returnProcess()
    {
        return $this->belongsTo(ReturnProcess::class);
    }
}
