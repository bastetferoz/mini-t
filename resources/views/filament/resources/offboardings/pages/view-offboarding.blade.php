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
                                @if($history->notes)
                                    <p class="text-xs text-gray-400 mt-1">{{ $history->notes }}</p>
                                @endif
                            @else
                                <x-filament::badge color="gray">{{ $history->action }}</x-filament::badge>
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