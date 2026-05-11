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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function smtpProfile()
    {
        return $this->belongsTo(SmtpProfile::class);
    }
}