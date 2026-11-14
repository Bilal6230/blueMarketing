@extends('admin.layouts.master')
@section('title','Plot Receive Payment')
@section('content')
<style>
/* Plot-specific modal allocation UI; directory shell is globally defined. */
#plotReceiptApp #prTable td:nth-child(3){color:var(--voucher-accent);font-weight:700}
#plotReceiptCreateModal .modal-body label{font-size:.83rem;font-weight:700}
#plotReceiptCreateModal .pr-bookings{max-height:300px;overflow:auto}
#plotReceiptCreateModal .pr-bookings thead th{position:sticky;top:0;background:#eaf2ff;z-index:1}
#plotReceiptCreateModal .pr-picked{background:#eaf3ff}
#plotReceiptCreateModal .pr-total{font-size:1.3rem;font-weight:700;color:var(--voucher-accent)}
#plotReceiptCreateModal .pr-stat{background:#f4f8fd;border:1px solid #dce6f2;border-radius:8px;padding:9px}
#plotReceiptCreateModal .pr-muted{color:#64748b}
</style>
<div class="container-fluid bm-ui bm-voucher-directory bm-voucher-workspace pr-page" id="plotReceiptApp"
 style="--voucher-accent:#1769e0;--voucher-tint:#eaf2ff"
 data-options-url="{{ route('finance.voucher.plot-receipt.options') }}"
 data-history-url="{{ route('finance.voucher.plot-receipt.history') }}"
 data-post-url="{{ route('finance.voucher.plot-receipt.post') }}"
 data-details-base="{{ url('admin/finance/voucher/plot-receipt') }}"
 data-print-base="{{ url('admin/finance/voucher/plot-receipt') }}">
 <div class="bm-voucher-heading pr-heading d-flex align-items-center justify-content-between">
  <div><h2>Plot Receive Payment</h2><div class="bm-voucher-subtitle pr-subtitle">Selected project records · Read-only history</div></div>
  <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
 </div>
 <x-finance.voucher-filter-card class="pr-filter-card">
  <form id="prFilterForm" class="bm-voucher-filter-grid pr-filter-row">
   <div><label for="prFrom">From</label><input id="prFrom" class="form-control" type="date"></div>
   <div><label for="prTo">To</label><input id="prTo" class="form-control" type="date"></div>
   <div><label for="prFilterCustomer">Customer</label><select id="prFilterCustomer" class="form-control"><option value="">All customers</option></select></div>
   <div><label for="prFilterSearch">Search</label><input id="prFilterSearch" class="form-control" maxlength="120" placeholder="Number, customer or details"></div>
   <div class="bm-voucher-filter-actions pr-filter-actions">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Apply</button>
    <button type="button" id="prResetFilters" class="btn btn-outline-secondary btn-sm">Reset</button>
    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#plotReceiptCreateModal"><i class="fas fa-plus mr-1"></i> New voucher</button>
   </div>
  </form>
 </x-finance.voucher-filter-card>
 <x-finance.voucher-table-card class="pr-table-card">
  <div class="bm-voucher-table-toolbar pr-tools bm-records-toolbar">
   <label>Show <select id="prLength" class="form-control form-control-sm"><option>10</option><option selected>25</option><option>50</option><option>100</option></select> entries</label>
   <label>Search: <input id="prQuickSearch" class="form-control form-control-sm" placeholder="Search vouchers"></label>
  </div>
  <div id="prListError" class="alert alert-danger d-none m-2" role="alert"></div>
  <div class="bm-voucher-table-scroll pr-table-wrap">
   <table id="prTable" class="table bm-table bm-voucher-table table-bordered table-hover table-sm w-100">
    <colgroup><col style="width:48px"><col style="width:115px"><col style="width:140px"><col style="width:230px"><col><col style="width:120px"><col style="width:100px"><col style="width:80px"></colgroup>
    <thead><tr><th>#</th>
     @foreach(['date'=>'Date','number'=>'Voucher #','customer'=>'Customer','detail'=>'Details','amount'=>'Amount','status'=>'Status'] as $field=>$label)
     <th class="dv-filter-heading" data-field="{{ $field }}"><span>{{ $label }}</span><button type="button" class="dv-excel-trigger" data-column="{{ $field }}" aria-label="Sort or filter {{ $label }}" aria-expanded="false" aria-haspopup="dialog" title="Sort and filter {{ $label }}"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
     @endforeach
     <th>Actions</th>
    </tr></thead>
    <tbody id="prHistoryRows"><tr><td colspan="8" class="text-center text-muted py-4">Loading vouchers…</td></tr></tbody>
   </table>
  </div>
  <div class="bm-voucher-table-footer pr-footer"><span id="prSummary" class="bm-voucher-count pr-count">Loading records…</span><div id="prPages" class="d-flex align-items-center flex-wrap" style="gap:4px"></div></div>
 </x-finance.voucher-table-card>
