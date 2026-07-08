<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class InvoiceAnalysis extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | \UnitEnum | null $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Análisis';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Análisis de facturación';

    protected string $view = 'filament.pages.invoice-analysis';

    public string $selectedYear = '';
    public string $selectedCurrency = 'ARS';

    public function mount(): void
    {
        $this->selectedYear = (string) now()->year;
    }

    /**
     * Total del año seleccionado.
     */
    public function getYearTotal(): float
    {
        return Invoice::whereYear('invoice_date', $this->selectedYear)
            ->where('currency', $this->selectedCurrency)
            ->sum('amount');
    }

    /**
     * Datos mes a mes del año seleccionado.
     */
    public function getMonthlyData(): array
    {
        $data = Invoice::selectRaw('period, SUM(amount) as total')
            ->where('currency', $this->selectedCurrency)
            ->where('period', 'like', $this->selectedYear . '-%')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total', 'period')
            ->toArray();

        // Llenar los 12 meses
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = $this->selectedYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
            $months[$key] = (float) ($data[$key] ?? 0);
        }

        return $months;
    }

    /**
     * Desglose por proveedor en el año seleccionado.
     */
    public function getByProvider(): array
    {
        return Invoice::selectRaw('provider, SUM(amount) as total')
            ->whereYear('invoice_date', $this->selectedYear)
            ->where('currency', $this->selectedCurrency)
            ->groupBy('provider')
            ->orderByDesc('total')
            ->pluck('total', 'provider')
            ->toArray();
    }

    /**
     * Comparación con el año anterior.
     */
    public function getYearComparison(): array
    {
        $currentYear = (int) $this->selectedYear;
        $previousYear = $currentYear - 1;

        $current = Invoice::whereYear('invoice_date', $currentYear)
            ->where('currency', $this->selectedCurrency)
            ->sum('amount');

        $previous = Invoice::whereYear('invoice_date', $previousYear)
            ->where('currency', $this->selectedCurrency)
            ->sum('amount');

        $diff = $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : 0;

        return [
            'current' => $current,
            'previous' => $previous,
            'diff_percent' => $diff,
        ];
    }

    /**
     * Años disponibles para el selector.
     */
    public function getAvailableYears(): array
    {
        $years = Invoice::selectRaw('DISTINCT YEAR(invoice_date) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            $years = [(int) now()->year];
        }

        return $years;
    }

    public function getByService(): array
    {
        return Invoice::selectRaw('service, SUM(amount) as total')
            ->whereYear('invoice_date', $this->selectedYear)
            ->where('currency', $this->selectedCurrency)
            ->groupBy('service')
            ->orderByDesc('total')
            ->pluck('total', 'service')
            ->toArray();
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }
    
}
