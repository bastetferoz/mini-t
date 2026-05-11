<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('name');              // Nombre visible
            $table->string('code')->unique();    // Código único

            // Contenido del correo
            $table->string('subject');
            $table->longText('body');

            // Perfil SMTP asociado (opcional)
            $table->foreignId('smtp_profile_id')
                ->nullable()
                ->constrained('smtp_profiles')
                ->nullOnDelete();

            // Estado
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};