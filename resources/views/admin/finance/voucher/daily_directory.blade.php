@extends('admin.layouts.master')
@section('title', $presetType ? $presetType.' Vouchers' : 'Voucher Directory')

@section('content')
<style>
    html.daily-voucher-directory-active,body.daily-voucher-directory-active{height:100%;overflow:hidden!important}
    body.daily-voucher-directory-active .main-footer{display:none!important}
    .content-wrapper.daily-voucher-page{height:calc(100vh - 57px)!important;min-height:0!important;overflow:hidden!important;display:flex;flex-direction:column;padding:.45rem .75rem!important}
    .daily-voucher-page .content-header{flex:0 0 auto;padding:.15rem 0 .35rem}
    .daily-voucher-page>.content{flex:1 1 auto;min-height:0;overflow:hidden;padding:0}
    .daily-voucher-page>.content>.container-fluid{height:100%;min-height:0;display:flex;flex-direction:column;padding:0}
    .daily-voucher-card{border:0;border-radius:14px;box-shadow:0 8px 24px rgba(16,36,64,.08);overflow:hidden}
    /* Applied only to the selected voucher directory, never to the global sidebar. */
    .daily-voucher-page{--dv-accent:#2463eb;--dv-pale:#eff5ff}
    .daily-voucher-page.dv-theme-credit{--dv-accent:#128044;--dv-pale:#e9f8ef}
    .daily-voucher-page.dv-theme-debit{--dv-accent:#bd3140;--dv-pale:#fff0f1}
    .daily-voucher-page .content-header h1{color:var(--dv-accent);font-weight:650}
    .daily-voucher-page .daily-voucher-card{border-top:3px solid var(--dv-accent)}
    .daily-voucher-page .daily-voucher-table thead tr:first-child th{background:var(--dv-pale);border-bottom:2px solid var(--dv-accent)}
    .daily-voucher-page .daily-voucher-actions .btn-primary,.daily-voucher-page .daily-voucher-actions .btn-success{background:var(--dv-accent);border-color:var(--dv-accent);color:#fff}
    .daily-voucher-page .daily-voucher-actions .btn:hover{filter:brightness(.93)}
    .daily-voucher-page .daily-voucher-table td:nth-child(3){color:var(--dv-accent)}
    .daily-voucher-page.dv-theme-credit,.daily-voucher-page.dv-theme-debit{background:var(--dv-pale)!important}
    .daily-voucher-page .daily-voucher-card{background:color-mix(in srgb,var(--dv-pale) 18%,white)}
    .daily-voucher-page #dv-type{background-color:var(--dv-pale);border:1px solid var(--dv-accent);color:var(--dv-accent);font-weight:600}
    .daily-voucher-page .dv-column-filters input:focus{border-color:var(--dv-accent);box-shadow:0 0 0 .15rem color-mix(in srgb,var(--dv-accent) 15%,transparent)}
    .daily-voucher-page .dv-page-btn{border:1px solid var(--dv-accent);background:#fff;color:var(--dv-accent);font-weight:500;border-radius:5px;min-width:31px;height:30px;padding:3px 7px}
    .daily-voucher-page .dv-page-btn.active{background:var(--dv-accent);color:#fff}
    .daily-voucher-page .dv-page-btn:disabled{opacity:.4;cursor:not-allowed}
    .daily-voucher-page .dv-page-ellipsis{padding:0 .2rem;color:#6f7c8a}
    .daily-voucher-page #dv-pages{display:flex;align-items:center;flex-wrap:wrap;gap:4px}
    .daily-voucher-page .dv-pagination-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .daily-voucher-page .dv-total-records{font-size:.81rem;color:#596476;font-weight:500}
    #dv-entry-loading{position:absolute;inset:0;z-index:2;display:flex;align-items:center;justify-content:center;gap:.75rem;background:#fff;color:#48556b}
    #dv-entry-loading.d-none{display:none!important}
    #dv-entry-loading .spinner-border{width:1.4rem;height:1.4rem;border-width:.18em}
    .daily-voucher-filters{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr)) auto;gap:.6rem;align-items:end}
    .daily-voucher-actions{display:flex;gap:.4rem;align-items:center;white-space:nowrap}
    .daily-voucher-shortcuts{display:flex;flex-wrap:wrap;gap:.5rem}
    .daily-voucher-table-card{flex:1 1 0;min-height:0;display:flex;flex-direction:column;margin-bottom:0!important}
    .dv-table-tools{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:.6rem .75rem;background:#fff}
    .dv-table-tools label{display:flex;align-items:center;gap:7px;margin:0;font-size:.88rem}
    .dv-table-tools select{width:72px}
    .dv-table-tools input{width:220px;max-width:45vw}
    .daily-voucher-table th.dv-sortable{cursor:pointer;user-select:none}
    .daily-voucher-table th.dv-sortable:hover{color:var(--dv-accent)}
    .dv-sort-indicator{margin-left:5px;color:var(--dv-accent);font-size:.75em}
    .dv-sortable:focus-visible{outline:2px solid var(--dv-accent);outline-offset:-2px}
    .daily-voucher-table-wrap{flex:1 1 0;min-height:0;overflow:auto}.daily-voucher-table{min-width:1100px;table-layout:fixed}
    /* Fixed column tracks prevent spare table width from enlarging Actions. Details absorbs the rest. */
    .daily-voucher-table{width:100%}
    .daily-voucher-table col.dv-col-actions{width:148px}
    .daily-voucher-table th:last-child,.daily-voucher-table td:last-child{width:148px;max-width:148px}
    .daily-voucher-table td:last-child{padding:.25rem .3rem;white-space:nowrap}
    .daily-voucher-table td:last-child .btn{padding:.2rem .32rem;font-size:.72rem;line-height:1.35;margin-right:2px}
    .daily-voucher-table td:last-child .btn:last-child{margin-right:0}
    .daily-voucher-table thead th{position:sticky;top:0;z-index:2;background:#edf4fc}.daily-voucher-table td{vertical-align:middle}
    .daily-voucher-table thead .dv-column-filters th{top:38px;background:#fff;z-index:3;padding:.3rem}
    .daily-voucher-table thead .dv-column-filters input{min-width:72px;font-size:.75rem;padding:.25rem .4rem;height:30px}
    .daily-voucher-muted{color:#70819a;font-size:.85rem}
    .daily-voucher-loading #dv-rows{pointer-events:none}
    .dv-skeleton-bar{height:12px;border-radius:5px;background:linear-gradient(90deg,#e7ecf1 22%,#f5f7fa 42%,#e7ecf1 67%);background-size:220% 100%;animation:dv-skeleton-pulse 1.3s ease-in-out infinite}
    .daily-voucher-page.dv-theme-credit .dv-skeleton-bar{background-image:linear-gradient(90deg,#daeee2 22%,#f3fbf6 42%,#daeee2 67%)}
    .daily-voucher-page.dv-theme-debit .dv-skeleton-bar{background-image:linear-gradient(90deg,#f2dce0 22%,#fff5f6 42%,#f2dce0 67%)}
    .daily-voucher-table tbody tr.dv-skeleton-row td{height:44px;vertical-align:middle;padding:.65rem .45rem}
    @keyframes dv-skeleton-pulse{0%{background-position:100% 0}100%{background-position:-100% 0}}
    @media(prefers-reduced-motion:reduce){.dv-skeleton-bar{animation:none}}
    @media(max-width:1200px){.daily-voucher-filters{grid-template-columns:repeat(3,minmax(115px,1fr))}.daily-voucher-actions{grid-column:1/-1;justify-content:flex-end}}
    @media(max-width:900px){.daily-voucher-filters{grid-template-columns:repeat(2,minmax(130px,1fr))}.daily-voucher-actions{grid-column:1/-1;justify-content:flex-end}}
    @media(max-width:520px){.daily-voucher-filters{grid-template-columns:1fr}.daily-voucher-page .daily-voucher-actions{grid-column:1;justify-content:flex-end}.content-wrapper.daily-voucher-page{padding:.35rem!important}}
</style>
<div class="content-wrapper daily-voucher-page {{ in_array($presetType, ['CR','CCR','BOR'], true) ? 'dv-theme-credit' : (in_array($presetType, ['DR','CDR','BOP','CP'], true) ? 'dv-theme-debit' : '') }}" id="dv-page">
    <section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1">
                @if($presetType)
                    {{ $presetType }} Vouchers
                @else
                    Voucher Directory
                @endif
            </h1>
            <div class="daily-voucher-muted">Selected project records · Read-only history</div>
        </div>
        <div class="d-flex flex-wrap" style="gap:.5rem">
            @if(\App\Http\Controllers\Finance\VoucherController::canAccessFinanceApprovalsForUser(auth()->user()))
                <a class="btn btn-outline-primary" href="{{ route('finance.daily-vouchers.approvals.index') }}">Approvals</a>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div></section>
    <section class="content"><div class="container-fluid">
        <div class="card daily-voucher-card mb-3"><div class="card-body">
            <form id="daily-voucher-filters" class="daily-voucher-filters">
                <div><label for="dv-from">From</label><input id="dv-from" name="from" type="date" class="form-control"></div>
                <div><label for="dv-to">To</label><input id="dv-to" name="to" type="date" class="form-control"></div>
                <input id="dv-type" name="type" type="hidden" value="{{ $presetType }}">
                <div><label for="dv-account">Account</label><select id="dv-account" name="account" class="form-control"><option value="">All accounts</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->head_name }}{{ $account->sub_name ? ' · '.$account->sub_name : '' }}</option>@endforeach</select></div>
                <div><label for="dv-search">Search</label><input id="dv-search" name="search" class="form-control" maxlength="100" placeholder="Number, reference, account or detail"></div>
                <div class="daily-voucher-actions">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter mr-1"></i> Apply</button>
                    <button class="btn btn-success" type="button" id="dv-create-voucher"><i class="fas fa-plus mr-1"></i> New voucher</button>
                </div>
            </form>
            <div class="d-none" aria-hidden="true">
                @foreach([
                    'CR' => ['Credit Voucher (CR)', route('finance.voucher.in.method', ['method'=>'cash','series'=>'CR','embed'=>1])],
                    'DR' => ['Debit Voucher (DR)', route('finance.voucher.out.method', ['method'=>'cash','series'=>'CP','embed'=>1])],
                    'CCR' => ['Cash Credit (CCR)', route('finance.voucher.in.method', ['method'=>'cash','series'=>'CCR','embed'=>1])],
                    'CDR' => ['Cash Debit (CDR)', route('finance.voucher.out.method', ['method'=>'cash','series'=>'CDR','embed'=>1])],
                    'BOR' => ['Bank Credit (BOR)', route('finance.voucher.in.method', ['method'=>'online','series'=>'BOR','embed'=>1])],
                    'BOP' => ['Bank Debit (BOP)', route('finance.voucher.out.method', ['method'=>'online','series'=>'BOP','embed'=>1])],
                ] as $series => [$label, $url])
                    <button type="button" class="dv-create" data-url="{{$url}}" data-cheque-url="{{ in_array($series, ['BOR','BOP'], true) ? route($series === 'BOR' ? 'finance.voucher.in.method' : 'finance.voucher.out.method', ['method'=>'cheque','series'=>$series,'embed'=>1]) : '' }}" data-title="{{$label}}" data-series="{{$series}}"></button>
                @endforeach
            </div>
        </div></div>
        <div class="card daily-voucher-card daily-voucher-table-card">
            <div class="dv-table-tools">
                <label for="dv-page-length">Show <select class="form-control form-control-sm" id="dv-page-length"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select> entries</label>
                <label for="dv-table-search">Search: <input id="dv-table-search" class="form-control form-control-sm" type="search" placeholder="Search vouchers" autocomplete="off"></label>
            </div>
            <div class="daily-voucher-table-wrap" id="dv-table-wrap"><table class="table table-sm table-hover mb-0 daily-voucher-table">
                <colgroup><col style="width:48px"><col style="width:100px"><col style="width:125px"><col style="width:160px"><col style="width:180px"><col><col style="width:115px"><col class="dv-col-actions"></colgroup>
                <thead>
                <tr><th>#</th><th class="dv-sortable" data-sort="date" tabindex="0" role="button">Date<span class="dv-sort-indicator">↕</span></th><th class="dv-sortable" data-sort="number" tabindex="0" role="button">Voucher #<span class="dv-sort-indicator">↕</span></th><th class="dv-sortable" data-sort="head" tabindex="0" role="button">Head<span class="dv-sort-indicator">↕</span></th><th class="dv-sortable" data-sort="subhead" tabindex="0" role="button">Sub Head<span class="dv-sort-indicator">↕</span></th><th class="dv-sortable" data-sort="detail" tabindex="0" role="button">Details<span class="dv-sort-indicator">↕</span></th><th class="dv-sortable text-right" data-sort="amount" tabindex="0" role="button">Amount<span class="dv-sort-indicator">↕</span></th><th>Actions</th></tr>
                <tr class="dv-column-filters">
                    <th></th>
                    @foreach(['date'=>'Date','number'=>'Voucher #','head'=>'Head','subhead'=>'Sub Head','detail'=>'Details','amount'=>'Amount'] as $key=>$label)
                        <th><input type="search" class="form-control form-control-sm dv-column-filter" data-column="{{$key}}" aria-label="Filter {{$label}}" placeholder="Filter"></th>
                    @endforeach
                    <th></th>
                </tr>
            </thead><tbody id="dv-rows"></tbody></table></div>
            <div class="card-footer dv-pagination-footer">
                <div id="dv-record-summary" class="dv-total-records" aria-live="polite">Loading records...</div>
                <nav id="dv-pages" aria-label="Voucher pagination"></nav>
            </div>
        </div>
    </div></section>
</div>
<div class="modal fade" id="dv-detail-modal" tabindex="-1" role="dialog" aria-labelledby="dv-detail-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="dv-detail-title">Voucher details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><dl class="row mb-0" id="dv-detail-fields"></dl><div id="dv-detail-lines-wrap" class="mt-3 d-none"><h6>Voucher lines</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Account</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody id="dv-detail-lines"></tbody></table></div></div><div id="dv-detail-prints-wrap" class="mt-3 d-none"><h6>Plot receipt print views</h6><div id="dv-detail-prints" class="d-flex flex-wrap" style="gap:.5rem"></div></div><div id="dv-detail-audit-wrap" class="mt-3 d-none"><h6>Payment and audit information</h6><dl class="row mb-0" id="dv-detail-audit"></dl></div></div><div class="modal-footer"><a id="dv-detail-print" class="btn btn-primary" target="_blank" rel="noopener"><i class="fas fa-print mr-1"></i> Open print view</a><span id="dv-no-print" class="small text-muted d-none">No posted print record is available.</span><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div></div></div></div>
<div class="modal fade" id="dv-entry-modal" tabindex="-1" role="dialog" aria-labelledby="dv-entry-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="dv-entry-title">New voucher</h5>
                <div id="dv-bank-method-wrap" class="ml-auto mr-3 d-none">
                    <label class="small mb-0 mr-2" for="dv-bank-method">Payment</label>
                    <select id="dv-bank-method" class="form-control form-control-sm d-inline-block" style="width:auto">
                        <option value="online">Online</option><option value="cheque">Cheque</option>
                    </select>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="dv-entry-loading" class="d-none" role="status" aria-live="polite"><span class="spinner-border text-primary" aria-hidden="true"></span><span>Loading voucher form...</span></div>
                <div id="dv-entry-choose" class="p-4 d-none">
                    <label for="dv-entry-type" class="font-weight-bold mb-2">Choose voucher type</label>
                    <select id="dv-entry-type" class="form-control" aria-label="Choose a voucher to create">
                        <option value="">Select voucher type</option>
                        <option value="CR">Credit Voucher (CR)</option>
                        <option value="DR">Debit Voucher (DR)</option>
                        <option value="CCR">Cash Credit (CCR)</option>
                        <option value="CDR">Cash Debit (CDR)</option>
                        <option value="BOR">Bank Credit (BOR)</option>
                        <option value="BOP">Bank Debit (BOP)</option>
                        <option value="JV">Journal Voucher (JV)</option>
                        <option value="CV">Commission Voucher (CV)</option>
                        <option value="PR">Plot Receive Payment (PR)</option>
                        <option value="PP">Plot Purchase Voucher (PP)</option>
                        <option value="BD">Bank Deposit (BD)</option>
                        <option value="BW">Bank Withdrawal (BW)</option>
                    </select>
                </div>
                <div id="dv-entry-error" class="alert alert-danger m-3 d-none" role="alert">
                    <span id="dv-entry-error-text"></span>
                    <a id="dv-entry-fallback" class="alert-link ml-2" target="_blank" rel="noopener">Open voucher form</a>
                </div>
                <iframe id="dv-entry-frame" title="Voucher entry" style="display:block;width:100%;height:78vh;border:0;visibility:hidden" referrerpolicy="same-origin"></iframe>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    document.documentElement.classList.add('daily-voucher-directory-active');
    document.body.classList.add('daily-voucher-directory-active');
    const form=document.getElementById('daily-voucher-filters'), body=document.getElementById('dv-rows'), wrap=document.getElementById('dv-table-wrap');
    let start=0, length=25, filtered=0, total=0, timer, activeRequest=0, activeController=null;
    let sortColumn='date',sortDirection='desc';
    const tableSearch=document.getElementById('dv-table-search');
    document.getElementById('dv-page-length').addEventListener('change',event=>{length=Number(event.target.value);start=0;load();});
    tableSearch.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{start=0;load();},300);});
    function updateSortArrows(){
        document.querySelectorAll('.daily-voucher-table th.dv-sortable').forEach(th=>{
            const isActive=th.dataset.sort===sortColumn;
            th.querySelector('.dv-sort-indicator').textContent=isActive?(sortDirection==='asc'?'↑':'↓'):'↕';
            th.setAttribute('aria-sort',isActive?(sortDirection==='asc'?'ascending':'descending'):'none');
        });
    }
    document.querySelectorAll('.daily-voucher-table th.dv-sortable').forEach(th=>{
        const sort=()=>{sortDirection=sortColumn===th.dataset.sort?(sortDirection==='asc'?'desc':'asc'):'asc';sortColumn=th.dataset.sort;start=0;updateSortArrows();load();};
        th.addEventListener('click',sort);
        th.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();sort();}});
    });
    updateSortArrows();
    const scheduleLoad=()=>{clearTimeout(timer);timer=setTimeout(()=>{start=0;load();},300);};
    function showTableSkeleton(){
        const widths=[68,62,79,87,58,65,91,70];
        body.innerHTML=Array.from({length:10},(_,row)=>'<tr class="dv-skeleton-row" aria-hidden="true">'+
            widths.map((width,column)=>'<td><div class="dv-skeleton-bar" style="width:'+Math.max(25,width-((row+column)%3)*10)+'%"></div></td>').join('')+
            '</tr>').join('');
        wrap.setAttribute('aria-busy','true');
        recordSummary.textContent='Loading records...';
        pager.replaceChildren();
    }
    const pager=document.getElementById('dv-pages');
    const recordSummary=document.getElementById('dv-record-summary');
    function renderPagination(){
        const pages=Math.ceil(filtered/length);
        const current=Math.floor(start/length)+1;
        const renderButton=(label,page,disabled=false,active=false)=>{
            const button=document.createElement('button');
            button.type='button';button.className='dv-page-btn'+(active?' active':'');
            button.textContent=label;button.disabled=disabled;button.setAttribute('aria-label',label==='Previous'||label==='Next'?label+' page':'Page '+label);
            if(active)button.setAttribute('aria-current','page');
            if(!disabled)button.addEventListener('click',()=>{start=(page-1)*length;load();wrap.scrollTop=0;});
            pager.appendChild(button);
        };
        pager.replaceChildren();
        renderButton('Previous',current-1,current<=1);
        const shown=new Set([1,2,3,4,5,6,pages-1,pages]);
        for(let offset=-2;offset<=2;offset++)shown.add(current+offset);
        let previous=0;
        [...shown].filter(page=>page>=1&&page<=pages).sort((a,b)=>a-b).forEach(page=>{
            if(previous&&page>previous+1){
                const dots=document.createElement('span');dots.className='dv-page-ellipsis';dots.textContent='…';pager.appendChild(dots);
            }
            renderButton(String(page),page,false,page===current);previous=page;
        });
        renderButton('Next',current+1,current>=pages);
        const first=filtered?start+1:0, last=Math.min(start+length,filtered);
        recordSummary.textContent='Showing '+first+'–'+last+' of '+filtered.toLocaleString()+' records'+(total!==filtered?' (from '+total.toLocaleString()+' total)':'');
    }
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    async function load(){
        const requestId=++activeRequest;
        if(activeController)activeController.abort();
        activeController=new AbortController();
        wrap.classList.add('daily-voucher-loading');
        showTableSkeleton();
        const params=new URLSearchParams(new FormData(form)); params.set('start',start); params.set('length',length);
        params.set('sort_column',sortColumn);params.set('sort_direction',sortDirection);
        if(tableSearch.value.trim())params.set('search',tableSearch.value.trim());
        document.querySelectorAll('.dv-column-filter').forEach(input=>{if(input.value.trim())params.set('column_'+input.dataset.column,input.value.trim());});
        try {
            const response=await fetch(@json(route('finance.daily-vouchers.data'))+'?'+params.toString(),{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},signal:activeController.signal});
            if(!response.ok){
                const errorBody=await response.json().catch(()=>({}));
                throw new Error(errorBody.message||'Unable to load voucher history.');
            }
            const result=await response.json();
            if(requestId!==activeRequest)return;
            filtered=Number(result.recordsFiltered||0);
            total=Number(result.recordsTotal||0);
            renderPagination();
            body.innerHTML=result.data.length?result.data.map((row,index)=>`<tr><td>${start+index+1}</td><td>${esc(row.date)}</td><td class="font-weight-bold">${esc(row.number)}</td><td>${esc(row.head)||'—'}</td><td>${esc(row.subhead)||'—'}</td><td class="text-truncate" style="max-width:420px" title="${esc(row.detail)}">${esc(row.detail)||'—'}</td><td class="text-right">${Number(row.amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary dv-details" data-row="${esc(JSON.stringify(row))}">Details</button> ${row.edit_url?`<button type="button" class="btn btn-sm btn-outline-primary dv-edit" data-url="${esc(row.edit_url)}" data-number="${esc(row.number)}" title="Edit voucher" aria-label="Edit voucher ${esc(row.number)}"><i class="fas fa-edit" aria-hidden="true"></i></button>`:''} ${row.delete_url?`<button type="button" class="btn btn-sm btn-outline-danger dv-delete" title="Delete voucher" aria-label="Delete voucher ${esc(row.number)}" data-url="${esc(row.delete_url)}" data-id="${esc(row.delete_id||'')}" data-number="${esc(row.number)}"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>`:''} ${row.url?`<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${esc(row.url)}" aria-label="Print ${esc(row.number)}"><i class="fas fa-print"></i></a>`:'<span class="small text-muted">—</span>'}</td></tr>`).join(''):'<tr><td colspan="8" class="text-center text-muted p-4">No vouchers match these filters.</td></tr>';
        } catch(error){
            if(requestId!==activeRequest)return;
            body.innerHTML=`<tr><td colspan="8" class="text-center text-danger p-4">${esc(error.message)}</td></tr>`;
            pager.replaceChildren();
            recordSummary.textContent='Unable to load records';
        }
        finally{if(requestId===activeRequest){wrap.classList.remove('daily-voucher-loading');wrap.removeAttribute('aria-busy');activeController=null;}}
    }
    form.addEventListener('submit',event=>{event.preventDefault();start=0;load();});
    document.getElementById('dv-search').addEventListener('input',scheduleLoad);
    document.querySelectorAll('.dv-column-filter').forEach(input=>{
        input.addEventListener('input',scheduleLoad);
        input.addEventListener('search',scheduleLoad);
        input.addEventListener('keydown',event=>{if(event.key==='Enter')event.preventDefault();});
    });
    // The modal contains the real voucher form. No overlay, spinner or polling.
    const entryFrame=document.getElementById('dv-entry-frame');
    const entryError=document.getElementById('dv-entry-error');
    const entryErrorText=document.getElementById('dv-entry-error-text');
    const fallback=document.getElementById('dv-entry-fallback');
    const entryLoading=document.getElementById('dv-entry-loading');
    const dvPage=document.getElementById('dv-page');
    const voucherTypeLabels={CR:'Credit Voucher (CR)',DR:'Debit Voucher (DR)',CCR:'Cash Credit (CCR)',CDR:'Cash Debit (CDR)',BOR:'Bank Credit (BOR)',BOP:'Bank Debit (BOP)'};
    function updateDirectoryTheme(type){
        dvPage.classList.toggle('dv-theme-credit',['CR','CCR','BOR'].includes(type));
        dvPage.classList.toggle('dv-theme-debit',['DR','CDR','BOP','CP'].includes(type));
        const heading=dvPage.querySelector('.content-header h1');
        const label=type ? type+' Vouchers' : 'Voucher Directory';
        if(heading)heading.textContent=label;
        document.title=label+' - '+@json(Setting::getValue('app_name'));
    }
    // The voucher type comes from the page URL; individual pages never switch series.
    let requestedEntryUrl='',entryMode='create';
    function revealEntry(){
        if(!requestedEntryUrl)return;
        entryFrame.style.visibility='visible';
        entryLoading.classList.add('d-none');
    }
    function showEntryError(message,url=requestedEntryUrl){
        entryLoading.classList.add('d-none');
        entryFrame.style.visibility='hidden';
        entryErrorText.textContent=message;
        fallback.href=url||'#';
        fallback.classList.toggle('d-none',!url);
        entryError.classList.remove('d-none');
    }
    function loadEntry(url,mode='create'){
        entryMode=mode;
        requestedEntryUrl=url;
        entryLoading.classList.toggle('d-none',!url);
        entryFrame.style.visibility='hidden';
        entryError.classList.add('d-none');
        entryFrame.src=url||'about:blank';
        if(!url)showEntryError('No voucher entry form is available for this type.');
    }
    entryFrame.addEventListener('load',()=>{
        if(!requestedEntryUrl || entryFrame.src==='about:blank')return;
        try{
            const doc=entryFrame.contentDocument;
            if(entryMode==='edit')return;
            if(doc?.querySelector('#voucherForm')){
                // The iframe load event is a fallback for a missed child ready message.
                requestAnimationFrame(()=>requestAnimationFrame(revealEntry));
                return;
            }
            const message=doc?.querySelector('.alert-danger,.exception-message,h1')?.textContent?.trim();
            showEntryError(message ? 'Voucher form unavailable: '+message.slice(0,180) : 'Voucher form unavailable. Check your create permission or the server response.');
        }catch(error){
            showEntryError('Voucher form is blocked inside the modal. Open it in a separate tab.');
        }
    });
    const entryChoose=document.getElementById('dv-entry-choose');
    const entryType=document.getElementById('dv-entry-type');
    const methodWrap=document.getElementById('dv-bank-method-wrap');
    const seriesMap={CR:'CR',DR:'DR',CP:'DR',CCR:'CCR',CDR:'CDR',BOR:'BOR',BOP:'BOP'};
    const dedicatedRoutes={
        JV:@json(route('journal.voucher.create')),
        CV:@json(route('commision.voucher.create')),
        PR:@json(route('finance.voucher.plot-receipt.workspace')),
        PP:@json(route('finance.voucher.plot-purchase.workspace')),
        PPR:@json(route('finance.voucher.plot-receipt.workspace')),
        BD:@json(route('finance.voucher.transfer.workspace',['kind'=>'deposit'])),
        BW:@json(route('finance.voucher.transfer.workspace',['kind'=>'withdrawal']))
    };
    function chooseVoucher(){
        requestedEntryUrl='';
        entryLoading.classList.add('d-none');
        entryFrame.src='about:blank';
        entryFrame.style.display='none';
        entryFrame.style.visibility='hidden';
        entryError.classList.add('d-none');
        methodWrap.classList.add('d-none');
        entryType.value='';
        entryChoose.classList.remove('d-none');
        document.getElementById('dv-entry-title').textContent='New voucher';
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('show');
    }
    function openVoucherType(selected){
        if(dedicatedRoutes[selected]){
            window.location.assign(dedicatedRoutes[selected]);
            return;
        }
        const series=seriesMap[selected];
        const button=series ? document.querySelector(`.dv-create[data-series="${series}"]`) : null;
        if(!button){chooseVoucher();return;}
        entryChoose.classList.add('d-none');
        entryFrame.style.display='block';
        document.getElementById('dv-entry-title').textContent=button.dataset.title;
        methodWrap.classList.toggle('d-none',!button.dataset.chequeUrl);
        document.getElementById('dv-bank-method').value='online';
        methodWrap.dataset.onlineUrl=button.dataset.url;
        methodWrap.dataset.chequeUrl=button.dataset.chequeUrl;
        loadEntry(button.dataset.url);
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('show');
    }
    entryType.addEventListener('change',event=>{
        if(event.target.value)openVoucherType(event.target.value);
    });
    document.getElementById('dv-bank-method').addEventListener('change',event=>{
        loadEntry(event.target.value==='cheque'?methodWrap.dataset.chequeUrl:methodWrap.dataset.onlineUrl);
    });
    document.getElementById('dv-create-voucher').addEventListener('click',()=>{
        const selected=document.getElementById('dv-type').value;
        if(!selected || (!seriesMap[selected] && !dedicatedRoutes[selected]))chooseVoucher();
        else openVoucherType(selected);
    });
    if(window.jQuery){window.jQuery('#dv-entry-modal').on('hidden.bs.modal',()=>{requestedEntryUrl='';entryLoading.classList.add('d-none');entryError.classList.add('d-none');entryChoose.classList.add('d-none');entryFrame.style.visibility='hidden';entryFrame.src='about:blank';});}
    window.addEventListener('message',event=>{
        if(event.origin!==window.location.origin || event.source!==entryFrame.contentWindow)return;
        if(event.data?.type==='daily-voucher-form-ready' && entryMode==='create'){
            requestAnimationFrame(()=>requestAnimationFrame(revealEntry));
            return;
        }
        if(event.data?.type==='daily-voucher-edit-ready' && entryMode==='edit'){
            requestAnimationFrame(()=>requestAnimationFrame(revealEntry));
            return;
        }
        if(event.data?.type==='daily-voucher-edit-saved' && entryMode==='edit'){
            if(window.jQuery)window.jQuery('#dv-entry-modal').modal('hide');
            start=0;load();
            return;
        }
        if(event.data?.type==='daily-voucher-edit-cancelled' && entryMode==='edit'){
            if(window.jQuery)window.jQuery('#dv-entry-modal').modal('hide');
            return;
        }
        if(event.data?.type!=='daily-voucher-saved')return;
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('hide');
        document.getElementById('dv-entry-frame').src='about:blank';start=0;load();
    });
    body.addEventListener('click',event=>{
        const button=event.target.closest('.dv-edit');if(!button)return;
        const url=new URL(button.dataset.url,window.location.href);
        url.searchParams.set('embed','1');
        entryChoose.classList.add('d-none');
        methodWrap.classList.add('d-none');
        entryFrame.style.display='block';
        document.getElementById('dv-entry-title').textContent='Edit '+button.dataset.number;
        loadEntry(url.toString(),'edit');
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('show');
    });
    body.addEventListener('click',async event=>{
        const button=event.target.closest('.dv-delete');
        if(!button)return;
        const reason=window.prompt('Reason for deactivating '+button.dataset.number+' (required):');
        if(!reason || !reason.trim())return;
        if(reason.trim().length>500){window.alert('Reason cannot exceed 500 characters.');return;}
        if(!window.confirm('Deactivate '+button.dataset.number+'? No ledger record will be physically deleted.'))return;
        // JV/CV use their independent authorized reversal workflow.
        if(!button.dataset.id){
            const deletion=document.createElement('form');deletion.method='POST';deletion.action=button.dataset.url;
            for(const [name,value] of [['_token',@json(csrf_token())],['_method','DELETE'],['reason',reason.trim()],['effective_date',new Date().toISOString().slice(0,10)],['request_key',String(Date.now())]]){
                const field=document.createElement('input');field.type='hidden';field.name=name;field.value=value;deletion.appendChild(field);
            }
            document.body.appendChild(deletion);deletion.submit();return;
        }
        button.disabled=true;
        try{
            const formData=new URLSearchParams();
            formData.set('_token',@json(csrf_token()));formData.set('_method','DELETE');
            formData.set('id',button.dataset.id);formData.set('delete_reason',reason.trim());
            const response=await fetch(button.dataset.url,{
                method:'POST',credentials:'same-origin',
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
                body:formData.toString()
            });
            const payload=await response.json().catch(()=>({}));
            if(!response.ok)throw new Error(payload.message||'Unable to deactivate voucher.');
            load();
        }catch(error){window.alert(error.message||'Unable to deactivate voucher.');}
        finally{button.disabled=false;}
    });
    body.addEventListener('click',event=>{
        const button=event.target.closest('.dv-details'); if(!button)return;
        const row=JSON.parse(button.dataset.row), fields=document.getElementById('dv-detail-fields');
        document.getElementById('dv-detail-title').textContent=`${row.type} · ${row.number}`;
        fields.replaceChildren();
        [['Date',row.date],['Voucher type',row.type],['Voucher number',row.number],['Reference',row.reference],['Account / customer',row.account],['Amount',Number(row.amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})],['Status',row.status],['Narration / detail',row.detail]].forEach(([label,value])=>{const term=document.createElement('dt'),description=document.createElement('dd');term.className='col-sm-4';description.className='col-sm-8';term.textContent=label;description.textContent=value||'—';fields.append(term,description);});
        const audit=document.getElementById('dv-detail-audit'), auditWrap=document.getElementById('dv-detail-audit-wrap');
        audit.replaceChildren();
        Object.entries(row.audit||{}).forEach(([label,value])=>{if(value===null||value===undefined||value==='')return;const term=document.createElement('dt'),description=document.createElement('dd');term.className='col-sm-4';description.className='col-sm-8';term.textContent=label;description.textContent=typeof value==='object'?JSON.stringify(value):String(value);audit.append(term,description);});
        auditWrap.classList.toggle('d-none',!audit.children.length);
        const lineWrap=document.getElementById('dv-detail-lines-wrap'), lineBody=document.getElementById('dv-detail-lines');
        lineBody.replaceChildren();
        (row.lines||[]).forEach(line=>{const tr=document.createElement('tr');[line.account,line.description,Number(line.debit||0).toLocaleString(),Number(line.credit||0).toLocaleString()].forEach((value,index)=>{const td=document.createElement('td');td.textContent=value||'—';if(index>1)td.className='text-right';tr.append(td);});lineBody.append(tr);});
        lineWrap.classList.toggle('d-none',!(row.lines||[]).length);
        const printsWrap=document.getElementById('dv-detail-prints-wrap'), prints=document.getElementById('dv-detail-prints');
        prints.replaceChildren();
        (row.prints||[]).forEach(item=>{const link=document.createElement('a');link.className='btn btn-sm btn-outline-primary';link.href=item.url;link.target='_blank';link.rel='noopener';link.textContent=item.label;prints.append(link);});
        printsWrap.classList.toggle('d-none',!(row.prints||[]).length);
        const printLink=document.getElementById('dv-detail-print'), noPrint=document.getElementById('dv-no-print');
        printLink.classList.toggle('d-none',!row.url); noPrint.classList.toggle('d-none',!!row.url);
        if(row.url) printLink.href=row.url;
        if(window.jQuery) window.jQuery('#dv-detail-modal').modal('show');
    });
    load();
})();
</script>
@endsection
