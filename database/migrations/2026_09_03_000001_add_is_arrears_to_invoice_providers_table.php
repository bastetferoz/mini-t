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
            if (! Schema::hasColumn('invoice_providers', 'is_arrears')) {
                $table->boolean('is_arrears')->default(false)->after('is_multi');
            }
        });

        // Marcar como "mes vencido" a los proveedores que emiten a principio de mes
        // la factura del servicio del mes anterior. Para estos, el mes de la factura
        // es el mes de emisión menos 1. El resto queda en OFF (mes de emisión directo).
        $arrears = ['microsoft', 'amazon', 'twilio', 'telefonica'];

        DB::table('invoice_providers')
            ->whereIn('slug', $arrears)
            ->update(['is_arrears' => true]);
    }

    public function down(): void
    {
        Schema::table('invoice_providers', function (Blueprint $table) {
            $table->dropColumn('is_arrears');
        });
    }
};
