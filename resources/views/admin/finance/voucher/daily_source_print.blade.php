<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $displayNumber }} · Voucher source</title>
    <style>
        *{box-sizing:border-box}body{margin:24px;color:#172033;font:14px Arial,sans-serif}.sheet{max-width:980px;margin:auto}.top{display:flex;justify-content:space-between;border-bottom:2px solid #24364b;padding-bottom:16px}.muted{color:#64748b}.facts{display:grid;grid-template-columns:repeat(2,1fr);gap:10px 24px;margin:20px 0}.fact{padding:10px 12px;background:#f4f7fb;border-radius:6px}.fact strong{display:block;font-size:11px;text-transform:uppercase;color:#64748b;margin-bottom:4px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #cbd5e1;padding:9px;text-align:left}th{background:#e8eef6}.num{text-align:right}.status{margin-top:18px;padding:10px;background:#f4f7fb}@media print{body{margin:0}.sheet{max-width:none}.no-print{display:none}}
    </style>
</head>
<body>
<main class="sheet">
    <div class="top"><div><h1 style="margin:0 0 6px">{{ $projectName }}</h1><div class="muted">Daily voucher source record</div></div><div style="text-align:right"><strong>{{ $displayNumber }}</strong><div class="muted">{{ $source->date }}</div></div></div>
    <section class="facts">
        <div class="fact"><strong>Type / status</strong>{{ $source->transaction_type }} / {{ (int) ($source->is_active ?? 0) === 1 ? ((int) ($source->is_approve ?? 0) === 1 ? 'Approved' : 'Posted') : 'Inactive' }}</div>
        <div class="fact"><strong>Customer</strong>{{ $customerName ?: '—' }}</div>
        <div class="fact"><strong>Reference</strong>{{ $source->reference ?: '—' }}</div>
        <div class="fact"><strong>Amount</strong>{{ number_format(max((float) $source->amount_in, (float) $source->amount_out), 2) }}</div>
        @if((int) ($source->payment_type ?? 0) === 3)
            <div class="fact"><strong>Cheque purpose</strong>{{ $source->cheque_purpose ?: '—' }}</div>
            <div class="fact"><strong>Cheque status</strong>{{ [0 => 'Pending', 1 => 'Passed / cleared', 2 => 'Returned', 3 => 'Bounced / rejected'][(int) ($source->passing_status ?? 0)] ?? 'Pending' }}</div>
        @endif
        @if((int) ($source->payment_type ?? 0) === 2)
            <div class="fact"><strong>Online transaction number</strong>{{ $source->t_number ?: '—' }}</div>
            <div class="fact"><strong>Bank account ID</strong>{{ $source->bank_id ?: '—' }}</div>
            <div class="fact"><strong>Passing date</strong>{{ $source->passing_date ?: '—' }}</div>
            <div class="fact"><strong>Confirmation status</strong>{{ [0 => 'Pending', 1 => 'Confirmed', 2 => 'Rejected', 3 => 'Cancelled'][(int) ($source->passing_status ?? 0)] ?? 'Pending' }}</div>
        @endif
        <div class="fact" style="grid-column:1/-1"><strong>Narration / detail</strong>{{ $source->description ?: '—' }}</div>
    </section>
    <h2 style="font-size:17px">Accounting lines</h2>
    <table><thead><tr><th>Head / account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
    @forelse($lines as $line)
        <tr><td>{{ trim(($line->head_name ? $line->head_name.' / ' : '').($line->subhead_name ?: 'Account')) }}</td><td>{{ $line->detail ?: '—' }}</td><td class="num">{{ number_format((float) $line->amount_out, 2) }}</td><td class="num">{{ number_format((float) $line->amount_in, 2) }}</td></tr>
    @empty
        <tr><td colspan="4" class="muted">No posted accounting line is linked to this source.</td></tr>
    @endforelse
    </tbody></table>
    @if((int) ($source->payment_type ?? 0) === 3 && $lifecycleEvents->isNotEmpty())
        <h2 style="font-size:17px">Cheque lifecycle</h2>
        <table><thead><tr><th>Date</th><th>Event</th><th>Status</th><th>Purpose</th><th>Note</th></tr></thead><tbody>
        @foreach($lifecycleEvents as $event)
            <tr><td>{{ $event->created_at }}</td><td>{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</td><td>{{ $event->to_status === null ? '—' : ([0 => 'Pending', 1 => 'Passed / cleared', 2 => 'Returned', 3 => 'Bounced / rejected'][(int) $event->to_status] ?? 'Updated') }}</td><td>{{ $event->purpose ?: '—' }}</td><td>{{ $event->note ?: '—' }}</td></tr>
        @endforeach
        </tbody></table>
    @endif
    @if((int) ($source->payment_type ?? 0) === 2 && $onlineLifecycleEvents->isNotEmpty())
        <h2 style="font-size:17px">Online payment lifecycle</h2>
        <table><thead><tr><th>Date</th><th>Event</th><th>Status</th><th>Transaction</th><th>Note</th></tr></thead><tbody>
        @foreach($onlineLifecycleEvents as $event)
            <tr><td>{{ $event->created_at }}</td><td>{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</td><td>{{ $event->to_status === null ? '—' : ([0 => 'Pending', 1 => 'Confirmed', 2 => 'Rejected', 3 => 'Cancelled'][(int) $event->to_status] ?? 'Updated') }}</td><td>{{ $event->transaction_number ?: '—' }}</td><td>{{ $event->note ?: '—' }}</td></tr>
        @endforeach
        </tbody></table>
    @endif
    @if(!empty($source->check_history))<section class="status"><strong>Cheque history</strong><pre style="white-space:pre-wrap">{{ json_encode($source->check_history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></section>@endif
    <p class="muted">Printed {{ now()->format('Y-m-d H:i') }}</p>
    <button class="no-print" onclick="window.print()">Print</button>
</main>
</body>
</html>
