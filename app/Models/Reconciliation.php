<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    protected $fillable = [
        'person_name',
        'month',
        'year',
        'created_by',
    ];

    protected $casts = [
        'month' => 'integer',
        'year'  => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(ReconciliationItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
