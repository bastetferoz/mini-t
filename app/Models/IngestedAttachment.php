<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestedAttachment extends Model
{
    protected $fillable = [
        'hash',
        'filename',
        'provider',
        'invoice_number',
        'invoice_id',
    ];

    /**
     * ¿Ya se procesó un adjunto con este contenido (hash)?
     */
    public static function alreadyProcessed(string $hash): bool
    {
        return static::where('hash', $hash)->exists();
    }
}
