<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    protected $fillable = [
        'asset_id',
        'person_id',
        'action',
        'notes',
    ];

    public function asset()
    {
        return $this->belongsTo(\App\Models\Asset::class);
    }

    public function person()
    {
        return $this->belongsTo(\App\Models\Person::class);
    }
}