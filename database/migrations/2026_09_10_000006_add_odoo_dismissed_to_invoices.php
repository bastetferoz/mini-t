<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'odoo_dismissed')) {
                // Marca de "no se carga en Odoo" (desestimada).
                $table->boolean('odoo_dismissed')->default(false)->after('odoo_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('odoo_dismissed');
        });
    }
};
