@php
    $dense = count($report['columns']) >= 12;
    $orientation = count($report['columns']) > 7 ? 'landscape' : 'portrait';
    $highlights = array_values(array_filter($report['columns'], fn ($column) => $column['sum'] && $column['type'] === 'money'));
    $rowCount = 0;
    $displayValue = function ($row, $column) use ($format) {
        $value = $row[$column['key']] ?? null;
        if ($value === null) return '—';
        return match ($column['key']) {
            'event' => $value === 'reversal' ? 'Pembalikan' : 'Transaksi awal',
            'sale_type' => $value === 'factory' ? 'Pabrik' : ($value === 'sort' ? 'Sortir' : $value),
            'method' => $value === 'cash' ? 'Tunai' : 'Transfer bank',
            'status' => ['posted' => 'Sah', 'reversed' => 'Dibalik', 'voided' => 'Dibatalkan', 'unpaid' => 'Belum lunas', 'partial' => 'Sebagian', 'paid' => 'Lunas'][$value] ?? $value,
            default => $format($value, $column['type']),
        };
    };
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $report['title'] }} - {{ $businessName }}</title>
    <style>
        @page {
            size: A4 {{ $orientation }}; margin: 12mm 12mm 16mm;
            @bottom-left { content: "Dokumen laporan usaha"; font: 8px 'Segoe UI', Arial, sans-serif; color: #65716a; }
            @bottom-right { content: "Halaman " counter(page) " / " counter(pages); font: 8px 'Segoe UI', Arial, sans-serif; color: #65716a; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1c3027; background: #eef1ea; font: 12px/1.5 'Segoe UI', Arial, sans-serif; }
        .controls { max-width: 1180px; margin: 24px auto 16px; padding: 0 24px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .controls p { margin: 0 0 0 auto; font-size: 11px; color: #65716a; }
        button, a.button { border: 1px solid #164b37; border-radius: 10px; padding: 12px 18px; font: 600 12px 'Segoe UI', Arial, sans-serif; text-decoration: none; cursor: pointer; }
        button { color: #fff; background: #164b37; } a.button { color: #164b37; background: #fff; }
        button:hover { background: #206447; } a.button:hover { background: #e6eee7; }
        button:focus-visible, a:focus-visible { outline: 2px solid #267955; outline-offset: 3px; }
        .document { max-width: 1132px; margin: 0 auto 40px; padding: 36px; background: #fff; border: 1px solid #dce3da; border-radius: 16px; }
        .brand-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding-bottom: 20px; border-bottom: 2px solid #164b37; }
        .brand { display: flex; flex: 1; align-items: center; gap: 12px; min-width: 0; }
        .brand > div { flex: 1; min-width: 0; }
        .brand svg { width: 42px; height: 42px; flex-shrink: 0; }
        .business { margin: 0; font-size: 18px; font-weight: 700; letter-spacing: -.4px; overflow-wrap: anywhere; }
        .brand-note { margin: 3px 0 0; color: #65716a; font-size: 10px; }
        .document-kind { flex: none; max-width: 40%; margin: 0; color: #65716a; font-size: 10px; text-align: right; }
        .document-kind strong { display: block; margin-top: 4px; color: #164b37; font-size: 11px; }
        .document-title { margin: 22px 0 6px; font-size: 26px; font-weight: 700; line-height: 1.2; letter-spacing: -.7px; }
        .period { margin: 0; color: #164b37; font-weight: 600; font-size: 12px; }
        .filter-labels { margin: 8px 0 0; font-size: 10px; color: #65716a; overflow-wrap: anywhere; }
        .muted { color: #65716a; }
        .note { margin: 14px 0 20px; padding-left: 12px; border-left: 2px solid #c4cfc4; font-size: 10px; line-height: 1.6; }
        .totals-strip { margin-bottom: 22px; }
        .totals-strip table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
        .totals-strip td { padding: 11px 14px; border: 1px solid #e0e7de; background: #f5f7f2; vertical-align: top; }
        .total-label { display: block; color: #65716a; font-size: 9px; }
        .total-value { display: block; margin-top: 4px; color: #164b37; font-size: 16px; font-weight: 700; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .section-heading { margin: 24px 0 10px; font-size: 14px; font-weight: 700; break-after: avoid; }
        .section-note { margin: -4px 0 12px; font-size: 10px; color: #65716a; break-after: avoid; }
        .scroll { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10px; }
        .data-table th { padding: 9px 7px; color: #fff; background: #164b37; font-size: 9px; font-weight: 600; text-align: left; vertical-align: bottom; }
        .data-table td { padding: 9px 7px; border-bottom: 1px solid #dce3da; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        .data-table tbody tr:nth-child(even) { background: #f6f8f4; }
        .data-table .number { text-align: right; font-variant-numeric: tabular-nums; }
        .data-table .total-row td { border-top: 2px solid #164b37; background: #e6eee7; color: #164b37; font-weight: 700; }
        .dense .data-table { font-size: 8.5px; }
        .dense .data-table th { font-size: 8px; }
        .dense .data-table th, .dense .data-table td { padding: 7px 5px; }
        .document-footer { margin-top: 18px; padding-top: 12px; border-top: 1px solid #dce3da; font-size: 9px; line-height: 1.6; color: #65716a; break-inside: avoid; break-before: avoid; }
        .empty { padding: 22px !important; color: #65716a; text-align: center !important; }
        thead { display: table-header-group; } tfoot { display: table-row-group; }
        tr { break-inside: avoid; } .data-table .total-row { break-before: avoid; } h1, h2 { break-after: avoid; } a { color: inherit; }
        @media screen and (max-width: 640px) { .controls { padding: 0 16px; } .controls p { width: 100%; margin: 0; } .document { margin: 0 12px 24px; padding: 20px; } .brand-row { gap: 12px; } .business { font-size: 15px; } .document-kind { display: none; } .data-table { min-width: 700px; } .document-title { font-size: 23px; } }
        @media print { html, body { background: #fff; } body { margin: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; } .controls { display: none; } .document { max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; } .scroll { overflow: visible; } a { text-decoration: none; } .brand-row { padding-bottom: 14px; } .document-title { margin-top: 16px; font-size: 24px; } }
    </style>
</head>
<body class="{{ $dense ? 'dense' : '' }}">
    <div class="controls"><button onclick="window.print()">Cetak / simpan PDF</button><a class="button" href="{{ route('reports.index', $filters) }}">Kembali ke laporan</a><p>Pilih ukuran A4 untuk menyimpan atau mencetak dokumen.</p></div>
    <main class="document">
        <div class="brand-row"><div class="brand"><svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect width="48" height="48" rx="12" fill="#164b37"/><path d="M24 37V19m0 9c-10 0-14-7-14-15 10 0 14 6 14 15Zm0-5c0-9 6-14 15-14 0 9-6 14-15 14Z" stroke="#f4f5ed" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg><div><p class="business">{{ $businessName }}</p><p class="brand-note">Pengelolaan usaha sawit</p></div></div><p class="document-kind">Dokumen laporan<strong>{{ $report['title'] }}</strong></p></div>
        <h1 class="document-title">{{ $report['title'] }}</h1>
        <p class="period">Periode {{ date('d/m/Y', strtotime($filters['from'])) }} sampai {{ date('d/m/Y', strtotime($filters['to'])) }}</p>
        @if ($selectedLabels || !empty($filters['status']))<p class="filter-labels">{{ implode(' · ', $selectedLabels) }}@if (!empty($filters['status'])) · Status: {{ ['unpaid'=>'Belum lunas','partial'=>'Sebagian','paid'=>'Lunas','posted'=>'Sah','reversed'=>'Dibalik','voided'=>'Dibatalkan'][$filters['status']] ?? $filters['status'] }}@endif</p>@endif
        <p class="note muted">{{ $report['note'] }}</p>
        @if ($highlights)
            <div class="totals-strip" role="group" aria-label="Total laporan">@foreach (array_chunk($highlights, 3) as $group)<table><tbody><tr>@foreach ($group as $column)<td><span class="total-label">{{ $column['label'] }}</span><strong class="total-value">{{ $format($report['totals'][$column['key']], $column['type']) }}</strong></td>@endforeach</tr></tbody></table>@endforeach</div>
        @endif
        <h2 class="section-heading">{{ $filters['view'] === 'summary' ? 'Rincian per hari' : 'Rincian transaksi' }}</h2>
        <div class="scroll"><table class="data-table" aria-label="Rincian laporan"><thead><tr>@foreach ($report['columns'] as $column)<th scope="col" class="{{ $column['type'] !== 'text' ? 'number' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>
            @forelse ($report['rows'] as $row)
                @php($rowCount++)
                <tr>@foreach ($report['columns'] as $column)<td class="{{ $column['type'] !== 'text' ? 'number' : '' }}">{{ $displayValue($row, $column) }}</td>@endforeach</tr>
            @empty
                <tr><td class="empty" colspan="{{ count($report['columns']) }}">Belum ada transaksi untuk filter ini.</td></tr>
            @endforelse
        @if ($report['totals'])<tr class="total-row">@foreach ($report['columns'] as $column)<td class="{{ $column['sum'] ? 'number' : '' }}">{{ $loop->first ? 'TOTAL' : ($column['sum'] ? $format($report['totals'][$column['key']], $column['type']) : '') }}</td>@endforeach</tr>@endif</tbody></table></div>
        @if (isset($report['summary']['balances']))
            <h2 class="section-heading">Saldo saat ini</h2><p class="section-note">Mengikuti seluruh tanggal transaksi. Kas & bank mencakup seluruh usaha.</p>
            <table class="data-table"><thead><tr>@foreach (['Utang konsumen','Utang upah','Piutang penjualan','Kas & bank'] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody><tr>@foreach (['customer_debt_idr','payroll_debt_idr','sale_receivable_idr','cash_idr'] as $key)<td class="number">{{ $format($report['summary']['balances'][$key], 'money') }}</td>@endforeach</tr></tbody></table>
        @endif
        @php($flow = $report['summary']['cashflow'] ?? ($report['cashflow'] ?? null))
        @if ($flow)
            <h2 class="section-heading">Arus kas periode per akun</h2><p class="section-note">Saldo awal, penerimaan, pengeluaran, dan saldo akhir dalam periode laporan.</p>
            <table class="data-table"><thead><tr>@foreach (['Akun','Saldo awal','Masuk','Keluar','Saldo akhir'] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse ($flow['accounts'] as $account)<tr><td>{{ $account['name'] }}</td>@foreach (['opening_idr','incoming_idr','outgoing_idr','closing_idr'] as $key)<td class="number">{{ $format($account[$key], 'money') }}</td>@endforeach</tr>@empty<tr><td class="empty" colspan="5">Belum ada akun pada filter ini.</td></tr>@endforelse<tr class="total-row"><td>Total akun yang ditampilkan</td>@foreach (['opening_idr','incoming_idr','outgoing_idr','closing_idr'] as $key)<td class="number">{{ $format($flow['totals'][$key], 'money') }}</td>@endforeach</tr></tbody></table>
        @endif
        @if (isset($report['summary']['vehicles']))
            <h2 class="section-heading">Rincian per mobil</h2>
            <table class="data-table"><thead><tr>@foreach (['Mobil','Timbang neto','TBS beli','Sortir beli','TBS jual','Sortir jual','Pembelian','Pendapatan','Hak upah'] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse ($report['summary']['vehicles'] as $vehicle)<tr><td>{{ $vehicle['plate_number'] }}</td><td class="number">{{ $vehicle['weighing_count'] }}</td>@foreach (['tbs_bought_grams','sort_bought_grams','factory_sold_grams','sort_sold_grams'] as $key)<td class="number">{{ $format($vehicle[$key], 'weight') }}</td>@endforeach @foreach (['purchase_idr','revenue_idr','loading_idr'] as $key)<td class="number">{{ $format($vehicle[$key], 'money') }}</td>@endforeach</tr>@empty<tr><td class="empty" colspan="9">Belum ada transaksi mobil pada periode ini.</td></tr>@endforelse</tbody></table>
        @endif
        <div class="document-footer"><strong>{{ $businessName }}</strong> · {{ $rowCount }} baris rincian · Seluruh hasil filter, termasuk semua halaman.<br />Disiapkan {{ str_replace('T', ' ', $preparedAt) }} WIB. Angka mengikuti transaksi dan pembalikan yang tercatat saat laporan dibuka.</div>
    </main>
</body>
</html>
