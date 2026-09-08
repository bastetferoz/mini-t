<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #222; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px 0; }
        .sub { color: #666; font-size: 12px; margin-bottom: 14px; }
        .summary { margin: 10px 0 16px 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; margin-right: 6px; color: #fff; }
        .b-green { background: #16a34a; }
        .b-yellow { background: #ca8a04; }
        .b-red { background: #dc2626; }
        .b-total { background: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; background: #f1f5f9; border-bottom: 2px solid #cbd5e1; padding: 6px 8px; font-size: 11px; color: #334155; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        .amount { text-align: right; white-space: nowrap; }
        .row-green { background: #dcfce7; }
        .row-yellow { background: #fef9c3; }
        .estado { font-weight: bold; }
        .e-green { color: #16a34a; }
        .e-yellow { color: #ca8a04; }
        .e-red { color: #dc2626; }
        .footer { margin-top: 18px; color: #94a3b8; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Conciliación de consumos</h1>
    <div class="sub">{{ $person }} &middot; {{ $mesLabel }}</div>

    <div class="summary">
        <span class="badge b-green">{{ $green }} con factura</span>
        <span class="badge b-yellow">{{ $yellow }} reconocido</span>
        <span class="badge b-red">{{ $red }} sin factura</span>
        <span class="badge b-total">{{ $total }} total</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Servicio / Consumo</th>
                <th class="amount">Monto</th>
                <th>Factura encontrada</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $r)
                @php
                    $rowClass = $r['status'] === 'green' ? 'row-green' : ($r['status'] === 'yellow' ? 'row-yellow' : '');
                    $estado = $r['status'] === 'green' ? ['Con factura', 'e-green'] : ($r['status'] === 'yellow' ? ['Reconocido', 'e-yellow'] : ['Sin factura', 'e-red']);
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>{{ $r['description'] }}</td>
                    <td class="amount">{{ number_format($r['amount'], 2, ',', '.') }} {{ $r['currency'] }}</td>
                    <td>{{ $r['invoice_info'] ?? '—' }}</td>
                    <td class="estado {{ $estado[1] }}">{{ $estado[0] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Generado el {{ $fecha }} · Mini-T</div>
</body>
</html>
