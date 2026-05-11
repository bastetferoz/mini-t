<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(587);
            $table->string('username');
            $table->text('password');
            $table->string('encryption')->nullable();

            $table->string('from_address');
            $table->string('from_name')->nullable();

            $table->string('default_to')->nullable();
            $table->text('cc_addresses')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_profiles');
    }
};