<x-filament-panels::page>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@php
    $comparison  = $this->getYearComparison();
    $monthlyData = $this->getMonthlyData();
    $byProvider  = $this->getByProvider();
    $yearTotal   = $this->getYearTotal();
    $byService = $this->getByService();

    $monthNames  = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $monthLabels = array_values(array_map(fn($k) => $monthNames[(int)explode('-',$k)[1]-1], array_keys($monthlyData)));
    $monthValues = array_values($monthlyData);
@endphp

{{-- Filtros --}}
<div style="display:flex; gap:16px; margin-bottom:24px; align-items:flex-end;">
    <div>
        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Año</label>
        <select wire:model.live="selectedYear" style="display:block; margin-top:4px; background:#1f2937; color:#fff; border:1px solid #374151; border-radius:8px; padding:6px 12px; font-size:13px;">
            @foreach ($this->getAvailableYears() as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Moneda</label>
        <select wire:model.live="selectedCurrency" style="display:block; margin-top:4px; background:#1f2937; color:#fff; border:1px solid #374151; border-radius:8px; padding:6px 12px; font-size:13px;">
            <option value="ARS">ARS (Pesos)</option>
            <option value="USD">USD (Dólares)</option>
        </select>
    </div>
</div>

{{-- KPIs --}}
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
    <x-filament::card>
        <p style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Total {{ $selectedYear }}</p>
        <p style="font-size:22px; font-weight:700; color:#fff; margin-top:4px;">
            {{ $selectedCurrency }} {{ number_format($yearTotal, 2, ',', '.') }}
        </p>
    </x-filament::card>
    <x-filament::card>
        <p style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Total {{ (int)$selectedYear - 1 }}</p>
        <p style="font-size:22px; font-weight:700; color:#fff; margin-top:4px;">
            {{ $selectedCurrency }} {{ number_format($comparison['previous'], 2, ',', '.') }}
        </p>
    </x-filament::card>
    <x-filament::card>
        <p style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Variación interanual</p>
        <p style="font-size:22px; font-weight:700; margin-top:4px; color:{{ $comparison['diff_percent'] > 0 ? '#f87171' : '#34d399' }}">
            {{ $comparison['diff_percent'] > 0 ? '+' : '' }}{{ $comparison['diff_percent'] }}%
        </p>
    </x-filament::card>
</div>

{{-- Gráfico de barras mensual --}}
<x-filament::card style="margin-bottom:24px;">
    <h3 style="font-size:13px; font-weight:600; color:#fff; margin-bottom:16px;">Evolución mensual ({{ $selectedCurrency }})</h3>
    <canvas id="monthlyChart" height="80"></canvas>
</x-filament::card>

{{-- Gráficos dona --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
    <x-filament::card>
        <h3 style="font-size:13px; font-weight:600; color:#fff; margin-bottom:16px;">Distribución por servicio</h3>
        <canvas id="serviceChart" height="200"></canvas>
    </x-filament::card>
    <x-filament::card>
        <h3 style="font-size:13px; font-weight:600; color:#fff; margin-bottom:16px;">Distribución por proveedor</h3>
        <canvas id="providerChart" height="200"></canvas>
    </x-filament::card>
</div>

{{-- Tablas lado a lado --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">

    {{-- Tabla mensual --}}
    <x-filament::card>
        <h3 style="font-size:13px; font-weight:600; color:#fff; margin-bottom:16px;">Detalle por mes</h3>
        <table style="width:100%; font-size:12px; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid #374151;">
                    <th style="text-align:left; padding:6px 8px; color:#6b7280;">Mes</th>
                    <th style="text-align:right; padding:6px 8px; color:#6b7280;">Monto</th>
                    <th style="text-align:right; padding:6px 8px; color:#6b7280;">%</th>
                </tr>
            </thead>
            <tbody>
                @php $fullMonthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']; @endphp
                @foreach ($monthlyData as $period => $total)
                    @php
                        $mi = (int)explode('-', $period)[1] - 1;
                        $pct = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0;
                    @endphp
                    <tr style="border-bottom:1px solid #1f2937; {{ $total > 0 ? '' : 'opacity:0.4' }}">
                        <td style="padding:6px 8px; color:#d1d5db;">{{ $fullMonthNames[$mi] }}</td>
                        <td style="padding:6px 8px; text-align:right; color:{{ $total > 0 ? '#f59e0b' : '#6b7280' }}; font-weight:{{ $total > 0 ? '600' : '400' }};">
                            {{ number_format($total, 2, ',', '.') }}
                        </td>
                        <td style="padding:6px 8px; text-align:right; color:#6b7280;">{{ $pct }}%</td>
                    </tr>
                @endforeach
                <tr style="border-top:2px solid #374151;">
                    <td style="padding:6px 8px; font-weight:700; color:#fff;">Total</td>
                    <td style="padding:6px 8px; text-align:right; font-weight:700; color:#f59e0b;">{{ number_format($yearTotal, 2, ',', '.') }}</td>
                    <td style="padding:6px 8px; text-align:right; color:#6b7280;">100%</td>
                </tr>
            </tbody>
        </table>
    </x-filament::card>

    {{-- Tabla por servicio --}}
    <x-filament::card>
        <h3 style="font-size:13px; font-weight:600; color:#fff; margin-bottom:16px;">Detalle por servicio</h3>
        <table style="width:100%; font-size:12px; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid #374151;">
                    <th style="text-align:left; padding:6px 8px; color:#6b7280;">Servicio</th>
                    <th style="text-align:right; padding:6px 8px; color:#6b7280;">Monto</th>
                    <th style="text-align:right; padding:6px 8px; color:#6b7280;">%</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byService as $service => $total)
                    @php $pct = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0; @endphp
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:6px 8px; color:#d1d5db;">{{ $service }}</td>
                        <td style="padding:6px 8px; text-align:right; color:#f59e0b; font-weight:600;">{{ number_format($total, 2, ',', '.') }}</td>
                        <td style="padding:6px 8px; text-align:right; color:#6b7280;">{{ $pct }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="padding:16px; text-align:center; color:#6b7280;">Sin datos</td></tr>
                @endforelse
                <tr style="border-top:2px solid #374151;">
                    <td style="padding:6px 8px; font-weight:700; color:#fff;">Total</td>
                    <td style="padding:6px 8px; text-align:right; font-weight:700; color:#f59e0b;">{{ number_format($yearTotal, 2, ',', '.') }}</td>
                    <td style="padding:6px 8px; text-align:right; color:#6b7280;">100%</td>
                </tr>
            </tbody>
        </table>
    </x-filament::card>

</div>

{{-- Barras por proveedor --}}
<x-filament::card>
    <h3 style="font-size:13px; font-weight:600; color:#fff; margin-bottom:16px;">Gasto por proveedor</h3>
    <div style="display:flex; flex-direction:column; gap:12px;">
        @forelse ($byProvider as $provider => $total)
            @php $pct = $yearTotal > 0 ? round(($total / $yearTotal) * 100, 1) : 0; @endphp
            <div>
                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                    <span style="color:#d1d5db;">{{ ucfirst($provider) }}</span>
                    <span style="color:#fff; font-weight:600;">
                        {{ $selectedCurrency }} {{ number_format($total, 2, ',', '.') }}
                        <span style="color:#6b7280; font-size:11px;">({{ $pct }}%)</span>
                    </span>
                </div>
                <div style="background:#374151; border-radius:4px; height:8px;">
                    <div style="background:#f59e0b; height:8px; border-radius:4px; width:{{ $pct }}%;"></div>
                </div>
            </div>
        @empty
            <p style="font-size:13px; color:#6b7280; text-align:center;">Sin datos.</p>
        @endforelse
    </div>
</x-filament::card>

{{-- Scripts Chart.js --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.color = '#9ca3af';
    Chart.defaults.borderColor = '#374151';

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: @json($monthLabels),
            datasets: [{ label: '{{ $selectedCurrency }}', data: @json($monthValues), backgroundColor: '#f59e0b', borderRadius: 4 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { grid: { color: '#1f2937' } }, x: { grid: { display: false } } }
        }
    });

    new Chart(document.getElementById('serviceChart'), {
        type: 'doughnut',
        data: {
            labels: @json(array_keys($byService)),
            datasets: [{ data: @json(array_values($byService)), backgroundColor: ['#f59e0b','#3b82f6','#10b981','#8b5cf6','#ef4444'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('providerChart'), {
        type: 'doughnut',
        data: {
            labels: @json(array_keys($byProvider)),
            datasets: [{ data: @json(array_values($byProvider)), backgroundColor: ['#3b82f6','#f59e0b','#10b981','#8b5cf6','#ef4444'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

</x-filament-panels::page>