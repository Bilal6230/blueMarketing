<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plot Receipt {{ $receipt->receipt_number }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:28px;background:#eef2f7;color:#182433;font:14px Arial,sans-serif}
        .receipt{max-width:760px;margin:auto;padding:32px;background:#fff;border:1px solid #dbe3ed;border-radius:12px}
        h1{margin:0 0 5px;text-align:center;font-size:24px}h2{margin:4px 0 24px;text-align:center;font-size:17px;color:#526174}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field{padding:12px;border:1px solid #e1e7ef;border-radius:8px}
        .label{display:block;margin-bottom:5px;color:#68778a;font-size:11px;text-transform:uppercase}.value{font-weight:600}
        .amount{margin-top:16px;padding:18px;background:#eff6ff;border-radius:8px;text-align:center;font-size:22px;font-weight:700}
        .actions{max-width:760px;margin:12px auto;text-align:right}.actions button{padding:9px 18px;border:0;border-radius:6px;background:#1677ff;color:#fff;cursor:pointer}
        /* Dedicated A5 receipt printing; do not force an A5-height body or use
           fixed positioning (fixed elements repeat across physical pages). */
        @page{size:A5 portrait;margin:8mm}
        @media print{
            html,body{display:block!important;width:auto!important;height:auto!important;min-height:0!important;max-height:none!important;margin:0!important;padding:0!important;overflow:visible!important;background:#fff!important}
            .receipt{display:block!important;position:static!important;width:100%!important;max-width:none!important;height:auto!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;overflow:visible!important}
            .actions{display:none!important}
            h1{font-size:19px!important;margin-bottom:4px!important}
            h2{font-size:14px!important;margin-bottom:14px!important}
            .grid{gap:8px!important}
            .field{padding:8px!important;break-inside:avoid;page-break-inside:avoid}
            .amount{margin-top:12px!important;padding:12px!important;font-size:18px!important;break-inside:avoid}
            .receipt p{margin-top:12px!important;overflow-wrap:anywhere}
        }
    </style>
</head>
<body>
<main class="receipt">
    <h1>Plot Payment Receipt</h1>
    <h2>{{ $allocation->plot_name }}</h2>
    <section class="grid">
        <div class="field"><span class="label">Receipt number</span><span class="value">{{ $receipt->receipt_number }}</span></div>
        <div class="field"><span class="label">Date</span><span class="value">{{ $receipt->receipt_date }}</span></div>
        <div class="field"><span class="label">Customer</span><span class="value">{{ trim(($allocation->first_name ?? '').' '.($allocation->last_name ?? '')) ?: ($allocation->business ?? 'Customer') }}</span></div>
        <div class="field"><span class="label">Booking</span><span class="value">#{{ $allocation->booking_id }}</span></div>
    </section>
    <div class="amount">Allocated amount: {{ number_format((float) $allocation->amount, 2) }}</div>
    <p>{{ $receipt->narration }}</p>
</main>
<div class="actions"><button type="button" onclick="window.print()">Print receipt</button></div>
</body>
</html>