</div>
<x-finance.voucher-column-filters />
@endsection

@section('modal')
<div class="modal fade" id="plotReceiptCreateModal" tabindex="-1" role="dialog" aria-labelledby="prCreateTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 id="prCreateTitle" class="modal-title">New Plot Receive Payment</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body">
   <div id="prAlert" class="alert d-none" role="status"></div>
   <div class="form-row">
    <div class="form-group col-md-6"><label for="prNumber">Voucher No.</label><input id="prNumber" class="form-control" maxlength="64" placeholder="e.g. PLOT-RCPT-001"></div>
    <div class="form-group col-md-6"><label for="prDate">Date</label><input id="prDate" type="date" class="form-control" value="{{ now()->toDateString() }}"></div>
    <div class="form-group col-md-6"><label for="prCustomer">Customer</label><select id="prCustomer" class="form-control"><option value="">Loading customers…</option></select></div>
    <div class="form-group col-md-6"><label for="prAccount">Receiving Account</label><select id="prAccount" class="form-control"><option value="">Select active project account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }}{{ $account->subheadAccounting?->name ? ' / '.$account->subheadAccounting->name : '' }}</option>@endforeach</select></div>
    <div class="form-group col-12"><label for="prNarration">Description / Narration</label><textarea id="prNarration" class="form-control" rows="2" maxlength="2000" placeholder="Payment details"></textarea></div>
   </div>
   <div class="d-flex align-items-center justify-content-between mb-2"><label for="prSearch" class="mb-0">Allocate to active plot bookings (minimum two)</label><input id="prSearch" class="form-control form-control-sm" style="max-width:240px" placeholder="Search plots"></div>
   <div class="table-responsive pr-bookings"><table class="table table-bordered table-hover table-sm mb-0"><thead><tr><th style="width:45px">Select</th><th>Plot / Booking</th><th>Customer</th><th>Receivable account</th><th style="width:150px">Allocate amount</th></tr></thead><tbody id="prBookingRows"><tr><td colspan="5" class="text-center text-muted p-3">Select customer to load bookings.</td></tr></tbody></table></div>
   <div class="pr-stat mt-3"><small>Receipt total (allocated): </small><span class="pr-total" id="prTotal">0.00</span><div class="small text-muted"><span id="prSelected">0</span> plots selected · choose at least 2</div></div>
   <small class="d-block text-muted mt-2">Each plot allocation posts to its own mapped receivable account. Corrections require reversal.</small>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button id="prPost" type="button" class="btn btn-primary btn-sm" disabled><i class="fas fa-check-circle mr-1"></i> Post receipt</button></div>
 </div></div>
</div>
<div class="modal fade" id="prDetailsModal" tabindex="-1" role="dialog" aria-labelledby="prDetailsTitle" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="prDetailsTitle">Receipt details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body" id="prDetailsBody"></div>
 </div></div>
</div>
@endsection

