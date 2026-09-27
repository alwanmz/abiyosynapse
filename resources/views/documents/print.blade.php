@php
    $language = $snapshot['language'];
    $labels = $snapshot['labels'];
    $header = $snapshot['header'];
    $approval = $snapshot['approval'];
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ $snapshot['title'] }}</title>
    <style>
        @page { margin: 34px 38px 42px; }
        body { color: #17202a; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; }
        h1 { color: #0f3d5e; font-size: 17px; margin: 0; }
        h2 { color: #0f3d5e; font-size: 12px; border-bottom: 1px solid #14b8c4; padding-bottom: 4px; margin: 15px 0 7px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #dceff2; color: #0f3d5e; text-align: left; }
        th, td { border-bottom: 1px solid #dbe3e8; padding: 4px 5px; }
        .meta { margin: 12px 0; }
        .meta td { padding: 2px 12px 2px 0; border: 0; }
        .number { text-align: right; white-space: nowrap; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; color: #64748b; font-size: 8px; text-align: center; }
        .stamp { display: inline-block; border: 1px solid #157f4f; color: #157f4f; padding: 8px 14px; transform: rotate(-5deg); font-weight: bold; }
        .signatures { margin-top: 28px; width: 100%; }
        .signatures td { height: 48px; width: 33%; vertical-align: bottom; border: 0; text-align: center; }
        .muted { color: #64748b; }
        .brand { display: table; width: 100%; }
        .brand-mark { display: table-cell; width: 34px; vertical-align: top; }
        .brand-mark img { width: 26px; height: 26px; }
        .brand-copy { display: table-cell; vertical-align: top; }
    </style>
</head>
<body>
    <div class="footer">Nexumi ERP · {{ $snapshot['title'] }} · {{ $labels['print'] }} #{{ $run->id }}</div>
    <div class="brand">
        <div class="brand-mark"><img src="{{ public_path('apple-touch-icon.png') }}" alt="Nexumi ERP"></div>
        <div class="brand-copy">
            <h1>{{ $snapshot['company']->name }}</h1>
            <div class="muted">{{ $snapshot['company']->legal_name ?: $snapshot['company']->address }}</div>
        </div>
    </div>
    <h2>{{ $snapshot['title'] }}</h2>
    <table class="meta">
        <tr><td>{{ $labels['number'] }}</td><td><strong>{{ $header['number'] }}</strong></td><td>{{ $labels['status'] }}</td><td>{{ $header['status'] }}</td></tr>
        <tr><td>{{ $labels['date'] }}</td><td>{{ $header['date'] ?: '—' }}</td><td>{{ $labels['party'] }}</td><td>{{ $header['party'] ?: '—' }}</td></tr>
        <tr><td>{{ $labels['currency'] }}</td><td>{{ $header['currency'] }}</td><td>{{ $labels['base_currency'] }}</td><td>{{ $header['base_currency'] }}</td></tr>
        @if ($header['exchange_rate'])<tr><td>{{ $labels['exchange_rate'] }}</td><td>{{ $header['exchange_rate'] }}</td><td>{{ $labels['base_total'] }}</td><td>{{ $header['total_base'] ?: '—' }}</td></tr>@endif
        @if ($header['reference'])<tr><td>{{ $labels['reference'] }}</td><td colspan="3">{{ $header['reference'] }}</td></tr>@endif
    </table>

    @foreach ($snapshot['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        <table>
            <thead><tr>@foreach ($section['columns'] as $column)<th class="{{ in_array($column['key'], ['sequence', 'quantity', 'required', 'issued', 'quantity_per_batch', 'scrap', 'unit_price', 'tax', 'total', 'planned_minutes', 'actual_minutes', 'output', 'cost', 'accumulated_depreciation', 'book_value', 'priority', 'downtime_minutes', 'cost_base'], true) ? 'number' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($section['rows'] as $row)
                <tr>@foreach ($section['columns'] as $column)<td class="{{ in_array($column['key'], ['sequence', 'quantity', 'required', 'issued', 'quantity_per_batch', 'scrap', 'unit_price', 'tax', 'total', 'planned_minutes', 'actual_minutes', 'output', 'cost', 'accumulated_depreciation', 'book_value', 'priority', 'downtime_minutes', 'cost_base'], true) ? 'number' : '' }}">{{ $row[$column['key']] ?? '—' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($section['columns']) }}" class="muted">—</td></tr>
            @endforelse
            </tbody>
        </table>
    @endforeach

    @if ($snapshot['totals'])
        <table class="meta totals">
            @foreach ($snapshot['totals'] as $total)<tr><td>{{ $total['label'] }}</td><td class="number"><strong>{{ $total['value'] }}</strong></td></tr>@endforeach
        </table>
    @endif

    @if ($approval['is_approved'])
        <div class="stamp">{{ $labels['approved'] }}<br><span class="muted">{{ $approval['approved_at'] }}</span></div>
    @endif
    <table class="signatures"><tr><td>{{ $labels['prepared_by'] }}<br><br>{{ $approval['prepared_by'] ?: '—' }}</td><td>{{ $labels['approval'] }}<br><br>{{ $approval['is_approved'] ? $approval['approved_by'] : $labels['not_approved'] }}</td><td>{{ $labels['approved_at'] }}<br><br>{{ $approval['approved_at'] ?: '—' }}</td></tr></table>
</body>
</html>
