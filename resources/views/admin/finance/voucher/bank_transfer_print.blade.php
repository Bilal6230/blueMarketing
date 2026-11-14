<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $kind === 'deposit' ? 'Bank Deposit' : 'Bank Withdrawal' }} Voucher</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eef2f7;color:#15243a;font:14px/1.45 Arial,sans-serif}.toolbar{max-width:900px;margin:18px auto;text-align:right}.toolbar button{border:0;border-radius:6px;background:#1769e0;color:#fff;padding:10px 16px;cursor:pointer}.sheet{max-width:900px;margin:0 auto 28px;padding:34px;background:#fff;box-shadow:0 8px 28px #15243a18}.heading{text-align:center;border-bottom:2px solid #26384d;padding-bottom:16px;margin-bottom:20px}.heading h1{font-size:22px;margin:0 0 5px}.heading h2{font-size:17px;margin:0}.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;margin-bottom:20px}.meta div{padding:8px 0;border-bottom:1px solid #dce4ed}.meta b{display:inline-block;min-width:145px}.lines{width:100%;border-collapse:collapse}.lines th,.lines td{border:1px solid #aab6c4;padding:10px;text-align:left}.lines thead th{background:#263b55;color:#fff}.lines .number{text-align:right;white-space:nowrap}.narration{margin-top:20px;padding:14px;border:1px solid #dce4ed;min-height:72px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:70px}.signatures div{border-top:1px solid #44556a;text-align:center;padding-top:8px}/* A5 portrait, content-driven print layout. A fixed-position element repeats
           on every Chrome page and must never be used for voucher content. */
        @page{size:A5 portrait;margin:8mm}
        @media print{
            html,body{
                display:block!important;
                width:auto!important;height:auto!important;
                min-height:0!important;max-height:none!important;
                margin:0!important;padding:0!important;
                overflow:visible!important;background:#fff!important;
            }
            .toolbar{display:none!important}
            .sheet{
                position:static!important;display:block!important;
                box-sizing:border-box!important;
                width:100%!important;max-width:none!important;
                height:auto!important;min-height:0!important;max-height:none!important;
                margin:0!important;padding:0!important;
                overflow:visible!important;box-shadow:none!important;
            }
            .heading,.meta,.narration,.signatures,.lines tr{
                break-inside:avoid;page-break-inside:avoid;
            }
            .lines thead{display:table-header-group}
            body{font-size:11px!important;line-height:1.3!important}
            .heading{padding-bottom:9px!important;margin-bottom:10px!important}
            .heading h1{font-size:17px!important}
            .heading h2{font-size:13px!important}
            .meta{gap:4px 12px!important;margin-bottom:10px!important}
            .meta div{padding:5px 0!important}
            .meta b{min-width:75px!important}
            .lines th,.lines td{padding:6px!important}
            .narration{margin-top:10px!important;padding:9px!important;min-height:48px!important}
            .signatures{gap:16mm!important;margin-top:20mm!important}
        }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print voucher</button></div>
    <main class="sheet">
        <header class="heading">
            <h1>{{ Setting::getValue('app_name') }}</h1>
            <h2>{{ $kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher' }}</h2>
        </header>
        <section class="meta">
            <div><b>Voucher No.</b>{{ (string) $source->voucher_series.'-'.($source->voucher_number ?: $source->posting_batch_id) }}</div>
            <div><b>Date</b>{{ $source->effective_date }}</div>
            <div><b>Bank</b>{{ $source->bank_name }} / {{ $source->account_title }}</div>
            <div><b>Amount</b>{{ number_format((float) $source->amount, 2) }}</div>
            @if($source->cheque_number)<div><b>Linked cheque</b>CP-{{ $source->cheque_number }} / {{ $source->cheque_reference }}</div>@endif
        </section>
        <table class="lines">
            <thead><tr><th>Account</th><th class="number">Debit</th><th class="number">Credit</th></tr></thead>
            <tbody>
                @foreach($lines as $line)
                    <tr>
                        <td>{{ $line->head_name }}{{ $line->subhead_name ? ' / '.$line->subhead_name : '' }}</td>
                        <td class="number">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '—' }}</td>
                        <td class="number">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="narration"><b>Description</b><br>{{ $source->narration }}</div>
        <footer class="signatures"><div>Prepared by</div><div>Authorized by</div></footer>
    </main>
</body>
</html>
