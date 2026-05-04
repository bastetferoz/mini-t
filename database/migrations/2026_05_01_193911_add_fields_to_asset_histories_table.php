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
    Schema::table('asset_histories', function (Blueprint $table) {
        $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
        $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('asset_histories', function (Blueprint $table) {
        $table->dropColumn(['asset_id', 'person_id']);
    });
}
};
