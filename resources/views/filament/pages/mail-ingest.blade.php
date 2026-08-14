<x-filament-panels::page>

    {{-- Estado actual --}}
    @php $config = $this->getConfig(); @endphp
    @if($config)
    <x-filament::card class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-white">{{ $config->name }}</h3>
                <p class="text-xs text-gray-400 mt-1">{{ $config->email }}</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-xs text-gray-500">Último check</p>
                    <p class="text-sm text-white">{{ $config->last_checked_at?->diffForHumans() ?? 'Nunca' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Procesadas</p>
                    <p class="text-sm text-green-400 font-bold">{{ $config->total_processed }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Errores</p>
                    <p class="text-sm text-red-400 font-bold">{{ $config->total_errors }}</p>
                </div>
                <x-filament::badge :color="$config->is_active ? 'success' : 'danger'">
                    {{ $config->is_active ? 'Activo' : 'Inactivo' }}
                </x-filament::badge>
            </div>
        </div>
    </x-filament::card>
    @endif

    {{-- Formulario --}}
    <x-filament::card class="mb-6">
        {{ $this->form }}

        <div class="flex gap-3 mt-6">
            <x-filament::button wire:click="save" color="success">
                Guardar configuración
            </x-filament::button>

            <x-filament::button wire:click="testConnection" color="info">
                Test conexión
            </x-filament::button>

            <x-filament::button wire:click="processNow" color="warning">
                Ejecutar ahora
            </x-filament::button>
        </div>
    </x-filament::card>

    {{-- Instrucciones --}}
    <x-filament::card>
        <h3 class="text-sm font-semibold text-white mb-3">Configuración en Azure</h3>
        <div class="text-xs text-gray-400 space-y-2">
            <p>1. Ir a <a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" class="text-amber-400 hover:underline">Azure AD → App Registrations</a></p>
            <p>2. Crear una nueva aplicación (o usar existente)</p>
            <p>3. En <strong>API Permissions</strong> agregar: <code class="bg-gray-800 px-1 rounded">Mail.Read</code> (Application permission)</p>
            <p>4. Hacer clic en "Grant admin consent"</p>
            <p>5. En <strong>Certificates & secrets</strong> crear un Client Secret</p>
            <p>6. Copiar: Tenant ID, Client ID y Client Secret acá</p>
            <p>7. Guardar y probar con "Test conexión"</p>
        </div>
    </x-filament::card>

</x-filament-panels::page>
