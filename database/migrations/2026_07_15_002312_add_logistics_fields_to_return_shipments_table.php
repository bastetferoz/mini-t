<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_shipments', function (Blueprint $table) {
            $table->string('logistics_method')->default('enviopack')->after('return_process_id');
            $table->string('carrier')->nullable()->change();
            $table->string('tracking_number')->nullable()->change();
            $table->text('notes')->nullable()->after('tracking_payload');
            $table->timestamp('pickup_scheduled_at')->nullable()->after('notes');
            $table->string('pickup_contact')->nullable()->after('pickup_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('return_shipments', function (Blueprint $table) {
            $table->dropColumn(['logistics_method', 'notes', 'pickup_scheduled_at', 'pickup_contact']);
            $table->string('carrier')->nullable(false)->change();
            $table->string('tracking_number')->nullable(false)->change();
        });
    }
};
