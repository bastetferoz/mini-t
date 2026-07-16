<x-filament-panels::page>

    {{-- Botones de carga --}}
    <div class="flex justify-end gap-3 mb-6">
        {{ $this->uploadAiAction }}
        {{ $this->manualCreateAction }}
    </div>

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm mb-6 bg-gray-800/50 rounded-lg px-4 py-2 border border-gray-700">
        <button wire:click="goToRoot" class="hover:text-amber-400 transition {{ !$selectedProvider && !$selectedYear ? 'text-amber-400 font-semibold' : 'text-gray-400' }}">
            📁 Proveedores
        </button>

        @if($selectedProvider)
            <span class="text-gray-600">/</span>
            <button wire:click="goToProvider('{{ $selectedProvider }}')" class="hover:text-amber-400 transition {{ $selectedProvider && !$selectedYear ? 'text-amber-400 font-semibold' : 'text-gray-400' }}">
                {{ $this->getProviderLabel($selectedProvider) }}
            </button>
        @endif

        @if($selectedYear)
            <span class="text-gray-600">/</span>
            <span class="text-amber-400 font-semibold">{{ $selectedYear }}</span>
        @endif
    </nav>

    {{-- NIVEL 1: Lista de proveedores --}}
    @if(!$selectedProvider)
        <div class="mb-6">
            <input type="text" wire:model.live.debounce.300ms="searchProvider" placeholder="Buscar proveedor..." class="w-full md:w-80 rounded-lg border-gray-600 bg-gray-800 text-white text-sm px-4 py-2.5 placeholder-gray-500 focus:border-amber-500 focus:ring-amber-500" />
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @forelse($this->getProviders() as $provider)
                <button wire:click="goToProvider('{{ $provider->provider }}')" class="group flex flex-col items-center gap-3 p-6 rounded-xl border border-gray-700 bg-gray-800/50 hover:bg-gray-700/70 hover:border-amber-500/50 transition-all">
                    <div class="w-14 h-14 rounded-full bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition">
                        <x-heroicon-o-folder class="w-8 h-8 text-amber-400" />
                    </div>
                    <span class="text-sm font-medium text-white text-center">{{ $this->getProviderLabel($provider->provider) }}</span>
                    <span class="text-xs text-gray-500">{{ $provider->total }} {{ $provider->total === 1 ? 'factura' : 'facturas' }}</span>
                </button>
            @empty
                <div class="col-span-full text-center py-16 text-gray-500">
                    <p>No hay facturas cargadas.</p>
                    <p class="text-sm mt-1">Usá "Cargar con IA" para empezar.</p>
                </div>
            @endforelse
        </div>
    @endif

    {{-- NIVEL 2: Años --}}
    @if($selectedProvider && !$selectedYear)
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @forelse($this->getYears() as $year)
                <button wire:click="goToYear({{ $year->year }})" class="group flex flex-col items-center gap-3 p-6 rounded-xl border border-gray-700 bg-gray-800/50 hover:bg-gray-700/70 hover:border-amber-500/50 transition-all">
                    <div class="w-14 h-14 rounded-full bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition">
                        <x-heroicon-o-folder-open class="w-8 h-8 text-amber-400" />
                    </div>
                    <span class="text-sm font-medium text-white">{{ $year->year }}</span>
                    <span class="text-xs text-gray-500">{{ $year->total }} {{ $year->total === 1 ? 'factura' : 'facturas' }}</span>
                </button>
            @empty
                <div class="col-span-full text-center py-16 text-gray-500">No hay facturas para este proveedor.</div>
            @endforelse
        </div>
    @endif

    {{-- NIVEL 3: Facturas del proveedor/año --}}
    @if($selectedProvider && $selectedYear)
        @php
            $invoices = $this->getInvoices();
            $monthNames = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        @endphp

        <div class="overflow-x-auto rounded-xl border border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="text-left py-3 px-4 text-gray-400 font-medium">Mes</th>
                        <th class="text-left py-3 px-4 text-gray-400 font-medium">Servicio</th>
                        <th class="text-left py-3 px-4 text-gray-400 font-medium">Referencia</th>
                        <th class="text-right py-3 px-4 text-gray-400 font-medium">Monto</th>
                        <th class="text-center py-3 px-4 text-gray-400 font-medium">Moneda</th>
                        <th class="text-left py-3 px-4 text-gray-400 font-medium">Nº Factura</th>
                        <th class="text-left py-3 px-4 text-gray-400 font-medium">Fecha</th>
                        <th class="text-left py-3 px-4 text-gray-400 font-medium">Empresa</th>
                        <th class="text-center py-3 px-4 text-gray-400 font-medium">Archivo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr class="border-t border-gray-800 hover:bg-gray-800/50">
                            <td class="py-3 px-4 text-white font-medium">{{ $monthNames[$invoice->month] ?? $invoice->month }}</td>
                            <td class="py-3 px-4 text-gray-300">{{ $invoice->service ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-300">{{ $invoice->reference ?? '—' }}</td>
                            <td class="py-3 px-4 text-right text-white font-medium">${{ number_format($invoice->amount, 2, ',', '.') }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $invoice->currency === 'USD' ? 'bg-green-500/20 text-green-400' : 'bg-blue-500/20 text-blue-400' }}">
                                    {{ $invoice->currency }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-300">{{ $invoice->invoice_number ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-400">{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                            <td class="py-3 px-4 text-gray-300">{{ $invoice->company ? ucfirst($invoice->company) : '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                @if($invoice->file_path)
                                    <a href="{{ asset('storage/' . $invoice->file_path) }}" target="_blank" class="text-amber-400 hover:text-amber-300 text-xs">📎 Ver</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-8 text-center text-gray-500">Sin facturas en este período.</td></tr>
                    @endforelse
                </tbody>
                @if($invoices->count() > 0)
                <tfoot class="bg-gray-800/50">
                    <tr class="border-t border-gray-600">
                        <td class="py-3 px-4 font-semibold text-white" colspan="3">Total</td>
                        <td class="py-3 px-4 text-right font-bold text-amber-400">${{ number_format($invoices->sum('amount'), 2, ',', '.') }}</td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    @endif

</x-filament-panels::page>
