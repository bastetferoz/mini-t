<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Services\InvoiceParserService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class Reconciliation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string | \UnitEnum | null $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Conciliación';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Conciliación de consumos';

    protected string $view = 'filament.pages.reconciliation';

    public ?array $data = [];

    /** Nombre de la persona cuyos consumos se quieren conciliar (tal como figura en el resumen). */
    public string $targetName = 'RODOLFO DURANTE';

    /** Texto del resumen pegado manualmente (alternativa a subir el PDF). */
    public string $pastedText = '';

    /** Mes y año de la conciliación (para guardar). */
    public ?int $month = null;
    public ?int $year = null;

    /**
     * Resultado de la conciliación actual. Cada item:
     * ['description', 'amount', 'currency', 'status' (green|yellow|red), 'invoice_info']
     */
    public array $results = [];

    public bool $analyzed = false;

    /** Id de la conciliación guardada que se está viendo (null = nueva). */
    public ?int $currentId = null;

    public function mount(): void
    {
        $this->month = (int) now()->month;
        $this->year = (int) now()->year;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') || auth()->user()->hasRole('it');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('statement')
                    ->label('Resumen de tarjeta')
                    ->required()
                    ->disk('public')
                    ->directory('reconciliation/temp')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(10240)
                    ->helperText('Subí el PDF o imagen del resumen. Formatos: JPG, PNG, WebP, PDF. Máx 10MB.'),
            ])
            ->statePath('data');
    }

    /**
     * Analiza el resumen subido: extrae consumos con IA y los cruza contra
     * las facturas cargadas en los últimos 2 meses.
     */
    public function analyze(): void
    {
        // getState() fuerza a Filament a persistir el archivo subido en su
        // directorio final del disco y devuelve la ruta real (no el temporal de PHP).
        $state = $this->form->getState();

        $file = $state['statement'] ?? null;

        if (blank($file)) {
            Notification::make()->title('Subí un resumen primero')->warning()->send();
            return;
        }

        // FileUpload puede devolver string o array [clave => ruta]
        $filePath = is_array($file) ? (reset($file) ?: null) : $file;

        if (! $filePath) {
            Notification::make()->title('Archivo inválido')->danger()->send();
            return;
        }

        // Extraer la lista de consumos del resumen con IA (solo los de la persona objetivo)
        $parsed = InvoiceParserService::parseStatement($filePath, trim($this->targetName) ?: null);

        if (! $parsed) {
            Notification::make()
                ->title('No se pudo leer el resumen')
                ->body(InvoiceParserService::$lastError ?? 'Error desconocido.')
                ->danger()
                ->send();
            return;
        }

        $this->conciliar($parsed['items'] ?? []);
    }

    /**
     * Analiza texto pegado manualmente (sin IA): una línea por consumo,
     * con la descripción y el monto (el monto al final de la línea).
     */
    public function analyzeText(): void
    {
        $texto = trim($this->pastedText ?? '');

        if ($texto === '') {
            Notification::make()->title('Pegá el texto del resumen primero')->warning()->send();
            return;
        }

        $items = $this->parsePastedText($texto);

        if (empty($items)) {
            Notification::make()
                ->title('No se detectaron consumos')
                ->body('Revisá que cada línea tenga la descripción y el monto (ej: GOOGLE ... 19,99).')
                ->warning()
                ->send();
            return;
        }

        $this->conciliar($items);
    }

    /**
     * Parsea texto pegado. Por cada línea toma el último número como monto
     * y el resto como descripción. Soporta formato AR (1.234,56) y US (1,234.56).
     */
    private function parsePastedText(string $texto): array
    {
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $texto) as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }

            // Buscar todos los números de la línea; el último se toma como monto.
            if (! preg_match_all('/-?[\d.,]+/', $linea, $nums) || empty($nums[0])) {
                continue;
            }

            $rawAmount = end($nums[0]);
            $amount = $this->normalizeAmount($rawAmount);

            if ($amount <= 0) {
                continue;
            }

            $currency = preg_match('/USD|U\$S/i', $linea) ? 'USD' : 'ARS';

            // Descripción = la línea, quitando de la cola: moneda + monto (en cualquier orden).
            $desc = $linea;
            // Quitar moneda/símbolo al final
            $desc = preg_replace('/\s*(USD|ARS|U\$S|\$)\s*$/i', '', $desc);
            // Quitar el monto al final
            $desc = preg_replace('/\s*' . preg_quote($rawAmount, '/') . '\s*$/', '', $desc);
            // Quitar moneda/símbolo que pudiera haber quedado antes del monto
            $desc = trim(preg_replace('/\s*(USD|ARS|U\$S|\$)\s*$/i', '', $desc));

            if ($desc === '') {
                continue;
            }

            $items[] = ['description' => $desc, 'amount' => $amount, 'currency' => $currency];
        }

        return $items;
    }

    /**
     * Normaliza un monto en texto (AR o US) a float.
     */
    private function normalizeAmount(string $raw): float
    {
        $raw = trim($raw);

        // Si tiene coma Y punto: el último separador es el decimal.
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                // Formato AR: 1.234,56
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                // Formato US: 1,234.56
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, ',')) {
            // Solo coma: decimal AR (1234,56) o miles US (1,234)
            // Si hay 2 dígitos después de la coma, es decimal.
            if (preg_match('/,\d{2}$/', $raw)) {
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        }

        return (float) $raw;
    }

    /**
     * Cruza los consumos contra las facturas de los últimos 2 meses y arma la tabla.
     */
    private function conciliar(array $items): void
    {
        // Facturas de los últimos 2 meses para cruzar
        $desde = now()->subMonths(2)->startOfMonth();
        $facturas = Invoice::where('invoice_date', '>=', $desde)
            ->orWhere(function ($q) use ($desde) {
                $q->whereYear('created_at', '>=', $desde->year);
            })
            ->get(['id', 'provider', 'service', 'reference', 'amount', 'currency', 'invoice_number', 'month', 'year']);

        $results = [];

        foreach ($items as $item) {
            $desc = trim($item['description'] ?? '');
            $amount = (float) ($item['amount'] ?? 0);
            $currency = $item['currency'] ?? 'ARS';

            if ($desc === '') {
                continue;
            }

            $match = $this->findMatch($desc, $amount, $facturas);

            $results[] = [
                'description'  => $desc,
                'amount'       => $amount,
                'currency'     => $currency,
                // green si matcheó factura; si no, red (el usuario puede pasar a yellow)
                'status'       => $match ? 'green' : 'red',
                'invoice_info' => $match
                    ? ucfirst($match->provider) . ' · ' . number_format((float) $match->amount, 2, ',', '.') . ' ' . $match->currency
                        . ($match->invoice_number ? ' · Nº ' . $match->invoice_number : '')
                    : null,
            ];
        }

        $this->results = $results;
        $this->analyzed = true;
        $this->currentId = null; // es una conciliación nueva sin guardar

        $reconocidos = collect($results)->where('status', 'green')->count();

        Notification::make()
            ->title('Conciliación completada')
            ->body("{$reconocidos} de " . count($results) . " consumo(s) con factura cargada.")
            ->success()
            ->send();

        \App\Services\ActivityLogger::facturacion("🧾 Conciliación: {$reconocidos}/" . count($results) . " consumos con factura");
    }

    /** Cambia el estado de un item (green|yellow|red). */
    public function setStatus(int $index, string $status): void
    {
        if (isset($this->results[$index]) && in_array($status, ['green', 'yellow', 'red'], true)) {
            $this->results[$index]['status'] = $status;
        }
    }

    /** Cicla el estado de un item con un clic: red → yellow → green → red. */
    public function cycleStatus(int $index): void
    {
        if (! isset($this->results[$index])) {
            return;
        }

        $this->results[$index]['status'] = match ($this->results[$index]['status']) {
            'red'    => 'yellow',
            'yellow' => 'green',
            default  => 'red', // green → red
        };
    }

    /** Elimina un item de la lista. */
    public function removeItem(int $index): void
    {
        if (isset($this->results[$index])) {
            array_splice($this->results, $index, 1);
        }
    }

    /** Guarda (o actualiza) la conciliación del mes/persona. */
    public function save(): void
    {
        if (empty($this->results)) {
            Notification::make()->title('No hay nada para guardar')->warning()->send();
            return;
        }

        $person = trim($this->targetName) ?: 'Sin nombre';
        $month = (int) ($this->month ?: now()->month);
        $year = (int) ($this->year ?: now()->year);

        // Una conciliación por persona + mes + año: se reemplaza si ya existe.
        $rec = \App\Models\Reconciliation::updateOrCreate(
            ['person_name' => $person, 'month' => $month, 'year' => $year],
            ['created_by' => auth()->id()],
        );

        // Reemplazar los items
        $rec->items()->delete();
        foreach ($this->results as $r) {
            $rec->items()->create([
                'description'  => $r['description'],
                'amount'       => $r['amount'],
                'currency'     => $r['currency'],
                'status'       => $r['status'],
                'invoice_info' => $r['invoice_info'] ?? null,
            ]);
        }

        $this->currentId = $rec->id;

        Notification::make()
            ->title('Conciliación guardada')
            ->body("{$person} · " . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . "/{$year}")
            ->success()
            ->send();

        \App\Services\ActivityLogger::facturacion("💾 Conciliación guardada: {$person} {$month}/{$year} (" . count($this->results) . " items)");
    }

    /** Conciliaciones guardadas (para el selector de reapertura). */
    public function getSaved(): \Illuminate\Support\Collection
    {
        return \App\Models\Reconciliation::orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('person_name')
            ->get();
    }

    /** Reabre una conciliación guardada. */
    public function load(int $id): void
    {
        $rec = \App\Models\Reconciliation::with('items')->find($id);

        if (! $rec) {
            Notification::make()->title('No se encontró la conciliación')->danger()->send();
            return;
        }

        $this->targetName = $rec->person_name;
        $this->month = $rec->month;
        $this->year = $rec->year;
        $this->currentId = $rec->id;

        $this->results = $rec->items->map(fn ($it) => [
            'description'  => $it->description,
            'amount'       => (float) $it->amount,
            'currency'     => $it->currency,
            'status'       => $it->status,
            'invoice_info' => $it->invoice_info,
        ])->values()->all();

        $this->analyzed = true;

        Notification::make()->title('Conciliación cargada')->success()->send();
    }

    /**
     * Busca una factura que coincida con un consumo del resumen.
     * Match por nombre de proveedor/servicio (texto) y, si hay monto, por monto aproximado.
     */
    private function findMatch(string $desc, float $amount, $facturas): ?Invoice
    {
        $descLower = mb_strtolower($desc);

        $candidatas = $facturas->filter(function ($f) use ($descLower) {
            $texto = mb_strtolower(trim(($f->provider ?? '') . ' ' . ($f->service ?? '') . ' ' . ($f->reference ?? '')));

            // Coincidencia si el nombre del consumo aparece en el texto de la factura o viceversa.
            return $texto !== '' && (
                str_contains($texto, $descLower) ||
                str_contains($descLower, mb_strtolower($f->provider ?? '____nope____'))
            );
        });

        if ($candidatas->isEmpty()) {
            return null;
        }

        // Si hay monto, priorizar la factura con monto más cercano (tolerancia 1%).
        if ($amount > 0) {
            $porMonto = $candidatas->first(function ($f) use ($amount) {
                $fa = (float) $f->amount;
                if ($fa <= 0) {
                    return false;
                }
                return abs($fa - $amount) <= max(0.01, $amount * 0.01);
            });

            if ($porMonto) {
                return $porMonto;
            }
        }

        // Si no hay match exacto por monto, devolver la primera por nombre.
        return $candidatas->first();
    }

    public function reset2(): void
    {
        $this->results = [];
        $this->analyzed = false;
        $this->data = [];
        $this->pastedText = '';
        $this->currentId = null;
    }

    /** Datos comunes para el PDF/correo. */
    private function pdfData(): array
    {
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

        return [
            'person'   => trim($this->targetName) ?: 'Sin nombre',
            'mesLabel' => ($meses[(int) $this->month] ?? $this->month) . ' ' . $this->year,
            'results'  => $this->results,
            'green'    => collect($this->results)->where('status', 'green')->count(),
            'yellow'   => collect($this->results)->where('status', 'yellow')->count(),
            'red'      => collect($this->results)->where('status', 'red')->count(),
            'total'    => count($this->results),
            'fecha'    => now()->format('d/m/Y H:i'),
        ];
    }

    private function pdfFilename(): string
    {
        return 'conciliacion-' . \Illuminate\Support\Str::slug(trim($this->targetName) ?: 'sin-nombre')
            . '-' . $this->year . str_pad((string) $this->month, 2, '0', STR_PAD_LEFT) . '.pdf';
    }

    /** Exporta la conciliación actual a PDF con una estructura similar a la pantalla. */
    public function exportPdf()
    {
        if (empty($this->results)) {
            Notification::make()->title('No hay nada para exportar')->warning()->send();
            return;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reconciliation', $this->pdfData());

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $this->pdfFilename(),
        );
    }

    /** Envía la conciliación por correo usando la plantilla 'conciliation', con el PDF adjunto. */
    public function sendEmail(): void
    {
        if (empty($this->results)) {
            Notification::make()->title('No hay nada para enviar')->warning()->send();
            return;
        }

        $data = $this->pdfData();

        // Generar el PDF para adjuntar
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reconciliation', $data);

        $variables = [
            'person_name' => $data['person'],
            'period'      => $data['mesLabel'],
            'green'       => (string) $data['green'],
            'yellow'      => (string) $data['yellow'],
            'red'         => (string) $data['red'],
            'total'       => (string) $data['total'],
            'date'        => now()->format('d/m/Y'),
        ];

        $ok = \App\Services\MailTemplateService::send('conciliation', $variables, null, [
            [
                'data' => $pdf->output(),
                'name' => $this->pdfFilename(),
                'mime' => 'application/pdf',
            ],
        ]);

        if ($ok) {
            Notification::make()
                ->title('Correo enviado')
                ->body('La conciliación se envió con el PDF adjunto.')
                ->success()
                ->send();
            \App\Services\ActivityLogger::facturacion("📧 Conciliación enviada por correo: {$data['person']} · {$data['mesLabel']}");
        } else {
            Notification::make()
                ->title('No se pudo enviar')
                ->body('Revisá que exista una plantilla activa de tipo "Conciliación de consumos" (Administración → Plantillas de correo) y un perfil SMTP configurado.')
                ->danger()
                ->send();
        }
    }
}
