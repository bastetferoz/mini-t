<?php

namespace App\Console\Commands;

use App\Models\Printer;
use App\Services\PrinterService;
use Illuminate\Console\Command;

class ReadPrinterCounters extends Command
{
    protected $signature = 'printers:read-counters {--scheduled : Solo ejecuta si hoy es el día de conteo configurado}';

    protected $description = 'Sondea todas las impresoras (ping + SNMP) y registra el contador de páginas del mes.';

    public function handle(): void
    {
        // Si se corre desde el scheduler (diario), verificar que hoy sea el día configurado.
        if ($this->option('scheduled') && ! $this->isCountDay()) {
            return;
        }

        // Solo impresoras de red (las manuales se cargan a mano)
        $printers = Printer::where('type', 'network')->get();

        if ($printers->isEmpty()) {
            $this->info('No hay impresoras de red registradas.');
            return;
        }

        $online = 0;
        $withCount = 0;

        foreach ($printers as $printer) {
            $result = PrinterService::probe($printer, 'scheduled');

            if ($result['online']) {
                $online++;
            }
            if ($result['page_count'] !== null) {
                $withCount++;
            }

            $estado = $result['online'] ? 'en línea' : 'desconectada';
            $conteo = $result['page_count'] !== null ? $result['page_count'] : 's/d';
            $this->line("  {$printer->name} ({$printer->ip}): {$estado} | contador: {$conteo}");
        }

        $this->info("✓ {$printers->count()} impresora(s) sondeada(s). {$online} en línea, {$withCount} con contador leído.");
    }

    /**
     * ¿Hoy es el día configurado para el conteo?
     * Si el día configurado no existe en el mes actual (ej: 31 en febrero),
     * se ejecuta el último día del mes.
     */
    private function isCountDay(): bool
    {
        $configured = (int) \App\Models\Setting::get('printer_count_day', 27);
        $configured = max(1, min(31, $configured)); // acotar 1-31

        $today = (int) now()->day;
        $lastDayOfMonth = (int) now()->endOfMonth()->day;

        // Si el día configurado supera los días del mes, usar el último día
        $effectiveDay = min($configured, $lastDayOfMonth);

        return $today === $effectiveDay;
    }
}
