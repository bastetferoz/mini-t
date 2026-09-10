<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estado de sincronización con Odoo en cada factura.
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'odoo_move_id')) {
                $table->unsignedBigInteger('odoo_move_id')->nullable()->after('file_path');
            }
            if (! Schema::hasColumn('invoices', 'odoo_synced_at')) {
                $table->timestamp('odoo_synced_at')->nullable()->after('odoo_move_id');
            }
        });

        // Configuración de mapeo a Odoo por proveedor.
        Schema::table('invoice_providers', function (Blueprint $table) {
            foreach ([
                'odoo_partner_id',
                'odoo_product_id',
                'odoo_tax_id',
                'odoo_document_type_id',
            ] as $col) {
                if (! Schema::hasColumn('invoice_providers', $col)) {
                    $table->unsignedBigInteger($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['odoo_move_id', 'odoo_synced_at']);
        });
        Schema::table('invoice_providers', function (Blueprint $table) {
            $table->dropColumn(['odoo_partner_id', 'odoo_product_id', 'odoo_tax_id', 'odoo_document_type_id']);
        });
    }
};
