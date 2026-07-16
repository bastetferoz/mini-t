<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProfile extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'model',
        'api_key',
        'endpoint',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Obtiene el perfil por defecto activo.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Retorna la URL del endpoint según el proveedor.
     */
    public function getEndpointUrl(): string
    {
        if ($this->endpoint) {
            return $this->endpoint;
        }

        return match ($this->provider) {
            'openai' => 'https://api.openai.com/v1/chat/completions',
            'google' => 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent',
            'anthropic' => 'https://api.anthropic.com/v1/messages',
            'groq' => 'https://api.groq.com/openai/v1/chat/completions',
            default => '',
        };
    }
}