@section('js')
<script>
(function($){
 'use strict';
 $(function(){
 var $root=$('#plotReceiptApp');if(!$root.length)return;
 var bookings=[],history=[],page=1,filters={from:'',to:'',customer:'',search:''},busy=false,pendingKey=null;
 var sort='date',direction='desc',columns=null;
 var escapeText=function(value){return $('<div>').text(value == null?'':String(value)).html();};
 var money=function(n){return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});};
 var key=function(){return 'plotreceipt'+Date.now().toString(36)+Math.random().toString(36).slice(2,14);};
 var notify=function(kind,message){$('#prAlert').attr('class','alert alert-'+kind).text(message);};
 var ajax=function(url,method,data){
  return $.ajax({url:url,type:method||'GET',dataType:'json',
   data:method==='POST'?JSON.stringify(data):undefined,
   contentType:method==='POST'?'application/json':undefined,
   headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}});
 };
 var getValue=function(row,field){
  var map={date:row.date,number:row.number,customer:row.customer,detail:row.narration,
   amount:row.amount,status:'Posted'};
  return String(map[field]==null?'':map[field]);
 };
 var selected=function(){
  return $('.pr-check:checked').map(function(){var id=Number($(this).val());return bookings.find(function(b){return b.booking_id===id;})||null;}).get().filter(Boolean);
 };
 var recalc=function(){
  var rows=selected(),sum=0,valid=rows.length>=2;
  rows.forEach(function(b){var amount=Number($('.pr-amount[data-amount="'+b.booking_id+'"]').val())||0;sum+=amount;if(amount<=0)valid=false;});
  $('#prTotal').text(money(sum));$('#prSelected').text(rows.length);
  valid=valid&&$.trim($('#prNumber').val())!==''&&$('#prDate').val()&&$('#prCustomer').val()&&$('#prAccount').val()&&$.trim($('#prNarration').val())!=='';
  $('#prPost').prop('disabled',!valid||busy);
  return {rows:rows,sum:sum,valid:!!valid};
 };
 var drawBookings=function(){
  var customer=Number($('#prCustomer').val()),term=$.trim($('#prSearch').val()).toLowerCase();
  var rows=bookings.filter(function(b){return b.customer_id===customer&&(!term||(b.plot+' '+b.customer+' '+b.booking_id).toLowerCase().includes(term));});
  var $body=$('#prBookingRows').empty();
  if(!rows.length){$body.append($('<tr>').append($('<td>',{colspan:5,'class':'text-center text-muted p-3',text:'No eligible active bookings found.'})));recalc();return;}
  rows.forEach(function(b){
   var $tr=$('<tr>'),$check=$('<input>',{'type':'checkbox','class':'pr-check',value:b.booking_id,'aria-label':'Select plot '+b.plot});
   $tr.append($('<td>').append($check));
   $tr.append($('<td>').append($('<strong>').text(b.plot),$('<small>',{'class':'d-block text-muted',text:'Booking #'+b.booking_id})));
   $tr.append($('<td>').text(b.customer));
   $tr.append($('<td>').text(b.receivable_account_id?'COA #'+b.receivable_account_id:'No mapped account'));
   $tr.append($('<td>').append($('<input>',{'type':'number','class':'form-control form-control-sm pr-amount','data-amount':b.booking_id,min:'0.01',step:'0.01',placeholder:'0.00',disabled:!b.receivable_account_id})));
   $body.append($tr);
  });
  recalc();
 };
 var filtered=function(){
  var quick=$.trim($('#prQuickSearch').val()).toLowerCase();
  var rows=history.filter(function(r){
   var hay=[r.number,r.date,r.customer,r.narration,r.amount].join(' ').toLowerCase();
   return (!filters.from||r.date>=filters.from)&&(!filters.to||r.date<=filters.to)
    &&(!filters.customer||r.customer===filters.customer)
    &&(!filters.search||hay.includes(filters.search))
    &&(!quick||hay.includes(quick));
  });
  return columns?columns.filterRows(rows,getValue):rows;
 };
 var drawHistory=function(){
  var rows=filtered(),length=Number($('#prLength').val())||25,totalPages=Math.max(1,Math.ceil(rows.length/length));
  page=Math.min(page,totalPages);var start=(page-1)*length,visible=rows.slice(start,start+length);
  if($.blueActionMenu)$.blueActionMenu.close();
  var $body=$('#prHistoryRows').empty();
  if(!visible.length)$body.append($('<tr>').append($('<td>',{colspan:8,'class':'text-center text-muted py-4',text:'No matching plot receipts'})));
  visible.forEach(function(r,index){
   var $row=$('<tr>');
   $row.append($('<td>').text(start+index+1),$('<td>').text(r.date));
   $row.append($('<td>').append($('<strong>').text(r.number)));
   $row.append($('<td>').text(r.customer),$('<td>').text(r.narration));
   $row.append($('<td>',{'class':'text-right'}).text(money(r.amount)),$('<td>').text('Posted'));
   $row.append($('<td>',{'class':'blue-actions-cell'}).append($('<button>',{'type':'button','class':'btn btn-sm btn-outline-primary pr-detail','data-id':r.id,text:'Details'})));
   $body.append($row);
  });
  if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
  $.blueVoucherPagination.render({container:'#prPages',summary:'#prSummary',
   total:rows.length,length:length,start:start,buttonClass:'bm-voucher-page-btn',
   onPage:function(next){page=Math.floor(next/length)+1;drawHistory();}});
 };
 var loadHistory=function(){
  $('#prListError').addClass('d-none').empty();
  return ajax($root.attr('data-history-url'),'GET').done(function(res){
   history=res.data||[];var names={};
   history.forEach(function(r){if(r.customer)names[r.customer]=true;});
   var $filter=$('#prFilterCustomer'),chosen=$filter.val();
   $filter.empty().append($('<option>',{value:'',text:'All customers'}));
   Object.keys(names).sort().forEach(function(name){$filter.append($('<option>',{value:name,text:name}));});
   $filter.val(chosen||'');page=1;drawHistory();
  }).fail(function(xhr){$('#prHistoryRows').empty().append($('<tr>').append($('<td>',{colspan:8,'class':'text-danger p-3',text:xhr.responseJSON&&xhr.responseJSON.message||'Unable to load receipts'})));
   $('#prListError').removeClass('d-none').text(xhr.responseJSON&&xhr.responseJSON.message||'Unable to load receipts');});
 };
 var loadBookings=function(){
  return ajax($root.attr('data-options-url'),'GET').done(function(res){
   bookings=res.data||[];var customers={};
   bookings.forEach(function(b){customers[b.customer_id]=b.customer;});
   var $select=$('#prCustomer').empty().append($('<option>',{value:'',text:'Select customer'}));
   Object.keys(customers).sort(function(a,b){return customers[a].localeCompare(customers[b]);})
     .forEach(function(id){$select.append($('<option>',{value:id,text:customers[id]}));});
   drawBookings();
  }).fail(function(xhr){notify('danger',xhr.responseJSON&&xhr.responseJSON.message||'Unable to load bookings');});
 };
 var showDetails=function(id){
  $('#prDetailsTitle').text('Receipt details');$('#prDetailsBody').text('Loading…');
  $('#prDetailsModal').modal('show');
  ajax($root.attr('data-details-base')+'/'+encodeURIComponent(id),'GET').done(function(res){
   var item=res.receipt,items=res.allocations||[],$body=$('#prDetailsBody').empty();
   $('#prDetailsTitle').text(item.receipt_number+' · '+item.receipt_date);
   $body.append($('<p>').append($('<b>').text('Customer: '),$('<span>').text(item.customer+'   Total: '+money(item.amount))));
   $body.append($('<p>').text(item.narration));
   var $table=$('<table>',{'class':'table table-sm table-bordered'}),$tbody=$('<tbody>');
   $table.append($('<thead>').append($('<tr>').append($('<th>').text('Plot'),$('<th>').text('Booking'),$('<th>').text('Allocated'),$('<th>').text('Print'))));
   items.forEach(function(v){
    var url=$root.attr('data-print-base')+'/'+encodeURIComponent(id)+'/plots/'+encodeURIComponent(v.plot_id)+'/print';
    $tbody.append($('<tr>').append($('<td>').text(v.plot_name),$('<td>').text('#'+v.booking_id),
     $('<td>').text(money(v.amount)),$('<td>').append($('<a>',{'class':'btn btn-sm btn-outline-primary',href:url,target:'_blank',rel:'noopener',text:'Print receipt'}))));
   });
   $body.append($('<div>',{'class':'table-responsive'}).append($table.append($tbody)));
  }).fail(function(xhr){$('#prDetailsBody').text(xhr.responseJSON&&xhr.responseJSON.message||'Unable to load details');});
 };
 columns=$.blueVoucherColumnFilters.init({
  initialOptions:{head:[],subhead:[],account_relations:[]},
  formSelector:'#prFilterForm',quickSearchSelector:'#prQuickSearch',
  triggerSelector:'#plotReceiptApp .dv-excel-trigger',tableWrapSelector:'.pr-table-wrap',
  getStart:function(){return (page-1)*(Number($('#prLength').val())||25);},
  getLength:function(){return Number($('#prLength').val())||25;},
  getSortColumn:function(){return sort;},getSortDirection:function(){return direction;},
  onSort:function(field,dir){sort=field;direction=dir;},
  onChange:function(){page=1;drawHistory();},
  choicesFor:function(field){return Array.from(new Set(history.map(function(r){return getValue(r,field);}))).sort();}
 });
 $('#prFilterForm').on('submit',function(e){e.preventDefault();filters={
  from:$('#prFrom').val(),to:$('#prTo').val(),customer:$('#prFilterCustomer').val(),
  search:$.trim($('#prFilterSearch').val()).toLowerCase()};page=1;drawHistory();});
 $('#prResetFilters').on('click',function(){
  $('#prFilterForm')[0].reset();$('#prQuickSearch').val('');filters={from:'',to:'',customer:'',search:''};
  sort='date';direction='desc';columns.reset();page=1;drawHistory();
 });
 $('#prLength').on('change',function(){page=1;drawHistory();});
 $('#prQuickSearch').on('input',function(){page=1;drawHistory();});
 $('#prHistoryRows').on('click','.pr-detail',function(){showDetails($(this).attr('data-id'));});
 $('#prCustomer').on('change',drawBookings);
 $('#prSearch').on('input',drawBookings);
 $('#prBookingRows').on('change','.pr-check',function(){$(this).closest('tr').toggleClass('pr-picked',$(this).prop('checked'));recalc();})
  .on('input','.pr-amount',recalc);
 $('#prNumber,#prDate,#prAccount,#prNarration').on('input change',recalc);
 $('#plotReceiptCreateModal').on('show.bs.modal',function(){$('#prAlert').addClass('d-none');recalc();});
 $('#prPost').on('click',function(){
  var state=recalc();if(!state.valid||busy)return;busy=true;recalc();
  if(!pendingKey)pendingKey=key();
  var payload={customer_id:Number($('#prCustomer').val()),receipt_number:$.trim($('#prNumber').val()),
   date:$('#prDate').val(),amount:state.sum.toFixed(2),cash_account_id:Number($('#prAccount').val()),
   narration:$.trim($('#prNarration').val()),request_key:pendingKey,
   allocations:state.rows.map(function(row){return {booking_id:row.booking_id,plot_id:row.plot_id,
    receivable_account_id:row.receivable_account_id,
    amount:Number($('.pr-amount[data-amount="'+row.booking_id+'"]').val()).toFixed(2)};})};
  ajax($root.attr('data-post-url'),'POST',payload).done(function(res){
   pendingKey=null;$('#plotReceiptCreateModal').modal('hide');$('#prNumber,#prNarration').val('');
   $('#prCustomer').val('');$('#prSearch').val('');drawBookings();loadHistory();
  }).fail(function(xhr){
   var error=xhr.responseJSON||{};
   notify('danger',error.message||Object.values(error.errors||{}).flat().join(' ')||'Receipt was rejected');
  }).always(function(){busy=false;recalc();});
 });
 loadBookings();loadHistory();
 });
})(jQuery);
</script>
@endsection
