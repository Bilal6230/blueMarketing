@extends('admin.layouts.master')
@section('title','Plot Purchase Voucher')
@section('content')
<style>
/* Standard CR/DR directory layout is global; only purchase form-specific CSS lives here. */
#plotPurchaseApp #ppTable td:nth-child(3){color:var(--voucher-accent);font-weight:700}
#plotPurchaseCreateModal .pp-item{border:1px solid #dce6f2;border-radius:10px;padding:12px;margin-bottom:10px;background:#fbfdff}
#plotPurchaseCreateModal .pp-item-grid{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:9px}
#plotPurchaseCreateModal .pp-item label{font-size:12px;margin-bottom:4px}
#plotPurchaseCreateModal .pp-item .form-control{height:34px;font-size:13px}
#plotPurchaseCreateModal .pp-item .wide{grid-column:span 2}
#plotPurchaseCreateModal .pp-total{font-size:22px;font-weight:700;color:var(--voucher-accent)}
#plotPurchaseCreateModal .pp-muted{color:#657891}
#plotPurchaseCreateModal #ppItems{max-height:350px;overflow:auto}
@media(max-width:900px){#plotPurchaseCreateModal .pp-item-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
@media(max-width:560px){#plotPurchaseCreateModal .pp-item-grid{grid-template-columns:1fr}#plotPurchaseCreateModal .pp-item .wide{grid-column:auto}}
</style>
<main class="container-fluid bm-ui bm-voucher-directory bm-voucher-workspace pp-page" id="plotPurchaseApp"
 style="--voucher-accent:#1769e0;--voucher-tint:#eaf2ff"
 data-options-url="{{ route('finance.voucher.plot-purchase.options') }}"
 data-history-url="{{ route('finance.voucher.plot-purchase.history') }}"
 data-post-url="{{ route('finance.voucher.plot-purchase.post') }}"
 data-details-base="{{ url('admin/finance/voucher/plot-purchase') }}">
 <div class="bm-voucher-heading d-flex justify-content-between align-items-center">
  <div><h2>Plot Purchase Voucher</h2><div class="bm-voucher-subtitle">Selected project records · Read-only history</div></div>
  <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
 </div>
 <x-finance.voucher-filter-card class="bm-voucher-filter-card pp-filter-card">
  <form id="ppFilterForm" class="bm-voucher-filter-grid">
   <div><label for="ppFrom">From</label><input id="ppFrom" class="form-control" type="date"></div>
   <div><label for="ppTo">To</label><input id="ppTo" class="form-control" type="date"></div>
   <div><label for="ppSellerFilter">Seller</label><input id="ppSellerFilter" class="form-control" placeholder="Seller account"></div>
   <div><label for="ppFilterSearch">Search</label><input id="ppFilterSearch" class="form-control" placeholder="Number, plot or details"></div>
   <div class="bm-voucher-filter-actions">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Apply</button>
    <button type="button" id="ppResetFilters" class="btn btn-outline-secondary btn-sm">Reset</button>
    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#plotPurchaseCreateModal"><i class="fas fa-plus mr-1"></i> New voucher</button>
   </div>
  </form>
 </x-finance.voucher-filter-card>
 <x-finance.voucher-table-card class="bm-voucher-table-card pp-table-card">
  <div class="bm-voucher-table-toolbar bm-records-toolbar">
   <label>Show <select id="ppLength" class="form-control form-control-sm"><option>10</option><option selected>25</option><option>50</option><option>100</option></select> entries</label>
   <label>Search: <input id="ppQuickSearch" class="form-control form-control-sm" placeholder="Search purchases"></label>
  </div>
  <div class="bm-voucher-table-scroll pp-scroll">
   <table id="ppTable" class="table bm-table bm-voucher-table table-bordered table-hover table-sm w-100">
    <colgroup><col style="width:45px"><col style="width:125px"><col style="width:155px"><col style="width:225px"><col><col style="width:75px"><col style="width:120px"><col style="width:85px"></colgroup>
    <thead><tr><th>#</th>
      @foreach(['date'=>'Date','number'=>'Voucher #','seller'=>'Seller','detail'=>'Details','items'=>'Plots','amount'=>'Amount'] as $field=>$label)
      <th class="dv-filter-heading" data-field="{{ $field }}"><span>{{ $label }}</span><button type="button" class="dv-excel-trigger" data-column="{{ $field }}" aria-label="Sort or filter {{ $label }}" aria-expanded="false" aria-haspopup="dialog"><i class="fas fa-caret-down"></i></button></th>
      @endforeach
      <th>Actions</th>
    </tr></thead>
    <tbody id="ppHistory"><tr><td colspan="8" class="text-center text-muted p-4">Loading purchases…</td></tr></tbody>
   </table>
  </div>
  <div class="bm-voucher-table-footer"><small id="ppSummary" class="bm-voucher-count">Loading records…</small><div id="ppPages" class="d-flex align-items-center flex-wrap" style="gap:4px"></div></div>
 </x-finance.voucher-table-card>
</main>
<x-finance.voucher-column-filters />
@endsection
@section('modal')
<div class="modal fade" id="plotPurchaseCreateModal" tabindex="-1" role="dialog" aria-labelledby="ppCreateTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="ppCreateTitle">New Plot Purchase Voucher</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body">
<div id="ppAlert" class="alert d-none" role="status"></div>
<div class="pp-body">
   <div class="form-row"><div class="form-group col-md-6"><label>Purchase voucher no.</label><input id="ppNumber" class="form-control" maxlength="64" placeholder="e.g. PP-2026-0001"></div><div class="form-group col-md-6"><label>Date</label><input id="ppDate" type="date" class="form-control" value="{{ now()->toDateString() }}"></div></div>
   <div class="form-group"><label>Seller / payable account</label><select id="ppSeller" class="form-control"><option value="">Select project account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }}{{ $account->subheadAccounting?->name ? ' / '.$account->subheadAccounting->name : '' }}</option>@endforeach</select></div>
   <div class="form-group"><label>Narration</label><textarea id="ppNarration" class="form-control" rows="2" maxlength="2000" placeholder="Purchase agreement, seller reference, or notes"></textarea></div>
   <div class="d-flex justify-content-between align-items-center mb-2"><b>Purchased plots</b><button type="button" id="ppAdd" class="btn btn-sm btn-outline-primary">+ Add plot</button></div>
   <div id="ppItems"></div><div class="d-flex justify-content-between align-items-center border-top pt-3 mb-3"><span>Total purchase cost</span><span class="pp-total" id="ppTotal">0.00</span></div>
   <small class="d-block pp-muted mt-2">Posts plot inventory debit and seller credit. Posting is append-only; corrections require reversal.</small>

  </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" id="ppPost" class="btn btn-primary btn-sm" disabled><i class="fas fa-check mr-1"></i> Post purchase</button></div>
 </div></div>
