<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actualizar estado de tracking de envíos EnvíoPack a las 9:00 y 19:00
Schedule::job(new \App\Jobs\UpdateTrackingStatus)->dailyAt('09:00');
Schedule::job(new \App\Jobs\UpdateTrackingStatus)->dailyAt('19:00');

// Enviar reporte de equipos pendientes (frecuencia configurable desde la plantilla)
Schedule::command('mail:pending-assets-report')->dailyAt('09:00');

// Procesar facturas desde buzón de correo (cada 15 min, el servicio verifica el intervalo configurado)
Schedule::command('invoices:process-mail')->everyFifteenMinutes();

// Eliminar facturas duplicadas (después de cada ingesta)
Schedule::command('invoices:remove-duplicates')->everyThirtyMinutes();

// Eliminar facturas caídas en "otro" que ya existen con proveedor identificado
Schedule::command('invoices:remove-other-duplicates')->everyThirtyMinutes();
