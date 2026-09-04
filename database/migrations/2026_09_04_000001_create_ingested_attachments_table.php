<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingested_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 64)->unique(); // SHA-256 del contenido del adjunto
            $table->string('filename')->nullable();
            $table->string('provider')->nullable();
            $table->string('invoice_number')->nullable();
            $table->foreignId('invoice_id')->nullable(); // factura creada (si aplica)
            $table->timestamps();

            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingested_attachments');
    }
};
