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
       Schema::create('return_processes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('person_id');
    $table->boolean('it_processed')->default(false);
    $table->boolean('rrhh_confirmed')->default(false);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_processes');
    }
};
