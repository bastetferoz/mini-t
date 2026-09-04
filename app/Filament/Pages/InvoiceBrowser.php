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
        $noMatch = [];

        foreach ($invoices as $invoice) {
            // Buscar en todos los campos de texto relevantes incluyendo file_path
            $searchText = strtolower(implode(' ', array_filter([
                $invoice->service,
                $invoice->reference,
                $invoice->notes,
                $invoice->invoice_number,
                $invoice->file_path,
                $invoice->project,
            ])));

            $matched = false;
            foreach ($providers as $provider) {
                foreach ($provider->detection_keywords ?? [] as $keyword) {
                    if (str_contains($searchText, strtolower($keyword))) {
                        $invoice->update(['provider' => $provider->slug]);
                        $reclassified++;
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (! $matched) {
                $noMatch[] = "#{$invoice->id}: [{$invoice->service}] [{$invoice->reference}] [{$invoice->invoice_number}]";
            }
        }

        $body = $reclassified > 0
            ? "Se movieron {$reclassified} a su carpeta correcta."
            : 'Ninguna coincidió con las keywords configuradas.';

        if (! empty($noMatch) && count($noMatch) <= 5) {
            $body .= "\nSin match: " . implode(' | ', $noMatch);
        }

        Notification::make()
            ->title("{$reclassified} factura(s) reclasificada(s)")
            ->body($body)
            ->color($reclassified > 0 ? 'success' : 'warning')
            ->duration(10000)
            ->send();
    }

    /**
     * Reclasifica facturas de "otro" usando la IA (re-corre la etapa 1).
     * Solo para facturas que tienen archivo adjunto.
     */
    public function reclassifyWithAi(): void
    {
        $invoices = Invoice::where('provider', $this->selectedProvider)
            ->when($this->selectedYear, fn ($q) => $q->where('year', $this->selectedYear))
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get();

        if ($invoices->isEmpty()) {
            Notification::make()
                ->title('Sin facturas para reprocesar')
                ->body('No hay facturas con archivo adjunto en esta carpeta.')
                ->warning()
                ->send();
            return;
        }

        $isOtro = ($this->selectedProvider === 'otro');
        $queued = 0;

        foreach ($invoices as $index => $invoice) {
            \App\Jobs\ReprocessInvoiceWithAi::dispatch($invoice->id, $isOtro)
                ->delay(now()->addSeconds($index * 5)); // 5s entre cada una
            $queued++;
        }

        $action = $isOtro ? 'reclasificación' : 'reprocesamiento';

        Notification::make()
            ->title("{$queued} factura(s) en cola para {$action}")
            ->body("Se procesan en segundo plano. Refrescá la página en unos minutos.")
            ->success()
            ->send();

        \App\Services\ActivityLogger::facturacion("🤖 {$queued} factura(s) encoladas para {$action} con IA en '{$this->selectedProvider}'");
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

                $queued = 0;

                foreach ($files as $filePath) {
                    \App\Jobs\ProcessInvoiceFile::dispatch($filePath)
                        ->delay(now()->addSeconds($queued * 5)); // 5s entre cada una
                    $queued++;
                }

                Notification::make()
                    ->title("{$queued} factura(s) en cola")
                    ->body('Se procesan en segundo plano. Refrescá la página en unos minutos para verlas.')
                    ->success()
                    ->send();

                \App\Services\ActivityLogger::facturacion("📥 {$queued} factura(s) encoladas para procesamiento con IA");
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
     * Fuerza la asignación de proveedor a una factura individual.
     */
    public function reassignProvider(int $invoiceId, string $newProvider): void
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            return;
        }

        $oldProvider = $invoice->provider;

        if ($oldProvider === $newProvider) {
            return;
        }

        $invoice->update(['provider' => $newProvider]);

        $label = $this->getProviderLabel($newProvider);
        \App\Services\ActivityLogger::facturacion("🔀 Factura #{$invoice->id} movida de '{$oldProvider}' a '{$newProvider}'");

        Notification::make()
            ->title("Factura movida a {$label}")
            ->success()
            ->send();
    }

    /**
     * Cambia manualmente el período (mes) al que se asigna una factura.
     * Recibe un valor "YYYY-MM" (input type=month) y actualiza period + month/year.
     * Impacta directo en el análisis, que suma por month/year.
     * Se respeta la elección manual del usuario por sobre la heurística automática.
     */
    public function updatePeriod(int $invoiceId, string $period): void
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            return;
        }

        if (! preg_match('/^(\d{4})-(\d{1,2})$/', trim($period), $m)) {
            Notification::make()
                ->title('Período inválido')
                ->body('Usá el formato YYYY-MM (ej: 2026-03).')
                ->danger()
                ->send();
            return;
        }

        $year = (int) $m[1];
        $month = (int) $m[2];

        $anterior = sprintf('%d-%02d', (int) $invoice->year, (int) $invoice->month);

        $invoice->period = sprintf('%04d-%02d', $year, $month);
        $invoice->month = $month;
        $invoice->year = $year;
        // saveQuietly evita que el hook saving() recalcule y pise la elección manual.
        $invoice->saveQuietly();

        $nuevo = sprintf('%d-%02d', $year, $month);
        \App\Services\ActivityLogger::facturacion("📅 Factura #{$invoice->id} ({$invoice->provider}) reasignada de {$anterior} a {$nuevo} (manual)");

        Notification::make()
            ->title('Período actualizado')
            ->body("La factura ahora se cuenta en {$nuevo}.")
            ->success()
            ->send();
    }

    /**
     * Devuelve las opciones de proveedor disponibles para el selector inline.
     */
    public function getProviderOptions(): array
    {
        return \App\Models\InvoiceProvider::getOptions() + ['otro' => 'Otro'];
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
