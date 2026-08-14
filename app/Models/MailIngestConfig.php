<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailIngestConfig extends Model
{
    protected $fillable = [
        'name',
        'email',
        'provider',
        'tenant_id',
        'client_id',
        'client_secret',
        'folder',
        'is_active',
        'check_interval_minutes',
        'last_checked_at',
        'total_processed',
        'total_errors',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];
}
