<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->string('schedule_frequency')->nullable()->after('is_active'); // daily, weekly, biweekly, triweekly, null
            $table->string('schedule_to')->nullable()->after('schedule_frequency'); // email destinatario
            $table->string('schedule_cc')->nullable()->after('schedule_to'); // CC separados por ;
            $table->timestamp('last_sent_at')->nullable()->after('schedule_cc');
        });
    }

    public function down(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->dropColumn(['schedule_frequency', 'schedule_to', 'schedule_cc', 'last_sent_at']);
        });
    }
};
