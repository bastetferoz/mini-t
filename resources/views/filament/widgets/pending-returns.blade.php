<x-filament::widget>
    <x-filament::card>

        <h2 class="text-lg font-bold mb-4 text-red-400">
            🔴 Devoluciones pendientes
        </h2>

        @forelse ($this->getGroups() as $person => $assets)

            @php
                // Obtener el ID de la persona
                $personId = optional($assets->first()->assignments->first())->person_id;

                // Buscar el proceso asociado
                $process = $this->getProcessByPerson($personId);

                // URL a la ficha de la persona
                $url = \App\Filament\Resources\People\PersonResource::getUrl('view', [
                    'record' => $personId,
                ]);

                // Días transcurridos
                $dias = $this->getDias($assets);
            @endphp

            {{-- Contenedor de dos columnas --}}
            <div class="flex gap-4 mb-4">

                {{-- Tarjeta izquierda: información --}}
                <div class="flex-1 border border-red-500 rounded-lg p-4">

                    <div class="mb-2">
                        <a
                            href="{{ $url }}"
                            class="text-yellow-400 font-semibold hover:underline"
                        >
                            👤 {{ $person }}
                        </a>
                    </div>

                    <div class="mb-3">
                        <span class="text-sm {{ $dias >= 7 ? 'text-red-400' : 'text-gray-400' }}">
                            ⏱ {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}
                        </span>
                    </div>

                    {{-- Carteles de estado --}}
                    @if($process && $process->it_confirmed_at && !$process->rrhh_confirmed_at)
                        <div class="text-xs text-yellow-400 font-medium mb-2">
                            Pendiente de confirmación de RRHH
                        </div>
                    @endif

                    @if($process && $process->rrhh_confirmed_at && !$process->it_confirmed_at)
                        <div class="text-xs text-yellow-400 font-medium mb-2">
                            Pendiente de recepción por IT
                        </div>
                    @endif

                    @if($process && $process->status === 'mismatch')
                        <div class="text-xs text-red-400 font-medium mb-2">
                            Diferencias detectadas entre RRHH e IT
                        </div>
                    @endif

                    <ul class="text-sm space-y-1 border-t border-gray-600 pt-2">
                        @foreach ($assets as $asset)
                            <li class="text-gray-300">
                                • {{ $asset->device }} - {{ $asset->brand }}
                            </li>
                        @endforeach
                    </ul>

                </div>

                {{-- Tarjeta derecha: acciones --}}
                @if(auth()->user()->getRoleNames()->contains('rrhh'))
                    <div class="w-48 border border-gray-600 rounded-lg p-4 flex flex-col gap-3">

                        <x-filament::button
                            color="success"
                            size="sm"
                            class="w-full"
                            wire:click="openModal({{ $personId }})"
                        >
                            Confirmar recepción
                        </x-filament::button>

                    </div>
                @endif

            </div>

        @empty
            <p>No hay devoluciones pendientes</p>
        @endforelse

    </x-filament::card>

    {{-- MODAL DE RRHH --}}
    <x-filament::modal id="confirmar-recepcion-rrhh" width="3xl">
        <x-slot name="heading">
            Confirmar recepción
        </x-slot>

        @php
            $assignments = collect();

            if ($this->rrhhPersonId) {
                $assignments = \App\Models\Asset::where('status', 'in_transit')
                    ->whereHas('assignments', function ($query) {
                        $query->withTrashed()
                            ->where('person_id', $this->rrhhPersonId);
                    })
                    ->with([
                        'assignments' => function ($query) {
                            $query->withTrashed()
                                ->where('person_id', $this->rrhhPersonId);
                        },
                    ])
                    ->get()
                    ->map(function ($asset) {
                        return (object) [
                            'asset' => $asset,
                        ];
                    });
            }
        @endphp

        <div class="space-y-4">
            @forelse ($assignments as $assignment)
                @if($assignment->asset)
                    <div class="border border-gray-700 rounded-lg p-4">
                        <div class="font-medium mb-2">
                            {{ $assignment->asset->device }}
                            - {{ $assignment->asset->brand }}
                            - {{ $assignment->asset->model }}
                        </div>

                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                wire:model="rrhhAssets.{{ $assignment->asset->id }}"
                            >
                            <span>Recibido</span>
                        </label>
                    </div>
                @endif
            @empty
                <p class="text-sm text-gray-500">
                    No se encontraron activos asignados.
                </p>
            @endforelse
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                color="success"
                wire:click="confirmarRecepcionRrhh"
            >
                Guardar confirmación
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

</x-filament::widget>