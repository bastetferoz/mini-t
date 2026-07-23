<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    protected $fillable = [
        'name',
        'code',
        'subject',
        'body',
        'smtp_profile_id',
        'is_active',
        'schedule_frequency',
        'schedule_to',
        'schedule_cc',
        'last_sent_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function smtpProfile()
    {
        return $this->belongsTo(SmtpProfile::class);
    }
}