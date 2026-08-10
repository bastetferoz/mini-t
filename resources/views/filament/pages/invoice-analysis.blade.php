<x-filament-panels::page>

    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1">Año</label>
            <select wire:model.live="selectedYear" class="rounded-lg border-gray-600 bg-gray-800 text-white text-sm px-3 py-2">
                @foreach ($this->getAvailableYears() as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1">Moneda</label>
            <select wire:model.live="viewMode" class="rounded-lg border-gray-600 bg-gray-800 text-white text-sm px-3 py-2">
                <option value="providers">Original</option>
                <option value="companies">Convertido a USD</option>
            </select>
        </div>
    </div>

    {{-- Checklist: Proveedores --}}
    <x-filament::card class="mb-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-gray-400 uppercase">Proveedores</h3>
            <div class="flex gap-2">
                <button wire:click="selectAllProviders" class="text-xs text-amber-400 hover:underline">Todos</button>
                <button wire:click="deselectAllProviders" class="text-xs text-gray-500 hover:underline">Ninguno</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($this->getAvailableProviders() as $provider)
                <label class="flex items-center gap-1.5 px-2 py-1 rounded cursor-pointer text-xs {{ in_array($provider, $selectedProviders) ? 'bg-amber-500/10 text-amber-400' : 'text-gray-500' }}">
                    <input type="checkbox" wire:click="toggleProvider('{{ $provider }}')" {{ in_array($provider, $selectedProviders) ? 'checked' : '' }} class="rounded border-gray-600 text-amber-500 w-3 h-3">
                    {{ $this->getProviderLabel($provider) }}
                </label>
            @endforeach
        </div>
    </x-filament::card>

    {{-- Checklist: Empresas --}}
    @if(count($this->getAvailableCompanies()) > 0)
    <x-filament::card class="mb-6">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-gray-400 uppercase">Empresas</h3>
            <div class="flex gap-2">
                <button wire:click="selectAllCompanies" class="text-xs text-amber-400 hover:underline">Todas</button>
                <button wire:click="deselectAllCompanies" class="text-xs text-gray-500 hover:underline">Ninguna</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($this->getAvailableCompanies() as $company)
                <label class="flex items-center gap-1.5 px-2 py-1 rounded cursor-pointer text-xs {{ in_array($company, $selectedCompanies) ? 'bg-blue-500/10 text-blue-400' : 'text-gray-500' }}">
                    <input type="checkbox" wire:click="toggleCompany('{{ $company }}')" {{ in_array($company, $selectedCompanies) ? 'checked' : '' }} class="rounded border-gray-600 text-blue-500 w-3 h-3">
                    {{ ucfirst($company) }}
                </label>
            @endforeach
        </div>
    </x-filament::card>
    @endif

    @php
        $comparison = $this->getYearComparison();
        $monthlyData = $this->getMonthlyData();
        $yearTotal = $this->getYearTotal();
        $monthNames = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    @endphp

    {{-- Resumen --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-filament::card>
            <p class="text-xs text-gray-500 uppercase">Total {{ $selectedYear }} (USD)</p>
            <p class="text-xl font-bold text-white mt-1">USD {{ number_format($yearTotal, 2, ',', '.') }}</p>
        </x-filament::card>
        <x-filament::card>
            <p class="text-xs text-gray-500 uppercase">Total {{ (int)$selectedYear - 1 }} (USD)</p>
            <p class="text-xl font-bold text-white mt-1">USD {{ number_format($comparison['previous'], 2, ',', '.') }}</p>
        </x-filament::card>
        <x-filament::card>
            <p class="text-xs text-gray-500 uppercase">Variación interanual</p>
            <p class="text-xl font-bold mt-1 {{ $comparison['diff_percent'] > 0 ? 'text-red-400' : 'text-green-400' }}">
                {{ $comparison['diff_percent'] > 0 ? '+' : '' }}{{ $comparison['diff_percent'] }}%
            </p>
        </x-filament::card>
        <x-filament::card>
            <p class="text-xs text-gray-500 uppercase">Promedio mensual</p>
            <p class="text-xl font-bold text-white mt-1">
                USD {{ number_format($yearTotal > 0 ? $yearTotal / 12 : 0, 2, ',', '.') }}
            </p>
        </x-filament::card>
    </div>

    {{-- TABLA MES A MES --}}
    <x-filament::card class="mb-6">
        <h3 class="text-sm font-semibold text-white mb-4">
            Por proveedor — mes a mes ({{ $viewMode === 'companies' ? 'USD convertido' : 'moneda original' }})
        </h3>
        <div class="overflow-x-auto">
            @php
                $maxMonth = (int) $selectedYear == now()->year ? now()->month : 12;
            @endphp
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left py-2 px-2 text-gray-400 sticky left-0 bg-gray-900">Proveedor</th>
                        @for($m = 1; $m <= $maxMonth; $m++)
                            <th class="text-right py-2 px-2 text-gray-400">{{ $monthNames[$m] }}</th>
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
                        @endphp
                        <tbody @if($isMulti) x-data="{ open: false }" @endif>
                        {{-- Fila principal del proveedor --}}
                        <tr class="border-b border-gray-800 hover:bg-gray-800/50 {{ $isMulti ? 'cursor-pointer' : '' }}" @if($isMulti) x-on:click="open = !open" @endif>
                            <td class="py-2 px-2 text-white font-medium sticky left-0 bg-gray-900">
                                {{ $this->getProviderLabel($label) }}
                                @if($isMulti)
                                    <span class="text-[10px] text-gray-500 ml-1" x-text="open ? '▲' : '▼'">▼</span>
                                @endif
                            </td>
                            @for($m = 1; $m <= $maxMonth; $m++)
                                <td class="py-2 px-2 text-right {{ $months[$m] > 0 ? $cellColor : 'text-gray-700' }}">
                                    {{ $months[$m] > 0 ? number_format($months[$m], 0, ',', '.') : '—' }}
                                </td>
                            @endfor
                        </tr>
                        {{-- Sub-filas por referencia si es multi --}}
                        @if($isMulti)
                            @php
                                $subData = $this->getMonthlyByReference($label);
                            @endphp
                            @foreach($subData as $ref => $refMonths)
                                @php $refTotal = array_sum($refMonths); @endphp
                                <tr class="border-b border-gray-900/50" x-show="open" x-cloak>
                                    <td class="py-1 px-2 pl-6 text-gray-400 text-xs sticky left-0 bg-gray-900">
                                        ↳ {{ $ref ?: '(sin referencia)' }}
                                    </td>
                                    @for($m = 1; $m <= $maxMonth; $m++)
                                        <td class="py-1 px-2 text-right text-xs {{ $refMonths[$m] > 0 ? 'text-gray-500' : 'text-gray-800' }}">
                                            {{ $refMonths[$m] > 0 ? number_format($refMonths[$m], 0, ',', '.') : '' }}
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-2 px-2 font-bold text-white sticky left-0 bg-gray-900">Total</td>
                        @for($m = 1; $m <= $maxMonth; $m++)
                            <td class="py-2 px-2 text-right font-bold text-white">
                                {{ $monthTotals[$m] > 0 ? number_format($monthTotals[$m], 0, ',', '.') : '—' }}
                            </td>
                        @endfor
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Cotizaciones usadas (solo en modo convertido) --}}
        @if($viewMode === 'companies')
            <div class="mt-3 pt-3 border-t border-gray-800">
                <p class="text-xs text-gray-500 mb-2">Cotización BNA (venta) usada por mes:</p>
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
                            <span class="text-xs px-2 py-1 rounded bg-gray-800 text-gray-400">
                                {{ $monthNames[$m] }}: <span class="text-white">${{ number_format($rate, 2, ',', '.') }}</span>
                            </span>
                        @endif
                    @endfor
                </div>
            </div>
        @endif
    </x-filament::card>

    {{-- DESGLOSE --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Por proveedor --}}
        <x-filament::card>
            <h3 class="text-sm font-semibold text-white mb-4">Por proveedor</h3>
            @php $byProvider = $this->getByProvider(); @endphp
            <div class="space-y-2">
                @forelse ($byProvider as $provider => $total)
                    @php $percent = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-0.5">
                            <span class="text-gray-300">{{ $this->getProviderLabel($provider) }}</span>
                            <span class="text-white">{{ number_format($total, 0, ',', '.') }} <span class="text-gray-500">({{ $percent }}%)</span></span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-1.5">
                            <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ min($percent, 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 text-center py-4">Sin datos.</p>
                @endforelse
            </div>
        </x-filament::card>

        {{-- Por empresa --}}
        <x-filament::card>
            <h3 class="text-sm font-semibold text-white mb-4">Por empresa</h3>
            @php $byCompany = $this->getByCompany(); @endphp
            <div class="space-y-2">
                @forelse ($byCompany as $company => $total)
                    @php $percent = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-0.5">
                            <span class="text-gray-300">{{ ucfirst($company) }}</span>
                            <span class="text-white">{{ number_format($total, 0, ',', '.') }} <span class="text-gray-500">({{ $percent }}%)</span></span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-1.5">
                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ min($percent, 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 text-center py-4">Sin datos de empresa.</p>
                @endforelse
            </div>
        </x-filament::card>
    </div>

</x-filament-panels::page>
