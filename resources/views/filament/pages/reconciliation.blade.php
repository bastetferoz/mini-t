<x-filament-panels::page>

    @php $saved = $this->getSaved(); @endphp
    @if($saved->count() > 0)
        @php $mesesN = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic']; @endphp
        <div class="mb-6 rounded-xl border border-gray-700 bg-gray-800/30 p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <x-heroicon-o-folder-open class="w-4 h-4 text-amber-400" />
                Conciliaciones guardadas
            </h3>
            <div class="flex flex-wrap gap-2">
                @foreach($saved as $s)
                    <button wire:click="load({{ $s->id }})"
                        class="text-xs rounded-lg border px-3 py-1.5 transition {{ $currentId === $s->id ? 'border-amber-400 bg-amber-500/10 text-amber-300' : 'border-gray-600 bg-gray-900 text-gray-300 hover:border-amber-400' }}">
                        {{ $s->person_name }} · {{ $mesesN[$s->month] ?? $s->month }} {{ $s->year }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

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
            $green = collect($results)->where('status', 'green')->count();
            $yellow = collect($results)->where('status', 'yellow')->count();
            $red = collect($results)->where('status', 'red')->count();
            $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        @endphp

        {{-- Barra de acciones: mes/año, guardar, exportar --}}
        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-700 bg-gray-800/30 p-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-400">Mes</label>
                <select wire:model="month" class="rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
                    @foreach($meses as $num => $label)
                        <option value="{{ $num }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-400">Año</label>
                <input type="number" min="2020" max="2100" wire:model="year"
                    class="w-24 rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
            </div>

            <x-filament::button wire:click="save" color="success" icon="heroicon-o-bookmark">
                Guardar conciliación
            </x-filament::button>

            <x-filament::button wire:click="exportPdf" color="danger" icon="heroicon-o-document-arrow-down">
                Exportar PDF
            </x-filament::button>

            <x-filament::button wire:click="sendEmail" color="info" icon="heroicon-o-envelope" wire:loading.attr="disabled" wire:target="sendEmail">
                <span wire:loading.remove wire:target="sendEmail">Enviar por correo</span>
                <span wire:loading wire:target="sendEmail">Enviando...</span>
            </x-filament::button>

            <x-filament::button wire:click="reset2" color="gray" icon="heroicon-o-arrow-path">
                Limpiar
            </x-filament::button>
        </div>

        <div class="rounded-xl border border-gray-700 bg-gray-800/30 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-700 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-semibold text-white">Consumos de {{ $targetName }}</h3>
                <span class="text-xs text-gray-400 flex items-center gap-3">
                    <span><span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-1"></span>{{ $green }} con factura</span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1"></span>{{ $yellow }} reconocido</span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-red-400 mr-1"></span>{{ $red }} sin factura</span>
                    <span class="text-gray-300">/ {{ $total }} total</span>
                </span>
            </div>

            @if($total === 0)
                <p class="text-sm text-gray-500 text-center py-8">No hay consumos.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 border-b border-gray-700">
                            <th class="px-5 py-2">Servicio / Consumo</th>
                            <th class="px-5 py-2 text-right">Monto</th>
                            <th class="px-5 py-2">Factura encontrada</th>
                            <th class="px-5 py-2 text-center">Estado</th>
                            <th class="px-5 py-2 text-center w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $i => $r)
                            @php
                                $rowBg = match($r['status']) {
                                    'green'  => 'bg-green-500/10',
                                    'yellow' => 'bg-yellow-500/10',
                                    default  => '',
                                };
                                $txt = match($r['status']) {
                                    'green'  => 'text-green-400',
                                    'yellow' => 'text-yellow-400',
                                    default  => 'text-gray-200',
                                };
                            @endphp
                            <tr class="border-b border-gray-800 {{ $rowBg }}">
                                <td class="px-5 py-2.5 font-medium {{ $txt }}">{{ $r['description'] }}</td>
                                <td class="px-5 py-2.5 text-right text-gray-300">
                                    {{ number_format($r['amount'], 2, ',', '.') }} {{ $r['currency'] }}
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-400">{{ $r['invoice_info'] ?? '—' }}</td>
                                <td class="px-5 py-2.5">
                                    @php
                                        $badge = match($r['status']) {
                                            'green'  => ['Con factura', 'text-green-400', 'heroicon-s-check-circle'],
                                            'yellow' => ['Reconocido', 'text-yellow-400', 'heroicon-s-shield-check'],
                                            default  => ['Sin factura', 'text-red-400', 'heroicon-s-x-circle'],
                                        };
                                    @endphp
                                    <button wire:click="cycleStatus({{ $i }})"
                                        title="Clic para cambiar el estado (Sin factura → Reconocido → Con factura)"
                                        class="inline-flex items-center gap-1 text-xs {{ $badge[1] }} hover:opacity-70 cursor-pointer">
                                        <x-dynamic-component :component="$badge[2]" class="w-4 h-4" />
                                        {{ $badge[0] }}
                                    </button>
                                </td>
                                <td class="px-5 py-2.5 text-center">
                                    <button wire:click="removeItem({{ $i }})" wire:confirm="¿Eliminar este consumo de la lista?"
                                        class="text-red-400/60 hover:text-red-400" title="Eliminar">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-gray-500 px-5 py-3">
                    Clic en los círculos para cambiar el estado: verde (con factura), amarillo (reconocido sin factura), rojo (sin factura). Usá la papelera para quitar consumos que no correspondan.
                </p>
            @endif
        </div>
    @endif

</x-filament-panels::page>
