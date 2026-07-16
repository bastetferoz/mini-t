<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'company')) {
                $table->string('company')->nullable()->after('provider'); // nova, phinx, ministerio, etc.
            }
            if (! Schema::hasColumn('invoices', 'project')) {
                $table->string('project')->nullable()->after('company'); // odoo, ster, choir, etc.
            }
            if (! Schema::hasColumn('invoices', 'reference')) {
                $table->string('reference')->nullable()->after('service'); // referencia adicional (cuenta AWS, dominio, etc.)
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['company', 'project', 'reference']);
        });
    }
};
