<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('person_name');   // persona conciliada (ej: RODOLFO DURANTE)
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('created_by')->nullable(); // usuario que guardó
            $table->timestamps();

            $table->index(['person_name', 'year', 'month']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('reconciliations')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 8)->default('ARS');
            // estado: green (con factura), yellow (reconocido sin factura), red (sin factura)
            $table->string('status', 10)->default('red');
            $table->string('invoice_info')->nullable(); // texto de la factura encontrada
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliations');
    }
};
