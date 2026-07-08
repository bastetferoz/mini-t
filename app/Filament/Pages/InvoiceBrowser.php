<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Services\InvoiceParserService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceBrowser extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-plus';

    protected static string | \UnitEnum | null $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Carga';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Facturas';

    protected string $view = 'filament.pages.invoice-browser';

    public ?string $selectedProvider = null;
    public ?int $selectedYear = null;
    public string $searchProvider = '';

    public function goToRoot(): void
    {
        $this->selectedProvider = null;
        $this->selectedYear = null;
    }

    public function goToProvider(string $provider): void
    {
        $this->selectedProvider = $provider;
        $this->selectedYear = null;
    }

    public function goToYear(int $year): void
    {
        // Redirigir al resource con filtros de proveedor y año
        $url = \App\Filament\Resources\Invoices\InvoiceResource::getUrl('index', [
            'tableFilters' => [
                'provider' => ['value' => $this->selectedProvider],
                'year' => ['value' => $year],
            ],
        ]);

        $this->redirect($url);
    }

    /**
     * Proveedores con cantidad de facturas.
     */
    public function getProviders(): Collection
    {
        $query = Invoice::selectRaw('provider, COUNT(*) as total')
            ->groupBy('provider')
            ->orderBy('provider');

        if ($this->searchProvider) {
            $search = strtolower($this->searchProvider);
            $query->where(function ($q) use ($search) {
                $q->where('provider', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Años disponibles para el proveedor seleccionado.
     */
    public function getYears(): Collection
    {
        return Invoice::selectRaw('year, COUNT(*) as total')
            ->where('provider', $this->selectedProvider)
            ->groupBy('year')
            ->orderByDesc('year')
            ->get();
    }

    /**
     * Facturas del proveedor y año seleccionados.
     */
    public function getInvoices(): Collection
    {
        return Invoice::where('provider', $this->selectedProvider)
            ->where('year', $this->selectedYear)
            ->orderBy('month')
            ->get();
    }

    /**
     * Label amigable del proveedor.
     */
    public function getProviderLabel(string $provider): string
    {
        $invoiceProvider = \App\Models\InvoiceProvider::where('slug', $provider)->first();

        if ($invoiceProvider) {
            return $invoiceProvider->name;
        }

        return match ($provider) {
            'telecom' => 'Telecom',
            'metrotel' => 'Metrotel',
            'amazon' => 'Amazon (AWS)',
            'microsoft' => 'Microsoft',
            'google' => 'Google',
            'movistar' => 'Movistar',
            'claro' => 'Claro',
            'iplan' => 'iPlan',
            'otro' => 'Otro',
            default => ucfirst($provider),
        };
    }

    /**
     * Acción: Cargar con IA.
     */
    public function uploadAiAction(): Action
    {
        return Action::make('uploadAi')
            ->label('Cargar con IA')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->modalHeading('Cargar factura con IA')
            ->modalDescription('Subí una imagen o PDF de la factura. La IA extraerá automáticamente los datos.')
            ->modalSubmitActionLabel('Analizar y guardar')
            ->form([
                FileUpload::make('invoice_file')
                    ->label('Archivo de factura')
                    ->required()
                    ->disk('public')
                    ->directory('invoices/temp')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ])
                    ->maxSize(10240)
                    ->helperText('Formatos: JPG, PNG, WebP, PDF. Máx 10MB.'),
            ])
            ->action(function (array $data) {
                $filePath = $data['invoice_file'];

                if (! $filePath) {
                    Notification::make()
                        ->title('Error')
                        ->body('No se subió ningún archivo.')
                        ->danger()
                        ->send();
                    return;
                }

                $parsed = InvoiceParserService::parse($filePath);

                if (! $parsed) {
                    $profile = \App\Models\AiProfile::getDefault();

                    if (! $profile) {
                        Notification::make()
                            ->title('Sin perfil de IA configurado')
                            ->body('Andá a Administración → IA y creá un perfil marcado como Predeterminado y Activo.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->title('Error al analizar')
                        ->body(InvoiceParserService::$lastError ?? 'Error desconocido.')
                        ->danger()
                        ->persistent()
                        ->send();
                    return;
                }

                $finalPath = InvoiceParserService::organizeFile($filePath, $parsed);
                $provider = InvoiceParserService::normalizeProvider($parsed['provider'] ?? null);
                $period = $parsed['period'] ?? now()->format('Y-m');
                $parts = explode('-', $period);

                $invoice = Invoice::create([
    'provider' => $provider,
    'service' => $parsed['service'] ?? null,
    'amount' => $parsed['amount'] ?? 0,
    'currency' => $parsed['currency'] ?? 'ARS',
    'invoice_date' => $parsed['invoice_date'] ?? now()->toDateString(),
    'period' => $period,
    'month' => (int) ($parts[1] ?? now()->month),
    'year' => (int) ($parts[0] ?? now()->year),
    'invoice_number' => $parsed['invoice_number'] ?? null,
    'file_path' => $finalPath,
    'notes' => 'Cargada automáticamente con IA',
]);

// ✅ Tipo de cambio
if ($invoice->currency === 'ARS') {
    $rate = \App\Services\ExchangeRateService::getBnaRate(
        $invoice->invoice_date->format('Y-m-d')
    );
    if ($rate) {
        $invoice->update([
            'exchange_rate' => $rate,
            'amount_usd'    => round($invoice->amount / $rate, 2),
        ]);
    }
} elseif ($invoice->currency === 'USD') {
    $invoice->update([
        'exchange_rate' => 1,
        'amount_usd'    => $invoice->amount,
    ]);
}

                Notification::make()
                    ->title('Factura cargada')
                    ->body("Proveedor: {$provider} | Monto: \${$parsed['amount']} | Período: {$period}")
                    ->success()
                    ->send();
            });
    }

    /**
     * Acción: Carga manual.
     */
    public function manualCreateAction(): Action
    {
        return Action::make('manualCreate')
            ->label('Carga manual')
            ->color('gray')
            ->url(\App\Filament\Resources\Invoices\InvoiceResource::getUrl('create'));
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }
}
