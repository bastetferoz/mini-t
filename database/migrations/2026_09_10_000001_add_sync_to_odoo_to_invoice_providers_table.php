<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_providers', 'sync_to_odoo')) {
                $table->boolean('sync_to_odoo')->default(false)->after('is_multi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            $table->dropColumn('sync_to_odoo');
        });
    }
};
