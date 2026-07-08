<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // "Google", "Amazon", "Telecom"
            $table->string('slug')->unique();     // "google", "amazon", "telecom"
            $table->string('category')->nullable(); // Cloud, Internet, Licencias, Telefonía, etc.
            $table->string('default_currency', 3)->default('ARS');
            $table->text('detection_keywords');    // palabras clave para identificar (JSON array)
            $table->text('custom_prompt')->nullable(); // prompt específico para extracción
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_providers');
    }
};
