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
#prTotalWords:disabled{background:#f1f5f9;color:#20364d;font-size:15px;font-weight:600;opacity:1}
</style>
<div class="container-fluid bm-ui bm-voucher-directory bm-voucher-workspace pr-page" id="plotReceiptApp"
 style="--voucher-accent:#1769e0;--voucher-tint:#eaf2ff"
 data-options-url="{{ route('finance.voucher.plot-receipt.options') }}"
 data-number-preview-url="{{ route('finance.voucher.plot-next-number','PPR') }}"
 data-history-url="{{ route('finance.voucher.plot-receipt.history') }}"
 data-post-url="{{ route('finance.voucher.plot-receipt.post') }}"
 data-details-base="{{ url('admin/finance/voucher/plot-receipt') }}"
 data-print-base="{{ url('admin/finance/voucher/plot-receipt') }}"
 data-legacy-print-base="{{ url('admin/booking/plot/voucher/print') }}"
 data-void-base="{{ url('admin/finance/voucher/plot-receipt') }}">
 <div class="bm-voucher-heading pr-heading d-flex align-items-center justify-content-between">
  <div><h2>Plot Receive Payment</h2><div class="bm-voucher-subtitle pr-subtitle">Selected project records · Full voucher Edit</div></div>
  <div class="d-flex align-items-center" style="gap:6px">
   <a href="{{ route('finance.daily-vouchers.approvals.index', ['type'=>'PPR']) }}" class="btn btn-outline-primary btn-sm">Approvals</a>
   <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
   @if(auth()->user()?->can('finance plot-receipt create') || auth()->user()?->can('create plot') || auth()->user()?->can('add slip'))
   <button type="button" id="prNewVoucher" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#prOriginalCreateModal"><i class="fas fa-plus mr-1"></i> New voucher</button>
   @endif
  </div>
 </div>
    <form id="prFilterForm" class="d-none" aria-hidden="true"></form>
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
    <tbody id="prHistoryRows" aria-busy="true">@include('components.finance.voucher-skeleton-rows', ['columns' => 8])</tbody>
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
    <div class="form-group col-md-6"><label for="prNumber">Voucher No.</label><input id="prNumber" class="form-control" maxlength="64" value="{{ $nextVoucherNumber }}" readonly aria-readonly="true"></div>
    <div class="form-group col-md-6"><label for="prDate">Date</label><input id="prDate" type="date" class="form-control" value="{{ now()->toDateString() }}"></div>
    <div class="form-group col-md-6"><label for="prCustomer">Customer</label><select id="prCustomer" class="form-control"><option value="">Loading customers…</option></select></div>
    <div class="form-group col-md-6"><label for="prAccount">Debit Account (Cash / Bank)</label><select id="prAccount" class="form-control"><option value="">Select active project account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }}{{ $account->subheadAccounting?->name ? ' / '.$account->subheadAccounting->name : '' }}</option>@endforeach</select></div>
    <div class="form-group col-md-6"><label for="prTypedPayment">Payment Type <span class="text-danger">*</span></label>
      <select id="prTypedPayment" class="form-control">
       <option value="1">Cash</option>
       <option value="2">Online — Confirmed</option>
       <option value="3">Cheque — Cleared</option>
      </select>
    </div>
    <div class="form-group col-md-6 pr-typed-verified d-none">
      <label>Verified Cheque / Online Source <span class="text-danger">*</span></label>
      <div class="input-group">
       <input id="prTypedPassedLabel" class="form-control" readonly placeholder="Choose confirmed or cleared source">
       <div class="input-group-append"><button type="button" id="prTypedOpenPassedPicker" class="btn btn-outline-primary">Choose Source</button></div>
      </div>
      <input type="hidden" id="prTypedPassedId" value="">
      <input type="hidden" id="prTypedPassedMethod" value="">
      <small class="text-muted">For verified payments choose a project bank receiving account. Allocations cannot exceed remaining source balance.</small>
    </div>

    <div class="form-group col-12"><label for="prNarration">Description / Narration</label><textarea id="prNarration" class="form-control" rows="2" maxlength="2000" placeholder="Payment details"></textarea></div>
   </div>
   <div class="d-flex align-items-center justify-content-between mb-2"><label for="prSearch" class="mb-0">Allocate to one or more active plot bookings</label><input id="prSearch" class="form-control form-control-sm" style="max-width:240px" placeholder="Search plots"></div>
   <div class="table-responsive pr-bookings"><table class="table table-bordered table-hover table-sm mb-0"><thead><tr><th style="width:45px">Select</th><th>Plot / Booking</th><th>Customer</th><th>Receivable account</th><th style="width:150px">Allocate amount</th></tr></thead><tbody id="prBookingRows"><tr><td colspan="5" class="text-center text-muted p-3">Select customer to load bookings.</td></tr></tbody></table></div>
   <div class="pr-stat mt-3"><small>Receipt total (allocated): </small><span class="pr-total" id="prTotal">0.00</span><div class="form-group mt-2 mb-1"><label for="prTotalWords">Amount in Words</label><input id="prTotalWords" type="text" class="form-control voucher-words-output" placeholder="Total in words" disabled readonly></div><div class="small text-muted"><span id="prSelected">0</span> plots selected · choose at least 1</div></div>
   <small class="d-block text-muted mt-2">Each plot allocation updates its mapped account. Current Edit retains the original allocation count.</small>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button id="prPost" type="button" class="btn btn-primary btn-sm" disabled><i class="fas fa-check-circle mr-1"></i> Post receipt</button></div>
 </div></div>
