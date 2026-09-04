<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_providers', 'is_arrears')) {
                $table->dropColumn('is_arrears');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_providers', 'is_arrears')) {
                $table->boolean('is_arrears')->default(false)->after('is_multi');
            }
        });
    }
};
