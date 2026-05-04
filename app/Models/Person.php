<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Assignment;

class Person extends Model
{
    use SoftDeletes;
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
    protected $fillable = [
        'name',
        'email',
        'area',
        'status',
    ];
}
