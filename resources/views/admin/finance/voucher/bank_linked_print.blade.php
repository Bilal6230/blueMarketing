<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $direction === 'receipt' ? 'Bank Receipt' : 'Bank Payment' }} {{ $source->voucher_series }}-{{ $source->voucher_number }}</title>
    <style>
        *{box-sizing:border-box}body{font:14px Arial,sans-serif;color:#172033;margin:32px}.sheet{max-width:900px;margin:auto}.top{display:flex;justify-content:space-between;border-bottom:2px solid #24354b;padding-bottom:18px}.muted{color:#64748b}.meta{display:grid;grid-template-columns:1fr 1fr;gap:12px 28px;margin:24px 0}.meta div{border-bottom:1px solid #dbe2ea;padding:8px 0}.meta b{display:block;font-size:11px;text-transform:uppercase;color:#64748b;margin-bottom:4px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #cbd5e1;padding:9px;text-align:left}th{background:#eaf1f8}td.num,th.num{text-align:right}.total{font-weight:bold}.actions{max-width:900px;margin:0 auto 18px;text-align:right}.actions button{padding:8px 14px}@media print{body{margin:0}.actions{display:none}.sheet{max-width:none}}
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print</button></div>
<main class="sheet">
    <header class="top"><div><h1 style="margin:0">{{ $direction === 'receipt' ? 'Bank Receipt Voucher' : 'Bank Payment Voucher' }}</h1><p class="muted">Project #{{ (int) getSelectedTown() }}</p></div><div style="text-align:right"><strong>{{ $source->voucher_series }}-{{ $source->voucher_number }}</strong><br><span class="muted">{{ substr((string) $source->effective_date, 0, 10) }}</span></div></header>
    <section class="meta">
        <div><b>Linked source</b>{{ $source->source_type ? $source->source_type.'-'.$source->source_number : 'Bank transfer' }}</div>
        <div><b>Party</b>{{ trim((string) $source->first_name.' '.(string) $source->last_name) ?: ($source->plot_name ?: '—') }}</div>
        <div><b>Payment method / reference</b>{{ (int) $source->payment_type === 3 ? 'Cheque' : ((int) $source->payment_type === 2 ? 'Online' : 'Bank transfer') }}{{ $source->t_number ? ' / '.$source->t_number : '' }}</div>
        <div><b>Project bank account</b>{{ $source->bank_name }} / {{ $source->account_title }}</div>
        <div><b>Amount</b>{{ number_format((float) $source->amount, 2) }}</div>
        <div><b>Narration</b>{{ $source->narration }}</div>
    </section>
    <h2>Posting lines</h2>
    <table><thead><tr><th>Account</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
    @php($debitTotal = 0)
    @php($creditTotal = 0)
    @foreach($lines as $line)
        @php($debitTotal += (float) $line->debit)
        @php($creditTotal += (float) $line->credit)
        <tr><td>{{ $line->head_name }} / {{ $line->subhead_name }}</td><td class="num">{{ number_format((float) $line->debit, 2) }}</td><td class="num">{{ number_format((float) $line->credit, 2) }}</td></tr>
    @endforeach
    </tbody><tfoot><tr class="total"><td>Total</td><td class="num">{{ number_format($debitTotal, 2) }}</td><td class="num">{{ number_format($creditTotal, 2) }}</td></tr></tfoot></table>
</main>
</body>
</html>
