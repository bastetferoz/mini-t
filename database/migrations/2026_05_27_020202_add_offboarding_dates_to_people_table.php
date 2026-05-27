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
    Schema::table('people', function (Blueprint $table) {
        $table->timestamp('offboarding_started_at')->nullable();
        $table->timestamp('offboarding_completed_at')->nullable();
    });
}

public function down(): void
{
    Schema::table('people', function (Blueprint $table) {
        $table->dropColumn(['offboarding_started_at', 'offboarding_completed_at']);
    });
}
};
