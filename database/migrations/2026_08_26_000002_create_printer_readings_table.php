<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('printer_readings')) {
            Schema::create('printer_readings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('printer_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('page_count');    // Contador leído
                $table->timestamp('read_at');                // Momento de la lectura
                $table->string('source')->default('manual'); // manual | scheduled
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_readings');
    }
};
