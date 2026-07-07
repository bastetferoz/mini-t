<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // "GPT-4o Producción", "Gemini Pro", etc.
            $table->string('provider');           // openai, google, anthropic
            $table->string('model');              // gpt-4o, gemini-1.5-pro, claude-sonnet-4-20250514, etc.
            $table->string('api_key');            // API key del proveedor
            $table->string('endpoint')->nullable(); // URL custom (para proxies o proveedores alternativos)
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_profiles');
    }
};
