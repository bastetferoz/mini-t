<?php

namespace App\Console\Commands;

use App\Models\MailIngestConfig;
use App\Services\MailIngestService;
use Illuminate\Console\Command;

class ProcessIncomingInvoices extends Command
{
    protected $signature = 'invoices:process-mail';

    protected $description = 'Procesa facturas desde buzones de correo configurados.';

    public function handle(): void
    {
        $configs = MailIngestConfig::where('is_active', true)->get();

        if ($configs->isEmpty()) {
            $this->info('No hay buzones activos configurados.');
            return;
        }

        foreach ($configs as $config) {
            // Verificar si corresponde revisar (según intervalo)
            if ($config->last_checked_at && $config->last_checked_at->diffInMinutes(now()) < $config->check_interval_minutes) {
                $this->info("Saltando {$config->email} (último check hace " . $config->last_checked_at->diffInMinutes(now()) . " min)");
                continue;
            }

            $this->info("Procesando buzón: {$config->email}...");

            $service = new MailIngestService($config);
            $stats = $service->process();

            $this->info("  → Procesadas: {$stats['processed']} | Errores: {$stats['errors']} | Omitidas: {$stats['skipped']}");
        }

        $this->info('Listo.');
    }
}
