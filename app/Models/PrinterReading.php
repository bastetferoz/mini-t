<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterReading extends Model
{
    protected $fillable = [
        'printer_id',
        'page_count',
        'read_at',
        'source',
    ];

    protected $casts = [
        'read_at'    => 'datetime',
        'page_count' => 'integer',
    ];

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }
}
