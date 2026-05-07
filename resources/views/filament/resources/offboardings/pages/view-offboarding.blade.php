<x-filament::page>

    <h2 class="text-xl font-bold mb-6">
        👤 {{ $record->name }}
    </h2>

    <div class="space-y-3">

        @forelse (
            \App\Models\AssetHistory::where('person_id', $record->id)
                ->with('asset')
                ->latest()
                ->get() as $history
        )
            @php $asset = $history->asset; @endphp

            <div class="p-3 border-b border-gray-700">

                <div class="font-semibold">
                    {{ $asset->device }}
                    @if($asset->brand) - {{ $asset->brand }} @endif
                    @if($asset->model) - {{ $asset->model }} @endif
                    @if($asset->cpu) - {{ $asset->cpu }} @endif
                    @if($asset->ram) - {{ $asset->ram }} @endif
                    @if($asset->disk) - {{ $asset->disk }} @endif
                </div>

                <div class="text-sm text-gray-400">
                    SN: {{ $asset->serial ?? '-' }}
                </div>

                <div class="text-sm font-semibold mt-1">
                    @if ($history->action === 'Devuelto')
                        <span class="text-green-400">✔ Devuelto</span>
                    @elseif ($history->action === 'No devuelto')
                        <span class="text-red-400">
                            ❌ No devuelto
                            @if($history->notes) - {{ $history->notes }} @endif
                        </span>
                    @else
                        <span class="text-gray-400">{{ $history->action }}</span>
                    @endif
                </div>

            </div>

        @empty
            <p class="text-gray-400">Sin historial de activos.</p>
        @endforelse

    </div>

</x-filament::page>