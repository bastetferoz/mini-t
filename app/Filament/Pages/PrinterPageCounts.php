<?php

namespace App\Filament\Pages;

use App\Models\Printer;
use App\Models\PrinterReading;
use App\Services\PrinterService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PrinterPageCounts extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calculator';

    protected static string | \UnitEnum | null $navigationGroup = 'Infra';

    protected static ?string $navigationLabel = 'Conteo de páginas';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Conteo de páginas';

    protected string $view = 'filament.pages.printer-page-counts';

    public ?int $year = null;
    public ?int $countDay = null;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->countDay = (int) \App\Models\Setting::get('printer_count_day', 27);
    }

    /**
     * Guarda el día global de conteo automático.
     */
    public function saveCountDay(): void
    {
        $day = max(1, min(31, (int) $this->countDay));
        \App\Models\Setting::set('printer_count_day', $day);
        $this->countDay = $day;

        Notification::make()
            ->title('Día de conteo actualizado')
            ->body("El conteo automático se ejecutará el día {$day} de cada mes (o el último día del mes si no existe).")
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }

    /**
     * Años que tienen lecturas registradas (para el selector).
     */
    public function getYears(): array
    {
        $years = PrinterReading::selectRaw('DISTINCT YEAR(read_at) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->all();

        $current = (int) now()->year;
        if (! in_array($current, $years, true)) {
            array_unshift($years, $current);
        }

        return $years;
    }

    public function getPrinters(): Collection
    {
        return Printer::orderBy('name')->get();
    }

    /**
     * Devuelve, por impresora, el último contador de cada mes del año seleccionado.
     * Estructura: [printer_id => [mes => page_count]]
     */
    public function getMonthlyCounts(): array
    {
        $readings = PrinterReading::whereYear('read_at', $this->year)
            ->orderBy('read_at')
            ->get();

        $data = [];

        foreach ($readings as $r) {
            $month = (int) $r->read_at->format('n');
            // Nos quedamos con la última lectura de cada mes (la más reciente pisa)
            $data[$r->printer_id][$month] = $r->page_count;
        }

        return $data;
    }

    /**
     * Sondea todas las impresoras ahora y registra una lectura.
     */
    public function readNow(): void
    {
        $printers = Printer::where('type', 'network')->get();

        if ($printers->isEmpty()) {
            Notification::make()->title('No hay impresoras de red registradas')->warning()->send();
            return;
        }

        $count = 0;
        foreach ($printers as $printer) {
            $result = PrinterService::probe($printer, 'manual');
            if ($result['page_count'] !== null) {
                $count++;
            }
        }

        Notification::make()
            ->title('Lectura completada')
            ->body("{$count} de {$printers->count()} impresora(s) con contador leído.")
            ->success()
            ->send();
    }
}
