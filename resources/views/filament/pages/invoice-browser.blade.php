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

        {{-- Buscador --}}
        <div class="mb-6">
            <input
                type="text"
                wire:model.live.debounce.300ms="searchProvider"
                placeholder="Buscar proveedor..."
                class="w-full md:w-80 rounded-lg border-gray-600 bg-gray-800 text-white text-sm px-4 py-2.5 placeholder-gray-500 focus:border-amber-500 focus:ring-amber-500"
            />
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @forelse($this->getProviders() as $provider)
                <button
                    wire:click="goToProvider('{{ $provider->provider }}')"
                    class="group flex flex-col items-center gap-3 p-6 rounded-xl border border-gray-700 bg-gray-800/50 hover:bg-gray-700/70 hover:border-amber-500/50 transition-all duration-200"
                >
                    <div class="w-14 h-14 rounded-full bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition">
                        <x-heroicon-o-folder class="w-8 h-8 text-amber-400" />
                    </div>
                    <span class="text-sm font-medium text-white text-center">{{ $this->getProviderLabel($provider->provider) }}</span>
                    <span class="text-xs text-gray-500">{{ $provider->total }} {{ $provider->total === 1 ? 'factura' : 'facturas' }}</span>
                </button>
            @empty
                <div class="col-span-full text-center py-16 text-gray-500">
                    <x-heroicon-o-document-plus class="w-12 h-12 mx-auto mb-3 text-gray-600" />
                    <p>No hay facturas cargadas.</p>
                    <p class="text-sm mt-1">Usá "Cargar con IA" para empezar.</p>
                </div>
            @endforelse
        </div>
    @endif

    {{-- NIVEL 2: Lista de años de un proveedor --}}
    @if($selectedProvider && !$selectedYear)
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @forelse($this->getYears() as $year)
                <button
                    wire:click="goToYear({{ $year->year }})"
                    class="group flex flex-col items-center gap-3 p-6 rounded-xl border border-gray-700 bg-gray-800/50 hover:bg-gray-700/70 hover:border-amber-500/50 transition-all duration-200"
                >
                    <div class="w-14 h-14 rounded-full bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition">
                        <x-heroicon-o-folder-open class="w-8 h-8 text-amber-400" />
                    </div>
                    <span class="text-sm font-medium text-white">{{ $year->year }}</span>
                    <span class="text-xs text-gray-500">{{ $year->total }} {{ $year->total === 1 ? 'factura' : 'facturas' }}</span>
                </button>
            @empty
                <div class="col-span-full text-center py-16 text-gray-500">
                    No hay facturas para este proveedor.
                </div>
            @endforelse
        </div>
    @endif

    {{-- NIVEL 3 se muestra en el InvoiceResource con filtros aplicados --}}

</x-filament-panels::page>
