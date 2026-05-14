<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_process_asset', function (Blueprint $table) {
            $table->unsignedBigInteger('return_process_id')->nullable()->after('id');
            $table->unsignedBigInteger('asset')->nullable()->after('return_process_id');
            $table->boolean('returned')->default(false)->after('asset');
            $table->string('reason')->nullable()->after('returned');
            $table->text('notes')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('return_process_asset', function (Blueprint $table) {
            $table->dropColumn([
                'return_process_id',
                'asset',
                'returned',
                'reason',
                'notes',
            ]);
        });
    }
};