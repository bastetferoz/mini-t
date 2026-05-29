<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'device',
        'brand',
        'model',
        'cpu',
        'ram',
        'disk',
        'wireless_mouse',
        'serial',
        'notes',
        'status',
    ];

    // 🔥 RELACIÓN CORRECTA (LA QUE FALTABA)
    public function assignments()
    {
        return $this->hasMany(\App\Models\Assignment::class);
    }

    // 📜 HISTORIAL (si lo usás)
    public function histories()
    {
        return $this->hasMany(\App\Models\AssetHistory::class)->latest();
    }

    public function getFullDescriptionAttribute(): string
{
    $parts = [
        $this->device,
        $this->brand,
        $this->model,
    ];

    if ($this->cpu) {
        $parts[] = $this->cpu;
    }

    if ($this->ram) {
        $parts[] = $this->ram;
    }

    if ($this->disk) {
        $parts[] = $this->disk;
    }

    $text = implode(' - ', array_filter($parts));

    if ($this->serial) {
        $text .= ' - SN: ' . $this->serial;
    }

    return $text;
}

}