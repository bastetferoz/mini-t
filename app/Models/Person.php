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
    ];

    protected $casts = [
        'services' => 'array',
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