<x-filament::widget>
    <x-filament::card>

        <h2 class="text-sm font-semibold uppercase tracking-widest mb-4">
            🔴 Devoluciones pendientes
        </h2>

        @forelse ($this->getGroups() as $person => $assets)

            @php
                $personId = optional($assets->first()->assignments->first())->person_id;
                $process  = $this->getProcessByPerson($personId);
                $url      = \App\Filament\Resources\People\PersonResource::getUrl('view', ['record' => $personId]);
                $dias     = $this->getDias($assets);
            @endphp

            <x-filament::card class="mb-4">

                {{-- Nombre + días --}}
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                    <a href="{{ $url }}" style="color:#f59e0b; font-weight:600; font-size:14px;">
                        👤 {{ $person }}
                    </a>
                    <x-filament::badge color="{{ $dias >= 7 ? 'danger' : 'gray' }}">
                        ⏱ {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}
                    </x-filament::badge>
                </div>

                {{-- Alertas --}}
                @if($process && $process->it_confirmed_at && !$process->rrhh_confirmed_at)
                    <div style="margin-bottom:8px;">
                        <x-filament::badge color="warning">Pendiente confirmación RRHH</x-filament::badge>
                    </div>
                @endif

                @if($process && $process->rrhh_confirmed_at && !$process->it_confirmed_at)
                    <div style="margin-bottom:8px;">
                        <x-filament::badge color="warning">Pendiente recepción IT</x-filament::badge>
                    </div>
                @endif

                @if($process && $process->status === 'mismatch')
                    <div style="margin-bottom:8px;">
                        <x-filament::badge color="danger">Diferencias detectadas</x-filament::badge>
                    </div>
                @endif

                {{-- Equipos --}}
                <div style="border-top: 1px solid #374151; padding-top:10px; margin-bottom:12px;">
                    @foreach ($assets as $asset)
                        <div style="font-size:12px; color:#9ca3af; padding:2px 0;">
    • {{ $asset->full_description }}
</div>
                    @endforeach
                </div>

                {{-- Botón RRHH --}}
                @if(auth()->user()->getRoleNames()->contains('rrhh'))
                    <x-filament::button
                        color="success"
                        size="sm"
                        wire:click="openModal({{ $personId }})"
                    >
                        Confirmar recepción
                    </x-filament::button>
              
                @endif

            </x-filament::card>

        @empty
            <p style="font-size:13px; color:#6b7280; text-align:center; padding:24px 0;">
                No hay devoluciones pendientes
            </p>
        @endforelse

    </x-filament::card>

    {{-- MODAL DE RRHH --}}
    <x-filament::modal id="confirmar-recepcion-rrhh" width="3xl">
        <x-slot name="heading">Confirmar recepción</x-slot>

        @php
            $assignments = collect();
            if ($this->rrhhPersonId) {
                $assignments = \App\Models\Asset::where('status', 'in_transit')
                    ->whereHas('assignments', function ($query) {
                        $query->withTrashed()->where('person_id', $this->rrhhPersonId);
                    })
                    ->with(['assignments' => function ($query) {
                        $query->withTrashed()->where('person_id', $this->rrhhPersonId);
                    }])
                    ->get()
                    ->map(fn ($asset) => (object) ['asset' => $asset]);
            }
        @endphp

        <div style="display:flex; flex-direction:column; gap:12px;">
            @forelse ($assignments as $assignment)
                @if($assignment->asset)
                    <x-filament::card>
                        <div style="font-size:13px; font-weight:500; margin-bottom:8px;">
                            {{ $assignment->asset->device }}
                            - {{ $assignment->asset->brand }}
                            - {{ $assignment->asset->model }}
                        </div>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px;">
                            <input type="checkbox" wire:model="rrhhAssets.{{ $assignment->asset->id }}">
                            <span>Recibido</span>
                        </label>
                    </x-filament::card>
                @endif
            @empty
                <p style="font-size:13px; color:#6b7280;">No se encontraron activos asignados.</p>
            @endforelse
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="success" wire:click="confirmarRecepcionRrhh">
                Guardar confirmación
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

</x-filament::widget>