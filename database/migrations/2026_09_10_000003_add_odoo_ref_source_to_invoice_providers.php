<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_providers', 'odoo_ref_source')) {
                // Texto de la Referencia para Odoo (libre, admite variables como {numero}).
                $table->string('odoo_ref_source', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            $table->dropColumn('odoo_ref_source');
        });
    }
};
