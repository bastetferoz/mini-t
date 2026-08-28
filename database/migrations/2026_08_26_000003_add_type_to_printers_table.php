<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            if (! Schema::hasColumn('printers', 'type')) {
                $table->string('type')->default('network')->after('name'); // network | manual
            }
        });

        // Hacer la IP nullable (las impresoras manuales pueden no tener IP)
        Schema::table('printers', function (Blueprint $table) {
            $table->string('ip')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            if (Schema::hasColumn('printers', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
