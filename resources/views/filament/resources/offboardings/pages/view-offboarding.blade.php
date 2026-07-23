<x-filament::page>

    {{-- Header --}}
    <x-filament::card class="mb-6">
        <div class="grid grid-cols-4 gap-6">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Nombre</p>
                <p class="font-semibold text-white">{{ $record->name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Email</p>
                <p class="text-sm text-gray-300">{{ $record->email ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Área</p>
                <p class="text-sm text-gray-300">{{ $record->area ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Estado</p>
                <x-filament::badge color="{{ $record->status === 'inactive' ? 'danger' : 'warning' }}">
                    {{ $record->status === 'inactive' ? 'Baja completa' : 'En proceso' }}
                </x-filament::badge>
            </div>
        </div>
    </x-filament::card>

    {{-- Activos --}}
    <x-filament::card class="mb-6">
        <h3 class="text-sm font-semibold text-white mb-4">Activos devueltos</h3>

        @php
            $histories = \App\Models\AssetHistory::where('person_id', $record->id)
                ->with('asset')
                ->latest()
                ->get()
                ->unique('asset_id');
        @endphp

        <div class="space-y-3">
            @forelse ($histories as $history)
                @php $asset = $history->asset; @endphp
                @if($asset)
                    <div class="flex items-center justify-between py-3 border-t border-gray-700">

                        <div>
                            <p class="text-sm font-medium text-white">
                                {{ $asset->device }}
                                @if($asset->brand) · {{ $asset->brand }} @endif
                                @if($asset->model) · {{ $asset->model }} @endif
                            </p>
                            @if($asset->cpu || $asset->ram || $asset->disk)
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ implode(' · ', array_filter([$asset->cpu, $asset->ram, $asset->disk])) }}
                                </p>
                            @endif
                            @if($asset->serial)
                                <p class="text-xs text-gray-500 mt-0.5">SN: {{ $asset->serial }}</p>
                            @endif
                        </div>

                        <div class="text-right">
                            @if($history->action === 'Devuelto')
                                <x-filament::badge color="success">✔ Devuelto</x-filament::badge>
                            @elseif($history->action === 'No devuelto')
                                <x-filament::badge color="danger">❌ No devuelto</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">{{ $history->action }}</x-filament::badge>
                            @endif
                            @if($history->notes)
                                <p class="text-xs text-gray-400 mt-1">{{ $history->notes }}</p>
                            @endif
                        </div>

                    </div>
                @endif
            @empty
                <p class="text-sm text-gray-500 py-4 text-center">Sin equipos registrados.</p>
            @endforelse
        </div>
    </x-filament::card>

    {{-- Confirmación RRHH --}}
    <x-filament::card class="mb-6">
        <h3 class="text-sm font-semibold text-white mb-4">Confirmación RRHH</h3>

        @php
            $rrhhHistories = \App\Models\AssetHistory::where('person_id', $record->id)
                ->where('action', 'like', 'RRHH:%')
                ->with('asset')
                ->latest()
                ->get();
        @endphp

        <div class="space-y-3">
            @forelse ($rrhhHistories as $history)
                @php $asset = $history->asset; @endphp
                @if($asset)
                    <div class="flex items-center justify-between py-3 border-t border-gray-700">
                        <div>
                            <p class="text-sm font-medium text-white">
                                {{ $asset->device }}
                                @if($asset->brand) · {{ $asset->brand }} @endif
                                @if($asset->model) · {{ $asset->model }} @endif
                            </p>
                            @if($asset->serial)
                                <p class="text-xs text-gray-500 mt-0.5">SN: {{ $asset->serial }}</p>
                            @endif
                        </div>

                        <div class="text-right">
                            @if($history->action === 'RRHH: Devuelto')
                                <x-filament::badge color="success">✔ Recibido por RRHH</x-filament::badge>
                            @else
                                <x-filament::badge color="danger">❌ No recibido por RRHH</x-filament::badge>
                            @endif
                            <p class="text-xs text-gray-500 mt-1">{{ $history->notes }}</p>
                            <p class="text-xs text-gray-600 mt-0.5">{{ $history->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                @endif
            @empty
                <p class="text-sm text-gray-500 py-4 text-center">RRHH aún no confirmó recepción.</p>
            @endforelse
        </div>
    </x-filament::card>

    {{-- Historial logístico --}}
    <x-filament::card class="mb-6">
        <h3 class="text-sm font-semibold text-white mb-4">Historial logístico</h3>

        @php
            $returnProcess = \App\Models\ReturnProcess::where('person_id', $record->id)->latest()->first();
            $shipment = $returnProcess?->shipments()->latest()->first();
        @endphp

        @if ($shipment)
            {{-- Información del envío --}}
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div class="rounded-lg border border-gray-700 bg-gray-900/60 p-3">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Modalidad</p>
                    <p class="text-sm font-medium text-white">
                        @if ($shipment->logistics_method === 'enviopack')
                            Envíopack
                        @else
                            Retiro en moto / mensajería
                        @endif
                    </p>
                </div>

                @if ($shipment->logistics_method === 'enviopack' && $shipment->tracking_number)
                    <div class="rounded-lg border border-gray-700 bg-gray-900/60 p-3">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Nº de seguimiento</p>
                        <p class="text-sm font-mono font-medium text-violet-300">{{ $shipment->tracking_number }}</p>
                    </div>
                @endif

                @if ($shipment->logistics_method === 'moto' && $shipment->pickup_scheduled_at)
                    <div class="rounded-lg border border-gray-700 bg-gray-900/60 p-3">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Retiro programado</p>
                        <p class="text-sm font-medium text-white">{{ $shipment->pickup_scheduled_at->format('d/m/Y H:i') }}</p>
                    </div>
                @endif

                @if ($shipment->pickup_contact)
                    <div class="rounded-lg border border-gray-700 bg-gray-900/60 p-3">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Contacto retiro</p>
                        <p class="text-sm font-medium text-white">{{ $shipment->pickup_contact }}</p>
                    </div>
                @endif

                <div class="rounded-lg border border-gray-700 bg-gray-900/60 p-3">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Estado final</p>
                    <p class="text-sm font-medium">
                        <x-filament::badge :color="\App\Services\TrackingService::statusColor($shipment->tracking_status ?? 'pending_tracking')">
                            {{ \App\Services\TrackingService::statusLabel($shipment->tracking_status ?? 'pending_tracking') }}
                        </x-filament::badge>
                    </p>
                </div>

                @if ($shipment->last_update)
                    <div class="rounded-lg border border-gray-700 bg-gray-900/60 p-3">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Última actualización</p>
                        <p class="text-sm font-medium text-white">
                            @php
                                try {
                                    $lastUpdateFormatted = $shipment->last_update instanceof \Carbon\Carbon
                                        ? $shipment->last_update->format('d/m/Y H:i')
                                        : (string) $shipment->last_update;
                                } catch (\Throwable $e) {
                                    $lastUpdateFormatted = (string) $shipment->last_update;
                                }
                            @endphp
                            {{ $lastUpdateFormatted }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Comentario --}}
            @if ($shipment->notes)
                <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-3 mb-5">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Comentario</p>
                    <p class="text-sm text-gray-300">{{ $shipment->notes }}</p>
                </div>
            @endif

            {{-- Timeline de eventos de tracking --}}
            @php
                $trackingEvents = $shipment->tracking_payload['events'] ?? [];
            @endphp

            @if (count($trackingEvents) > 0)
                <div class="mt-2">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-3">Eventos de seguimiento</p>
                    <div class="space-y-2 border-l-2 border-gray-700 pl-4">
                        @foreach ($trackingEvents as $index => $event)
                            <div class="relative rounded-lg border border-gray-800 bg-gray-900/60 p-3">
                                <span @class([
                                    'absolute -left-[21px] top-4 h-2.5 w-2.5 rounded-full border-2 border-gray-900',
                                    'bg-emerald-400' => $index === 0,
                                    'bg-gray-500' => $index !== 0,
                                ])></span>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-medium text-white">{{ $event['status'] ?? 'Actualización' }}</p>
                                    @if (!empty($event['date']))
                                        <p class="shrink-0 text-xs text-gray-500">
                                            @php
                                                try {
                                                    $formattedDate = \Illuminate\Support\Carbon::parse($event['date'])->format('d/m/Y H:i');
                                                } catch (\Throwable $e) {
                                                    $formattedDate = $event['date'];
                                                }
                                            @endphp
                                            {{ $formattedDate }}
                                        </p>
                                    @endif
                                </div>
                                @if (!empty($event['location']))
                                    <p class="mt-1 text-xs text-gray-400">{{ $event['location'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif ($shipment->logistics_method === 'enviopack')
                <p class="text-sm text-gray-500 text-center py-2">Sin eventos de tracking registrados.</p>
            @endif
        @else
            <p class="text-sm text-gray-500 py-4 text-center">No se registró información logística para esta baja.</p>
        @endif
    </x-filament::card>

 {{-- Fechas --}}
<x-filament::card>
    <div class="grid grid-cols-2 gap-6">
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Inicio de baja</p>
            <p class="text-sm text-gray-300">
                {{ $record->offboarding_started_at?->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Cierre de baja</p>
            <p class="text-sm text-gray-300">
                @if($record->offboarding_completed_at)
                    {{ $record->offboarding_completed_at->format('d/m/Y H:i') }}
                @else
                    <x-filament::badge color="warning">Pendiente</x-filament::badge>
                @endif
            </p>
        </div>
    </div>
</x-filament::card>

</x-filament::page>