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

    {{-- Sección: Backup de Base de Datos --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Backup de Base de Datos</x-slot>
        <x-slot name="description">Exporta o importa un backup completo de la base de datos (formato SQL).</x-slot>

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
                    Sube un archivo .sql para restaurar la base de datos. Esto sobreescribirá los datos actuales.
                </p>

                {{ $this->backupForm }}

                <x-filament::button wire:click="importBackup" color="danger" icon="heroicon-o-arrow-up-tray">
                    Importar backup
                </x-filament::button>
            </div>

        </div>
    </x-filament::section>

</x-filament-panels::page>