<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Models\InvoiceProvider;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class OdooSync extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string | \UnitEnum | null $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Odoo';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Sincronización con Odoo';

    protected string $view = 'filament.pages.odoo-sync';

    public ?int $year = null;

    public function mount(): void
    {
        $this->year = (int) now()->year;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') || auth()->user()->hasRole('it');
    }

    /** Slugs de proveedores marcados para cargar en Odoo. */
    public function getOdooProviderSlugs(): array
    {
        return InvoiceProvider::where('sync_to_odoo', true)
            ->pluck('slug')
            ->all();
    }

    /** Proveedores marcados para Odoo (para mostrar). */
    public function getOdooProviders(): Collection
    {
        return InvoiceProvider::where('sync_to_odoo', true)
            ->orderBy('name')
            ->get();
    }

    /** Facturas de proveedores marcados para Odoo en el año seleccionado. */
    public function getInvoices(): Collection
    {
        $slugs = $this->getOdooProviderSlugs();

        if (empty($slugs)) {
            return collect();
        }

        return Invoice::whereIn('provider', $slugs)
            ->when($this->year, fn ($q) => $q->where('year', $this->year))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('provider')
            ->get();
    }

    public function getYears(): array
    {
        $years = Invoice::selectRaw('DISTINCT year')
            ->whereNotNull('year')
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        return empty($years) ? [(int) now()->year] : $years;
    }

    /** URL directa a la factura en Odoo. */
    public function odooUrl(int $moveId): string
    {
        $base = rtrim((string) config('services.odoo.url'), '/');
        return "{$base}/web#id={$moveId}&model=account.move&view_type=form";
    }

    /** Carga una factura puntual en Odoo (borrador). */
    public function pushOne(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return;
        }

        if ($invoice->odoo_move_id) {
            \Filament\Notifications\Notification::make()
                ->title('Ya está cargada en Odoo')
                ->body("Factura Odoo #{$invoice->odoo_move_id}.")
                ->warning()->send();
            return;
        }

        $odoo = new \App\Services\OdooService();
        $id = $odoo->pushInvoice($invoice);

        if ($id) {
            \Filament\Notifications\Notification::make()
                ->title('Factura cargada en Odoo')
                ->body("Se creó el borrador #{$id} para {$invoice->provider}.")
                ->success()->send();
            \App\Services\ActivityLogger::facturacion("📤 Odoo: factura #{$invoice->id} ({$invoice->provider}) cargada como borrador #{$id}");
        } else {
            \Filament\Notifications\Notification::make()
                ->title('No se pudo cargar en Odoo')
                ->body(\App\Services\OdooService::$lastError ?? 'Error desconocido.')
                ->danger()->send();
        }
    }

    /** Carga todas las pendientes del año seleccionado. */
    public function pushAll(): void
    {
        // Solo las pendientes: sin cargar y no descartadas.
        $pendientes = $this->getInvoices()
            ->whereNull('odoo_move_id')
            ->where('odoo_dismissed', false);

        if ($pendientes->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('No hay facturas pendientes')->warning()->send();
            return;
        }

        $odoo = new \App\Services\OdooService();
        $ok = 0;
        $fail = 0;

        foreach ($pendientes as $invoice) {
            $id = $odoo->pushInvoice($invoice);
            if ($id) {
                $ok++;
            } else {
                $fail++;
            }
        }

        \Filament\Notifications\Notification::make()
            ->title("Carga en Odoo finalizada")
            ->body("{$ok} cargada(s)" . ($fail > 0 ? ", {$fail} con error" : '') . '.')
            ->color($fail > 0 ? 'warning' : 'success')
            ->send();

        \App\Services\ActivityLogger::facturacion("📤 Odoo: carga masiva — {$ok} ok, {$fail} error");
    }

    /** Desestima una factura: marca que no se carga en Odoo. */
    public function dismiss(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice || $invoice->odoo_move_id) {
            return;
        }
        $invoice->update(['odoo_dismissed' => true]);
        \Filament\Notifications\Notification::make()
            ->title('Factura descartada')
            ->body('No se cargará en Odoo.')
            ->success()->send();
    }

    /** Reactiva una factura descartada (vuelve a pendiente). */
    public function undismiss(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return;
        }
        $invoice->update(['odoo_dismissed' => false]);
        \Filament\Notifications\Notification::make()
            ->title('Factura reactivada')
            ->body('Vuelve a estar pendiente de carga.')
            ->success()->send();
    }

    /**
     * Deshace la vinculación con Odoo en Mini-T (por si se cargó por error).
     * No borra la factura en Odoo (eso se hace manualmente en Odoo).
     */
    public function unlink(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return;
        }
        $odooId = $invoice->odoo_move_id;
        $invoice->update(['odoo_move_id' => null, 'odoo_synced_at' => null]);
        \Filament\Notifications\Notification::make()
            ->title('Vinculación deshecha')
            ->body("La factura vuelve a pendiente. El borrador #{$odooId} sigue en Odoo (borralo desde Odoo si no lo querés).")
            ->warning()->send();
        \App\Services\ActivityLogger::facturacion("↩️ Odoo: factura #{$invoice->id} desvinculada (Odoo #{$odooId})");
    }
}
