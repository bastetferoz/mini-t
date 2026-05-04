<x-filament::widget>
    <x-filament::card>

        <h2 class="text-lg font-bold mb-4 text-red-400">
            🔴 Devoluciones pendientes
        </h2>

        @forelse ($this->getGroups() as $person => $assets)

            @php
                $personId = optional($assets->first()->assignments->first())->person_id;
            @endphp

            <div class="mb-4 p-4 rounded bg-gray-800 border border-red-500">

                <div class="flex justify-between items-center mb-2">

                    <!-- 👤 NOMBRE CLICKABLE -->
                    <a
                        href="{{ \App\Filament\Resources\People\PersonResource::getUrl('view', ['record' => $personId]) }}"
                        class="text-yellow-400 font-semibold hover:underline"
                    >
                        👤 {{ $person }}
                    </a>

                    <!-- 🔥 BOTÓN PROCESAR -->
                    <a
                        href="{{ \App\Filament\Resources\People\PersonResource::getUrl('view', ['record' => $personId]) }}"
                        class="text-xs bg-green-600 px-2 py-1 rounded hover:bg-green-500"
                    >
                        Procesar
                    </a>

                </div>

                <ul class="text-sm">
                    @foreach ($assets as $asset)
                        <li>• {{ $asset->device }} - {{ $asset->brand }}</li>
                    @endforeach
                </ul>

            </div>

        @empty
            <p>No hay devoluciones pendientes</p>
        @endforelse

    </x-filament::card>
</x-filament::widget>