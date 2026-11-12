@extends('admin.layouts.master')
@section('title', $kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher')
@section('content')
<style>
/* Bank Transfer screen is rendered in a custom .main-content wrapper, not
   AdminLTE .content-wrapper. Keep offsets and navbar clearance page-specific. */
body:has(#bankTransferApp) .main-content.page-content {
    margin-left:250px !important;
    width:calc(100% - 250px) !important;
    max-width:calc(100% - 250px);
    box-sizing:border-box;
    min-width:0;
    padding:76px 24px 32px !important;
    overflow-x:hidden;
}
body.sidebar-collapse:has(#bankTransferApp) .main-content.page-content {
    margin-left:4.6rem !important;
    width:calc(100% - 4.6rem) !important;
    max-width:calc(100% - 4.6rem);
}
@media(max-width:991.98px){
    body:has(#bankTransferApp) .main-content.page-content,
    body.sidebar-collapse:has(#bankTransferApp) .main-content.page-content {
        margin-left:0 !important;width:100% !important;max-width:100%;
        padding:74px 12px 24px !important;
    }
}
#bankTransferApp {
    --bt-ink:#172840;--bt-muted:#64748b;--bt-line:#dce5f1;
    color:var(--bt-ink);max-width:1450px;min-width:0;
    margin:0 auto;padding:0 !important;
}
#bankTransferApp .bt-hero {
    padding:24px 28px;border-radius:16px;
    background:linear-gradient(115deg,#102440,#19569a 70%,#2876c5);
    color:#fff;margin-bottom:20px;box-shadow:0 8px 22px #182e4b1a;
}
#bankTransferApp .bt-hero h2 {font-weight:700;font-size:clamp(1.35rem,2.2vw,1.85rem);line-height:1.3}
#bankTransferApp .bt-hero p {color:#e1edfc;margin:6px 0 0}
#bankTransferApp .bt-panel {
    display:grid;grid-template-columns:minmax(0,1.3fr) minmax(330px,.85fr);
    align-items:start;gap:20px;
}
#bankTransferApp .bt-card {
    min-width:0;background:#fff;border:1px solid var(--bt-line);
    border-radius:15px;box-shadow:0 6px 24px #1c3c620b;
    overflow:hidden;
}
#bankTransferApp .bt-card h5 {
    margin:0;font-size:1rem;font-weight:700;
    padding:17px 22px;border-bottom:1px solid var(--bt-line);
}
#bankTransferApp .bt-body {padding:22px}
#bankTransferApp .bt-body label {
    font-weight:600;display:block;color:#263c55;margin-bottom:7px;
}
#bankTransferApp .bt-body .form-control {
    border:1px solid #ccd9e7;border-radius:9px;
    min-height:43px;width:100%;box-shadow:none;
}
#bankTransferApp .bt-body .form-control:focus {
    border-color:#3381da;box-shadow:0 0 0 3px #3381da1a;
}
#bankTransferApp .bt-body textarea.form-control {min-height:92px}
#bankTransferApp .bt-body .form-group {margin-bottom:18px}
#bankTransferApp .bt-post {
    min-height:44px;border-radius:9px;font-weight:600;padding:10px 22px;
}
#bankTransferApp .bt-scroll {overflow:auto;max-height:460px}
#bankTransferApp table {font-size:13px;margin:0;min-width:560px}
#bankTransferApp .bt-scroll thead th {
    position:sticky;top:0;z-index:1;background:#eef4fb;
    white-space:nowrap;color:#39506d;font-weight:700;
    padding:13px 12px;border-top:0;
}
#bankTransferApp .bt-scroll tbody td {padding:14px 12px;vertical-align:middle}
#bankTransferApp .bt-empty-note {padding:48px 20px;text-align:center;color:var(--bt-muted)}
#bankTransferApp .bt-muted {color:var(--bt-muted)}
#bankTransferApp .bt-account-alert {
    display:block;font-size:12px;line-height:1.5;margin:8px 0 0;
    padding:9px 11px;border:1px solid #f6d6b3;
    background:#fffbeb;color:#915100;border-radius:8px;
}
#bankTransferApp .bt-account-count {font-size:12px;color:var(--bt-muted);font-weight:400}
@media(max-width:1199.98px){
    #bankTransferApp .bt-panel {grid-template-columns:minmax(0,1fr)}
}
@media(max-width:575.98px){
    #bankTransferApp .bt-hero {padding:19px}
    #bankTransferApp .bt-body {padding:17px}
    #bankTransferApp .bt-card h5 {padding:15px 17px}
}
</style>
<div class="container-fluid bt-page" id="bankTransferApp" data-kind="{{ $kind }}" data-post-url="{{ route('finance.voucher.transfer.post',['kind'=>$kind]) }}" data-history-url="{{ route('finance.voucher.transfer.history',['kind'=>$kind]) }}" data-source-url="{{ route('finance.voucher.transfer.sources',['kind'=>$kind]) }}" data-print-base="{{ url('admin/finance/voucher/bank-transfer/'.$kind) }}">
 <header class="bt-hero"><div class="d-flex justify-content-between align-items-center flex-wrap"><div><h2 class="mb-0">{{ $kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher' }}</h2><p>Project #{{ (int)getSelectedTown() }} · Balanced cash and bank posting</p></div><span class="badge badge-light">Isolated finance pilot</span></div></header>
 <div class="bt-panel">
  <div><section class="bt-card"><h5>{{ $kind === 'deposit' ? 'Deposit details' : 'Withdrawal details' }} <span class="bt-account-count">· Project #{{ (int)getSelectedTown() }}</span></h5><div class="bt-body">
   @if($kind === 'withdrawal')<div class="form-group"><label>Cleared cheque (optional)</label><select id="chequeSource" class="form-control"><option value="">Manual cash withdrawal</option></select><small class="bt-muted">Selecting a verified cleared cheque fixes its amount and links its reference.</small></div>@endif
   <div class="form-row"><div class="form-group col-md-6"><label>Date</label><input id="voucherDate" type="date" class="form-control" value="{{ now()->toDateString() }}"></div><div class="form-group col-md-6"><label>Amount</label><input id="voucherAmount" type="number" min="0.01" step="0.01" class="form-control" placeholder="0.00"></div></div>
   <div class="form-group"><label>Cash account <span class="bt-account-count">({{ $cashAccounts->count() }} available)</span></label><select id="cashAccount" class="form-control"><option value="">Choose a cash account</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }}{{ $account->subheadAccounting?->name ? ' / '.$account->subheadAccounting->name : '' }}</option>@endforeach</select>@if($cashAccounts->isEmpty())<small class="bt-account-alert">No active Cash account mapping for this project. Configure an active Cash head/child-account mapping in the project COA before posting.</small>@endif</div>
   <div class="form-group"><label>Bank account <span class="bt-account-count">({{ $bankAccounts->count() }} available)</span></label><select id="bankAccount" class="form-control"><option value="">Choose a bank account</option>@foreach($bankAccounts as $bank)<option value="{{ $bank->id }}">{{ $bank->bank_name }} · {{ $bank->account_title }}{{ $bank->branch ? ' · '.$bank->branch : '' }}</option>@endforeach</select>@if($bankAccounts->isEmpty())<small class="bt-account-alert">No active project bank account is configured. Check Project Bank Account Settings.</small>@endif</div>
   <div class="form-group"><label>Reference number</label><input id="voucherReference" class="form-control" maxlength="120" placeholder="Optional reference"></div>
   <div class="form-group"><label>Description</label><textarea id="voucherDescription" class="form-control" rows="3" maxlength="2000" placeholder="Reason / description"></textarea></div>
   <button id="saveTransfer" class="btn btn-primary bt-post" disabled>{{ $kind === 'deposit' ? 'Post bank deposit' : 'Post bank withdrawal' }}</button>
   <small class="d-block bt-muted mt-2">{{ $kind === 'deposit' ? 'Bank is debited; cash is credited.' : 'Cash is debited; bank is credited.' }} Existing records are not overwritten.</small>
  </div></section></div>
  <div><section class="bt-card"><h5>Recent {{ $kind }} vouchers <span class="bt-account-count">· Latest activity</span></h5><div class="bt-scroll"><table class="table table-hover mb-0"><thead><tr><th>Voucher / Date</th><th>Cash</th><th>Bank account</th><th>Cheque reference</th><th class="text-right">Amount</th><th></th></tr></thead><tbody id="historyRows"><tr><td colspan="6" class="text-center bt-muted p-4">Loading vouchers…</td></tr></tbody></table></div></section></div>
 </div>
