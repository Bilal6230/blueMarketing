<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $mode === 'completed' ? 'Completed Follow-ups' : ($mode === 'pending_overdue' ? 'Pending & Overdue Follow-ups' : "Today's Follow-ups") }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:#17233c;font-family:Inter,Segoe UI,Arial,sans-serif}.wrap{max-width:1500px;margin:auto;padding:28px}.top,.filter,.empty,.user-block{background:#fff;border:1px solid #e2e8f2;border-radius:14px;box-shadow:0 4px 18px rgba(28,50,85,.06)}.top{padding:22px 26px;display:flex;align-items:center;justify-content:space-between}.top h1{margin:0 0 6px;font-size:25px}.muted{color:#71809b;font-size:13px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 16px;background:#1677ff;color:#fff;text-decoration:none;font-weight:600;cursor:pointer}.btn.light{background:#edf4ff;color:#1469da}.filter{margin:18px 0;padding:18px;display:grid;grid-template-columns:1fr 1fr auto;gap:14px;align-items:end}.filter label{display:block;margin-bottom:7px;font-size:12px;font-weight:700;color:#53627a}.filter input{width:100%;height:42px;border:1px solid #ccd6e5;border-radius:8px;padding:0 12px}.cards{display:flex;gap:14px;margin:18px 0;overflow-x:auto;padding:2px 2px 8px}.user-card{flex:0 0 260px;display:block;background:#fff;border:2px solid transparent;border-radius:13px;padding:18px;text-decoration:none;color:inherit;box-shadow:0 4px 16px rgba(28,50,85,.06);transition:.15s}.user-card:hover{border-color:#8dbdff;transform:translateY(-2px)}.user-card.selected{border-color:#1677ff;background:#eaf3ff;box-shadow:0 5px 18px rgba(22,119,255,.2)}.user-card.selected .avatar{background:#1677ff;color:#fff}.avatar{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#e8f2ff;color:#1677ff;font-weight:800}.card-line{display:flex;align-items:center;gap:12px}.card-name{font-weight:750}.count{margin-top:5px;color:#71809b;font-size:13px}.user-block{overflow:hidden;margin:18px 0}.user-head{padding:14px 18px;background:#14243b;color:#fff;display:flex;justify-content:space-between;align-items:center}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:920px}th{background:#eaf1fa;color:#34445f;text-align:left;font-size:12px;padding:11px}td{padding:11px;border-top:1px solid #e5eaf1;font-size:13px;vertical-align:top}.customer{font-weight:700;color:#1268d4;text-decoration:none}.status{display:inline-block;padding:4px 8px;border-radius:20px;background:#e8f7ef;color:#168653;font-weight:700;font-size:11px}.late{color:#d33b3b;font-weight:800}.empty{padding:36px;text-align:center;color:#71809b}.skeleton{height:54px;margin:12px 0;border-radius:10px;background:linear-gradient(90deg,#e8edf4 25%,#f7f9fc 50%,#e8edf4 75%);background-size:200% 100%;animation:shine 1.1s infinite}@keyframes shine{to{background-position:-200% 0}}@media(max-width:720px){.wrap{padding:12px}.top{align-items:flex-start;gap:15px;flex-direction:column}.filter{grid-template-columns:1fr}.top h1{font-size:21px}.user-card{flex-basis:220px}}
    </style>
</head>
<body>
@php
    $title = $mode === 'completed' ? 'Completed Follow-ups' : ($mode === 'pending_overdue' ? 'Pending + Overdue Follow-ups' : "Today's Follow-ups");
    $range = $fromDate->format('Y-m-d').' to '.$toDate->format('Y-m-d');
@endphp
<main class="wrap">
    <section class="top">
        <div><h1>{{ $title }}</h1><div class="muted">Selected project records only · {{ $range }}</div></div>
        <a class="btn light" href="{{ route('dashboard') }}">← Dashboard</a>
    </section>

    <form class="filter" method="get" action="{{ route('crm.todayLeadWorkReport') }}" onsubmit="this.date_range.value=this.from.value+' to '+this.to.value">
        <input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="date_range" value="{{ $range }}">
        <div><label for="from">FROM</label><input id="from" name="from" type="date" value="{{ $fromDate->format('Y-m-d') }}" required></div>
        <div><label for="to">TO</label><input id="to" name="to" type="date" value="{{ $toDate->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required></div>
        <button class="btn" type="submit">Apply Filter</button>
    </form>

    @if($cards->isNotEmpty())
        <div class="cards">
            @foreach($cards as $card)
                <a class="user-card {{ (int)$userId === (int)$card['id'] ? 'selected' : '' }}" data-user-id="{{ $card['id'] }}" href="{{ route('crm.todayLeadWorkReport', ['mode'=>$mode,'date_range'=>$range,'user_id'=>$card['id']]) }}">
                    <div class="card-line"><span class="avatar">{{ strtoupper(substr($card['name'],0,1)) }}</span><div><div class="card-name">{{ $card['name'] }}</div><div class="count">{{ number_format($card['count']) }} record(s)</div></div></div>
                </a>
            @endforeach
        </div>

        <div id="report-results">@include('dashboard.partials.follow_up_user_tables', ['mode'=>$mode, 'rowsByUser'=>$rowsByUser])</div>
    @else
        <div class="empty">No {{ strtolower($title) }} found for this project and selected period.</div>
    @endif
</main>
<script src="{{ asset('template/admin/plugins/jquery/jquery.min.js') }}"></script>
<script>
$(function(){
    $(document).on('click','.user-card[data-user-id]',function(event){
        event.preventDefault();
        const $card=$(this),url=$card.attr('href');
        $('.user-card').removeClass('selected');
        $card.addClass('selected');
        $('#report-results').html('<div class="skeleton"></div>'.repeat(4));
        $.ajax({
            url,method:'GET',dataType:'json',
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        }).done(payload=>{
            $('#report-results').html(payload.html||'');
            window.history.replaceState({},'',url);
        }).fail(()=>{
            $('#report-results').html('<div class="empty">Data load nahi ho saka. Please dobara try karein.</div>');
        });
    });
});
</script>
</body>
</html>
