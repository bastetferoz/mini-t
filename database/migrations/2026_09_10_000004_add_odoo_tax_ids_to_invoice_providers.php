<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_providers', 'odoo_tax_ids')) {
                // Lista de impuestos de Odoo (permite varios por línea).
                $table->json('odoo_tax_ids')->nullable();
            }
        });

        // Migrar el valor único existente (odoo_tax_id) a la lista.
        if (Schema::hasColumn('invoice_providers', 'odoo_tax_id')) {
            foreach (DB::table('invoice_providers')->whereNotNull('odoo_tax_id')->get() as $p) {
                DB::table('invoice_providers')
                    ->where('id', $p->id)
                    ->update(['odoo_tax_ids' => json_encode([(int) $p->odoo_tax_id])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            $table->dropColumn('odoo_tax_ids');
        });
    }
};
