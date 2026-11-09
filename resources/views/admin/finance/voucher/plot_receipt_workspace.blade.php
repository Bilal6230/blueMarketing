@extends('admin.layouts.master')
@section('title', 'Plot Receive Payment')
@section('content')
<style>
 .pr-page{--ink:#14243b;--muted:#657891;--line:#dce6f2;color:var(--ink)}.pr-hero{padding:20px 24px;border-radius:15px;background:linear-gradient(120deg,#12243d,#1769e0);color:#fff;margin-bottom:16px}.pr-hero p{margin:5px 0 0;color:#dceaff}.pr-card{background:#fff;border:1px solid var(--line);border-radius:13px;box-shadow:0 6px 20px #152a4510;overflow:hidden}.pr-card h5{margin:0;padding:14px 17px;border-bottom:1px solid var(--line);font-size:15px;font-weight:700}.pr-body{padding:16px}.pr-scroll{max-height:390px;overflow:auto}.pr-scroll thead th{position:sticky;top:0;z-index:1;background:#edf4fc}.pr-page table{font-size:13px}.pr-page .form-control{border-radius:8px}.pr-muted{color:var(--muted)}.pr-picked{background:#eaf3ff}.pr-total{font-size:23px;font-weight:750;color:#1769e0}.pr-stat{background:#f4f8fd;border:1px solid var(--line);border-radius:10px;padding:12px}.pr-stat small{display:block;color:var(--muted)}
</style>
<div class="container-fluid pr-page" id="plotReceiptApp"
 data-options-url="{{ route('finance.voucher.plot-receipt.options') }}"
 data-history-url="{{ route('finance.voucher.plot-receipt.history') }}"
 data-post-url="{{ route('finance.voucher.plot-receipt.post') }}"
 data-details-base="{{ url('admin/finance/voucher/plot-receipt') }}"
 data-print-base="{{ url('admin/finance/voucher/plot-receipt') }}">
 <header class="pr-hero"><div class="d-flex justify-content-between align-items-center flex-wrap"><div><h2 class="mb-0">Plot Receive Payment</h2><p>One receipt · allocate it across multiple active plot bookings · Project #{{ (int)getSelectedTown() }}</p></div><span class="badge badge-light">Isolated finance pilot</span></div></header>
 <div id="prAlert" class="alert d-none" role="status"></div>
 <div class="row">
  <div class="col-xl-5 mb-3"><section class="pr-card"><h5>Receipt details</h5><div class="pr-body">
   <div class="form-row"><div class="form-group col-md-6"><label>Receipt number</label><input id="prNumber" class="form-control" maxlength="64" placeholder="e.g. PLOT-RCPT-001"></div><div class="form-group col-md-6"><label>Date</label><input id="prDate" type="date" class="form-control" value="{{ now()->toDateString() }}"></div></div>
   <div class="form-group"><label>Customer</label><select id="prCustomer" class="form-control"><option value="">Loading active bookings…</option></select><small class="pr-muted">Only customers with active bookings and mapped project receivable accounts are available.</small></div>
   <div class="form-group"><label>Receiving account</label><select id="prAccount" class="form-control"><option value="">Select active project account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }}{{ $account->subheadAccounting?->name ? ' / '.$account->subheadAccounting->name : '' }}</option>@endforeach</select></div>
   <div class="form-group"><label>Narration</label><textarea id="prNarration" class="form-control" rows="2" maxlength="2000" placeholder="Payment details"></textarea></div>
   <div class="pr-stat mb-3"><small>Receipt total (allocated)</small><div class="pr-total" id="prTotal">0.00</div><small><span id="prSelected">0</span> plots selected · choose at least 2</small></div>
   <button id="prPost" class="btn btn-primary" disabled><i class="fas fa-check-circle mr-1"></i> Post receipt</button>
   <small class="d-block pr-muted mt-2">Each allocation posts to its own customer/plot receivable. Posted receipts are immutable; corrections use reversal.</small>
  </div></section></div>
  <div class="col-xl-7 mb-3"><section class="pr-card"><h5>Active plot bookings</h5><div class="pr-body pb-2"><input id="prSearch" class="form-control" placeholder="Search plot or customer…"></div><div class="pr-scroll"><table class="table table-hover mb-0"><thead><tr><th></th><th>Plot / Booking</th><th>Customer</th><th>Receivable account</th><th style="width:145px">Allocate amount</th></tr></thead><tbody id="prBookingRows"><tr><td colspan="5" class="text-center pr-muted p-4">Select a customer to load their bookings.</td></tr></tbody></table></div></section></div>
 </div>
 <section class="pr-card"><h5>Recent plot receipts</h5><div class="pr-scroll"><table class="table table-hover mb-0"><thead><tr><th>Receipt / Date</th><th>Customer</th><th class="text-right">Total</th><th>Narration</th><th>Action</th></tr></thead><tbody id="prHistoryRows"><tr><td colspan="5" class="text-center pr-muted p-4">Loading receipts…</td></tr></tbody></table></div></section>
