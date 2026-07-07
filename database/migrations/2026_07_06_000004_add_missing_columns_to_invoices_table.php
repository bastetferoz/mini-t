<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'period')) {
                $table->string('period')->nullable()->after('invoice_date');
            }
            if (! Schema::hasColumn('invoices', 'service')) {
                $table->string('service')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('invoices', 'currency')) {
                $table->string('currency', 3)->default('ARS')->after('amount');
            }
            if (! Schema::hasColumn('invoices', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('period');
            }
            if (! Schema::hasColumn('invoices', 'file_path')) {
                $table->string('file_path')->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('invoices', 'notes')) {
                $table->text('notes')->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas en rollback para evitar pérdida de datos
    }
};
