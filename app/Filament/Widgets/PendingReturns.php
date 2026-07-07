<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Person;
use App\Models\ReturnProcess;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;





class PendingReturns extends Widget
{
    protected string $view = 'filament.widgets.pending-returns';

    protected int | string | array $columnSpan = 'full';

    // Propiedades generales
    public $selectedAssets = [];
    public $currentPerson = null;

    // Propiedades para el modal de RRHH
    public $rrhhAssets = [];
    public $rrhhPersonId = null;

    /**
     * Agrupa los activos en devolución por persona.
     */
    public function getGroups()
    {
        return Asset::where('status', 'in_transit')
            ->with([
                'assignments' => fn ($q) => $q
                    ->withTrashed()
                    ->latest()
                    ->with('person'),
            ])
            ->get()
            ->groupBy(function ($asset) {
                return optional(
                    $asset->assignments->first()?->person
                )->name ?? 'Sin usuario';
            });
    }

    /**
     * Días transcurridos desde la última actualización de la persona.
     */
    public function getDias($assets): int
    {
        $person = optional(
            $assets->first()->assignments->first()
        )->person;

        if (! $person || ! $person->updated_at) {
            return 0;
        }

        return (int) $person->updated_at->diffInDays(now());
    }

    /**
     * Abre el modal correspondiente según el rol:
     * - RRHH -> modal simple de confirmación de recepción
     * - IT/Admin -> modal técnico actual
     */
    public function openModal($personId): void
    {
        $this->currentPerson = $personId;

        if (auth()->user()->hasRole('rrhh')) {
            $this->rrhhPersonId = $personId;
            $this->rrhhAssets = [];

            $this->dispatch('open-modal', id: 'confirmar-recepcion-rrhh');
        } else {
            $this->dispatch('open-modal', id: 'procesar-devolucion');
        }
    }

    /**
     * RRHH confirma qué activos fueron recibidos.
     */
  public function confirmarRecepcionRrhh(): void
{
    $person = Person::find($this->rrhhPersonId);

    if (! $person) {
        return;
    }

    // Buscar el proceso existente o crearlo si no existe
    $process = ReturnProcess::firstOrCreate([
        'person_id' => $this->rrhhPersonId,
    ]);

        // Actualizar manualmente la tabla pivote con los nombres reales de tus columnas
foreach ($this->rrhhAssets as $assetId => $received) {
    DB::table('return_process_asset')
        ->where('return_process_id', $process->id)
        ->where('asset', $assetId)
        ->update([
            'returned' => $received ? 1 : 0,
        ]);

    // Registrar en historial lo que RRHH acusa
    \App\Models\AssetHistory::create([
        'asset_id'  => $assetId,
        'person_id' => $this->rrhhPersonId,
        'action'    => $received ? 'RRHH: Devuelto' : 'RRHH: No devuelto',
        'notes'     => 'Confirmado por RRHH (' . auth()->user()->name . ')',
    ]);
}

        // Registrar confirmación de RRHH
        $process->update([
            'rrhh_confirmed_at' => now(),
            'rrhh_confirmed_by' => auth()->id(),
        ]);

        // Cerrar modal
        $this->dispatch('close-modal', id: 'confirmar-recepcion-rrhh');

        // Limpiar variables
        $this->rrhhAssets = [];
        $this->rrhhPersonId = null;

        // Refrescar widget
        $this->dispatch('$refresh');
    }

    /**
     * Cierra definitivamente la baja cuando RRHH e IT ya confirmaron.
     */
    public function confirmarBaja($personId): void
    {
        $process = ReturnProcess::where('person_id', $personId)
            ->latest()
            ->first();

        if (! $process) {
            return;
        }

        // Verificar que RRHH e IT hayan confirmado
        if (is_null($process->rrhh_confirmed_at) || is_null($process->it_confirmed_at)) {
            Notification::make()
                ->title('La baja aún no está completa')
                ->body('RRHH e IT deben confirmar antes de cerrar la baja.')
                ->warning()
                ->send();

            return;
        }

        // Procesar todos los activos asociados
        foreach ($process->assets as $asset) {
            $returned = (bool) $asset->pivot->returned;
            $reason = $asset->pivot->reason;

            if (! $returned) {
                continue;
            }

            if ($reason === 'fault') {
                $asset->update([
                    'status' => 'retired',
                ]);
            } else {
                $asset->update([
                    'status' => 'available',
                ]);
            }
        }

        // Marcar el proceso como finalizado
        $process->update([
            'status' => 'completed',
        ]);

        Notification::make()
            ->title('Baja confirmada correctamente')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Registra la confirmación de IT/Admin.
     */
    public function marcarConfirmacionIt($personId): void
    {
        $process = ReturnProcess::where('person_id', $personId)
            ->latest()
            ->first();

        if (! $process) {
            return;
        }

        $process->update([
            'it_confirmed_at' => now(),
            'it_confirmed_by' => auth()->id(),
        ]);
    }

    /**
     * Obtiene el proceso de devolución de una persona.
     */
    public function getProcessByPerson($personId)
    {
        return ReturnProcess::where('person_id', $personId)
            ->latest()
            ->first();
    }

    public function verificarCoincidenciaYFinalizar(int $personId): void
{
    $process = ReturnProcess::where('person_id', $personId)
        ->latest()
        ->first();

    if (! $process) {
        return;
    }

    // Ambos deben haber confirmado.
    if (! $process->rrhh_confirmed_at || ! $process->it_confirmed_at) {
        return;
    }

    // TODO: aquí se puede agregar una comparación detallada entre
    // lo marcado por RRHH y lo registrado por IT.
    // Por ahora, si ambos confirmaron, se finaliza el proceso.

    $person = Person::find($personId);

    if (! $person) {
        return;
    }

    // Cambiar estados de los activos según lo registrado por IT.
    $rows = DB::table('return_process_asset')
        ->where('return_process_id', $process->id)
        ->get();

    foreach ($rows as $row) {
        $asset = Asset::find($row->asset);

        if (! $asset) {
            continue;
        }

        if ($row->returned) {
            $asset->update([
                'status' => 'available',
            ]);
        } else {
            $asset->update([
                'status' => 'retired',
            ]);
        }
    }

    // Finalizar la baja.
    $person->update([
        'status' => 'inactive',
    ]);
}

    /**
     * Indica si el usuario actual pertenece a RRHH.
     */
    public function isRrhh(): bool
    {
        return auth()->user()->hasRole('rrhh');
    }
}