</div>
@include('admin.finance.voucher.original_ppr_create_modal')
<div class="modal fade" id="prDetailsModal" tabindex="-1" role="dialog" aria-labelledby="prDetailsTitle" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="prDetailsTitle">Receipt details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body" id="prDetailsBody"></div>
 </div></div>
</div>
@can('finance plot-receipt edit')
<div class="modal fade" id="prNarrationModal" tabindex="-1" role="dialog" aria-labelledby="prNarrationTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content fvc-modern-modal">
  <div class="modal-header"><h5 class="modal-title" id="prNarrationTitle">Edit voucher narration</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="prNarrationForm"><div class="modal-body">
   <p class="small text-muted">Narration only; financial postings, amounts, allocations and plot inventory are unchanged.</p>
   <label for="prNarrationValue">Narration</label><textarea id="prNarrationValue" class="form-control" maxlength="2000" rows="4" required></textarea>
   <div id="prNarrationError" class="alert alert-danger d-none mt-2"></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
   <button id="prNarrationSubmit" class="btn btn-primary btn-sm" type="submit">Save narration</button></div></form>
 </div></div>
</div>
@endcan
@can('finance plot-receipt delete')
<div class="modal fade" id="prDeleteModal" tabindex="-1" role="dialog" aria-label="Delete voucher">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content fvc-modern-modal">
  <div class="modal-header"><h5 class="modal-title">Delete voucher</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
  <form id="prDeleteForm"><div class="modal-body">
   <p class="small text-muted">The voucher is archived, not permanently erased. Linked financial entries are offset to keep balances consistent.</p>
   <label for="prDeleteReason">Reason</label><textarea id="prDeleteReason" class="form-control" required minlength="3" maxlength="200" rows="3"></textarea>
   <div id="prDeleteError" class="alert alert-danger d-none mt-2" role="alert"></div>
  </div><div class="modal-footer">
   <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
   <button id="prDeleteSubmit" type="submit" class="btn btn-danger btn-sm">Delete voucher</button>
  </div></form>
 </div></div>
</div>
@endcan
@can('finance plot-receipt reverse')
<div class="modal fade" id="prVoidModal" tabindex="-1" role="dialog" aria-labelledby="prVoidTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content fvc-modern-modal">
  <div class="modal-header"><h5 id="prVoidTitle" class="modal-title">Reverse Plot Receipt</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="prVoidForm"><div class="modal-body">
   <p class="text-muted small">This preserves the original posting and creates exact reversing entries.</p>
   <label for="prVoidReason">Reason</label><textarea id="prVoidReason" class="form-control" required minlength="3" maxlength="200" rows="3"></textarea>
   <label for="prVoidDate" class="mt-2">Effective date</label><input id="prVoidDate" type="date" class="form-control" required value="{{ now()->toDateString() }}">
   <div id="prVoidError" class="alert alert-danger d-none mt-2" role="alert"></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
   <button id="prVoidSubmit" type="submit" class="btn btn-danger">Reverse voucher</button></div></form>
 </div></div>
