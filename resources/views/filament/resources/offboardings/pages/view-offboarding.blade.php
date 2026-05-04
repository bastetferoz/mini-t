<x-filament::page>

    <h2 class="text-xl font-bold mb-6">
        👤 {{ $record->name }}
    </h2>

    <div class="space-y-3">

        @foreach ($record->assignments as $assignment)
            @php 
                $asset = $assignment->asset;
                $history = $asset->histories->last();
                $notes = $history?->notes ?? '';
            @endphp

            <div class="p-3 border-b border-gray-700">

                {{-- 🔹 INFO COMPLETA DEL EQUIPO --}}
                <div class="font-semibold">
                    {{ $asset->device }}

                    @if($asset->brand) - {{ $asset->brand }} @endif
                    @if($asset->model) - {{ $asset->model }} @endif
                    @if($asset->processor) - {{ $asset->processor }} @endif
                    @if($asset->ram) - {{ $asset->ram }}GB @endif
                    @if($asset->disk) - {{ $asset->disk }} @endif
                </div>

                <div class="text-sm text-gray-400">
                    SN: {{ $asset->serial ?? '-' }}
                </div>

                {{-- 🔹 ESTADO --}}
                <div class="text-sm font-semibold mt-1">

                    @if ($asset->status === 'available')
                        <span class="text-green-400">✔ Devuelto</span>

                    @elseif ($asset->status === 'retired')
                        <span class="text-red-400">
                            ❌ No devuelto
                            @if($notes)
                                - {{ $notes }}
                            @endif
                        </span>

                    @elseif ($asset->status === 'in_transit')
                        <span class="text-yellow-400">🟡 En tránsito</span>
                    @endif

                </div>

            </div>
        @endforeach

    </div>

</x-filament::page>