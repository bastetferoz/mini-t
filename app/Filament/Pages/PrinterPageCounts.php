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

    // Formulario de carga manual del contador de un mes
    public ?int $manualPrinterId = null;
    public ?int $manualYear = null;
    public ?int $manualMonth = null;
    public ?int $manualCount = null;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->countDay = (int) \App\Models\Setting::get('printer_count_day', 27);

        $this->manualYear = (int) now()->year;
        $this->manualMonth = (int) now()->month;
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
     * Último contador registrado hasta el cierre del año anterior, por impresora.
     * Sirve como base para calcular las páginas nuevas de enero del año seleccionado.
     * Estructura: [printer_id => page_count]
     */
    public function getPreviousYearClosing(): array
    {
        $endOfPrevYear = \Carbon\Carbon::create($this->year - 1, 12, 31)->endOfDay();

        $readings = PrinterReading::where('read_at', '<=', $endOfPrevYear)
            ->orderBy('read_at')
            ->get();

        $data = [];
        foreach ($readings as $r) {
            // La última lectura (la más reciente) pisa: queda el cierre del año previo
            $data[$r->printer_id] = $r->page_count;
        }

        return $data;
    }

    /**
     * Páginas nuevas por mes (diferencia con el mes anterior con lectura) del año seleccionado.
     * Estructura: [printer_id => [mes => paginas_nuevas]]
     */
    public function getMonthlyDeltas(): array
    {
        $monthly  = $this->getMonthlyCounts();
        $prevYear = $this->getPreviousYearClosing();

        $deltas = [];

        foreach ($monthly as $printerId => $months) {
            // Base inicial: cierre del año anterior (si existe)
            $prev = $prevYear[$printerId] ?? null;

            for ($m = 1; $m <= 12; $m++) {
                $val = $months[$m] ?? null;

                if ($val === null) {
                    continue;
                }

                if ($prev !== null) {
                    $deltas[$printerId][$m] = $val - $prev;
                }

                $prev = $val;
            }
        }

        return $deltas;
    }

    /**
     * Ventana de los últimos 12 meses (incluye el mes actual).
     * Devuelve un array ordenado del más viejo al más nuevo:
     * [ ['year' => 2025, 'month' => 10, 'label' => 'Oct 2025'], ... ]
     */
    public function getLast12Months(): array
    {
        $meses = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];

        $months = [];
        $cursor = now()->startOfMonth();

        for ($i = 11; $i >= 0; $i--) {
            $date = $cursor->copy()->subMonths($i);
            $months[] = [
                'year'  => (int) $date->year,
                'month' => (int) $date->month,
                'label' => $meses[(int) $date->month] . ' ' . $date->year,
            ];
        }

        return $months;
    }

    /**
     * Contador al cierre de cada mes de la ventana de últimos 12 meses.
     * Estructura: [printer_id => ["YYYY-M" => page_count]]
     */
    public function getLast12MonthsCounts(): array
    {
        $window = $this->getLast12Months();
        $start = \Carbon\Carbon::create($window[0]['year'], $window[0]['month'], 1)->startOfMonth();

        $readings = PrinterReading::where('read_at', '>=', $start)
            ->orderBy('read_at')
            ->get();

        $data = [];

        foreach ($readings as $r) {
            $key = $r->read_at->format('Y-n');
            // La última lectura de cada mes pisa (contador al cierre del mes)
            $data[$r->printer_id][$key] = $r->page_count;
        }

        return $data;
    }

    /**
     * Exporta a CSV el contador al cierre de cada uno de los últimos 12 meses,
     * por impresora, más las páginas del mes (diferencia con el mes previo).
     */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $printers = $this->getPrinters();
        $window   = $this->getLast12Months();
        $counts   = $this->getLast12MonthsCounts();

        $filename = 'conteo-paginas-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($printers, $window, $counts) {
            $handle = fopen('php://output', 'w');

            // BOM para que Excel abra bien los acentos
            fwrite($handle, "\xEF\xBB\xBF");

            // Encabezado: Impresora + un par de columnas por mes (contador y páginas del mes)
            $header = ['Impresora', 'Modelo'];
            foreach ($window as $m) {
                $header[] = $m['label'];
                $header[] = 'Páginas ' . $m['label'];
            }
            fputcsv($handle, $header, ';');

            foreach ($printers as $p) {
                $row  = [$p->name, $p->model ?? ''];
                $prev = null;

                foreach ($window as $m) {
                    $key = $m['year'] . '-' . $m['month'];
                    $val = $counts[$p->id][$key] ?? null;

                    $delta = ($val !== null && $prev !== null) ? ($val - $prev) : null;
                    if ($val !== null) {
                        $prev = $val;
                    }

                    $row[] = $val !== null ? $val : '';
                    $row[] = $delta !== null ? $delta : '';
                }

                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
     * Carga manual del contador al cierre de un mes.
     * Crea o actualiza una lectura fechada el último día del mes indicado,
     * de modo que quede como "cierre" de ese mes en la tabla y el export.
     */
    public function saveManualReading(): void
    {
        $data = [
            'manualPrinterId' => $this->manualPrinterId,
            'manualYear'      => $this->manualYear,
            'manualMonth'     => $this->manualMonth,
            'manualCount'     => $this->manualCount,
        ];

        \Illuminate\Support\Facades\Validator::make($data, [
            'manualPrinterId' => ['required', 'exists:printers,id'],
            'manualYear'      => ['required', 'integer', 'min:2000', 'max:2100'],
            'manualMonth'     => ['required', 'integer', 'min:1', 'max:12'],
            'manualCount'     => ['required', 'integer', 'min:0'],
        ], [], [
            'manualPrinterId' => 'impresora',
            'manualYear'      => 'año',
            'manualMonth'     => 'mes',
            'manualCount'     => 'contador',
        ])->validate();

        // Fechar la lectura al cierre del mes (último día, 23:59).
        $readAt = \Carbon\Carbon::create($this->manualYear, $this->manualMonth, 1)
            ->endOfMonth();

        // Buscar una lectura manual ya existente para ese mes (para actualizarla).
        $existing = PrinterReading::where('printer_id', $this->manualPrinterId)
            ->where('source', 'manual')
            ->whereYear('read_at', $this->manualYear)
            ->whereMonth('read_at', $this->manualMonth)
            ->first();

        if ($existing) {
            $existing->update([
                'page_count' => $this->manualCount,
                'read_at'    => $readAt,
            ]);
        } else {
            PrinterReading::create([
                'printer_id' => $this->manualPrinterId,
                'page_count' => $this->manualCount,
                'read_at'    => $readAt,
                'source'     => 'manual',
            ]);
        }

        // Si el mes cargado es el más reciente con lectura, reflejarlo también
        // en el contador "actual" de la impresora.
        $printer = Printer::find($this->manualPrinterId);
        if ($printer) {
            $latest = $printer->readings()->first(); // readings() ya viene ordenado desc por read_at
            if ($latest) {
                $printer->update([
                    'page_count'    => $latest->page_count,
                    'page_count_at' => $latest->read_at,
                ]);
            }
        }

        $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];

        Notification::make()
            ->title($existing ? 'Contador actualizado' : 'Contador cargado')
            ->body("Contador de {$printer?->name} para {$meses[$this->manualMonth]} {$this->manualYear}: " . number_format($this->manualCount, 0, ',', '.') . ' páginas.')
            ->success()
            ->send();

        // Limpiar solo el valor, dejando impresora/mes/año para cargar el siguiente
        $this->manualCount = null;
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
