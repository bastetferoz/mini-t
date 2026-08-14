<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_ingest_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // "Facturación Phinxlab"
            $table->string('email');                   // it-facturacion@phinxlab.com
            $table->string('provider')->default('microsoft'); // microsoft, gmail (futuro)
            $table->string('tenant_id')->nullable();
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->string('folder')->default('INBOX'); // carpeta a monitorear
            $table->boolean('is_active')->default(false);
            $table->integer('check_interval_minutes')->default(30);
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('total_processed')->default(0);
            $table->integer('total_errors')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_ingest_configs');
    }
};
