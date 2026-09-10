<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_providers', 'odoo_account_id')) {
                // Cuenta contable de Odoo (opcional). Si es null, se usa la del producto.
                $table->unsignedBigInteger('odoo_account_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            $table->dropColumn('odoo_account_id');
        });
    }
};
