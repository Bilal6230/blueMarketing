@extends('admin.layouts.master')
@section('content')
<style>
    .bank-workspace{--ink:#15243a;--muted:#64748b;--line:#dbe5f1;--blue:#1769e0;color:var(--ink)}
    .bank-workspace .hero{background:linear-gradient(120deg,#10243f,#1f5595);color:#fff;border-radius:16px;padding:22px 26px;margin-bottom:18px;box-shadow:0 10px 28px #14294720}
    .bank-workspace .hero p{margin:5px 0 0;color:#d5e5ff}.bank-workspace .panel{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 5px 18px #19304e0b;overflow:hidden}
    .bank-workspace .panel-head{padding:15px 18px;border-bottom:1px solid var(--line);font-weight:700;display:flex;justify-content:space-between;align-items:center}
    .bank-workspace .panel-body{padding:18px}.bank-workspace .table-wrap{max-height:430px;overflow:auto}.bank-workspace table{margin:0;font-size:13px}.bank-workspace thead th{position:sticky;top:0;background:#edf4fc;z-index:1;color:#27405f}
    .bank-workspace tbody tr{cursor:pointer}.bank-workspace tbody tr:hover,.bank-workspace tbody tr.selected{background:#eaf3ff}.bank-workspace .form-control,.bank-workspace select{border-radius:8px;min-height:39px}
    .bank-workspace .allocation-row{display:grid;grid-template-columns:minmax(220px,1fr) 150px 150px 40px;gap:8px;margin:8px 0;align-items:center}
    .bank-workspace .pill{border-radius:99px;background:#edf4fc;padding:5px 10px;font-size:12px;color:#36516f}.bank-workspace .empty{padding:28px;text-align:center;color:var(--muted)}
    @media(max-width:900px){.bank-workspace .allocation-row{grid-template-columns:1fr 1fr}.bank-workspace .allocation-row select{grid-column:1/-1}}
</style>
<div class="container-fluid bm-ui bank-workspace" id="bankVoucherApp" data-direction="{{ $direction }}" data-sources-url="{{ route('finance.voucher.bank.sources',['direction'=>$direction]) }}" data-history-url="{{ route('finance.voucher.bank.history',['direction'=>$direction]) }}" data-print-base="{{ url('admin/finance/voucher/bank/'.$direction) }}" data-settle-base="{{ url('admin/finance/voucher/pending-bank-payments') }}">
    <section class="hero"><div class="d-flex justify-content-between align-items-center flex-wrap"><div><h2 class="mb-0">{{ $direction === 'receipt' ? 'Bank Receipt Voucher' : 'Bank Payment Voucher' }}</h2><p>Link one verified online or cheque source to its exact project bank account and balanced allocations.</p></div><span class="pill">Project #{{ (int) getSelectedTown() }} · Isolated finance pilot</span></div></section>
    <div class="row">
        <div class="col-xl-7 mb-3"><x-finance.voucher-table-card class="panel h-100"><div class="panel-head"><span>Eligible source transactions</span><button type="button" class="btn btn-sm btn-outline-primary" id="refreshSources"><i class="fas fa-sync-alt"></i> Refresh</button></div><div class="panel-body pb-2"><input id="sourceSearch" class="form-control" placeholder="Search voucher, party, transaction or bank"></div><div class="table-wrap"><table class="table table-hover"><thead><tr><th>Voucher / Date</th><th>Party / Method</th><th>Bank / Ref</th><th class="text-right">Amount</th></tr></thead><tbody id="sourceRows"><tr><td colspan="4" class="empty">Loading verified sources…</td></tr></tbody></table></div></x-finance.voucher-table-card></div>
        <div class="col-xl-5 mb-3"><section class="panel"><div class="panel-head"><span>Linked posting</span><span class="pill" id="selectedSourceLabel">Select a source</span></div><div class="panel-body">
            <div class="form-row"><div class="form-group col-md-6"><label>Posting date</label><input id="postingDate" type="date" class="form-control"></div><div class="form-group col-md-6"><label>Exact source amount</label><input id="sourceAmount" class="form-control" readonly></div></div>
            <div class="form-group"><label>Project bank account</label><select id="bankAccount" class="form-control"><option value="">Select active bank account</option>@foreach($bankAccounts as $bank)<option value="{{ $bank->id }}" data-mapping="{{ $bank->account_mapping_id }}">{{ $bank->bank_name }} · {{ $bank->account_title }}{{ $bank->branch ? ' · '.$bank->branch : '' }}</option>@endforeach</select></div>
            <div class="form-group"><label>Source narration / purpose</label><textarea id="postingNote" class="form-control" rows="2" maxlength="1000" placeholder="Required posting note"></textarea></div>
            <div class="d-flex justify-content-between align-items-center mb-2"><strong>Opposite-side allocations</strong><button type="button" id="addAllocation" class="btn btn-sm btn-outline-primary">+ Add allocation</button></div>
            <div id="allocationRows"></div>
            <div class="d-flex justify-content-between border-top pt-2 mt-2"><span>Allocation total</span><strong id="allocationTotal">0.00</strong></div>
            <button id="postBankVoucher" type="button" class="btn btn-primary btn-block mt-3" disabled>{{ $direction === 'receipt' ? 'Confirm & Post Bank Receipt' : 'Confirm & Post Bank Payment' }}</button>
            <small class="text-muted d-block mt-2">Source amount and source record stay unchanged. Posting is project-scoped, balanced, and idempotent.</small>
        </div></section></div>
    </div>
    <x-finance.voucher-table-card class="panel mt-2"><div class="panel-head"><span>Posted {{ $direction === 'receipt' ? 'bank receipts' : 'bank payments' }}</span><button type="button" class="btn btn-sm btn-outline-primary" id="refreshHistory">Refresh history</button></div><div class="table-wrap"><table class="table table-hover"><thead><tr><th>Voucher / Date</th><th>Linked source</th><th>Bank account</th><th>Narration</th><th class="text-right">Amount</th><th>Print</th></tr></thead><tbody id="historyRows"><tr><td colspan="6" class="empty">Loading posted vouchers...</td></tr></tbody></table></div></x-finance.voucher-table-card>
</div>

@endsection

@section('js')
<script>
(() => {
  const requestJson=async(url,options={})=>{
    const headers=options.headers||{};
    try{
      const data=await $.ajax({
        url,method:options.method||'GET',
        data:options.body||undefined,
        contentType:headers['Content-Type']||undefined,
        processData:false,
        headers,
        dataType:'json'
      });
      return {ok:true,status:200,json:async()=>data};
    }catch(xhr){
      return {ok:false,status:xhr.status||0,json:async()=>xhr.responseJSON||{message:xhr.statusText||'Request failed'}};
    }
  };
  
    const root=$('#bankVoucherApp')[0]; if(!root)return;
    const sourceRows=$('#sourceRows')[0], historyRows=$('#historyRows')[0], accountSelect=$('#bankAccount')[0];
    const accounts=@json($accounts->map(fn($a)=>['id'=>$a->id,'head'=>$a->headAccounting?->name,'sub'=>$a->subheadAccounting?->name])->values());
    let sources=[], selected=null, busy=false;
    const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const toast=(icon,title,text)=>{if(window.Swal)Swal.fire({icon,title,text});else alert(title+': '+text)};
    async function loadHistory(){historyRows.innerHTML='<tr><td colspan="6" class="empty">Loading posted vouchers...</td></tr>';try{const r=await requestJson(root.dataset.historyUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const j=await r.json();if(!r.ok)throw Error(j.message||'Could not load posted vouchers.');const rows=j.data||[];historyRows.innerHTML=rows.length?rows.map(v=>`<tr><td><strong>${esc(v.voucher)}</strong><br><small>${esc(v.date)}</small></td><td>${esc(v.source)}<br><small>${esc(v.party||'—')} · ${esc(v.method)}</small></td><td>${esc(v.bank)}<br><small>${esc(v.account_title)}</small></td><td>${esc(v.narration||'—')}</td><td class="text-right font-weight-bold">${esc(v.amount)}</td><td><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${esc(root.dataset.printBase+'/'+v.posting_batch_id+'/print')}">Print</a></td></tr>`).join(''):'<tr><td colspan="6" class="empty">No posted bank vouchers yet.</td></tr>'}catch(e){historyRows.innerHTML='<tr><td colspan="6" class="empty text-danger">'+esc(e.message)+'</td></tr>'}}
    async function loadSources(){sourceRows.innerHTML='<tr><td colspan="4" class="empty">Loading verified sources…</td></tr>';try{const r=await requestJson(root.dataset.sourcesUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const j=await r.json();if(!r.ok)throw Error(j.message||'Could not load bank sources.');sources=j.data||[];renderSources()}catch(e){sourceRows.innerHTML='<tr><td colspan="4" class="empty text-danger">'+esc(e.message)+'</td></tr>'}}
    function renderSources(){const q=$('#sourceSearch')[0].value.trim().toLowerCase();const list=sources.filter(s=>[s.voucher,s.party,s.method,s.bank,s.transaction_number].join(' ').toLowerCase().includes(q));sourceRows.innerHTML=list.length?list.map(s=>`<tr data-id="${s.id}" class="${selected?.id===s.id?'selected':''}"><td><strong>${esc(s.voucher)}</strong><br><small>${esc(s.date)}</small></td><td>${esc(s.party||'—')}<br><small>${esc(s.method)} · ${esc(s.status)}</small></td><td>${esc(s.bank||'—')}<br><small>${esc(s.transaction_number||'—')}</small></td><td class="text-right font-weight-bold">${esc(s.amount)}</td></tr>`).join(''):'<tr><td colspan="4" class="empty">No eligible verified sources in this project.</td></tr>';$(sourceRows).find('tr[data-id]').toArray().forEach(tr=>$(tr).on('click',()=>selectSource(sources.find(s=>s.id===Number(tr.dataset.id)))))}
    function selectSource(s){selected=s;$('#selectedSourceLabel')[0].textContent=s?`${s.voucher} · ${s.method}`:'Select a source';$('#sourceAmount')[0].value=s?.amount||'';$('#postingDate')[0].value=s?.date||new Date().toISOString().slice(0,10);$('#postingNote')[0].value=s?`${s.voucher} ${s.method} transaction ${s.transaction_number||''} · ${s.party||''}`.trim():'';if(!$('.allocation-row')[0])addAllocation();renderSources();validateForm()}
    function addAllocation(){const $row=$('<div>',{'class':'allocation-row'});$row.html(`<select class="form-control alloc-account"><option value="">Select account</option>${accounts.map(a=>`<option value="${a.id}">${esc([a.head,a.sub].filter(Boolean).join(' / '))}</option>`).join('')}</select><input class="form-control alloc-debit" inputmode="decimal" placeholder="Debit" value="0.00"><input class="form-control alloc-credit" inputmode="decimal" placeholder="Credit" value="0.00"><button type="button" class="btn btn-sm btn-outline-danger alloc-remove" aria-label="Remove allocation">×</button>`);$('#allocationRows').append($row);$row.find('input,select').on('input',validateForm);$row.find('.alloc-remove').on('click',()=>{$row.remove();validateForm()});validateForm()}
    function cents(v){const n=Number(String(v).replace(/,/g,''));return Number.isFinite(n)?Math.round(n*100):0}
    function allocationData(){return $('.allocation-row').toArray().map(r=>({account_id:Number($(r).find('.alloc-account')[0].value),debit:cents($(r).find('.alloc-debit')[0].value),credit:cents($(r).find('.alloc-credit')[0].value)}))}
    function validateForm(){const legs=allocationData(),target=cents(selected?.amount||0),bankId=Number(accountSelect.value),bankMap=Number(accountSelect.selectedOptions[0]?.dataset.mapping||0),bankDebit=root.dataset.direction==='receipt';let sumD=0,sumC=0,valid=!!selected&&!!bankId&&!!$('#postingDate')[0].value&&!!$('#postingNote')[0].value.trim()&&legs.length>0;for(const l of legs){sumD+=l.debit;sumC+=l.credit;if(!l.account_id||l.account_id===bankMap||((l.debit>0)===(l.credit>0)))valid=false}const bankLine=bankDebit?{account_id:bankMap,debit:target,credit:0}:{account_id:bankMap,debit:0,credit:target};sumD+=bankLine.debit;sumC+=bankLine.credit;valid=valid&&sumD===sumC&&sumD===target;$('#allocationTotal')[0].textContent=(sumD/100).toFixed(2)+' / '+(target/100).toFixed(2);$('#postBankVoucher')[0].disabled=!valid||busy;return valid}
    $('#addAllocation').on('click',addAllocation);$('#refreshSources').on('click',loadSources);$('#refreshHistory').on('click',loadHistory);$('#sourceSearch').on('input',renderSources);$(accountSelect).on('change',validateForm);['postingDate','postingNote'].forEach(id=>$('#'+$.escapeSelector(id)).on('input',validateForm));
    $('#postBankVoucher').on('click',async()=>{if(!validateForm()||busy)return;const bankMap=Number(accountSelect.selectedOptions[0].dataset.mapping),amount=cents(selected.amount),legs=allocationData().map(l=>({account_id:l.account_id,debit:(l.debit/100).toFixed(2),credit:(l.credit/100).toFixed(2)}));legs.push({account_id:bankMap,debit:((root.dataset.direction==='receipt'?amount:0)/100).toFixed(2),credit:((root.dataset.direction==='payment'?amount:0)/100).toFixed(2)});busy=true;validateForm();try{const r=await requestJson(`${root.dataset.settleBase}/${selected.id}/status`,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},body:JSON.stringify({passing_status:1,request_key:(crypto.randomUUID?crypto.randomUUID().replaceAll('-',''):'bank'+Date.now()+'requestkey').slice(0,32),bank_post_at:$('#postingDate')[0].value,note:$('#postingNote')[0].value.trim(),bank_account_id:Number(accountSelect.value),posting_lines:legs})});const j=await r.json();if(!r.ok)throw Error(j.message||'Voucher posting was rejected.');toast('success','Voucher posted',`${j.data?.posting_batch_id?'Posting batch #'+j.data.posting_batch_id:'Bank transaction linked'}; source was preserved.`);selected=null;$('#allocationRows')[0].innerHTML='';selectSource(null);await Promise.all([loadSources(),loadHistory()])}catch(e){toast('error','Posting failed',e.message)}finally{busy=false;validateForm()}});
    loadSources();loadHistory();
})();
</script>
@endsection
