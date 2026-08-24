<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class InvoiceAnalysis extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'Facturación';
    protected static ?string $navigationLabel = 'Análisis';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Análisis de facturación';

    protected string $view = 'filament.pages.invoice-analysis';

    // Filtros
    public string $selectedYear = '';
    public string $displayCurrency = 'USD'; // Todo normalizado a USD
    public array $selectedProviders = [];
    public array $selectedCompanies = [];
    public string $viewMode = 'providers'; // providers, companies, projects
    public ?string $selectedChartProvider = null; // Proveedor seleccionado para gráfico de velas

    // Vistas guardadas
    public ?int $activeViewId = null;
    public string $newViewName = '';
    public bool $showSaveView = false;

    public function mount(): void
    {
        $this->selectedYear = (string) now()->year;
        $this->selectedProviders = $this->getAvailableProviders();
        $this->selectedCompanies = $this->getAvailableCompanies();

        // Cargar vista por defecto si existe
        $defaultView = \App\Models\AnalysisView::forCurrentUser()->where('is_default', true)->first();
        if ($defaultView) {
            $this->applyViewFilters($defaultView);
        }
    }

    public function selectYear(string $year): void
    {
        $this->selectedYear = $year;
        $this->selectedChartProvider = null;
    }

    // ─── OPCIONES DISPONIBLES ───

    public function getAvailableProviders(): array
    {
        return Invoice::selectRaw('DISTINCT provider')
            ->whereNotNull('provider')
            ->where('provider', '!=', 'otro')
            ->orderBy('provider')
            ->pluck('provider')
            ->toArray();
    }

    public function getAvailableCompanies(): array
    {
        return ['novatech', 'phinxlab'];
    }

    public function getAvailableYears(): array
    {
        $years = Invoice::selectRaw('DISTINCT year')
            ->whereNotNull('year')
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        return empty($years) ? [(int) now()->year] : $years;
    }

    // ─── TOGGLES ───

    public function toggleProvider(string $provider): void
    {
        if (in_array($provider, $this->selectedProviders)) {
            $this->selectedProviders = array_values(array_diff($this->selectedProviders, [$provider]));
        } else {
            $this->selectedProviders[] = $provider;
        }
    }

    public function toggleCompany(string $company): void
    {
        if (in_array($company, $this->selectedCompanies)) {
            $this->selectedCompanies = array_values(array_diff($this->selectedCompanies, [$company]));
        } else {
            $this->selectedCompanies[] = $company;
        }
    }

    public function selectAllProviders(): void
    {
        $this->selectedProviders = $this->getAvailableProviders();
    }

    public function deselectAllProviders(): void
    {
        $this->selectedProviders = [];
    }

    public function toggleChartProvider(string $provider): void
    {
        $this->selectedChartProvider = $this->selectedChartProvider === $provider ? null : $provider;
    }

    /**
     * Datos para el gráfico de velas del proveedor seleccionado.
     * Devuelve datos de los últimos 2 años para comparar.
     */
    public function getCandlestickData(): array
    {
        if (! $this->selectedChartProvider) {
            return [];
        }

        $currentYear = (int) $this->selectedYear;
        $previousYear = $currentYear - 1;
        $provider = $this->selectedChartProvider;
        $convertToUsd = ($this->viewMode === 'companies');

        $result = ['current' => [], 'previous' => [], 'provider' => $provider];

        foreach ([$currentYear, $previousYear] as $year) {
            $key = $year === $currentYear ? 'current' : 'previous';

            for ($m = 1; $m <= 12; $m++) {
                if ($convertToUsd) {
                    $query = Invoice::where('year', $year)
                        ->where('provider', $provider)
                        ->where('month', $m)
                        ->when(! empty($this->selectedCompanies), fn ($q) => $q->where(function ($sq) {
                            $sq->whereIn('company', $this->selectedCompanies)
                               ->orWhereNull('company')
                               ->orWhere('company', '');
                        }));

                    $usd = (float) (clone $query)->where('currency', 'USD')->sum('amount');
                    $ars = (float) (clone $query)->where('currency', 'ARS')->sum('amount');
                    $arsInUsd = $ars > 0 ? (\App\Services\ExchangeRateService::convertToUsd($ars, $year, $m) ?? 0) : 0;
                    $result[$key][$m] = round($usd + $arsInUsd, 2);
                } else {
                    $result[$key][$m] = round((float) Invoice::where('year', $year)
                        ->where('provider', $provider)
                        ->where('month', $m)
                        ->when(! empty($this->selectedCompanies), fn ($q) => $q->where(function ($sq) {
                            $sq->whereIn('company', $this->selectedCompanies)
                               ->orWhereNull('company')
                               ->orWhere('company', '');
                        }))
                        ->sum('amount'), 2);
                }
            }
        }

        return $result;
    }

    public function selectAllCompanies(): void
    {
        $this->selectedCompanies = $this->getAvailableCompanies();
    }

    public function deselectAllCompanies(): void
    {
        $this->selectedCompanies = [];
    }

    // ─── VISTAS GUARDADAS ───

    /**
     * Obtiene las vistas guardadas del usuario actual.
     */
    public function getSavedViews(): array
    {
        return \App\Models\AnalysisView::forCurrentUser()
            ->get()
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'is_default' => $v->is_default])
            ->toArray();
    }

    /**
     * Guarda la vista actual con un nombre.
     */
    public function saveView(): void
    {
        $name = trim($this->newViewName);

        if (blank($name)) {
            $this->addError('newViewName', 'El nombre es obligatorio.');
            return;
        }

        $filters = [
            'providers' => $this->selectedProviders,
            'companies' => $this->selectedCompanies,
            'viewMode' => $this->viewMode,
        ];

        $view = \App\Models\AnalysisView::create([
            'name' => $name,
            'filters' => $filters,
            'user_id' => auth()->id(),
            'is_default' => false,
        ]);

        $this->activeViewId = $view->id;
        $this->newViewName = '';
        $this->showSaveView = false;

        \Filament\Notifications\Notification::make()
            ->title("Vista \"{$name}\" guardada")
            ->success()
            ->send();
    }

    /**
     * Carga una vista guardada.
     */
    public function loadView(int $viewId): void
    {
        $view = \App\Models\AnalysisView::forCurrentUser()->find($viewId);

        if (! $view) {
            return;
        }

        $this->applyViewFilters($view);
    }

    /**
     * Aplica los filtros de una vista.
     */
    private function applyViewFilters(\App\Models\AnalysisView $view): void
    {
        $filters = $view->filters;

        $this->selectedProviders = $filters['providers'] ?? $this->getAvailableProviders();
        $this->selectedCompanies = $filters['companies'] ?? $this->getAvailableCompanies();
        $this->viewMode = $filters['viewMode'] ?? 'providers';
        $this->activeViewId = $view->id;
        $this->selectedChartProvider = null;
    }

    /**
     * Marca/desmarca una vista como predeterminada.
     */
    public function toggleDefaultView(int $viewId): void
    {
        // Quitar default de todas las del usuario
        \App\Models\AnalysisView::where('user_id', auth()->id())->update(['is_default' => false]);

        $view = \App\Models\AnalysisView::forCurrentUser()->find($viewId);
        if ($view) {
            $view->update(['is_default' => true]);
        }
    }

    /**
     * Elimina una vista guardada.
     */
    public function deleteView(int $viewId): void
    {
        $view = \App\Models\AnalysisView::forCurrentUser()->find($viewId);

        if (! $view) {
            return;
        }

        $name = $view->name;
        $view->delete();

        if ($this->activeViewId === $viewId) {
            $this->activeViewId = null;
        }

        \Filament\Notifications\Notification::make()
            ->title("Vista \"{$name}\" eliminada")
            ->success()
            ->send();
    }

    /**
     * Desactiva la vista activa y muestra todo.
     */
    public function clearView(): void
    {
        $this->activeViewId = null;
        $this->selectedProviders = $this->getAvailableProviders();
        $this->selectedCompanies = $this->getAvailableCompanies();
        $this->viewMode = 'providers';
        $this->selectedChartProvider = null;
    }

    // ─── QUERIES ───

    private function baseQuery()
    {
        $query = Invoice::where('year', $this->selectedYear);

        if (! empty($this->selectedProviders)) {
            $query->whereIn('provider', $this->selectedProviders);
        }

        if (! empty($this->selectedCompanies)) {
            $query->where(function ($q) {
                $q->whereIn('company', $this->selectedCompanies)
                  ->orWhereNull('company')
                  ->orWhere('company', '');
            });
        }

        return $query;
    }

    /**
     * Total del año en USD (usa amount_usd si existe, sino amount para USD).
     */
    public function getYearTotal(): float
    {
        return $this->baseQuery()->sum('amount_usd') ?: $this->baseQuery()->where('currency', 'USD')->sum('amount');
    }

    public function getYearTotalArs(): float
    {
        return $this->baseQuery()->where('currency', 'ARS')->sum('amount');
    }

    /**
     * Datos mes a mes.
     */
    public function getMonthlyData(): array
    {
        $data = $this->baseQuery()
            ->selectRaw('month, SUM(COALESCE(amount_usd, amount)) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = round((float) ($data[$m] ?? 0), 2);
        }

        return $months;
    }

    /**
     * Desglose por proveedor.
     */
    public function getByProvider(): array
    {
        return $this->baseQuery()
            ->selectRaw('provider, SUM(COALESCE(amount_usd, amount)) as total')
            ->groupBy('provider')
            ->orderByDesc('total')
            ->pluck('total', 'provider')
            ->toArray();
    }

    /**
     * Desglose por empresa.
     */
    public function getByCompany(): array
    {
        return $this->baseQuery()
            ->selectRaw('company, SUM(COALESCE(amount_usd, amount)) as total')
            ->whereNotNull('company')
            ->where('company', '!=', '')
            ->groupBy('company')
            ->orderByDesc('total')
            ->pluck('total', 'company')
            ->toArray();
    }

    /**
     * Mes a mes por empresa (para tabla tipo Excel).
     */
    public function getMonthlyByCompany(): array
    {
        $result = [];
        $companies = $this->getAvailableCompanies();

        foreach ($companies as $company) {
            if (! in_array($company, $this->selectedCompanies)) continue;

            $data = Invoice::where('year', $this->selectedYear)
                ->where('company', $company)
                ->whereIn('provider', $this->selectedProviders)
                ->selectRaw('month, SUM(COALESCE(amount_usd, amount)) as total')
                ->groupBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $months[$m] = round((float) ($data[$m] ?? 0), 2);
            }

            $result[$company] = $months;
        }

        return $result;
    }

    /**
     * Mes a mes por proveedor.
     * Si viewMode es 'companies' (Convertido a USD), convierte ARS→USD.
     * Si viewMode es 'providers' (Original), muestra montos tal cual.
     */
    public function getMonthlyByProvider(): array
    {
        $result = [];
        $convertToUsd = ($this->viewMode === 'companies');
        $year = (int) $this->selectedYear;

        foreach ($this->selectedProviders as $provider) {
            $months = [];

            if ($convertToUsd) {
                for ($m = 1; $m <= 12; $m++) {
                    $baseQ = Invoice::where('year', $year)
                        ->where('provider', $provider)
                        ->where('month', $m)
                        ->when(! empty($this->selectedCompanies), fn ($q) => $q->where(function ($sq) {
                            $sq->whereIn('company', $this->selectedCompanies)
                               ->orWhereNull('company')
                               ->orWhere('company', '');
                        }));

                    // USD queda igual
                    $usdAmount = (float) (clone $baseQ)->where('currency', 'USD')->sum('amount');

                    // ARS se convierte con cotización BNA del mes
                    $arsAmount = (float) (clone $baseQ)->where('currency', 'ARS')->sum('amount');
                    $arsInUsd = 0;
                    if ($arsAmount > 0) {
                        $arsInUsd = \App\Services\ExchangeRateService::convertToUsd($arsAmount, $year, $m) ?? 0;
                    }

                    $months[$m] = round($usdAmount + $arsInUsd, 2);
                }
            } else {
                // Original: sumar todo sin convertir
                $data = $this->baseQuery()
                    ->where('provider', $provider)
                    ->selectRaw('month, SUM(amount) as total')
                    ->groupBy('month')
                    ->pluck('total', 'month')
                    ->toArray();

                for ($m = 1; $m <= 12; $m++) {
                    $months[$m] = round((float) ($data[$m] ?? 0), 2);
                }
            }

            $result[$provider] = $months;
        }

        return $result;
    }

    /**
     * Sub-desglose por referencia para proveedores multi.
     */
    public function getMonthlyByReference(string $provider): array
    {
        $year = (int) $this->selectedYear;
        $convertToUsd = ($this->viewMode === 'companies');

        $references = Invoice::where('year', $year)
            ->where('provider', $provider)
            ->when(! empty($this->selectedCompanies), fn ($q) => $q->where(function ($sq) {
                $sq->whereIn('company', $this->selectedCompanies)
                   ->orWhereNull('company')
                   ->orWhere('company', '');
            }))
            ->selectRaw('DISTINCT COALESCE(reference, service) as ref')
            ->pluck('ref')
            ->filter()
            ->toArray();

        $result = [];

        foreach ($references as $ref) {
            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $query = Invoice::where('year', $year)
                    ->where('provider', $provider)
                    ->where('month', $m)
                    ->where(function ($q) use ($ref) {
                        $q->where('reference', $ref)->orWhere('service', $ref);
                    })
                    ->when(! empty($this->selectedCompanies), fn ($q) => $q->where(function ($sq) {
                        $sq->whereIn('company', $this->selectedCompanies)
                           ->orWhereNull('company')
                           ->orWhere('company', '');
                    }));

                if ($convertToUsd) {
                    $usd = (float) (clone $query)->where('currency', 'USD')->sum('amount');
                    $ars = (float) (clone $query)->where('currency', 'ARS')->sum('amount');
                    $arsInUsd = $ars > 0 ? (\App\Services\ExchangeRateService::convertToUsd($ars, $year, $m) ?? 0) : 0;
                    $months[$m] = round($usd + $arsInUsd, 2);
                } else {
                    $months[$m] = round((float) $query->sum('amount'), 2);
                }
            }

            $result[$ref] = $months;
        }

        return $result;
    }

    /**
     * Comparación interanual.
     */
    public function getYearComparison(): array
    {
        $currentYear = (int) $this->selectedYear;
        $previousYear = $currentYear - 1;

        $current = $this->getYearTotal();

        $previous = Invoice::where('year', $previousYear)
            ->whereIn('provider', $this->selectedProviders)
            ->when(! empty($this->selectedCompanies), fn ($q) => $q->whereIn('company', $this->selectedCompanies))
            ->sum('amount_usd') ?: Invoice::where('year', $previousYear)->where('currency', 'USD')->sum('amount');

        $diff = $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : 0;

        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'diff_percent' => $diff,
        ];
    }

    /**
     * Label del proveedor.
     */
    public function getProviderLabel(string $provider): string
    {
        $p = \App\Models\InvoiceProvider::where('slug', $provider)->first();
        return $p?->name ?? ucfirst($provider);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }
}