</div>

@endsection

@section('js')
<script>
(()=>{
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
  const root=$('#bankTransferApp')[0];if(!root)return;const byId=id=>$('#'+$.escapeSelector(id))[0],esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));let busy=false;
 const toast=(icon,title,text)=>window.Swal?Swal.fire({icon,title,text}):alert(title+': '+text);
 function valid(){const amount=Number(byId('voucherAmount').value);const ok=!!byId('voucherDate').value&&Number.isFinite(amount)&&amount>0&&!!byId('cashAccount').value&&!!byId('bankAccount').value&&!!byId('voucherDescription').value.trim();byId('saveTransfer').disabled=!ok||busy;return ok}
 async function loadHistory(){const body=byId('historyRows');body.innerHTML='<tr><td colspan="6" class="text-center p-4">Loading vouchers…</td></tr>';try{const r=await requestJson(root.dataset.historyUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load vouchers');body.innerHTML=(j.data||[]).length?j.data.map(x=>`<tr><td><b>${esc(x.voucher)}</b><br><small>${esc(x.date)}</small></td><td>${esc(x.cash_account)}</td><td>${esc(x.bank)}</td><td>${esc(x.cheque||'—')}</td><td class="text-right">${esc(x.amount)}</td><td><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${esc(root.dataset.printBase+'/'+x.posting_batch_id+'/print')}">Print</a></td></tr>`).join(''):'<tr><td colspan="6" class="text-center bt-muted p-4">No {{ $kind }} vouchers posted yet.</td></tr>'}catch(e){body.innerHTML=`<tr><td colspan="6" class="text-danger p-3">${esc(e.message)}</td></tr>`}}
 @if($kind === 'withdrawal')
 async function loadCheques(){try{const r=await requestJson(root.dataset.sourceUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load eligible cheques');byId('chequeSource').innerHTML='<option value="">Manual cash withdrawal</option>'+(j.data||[]).map(x=>`<option value="${x.id}" data-amount="${esc(x.amount)}" data-date="${esc(x.date)}" data-ref="${esc(x.reference)}" data-voucher="${esc(x.voucher)}">${esc(x.voucher)} · ${esc(x.party)} · ${esc(x.amount)} · ${esc(x.reference)}</option>`).join('')}catch(e){toast('error','Cheque list failed',e.message)}}
 $('#chequeSource').on('change',()=>{const o=byId('chequeSource').selectedOptions[0],linked=!!o.value;byId('voucherAmount').value=linked?o.dataset.amount:'';byId('voucherAmount').readOnly=linked;if(linked){byId('voucherDate').value=o.dataset.date;byId('voucherReference').value=o.dataset.ref;byId('voucherDescription').value=`Withdrawal against ${o.dataset.voucher} cheque ${o.dataset.ref}`}valid()});loadCheques();
 @endif
 ['voucherDate','voucherAmount','cashAccount','bankAccount','voucherReference','voucherDescription'].forEach(id=>$('#'+$.escapeSelector(id)).on('input',valid));
 $('#saveTransfer').on('click',async()=>{if(!valid()||busy)return;busy=true;valid();const o=byId('chequeSource')?.selectedOptions[0];const payload={date:byId('voucherDate').value,amount:Number(byId('voucherAmount').value).toFixed(2),cash_account_id:Number(byId('cashAccount').value),bank_account_id:Number(byId('bankAccount').value),reference:byId('voucherReference').value,description:byId('voucherDescription').value,request_key:(crypto.randomUUID?crypto.randomUUID().replaceAll('-',''):'transfer'+Date.now()+'request').slice(0,32),cheque_source_id:o?.value?Number(o.value):null};try{const r=await requestJson(root.dataset.postUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},body:JSON.stringify(payload)}),j=await r.json();if(!r.ok)throw Error(j.message||'Voucher was rejected');toast('success','Voucher posted',`Posting batch #${j.data.posting_batch_id}`);byId('voucherAmount').value='';byId('voucherReference').value='';byId('voucherDescription').value='';@if($kind === 'withdrawal')byId('chequeSource').value='';byId('voucherAmount').readOnly=false;loadCheques();@endif await loadHistory()}catch(e){toast('error','Post failed',e.message)}finally{busy=false;valid()}});loadHistory();valid()})();
</script>
@endsection
