@extends('admin.layouts.master')

@section('content')
<style>
    html.daily-voucher-directory-active,body.daily-voucher-directory-active{height:100%;overflow:hidden!important}
    body.daily-voucher-directory-active .main-footer{display:none!important}
    .content-wrapper.daily-voucher-page{height:calc(100vh - 57px)!important;min-height:0!important;overflow:hidden!important;display:flex;flex-direction:column;padding:.45rem .75rem!important}
    .daily-voucher-page .content-header{flex:0 0 auto;padding:.15rem 0 .35rem}
    .daily-voucher-page>.content{flex:1 1 auto;min-height:0;overflow:hidden;padding:0}
    .daily-voucher-page>.content>.container-fluid{height:100%;min-height:0;display:flex;flex-direction:column;padding:0}
    .daily-voucher-card{border:0;border-radius:14px;box-shadow:0 8px 24px rgba(16,36,64,.08);overflow:hidden}
    .daily-voucher-filters{display:grid;grid-template-columns:repeat(5,minmax(115px,1fr)) auto;gap:.6rem;align-items:end}
    .daily-voucher-actions{display:flex;gap:.4rem;align-items:center;white-space:nowrap}
    .daily-voucher-shortcuts{display:flex;flex-wrap:wrap;gap:.5rem}
    .daily-voucher-table-card{flex:1 1 0;min-height:0;display:flex;flex-direction:column;margin-bottom:0!important}
    .daily-voucher-table-wrap{flex:1 1 0;min-height:0;overflow:auto}.daily-voucher-table{min-width:1250px;table-layout:fixed}
    .daily-voucher-table th:nth-child(1){width:105px}.daily-voucher-table th:nth-child(2){width:145px}.daily-voucher-table th:nth-child(3){width:190px}.daily-voucher-table th:nth-child(4){width:220px}.daily-voucher-table th:nth-child(5){width:115px}.daily-voucher-table th:nth-child(6){width:100px}.daily-voucher-table th:nth-child(7){width:230px}.daily-voucher-table th:nth-child(8){width:220px}
    .daily-voucher-table thead th{position:sticky;top:0;z-index:2;background:#edf4fc}.daily-voucher-table td{vertical-align:middle}
    .daily-voucher-table thead .dv-column-filters th{top:38px;background:#fff;z-index:3;padding:.3rem}
    .daily-voucher-table thead .dv-column-filters input{min-width:72px;font-size:.75rem;padding:.25rem .4rem;height:30px}
    .daily-voucher-muted{color:#70819a;font-size:.85rem}.daily-voucher-loading{opacity:.48;pointer-events:none}
    @media(max-width:1200px){.daily-voucher-filters{grid-template-columns:repeat(3,minmax(115px,1fr))}.daily-voucher-actions{grid-column:1/-1;justify-content:flex-end}}
    @media(max-width:900px){.daily-voucher-filters{grid-template-columns:repeat(2,minmax(130px,1fr))}.daily-voucher-actions{grid-column:1/-1;justify-content:flex-end}}
    @media(max-width:520px){.daily-voucher-filters{grid-template-columns:1fr}.daily-voucher-page .daily-voucher-actions{grid-column:1;justify-content:flex-end}.content-wrapper.daily-voucher-page{padding:.35rem!important}}
</style>
<div class="content-wrapper daily-voucher-page">
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
                <div><label for="dv-type">Voucher type</label><select id="dv-type" name="type" class="form-control"><option value="">All types</option>@foreach(['CR','DR','CCR','CDR','BOR','BOP','CP','PPR','JV','CV','D','C','BR','BO','BW','BD','PR','PP'] as $type)<option value="{{ $type }}" @selected($presetType === $type)>{{ $type }}</option>@endforeach</select></div>
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
            <div class="daily-voucher-table-wrap" id="dv-table-wrap"><table class="table table-sm table-hover mb-0 daily-voucher-table"><thead>
                <tr><th>Date</th><th>Voucher #</th><th>Reference</th><th>Account / Customer</th><th class="text-right">Amount</th><th>Status</th><th>Details</th><th>Actions</th></tr>
                <tr class="dv-column-filters">
                    @foreach(['date'=>'Date','number'=>'Voucher #','reference'=>'Reference','account'=>'Account / Customer','amount'=>'Amount','status'=>'Status','detail'=>'Details'] as $key=>$label)
                        <th><input type="search" class="form-control form-control-sm dv-column-filter" data-column="{{$key}}" aria-label="Filter {{$label}}" placeholder="Filter"></th>
                    @endforeach
                    <th></th>
                </tr>
            </thead><tbody id="dv-rows"><tr><td colspan="8" class="text-center p-4">Loading voucher history…</td></tr></tbody></table></div>
            <div class="card-footer d-flex justify-content-end align-items-center"><div><button type="button" class="btn btn-sm btn-outline-secondary" id="dv-prev">Previous</button> <button type="button" class="btn btn-sm btn-outline-secondary" id="dv-next">Next</button></div></div>
        </div>
    </div></section>
</div>
<div class="modal fade" id="dv-detail-modal" tabindex="-1" role="dialog" aria-labelledby="dv-detail-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="dv-detail-title">Voucher details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><dl class="row mb-0" id="dv-detail-fields"></dl><div id="dv-detail-lines-wrap" class="mt-3 d-none"><h6>Voucher lines</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Account</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody id="dv-detail-lines"></tbody></table></div></div><div id="dv-detail-prints-wrap" class="mt-3 d-none"><h6>Plot receipt print views</h6><div id="dv-detail-prints" class="d-flex flex-wrap" style="gap:.5rem"></div></div><div id="dv-detail-audit-wrap" class="mt-3 d-none"><h6>Payment and audit information</h6><dl class="row mb-0" id="dv-detail-audit"></dl></div></div><div class="modal-footer"><a id="dv-detail-print" class="btn btn-primary" target="_blank" rel="noopener"><i class="fas fa-print mr-1"></i> Open print view</a><span id="dv-no-print" class="small text-muted d-none">No posted print record is available.</span><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div></div></div></div>
<div class="modal fade" id="dv-entry-modal" tabindex="-1" role="dialog" aria-labelledby="dv-entry-title" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header py-2"><h5 class="modal-title" id="dv-entry-title">New voucher</h5><div id="dv-bank-method-wrap" class="ml-auto mr-3 d-none"><label class="small mb-0 mr-2" for="dv-bank-method">Payment</label><select id="dv-bank-method" class="form-control form-control-sm d-inline-block" style="width:auto"><option value="online">Online</option><option value="cheque">Cheque</option></select></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body p-0 position-relative"><div id="dv-entry-loader" class="d-flex align-items-center justify-content-center flex-column text-muted" style="position:absolute;inset:0;z-index:2;background:#fff;min-height:220px"><div id="dv-entry-spinner" class="spinner-border text-primary mb-2" role="status"></div><span id="dv-entry-loader-text">Loading voucher form...</span><a id="dv-entry-fallback" class="btn btn-sm btn-outline-primary mt-3 d-none" target="_blank" rel="noopener">Open form in new tab</a></div><iframe id="dv-entry-frame" title="Voucher entry" style="display:block;width:100%;height:78vh;border:0"></iframe></div></div></div></div>
<script>
(() => {
    document.documentElement.classList.add('daily-voucher-directory-active');
    document.body.classList.add('daily-voucher-directory-active');
    const form=document.getElementById('daily-voucher-filters'), body=document.getElementById('dv-rows'), wrap=document.getElementById('dv-table-wrap');
    let start=0, length=25, filtered=0, timer;
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    async function load(){
        wrap.classList.add('daily-voucher-loading'); const params=new URLSearchParams(new FormData(form)); params.set('start',start); params.set('length',length);
        document.querySelectorAll('.dv-column-filter').forEach(input=>{if(input.value.trim())params.set('column_'+input.dataset.column,input.value.trim());});
        try {
            const response=await fetch(@json(route('finance.daily-vouchers.data'))+'?'+params.toString(),{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if(!response.ok) throw new Error('Unable to load voucher history.');
            const result=await response.json(); filtered=Number(result.recordsFiltered||0);
            body.innerHTML=result.data.length?result.data.map(row=>`<tr><td>${esc(row.date)}</td><td class="font-weight-bold">${esc(row.number)}</td><td>${esc(row.reference)||'—'}</td><td>${esc(row.account)||'—'}</td><td class="text-right">${Number(row.amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td><td>${esc(row.status)}</td><td class="text-truncate" style="max-width:260px" title="${esc(row.detail)}">${esc(row.detail)||'—'}</td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary dv-details" data-row="${esc(JSON.stringify(row))}">Details</button> ${row.edit_url?`<a class="btn btn-sm btn-outline-primary" href="${esc(row.edit_url)}"><i class="fas fa-edit"></i> Edit</a>`:''} ${row.delete_url?`<button type="button" class="btn btn-sm btn-outline-danger dv-delete" data-url="${esc(row.delete_url)}" data-number="${esc(row.number)}"><i class="fas fa-trash-alt"></i> Delete</button>`:''} ${row.url?`<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${esc(row.url)}" aria-label="Print ${esc(row.number)}"><i class="fas fa-print"></i></a>`:'<span class="small text-muted">—</span>'}</td></tr>`).join(''):'<tr><td colspan="8" class="text-center text-muted p-4">No vouchers match these filters.</td></tr>';
        } catch(error){body.innerHTML=`<tr><td colspan="8" class="text-center text-danger p-4">${esc(error.message)}</td></tr>`;}
        finally{wrap.classList.remove('daily-voucher-loading');}
    }
    form.addEventListener('submit',event=>{event.preventDefault();start=0;load();});
    document.getElementById('dv-prev').addEventListener('click',()=>{start=Math.max(0,start-length);load();});
    document.getElementById('dv-next').addEventListener('click',()=>{if(start+length<filtered){start+=length;load();}});
    document.getElementById('dv-search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{start=0;load();},300);});
    document.querySelectorAll('.dv-column-filter').forEach(input=>input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{start=0;load();},250);}));
    const entryFrame=document.getElementById('dv-entry-frame'), entryLoader=document.getElementById('dv-entry-loader');
    const loaderText=document.getElementById('dv-entry-loader-text'), fallback=document.getElementById('dv-entry-fallback'), entrySpinner=document.getElementById('dv-entry-spinner');
    let entryLoadTimer, entryFormCheck, requestedEntryUrl='';
    function clearEntryTimers(){clearTimeout(entryLoadTimer);clearInterval(entryFormCheck);}
    function entryReady(){
        clearEntryTimers();
        entryLoader.classList.add('d-none');
    }
    function showEntryError(message,url=requestedEntryUrl){
        loaderText.textContent=message;
        entrySpinner.classList.add('d-none');
        fallback.href=url;
        fallback.classList.remove('d-none');
        entryLoader.classList.remove('d-none');
    }
    function loadEntry(url){
        if(!url){showEntryError('This voucher type does not have an entry form configured.');return;}
        requestedEntryUrl=url;
        entryLoader.classList.remove('d-none');
        entrySpinner.classList.remove('d-none'); loaderText.textContent='Loading voucher form...'; fallback.classList.add('d-none'); fallback.href=url;
        clearEntryTimers();
        entryLoadTimer=setTimeout(()=>{clearInterval(entryFormCheck);showEntryError('The voucher form is taking too long. Open it in a new tab to see the error or continue.');},12000);
        entryFrame.src=url;
        // The embedded voucher page loads many scripts from the main layout.
        // Its window load event can be delayed even though the form is already
        // in the document, so detect the actual form independently.
        entryFormCheck=setInterval(()=>{
            if(requestedEntryUrl!==url)return clearInterval(entryFormCheck);
            try {
                if(entryFrame.contentDocument?.querySelector('#voucherForm')){
                    entryReady();
                }
            } catch(error) {
                // Keep waiting for the same-origin document to become readable.
            }
        },150);
    }
    entryFrame.addEventListener('load',()=>{
        if(!requestedEntryUrl || entryFrame.src==='about:blank')return;
        clearEntryTimers();
        try {
            const doc=entryFrame.contentDocument;
            if(doc?.querySelector('#voucherForm'))entryReady();
            else showEntryError('Voucher form is unavailable (permission denied, redirect, or server error). Open the form in a new tab to see the response.');
        } catch(error) {
            showEntryError('Cannot access the voucher form inside this window. Open the form in a new tab.');
        }
    });
    document.querySelectorAll('.dv-create').forEach(button=>button.addEventListener('click',()=>{
        document.getElementById('dv-entry-title').textContent=button.dataset.title;
        const methodWrap=document.getElementById('dv-bank-method-wrap');
        methodWrap.classList.toggle('d-none',!button.dataset.chequeUrl);
        document.getElementById('dv-bank-method').value='online';
        methodWrap.dataset.onlineUrl=button.dataset.url;
        methodWrap.dataset.chequeUrl=button.dataset.chequeUrl;
        // Start loading directly; relying only on Bootstrap's shown event can
        // leave the overlay spinning when the modal event is missed.
        loadEntry(button.dataset.url);
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('show');
    }));
    document.getElementById('dv-bank-method').addEventListener('change',event=>{
        const methodWrap=document.getElementById('dv-bank-method-wrap');
        loadEntry(event.target.value==='cheque'?methodWrap.dataset.chequeUrl:methodWrap.dataset.onlineUrl);
    });
    document.getElementById('dv-create-voucher').addEventListener('click',()=>{
        const selected=document.getElementById('dv-type').value;
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
        if(dedicatedRoutes[selected]){
            window.location.assign(dedicatedRoutes[selected]);
            return;
        }
        const series=seriesMap[selected];
        const target=series ? document.querySelector(`.dv-create[data-series="${series}"]`) : null;
        if(!target){
            requestedEntryUrl='';
            entryFrame.src='about:blank';
            showEntryError('This voucher type uses a separate entry workflow. Use its dedicated page to create a voucher.', '');
            fallback.classList.add('d-none');
            if(window.jQuery)window.jQuery('#dv-entry-modal').modal('show');
            return;
        }
        target.click();
    });
    if(window.jQuery){window.jQuery('#dv-entry-modal').on('hidden.bs.modal',()=>{clearEntryTimers();requestedEntryUrl='';entryLoader.classList.add('d-none');entryFrame.src='about:blank';});}
    window.addEventListener('message',event=>{
        if(event.origin!==window.location.origin || event.source!==entryFrame.contentWindow)return;
        if(event.data?.type==='daily-voucher-form-ready'){
            if(requestedEntryUrl)entryReady();
            return;
        }
        if(event.data?.type!=='daily-voucher-saved')return;
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('hide');
        document.getElementById('dv-entry-frame').src='about:blank';start=0;load();
    });
    body.addEventListener('click',event=>{
        const button=event.target.closest('.dv-delete');
        if(!button)return;
        const reason=window.prompt('Reason for reversing/deleting voucher '+button.dataset.number+' (required):');
        if(!reason || !reason.trim())return;
        if(reason.trim().length>200){window.alert('Reason cannot exceed 200 characters.');return;}
        const effectiveDate=window.prompt('Effective reversal date (YYYY-MM-DD):',new Date().toISOString().slice(0,10));
        if(!effectiveDate || !/^\\d{4}-\\d{2}-\\d{2}$/.test(effectiveDate)){window.alert('Enter a valid date in YYYY-MM-DD format.');return;}
        if(!window.confirm('Confirm reversal request for '+button.dataset.number+'? The finance workflow may create reversing ledger entries.'))return;
        const deletion=document.createElement('form');
        deletion.method='POST';
        deletion.action=button.dataset.url;
        deletion.style.display='none';
        const requestKey=window.crypto?.randomUUID ? window.crypto.randomUUID().replace(/-/g,'') : String(Date.now())+String(Math.random()).slice(2);
        for(const [name,value] of [['_token',@json(csrf_token())],['_method','DELETE'],['reason',reason.trim()],['effective_date',effectiveDate],['request_key',requestKey]]){
            const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;deletion.appendChild(input);
        }
        document.body.appendChild(deletion);deletion.submit();
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
