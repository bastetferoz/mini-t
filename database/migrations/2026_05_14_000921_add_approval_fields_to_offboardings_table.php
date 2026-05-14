<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_processes', function (Blueprint $table) {
            $table->timestamp('rrhh_confirmed_at')->nullable();

            $table->foreignId('rrhh_confirmed_by')
                ->nullable()
                ->constrained('users');

            $table->timestamp('it_confirmed_at')->nullable();

            $table->foreignId('it_confirmed_by')
                ->nullable()
                ->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('return_processes', function (Blueprint $table) {
            $table->dropForeign(['rrhh_confirmed_by']);
            $table->dropForeign(['it_confirmed_by']);

            $table->dropColumn([
                'rrhh_confirmed_at',
                'rrhh_confirmed_by',
                'it_confirmed_at',
                'it_confirmed_by',
            ]);
        });
    }
};