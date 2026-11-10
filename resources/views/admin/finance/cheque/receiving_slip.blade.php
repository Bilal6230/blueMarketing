<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cheque Receiving Slip {{ $slipNumber }} - {{ Setting::getValue('app_name') }}</title>
    <style>
        * {box-sizing:border-box}
        body {margin:0;background:#eef2f6;color:#243447;font:14px Arial,sans-serif}
        .toolbar {background:#102034;padding:12px;text-align:center}
        .toolbar button {background:#13824b;border:0;color:white;padding:10px 22px;border-radius:5px;font-weight:bold;cursor:pointer}
        .paper {width:min(850px,95vw);margin:22px auto;padding:34px 42px;background:white;box-shadow:0 6px 30px #15253522}
        .company {display:flex;align-items:center;gap:20px;border-bottom:3px solid #13824b;padding-bottom:16px}
        .company img {max-height:65px;max-width:140px;object-fit:contain}
        .company h1 {margin:0;color:#0c6e40;font-size:24px}
        h2 {text-align:center;font-size:19px;margin:22px 0 8px;letter-spacing:.4px}
        .disclaimer {text-align:center;color:#a13b22;font-weight:bold;line-height:1.5;margin:8px 0 23px}
        .meta {display:flex;justify-content:space-between;gap:12px;margin-bottom:17px;font-size:13px}
        .grid {display:grid;grid-template-columns:1fr 1fr;gap:0 26px}
        .item {display:flex;gap:12px;justify-content:space-between;border-bottom:1px solid #dce4ea;padding:11px 0}
        .item strong {flex-shrink:0;color:#536173;font-size:12px}
        .item span {text-align:right;overflow-wrap:anywhere}
        .wide {grid-column:1/-1}
        .wide span {text-align:left}
        .status {display:inline-block;color:#0d6b3e;border:1px solid #b7ddc7;border-radius:5px;padding:3px 9px;font-weight:bold;text-transform:capitalize}
        .signatures {display:flex;justify-content:space-between;gap:40px;margin-top:70px}
        .signatures div {width:43%;border-top:1px solid #3b4856;text-align:center;padding-top:9px}
        .footer {border-top:1px solid #dce4ea;margin-top:30px;padding-top:12px;text-align:center;font-size:11px;color:#657487}
        @page {size:A4;margin:12mm}
        @media print {.toolbar{display:none}body{background:white}.paper{width:100%;margin:0;padding:0;box-shadow:none}.company img{print-color-adjust:exact}}
        @media(max-width:600px){.paper{padding:20px}.grid{grid-template-columns:1fr}.wide{grid-column:1}.meta{display:block}.signatures{gap:16px}}
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print Receiving Slip</button></div>
    <main class="paper">
        <header class="company">
            <img src="{{ asset('images/logo/blue-marketing-logo.png') }}" alt="Company logo">
            <div>
                <h1>{{ Setting::getValue('app_name') }}</h1>
                <div>Official cheque receiving acknowledgement</div>
            </div>
        </header>
        <h2>CHEQUE RECEIVING SLIP</h2>
        <p class="disclaimer">Receipt of cheque only — NOT proof of bank clearance, successful payment or final accounting voucher.</p>
        <div class="meta">
            <div><strong>Slip No:</strong> {{ $slipNumber }}</div>
            <div><strong>Received Date:</strong> {{ $cheque->received_date }}</div>
        </div>
        <div class="grid">
            <div class="item"><strong>Cheque Number</strong><span>{{ $cheque->cheque_number }}</span></div>
            <div class="item"><strong>Cheque Date</strong><span>{{ $cheque->cheque_date }}</span></div>
            <div class="item"><strong>Cheque Amount</strong><span>Rs. {{ number_format((float) $cheque->amount,2) }}</span></div>
            <div class="item"><strong>Bank</strong><span>{{ $cheque->bank_name }}</span></div>
            <div class="item"><strong>Branch</strong><span>{{ $cheque->branch ?: '—' }}</span></div>
            <div class="item"><strong>Received From</strong><span>{{ $cheque->received_from }}</span></div>
            <div class="item wide"><strong>Related Customer Account</strong><span>{{ trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) }} (#{{ $cheque->customer_id }})</span></div>
            <div class="item wide"><strong>Purpose / Description</strong><span>{{ $cheque->purpose }}</span></div>
            <div class="item wide"><strong>Remarks</strong><span>{{ $cheque->remarks ?: '—' }}</span></div>
            <div class="item"><strong>Current Cheque Status</strong><span class="status">{{ str_replace('_',' ', $cheque->status) }}</span></div>
            <div class="item"><strong>Received By</strong><span>{{ $receiver ?: 'User #'.$cheque->created_by }}</span></div>
        </div>
        <div class="signatures">
            <div>Received By / Authorized Person</div>
            <div>Customer / Depositor Signature</div>
        </div>
        <p class="footer">This slip displays the current status from the original cheque record. A cheque receiving slip does not confirm available funds. Generated from the secure cheque register.</p>
    </main>
    @if(request()->boolean('print'))
        <script>window.addEventListener('load', function(){ window.print(); });</script>
    @endif
</body>
</html>