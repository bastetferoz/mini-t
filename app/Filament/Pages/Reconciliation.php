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

    /**
     * Resultado de la última conciliación.
     * Cada item: ['description' => ..., 'amount' => ..., 'currency' => ..., 'matched' => bool, 'invoice' => ...]
     */
    public array $results = [];

    public bool $analyzed = false;

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

        $items = $parsed['items'] ?? [];

        // Facturas de los últimos 2 meses para cruzar
        $desde = now()->subMonths(2)->startOfMonth();
        $facturas = Invoice::where('invoice_date', '>=', $desde)
            ->orWhere(function ($q) use ($desde) {
                // También por period/month-year recientes
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
                'description' => $desc,
                'amount'      => $amount,
                'currency'    => $currency,
                'matched'     => $match !== null,
                'invoice'     => $match ? [
                    'provider' => $match->provider,
                    'amount'   => $match->amount,
                    'currency' => $match->currency,
                    'number'   => $match->invoice_number,
                ] : null,
            ];
        }

        $this->results = $results;
        $this->analyzed = true;

        $reconocidos = collect($results)->where('matched', true)->count();

        Notification::make()
            ->title('Conciliación completada')
            ->body("{$reconocidos} de " . count($results) . " consumo(s) con factura cargada.")
            ->success()
            ->send();

        \App\Services\ActivityLogger::facturacion("🧾 Conciliación: {$reconocidos}/" . count($results) . " consumos con factura");
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
    }
}
