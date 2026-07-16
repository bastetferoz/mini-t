@php
    $groups = $this->getGroups();
    $metrics = $this->getMetrics();
    $selectedPerson = $this->getSelectedPerson();
    $shipment = $this->getSelectedShipment();
    $timeline = $this->getTimeline($shipment);
    $isMotoPickup = $shipment?->logistics_method === 'moto';
    $isEnviopack = $shipment?->logistics_method === 'enviopack';
    $trackingStatusLabel = $this->getTrackingStatusLabel($shipment);
    $trackingStatusColor = $this->getTrackingStatusColor($shipment);
    $showForm = !$shipment || $this->editingShipment;
@endphp

<x-filament::widget>
    <div class="min-h-full space-y-5 text-gray-100">
        <div>
            <p class="text-sm text-gray-400">Resumen general y devoluciones pendientes</p>
        </div>

        {{-- Métricas --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Pendientes', 'value' => $metrics['pending'], 'description' => 'Personas con devolución pendiente', 'icon' => 'cube', 'tone' => 'violet'],
                ['label' => 'En tránsito', 'value' => $metrics['in_transit'], 'description' => 'En camino', 'icon' => 'truck', 'tone' => 'blue'],
                ['label' => 'Entregados', 'value' => $metrics['delivered'], 'description' => 'Devueltos correctamente', 'icon' => 'check-circle', 'tone' => 'emerald'],
                ['label' => 'Demorados', 'value' => $metrics['delayed'], 'description' => 'Con novedades', 'icon' => 'clock', 'tone' => 'orange'],
            ] as $metric)
                <div @class([
                    'rounded-xl border p-5 shadow-sm',
                    'border-violet-500/25 bg-violet-500/[.06]' => $metric['tone'] === 'violet',
                    'border-blue-500/25 bg-blue-500/[.06]' => $metric['tone'] === 'blue',
                    'border-emerald-500/25 bg-emerald-500/[.06]' => $metric['tone'] === 'emerald',
                    'border-orange-500/25 bg-orange-500/[.06]' => $metric['tone'] === 'orange',
                ])>
                    <div class="flex items-start justify-between">
                        <div>
                            <p @class([
                                'text-sm font-medium',
                                'text-violet-300' => $metric['tone'] === 'violet',
                                'text-blue-300' => $metric['tone'] === 'blue',
                                'text-emerald-300' => $metric['tone'] === 'emerald',
                                'text-orange-300' => $metric['tone'] === 'orange',
                            ])>{{ $metric['label'] }}</p>
                            <p class="mt-1 text-3xl font-bold">{{ $metric['value'] }}</p>
                        </div>
                        <x-dynamic-component :component="'heroicon-o-' . $metric['icon']" @class([
                            'h-10 w-10',
                            'text-violet-500' => $metric['tone'] === 'violet',
                            'text-blue-500' => $metric['tone'] === 'blue',
                            'text-emerald-500' => $metric['tone'] === 'emerald',
                            'text-orange-500' => $metric['tone'] === 'orange',
                        ]) />
                    </div>
                    <p class="mt-3 text-sm text-gray-400">{{ $metric['description'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Layout principal: lista + panel de seguimiento --}}
        <div class="grid gap-5 xl:grid-cols-12">

            {{-- Lista de personas --}}
            <section class="rounded-xl border border-gray-800 bg-gray-950/50 p-4 xl:col-span-7">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold">Personas con equipos a recuperar</h2>
                    <label class="relative block sm:w-72">
                        <span class="sr-only">Buscar persona</span>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar persona..."
                            class="w-full rounded-lg border border-gray-700 bg-gray-900 py-2.5 pl-3 pr-10 text-sm text-gray-100 placeholder:text-gray-500 focus:border-violet-500 focus:ring-violet-500" />
                        <x-heroicon-o-magnifying-glass class="absolute right-3 top-2.5 h-5 w-5 text-gray-500" />
                    </label>
                </div>

                <div class="space-y-3">
                    @forelse ($groups as $personName => $assets)
                        @php
                            $assignment = $assets->first()->assignments->first();
                            $personId = $assignment?->person_id;
                            $person = $assignment?->person;
                            $days = $this->getDias($assets);
                            $isSelected = (int) $personId === (int) $this->selectedPersonId;
                        @endphp
                        <div role="button" tabindex="0" wire:click="selectPerson({{ $personId ?? 'null' }})"
                            @class([
                                'w-full rounded-xl border p-4 text-left transition',
                                'border-violet-500 bg-violet-500/[.07] shadow-[0_0_24px_rgba(124,58,237,.08)]' => $isSelected,
                                'border-gray-800 bg-gray-900/60 hover:border-gray-700' => ! $isSelected,
                            ])>
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-500/20 text-violet-300">
                                        <x-heroicon-o-user class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        @if ($personId)
                                            <a href="{{ \App\Filament\Resources\People\PersonResource::getUrl('view', ['record' => $personId]) }}"
                                                wire:click.stop class="block truncate font-semibold text-gray-100 hover:text-violet-300 hover:underline">
                                                {{ $personName }}
                                            </a>
                                        @else
                                            <p class="truncate font-semibold">{{ $personName }}</p>
                                        @endif
                                        <p class="mt-0.5 truncate text-sm text-gray-400">{{ $person?->area ?: 'Sin área asignada' }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span @class([
                                        'inline-flex rounded-md border px-2 py-1 text-xs font-medium',
                                        'border-rose-500/40 bg-rose-500/10 text-rose-300' => $days >= 15,
                                        'border-gray-600 bg-gray-800 text-gray-300' => $days < 15,
                                    ])>{{ $days }} {{ $days === 1 ? 'día' : 'días' }}</span>
                                    <p class="mt-1 text-xs text-gray-500">En devolución</p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2 border-t border-gray-800 pt-3">
                                @foreach ($assets as $asset)
                                    <div class="flex items-center gap-2 text-sm text-gray-300">
                                        <x-heroicon-o-computer-desktop class="h-4 w-4 shrink-0 text-gray-500" />
                                        <span class="truncate">{{ $asset->full_description }}</span>
                                    </div>
                                @endforeach
                            </div>

                            @if (auth()->user()->hasRole('rrhh') && $personId)
                                <div class="mt-4 border-t border-gray-800 pt-3">
                                    <button type="button" wire:click.stop="openModal({{ $personId }})"
                                        class="rounded-lg px-3 py-2 text-sm font-semibold text-white transition"
                                        style="background-color: #16a34a; color: #ffffff;">
                                        Confirmar recepción
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-700 px-6 py-14 text-center text-sm text-gray-400">
                            No hay devoluciones pendientes{{ filled($this->search) ? ' para esta búsqueda' : '' }}.
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- Panel de seguimiento --}}
            <aside class="rounded-xl border border-gray-800 bg-gray-950/50 xl:col-span-5">
                <div class="border-b border-gray-800 px-5 py-4">
                    <h2 class="text-lg font-semibold">Seguimiento de devolución</h2>
                </div>

                @if ($selectedPerson)
                    <div class="space-y-5 p-5">

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- FORMULARIO: cuando no hay shipment o se está editando  --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        @if ($showForm)
                            <form wire:submit="saveLogistics" class="space-y-4">
                                @if ($this->editingShipment)
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-semibold text-amber-300">Editando datos de envío</p>
                                        <button type="button" wire:click="cancelEdit" class="text-xs text-gray-400 hover:text-gray-200">
                                            Cancelar
                                        </button>
                                    </div>
                                @else
                                    <p class="text-sm text-gray-400">Elegí cómo se recuperarán los equipos de esta baja.</p>
                                @endif

                                {{-- Selector de modalidad --}}
                                <div class="grid grid-cols-2 gap-3">
                                    <label @class([
                                        'cursor-pointer rounded-lg border p-3 transition',
                                        'border-violet-500 bg-violet-500/10' => $this->logisticsMethod === 'enviopack',
                                        'border-gray-700 bg-gray-900/60' => $this->logisticsMethod !== 'enviopack',
                                    ])>
                                        <input wire:model.live="logisticsMethod" type="radio" value="enviopack" class="sr-only">
                                        <span class="block text-sm font-semibold">Envíopack</span>
                                        <span class="mt-1 block text-xs text-gray-400">Seguimiento por número de envío.</span>
                                    </label>
                                    <label @class([
                                        'cursor-pointer rounded-lg border p-3 transition',
                                        'border-violet-500 bg-violet-500/10' => $this->logisticsMethod === 'moto',
                                        'border-gray-700 bg-gray-900/60' => $this->logisticsMethod !== 'moto',
                                    ])>
                                        <input wire:model.live="logisticsMethod" type="radio" value="moto" class="sr-only">
                                        <span class="block text-sm font-semibold">Retiro en moto</span>
                                        <span class="mt-1 block text-xs text-gray-400">Mensajería o retiro coordinado.</span>
                                    </label>
                                </div>

                                {{-- Campos según modalidad --}}
                                @if ($this->logisticsMethod === 'enviopack')
                                    <div>
                                        <label class="mb-1 block text-sm font-medium">Número de seguimiento</label>
                                        <input wire:model="trackingNumber" type="text" placeholder="Ingresá el número de Envíopack"
                                            class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-500 focus:border-violet-500 focus:ring-violet-500" />
                                        @error('tracking_number')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                                    </div>
                                @else
                                    <div>
                                        <label class="mb-1 block text-sm font-medium">Fecha y hora de retiro</label>
                                        <input wire:model="pickupScheduledAt" type="datetime-local"
                                            class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-100 focus:border-violet-500 focus:ring-violet-500" />
                                        @error('pickup_scheduled_at')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium">Contacto para el retiro <span class="text-gray-500">(opcional)</span></label>
                                        <input wire:model="pickupContact" type="text" placeholder="Nombre, teléfono o referencia"
                                            class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-500 focus:border-violet-500 focus:ring-violet-500" />
                                    </div>
                                @endif

                                {{-- Comentario (siempre visible) --}}
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Comentario <span class="text-gray-500">(opcional)</span></label>
                                    <textarea wire:model="logisticsNotes" rows="3" placeholder="Instrucciones, dirección alternativa, horario, etc."
                                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-500 focus:border-violet-500 focus:ring-violet-500"></textarea>
                                </div>

                                {{-- Botón guardar --}}
                                <button type="submit" class="w-full rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700">
                                    <span class="flex items-center justify-center gap-2">
                                        <x-heroicon-o-check class="h-4 w-4" />
                                        {{ $this->editingShipment ? 'Guardar cambios' : 'Guardar y coordinar' }}
                                    </span>
                                </button>
                            </form>

                        {{-- ═══════════════════════════════════════════════════════════ --}}
                        {{-- DATOS GUARDADOS: shipment existe y no se está editando      --}}
                        {{-- ═══════════════════════════════════════════════════════════ --}}
                        @else
                            {{-- Información del envío guardado --}}
                            <div>
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">Información logística</h3>
                                    <button wire:click="editShipment" type="button"
                                        class="inline-flex items-center gap-1 rounded-md border border-gray-700 bg-gray-800 px-2.5 py-1.5 text-xs font-medium text-gray-300 transition hover:border-gray-600 hover:text-gray-100">
                                        <x-heroicon-o-pencil class="h-3.5 w-3.5" />
                                        Editar
                                    </button>
                                </div>

                                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-lg border border-gray-800 bg-gray-900/70 p-3">
                                        <dt class="text-xs text-gray-500">Modalidad</dt>
                                        <dd class="mt-1 font-medium">{{ $isMotoPickup ? 'Retiro en moto' : 'Envíopack' }}</dd>
                                    </div>

                                    @if ($isMotoPickup)
                                        <div class="rounded-lg border border-gray-800 bg-gray-900/70 p-3">
                                            <dt class="text-xs text-gray-500">Retiro programado</dt>
                                            <dd class="mt-1 font-medium">{{ $shipment->pickup_scheduled_at?->format('d/m/Y H:i') ?: 'A coordinar' }}</dd>
                                        </div>
                                        @if ($shipment->pickup_contact)
                                            <div class="rounded-lg border border-gray-800 bg-gray-900/70 p-3">
                                                <dt class="text-xs text-gray-500">Contacto</dt>
                                                <dd class="mt-1 font-medium">{{ $shipment->pickup_contact }}</dd>
                                            </div>
                                        @endif
                                    @else
                                        <div class="rounded-lg border border-gray-800 bg-gray-900/70 p-3">
                                            <dt class="text-xs text-gray-500">Nº de seguimiento</dt>
                                            <dd class="mt-1 break-all font-mono font-medium text-violet-300">{{ $shipment->tracking_number }}</dd>
                                        </div>
                                    @endif
                                </dl>

                                {{-- Comentario guardado --}}
                                @if ($shipment->notes)
                                    <div class="mt-3 rounded-lg border border-gray-800 bg-gray-900/50 p-3">
                                        <p class="text-xs font-medium text-gray-500">Comentario</p>
                                        <p class="mt-1 text-sm text-gray-300">{{ $shipment->notes }}</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Estado actual --}}
                            <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold">Estado actual</p>
                                    @if ($isEnviopack)
                                        <button wire:click="refreshTracking" type="button"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-400 transition hover:border-gray-600 hover:text-gray-200">
                                            <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                                            Actualizar
                                        </button>
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <span @class([
                                        'inline-flex items-center gap-2 text-base font-semibold',
                                        'text-gray-400' => $trackingStatusColor === 'gray',
                                        'text-blue-300' => $trackingStatusColor === 'info',
                                        'text-amber-300' => $trackingStatusColor === 'warning',
                                        'text-emerald-300' => $trackingStatusColor === 'success',
                                        'text-rose-300' => $trackingStatusColor === 'danger',
                                    ])>
                                        <x-heroicon-o-truck class="h-5 w-5" />
                                        {{ $trackingStatusLabel }}
                                    </span>
                                    @if ($shipment->last_update)
                                        <span class="text-xs text-gray-500">{{ $shipment->last_update->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Timeline de seguimiento (datos de la API) --}}
                            @if ($isEnviopack)
                                <div>
                                    <h3 class="text-sm font-semibold">Historial de seguimiento</h3>
                                    <div class="mt-3 space-y-3 border-l border-gray-700 pl-4">
                                        @forelse ($timeline as $index => $event)
                                            <div class="relative rounded-lg border border-gray-800 bg-gray-900/60 p-3">
                                                <span @class([
                                                    'absolute -left-[22px] top-5 h-3 w-3 rounded-full border-2 border-gray-950',
                                                    'bg-emerald-400' => $index === 0,
                                                    'bg-gray-500' => $index !== 0,
                                                ])></span>
                                                <div class="flex justify-between gap-3">
                                                    <p class="text-sm font-medium">{{ $event['status'] }}</p>
                                                    @if ($event['date'])
                                                        <p class="shrink-0 text-xs text-gray-500">{{ $event['date']->format('d/m/Y H:i') }}</p>
                                                    @endif
                                                </div>
                                                @if ($event['location'])
                                                    <p class="mt-1 text-xs text-gray-400">{{ $event['location'] }}</p>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-sm text-gray-400">
                                                No hay eventos de seguimiento todavía.
                                                @if ($shipment->tracking_status === 'pending_tracking')
                                                    <br><span class="text-xs">El estado se actualizará automáticamente cuando EnvíoPack registre movimientos.</span>
                                                @endif
                                            </p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        @endif

                    </div>
                @else
                    <div class="flex min-h-80 items-center justify-center px-6 text-center text-sm text-gray-400">
                        Seleccioná una persona para ver el seguimiento de su devolución.
                    </div>
                @endif
            </aside>
        </div>
    </div>

    {{-- Modal RRHH: confirmar recepción --}}
    <x-filament::modal id="confirmar-recepcion-rrhh" width="3xl">
        <x-slot name="heading">Confirmar recepción</x-slot>

        @php
            $receiptAssets = collect();
            if ($this->rrhhPersonId) {
                $receiptAssets = \App\Models\Asset::where('status', 'in_transit')
                    ->whereHas('assignments', fn ($query) => $query->withTrashed()->where('person_id', $this->rrhhPersonId))
                    ->get();
            }
        @endphp

        <div class="space-y-3">
            @forelse ($receiptAssets as $asset)
                <label class="flex items-center justify-between gap-4 rounded-lg border border-gray-700 bg-gray-900 p-3">
                    <span class="text-sm font-medium">{{ $asset->full_description }}</span>
                    <span class="inline-flex shrink-0 items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" wire:model="rrhhAssets.{{ $asset->id }}" class="rounded border-gray-600 text-emerald-500 focus:ring-emerald-500">
                        Recibido
                    </span>
                </label>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">No se encontraron equipos en tránsito.</p>
            @endforelse
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="success" wire:click="confirmarRecepcionRrhh">
                Guardar confirmación
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament::widget>
