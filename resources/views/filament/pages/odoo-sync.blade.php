<x-filament-panels::page>

    @php
        $providers = $this->getOdooProviders();
        $invoices = $this->getInvoices();
        $years = $this->getYears();
        $monthNames = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
    @endphp

    {{-- Aviso de configuración pendiente --}}
    <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
        <p class="text-sm text-amber-300 flex items-center gap-2">
            <x-heroicon-o-information-circle class="w-5 h-5" />
            La conexión con Odoo todavía no está configurada. Por ahora podés marcar qué proveedores se cargan en Odoo (en Facturación → Proveedores, toggle "Cargar en Odoo") y ver acá sus facturas.
        </p>
    </div>

    {{-- Proveedores marcados para Odoo --}}
    <div class="mb-6 rounded-xl border border-gray-700 bg-gray-800/30 p-5">
        <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
            <x-heroicon-o-building-storefront class="w-4 h-4 text-amber-400" />
            Proveedores que se cargan en Odoo
        </h3>
        @if($providers->isEmpty())
            <p class="text-xs text-gray-400">
                Ningún proveedor marcado todavía. Andá a Facturación → Proveedores y activá el toggle "Cargar en Odoo" en los que correspondan.
            </p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach($providers as $p)
                    <span class="text-xs rounded-lg border border-gray-600 bg-gray-900 text-gray-200 px-3 py-1.5">
                        {{ $p->name }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Facturas de esos proveedores --}}
    @php
        $counts = $this->getCounts();
        $pendientes = $counts['pending'];
    @endphp
    <div class="rounded-xl border border-gray-700 bg-gray-800/30 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-700 flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-sm font-semibold text-white mr-1">Facturas</h3>
                @foreach(['pending' => 'Pendientes', 'loaded' => 'Cargadas', 'dismissed' => 'Descartadas', 'all' => 'Todas'] as $key => $label)
                    <button wire:click="$set('statusFilter', '{{ $key }}')"
                        class="text-xs rounded-lg border px-2.5 py-1 transition {{ $statusFilter === $key ? 'border-amber-400 bg-amber-500/10 text-amber-300' : 'border-gray-600 bg-gray-900 text-gray-400 hover:border-amber-400' }}">
                        {{ $label }} ({{ $counts[$key] }})
                    </button>
                @endforeach
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-400">Año</label>
                    <select wire:model.live="year" class="rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-1 text-sm focus:border-amber-400 focus:ring-0">
                        @foreach($years as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                @if($pendientes > 0)
                    <x-filament::button wire:click="pushAll" color="success" icon="heroicon-o-arrow-up-tray" size="sm"
                        wire:confirm="¿Cargar {{ $pendientes }} factura(s) pendientes en Odoo (borrador)?"
                        wire:loading.attr="disabled" wire:target="pushAll">
                        <span wire:loading.remove wire:target="pushAll">Cargar todas ({{ $pendientes }})</span>
                        <span wire:loading wire:target="pushAll">Cargando...</span>
                    </x-filament::button>
                @endif
            </div>
        </div>

        @if($invoices->isEmpty())
            <p class="text-sm text-gray-500 text-center py-8">
                @if($statusFilter === 'pending')
                    No hay facturas pendientes de cargar en Odoo en {{ $year }}. 🎉
                @else
                    No hay facturas en este filtro para {{ $year }}.
                @endif
            </p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-400 border-b border-gray-700">
                        <th class="px-5 py-2">Proveedor</th>
                        <th class="px-5 py-2">Fecha</th>
                        <th class="px-5 py-2">Referencia (Odoo)</th>
                        <th class="px-5 py-2 text-right">Monto</th>
                        <th class="px-5 py-2">Nº Factura</th>
                        <th class="px-5 py-2">Estado Odoo</th>
                        <th class="px-5 py-2 text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $inv)
                        @php
                            $cargada = (bool) $inv->odoo_move_id;
                            $descartada = ! $cargada && $inv->odoo_dismissed;
                            $rowBg = $cargada ? 'bg-green-500/5' : ($descartada ? 'opacity-50' : '');
                        @endphp
                        <tr class="border-b border-gray-800 hover:bg-gray-800/50 {{ $rowBg }}">
                            <td class="px-5 py-2.5 text-gray-100 font-medium">{{ ucfirst($inv->provider) }}</td>
                            <td class="px-5 py-2.5 text-gray-300">{{ $inv->invoice_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-amber-300">{{ $this->refFor($inv) ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-right text-white">{{ number_format($inv->amount, 2, ',', '.') }} {{ $inv->currency }}</td>
                            <td class="px-5 py-2.5 text-gray-400">{{ $inv->invoice_number ?? '—' }}</td>
                            <td class="px-5 py-2.5">
                                @if($cargada)
                                    <a href="{{ $this->odooUrl($inv->odoo_move_id) }}" target="_blank"
                                       class="inline-flex items-center gap-1 text-xs text-green-400 hover:text-green-300">
                                        <x-heroicon-s-check-circle class="w-4 h-4" />
                                        Borrador #{{ $inv->odoo_move_id }}
                                    </a>
                                @elseif($descartada)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <x-heroicon-o-no-symbol class="w-4 h-4" /> Descartada
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-amber-400">
                                        <x-heroicon-o-clock class="w-4 h-4" /> Pendiente
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                <div class="flex items-center justify-center gap-2">
                                    @if($cargada)
                                        <x-filament::button wire:click="unlink({{ $inv->id }})" color="gray" size="xs"
                                            wire:confirm="¿Deshacer la vinculación con Odoo? La factura vuelve a pendiente (el borrador en Odoo no se borra).">
                                            Deshacer
                                        </x-filament::button>
                                    @elseif($descartada)
                                        <x-filament::button wire:click="undismiss({{ $inv->id }})" color="warning" size="xs">
                                            Reactivar
                                        </x-filament::button>
                                    @else
                                        <x-filament::button wire:click="pushOne({{ $inv->id }})" color="info" size="xs"
                                            wire:loading.attr="disabled" wire:target="pushOne({{ $inv->id }})">
                                            Cargar
                                        </x-filament::button>
                                        <x-filament::button wire:click="dismiss({{ $inv->id }})" color="danger" size="xs"
                                            wire:confirm="¿Descartar esta factura? No se cargará en Odoo.">
                                            Descartar
                                        </x-filament::button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-800/50">
                    <tr class="border-t border-gray-600">
                        <td class="px-5 py-3 font-semibold text-white" colspan="3">Total ({{ $invoices->count() }} facturas)</td>
                        <td class="px-5 py-3 text-right font-bold text-amber-400">{{ number_format($invoices->sum('amount'), 2, ',', '.') }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

</x-filament-panels::page>
