<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_process_id')->constrained('return_processes')->cascadeOnDelete();
            $table->string('logistics_method');         // enviopack, moto
            $table->string('carrier')->nullable();      // Envíopack, Moto / mensajería
            $table->string('tracking_number')->nullable();
            $table->string('tracking_status')->default('pending_tracking'); // pending_tracking, pickup_scheduled, in_transit, delivered
            $table->json('tracking_payload')->nullable(); // eventos del tracking
            $table->text('notes')->nullable();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->string('pickup_contact')->nullable();
            $table->timestamp('last_update')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_shipments');
    }
};
