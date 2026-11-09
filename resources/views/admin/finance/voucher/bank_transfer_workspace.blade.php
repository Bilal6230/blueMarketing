@extends('admin.layouts.master')
@section('title', $kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher')
@section('content')
<style>
 .bt-page{--ink:#14243b;--muted:#667991;--line:#dce6f2;color:var(--ink)}.bt-hero{padding:20px 24px;border-radius:15px;background:linear-gradient(120deg,#12243d,#1769e0);color:white;margin-bottom:16px}.bt-hero p{margin:4px 0 0;color:#dceaff}.bt-card{background:white;border:1px solid var(--line);border-radius:13px;box-shadow:0 6px 20px #152a4510;overflow:hidden}.bt-card h5{font-size:15px;font-weight:700;margin:0;padding:14px 17px;border-bottom:1px solid var(--line)}.bt-body{padding:16px}.bt-scroll{max-height:360px;overflow:auto}.bt-scroll thead th{position:sticky;top:0;background:#edf4fc}.bt-page table{font-size:13px}.bt-page .form-control{border-radius:8px}.bt-page tr[data-id]{cursor:pointer}.bt-page tr.selected{background:#e7f1ff}.bt-muted{color:var(--muted)}
</style>
<div class="container-fluid bt-page" id="bankTransferApp" data-kind="{{ $kind }}" data-post-url="{{ route('finance.voucher.transfer.post',['kind'=>$kind]) }}" data-history-url="{{ route('finance.voucher.transfer.history',['kind'=>$kind]) }}" data-source-url="{{ route('finance.voucher.transfer.sources',['kind'=>$kind]) }}" data-print-base="{{ url('admin/finance/voucher/bank-transfer/'.$kind) }}">
 <header class="bt-hero"><div class="d-flex justify-content-between align-items-center flex-wrap"><div><h2 class="mb-0">{{ $kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher' }}</h2><p>Project #{{ (int)getSelectedTown() }} · Balanced cash and bank posting</p></div><span class="badge badge-light">Isolated finance pilot</span></div></header>
 <div class="row">
  <div class="col-xl-5 mb-3"><section class="bt-card"><h5>{{ $kind === 'deposit' ? 'Deposit details' : 'Withdrawal details' }}</h5><div class="bt-body">
   @if($kind === 'withdrawal')<div class="form-group"><label>Cleared cheque (optional)</label><select id="chequeSource" class="form-control"><option value="">Manual cash withdrawal</option></select><small class="bt-muted">Selecting a verified cleared cheque fixes its amount and links its reference.</small></div>@endif
   <div class="form-row"><div class="form-group col-md-6"><label>Date</label><input id="voucherDate" type="date" class="form-control" value="{{ now()->toDateString() }}"></div><div class="form-group col-md-6"><label>Amount</label><input id="voucherAmount" type="number" min="0.01" step="0.01" class="form-control" placeholder="0.00"></div></div>
   <div class="form-group"><label>Cash account</label><select id="cashAccount" class="form-control"><option value="">Select active cash account</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }}{{ $account->subheadAccounting?->name ? ' / '.$account->subheadAccounting->name : '' }}</option>@endforeach</select>@if($cashAccounts->isEmpty())<small class="text-danger">No active project COA account with “cash” in its head name was found.</small>@endif</div>
   <div class="form-group"><label>Bank account</label><select id="bankAccount" class="form-control"><option value="">Select active bank account</option>@foreach($bankAccounts as $bank)<option value="{{ $bank->id }}">{{ $bank->bank_name }} · {{ $bank->account_title }}{{ $bank->branch ? ' · '.$bank->branch : '' }}</option>@endforeach</select></div>
   <div class="form-group"><label>Reference number</label><input id="voucherReference" class="form-control" maxlength="120" placeholder="Optional reference"></div>
   <div class="form-group"><label>Description</label><textarea id="voucherDescription" class="form-control" rows="3" maxlength="2000" placeholder="Reason / description"></textarea></div>
   <button id="saveTransfer" class="btn btn-primary" disabled>{{ $kind === 'deposit' ? 'Post bank deposit' : 'Post bank withdrawal' }}</button>
   <small class="d-block bt-muted mt-2">{{ $kind === 'deposit' ? 'Bank is debited; cash is credited.' : 'Cash is debited; bank is credited.' }} Existing records are not overwritten.</small>
  </div></section></div>
  <div class="col-xl-7 mb-3"><section class="bt-card"><h5>Recent {{ $kind }} vouchers</h5><div class="bt-scroll"><table class="table table-hover mb-0"><thead><tr><th>Voucher / Date</th><th>Cash</th><th>Bank account</th><th>Cheque reference</th><th class="text-right">Amount</th><th></th></tr></thead><tbody id="historyRows"><tr><td colspan="6" class="text-center bt-muted p-4">Loading vouchers…</td></tr></tbody></table></div></section></div>
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
 async function loadHistory(){const body=byId('historyRows');body.innerHTML='<tr><td colspan="6" class="text-center p-4">Loading vouchers…</td></tr>';try{const r=await requestJson(root.dataset.historyUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load vouchers');body.innerHTML=(j.data||[]).length?j.data.map(x=>`<tr><td><b>${esc(x.voucher)}</b><br><small>${esc(x.date)}</small></td><td>${esc(x.cash_account)}</td><td>${esc(x.bank)}</td><td>${esc(x.cheque||'—')}</td><td class="text-right">${esc(x.amount)}</td><td><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${esc(root.dataset.printBase+'/'+x.posting_batch_id+'/print')}">Print</a></td></tr>`).join(''):'<tr><td colspan="6" class="text-center bt-muted p-4">No vouchers posted yet.</td></tr>'}catch(e){body.innerHTML=`<tr><td colspan="6" class="text-danger p-3">${esc(e.message)}</td></tr>`}}
 @if($kind === 'withdrawal')
 async function loadCheques(){try{const r=await requestJson(root.dataset.sourceUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load eligible cheques');byId('chequeSource').innerHTML='<option value="">Manual cash withdrawal</option>'+(j.data||[]).map(x=>`<option value="${x.id}" data-amount="${esc(x.amount)}" data-date="${esc(x.date)}" data-ref="${esc(x.reference)}" data-voucher="${esc(x.voucher)}">${esc(x.voucher)} · ${esc(x.party)} · ${esc(x.amount)} · ${esc(x.reference)}</option>`).join('')}catch(e){toast('error','Cheque list failed',e.message)}}
 $('#chequeSource').on('change',()=>{const o=byId('chequeSource').selectedOptions[0],linked=!!o.value;byId('voucherAmount').value=linked?o.dataset.amount:'';byId('voucherAmount').readOnly=linked;if(linked){byId('voucherDate').value=o.dataset.date;byId('voucherReference').value=o.dataset.ref;byId('voucherDescription').value=`Withdrawal against ${o.dataset.voucher} cheque ${o.dataset.ref}`}valid()});loadCheques();
 @endif
 ['voucherDate','voucherAmount','cashAccount','bankAccount','voucherReference','voucherDescription'].forEach(id=>$('#'+$.escapeSelector(id)).on('input',valid));
 $('#saveTransfer').on('click',async()=>{if(!valid()||busy)return;busy=true;valid();const o=byId('chequeSource')?.selectedOptions[0];const payload={date:byId('voucherDate').value,amount:Number(byId('voucherAmount').value).toFixed(2),cash_account_id:Number(byId('cashAccount').value),bank_account_id:Number(byId('bankAccount').value),reference:byId('voucherReference').value,description:byId('voucherDescription').value,request_key:(crypto.randomUUID?crypto.randomUUID().replaceAll('-',''):'transfer'+Date.now()+'request').slice(0,32),cheque_source_id:o?.value?Number(o.value):null};try{const r=await requestJson(root.dataset.postUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},body:JSON.stringify(payload)}),j=await r.json();if(!r.ok)throw Error(j.message||'Voucher was rejected');toast('success','Voucher posted',`Posting batch #${j.data.posting_batch_id}`);byId('voucherAmount').value='';byId('voucherReference').value='';byId('voucherDescription').value='';@if($kind === 'withdrawal')byId('chequeSource').value='';byId('voucherAmount').readOnly=false;loadCheques();@endif await loadHistory()}catch(e){toast('error','Post failed',e.message)}finally{busy=false;valid()}});loadHistory();valid()})();
</script>
@endsection