</div>
<div class="modal fade" id="ppDetailsModal" tabindex="-1" role="dialog" aria-label="Purchase details" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Purchase details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body" id="ppDetails"></div>
 </div></div>
</div>
<template id="ppItemTemplate"><div class="pp-item"><div class="d-flex justify-content-between mb-2"><strong class="pp-item-title">Plot</strong><button type="button" class="btn btn-sm btn-outline-danger pp-remove" aria-label="Remove plot">Remove</button></div><div class="pp-item-grid">
 <div><label>Plot code</label><input data-field="plot_code" class="form-control" maxlength="64" placeholder="e.g. A-101"></div><div class="wide"><label>Description</label><input data-field="description" class="form-control" maxlength="255" placeholder="Plot description"></div><div><label>Area</label><input data-field="area" class="form-control" type="number" min="0.0001" step="0.0001" placeholder="0"></div><div><label>Unit</label><input data-field="unit" class="form-control" maxlength="24" placeholder="Marla / Sq Ft"></div>
 <div><label>Plot class</label><select data-field="type" class="form-control"><option value="1">Residential</option><option value="2">Commercial</option></select></div><div><label>Road</label><select data-field="road_id" class="form-control"><option value="">Select road</option></select></div><div><label>Facing</label><select data-field="facing_id" class="form-control"><option value="">Select facing</option></select></div><div><label>Inventory account</label><select data-field="inventory_account_id" class="form-control"><option value="">Select project account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }}{{ $account->subheadAccounting?->name ? ' / '.$account->subheadAccounting->name : '' }}</option>@endforeach</select></div>
 <div><label>Unit rate</label><input data-field="unit_rate" class="form-control" type="number" min="0.01" step="0.01" placeholder="0.00"></div><div><label>Corner plot</label><select data-field="is_corner" class="form-control"><option value="0">No</option><option value="1">Yes</option></select></div>
 <div data-definition="category"><label>Category</label><select data-field="plot_category_definition_id" class="form-control"><option value="">Select category</option></select></div><div data-definition="type"><label>Plot type definition</label><select data-field="plot_type_definition_id" class="form-control"><option value="">Select type</option></select></div><div data-definition="size"><label>Size definition</label><select data-field="plot_size_definition_id" class="form-control"><option value="">Select size</option></select></div><div data-definition="unit"><label>Unit definition</label><select data-field="plot_unit_definition_id" class="form-control"><option value="">Select unit</option></select></div>
 </div></div></template>
