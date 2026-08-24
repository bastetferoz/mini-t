<x-filament-panels::page>

    @php
        $comparison = $this->getYearComparison();
        $monthlyData = $this->getMonthlyData();
        $yearTotal = $this->getYearTotal();
        $monthNames = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    @endphp

    {{-- ═══ MÉTRICAS SUPERIORES ═══ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total año seleccionado --}}
        <div class="rounded-xl border border-amber-500/30 bg-gradient-to-br from-amber-500/5 to-transparent p-4 flex items-start justify-between">
            <div>
                <p class="text-xs text-amber-400 uppercase font-semibold tracking-wide">Total {{ $selectedYear }}</p>
                <p class="text-2xl font-bold text-white mt-1">USD {{ number_format($yearTotal, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Año seleccionado</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <x-heroicon-o-currency-dollar class="w-6 h-6 text-amber-400" />
            </div>
        </div>

        {{-- Total año anterior --}}
        <div class="rounded-xl border border-gray-700 bg-gray-800/30 p-4 flex items-start justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide">Total {{ (int)$selectedYear - 1 }}</p>
                <p class="text-2xl font-bold text-white mt-1">USD {{ number_format($comparison['previous'], 2, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Año anterior</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-gray-700/50 flex items-center justify-center">
                <x-heroicon-o-clock class="w-6 h-6 text-gray-400" />
            </div>
        </div>

        {{-- Variación interanual --}}
        @php
            $diffColor = $comparison['diff_percent'] > 0 ? 'red' : 'green';
            $diffIcon = $comparison['diff_percent'] > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down';
        @endphp
        <div class="rounded-xl border border-{{ $diffColor }}-500/30 bg-gradient-to-br from-{{ $diffColor }}-500/5 to-transparent p-4 flex items-start justify-between">
            <div>
                <p class="text-xs text-{{ $diffColor }}-400 uppercase font-semibold tracking-wide">Variación interanual</p>
                <p class="text-2xl font-bold text-{{ $diffColor }}-400 mt-1">
                    {{ $comparison['diff_percent'] > 0 ? '+' : '' }}{{ $comparison['diff_percent'] }}%
                </p>
                <p class="text-xs text-gray-500 mt-1">vs {{ (int)$selectedYear - 1 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-{{ $diffColor }}-500/10 flex items-center justify-center">
                <x-dynamic-component :component="$diffIcon" class="w-6 h-6 text-{{ $diffColor }}-400" />
            </div>
        </div>

        {{-- Promedio mensual --}}
        <div class="rounded-xl border border-blue-500/30 bg-gradient-to-br from-blue-500/5 to-transparent p-4 flex items-start justify-between">
            <div>
                <p class="text-xs text-blue-400 uppercase font-semibold tracking-wide">Promedio mensual</p>
                <p class="text-2xl font-bold text-white mt-1">
                    USD {{ number_format($yearTotal > 0 ? $yearTotal / 12 : 0, 2, ',', '.') }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Gasto promedio</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                <x-heroicon-o-calculator class="w-6 h-6 text-blue-400" />
            </div>
        </div>
    </div>

    {{-- ═══ FILTROS ═══ --}}
    <div class="rounded-xl border border-gray-700 bg-gray-800/30 p-5 mb-6">
        <div class="flex flex-wrap items-end gap-6">
            {{-- Año --}}
            <div>
                <label class="text-xs text-gray-400 uppercase tracking-wide font-semibold block mb-2">Año</label>
                <select wire:model.live="selectedYear" class="rounded-lg border-gray-600 bg-gray-900 text-white text-sm px-4 py-2.5 focus:border-amber-500 focus:ring-amber-500 min-w-[100px]">
                    @foreach ($this->getAvailableYears() as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Moneda --}}
            <div>
                <label class="text-xs text-gray-400 uppercase tracking-wide font-semibold block mb-2">Moneda</label>
                <select wire:model.live="viewMode" class="rounded-lg border-gray-600 bg-gray-900 text-white text-sm px-4 py-2.5 focus:border-amber-500 focus:ring-amber-500">
                    <option value="providers">Moneda original</option>
                    <option value="companies">Convertido a USD</option>
                </select>
            </div>

            {{-- Exportar --}}
            <div class="ml-auto">
                <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium transition print:hidden border border-gray-600">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    Exportar
                </button>
            </div>
        </div>

        {{-- Proveedores --}}
        <div class="mt-5 pt-4 border-t border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide flex items-center gap-2">
                    <x-heroicon-o-building-storefront class="w-4 h-4" />
                    Proveedores
                </h3>
                <div class="flex gap-3">
                    <button wire:click="selectAllProviders" class="text-xs text-amber-400 hover:text-amber-300 transition">Todos</button>
                    <button wire:click="deselectAllProviders" class="text-xs text-gray-500 hover:text-gray-300 transition">Ninguno</button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($this->getAvailableProviders() as $provider)
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg cursor-pointer text-xs transition {{ in_array($provider, $selectedProviders) ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'text-gray-500 border border-gray-700 hover:border-gray-500' }}">
                        <input type="checkbox" wire:click="toggleProvider('{{ $provider }}')" {{ in_array($provider, $selectedProviders) ? 'checked' : '' }} class="rounded border-gray-600 text-amber-500 w-3 h-3 focus:ring-amber-500">
                        {{ $this->getProviderLabel($provider) }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Empresas --}}
        @if(count($this->getAvailableCompanies()) > 0)
        <div class="mt-4 pt-4 border-t border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide flex items-center gap-2">
                    <x-heroicon-o-building-office class="w-4 h-4" />
                    Empresas
                </h3>
                <div class="flex gap-3">
                    <button wire:click="selectAllCompanies" class="text-xs text-blue-400 hover:text-blue-300 transition">Todas</button>
                    <button wire:click="deselectAllCompanies" class="text-xs text-gray-500 hover:text-gray-300 transition">Ninguna</button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($this->getAvailableCompanies() as $company)
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg cursor-pointer text-xs transition {{ in_array($company, $selectedCompanies) ? 'bg-blue-500/10 text-blue-400 border border-blue-500/30' : 'text-gray-500 border border-gray-700 hover:border-gray-500' }}">
                        <input type="checkbox" wire:click="toggleCompany('{{ $company }}')" {{ in_array($company, $selectedCompanies) ? 'checked' : '' }} class="rounded border-gray-600 text-blue-500 w-3 h-3 focus:ring-blue-500">
                        {{ ucfirst($company) }}
                    </label>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ═══ TABLA MES A MES ═══ --}}
    <div class="rounded-xl border border-gray-700 bg-gray-800/30 p-5 mb-6" id="tabla-analisis">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <x-heroicon-o-table-cells class="w-5 h-5 text-amber-400" />
                Por proveedor — mes a mes
                <span class="text-xs font-normal text-gray-500">({{ $viewMode === 'companies' ? 'USD convertido' : 'moneda original' }})</span>
            </h3>
        </div>

        <div class="overflow-x-auto">
            @php
                $maxMonth = (int) $selectedYear == now()->year ? now()->month : 12;
            @endphp
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-600">
                        <th class="text-left py-3 px-3 text-gray-400 font-semibold sticky left-0 bg-gray-800/80 backdrop-blur-sm">Proveedor</th>
                        @for($m = 1; $m <= $maxMonth; $m++)
                            <th class="text-right py-3 px-2 text-gray-400 font-semibold">{{ $monthNames[$m] }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @php
                        $tableData = $this->getMonthlyByProvider();
                        $monthTotals = array_fill(1, 12, 0);
                        $grandTotal = 0;
                    @endphp
                    @foreach($tableData as $label => $months)
                        @php
                            $rowTotal = array_sum($months);
                            $grandTotal += $rowTotal;
                            foreach ($months as $m => $val) { $monthTotals[$m] += $val; }
                            if ($viewMode === 'providers') {
                                $providerCurrency = \App\Models\InvoiceProvider::where('slug', $label)->value('default_currency') ?? 'USD';
                                $cellColor = $providerCurrency === 'ARS' ? 'text-blue-400' : 'text-green-400';
                            } else {
                                $cellColor = 'text-green-400';
                            }
                            $isMulti = \App\Models\InvoiceProvider::where('slug', $label)->value('is_multi') ?? false;
                            $isChartSelected = $selectedChartProvider === $label;
                        @endphp
                        <tbody @if($isMulti) x-data="{ open: false }" @endif>
                        {{-- Fila principal --}}
                        <tr class="border-b border-gray-600 hover:bg-gray-700/30 transition cursor-pointer {{ $isChartSelected ? 'bg-amber-500/5 border-l-2 border-l-amber-500' : '' }}" @if($isMulti) x-on:click="open = !open" @endif>
                            <td class="py-2.5 px-3 text-white font-medium sticky left-0 backdrop-blur-sm {{ $isChartSelected ? 'bg-amber-500/5' : 'bg-gray-800/80' }}">
                                <span class="flex items-center gap-2">
                                    <button wire:click="toggleChartProvider('{{ $label }}')" class="p-0.5 rounded hover:bg-amber-500/20 transition {{ $isChartSelected ? 'text-amber-400 bg-amber-500/10' : 'text-gray-500 hover:text-amber-400' }}" title="Ver gráfico">
                                        <x-heroicon-o-chart-bar class="w-3.5 h-3.5" />
                                    </button>
                                    <span>{{ $this->getProviderLabel($label) }}</span>
                                    @if($isMulti)
                                        <span class="text-[10px] text-gray-500" x-text="open ? '▲' : '▼'">▼</span>
                                    @endif
                                </span>
                            </td>
                            @for($m = 1; $m <= $maxMonth; $m++)
                                <td class="py-2.5 px-2 text-right {{ $months[$m] > 0 ? $cellColor : 'text-gray-700' }}">
                                    {{ $months[$m] > 0 ? number_format($months[$m], 2, ',', '.') : '—' }}
                                </td>
                            @endfor
                        </tr>
                        {{-- Sub-filas si es multi --}}
                        @if($isMulti)
                            @php $subData = $this->getMonthlyByReference($label); @endphp
                            @foreach($subData as $ref => $refMonths)
                                <tr class="border-b border-gray-600" x-show="open" x-cloak>
                                    <td class="py-1.5 px-3 pl-10 text-gray-400 text-xs sticky left-0 bg-gray-800/80 backdrop-blur-sm">
                                        ↳ {{ $ref ?: '(sin referencia)' }}
                                    </td>
                                    @for($m = 1; $m <= $maxMonth; $m++)
                                        <td class="py-1.5 px-2 text-right text-xs {{ $refMonths[$m] > 0 ? 'text-gray-500' : 'text-gray-800' }}">
                                            {{ $refMonths[$m] > 0 ? number_format($refMonths[$m], 2, ',', '.') : '' }}
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-amber-500/30">
                        <td class="py-3 px-3 font-bold text-amber-400 sticky left-0 bg-gray-800/80 backdrop-blur-sm">Total</td>
                        @for($m = 1; $m <= $maxMonth; $m++)
                            <td class="py-3 px-2 text-right font-bold text-white">
                                {{ $monthTotals[$m] > 0 ? number_format($monthTotals[$m], 2, ',', '.') : '—' }}
                            </td>
                        @endfor
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Cotizaciones (solo modo convertido) --}}
        @if($viewMode === 'companies')
            <div class="mt-4 pt-4 border-t border-gray-700">
                <p class="text-xs text-gray-500 mb-2 flex items-center gap-1.5">
                    <x-heroicon-o-banknotes class="w-3.5 h-3.5" />
                    Cotización BNA (venta) usada por mes:
                </p>
                <div class="flex flex-wrap gap-2">
                    @for($m = 1; $m <= 12; $m++)
                        @php
                            $rate = \App\Models\Invoice::where('year', $selectedYear)
                                ->where('month', $m)
                                ->where('currency', 'ARS')
                                ->whereNotNull('exchange_rate')
                                ->value('exchange_rate');
                        @endphp
                        @if($rate)
                            <span class="text-xs px-2.5 py-1 rounded-lg bg-gray-900 text-gray-400 border border-gray-700">
                                {{ $monthNames[$m] }}: <span class="text-white font-medium">${{ number_format($rate, 2, ',', '.') }}</span>
                            </span>
                        @endif
                    @endfor
                </div>
            </div>
        @endif
    </div>

    {{-- ═══ DESGLOSE ═══ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {{-- Por proveedor --}}
        <div class="rounded-xl border border-gray-700 bg-gray-800/30 p-5">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-building-storefront class="w-4 h-4 text-amber-400" />
                Por proveedor
            </h3>
            @php $byProvider = $this->getByProvider(); @endphp
            <div class="space-y-2">
                @forelse ($byProvider as $provider => $total)
                    @php $percent = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-0.5">
                            <span class="text-gray-300">{{ $this->getProviderLabel($provider) }}</span>
                            <span class="text-white">{{ number_format($total, 0, ',', '.') }} <span class="text-gray-500">({{ $percent }}%)</span></span>
                        </div>
                        <div class="w-full bg-gray-600/40 rounded-full h-1.5">
                            <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ min($percent, 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 text-center py-4">Sin datos.</p>
                @endforelse
            </div>
        </div>

        {{-- Por empresa --}}
        <div class="rounded-xl border border-gray-700 bg-gray-800/30 p-5">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-building-office class="w-4 h-4 text-blue-400" />
                Por empresa
            </h3>
            @php $byCompany = $this->getByCompany(); @endphp
            <div class="space-y-2">
                @forelse ($byCompany as $company => $total)
                    @php $percent = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-0.5">
                            <span class="text-gray-300">{{ ucfirst($company) }}</span>
                            <span class="text-white">{{ number_format($total, 0, ',', '.') }} <span class="text-gray-500">({{ $percent }}%)</span></span>
                        </div>
                        <div class="w-full bg-gray-600/40 rounded-full h-1.5">
                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ min($percent, 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 text-center py-4">Sin datos de empresa.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══ MODAL GRÁFICO ═══ --}}
    @if($selectedChartProvider)
        @php
            $chartData = $this->getCandlestickData();
            $chartMaxMonth = (int) $selectedYear == now()->year ? now()->month : 12;
            $allValues = array_merge(
                array_slice($chartData['current'], 0, $chartMaxMonth, true),
                array_slice($chartData['previous'], 0, 12, true)
            );
            $maxVal = max(array_filter($allValues) ?: [1]);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.toggleChartProvider('{{ $selectedChartProvider }}')">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" wire:click="toggleChartProvider('{{ $selectedChartProvider }}')"></div>

            {{-- Panel --}}
            <div class="relative w-full max-w-3xl bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl shadow-black/50 p-6 max-h-[85vh] overflow-y-auto">
                {{-- Header --}}
                <div class="flex items-center justify-between mb-6">
                    <h4 class="text-lg font-semibold text-white flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                            <x-heroicon-o-chart-bar class="w-5 h-5 text-amber-400" />
                        </div>
                        {{ $this->getProviderLabel($selectedChartProvider) }}
                    </h4>
                    <button wire:click="toggleChartProvider('{{ $selectedChartProvider }}')" class="text-gray-400 hover:text-white transition p-2 rounded-lg hover:bg-gray-800 border border-gray-700">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Leyenda --}}
                <div class="flex gap-4 mb-5 text-xs">
                    <span class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20">
                        <span class="w-3 h-3 rounded-sm bg-amber-500"></span>
                        <span class="text-amber-400 font-medium">{{ $selectedYear }}</span>
                    </span>
                    <span class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700">
                        <span class="w-3 h-3 rounded-sm bg-gray-500"></span>
                        <span class="text-gray-400 font-medium">{{ (int)$selectedYear - 1 }}</span>
                    </span>
                </div>

                {{-- Gráfico --}}
                <div class="rounded-xl border border-gray-700 bg-gray-800/30 p-4 mb-5">
                    <div class="flex items-end gap-2 h-56 px-1">
                        @for($m = 1; $m <= $chartMaxMonth; $m++)
                            @php
                                $currentVal = $chartData['current'][$m] ?? 0;
                                $previousVal = $chartData['previous'][$m] ?? 0;
                                $currentHeight = $maxVal > 0 ? ($currentVal / $maxVal) * 100 : 0;
                                $previousHeight = $maxVal > 0 ? ($previousVal / $maxVal) * 100 : 0;
                                $isUp = $currentVal >= $previousVal;
                            @endphp
                            <div class="flex-1 flex flex-col items-center gap-0.5">
                                <div class="w-full flex items-end justify-center gap-1 h-48">
                                    {{-- Barra año anterior --}}
                                    <div class="w-2/5 bg-gray-600 rounded-t transition-all relative group hover:bg-gray-500"
                                         style="height: {{ max($previousHeight, 2) }}%"
                                         title="{{ $monthNames[$m] }} {{ (int)$selectedYear - 1 }}: {{ number_format($previousVal, 2, ',', '.') }}">
                                        <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] text-gray-400 opacity-0 group-hover:opacity-100 whitespace-nowrap transition font-medium">
                                            {{ $previousVal > 0 ? number_format($previousVal, 0, ',', '.') : '' }}
                                        </span>
                                    </div>
                                    {{-- Barra año actual --}}
                                    <div class="w-2/5 rounded-t transition-all relative group {{ $isUp ? 'bg-amber-500 hover:bg-amber-400' : 'bg-red-500 hover:bg-red-400' }}"
                                         style="height: {{ max($currentHeight, 2) }}%"
                                         title="{{ $monthNames[$m] }} {{ $selectedYear }}: {{ number_format($currentVal, 2, ',', '.') }}">
                                        <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] text-white opacity-0 group-hover:opacity-100 whitespace-nowrap transition font-medium">
                                            {{ $currentVal > 0 ? number_format($currentVal, 0, ',', '.') : '' }}
                                        </span>
                                    </div>
                                </div>
                                <span class="text-[11px] text-gray-500 mt-1 font-medium">{{ $monthNames[$m] }}</span>
                            </div>
                        @endfor
                    </div>
                </div>

                {{-- Resumen --}}
                @php
                    $provCurrentTotal = array_sum(array_slice($chartData['current'], 0, $chartMaxMonth, true));
                    $provPreviousTotal = array_sum($chartData['previous']);
                    $provDiff = $provPreviousTotal > 0 ? round((($provCurrentTotal - $provPreviousTotal) / $provPreviousTotal) * 100, 1) : 0;
                @endphp
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 text-center">
                        <p class="text-[10px] text-amber-400 uppercase font-semibold">{{ $selectedYear }}</p>
                        <p class="text-sm font-bold text-white mt-0.5">{{ number_format($provCurrentTotal, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-700 bg-gray-800 p-3 text-center">
                        <p class="text-[10px] text-gray-400 uppercase font-semibold">{{ (int)$selectedYear - 1 }}</p>
                        <p class="text-sm font-bold text-white mt-0.5">{{ number_format($provPreviousTotal, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg border {{ $provDiff > 0 ? 'border-red-500/20 bg-red-500/5' : 'border-green-500/20 bg-green-500/5' }} p-3 text-center">
                        <p class="text-[10px] {{ $provDiff > 0 ? 'text-red-400' : 'text-green-400' }} uppercase font-semibold">Variación</p>
                        <p class="text-sm font-bold {{ $provDiff > 0 ? 'text-red-400' : 'text-green-400' }} mt-0.5">
                            {{ $provDiff > 0 ? '+' : '' }}{{ $provDiff }}%
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Estilos de impresión --}}
    <style>
        @media print {
            body * { visibility: hidden; }
            #tabla-analisis, #tabla-analisis * { visibility: visible; }
            #tabla-analisis { position: absolute; top: 0; left: 0; width: 100%; }
            body, html { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { size: landscape; margin: 1cm; }
        }
    </style>

</x-filament-panels::page>
