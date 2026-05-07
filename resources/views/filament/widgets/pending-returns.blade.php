<x-filament::widget>
    <x-filament::card>

        <h2 class="text-lg font-bold mb-4 text-red-400">
            🔴 Devoluciones pendientes
        </h2>

        @forelse ($this->getGroups() as $person => $assets)

            @php
                $personId = optional($assets->first()->assignments->first())->person_id;
                $url = \App\Filament\Resources\People\PersonResource::getUrl('view', ['record' => $personId]);
                $dias = $this->getDias($assets);
            @endphp

            <x-filament::card class="mb-4 border border-red-500">

                <div class="flex justify-between items-center mb-3">

                    <a href="{{ $url }}" class="text-yellow-400 font-semibold hover:underline">
                        👤 {{ $person }}
                    </a>

                    <div class="flex items-center gap-3">
                        <span class="text-sm {{ $dias >= 7 ? 'text-red-400' : 'text-gray-400' }}">
                            ⏱ {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}
                        </span>
                        <a href="{{ $url }}" class="text-xs bg-green-600 px-2 py-1 rounded hover:bg-green-500">
                            Procesar
                        </a>
                    </div>

                </div>

                <ul class="text-sm space-y-1 border-t border-gray-600 pt-2">
                    @foreach ($assets as $asset)
                        <li class="text-gray-300">• {{ $asset->device }} - {{ $asset->brand }}</li>
                    @endforeach
                </ul>

            </x-filament::card>

        @empty
            <p>No hay devoluciones pendientes</p>
        @endforelse

    </x-filament::card>
</x-filament::widget>