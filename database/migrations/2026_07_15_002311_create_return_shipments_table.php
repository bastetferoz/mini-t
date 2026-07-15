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

            $table->foreignId('return_process_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('carrier');

            $table->string('tracking_number');

            $table->string('tracking_status')
                ->default('pending');

            $table->json('tracking_payload')
                ->nullable();

            $table->timestamp('last_update')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_shipments');
    }
};