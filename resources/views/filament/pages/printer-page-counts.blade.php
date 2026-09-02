<x-filament-panels::page>

    @php
        $printers = $this->getPrinters();
        $monthly = $this->getMonthlyCounts();
        $deltas = $this->getMonthlyDeltas();
        $years = $this->getYears();
        $meses = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
    @endphp

    {{-- Controles --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-400">Año</label>
            <select wire:model.live="year" class="rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
                @foreach($years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-400">Conteo automático el día</label>
            <input type="number" min="1" max="31" wire:model="countDay"
                class="w-20 rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
            <span class="text-sm text-gray-400">de cada mes</span>
            <x-filament::button wire:click="saveCountDay" color="gray" size="sm" icon="heroicon-o-check">
                Guardar
            </x-filament::button>
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button wire:click="exportCsv" color="success" icon="heroicon-o-arrow-down-tray">
                Exportar (últimos 12 meses)
            </x-filament::button>

            <x-filament::button wire:click="readNow" color="info" icon="heroicon-o-signal" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="readNow">Leer ahora</span>
                <span wire:loading wire:target="readNow">Leyendo...</span>
            </x-filament::button>
        </div>
    </div>

    @if($printers->isEmpty())
        <div class="bg-gray-800/50 rounded-lg p-6 border border-gray-700 text-center text-gray-400">
            No hay impresoras registradas. Agregá una desde Impresoras o desde Diagnóstico SNMP.
        </div>
    @else
        {{-- Estado actual --}}
        <div class="bg-gray-800/50 rounded-lg border border-gray-700 mb-6 overflow-hidden">
            <h3 class="text-sm font-semibold text-amber-400 px-4 pt-4 pb-2">Contador actual</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-400 border-b border-gray-700">
                        <th class="px-4 py-2">Impresora</th>
                        <th class="px-4 py-2">Tipo</th>
                        <th class="px-4 py-2">Modelo</th>
                        <th class="px-4 py-2">IP</th>
                        <th class="px-4 py-2 text-right">Páginas</th>
                        <th class="px-4 py-2">Última lectura</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($printers as $p)
                        <tr class="border-b border-gray-800">
                            <td class="px-4 py-2 text-gray-100 font-medium">{{ $p->name }}</td>
                            <td class="px-4 py-2 text-gray-400">{{ $p->type === 'manual' ? 'Manual' : 'De red' }}</td>
                            <td class="px-4 py-2 text-gray-300">{{ $p->model ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-gray-400">{{ $p->ip ?? '—' }}</td>
                            <td class="px-4 py-2 text-right font-bold text-green-400">{{ $p->page_count !== null ? number_format($p->page_count, 0, ',', '.') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-400">{{ $p->page_count_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tabla mensual: contador al cierre de cada mes + páginas del período --}}
        <div class="bg-gray-800/50 rounded-lg border border-gray-700 overflow-x-auto">
            <h3 class="text-sm font-semibold text-amber-400 px-4 pt-4 pb-2">
                Conteo mensual {{ $year }} — contador al cierre de cada mes (y páginas del mes)
            </h3>
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-gray-400 border-b border-gray-700">
                        <th class="px-3 py-2 sticky left-0 bg-gray-800">Impresora</th>
                        @foreach($meses as $m)
                            <th class="px-3 py-2 text-right">{{ $m }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($printers as $p)
                        @php $prev = null; @endphp
                        <tr class="border-b border-gray-800">
                            <td class="px-3 py-2 text-gray-100 font-medium sticky left-0 bg-gray-800/95">{{ $p->name }}</td>
                            @foreach($meses as $num => $label)
                                @php
                                    $val = $monthly[$p->id][$num] ?? null;
                                    $delta = ($val !== null && $prev !== null) ? ($val - $prev) : null;
                                    if ($val !== null) { $prev = $val; }
                                @endphp
                                <td class="px-3 py-2 text-right">
                                    @if($val !== null)
                                        <div class="text-gray-200 font-mono">{{ number_format($val, 0, ',', '.') }}</div>
                                        @if($delta !== null)
                                            <div class="text-[10px] {{ $delta > 0 ? 'text-amber-400' : 'text-gray-500' }}">
                                                {{ $delta > 0 ? '+'.number_format($delta, 0, ',', '.') : $delta }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-gray-600">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-xs text-gray-500 px-4 py-3">
                El número grande es el contador total al cierre del mes. El número chico ámbar (+N) son las páginas impresas durante ese mes (diferencia con el mes anterior con lectura).
            </p>
        </div>

        {{-- Diferencia mes a mes: páginas nuevas por mes --}}
        <div class="bg-gray-800/50 rounded-lg border border-gray-700 overflow-x-auto mt-6">
            <h3 class="text-sm font-semibold text-amber-400 px-4 pt-4 pb-2">
                Páginas nuevas por mes {{ $year }} — diferencia con el mes anterior
            </h3>
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-gray-400 border-b border-gray-700">
                        <th class="px-3 py-2 sticky left-0 bg-gray-800">Impresora</th>
                        @foreach($meses as $m)
                            <th class="px-3 py-2 text-right">{{ $m }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-right">Total año</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($printers as $p)
                        @php $totalYear = 0; $hasAny = false; @endphp
                        <tr class="border-b border-gray-800">
                            <td class="px-3 py-2 text-gray-100 font-medium sticky left-0 bg-gray-800/95">{{ $p->name }}</td>
                            @foreach($meses as $num => $label)
                                @php
                                    $d = $deltas[$p->id][$num] ?? null;
                                    if ($d !== null) { $totalYear += $d; $hasAny = true; }
                                @endphp
                                <td class="px-3 py-2 text-right">
                                    @if($d !== null)
                                        <span class="font-mono font-semibold {{ $d > 0 ? 'text-amber-400' : 'text-gray-400' }}">
                                            {{ number_format($d, 0, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-gray-600">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-right">
                                @if($hasAny)
                                    <span class="font-mono font-bold text-green-400">{{ number_format($totalYear, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-gray-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-xs text-gray-500 px-4 py-3">
                Páginas impresas durante cada mes (contador del mes menos el del mes anterior con lectura). Enero se calcula contra el cierre de diciembre del año anterior si hay dato. La columna "Total año" suma las páginas de todos los meses.
            </p>
        </div>

        {{-- Carga manual del contador de un mes --}}
        <div class="bg-gray-800/50 rounded-lg border border-gray-700 mt-6 p-4">
            <h3 class="text-sm font-semibold text-amber-400 mb-1">Cargar contador de un mes manualmente</h3>
            <p class="text-xs text-gray-500 mb-4">
                Ingresá el contador total al cierre del mes (el número que marcaba la impresora a fin de ese mes). Si ya existe una carga manual para ese mes, se actualiza.
            </p>

            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400">Impresora</label>
                    <select wire:model="manualPrinterId" class="rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0 min-w-[180px]">
                        <option value="">— Elegir —</option>
                        @foreach($printers as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400">Mes</label>
                    <select wire:model="manualMonth" class="rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
                        @foreach($meses as $num => $label)
                            <option value="{{ $num }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400">Año</label>
                    <input type="number" min="2000" max="2100" wire:model="manualYear"
                        class="w-24 rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400">Contador al cierre</label>
                    <input type="number" min="0" wire:model="manualCount" placeholder="Ej: 15230"
                        class="w-36 rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1.5 text-sm focus:border-amber-400 focus:ring-0">
                </div>

                <x-filament::button wire:click="saveManualReading" color="warning" icon="heroicon-o-pencil-square">
                    Guardar contador
                </x-filament::button>
            </div>

            @error('manualPrinterId') <p class="text-xs text-red-400 mt-2">{{ $message }}</p> @enderror
            @error('manualCount') <p class="text-xs text-red-400 mt-2">{{ $message }}</p> @enderror
        </div>
    @endif

</x-filament-panels::page>
