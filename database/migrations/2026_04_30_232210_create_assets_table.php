<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('assets', function (Blueprint $table) {
        $table->id();
        $table->string('device'); // Dispositivo
        $table->string('brand')->nullable(); // Marca
        $table->string('model')->nullable(); // Modelo
        $table->string('cpu')->nullable(); // CPU
        $table->string('ram')->nullable(); // Memoria
        $table->string('disk')->nullable(); // Disco
        $table->boolean('wireless_mouse')->default(false); // Mouse
        $table->string('serial')->unique(); // Nº Serie
        $table->text('notes')->nullable(); // Observaciones
        $table->timestamps();
        $table->softDeletes();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
