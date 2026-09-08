<x-filament-panels::page>

    <div class="mb-6 rounded-xl border border-gray-700 bg-gray-800/30 p-5">
        <h3 class="text-sm font-semibold text-white mb-2 flex items-center gap-2">
            <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-amber-400" />
            Subí el resumen de tarjeta
        </h3>
        <p class="text-xs text-gray-400 mb-4">
            La IA lee el resumen y extrae solo los consumos de la persona indicada (los que están sobre su línea "Total Consumos de ..."). Luego busca, entre las facturas cargadas de los últimos 2 meses, cuáles ya tienen su factura. Los reconocidos se marcan en verde.
        </p>

        <div class="mb-4 flex flex-col gap-1 max-w-md">
            <label class="text-xs text-gray-400">Persona a conciliar (como figura en el resumen)</label>
            <input type="text" wire:model="targetName" placeholder="RODOLFO DURANTE"
                class="rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
        </div>

        {{ $this->form }}

        <div class="mt-4 flex gap-3">
            <x-filament::button wire:click="analyze" color="success" icon="heroicon-o-sparkles" wire:loading.attr="disabled" wire:target="analyze">
                <span wire:loading.remove wire:target="analyze">Analizar PDF con IA</span>
                <span wire:loading wire:target="analyze">Analizando...</span>
            </x-filament::button>

            @if($analyzed)
                <x-filament::button wire:click="reset2" color="gray" icon="heroicon-o-arrow-path">
                    Limpiar
                </x-filament::button>
            @endif
        </div>

        {{-- Alternativa: pegar el texto manualmente --}}
        <div class="mt-6 pt-5 border-t border-gray-700">
            <h4 class="text-sm font-semibold text-white mb-1 flex items-center gap-2">
                <x-heroicon-o-clipboard class="w-4 h-4 text-amber-400" />
                O pegá el texto del resumen (sin IA)
            </h4>
            <p class="text-xs text-gray-400 mb-3">
                Copiá y pegá solo las líneas de consumos de la persona (una por línea, con el monto al final). No usa IA, así controlás exactamente qué se concilia.
            </p>
            <textarea wire:model="pastedText" rows="6" placeholder="GOOGLE *Google Workspace     19,99&#10;GODADDY 44122814     55,19&#10;ANTHROPIC* CLAUD     15,32"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm font-mono focus:border-amber-400 focus:ring-0"></textarea>
            <div class="mt-3">
                <x-filament::button wire:click="analyzeText" color="warning" icon="heroicon-o-clipboard-document-check">
                    Analizar texto pegado
                </x-filament::button>
            </div>
        </div>
    </div>

    @if($analyzed)
        @php
            $total = count($results);
            $ok = collect($results)->where('matched', true)->count();
        @endphp

        <div class="rounded-xl border border-gray-700 bg-gray-800/30 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Consumos del resumen</h3>
                <span class="text-xs text-gray-400">
                    <span class="text-green-400 font-semibold">{{ $ok }}</span> con factura /
                    <span class="text-gray-300">{{ $total }}</span> total
                </span>
            </div>

            @if($total === 0)
                <p class="text-sm text-gray-500 text-center py-8">No se detectaron consumos en el resumen.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 border-b border-gray-700">
                            <th class="px-5 py-2">Servicio / Consumo</th>
                            <th class="px-5 py-2 text-right">Monto</th>
                            <th class="px-5 py-2">Estado</th>
                            <th class="px-5 py-2">Factura encontrada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $r)
                            <tr class="border-b border-gray-800 {{ $r['matched'] ? 'bg-green-500/10' : '' }}">
                                <td class="px-5 py-2.5 font-medium {{ $r['matched'] ? 'text-green-400' : 'text-gray-200' }}">
                                    {{ $r['description'] }}
                                </td>
                                <td class="px-5 py-2.5 text-right text-gray-300">
                                    {{ number_format($r['amount'], 2, ',', '.') }} {{ $r['currency'] }}
                                </td>
                                <td class="px-5 py-2.5">
                                    @if($r['matched'])
                                        <span class="inline-flex items-center gap-1 text-xs text-green-400">
                                            <x-heroicon-s-check-circle class="w-4 h-4" /> Con factura
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-red-400">
                                            <x-heroicon-s-x-circle class="w-4 h-4" /> Sin factura
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-400">
                                    @if($r['matched'] && $r['invoice'])
                                        {{ ucfirst($r['invoice']['provider']) }}
                                        · {{ number_format($r['invoice']['amount'], 2, ',', '.') }} {{ $r['invoice']['currency'] }}
                                        @if($r['invoice']['number']) · Nº {{ $r['invoice']['number'] }} @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

</x-filament-panels::page>
