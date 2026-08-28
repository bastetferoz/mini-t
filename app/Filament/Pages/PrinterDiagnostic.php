<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Printers\PrinterResource;
use App\Models\Printer;
use App\Services\PrinterService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PrinterDiagnostic extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string | \UnitEnum | null $navigationGroup = 'Infra';

    protected static ?string $navigationLabel = 'Diagnóstico SNMP';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Diagnóstico SNMP de impresoras';

    protected string $view = 'filament.pages.printer-diagnostic';

    // Inputs
    public string $ip = '';
    public string $community = 'public';
    public string $customOid = '';
    public string $printerName = '';
    public string $printerLocation = '';

    // Resultados
    public ?array $diagnosis = null;
    public ?array $walkResults = null;
    public ?string $customResult = null;
    public ?array $detected = null;
    public bool $ran = false;

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }

    /**
     * Prueba ping + todos los OIDs candidatos de contador.
     */
    /**
     * Detección resuelta: muestra marca, modelo, serie y contador ya interpretados
     * (igual a lo que guardaría la impresora al verificarla).
     */
    public function detect(): void
    {
        $this->reset(['diagnosis', 'walkResults', 'customResult', 'detected']);

        if (! filter_var($this->ip, FILTER_VALIDATE_IP)) {
            Notification::make()->title('IP inválida')->danger()->send();
            return;
        }

        $online = PrinterService::ping($this->ip);
        $this->ran = true;

        if (! $online) {
            Notification::make()
                ->title('Sin respuesta al ping')
                ->body("La IP {$this->ip} no respondió.")
                ->warning()
                ->send();
            return;
        }

        $this->detected = PrinterService::querySnmp($this->ip, $this->community ?: 'public');

        $ok = ! empty($this->detected['page_count']);

        Notification::make()
            ->title($ok ? 'Datos detectados' : 'Detección parcial')
            ->body($ok
                ? "Contador leído: {$this->detected['page_count']} páginas."
                : 'No se pudo leer el contador. Probá el diagnóstico completo o el walk.')
            ->color($ok ? 'success' : 'warning')
            ->send();
    }

    /**
     * Guarda la impresora detectada en el listado de Infra > Impresoras.
     */
    public function savePrinter(): void
    {
        if (! $this->detected) {
            Notification::make()->title('Primero detectá la impresora')->warning()->send();
            return;
        }

        if (trim($this->printerName) === '') {
            Notification::make()->title('Ingresá un nombre para la impresora')->warning()->send();
            return;
        }

        // Evitar duplicar por IP
        if (Printer::where('ip', $this->ip)->exists()) {
            Notification::make()
                ->title('Ya existe una impresora con esa IP')
                ->body("La IP {$this->ip} ya está registrada.")
                ->warning()
                ->send();
            return;
        }

        $printer = Printer::create([
            'name'           => trim($this->printerName),
            'ip'             => $this->ip,
            'location'       => trim($this->printerLocation) ?: null,
            'snmp_community' => $this->community ?: 'public',
            'brand'          => $this->detected['brand'] ?? null,
            'model'          => $this->detected['model'] ?? null,
            'serial'         => $this->detected['serial'] ?? null,
            'status'         => 'online',
            'last_seen_at'   => now(),
        ]);

        // Guardar la primera lectura del contador si vino
        if (! empty($this->detected['page_count'])) {
            $printer->update([
                'page_count'    => $this->detected['page_count'],
                'page_count_at' => now(),
            ]);
            $printer->readings()->create([
                'page_count' => $this->detected['page_count'],
                'read_at'    => now(),
                'source'     => 'manual',
            ]);
        }

        Notification::make()
            ->title('Impresora guardada')
            ->body("{$printer->name} se agregó a Impresoras.")
            ->success()
            ->actions([
                \Filament\Actions\Action::make('ver')
                    ->label('Ver impresora')
                    ->url(PrinterResource::getUrl('view', ['record' => $printer]))
                    ->button(),
            ])
            ->send();

        // Limpiar el formulario de guardado
        $this->reset(['printerName', 'printerLocation']);
    }

    public function diagnose(): void
    {
        $this->reset(['diagnosis', 'walkResults', 'customResult', 'detected']);

        if (! filter_var($this->ip, FILTER_VALIDATE_IP)) {
            Notification::make()->title('IP inválida')->danger()->send();
            return;
        }

        $this->diagnosis = PrinterService::diagnose($this->ip, $this->community ?: 'public');
        $this->ran = true;

        if (! $this->diagnosis['online']) {
            Notification::make()
                ->title('Sin respuesta al ping')
                ->body("La IP {$this->ip} no respondió. Verificá que esté encendida y en la red.")
                ->warning()
                ->send();
            return;
        }

        $hits = collect($this->diagnosis['candidates'])->where('numeric', true)->count();

        Notification::make()
            ->title('Diagnóstico completado')
            ->body($hits > 0
                ? "{$hits} OID(s) devolvieron un número. Revisá cuál coincide con el contador real de la impresora."
                : 'Ningún OID de contador respondió. Probá el walk para explorar.')
            ->color($hits > 0 ? 'success' : 'warning')
            ->send();
    }

    /**
     * SNMP walk del subárbol de impresora.
     */
    public function runWalk(): void
    {
        if (! filter_var($this->ip, FILTER_VALIDATE_IP)) {
            Notification::make()->title('IP inválida')->danger()->send();
            return;
        }

        $this->walkResults = PrinterService::walk($this->ip, $this->community ?: 'public', '1.3.6.1.2.1.43');
        $this->ran = true;

        Notification::make()
            ->title('Walk completado')
            ->body(count($this->walkResults) . ' OID(s) encontrados en el subárbol de impresora.')
            ->color(count($this->walkResults) > 0 ? 'success' : 'warning')
            ->send();
    }

    /**
     * Consulta un OID arbitrario ingresado a mano.
     */
    public function queryCustom(): void
    {
        if (! filter_var($this->ip, FILTER_VALIDATE_IP)) {
            Notification::make()->title('IP inválida')->danger()->send();
            return;
        }

        if (trim($this->customOid) === '') {
            Notification::make()->title('Ingresá un OID')->warning()->send();
            return;
        }

        $this->customResult = PrinterService::get($this->ip, $this->community ?: 'public', trim($this->customOid));
        $this->ran = true;

        Notification::make()
            ->title($this->customResult !== null ? 'OID respondió' : 'OID sin respuesta')
            ->body($this->customResult !== null ? "Valor: {$this->customResult}" : 'No devolvió valor.')
            ->color($this->customResult !== null ? 'success' : 'warning')
            ->send();
    }
}
