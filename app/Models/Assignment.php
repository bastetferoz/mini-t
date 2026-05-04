<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Asset;
use App\Models\Person;

class Assignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'person_id',
        'asset_id',
        'assigned_at',
        'notes',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}