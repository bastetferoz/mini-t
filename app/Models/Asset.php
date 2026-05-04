<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'device',
        'brand',
        'model',
        'processor',
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
}