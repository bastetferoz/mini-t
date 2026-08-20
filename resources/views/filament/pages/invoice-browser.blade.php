<x-filament-panels::page>

    {{-- Estado de la cola de procesamiento --}}
    @php
        $pendingJobs = \DB::table('jobs')->where('queue', 'default')->count();
        $failedJobs = \DB::table('failed_jobs')->count();
        $recentProcessed = \App\Models\Invoice::where('created_at', '>=', now()->subHour())->count();
    @endphp
    @if($pendingJobs > 0 || $failedJobs > 0 || $recentProcessed > 0)
    <div class="mb-6 flex items-center gap-4 bg-gray-800/50 rounded-lg px-4 py-3 border border-gray-700">
        <span class="text-xs text-gray-400 font-medium">Cola IA:</span>
        @if($pendingJobs > 0)
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span class="text-sm text-amber-400 font-medium">{{ $pendingJobs }} pendiente{{ $pendingJobs > 1 ? 's' : '' }}</span>
            </span>
        @endif
        @if($recentProcessed > 0)
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-green-400"></span>
                <span class="text-sm text-green-400">{{ $recentProcessed }} procesada{{ $recentProcessed > 1 ? 's' : '' }} (última hora)</span>
            </span>
        @endif
        @if($failedJobs > 0)
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                <span class="text-sm text-red-400">{{ $failedJobs }} fallida{{ $failedJobs > 1 ? 's' : '' }}</span>
            </span>
        @endif
        @if($pendingJobs === 0 && $failedJobs === 0 && $recentProcessed === 0)
            <span class="text-sm text-gray-500">Sin actividad</span>
        @endif
        <button wire:click="$refresh" class="ml-auto text-gray-500 hover:text-white transition text-xs" title="Refrescar">
            🔄
        </button>
    </div>
    @endif

    {{-- Botones de carga --}}
    <div class="flex justify-end gap-3 mb-6">
        <x-filament::button wire:click="removeDuplicates" color="warning" icon="heroicon-o-trash" size="sm" wire:confirm="¿Eliminar todas las facturas duplicadas? Se conserva la primera de cada grupo.">
            Eliminar duplicados
        </x-filament::button>
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
                @php
                    $isOtro = $provider->provider === 'otro';
                    $categoryIcon = match(\App\Models\InvoiceProvider::where('slug', $provider->provider)->value('category')) {
                        'cloud' => 'heroicon-o-cloud',
                        'internet' => 'heroicon-o-globe-alt',
                        'telefonia' => 'heroicon-o-phone',
                        'licencias' => 'heroicon-o-key',
                        'devtool' => 'heroicon-o-code-bracket',
                        'dominios' => 'heroicon-o-globe-americas',
                        'ia' => 'heroicon-o-cpu-chip',
                        'seguridad' => 'heroicon-o-shield-check',
                        'comunicaciones' => 'heroicon-o-chat-bubble-left-right',
                        default => 'heroicon-o-folder',
                    };
                @endphp
                <button wire:click="goToProvider('{{ $provider->provider }}')" class="group flex flex-col items-center gap-3 p-6 rounded-xl border {{ $isOtro ? 'border-red-700/50 bg-red-900/20 hover:bg-red-800/30 hover:border-red-500/50' : 'border-gray-700 bg-gray-800/50 hover:bg-gray-700/70 hover:border-amber-500/50' }} transition-all">
                    <div class="w-14 h-14 rounded-full {{ $isOtro ? 'bg-red-500/10 group-hover:bg-red-500/20' : 'bg-amber-500/10 group-hover:bg-amber-500/20' }} flex items-center justify-center transition">
                        <x-dynamic-component :component="$categoryIcon" class="w-8 h-8 {{ $isOtro ? 'text-red-400' : 'text-amber-400' }}" />
                    </div>
                    <span class="text-sm font-medium text-white text-center">{{ $this->getProviderLabel($provider->provider) }}</span>
                    <span class="text-xs {{ $isOtro ? 'text-red-400' : 'text-gray-500' }}">{{ $provider->total }} {{ $provider->total === 1 ? 'factura' : 'facturas' }}</span>
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

        {{-- Botón reclasificar si estamos en "otro" --}}
        @if($selectedProvider === 'otro' && $invoices->count() > 0)
            <div class="mb-4">
                <x-filament::button wire:click="reclassifyAll" color="info" icon="heroicon-o-arrow-path" size="sm">
                    Reclasificar todas ({{ $invoices->count() }})
                </x-filament::button>
            </div>
        @endif

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
                        <th class="text-center py-3 px-4 text-gray-400 font-medium w-10"></th>
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
                            <td class="py-3 px-4 text-center">
                                <button wire:click="deleteInvoice({{ $invoice->id }})" wire:confirm="¿Eliminar esta factura?" class="text-red-400/60 hover:text-red-400 transition" title="Eliminar">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-8 text-center text-gray-500">Sin facturas en este período.</td></tr>
                    @endforelse
                </tbody>
                @if($invoices->count() > 0)
                <tfoot class="bg-gray-800/50">
                    <tr class="border-t border-gray-600">
                        <td class="py-3 px-4 font-semibold text-white" colspan="3">Total</td>
                        <td class="py-3 px-4 text-right font-bold text-amber-400">${{ number_format($invoices->sum('amount'), 2, ',', '.') }}</td>
                        <td colspan="6"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    @endif

</x-filament-panels::page>