@endsection

@section('js')
<script>
(()=>{
 $(function(){
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
  const root=$('#plotPurchaseApp')[0];if(!root)return;const byId=id=>$('#'+$.escapeSelector(id))[0],esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),money=n=>(Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});let options={roads:[],facings:[],definitions:[]},busy=false,pendingRequestKey=null;
 const alert=(kind,msg)=>{const box=byId('ppAlert');box.className='alert alert-'+kind;box.textContent=msg;$(box).removeClass('d-none')};
 const defs=kind=>options.definitions.filter(x=>x.kind===kind);
 function fillSelect(select,items,label){select.innerHTML=`<option value="">Select ${label}</option>`+items.map(x=>`<option value="${x.id}" data-legacy="${esc(x.legacy_value??'')}" data-size="${esc(x.size_value??'')}" data-unit="${esc(x.unit_code??'')}">${esc(x.code?x.code+' · ':'')}${esc(x.name)}</option>`).join('')}
 function row(){const node=byId('ppItemTemplate').content.firstElementChild.cloneNode(true);for(const kind of ['road_id','facing_id'])fillSelect($(node).find(`[data-field="${kind}"]`)[0],options[kind==='road_id'?'roads':'facings'],kind==='road_id'?'road':'facing');for(const kind of ['category','type','size','unit']){const wrap=$(node).find(`[data-definition="${kind}"]`)[0],select=$(wrap).find('select')[0];fillSelect(select,defs(kind),kind);wrap.hidden=!defs(kind).length;select.required=defs(kind).length>0;if(kind==='type')$(select).on('change',()=>{const v=select.selectedOptions[0]?.dataset.legacy;if(['1','2'].includes(v))$(node).find('[data-field="type"]')[0].value=v});if(kind==='size')$(select).on('change',()=>{const opt=select.selectedOptions[0];if(opt?.dataset.size)$(node).find('[data-field="area"]')[0].value=opt.dataset.size;if(opt?.dataset.unit)$(node).find('[data-field="unit"]')[0].value=opt.dataset.unit})}$(node).find('input,select').toArray().forEach(el=>$(el).on('input',recalc));$(node).find('.pp-remove').on('click',()=>{if(byId('ppItems').children.length>1)node.remove();recalc()});byId('ppItems').append(node);$(node).find('.pp-item-title')[0].textContent=`Plot ${byId('ppItems').children.length}`;recalc()}
 function recalc(){let total=0,valid=!!byId('ppNumber').value.trim()&&!!byId('ppDate').value&&!!byId('ppSeller').value&&!!byId('ppNarration').value.trim();$('.pp-item').each((_,el)=>{const get=k=>$(el).find(`[data-field="${k}"]`)[0],area=Number(get('area').value),rate=Number(get('unit_rate').value);total+=area*rate;for(const k of ['plot_code','description','area','unit','road_id','facing_id','inventory_account_id','unit_rate'])valid=valid&&!!get(k).value;valid=valid&&area>0&&rate>0;for(const kind of ['category','type','size','unit'])if(defs(kind).length)valid=valid&&!!get(`plot_${kind}_definition_id`).value});byId('ppTotal').textContent=money(total);byId('ppPost').disabled=!valid||busy||total<=0}
 let history=[],historyPage=1,historySort='date',historyDirection='desc';
 let applied={from:'',to:'',seller:'',search:''},purchaseColumns=null;
 function historyValue(x,field){
   const values={date:x.date,number:x.number,seller:x.seller,detail:x.narration,items:x.items,amount:x.amount};
   return String(values[field]==null?'':values[field]);
 }
 function drawHistory(){
   const search=String($('#ppQuickSearch').val()||'').trim().toLowerCase();
   let rows=history.filter(x=>{
     const hay=[x.number,x.date,x.seller,x.narration,x.items,x.amount].join(' ').toLowerCase();
     return (!applied.from||x.date>=applied.from)&&(!applied.to||x.date<=applied.to)
       &&(!applied.seller||String(x.seller).toLowerCase().includes(applied.seller))
       &&(!applied.search||hay.includes(applied.search))&&(!search||hay.includes(search));
   });
   if(purchaseColumns)rows=purchaseColumns.filterRows(rows,historyValue);
   if(historySort){
     rows.sort((a,b)=>{
       const left=historyValue(a,historySort),right=historyValue(b,historySort);
       const compare=['amount','items'].includes(historySort)
          ?Number(left)-Number(right):left.localeCompare(right,undefined,{numeric:true});
       return historyDirection==='asc'?compare:-compare;
     });
   }
   const size=Number($('#ppLength').val())||25,pages=Math.max(1,Math.ceil(rows.length/size));
   historyPage=Math.min(historyPage,pages);
   const start=(historyPage-1)*size,visible=rows.slice(start,start+size);
   if($.blueActionMenu)$.blueActionMenu.close();
   const $body=$('#ppHistory').empty();
   if(!visible.length)$body.append($('<tr>').append($('<td>',{colspan:8,'class':'text-center text-muted p-4',text:'No matching plot purchases'})));
   visible.forEach((x,i)=>{
     const $tr=$('<tr>');
     $tr.append($('<td>').text(start+i+1),$('<td>').text(x.date));
     $tr.append($('<td>').append($('<strong>').text(x.number)));
     $tr.append($('<td>').text(x.seller),$('<td>').text(x.narration));
     $tr.append($('<td>').text(x.items),$('<td>',{'class':'text-right'}).text(money(x.amount)));
     $tr.append($('<td>',{'class':'blue-actions-cell'}).append(
       $('<button>',{'type':'button','class':'btn btn-sm btn-outline-primary pp-detail','data-id':x.id,text:'Details / print'})));
     $body.append($tr);
   });
   if($.blueActionMenu&&typeof $.blueActionMenu.enhance==='function')$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
   if($.blueVoucherPagination)$.blueVoucherPagination.render({
     container:'#ppPages',summary:'#ppSummary',total:rows.length,length:size,start:start,
     buttonClass:'bm-voucher-page-btn',onPage:function(next){historyPage=Math.floor(next/size)+1;drawHistory();}
   });
 }
 async function loadHistory(){
   try{
     const response=await requestJson(root.dataset.historyUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
     const data=await response.json();
     if(!response.ok)throw Error(data.message||'Could not load purchases');
     history=data.data||[];historyPage=1;drawHistory();
   }catch(error){
     $('#ppHistory').empty().append($('<tr>').append($('<td>',{colspan:8,'class':'text-danger p-3',text:error.message||'Unable to load'})));
     $('#ppSummary').text('Unable to load purchases');
   }
 }
 async function details(id){const body=byId('ppDetails');body.innerHTML='<div class="text-center p-4">Loading…</div>';$('#ppDetailsModal').modal('show');try{const r=await requestJson(`${root.dataset.detailsBase}/${encodeURIComponent(id)}`,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load purchase');const p=j.purchase,items=j.items||[];body.innerHTML=`<div class="d-flex justify-content-between"><div><h4>${esc(p.purchase_number)}</h4><div>${esc(p.purchase_date)} · Seller: ${esc(p.seller||'Seller account')}</div><p>${esc(p.narration)}</p></div><a class="btn btn-primary btn-sm align-self-start" target="_blank" href="${root.dataset.detailsBase}/${encodeURIComponent(id)}/print">Print</a></div><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Plot</th><th>Description</th><th>Area</th><th>Unit</th><th>Unit rate</th><th>Cost</th><th>Inventory account</th></tr></thead><tbody>${items.map(x=>`<tr><td>${esc(x.plot_code)}</td><td>${esc(x.description)}</td><td>${esc(x.area)}</td><td>${esc(x.unit)}</td><td>${money(x.unit_rate)}</td><td>${money(x.amount)}</td><td>#${x.inventory_account_id}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="5" class="text-right">Total</th><th>${money(p.amount)}</th><th></th></tr></tfoot></table></div>`}catch(e){body.innerHTML=`<div class="alert alert-danger">${esc(e.message)}</div>`}}
 async function init(){try{const r=await requestJson(root.dataset.optionsUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await r.json();if(!r.ok)throw Error(j.message||'Could not load plot definitions');options=j;row();await loadHistory()}catch(e){alert('danger',e.message)}}
 purchaseColumns=$.blueVoucherColumnFilters.init({
   initialOptions:{head:[],subhead:[],account_relations:[]},
   formSelector:'#ppFilterForm',quickSearchSelector:'#ppQuickSearch',
   triggerSelector:'#plotPurchaseApp .dv-excel-trigger',tableWrapSelector:'.pp-scroll',
   getStart:function(){return (historyPage-1)*(Number($('#ppLength').val())||25);},
   getLength:function(){return Number($('#ppLength').val())||25;},
   getSortColumn:function(){return historySort;},getSortDirection:function(){return historyDirection;},
   onSort:function(field,dir){historySort=field;historyDirection=dir;},
   onChange:function(){historyPage=1;drawHistory();},
   choicesFor:function(field){return Array.from(new Set(history.map(x=>historyValue(x,field)))).sort();}
 });
 $('#ppFilterForm').on('submit',function(event){event.preventDefault();applied={
   from:$('#ppFrom').val(),to:$('#ppTo').val(),
   seller:String($('#ppSellerFilter').val()||'').trim().toLowerCase(),
   search:String($('#ppFilterSearch').val()||'').trim().toLowerCase()};
   historyPage=1;drawHistory();
 });
 $('#ppResetFilters').on('click',function(){
   $('#ppFilterForm')[0].reset();$('#ppQuickSearch').val('');
   applied={from:'',to:'',seller:'',search:''};historySort='date';historyDirection='desc';
   purchaseColumns.reset();historyPage=1;drawHistory();
 });
 $('#ppLength,#ppQuickSearch').on('input change',function(){historyPage=1;drawHistory();});
 $('#ppHistory').on('click','.pp-detail',function(){details($(this).attr('data-id'));});
 $('#ppAdd').on('click',row);['ppNumber','ppDate','ppSeller','ppNarration'].forEach(id=>$('#'+$.escapeSelector(id)).on('input',recalc));$('#ppPost').on('click',async()=>{recalc();if(byId('ppPost').disabled)return;busy=true;recalc();const payload={request_key:(pendingRequestKey||(pendingRequestKey=('plotpurchase'+Date.now().toString(36)+Math.random().toString(36).slice(2,15)))),purchase_number:byId('ppNumber').value.trim(),date:byId('ppDate').value,seller_account_id:Number(byId('ppSeller').value),narration:byId('ppNarration').value.trim(),items:$('.pp-item').toArray().map(el=>{const o={};$(el).find('[data-field]').toArray().forEach(x=>o[x.dataset.field]=x.value);for(const k of ['type','road_id','facing_id','is_corner','plot_category_definition_id','plot_type_definition_id','plot_size_definition_id','plot_unit_definition_id','inventory_account_id'])if(o[k]==='')delete o[k];return o})};try{const r=await requestJson(root.dataset.postUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},body:JSON.stringify(payload)}),j=await r.json();if(!r.ok)throw Error(j.message||Object.values(j.errors||{}).flat().join(' ')||'Purchase was rejected');pendingRequestKey=null;alert('success',`Purchase ${payload.purchase_number} posted. Posting batch #${j.data.posting_batch_id}.`);byId('ppNumber').value='';byId('ppNarration').value='';await loadHistory();$('#plotPurchaseCreateModal').modal('hide')}catch(e){alert('danger',e.message)}finally{busy=false;recalc()}});init()});
})();
</script>
@endsection
