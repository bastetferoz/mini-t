<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Assignment;

class Person extends Model
{
    use SoftDeletes;

    protected $fillable = [
    'name',
    'email',
    'area',
    'status',
    'services',
    'offboarding_started_at',
    'offboarding_completed_at',
];



    protected $casts = [
        'services' => 'array',
        'offboarding_started_at' => 'datetime',
        'offboarding_completed_at' => 'datetime',
    ];

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
    public function assetHistories()
{
    return $this->hasMany(\App\Models\AssetHistory::class);
}
}