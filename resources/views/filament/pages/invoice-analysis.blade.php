<x-filament-panels::page>

    {{-- Filtros --}}
    <div class="flex items-center gap-4 mb-6">
        <div>
            <label class="text-xs text-gray-500 uppercase tracking-wide">Año</label>
            <select wire:model.live="selectedYear" class="block mt-1 rounded-lg border-gray-600 bg-gray-800 text-white text-sm px-3 py-2">
                @foreach ($this->getAvailableYears() as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 uppercase tracking-wide">Moneda</label>
            <select wire:model.live="selectedCurrency" class="block mt-1 rounded-lg border-gray-600 bg-gray-800 text-white text-sm px-3 py-2">
                <option value="ARS">ARS (Pesos)</option>
                <option value="USD">USD (Dólares)</option>
            </select>
        </div>
    </div>

    {{-- Resumen anual --}}
    @php
        $comparison = $this->getYearComparison();
        $monthlyData = $this->getMonthlyData();
        $byProvider = $this->getByProvider();
        $yearTotal = $this->getYearTotal();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Total año --}}
        <x-filament::card>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total {{ $selectedYear }}</p>
            <p class="text-2xl font-bold text-white mt-1">
                {{ $selectedCurrency }} {{ number_format($yearTotal, 2, ',', '.') }}
            </p>
        </x-filament::card>

        {{-- Año anterior --}}
        <x-filament::card>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total {{ (int)$selectedYear - 1 }}</p>
            <p class="text-2xl font-bold text-white mt-1">
                {{ $selectedCurrency }} {{ number_format($comparison['previous'], 2, ',', '.') }}
            </p>
        </x-filament::card>

        {{-- Variación --}}
        <x-filament::card>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Variación interanual</p>
            <p class="text-2xl font-bold mt-1 {{ $comparison['diff_percent'] > 0 ? 'text-red-400' : 'text-green-400' }}">
                {{ $comparison['diff_percent'] > 0 ? '+' : '' }}{{ $comparison['diff_percent'] }}%
            </p>
        </x-filament::card>
    </div>

    {{-- Tabla mes a mes --}}
    <x-filament::card class="mb-6">
        <h3 class="text-sm font-semibold text-white mb-4">Detalle mensual — {{ $selectedYear }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left py-2 px-3 text-gray-400">Mes</th>
                        <th class="text-right py-2 px-3 text-gray-400">Monto</th>
                        <th class="text-right py-2 px-3 text-gray-400">% del total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                    @endphp
                    @foreach ($monthlyData as $period => $total)
                        @php
                            $monthIndex = (int) explode('-', $period)[1] - 1;
                            $percent = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0;
                        @endphp
                        <tr class="border-b border-gray-800 {{ $total > 0 ? '' : 'opacity-40' }}">
                            <td class="py-2 px-3 text-gray-300">{{ $monthNames[$monthIndex] }}</td>
                            <td class="py-2 px-3 text-right text-white font-medium">
                                {{ $selectedCurrency }} {{ number_format($total, 2, ',', '.') }}
                            </td>
                            <td class="py-2 px-3 text-right text-gray-400">{{ $percent }}%</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-2 px-3 font-semibold text-white">Total</td>
                        <td class="py-2 px-3 text-right font-bold text-white">
                            {{ $selectedCurrency }} {{ number_format($yearTotal, 2, ',', '.') }}
                        </td>
                        <td class="py-2 px-3 text-right text-gray-400">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::card>

    {{-- Desglose por proveedor --}}
    <x-filament::card>
        <h3 class="text-sm font-semibold text-white mb-4">Por proveedor — {{ $selectedYear }}</h3>
        <div class="space-y-3">
            @forelse ($byProvider as $provider => $total)
                @php
                    $providerLabel = match ($provider) {
                        'telecom' => 'Telecom',
                        'metrotel' => 'Metrotel',
                        'amazon' => 'Amazon (AWS)',
                        'microsoft' => 'Microsoft',
                        'google' => 'Google',
                        'movistar' => 'Movistar',
                        'claro' => 'Claro',
                        'iplan' => 'iPlan',
                        'otro' => 'Otro',
                        default => ucfirst($provider),
                    };
                    $percent = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0;
                @endphp
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-300">{{ $providerLabel }}</span>
                        <span class="text-white font-medium">
                            {{ $selectedCurrency }} {{ number_format($total, 2, ',', '.') }}
                            <span class="text-gray-500 text-xs">({{ $percent }}%)</span>
                        </span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-2">
                        <div class="bg-amber-500 h-2 rounded-full" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 text-center py-4">Sin datos para este período.</p>
            @endforelse
        </div>
    </x-filament::card>

</x-filament-panels::page>
