<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpProfile extends Model
{
    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'default_to',
        'cc_addresses',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        // Opcional: guardar la contraseña cifrada en la base de datos
        // 'password' => 'encrypted',
    ];
}