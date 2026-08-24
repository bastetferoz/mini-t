<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisView extends Model
{
    protected $fillable = [
        'name',
        'filters',
        'user_id',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Vistas del usuario actual.
     */
    public static function forCurrentUser()
    {
        return static::where('user_id', auth()->id())->orderBy('name');
    }
}
