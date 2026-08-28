<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('printers')) {
            Schema::create('printers', function (Blueprint $table) {
                $table->id();
                $table->string('name');                          // Nombre identificador
                $table->string('ip');                            // IP de la impresora
                $table->string('brand')->nullable();             // Marca (detectada por SNMP)
                $table->string('model')->nullable();             // Modelo (detectado por SNMP)
                $table->string('serial')->nullable();            // Nº de serie (SNMP)
                $table->string('location')->nullable();          // Ubicación / oficina
                $table->string('snmp_community')->default('public'); // Community SNMP de lectura
                $table->string('status')->default('unknown');    // online | offline | unknown
                $table->timestamp('last_seen_at')->nullable();   // Último ping/SNMP exitoso
                $table->unsignedBigInteger('page_count')->nullable();     // Último contador de páginas
                $table->timestamp('page_count_at')->nullable();  // Fecha de la última lectura de contador
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
