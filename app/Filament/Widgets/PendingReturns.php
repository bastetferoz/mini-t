<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Person;
use App\Models\ReturnProcess;
use App\Models\ReturnShipment;
use App\Services\TrackingService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
    public ?int $selectedPersonId = null;
    public string $search = '';
    public string $logisticsMethod = 'enviopack';
    public string $trackingNumber = '';
    public string $pickupScheduledAt = '';
    public string $pickupContact = '';
    public string $logisticsNotes = '';

    // Estado de edición del envío
    public bool $editingShipment = false;

    public function mount(): void
    {
        $this->selectedPersonId = $this->getGroups()->first()?->first()?->assignments->first()?->person_id;
    }

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
            })
            ->filter(function ($assets, $person) {
                return blank($this->search)
                    || str_contains(mb_strtolower($person), mb_strtolower($this->search));
            });
    }

    /** Selecciona una devolución para mostrar el detalle de su envío. */
    public function selectPerson(?int $personId): void
    {
        $this->selectedPersonId = $personId;
        $this->editingShipment = false;
        $this->resetLogisticsForm();

        // Auto-actualizar tracking al seleccionar
        $shipment = $this->getSelectedShipment();
        if ($shipment && $shipment->tracking_number && $shipment->tracking_status !== 'delivered') {
            $this->fetchTrackingForShipment($shipment);
        }
    }

    public function resetLogisticsForm(): void
    {
        $this->logisticsMethod = 'enviopack';
        $this->trackingNumber = '';
        $this->pickupScheduledAt = '';
        $this->pickupContact = '';
        $this->logisticsNotes = '';
        $this->resetErrorBag();
    }

    public function getSelectedPerson(): ?Person
    {
        return $this->selectedPersonId ? Person::find($this->selectedPersonId) : null;
    }

    public function getSelectedShipment(): ?ReturnShipment
    {
        if (! $this->selectedPersonId) {
            return null;
        }

        return ReturnShipment::query()
            ->whereHas('returnProcess', fn ($query) => $query->where('person_id', $this->selectedPersonId))
            ->latest('last_update')
            ->first();
    }

    /** Guarda la modalidad logística elegida desde el panel de seguimiento. */
    public function saveLogistics(): void
    {
        if (! $this->selectedPersonId) {
            return;
        }

        $data = [
            'method' => $this->logisticsMethod,
            'tracking_number' => $this->trackingNumber,
            'pickup_scheduled_at' => $this->pickupScheduledAt,
            'pickup_contact' => $this->pickupContact,
            'notes' => $this->logisticsNotes,
        ];

        Validator::make($data, [
            'method' => ['required', 'in:enviopack,moto'],
            'tracking_number' => ['nullable', 'string', 'max:100', 'required_if:method,enviopack'],
            'pickup_scheduled_at' => ['nullable', 'date', 'required_if:method,moto'],
            'pickup_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $process = ReturnProcess::firstOrCreate(['person_id' => $this->selectedPersonId]);

        $shipment = ReturnShipment::updateOrCreate(
            ['return_process_id' => $process->id],
            [
                'logistics_method' => $data['method'],
                'carrier' => $data['method'] === 'enviopack' ? 'Envíopack' : 'Moto / mensajería',
                'tracking_number' => $data['method'] === 'enviopack' ? $data['tracking_number'] : null,
                'tracking_status' => $data['method'] === 'enviopack' ? 'pending_tracking' : 'pickup_scheduled',
                'notes' => blank($data['notes']) ? null : $data['notes'],
                'pickup_scheduled_at' => $data['method'] === 'moto' ? $data['pickup_scheduled_at'] : null,
                'pickup_contact' => $data['method'] === 'moto' && filled($data['pickup_contact']) ? $data['pickup_contact'] : null,
            ]
        );

        // Si es EnvíoPack, intentar consultar el tracking inmediatamente
        if ($data['method'] === 'enviopack' && filled($data['tracking_number'])) {
            $this->fetchTrackingForShipment($shipment);
        }

        Notification::make()
            ->title('Logística de devolución guardada')
            ->success()
            ->send();

        $this->editingShipment = false;
        $this->resetLogisticsForm();
    }

    /**
     * Activa el modo edición para corregir el número de seguimiento.
     */
    public function editShipment(): void
    {
        $shipment = $this->getSelectedShipment();

        if (! $shipment) {
            return;
        }

        $this->editingShipment = true;
        $this->logisticsMethod = $shipment->logistics_method ?? 'enviopack';
        $this->trackingNumber = $shipment->tracking_number ?? '';
        $this->logisticsNotes = $shipment->notes ?? '';
        $this->pickupScheduledAt = $shipment->pickup_scheduled_at?->format('Y-m-d\TH:i') ?? '';
        $this->pickupContact = $shipment->pickup_contact ?? '';
    }

    /**
     * Cancela la edición sin guardar.
     */
    public function cancelEdit(): void
    {
        $this->editingShipment = false;
        $this->resetLogisticsForm();
    }

    /**
     * Fuerza una actualización del tracking desde la API de EnvíoPack.
     */
    public function refreshTracking(): void
    {
        $shipment = $this->getSelectedShipment();

        if (! $shipment || ! $shipment->tracking_number) {
            Notification::make()
                ->title('No hay número de seguimiento')
                ->warning()
                ->send();
            return;
        }

        $updated = $this->fetchTrackingForShipment($shipment);

        if ($updated) {
            Notification::make()
                ->title('Seguimiento actualizado')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No se pudo actualizar')
                ->body('Verificá que el número de seguimiento sea correcto o que las credenciales de EnvíoPack estén configuradas.')
                ->warning()
                ->send();
        }
    }

    /**
     * Consulta la API de EnvíoPack y actualiza el shipment con los datos obtenidos.
     */
    protected function fetchTrackingForShipment(ReturnShipment $shipment): bool
    {
        $service = app(TrackingService::class);

        if (! $service->isConfigured()) {
            return false;
        }

        $result = $service->track($shipment->tracking_number);

        if (! $result) {
            return false;
        }

        $shipment->update([
            'tracking_status' => $result['status'],
            'tracking_payload' => [
                'events' => $result['events'],
                'status_raw' => $result['status_raw'] ?? null,
            ],
            'last_update' => now(),
        ]);

        return true;
    }

    public function getMetrics(): array
    {
        return [
            'pending' => Assignment::withTrashed()
                ->whereHas('asset', fn ($query) => $query->where('status', 'in_transit'))
                ->whereNotNull('person_id')
                ->distinct('person_id')
                ->count('person_id'),

            'in_transit' => ReturnShipment::whereIn('tracking_status', ['in_transit', 'picked_up'])
                ->whereHas('returnProcess')
                ->distinct('return_process_id')
                ->count('return_process_id'),

            'delivered' => ReturnShipment::where('tracking_status', 'delivered')
                ->whereHas('returnProcess')
                ->distinct('return_process_id')
                ->count('return_process_id'),

            'delayed' => Person::whereHas('assignments', function ($q) {
                    $q->withTrashed()->whereHas('asset', fn ($aq) => $aq->where('status', 'in_transit'));
                })
                ->where('updated_at', '<=', now()->subDays(15))
                ->count(),
        ];
    }

    public function getTimeline(?ReturnShipment $shipment): array
    {
        $events = $shipment?->tracking_payload['events'] ?? [];

        return collect($events)->map(fn ($event) => [
            'status' => $event['status'] ?? 'Actualización de envío',
            'location' => $event['location'] ?? null,
            'date' => $event['date'] ?? null,
        ])->values()->all();
    }

    /**
     * Retorna el label legible del estado de tracking.
     */
    public function getTrackingStatusLabel(?ReturnShipment $shipment): string
    {
        if (! $shipment) {
            return 'Pendiente de coordinación';
        }

        return TrackingService::statusLabel($shipment->tracking_status ?? 'pending_tracking');
    }

    /**
     * Retorna el color del estado de tracking.
     */
    public function getTrackingStatusColor(?ReturnShipment $shipment): string
    {
        if (! $shipment) {
            return 'gray';
        }

        return TrackingService::statusColor($shipment->tracking_status ?? 'pending_tracking');
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
