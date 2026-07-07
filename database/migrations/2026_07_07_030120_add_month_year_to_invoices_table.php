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
    Schema::table('invoices', function (Blueprint $table) {
        if (!Schema::hasColumn('invoices', 'month')) {
            $table->unsignedTinyInteger('month')->after('period');
        }
        if (!Schema::hasColumn('invoices', 'year')) {
            $table->unsignedSmallInteger('year')->after('month');
        }
    });
}

public function down(): void
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->dropColumn(['month', 'year']);
    });
}
};
