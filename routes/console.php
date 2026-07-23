<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actualizar estado de tracking de envíos EnvíoPack cada 30 minutos
Schedule::job(new \App\Jobs\UpdateTrackingStatus)->everyThirtyMinutes();

// Enviar reporte de equipos pendientes (frecuencia configurable desde la plantilla)
Schedule::command('mail:pending-assets-report')->dailyAt('09:00');
