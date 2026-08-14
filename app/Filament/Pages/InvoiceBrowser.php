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
        $this->selectedYear = $year;
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
     * Reclasifica las facturas de "otro" usando keywords de proveedores.
     */
    public function reclassifyAll(): void
    {
        $invoices = Invoice::where('provider', 'otro')
            ->when($this->selectedYear, fn ($q) => $q->where('year', $this->selectedYear))
            ->get();

        $providers = \App\Models\InvoiceProvider::where('is_active', true)->get();
        $reclassified = 0;

        foreach ($invoices as $invoice) {
            $searchText = strtolower(implode(' ', array_filter([
                $invoice->service,
                $invoice->reference,
                $invoice->notes,
                $invoice->invoice_number,
            ])));

            foreach ($providers as $provider) {
                foreach ($provider->detection_keywords ?? [] as $keyword) {
                    if (str_contains($searchText, strtolower($keyword))) {
                        $invoice->update(['provider' => $provider->slug]);
                        $reclassified++;
                        break 2;
                    }
                }
            }
        }

        Notification::make()
            ->title("{$reclassified} factura(s) reclasificada(s)")
            ->body($reclassified > 0 ? 'Se movieron a su carpeta correcta.' : 'Ninguna coincidió con las keywords configuradas.')
            ->color($reclassified > 0 ? 'success' : 'warning')
            ->send();
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
            ->modalHeading('Cargar facturas con IA')
            ->modalDescription('Subí una o varias imágenes/PDF. La IA extraerá los datos de cada una.')
            ->modalSubmitActionLabel('Analizar y guardar')
            ->form([
                FileUpload::make('invoice_files')
                    ->label('Archivos de factura')
                    ->required()
                    ->multiple()
                    ->disk('public')
                    ->directory('invoices/temp')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ])
                    ->maxSize(10240)
                    ->helperText('Podés subir varios archivos. Formatos: JPG, PNG, WebP, PDF. Máx 10MB c/u.'),
            ])
            ->action(function (array $data) {
                $files = $data['invoice_files'] ?? [];

                if (empty($files)) {
                    Notification::make()
                        ->title('Error')
                        ->body('No se subió ningún archivo.')
                        ->danger()
                        ->send();
                    return;
                }

                $profile = \App\Models\AiProfile::getDefault();

                if (! $profile) {
                    Notification::make()
                        ->title('Sin perfil de IA configurado')
                        ->body('Andá a Administración → IA y creá un perfil marcado como Predeterminado y Activo.')
                        ->danger()
                        ->send();
                    return;
                }

                $success = 0;
                $errors = [];

                foreach ($files as $index => $filePath) {
                    // Delay entre facturas para evitar rate limit de la IA
                    if ($index > 0) {
                        sleep(3);
                    }

                    $parsed = InvoiceParserService::parse($filePath);

                    if (! $parsed) {
                        $errorMsg = basename($filePath) . ': ' . (InvoiceParserService::$lastError ?? 'Error desconocido');
                        $errors[] = $errorMsg;
                        \App\Services\ActivityLogger::facturacion("❌ Error al cargar factura: {$errorMsg}");
                        continue;
                    }

                    $finalPath = InvoiceParserService::organizeFile($filePath, $parsed);
                    $provider = InvoiceParserService::normalizeProvider($parsed['provider'] ?? null);
                    $period = $parsed['period'] ?? now()->format('Y-m');
                    $parts = explode('-', $period);

                    // Verificar duplicado por número de factura
                    $invoiceNumber = $parsed['invoice_number'] ?? null;
                    if ($invoiceNumber) {
                        $duplicate = Invoice::where('invoice_number', $invoiceNumber)
                            ->where('provider', $provider)
                            ->exists();

                        if ($duplicate) {
                            $errors[] = basename($filePath) . ': Duplicada (Nº ' . $invoiceNumber . ')';
                            \App\Services\ActivityLogger::facturacion("⚠️ Factura duplicada omitida: {$provider} Nº {$invoiceNumber}");
                            continue;
                        }
                    }

                    $invoice = Invoice::create([
                        'provider' => $provider,
                        'company' => $parsed['company'] ?? null,
                        'service' => $parsed['service'] ?? null,
                        'reference' => $parsed['reference'] ?? null,
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

                    // Tipo de cambio
                    if ($invoice->currency === 'ARS') {
                        $rate = \App\Services\ExchangeRateService::getBnaRate(
                            $invoice->invoice_date->format('Y-m-d')
                        );
                        if ($rate) {
                            $invoice->update([
                                'exchange_rate' => $rate,
                                'amount_usd' => round($invoice->amount / $rate, 2),
                            ]);
                        }
                    } elseif ($invoice->currency === 'USD') {
                        $invoice->update([
                            'exchange_rate' => 1,
                            'amount_usd' => $invoice->amount,
                        ]);
                    }

                    $success++;
                    \App\Services\ActivityLogger::facturacion("✓ Factura cargada: {$provider} | \${$parsed['amount']} | {$period}", $invoice);
                }

                // Notificaciones
                if ($success > 0) {
                    Notification::make()
                        ->title("{$success} factura(s) cargada(s)")
                        ->success()
                        ->send();
                }

                if (! empty($errors)) {
                    Notification::make()
                        ->title(count($errors) . ' archivo(s) con error')
                        ->body(implode("\n", array_slice($errors, 0, 5)))
                        ->danger()
                        ->persistent()
                        ->send();
                }
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

    /**
     * Elimina una factura individual.
     */
    public function deleteInvoice(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            return;
        }

        // Borrar archivo si existe
        if ($invoice->file_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($invoice->file_path);
        }

        $provider = $invoice->provider;
        $number = $invoice->invoice_number ?? 'sin número';
        $invoice->delete();

        \App\Services\ActivityLogger::facturacion("🗑️ Factura eliminada: {$provider} Nº {$number}");

        Notification::make()
            ->title('Factura eliminada')
            ->success()
            ->send();
    }

    /**
     * Elimina duplicados (misma invoice_number + provider, queda la primera).
     */
    public function removeDuplicates(): void
    {
        $duplicates = Invoice::selectRaw('invoice_number, provider, COUNT(*) as total, MIN(id) as keep_id')
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->groupBy('invoice_number', 'provider')
            ->having('total', '>', 1)
            ->get();

        $removed = 0;

        foreach ($duplicates as $dup) {
            $toDelete = Invoice::where('invoice_number', $dup->invoice_number)
                ->where('provider', $dup->provider)
                ->where('id', '!=', $dup->keep_id)
                ->get();

            foreach ($toDelete as $invoice) {
                if ($invoice->file_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($invoice->file_path);
                }
                $invoice->delete();
                $removed++;
            }
        }

        \App\Services\ActivityLogger::facturacion("🧹 Eliminados {$removed} duplicados");

        Notification::make()
            ->title($removed > 0 ? "{$removed} duplicado(s) eliminado(s)" : 'Sin duplicados')
            ->color($removed > 0 ? 'success' : 'info')
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }
}
