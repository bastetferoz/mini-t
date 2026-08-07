<x-filament-panels::page>

    {{-- Sección: Importar asignaciones CSV --}}
    <x-filament::section>
        <x-slot name="heading">Importar asignaciones</x-slot>
        <x-slot name="description">Sube un archivo CSV con las asignaciones a importar.</x-slot>

        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button wire:click="import">
                Importar asignaciones
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- Secciones solo admin --}}
    @if(auth()->user()->hasRole('admin'))

    {{-- Sección: Proveedores de facturación --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Proveedores de facturación</x-slot>
        <x-slot name="description">Exporta o importa los proveedores configurados (JSON).</x-slot>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

            {{-- Exportar --}}
            <div class="space-y-3">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Exportar proveedores</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Descarga un archivo JSON con todos los proveedores configurados.
                </p>
                <x-filament::button wire:click="exportProviders" color="success" icon="heroicon-o-arrow-down-tray">
                    Exportar proveedores
                </x-filament::button>
            </div>

            {{-- Importar --}}
            <div class="space-y-3">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Importar proveedores</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Sube un archivo JSON exportado. Los existentes se actualizan, los nuevos se crean.
                </p>

                {{ $this->backupForm }}

                <x-filament::button wire:click="importProviders" color="warning" icon="heroicon-o-arrow-up-tray">
                    Importar proveedores
                </x-filament::button>
            </div>

        </div>
    </x-filament::section>

    {{-- Sección: Backup de Base de Datos (solo admin) --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Backup de Base de Datos</x-slot>
        <x-slot name="description">Exporta o importa un backup completo de la base de datos (formato SQL). ⚠️ La importación sobreescribe todos los datos.</x-slot>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

            {{-- Exportar --}}
            <div class="space-y-3">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Exportar backup</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Descarga un archivo .sql con toda la base de datos actual.
                </p>
                <x-filament::button wire:click="exportBackup" color="success" icon="heroicon-o-arrow-down-tray">
                    Exportar backup
                </x-filament::button>
            </div>

            {{-- Importar --}}
            <div class="space-y-3">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Importar backup</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Sube un archivo .sql para restaurar la base de datos.
                </p>

                {{ $this->backupForm }}

                <x-filament::button wire:click="importBackup" color="danger" icon="heroicon-o-arrow-up-tray">
                    Importar backup
                </x-filament::button>
            </div>

        </div>
    </x-filament::section>
    @endif

</x-filament-panels::page>
