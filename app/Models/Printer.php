<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Printer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'ip',
        'brand',
        'model',
        'serial',
        'location',
        'snmp_community',
        'status',
        'last_seen_at',
        'page_count',
        'page_count_at',
        'notes',
    ];

    protected $casts = [
        'last_seen_at'  => 'datetime',
        'page_count_at' => 'datetime',
        'page_count'    => 'integer',
    ];

    /**
     * Historial de lecturas de contador de páginas.
     */
    public function readings()
    {
        return $this->hasMany(PrinterReading::class)->latest('read_at');
    }

    public function isNetwork(): bool
    {
        return $this->type !== 'manual';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'online'  => 'En línea',
            'offline' => 'Desconectada',
            default   => 'Desconocido',
        };
    }
}
