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

        'last_update',

        'notes',

        'pickup_scheduled_at',

        'pickup_contact',

    ];

    protected $casts = [

        'tracking_payload' => 'array',

        'last_update' => 'datetime',

        'pickup_scheduled_at' => 'datetime',

    ];

    public function returnProcess()
    {
        return $this->belongsTo(ReturnProcess::class);
    }
}
