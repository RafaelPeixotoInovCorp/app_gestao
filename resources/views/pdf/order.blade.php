<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Encomenda {{ $record->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 12px; color: #0f172a; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.meta td { padding: 6px 8px; vertical-align: top; }
        table.meta td.label { width: 26%; color: #64748b; font-weight: 600; }
        .block { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; margin-bottom: 14px; }
        .muted { color: #64748b; font-size: 10px; margin-top: 24px; }
    </style>
</head>
<body>
    <h1>{{ $isSupplierOrder ? 'Encomenda a fornecedor' : 'Encomenda de cliente' }}</h1>

    <table class="meta">
        <tr>
            <td class="label">Número</td>
            <td>{{ $record->order_number }}</td>
        </tr>
        <tr>
            <td class="label">Data</td>
            <td>{{ $record->order_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Validade</td>
            <td>{{ $record->order_valid_until ? $record->order_valid_until->format('d/m/Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ $isSupplierOrder ? 'Fornecedor' : 'Cliente' }}</td>
            <td>{{ $record->entity?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Estado</td>
            <td>{{ $record->order_status?->label() ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Valor</td>
            <td>
                @if($record->order_amount !== null)
                    {{ number_format((float) $record->order_amount, 2, ',', ' ') }} €
                @else
                    —
                @endif
            </td>
        </tr>
    </table>

    <div class="block">
        <strong>{{ $record->title }}</strong>
        @if($record->description)
            <div style="margin-top:8px; white-space: pre-wrap;">{{ $record->description }}</div>
        @endif
    </div>

    @if($record->entity)
        <div class="block">
            <strong>{{ $isSupplierOrder ? 'Morada do fornecedor' : 'Morada do cliente' }}</strong>
            <div style="margin-top:6px;">
                {{ $record->entity->decryptedAddress() ?? '—' }}<br>
                {{ trim(implode(' ', array_filter([$record->entity->postal_code ?? '', $record->entity->city ?? '']))) ?: '' }}
            </div>
        </div>
    @endif

    <p class="muted">Documento gerado em {{ now()->format('d/m/Y H:i') }}.</p>
</body>
</html>
