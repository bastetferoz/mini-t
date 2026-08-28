<x-filament-panels::page>

    {{-- Formulario de conexión --}}
    <div class="bg-gray-800/50 rounded-lg p-4 border border-gray-700 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm text-gray-300 mb-1">Dirección IP</label>
                <input type="text" wire:model="ip" placeholder="192.168.1.50"
                    class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm focus:border-amber-400 focus:ring-0">
            </div>
            <div>
                <label class="block text-sm text-gray-300 mb-1">Community SNMP</label>
                <input type="text" wire:model="community" placeholder="public"
                    class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm focus:border-amber-400 focus:ring-0">
            </div>
            <div class="flex items-end gap-2">
                <x-filament::button wire:click="detect" color="success" icon="heroicon-o-check-badge" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="detect">Detectar</span>
                    <span wire:loading wire:target="detect">Leyendo...</span>
                </x-filament::button>
                <x-filament::button wire:click="diagnose" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="diagnose">Diagnosticar</span>
                    <span wire:loading wire:target="diagnose">Consultando...</span>
                </x-filament::button>
                <x-filament::button wire:click="runWalk" color="gray" icon="heroicon-o-list-bullet" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="runWalk">Walk</span>
                    <span wire:loading wire:target="runWalk">Recorriendo...</span>
                </x-filament::button>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            "Detectar" resuelve directamente marca, modelo, serie y contador (lo que guardaría la impresora). "Diagnosticar" prueba una batería de OIDs de contador. "Walk" recorre todo el subárbol de impresora (1.3.6.1.2.1.43).
        </p>
    </div>

    @if($detected)
        {{-- Datos resueltos (resultado final de la detección) --}}
        <div class="bg-gray-800/50 rounded-lg p-4 border border-green-700/50 mb-6">
            <h3 class="text-sm font-semibold text-green-400 mb-3">Datos detectados</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="flex items-center justify-between bg-gray-900/60 rounded px-3 py-2">
                    <span class="text-gray-400 text-sm">Marca</span>
                    <span class="text-gray-100 font-medium">{{ $detected['brand'] ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between bg-gray-900/60 rounded px-3 py-2">
                    <span class="text-gray-400 text-sm">Modelo</span>
                    <span class="text-gray-100 font-medium">{{ $detected['model'] ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between bg-gray-900/60 rounded px-3 py-2">
                    <span class="text-gray-400 text-sm">Nº Serie</span>
                    <span class="text-gray-100 font-mono">{{ $detected['serial'] ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between bg-gray-900/60 rounded px-3 py-2">
                    <span class="text-gray-400 text-sm">Contador de páginas</span>
                    <span class="{{ !empty($detected['page_count']) ? 'text-green-400 font-bold text-lg' : 'text-gray-500' }}">
                        {{ $detected['page_count'] ?? '—' }}
                    </span>
                </div>
            </div>

            {{-- Guardar en Impresoras --}}
            <div class="mt-4 pt-4 border-t border-gray-700">
                <h4 class="text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wide">Agregar a Impresoras</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nombre</label>
                        <input type="text" wire:model="printerName" placeholder="Ej: Pantum Recepción"
                            class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm focus:border-amber-400 focus:ring-0">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Ubicación (opcional)</label>
                        <input type="text" wire:model="printerLocation" placeholder="Oficina / piso"
                            class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm focus:border-amber-400 focus:ring-0">
                    </div>
                    <div class="flex items-end">
                        <x-filament::button wire:click="savePrinter" color="success" icon="heroicon-o-plus-circle" class="w-full justify-center">
                            Guardar impresora
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($diagnosis)
        {{-- Datos del equipo --}}
        <div class="bg-gray-800/50 rounded-lg p-4 border border-gray-700 mb-6">
            <h3 class="text-sm font-semibold text-amber-400 mb-3">Información del equipo</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <div><span class="text-gray-400">Estado ping:</span>
                    <span class="{{ $diagnosis['online'] ? 'text-green-400' : 'text-red-400' }}">
                        {{ $diagnosis['online'] ? 'En línea' : 'Sin respuesta' }}
                    </span>
                </div>
                <div><span class="text-gray-400">SNMP disponible:</span>
                    <span class="text-gray-200">{{ $diagnosis['snmp_ext'] ? 'Extensión PHP' : ($diagnosis['snmp_bin'] ? 'Binario snmpget' : 'NO — falta instalar') }}</span>
                </div>
                <div class="md:col-span-2"><span class="text-gray-400">Descripción (sysDescr):</span>
                    <span class="text-gray-200">{{ $diagnosis['sys_descr'] ?? '—' }}</span>
                </div>
                <div><span class="text-gray-400">Nombre (sysName):</span>
                    <span class="text-gray-200">{{ $diagnosis['sys_name'] ?? '—' }}</span>
                </div>
                <div><span class="text-gray-400">Nº Serie:</span>
                    <span class="text-gray-200">{{ $diagnosis['serial'] ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Tabla de OIDs candidatos --}}
        <div class="bg-gray-800/50 rounded-lg border border-gray-700 mb-6 overflow-hidden">
            <h3 class="text-sm font-semibold text-amber-400 px-4 pt-4 pb-2">
                Contadores de página — probá cuál coincide con el valor real de la impresora
            </h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-400 border-b border-gray-700">
                        <th class="px-4 py-2">Marca / Descripción</th>
                        <th class="px-4 py-2">OID</th>
                        <th class="px-4 py-2 text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($diagnosis['candidates'] as $c)
                        <tr class="border-b border-gray-800 {{ $c['numeric'] ? 'bg-green-900/20' : '' }}">
                            <td class="px-4 py-2 text-gray-200">{{ $c['label'] }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-gray-400">{{ $c['oid'] }}</td>
                            <td class="px-4 py-2 text-right font-mono {{ $c['numeric'] ? 'text-green-400 font-bold' : 'text-gray-500' }}">
                                {{ $c['value'] ?? '—' }}
                                @if($c['numeric'])
                                    <span class="ml-1" title="Devolvió un número — posible contador">✓</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-xs text-gray-500 px-4 py-3">
                Las filas en verde devolvieron un número. Compará ese valor con el contador físico de la impresora (menú del equipo o página de configuración impresa) para confirmar cuál es el correcto. Ese OID es el que hay que usar para ese modelo.
            </p>
        </div>
    @endif

    {{-- Consulta de OID manual --}}
    @if($ran)
        <div class="bg-gray-800/50 rounded-lg p-4 border border-gray-700 mb-6">
            <h3 class="text-sm font-semibold text-amber-400 mb-3">Probar un OID específico</h3>
            <div class="flex gap-2 items-end">
                <div class="flex-1">
                    <label class="block text-sm text-gray-300 mb-1">OID</label>
                    <input type="text" wire:model="customOid" placeholder="1.3.6.1.2.1.43.10.2.1.4.1.1"
                        class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm font-mono focus:border-amber-400 focus:ring-0">
                </div>
                <x-filament::button wire:click="queryCustom" color="gray" icon="heroicon-o-play">
                    Consultar
                </x-filament::button>
            </div>
            @if($customResult !== null)
                <div class="mt-3 text-sm">
                    <span class="text-gray-400">Resultado:</span>
                    <span class="text-green-400 font-mono font-bold">{{ $customResult }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Resultados del walk --}}
    @if($walkResults !== null)
        <div class="bg-gray-800/50 rounded-lg border border-gray-700 overflow-hidden">
            <h3 class="text-sm font-semibold text-amber-400 px-4 pt-4 pb-2">
                SNMP Walk — {{ count($walkResults) }} OID(s) en el subárbol de impresora
            </h3>
            @if(count($walkResults) === 0)
                <p class="text-sm text-gray-500 px-4 pb-4">No se obtuvieron OIDs. Verificá la community o que SNMP esté habilitado en la impresora.</p>
            @else
                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-gray-800">
                            <tr class="text-left text-gray-400 border-b border-gray-700">
                                <th class="px-4 py-2">OID</th>
                                <th class="px-4 py-2">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($walkResults as $oid => $value)
                                <tr class="border-b border-gray-800 {{ is_numeric($value) ? 'bg-green-900/10' : '' }}">
                                    <td class="px-4 py-1.5 font-mono text-gray-400">{{ $oid }}</td>
                                    <td class="px-4 py-1.5 font-mono {{ is_numeric($value) ? 'text-green-400' : 'text-gray-200' }}">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

</x-filament-panels::page>