</div>
<div class="modal fade" id="prDetailsModal" tabindex="-1" role="dialog" aria-labelledby="prDetailsTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="prDetailsTitle">Receipt details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div><div class="modal-body" id="prDetailsBody"></div></div></div></div>

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
  const root=$('#plotReceiptApp')[0];if(!root)return;const byId=id=>$('#'+$.escapeSelector(id))[0],esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));let bookings=[],busy=false;
 const alert=(kind,msg)=>{const box=byId('prAlert');box.className='alert alert-'+kind;box.textContent=msg;$(box).removeClass('d-none');box.scrollIntoView({behavior:'smooth',block:'nearest'})};
 const money=n=>(Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
 function selected(){return $('.pr-check:checked').toArray().map(c=>bookings.find(x=>x.booking_id===Number(c.value))).filter(Boolean)}
 function total(){const rows=selected(),sum=rows.reduce((s,x)=>s+(Number($(`[data-amount="${x.booking_id}"]`)[0]?.value)||0),0);byId('prTotal').textContent=money(sum);byId('prSelected').textContent=rows.length;const valid=rows.length>=2&&rows.every(x=>Number($(`[data-amount="${x.booking_id}"]`)[0]?.value)>0)&&!!byId('prNumber').value.trim()&&!!byId('prDate').value&&!!byId('prCustomer').value&&!!byId('prAccount').value&&!!byId('prNarration').value.trim();byId('prPost').disabled=!valid||busy;return {rows,sum,valid}}
 function renderBookings(){const customer=Number(byId('prCustomer').value),term=byId('prSearch').value.trim().toLowerCase();const rows=bookings.filter(x=>x.customer_id===customer&&(!term||`${x.plot} ${x.customer} ${x.booking_id}`.toLowerCase().includes(term)));byId('prBookingRows').innerHTML=rows.length?rows.map(x=>`<tr data-booking="${x.booking_id}"><td><input class="pr-check" type="checkbox" value="${x.booking_id}" aria-label="Select plot ${esc(x.plot)}"></td><td><b>${esc(x.plot)}</b><br><small class="pr-muted">Booking #${x.booking_id}</small></td><td>${esc(x.customer)}</td><td>${x.receivable_account_id?`COA #${x.receivable_account_id}`:'<span class="text-danger">No mapped account</span>'}</td><td><input class="form-control form-control-sm pr-amount" data-amount="${x.booking_id}" type="number" min="0.01" step="0.01" placeholder="0.00" ${x.receivable_account_id?'':'disabled'}></td></tr>`).join(''):'<tr><td colspan="5" class="text-center pr-muted p-4">No eligible active bookings found for this customer.</td></tr>';$('.pr-check').each((_,c)=>$(c).on('change',()=>{c.closest('tr').classList.toggle('pr-picked',c.checked);total()}));$('.pr-amount').each((_,i)=>$(i).on('input',total));total()}
 async function loadBookings(){try{const r=await requestJson(root.dataset.optionsUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load active bookings');bookings=j.data||[];const groups=new Map();bookings.forEach(x=>{if(!groups.has(x.customer_id))groups.set(x.customer_id,x.customer)});byId('prCustomer').innerHTML='<option value="">Select customer</option>'+[...groups].sort((a,b)=>a[1].localeCompare(b[1])).map(([id,name])=>`<option value="${id}">${esc(name)}</option>`).join('');if(!bookings.length)byId('prBookingRows').innerHTML='<tr><td colspan="5" class="text-center pr-muted p-4">No eligible active bookings with mapped receivable accounts.</td></tr>'}catch(e){alert('danger',e.message)}}
 async function loadHistory(){const body=byId('prHistoryRows');body.innerHTML='<tr><td colspan="5" class="text-center p-4">Loading receipts…</td></tr>';try{const r=await requestJson(root.dataset.historyUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load receipts');body.innerHTML=(j.data||[]).length?j.data.map(x=>`<tr><td><b>${esc(x.number)}</b><br><small>${esc(x.date)}</small></td><td>${esc(x.customer)}</td><td class="text-right">${money(x.amount)}</td><td>${esc(x.narration)}</td><td><button type="button" class="btn btn-sm btn-outline-primary pr-detail" data-id="${x.id}">Details / print</button></td></tr>`).join(''):'<tr><td colspan="5" class="text-center pr-muted p-4">No plot receipts posted yet.</td></tr>';$('.pr-detail').each((_,b)=>$(b).on('click',()=>showDetails(b.dataset.id)))}catch(e){body.innerHTML=`<tr><td colspan="5" class="text-danger p-3">${esc(e.message)}</td></tr>`}}
 async function showDetails(id){const body=byId('prDetailsBody');body.innerHTML='<div class="text-center p-4">Loading…</div>';$('#prDetailsTitle').textContent='Plot receipt details';$('#prDetailsModal').modal('show');try{const r=await requestJson(`${root.dataset.detailsBase}/${encodeURIComponent(id)}`,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load receipt details');const x=j.receipt,a=j.allocations||[];$('#prDetailsTitle').textContent=`${x.receipt_number} · ${x.receipt_date}`;body.innerHTML=`<p><b>Customer:</b> ${esc(x.customer)} &nbsp; <b>Total:</b> ${money(x.amount)}</p><p>${esc(x.narration)}</p><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Plot</th><th>Booking</th><th class="text-right">Allocated</th><th></th></tr></thead><tbody>${a.map(y=>`<tr><td>${esc(y.plot_name)}</td><td>#${y.booking_id}</td><td class="text-right">${money(y.amount)}</td><td><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${esc(root.dataset.printBase+'/'+id+'/plots/'+y.plot_id+'/print')}">Print receipt</a></td></tr>`).join('')}</tbody></table></div>`}catch(e){body.innerHTML=`<div class="alert alert-danger">${esc(e.message)}</div>`}}
 $('#prCustomer').on('change',renderBookings);$('#prSearch').on('input',renderBookings);['prNumber','prDate','prAccount','prNarration'].forEach(id=>$('#'+$.escapeSelector(id)).on('input',total));
 $('#prPost').on('click',async()=>{const state=total();if(!state.valid||busy)return;busy=true;total();const payload={customer_id:Number(byId('prCustomer').value),receipt_number:byId('prNumber').value.trim(),date:byId('prDate').value,amount:state.sum.toFixed(2),cash_account_id:Number(byId('prAccount').value),narration:byId('prNarration').value.trim(),request_key:(crypto.randomUUID?crypto.randomUUID().replaceAll('-',''):'plotreceipt'+Date.now()+'request').slice(0,32),allocations:state.rows.map(x=>({booking_id:x.booking_id,plot_id:x.plot_id,receivable_account_id:x.receivable_account_id,amount:Number($(`[data-amount="${x.booking_id}"]`)[0].value).toFixed(2)}))};try{const r=await requestJson(root.dataset.postUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(payload)}),j=await r.json();if(!r.ok)throw Error(j.message||Object.values(j.errors||{}).flat().join(' ')||'Receipt was rejected');alert('success',`Receipt ${j.data.receipt_number||payload.receipt_number} posted. Posting batch #${j.data.posting_batch_id}.`);byId('prNumber').value='';byId('prNarration').value='';renderBookings();await loadHistory()}catch(e){alert('danger',e.message)}finally{busy=false;total()}});
 loadBookings();loadHistory();total()})();
</script>
@endsection
