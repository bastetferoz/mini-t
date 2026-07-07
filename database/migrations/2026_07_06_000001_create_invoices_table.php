<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('provider');
                $table->string('service')->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('ARS');
                $table->date('invoice_date');
                $table->string('period');
                $table->string('invoice_number')->nullable();
                $table->string('file_path')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