</div>
@endcan
@endsection

@section('js')
<script src="{{ asset('js/finance/verified-source-narration.js') }}"></script>
<script>
(function($){
 'use strict';
 $(function(){
 var $root=$('#plotReceiptApp');if(!$root.length)return;
 var bookings=[],history=[],page=1,filters={from:'',to:'',customer:'',search:''},busy=false,pendingKey=null,editReceiptId=null,editAllocations=[];
 var sort='date',direction='desc',columns=null;
 var escapeText=function(value){return $('<div>').text(value == null?'':String(value)).html();};
 var money=function(n){return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});};
 var key=function(){return 'plotreceipt'+Date.now().toString(36)+Math.random().toString(36).slice(2,14);};
 var notify=function(kind,message){
  var type=kind==='danger'?'error':kind,feedback=$.financeVoucherFeedback||{};
  if($.isFunction(feedback.toast))feedback.toast(type,message);
  else if(window.toastr&&$.isFunction(window.toastr[type]))window.toastr[type](message);
  else $('<div>',{'class':'alert alert-'+(type==='success'?'success':'danger')+' fvc-feedback-fallback',role:'alert',text:message})
   .appendTo('body').delay(4500).fadeOut(250,function(){$(this).remove();});
  $('#prAlert').attr('class','alert alert-'+kind).text(message);
 };
 var ajax=function(url,method,data){
  return $.ajax({url:url,type:method||'GET',dataType:'json',
   data:method==='POST'?JSON.stringify(data):undefined,
   contentType:method==='POST'?'application/json':undefined,
   headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}});
 };
 var getValue=function(row,field){
  var map={date:row.date,number:row.number,customer:row.customer,detail:row.narration,
   amount:row.amount,status:row.legacy
    ?(row.approval_status==='approved'?'Posted':'Pending approval')
    :row.approval_draft_id
    ?(row.approval_status==='rejected'?'Rejected':'Pending')
    :(row.reversed?'Reversed':'Posted')};
  return String(map[field]==null?'':map[field]);
 };
 var selected=function(){
  return $('.pr-check:checked').map(function(){var id=Number($(this).val());return bookings.find(function(b){return b.booking_id===id;})||null;}).get().filter(Boolean);
 };
 var recalc=function(){
  var rows=selected(),sum=0,valid=rows.length>=1;
  rows.forEach(function(b){var amount=Number($('.pr-amount[data-amount="'+b.booking_id+'"]').val())||0;sum+=amount;if(amount<=0)valid=false;});
  $('#prTotal').text(money(sum));if (window.numberToWords&&typeof numberToWords.toWords==='function')$('#prTotalWords').val(sum>0?numberToWords.toWords(sum):'');$('#prSelected').text(rows.length);
  valid=valid&&$.trim($('#prNumber').val())!==''&&$('#prDate').val()
   &&$('#prCustomer').val()&&$('#prAccount').val()
   &&$.trim($('#prNarration').val())!=='';
  var typedBank=['2','3'].includes(String($('#prTypedPayment').val()));
  if(typedBank){
   valid=valid && !!$('#prTypedPassedId').val()
      && !!$('#prTypedPassedMethod').val()
      && Number.isFinite(prTypedRemaining) && sum<=prTypedRemaining;
  }
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
  if(editReceiptId){
   editAllocations.forEach(function(a){
    $('.pr-check').filter(function(){return Number($(this).val())===Number(a.booking_id);})
     .prop('checked',true).closest('tr').addClass('pr-picked');
    $('.pr-amount[data-amount="'+a.booking_id+'"]').val(a.amount);
   });
  }
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
   var status=r.legacy ? (r.approval_status==='approved'?'Posted':'Pending approval') : r.approval_draft_id
      ?(r.approval_status==='rejected'?'Rejected':'Pending')
      :(r.reversed?'Reversed':'Posted');
   $row.append($('<td>',{'class':'text-right'}).text(money(r.amount)),$('<td>').text(status));
   if(r.approval_draft_id){
    $row.append($('<td>',{'class':'blue-actions-cell'}).text(status==='Pending'
      ?'Awaiting approval':'No posting'));
    $body.append($row);return;
   }
   if(r.legacy){
    var $legacyActions=$('<td>',{'class':'blue-actions-cell'}).append(
     $('<a>',{'class':'btn btn-sm btn-outline-primary',target:'_blank',rel:'noopener',
      href:$root.attr('data-legacy-print-base')+'/'+encodeURIComponent(r.legacy_id),
      text:'View / Print'}));
    $row.append($legacyActions);$body.append($row);return;
   }
   var $actions=$('<td>',{'class':'blue-actions-cell'}).append($('<button>',{'type':'button','class':'btn btn-sm btn-outline-primary pr-detail','data-id':r.id,text:'Details'}));
   @can('finance plot-receipt edit')
   if(!r.reversed)$actions.append($('<button>',{'type':'button','class':'btn btn-sm btn-outline-primary pr-edit ml-1','data-id':r.id,text:'Edit voucher'}));
   @endcan
      @can('finance plot-receipt delete')
   if(!r.reversed)$actions.append($('<button>',{'type':'button','class':'btn btn-sm btn-outline-danger pr-delete ml-1','data-id':r.id,text:'Delete'}));
   @endcan
   @can('finance plot-receipt reverse')
   if(r.can_reverse)$actions.append($('<button>',{'type':'button','class':'btn btn-sm btn-outline-danger pr-void ml-1','data-id':r.id,text:'Reverse'}));
   @endcan
   $row.append($actions);
   $body.append($row);
  });
  if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
  $.blueVoucherPagination.render({container:'#prPages',summary:'#prSummary',
   total:rows.length,length:length,start:start,buttonClass:'bm-voucher-page-btn',
   onPage:function(next){page=Math.floor(next/length)+1;drawHistory();}});
 };
 var loadHistory=function(){
  $('#prListError').addClass('d-none').empty();
  if($.blueTableSkeleton)$.blueTableSkeleton.show('#prHistoryRows',{rows:10,columns:8});
  return ajax($root.attr('data-history-url'),'GET').done(function(res){
   history=res.data||[];var names={};
   history.forEach(function(r){if(r.customer)names[r.customer]=true;});
   var $filter=$('#prFilterCustomer'),chosen=$filter.val();
   $filter.empty().append($('<option>',{value:'',text:'All customers'}));
   Object.keys(names).sort().forEach(function(name){$filter.append($('<option>',{value:name,text:name}));});
   $filter.val(chosen||'');page=1;drawHistory();
   if($.blueTableSkeleton)$.blueTableSkeleton.clear('#prHistoryRows');
  }).fail(function(xhr){if($.blueTableSkeleton)$.blueTableSkeleton.clear('#prHistoryRows');$('#prHistoryRows').empty().append($('<tr>').append($('<td>',{colspan:8,'class':'text-danger p-3',text:xhr.responseJSON&&xhr.responseJSON.message||'Unable to load receipts'})));
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
 $('#prLength').on('change',function(){page=1;drawHistory();});
 $('#prQuickSearch').on('input',function(){page=1;drawHistory();});
 @can('finance plot-receipt edit')
 $('#prHistoryRows').on('click','.pr-edit',function(){
  var id=$(this).attr('data-id');
  $('#prListError').addClass('d-none').text('');
  ajax($root.attr('data-details-base')+'/'+encodeURIComponent(id),'GET').done(function(result){
   var receipt=result.receipt;
   editReceiptId=Number(id);editAllocations=result.allocations||[];pendingKey=null;
   editAllocations.forEach(function(allocation){
    var mapped=bookings.find(function(b){return Number(b.booking_id)===Number(allocation.booking_id);});
    if(mapped && allocation.receivable_account_id)mapped.receivable_account_id=Number(allocation.receivable_account_id);
   });
   $('#prNumber').val(receipt.receipt_number);$('#prDate').val(String(receipt.receipt_date).slice(0,10));
   $('#prCustomer').val(String(receipt.customer_id));$('#prAccount').val(String(receipt.cash_account_id));
   $('#prNarration').val(receipt.narration);$('#prSearch').val('').prop('disabled',true);
   $('#prCreateTitle').text('Edit Plot Receive Payment');$('#prPost').text('Save voucher');
   drawBookings();$('#plotReceiptCreateModal').modal('show');
  }).fail(function(xhr){
   $('#prListError').removeClass('d-none').text(xhr.responseJSON&&xhr.responseJSON.message||'Unable to load full voucher');
  });
 });
 @endcan

 @can('finance plot-receipt edit')
  $('#prHistoryRows').on('click','.pr-narration',function(){
   var id=$(this).attr('data-id'),record=history.find(function(row){return String(row.id)===String(id);});
   if(!record)return;
   $('#prNarrationForm').data('source-id',id);
   $('#prNarrationValue').val(record.narration||'');
   $('#prNarrationError').addClass('d-none').text('');
   $('#prNarrationModal').modal('show');
  });
  $('#prNarrationForm').on('submit',function(event){
   event.preventDefault();
   var id=$(this).data('source-id'),$button=$('#prNarrationSubmit');
   if(!id||$button.prop('disabled'))return;
   $button.prop('disabled',true);
   $.ajax({
    url:$root.attr('data-details-base')+'/'+encodeURIComponent(id)+'/narration',
    type:'PATCH',contentType:'application/json',dataType:'json',
    headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
    data:JSON.stringify({narration:$.trim($('#prNarrationValue').val())})
   }).done(function(response){
    $('#prNarrationModal').modal('hide');
    notify('success',response.message||'Narration updated');loadHistory();
   }).fail(function(xhr){
    $('#prNarrationError').removeClass('d-none').text(xhr.responseJSON&&xhr.responseJSON.message||'Unable to update narration');
   }).always(function(){$button.prop('disabled',false);});
  });
  @endcan
  $('#prHistoryRows').on('click','.pr-detail',function(){showDetails($(this).attr('data-id'));});
 @can('finance plot-receipt delete')
 $('#prHistoryRows').on('click','.pr-delete',function(){
  $('#prDeleteForm').data('source-id',$(this).attr('data-id'));
  $('#prDeleteReason').val('');$('#prDeleteError').addClass('d-none').text('');
  $('#prDeleteModal').modal('show');
 });
 $('#prDeleteForm').on('submit',function(event){
  event.preventDefault();
  var id=$(this).data('source-id'),$button=$('#prDeleteSubmit');
  if(!id||$button.prop('disabled'))return;
  $button.prop('disabled',true);
  $.ajax({
   url:$root.attr('data-details-base')+'/'+encodeURIComponent(id),
   type:'DELETE',contentType:'application/json',dataType:'json',
   headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
   data:JSON.stringify({reason:$.trim($('#prDeleteReason').val())})
  }).done(function(response){
   $('#prDeleteModal').modal('hide');
   notify('success',response.message||'Voucher deleted');
   loadHistory();
  }).fail(function(xhr){
   $('#prDeleteError').removeClass('d-none').text(xhr.responseJSON&&xhr.responseJSON.message||'Unable to delete voucher');
  }).always(function(){$button.prop('disabled',false);});
 });
@endcan
 @can('finance plot-receipt reverse')
 $('#prHistoryRows').on('click','.pr-void',function(){
  $('#prVoidForm').data('source-id',$(this).attr('data-id'));
  $('#prVoidReason').val('');$('#prVoidError').addClass('d-none').text('');
  $('#prVoidModal').modal('show');
 });
 $('#prVoidForm').on('submit',function(event){
  event.preventDefault();
  var id=$(this).data('source-id'),$button=$('#prVoidSubmit');
  if(!id||$button.prop('disabled'))return;
  $button.prop('disabled',true);
  ajax($root.attr('data-void-base')+'/'+encodeURIComponent(id)+'/reverse','POST',{
   reason:$.trim($('#prVoidReason').val()),effective_date:$('#prVoidDate').val(),request_key:key()
  }).done(function(response){
   $('#prVoidModal').modal('hide');notify('success',response.message||'Receipt reversed');loadHistory();
  }).fail(function(xhr){
   var message=xhr.responseJSON&&xhr.responseJSON.message||'Unable to reverse this receipt';
   $('#prVoidError').removeClass('d-none').text(message);
  }).always(function(){$button.prop('disabled',false);});
 });
 @endcan
 // Original single-booking PPR form uses the existing booking source writer.
 var fillOriginalCustomers=function(){
  var names={},$customers=$('#prOriginalCustomer').empty();
  $customers.append($('<option>',{value:'',text:'Select customer'}));
  bookings.forEach(function(b){names[b.customer_id]=b.customer;});
  Object.keys(names).sort(function(a,b){return names[a].localeCompare(names[b]);}).forEach(function(id){
   $customers.append($('<option>',{value:id,text:names[id]}));
  });
  $('#prOriginalPlot').empty().append($('<option>',{value:'',text:'Select plot'}));
 };
 $('#prOriginalCustomer').on('change',function(){
  var id=Number($(this).val()),seen={};
  var $plots=$('#prOriginalPlot').empty().append($('<option>',{value:'',text:'Select plot'}));
  bookings.filter(function(b){return Number(b.customer_id)===id;}).forEach(function(b){
   if(seen[b.plot_id])return;seen[b.plot_id]=true;
   $plots.append($('<option>',{value:b.plot_id,text:b.plot+' · Booking #'+b.booking_id}));
  });
 });
 var prVerifiedRows=[],prVerifiedTimer=null,prVerifiedRequest=null;
 var prPickerTarget='original',prTypedRemaining=null;
 var clearTypedPprSource=function(){
  $('#prTypedPassedId,#prTypedPassedMethod,#prTypedPassedLabel').val('');
  prTypedRemaining=null;
 };
 var clearPprBankSource=function(){
  prVerifiedRows=[];
  $('#prPassedId,#prPassedMethod,#prPassedLabel').val('');
 };
 var loadPprBankSources=function(){
  var selectedPayment=String(prPickerTarget==='typed'
    ?$('#prTypedPayment').val():$('#prOriginalPayment').val());
  var method=selectedPayment==='2'?'online':(selectedPayment==='3'?'cheque':'');
  if(!method)return;
  if(prVerifiedRequest&&prVerifiedRequest.readyState!==4)prVerifiedRequest.abort();
  $('#prPassedError').addClass('d-none').text('');
  var $tbody=$('#prPassedRows').empty().append($('<tr>').append($('<td>',{colspan:5,'class':'text-center text-muted',text:'Loading verified sources…'})));
  $('#prApplyPassed').prop('disabled',true);
  prVerifiedRequest=$.ajax({
   url:@json(url('admin/finance/voucher/passed-sources')),
   type:'GET',dataType:'json',headers:{Accept:'application/json'},
   data:{method:method,direction:'receipt',voucher_series:'PPR',search:$('#prPassedSearch').val()}
  }).done(function(result){
   prVerifiedRows=result.data||[];
   $tbody.empty();
   if(!prVerifiedRows.length)$tbody.append($('<tr>').append($('<td>',{colspan:5,'class':'text-center text-muted',text:'No eligible verified source with remaining balance.'})));
   $.each(prVerifiedRows,function(_,item){
    var $row=$('<tr>');
    $row.append($('<td>').append($('<input>',{type:'radio',name:'prPassedChoice',value:item.id,'aria-label':'Select source '+item.reference})));
    $row.append($('<td>').text(item.reference||'—'));
    $row.append($('<td>').text(item.passing_date||'—'));
    $row.append($('<td>').text(item.remaining_amount||'0.00'));
    $row.append($('<td>').text(item.bank||'—'));
    $tbody.append($row);
   });
  }).fail(function(xhr,status){
   if(status==='abort')return;
   $tbody.empty();
   $('#prPassedError').removeClass('d-none').text((xhr.responseJSON||{}).message||'Unable to load verified sources.');
  });
 };
 $('#prOriginalPayment').on('change.pprBankPicker',function(){
  prPickerTarget='original';
  $.financeVerifiedSourceNarration.set($('#prOriginalForm [name="detail"]'),null);
  clearPprBankSource();
  $('#prPassedSearch').val('');
  if(['2','3'].includes(String($(this).val()))){
   $('#prPassedPickerModal').modal('show');
  }
 });
 $('#prOpenPassedPicker').on('click',function(){
  prPickerTarget='original';
  if(['2','3'].includes(String($('#prOriginalPayment').val())))$('#prPassedPickerModal').modal('show');
 });
 $('#prTypedPayment').on('change',function(){
  prPickerTarget='typed';
  $.financeVerifiedSourceNarration.set($('#prNarration'),null);
  clearTypedPprSource();
  $('.pr-typed-verified').toggleClass('d-none',String($(this).val())==='1');
  if(['2','3'].includes(String($(this).val())) && !editReceiptId){
   $('#prPassedSearch').val('');$('#prPassedPickerModal').modal('show');
  }
  pendingKey=null;recalc();
 });
 $('#prTypedOpenPassedPicker').on('click',function(){
  prPickerTarget='typed';
  if(['2','3'].includes(String($('#prTypedPayment').val())))
   $('#prPassedPickerModal').modal('show');
 });
 $('#prPassedPickerModal').on('show.bs.modal',function(){
  $(this).css('z-index',1065);
  loadPprBankSources();
 }).on('shown.bs.modal',function(){
  $('.modal-backdrop').last().css('z-index',1060);
  $('body').addClass('modal-open');
 }).on('hidden.bs.modal',function(){
  if($('#prOriginalCreateModal').hasClass('show')
     || $('#plotReceiptCreateModal').hasClass('show'))$('body').addClass('modal-open');
 });
 $('#prPassedSearch').on('input',function(){
  clearTimeout(prVerifiedTimer);
  prVerifiedTimer=setTimeout(loadPprBankSources,250);
 });
 $('#prPassedRows').on('change','input[name="prPassedChoice"]',function(){
  $('#prApplyPassed').prop('disabled',!$(this).val());
 });
 $('#prApplyPassed').on('click',function(){
  var id=$('#prPassedRows input[name="prPassedChoice"]:checked').val();
  var item=prVerifiedRows.find(function(row){return String(row.id)===String(id);});
  if(!item)return;
  var narration=$.financeVerifiedSourceNarration.set($('#prOriginalForm [name="detail"]'),item);
  if(!narration.ok){
   $('#prPassedError').removeClass('d-none').text(narration.message);
   return;
  }
  if(prPickerTarget==='typed'){
   var typedNarration=$.financeVerifiedSourceNarration.set($('#prNarration'),item);
   if(!typedNarration.ok){
    $('#prPassedError').removeClass('d-none').text(typedNarration.message);
    return;
   }
   $('#prTypedPassedId').val(item.id);$('#prTypedPassedMethod').val(item.method);
   $('#prTypedPassedLabel').val((item.method==='online'?'Online: ':'Cheque: ')+
      item.reference+' · Available '+item.remaining_amount);
   prTypedRemaining=Number(item.remaining_amount);
   $('#prPassedPickerModal').modal('hide');
   pendingKey=null;recalc();
   return;
  }
  $('#prPassedId').val(item.id);$('#prPassedMethod').val(item.method);
  $('#prPassedLabel').val((item.method==='online'?'Online: ':'Cheque: ')+item.reference+' · Available '+item.remaining_amount);
  $('#prOriginalForm [name="t_number"]').val(item.reference);
  $('#prOriginalForm [name="passing_date"]').val(String(item.passing_date||'').slice(0,10));
  // PPR slip reference remains its own unique receipt identity. The bank
  // instrument reference is recorded separately as t_number + immutable claim.
  // The slip reference is a separate, required operator-supplied identity.
  // Do NOT copy a project-specific voucher preview here: it can collide
  // with another project's PPR slip in the global legacy ledger lookup.
  $('#prOriginalForm [name="amount"]').val(item.remaining_amount).attr('max',item.remaining_amount);
  // Preserve typed PPR narration. The verified source evidence has been
  // appended once, not replaced with the management purpose.
  if(item.method==='cheque')$('#prOriginalForm [name="cheque_purpose"]').val(item.purpose||'');
  var $bank=$('#prOriginalForm [name="bank_id"]'),bankText=String(item.bank||'').trim().toLowerCase();
  if(bankText){
   var option=$bank.find('option').filter(function(){return $(this).text().trim().toLowerCase()===bankText;}).first();
   if(option.length)$bank.val(option.val()).trigger('change');
  }
  $('#prPassedPickerModal').modal('hide');
 });
 $('#prOriginalPayment').on('change',function(){
  var bank=String($(this).val())!=='1',cheque=String($(this).val())==='3';
  $('.pr-original-bank').toggleClass('d-none',!bank);
  $('.pr-original-cheque').toggleClass('d-none',!cheque);
  // Keep the opposite posting account limited to Cash for cash receipts
  // and active selected-project Banks for cleared Cheque/Online receipts.
  var $counter=$('#prOriginalCounterAccount');
  $counter.find('option').each(function(){
   var $option=$(this);
   if(!$option.val())return;
   var allowed=bank?Number($option.attr('data-bank'))===1
                   :Number($option.attr('data-cash'))===1;
   $option.prop('disabled',!allowed).toggleClass('d-none',!allowed);
  });
  if($counter.find('option:selected').prop('disabled')){
   $counter.val('').trigger('change');
  }
  $('#prOriginalForm [name="t_number"],#prOriginalForm [name="bank_id"],#prOriginalForm [name="passing_date"]').prop('required',bank);
  $('#prOriginalForm [name="cheque_purpose"]').prop('required',cheque);
 });
 $('#prNewVoucher').on('click.originalPpr',function(){
  $('#prOriginalForm')[0].reset();$('#prOriginalError').addClass('d-none').text('');
  fillOriginalCustomers();$('#prOriginalPayment').trigger('change');
  ajax($root.attr('data-number-preview-url'),'GET').done(function(data){
   $('#prOriginalNumber').val(data.voucher_number);
  }).fail(function(){
   $('#prOriginalError').removeClass('d-none').text('Cannot refresh PPR number.');
  });
 });
 $('#prOriginalForm').on('submit',function(event){
  event.preventDefault();
  var bankPayment=['2','3'].includes(String($('#prOriginalPayment').val()));
  if(bankPayment&&(!$('#prPassedId').val()||!$('#prPassedMethod').val())){
   $('#prOriginalError').removeClass('d-none').text('Select a cleared Cheque or confirmed Online transaction before saving.');
   $('#prPassedPickerModal').modal('show');return;
  }

  var $form=$(this),$save=$('#prOriginalSave').prop('disabled',true);
  $('#prOriginalError').addClass('d-none');
  $.ajax({url:$form.attr('action'),type:'POST',data:$form.serialize(),dataType:'json',
   headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
   .done(function(response){
    $('#prOriginalCreateModal').modal('hide');
    notify('success',response.message||'PPR voucher saved.');
    loadHistory();
   }).fail(function(xhr){
    var data=xhr.responseJSON||{};
    $('#prOriginalError').removeClass('d-none')
     .text(data.message||Object.values(data.errors||{}).flat().join(' ')||'PPR voucher could not be saved.');
   }).always(function(){$save.prop('disabled',false);});
 });
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
   payment_type:Number($('#prTypedPayment').val())||1,
   passed_source_method:$('#prTypedPassedMethod').val()||null,
   passed_source_id:$('#prTypedPassedId').val()?Number($('#prTypedPassedId').val()):null,
   allocations:state.rows.map(function(row){return {booking_id:row.booking_id,plot_id:row.plot_id,
    receivable_account_id:row.receivable_account_id,
    amount:Number($('.pr-amount[data-amount="'+row.booking_id+'"]').val()).toFixed(2)};})};
  var url=editReceiptId?$root.attr('data-details-base')+'/'+editReceiptId:$root.attr('data-post-url');
  $.ajax({url:url,type:editReceiptId?'PATCH':'POST',contentType:'application/json',dataType:'json',
   headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
   data:JSON.stringify(payload)}).done(function(res){
   var wasEdit=!!editReceiptId;
   pendingKey=null;editReceiptId=null;editAllocations=[];$('#plotReceiptCreateModal').modal('hide');$('#prNumber,#prNarration').val('');
   notify('success',res.message||(wasEdit?'Plot Receipt updated.':'Plot Receipt posted.'));
   $('#prCustomer').val('');$('#prSearch').val('');drawBookings();loadHistory();
  }).fail(function(xhr){
   var error=xhr.responseJSON||{};
   notify('danger',error.message||Object.values(error.errors||{}).flat().join(' ')||'Receipt was rejected');$('#prAlert').removeClass('d-none');
  }).always(function(){busy=false;recalc();});
 });
 loadBookings();loadHistory();
 });
})(jQuery);
</script>
@endsection
