@extends('admin.layouts.master')
@section('title','Online Transaction Management')
@section('content')
<style>
html.online-page-active,body.online-page-active{height:100%;overflow:hidden!important}
body.online-page-active .main-footer{position:fixed!important;bottom:0;left:0;right:0;z-index:10;margin-bottom:0!important}
.main-content.page-content:has(> .online-register-page){height:auto!important;min-height:0!important;max-height:none!important;overflow:hidden!important;padding:0!important;margin-bottom:0!important}
.online-register-page{--online-green:#168348;--online-border:#b9e1c6;height:var(--online-height,calc(100dvh - 165px));max-height:var(--online-height,calc(100dvh - 165px));min-height:0;display:flex;flex-direction:column;overflow:hidden}
.online-register-page>.container-fluid{display:flex;flex-direction:column;flex:1;min-height:0}
.online-heading{background:#eaf7ee;border-bottom:2px solid var(--online-green);padding:12px 15px;flex-shrink:0}
.online-heading h4{font-size:21px;font-weight:700;color:#087a38}
.online-filter,.online-table{border:1px solid var(--online-border);border-top:3px solid var(--online-green);border-radius:12px;background:white;padding:12px;margin-bottom:9px}
.online-filter{flex-shrink:0}
.online-filter-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr)) minmax(0,1.2fr) repeat(2,minmax(0,.9fr)) minmax(0,1.2fr) auto;gap:10px;align-items:end}
.online-filter-row>div{min-width:0}
.online-filter label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}
.online-filter-actions{display:flex;gap:5px;align-items:end;height:31px;white-space:nowrap}
.online-table{flex:1;min-height:0;display:flex;flex-direction:column;margin-bottom:0}
.online-table>.table-responsive{flex:1;min-height:80px;overflow:auto}
.online-table>.d-flex:last-child{flex-shrink:0}
.online-table .table{margin-bottom:0}
.online-table thead th{position:sticky;top:0;z-index:1;background:#e6f4eb;border-bottom:2px solid #168348;white-space:nowrap}
.online-table thead .online-column-filters th{top:32px;background:#e6f4eb;z-index:2;padding:4px}
.online-table thead .online-column-filters input,.online-table thead .online-column-filters select{display:block;width:100%;min-width:74px;height:27px!important;min-height:27px!important;max-height:27px!important;font-size:11px;padding:3px 5px;line-height:18px;margin:0;box-sizing:border-box}
.online-table thead .online-column-filters th{height:36px!important;max-height:36px!important;vertical-align:middle!important}
.online-table thead .online-column-filters select.online-native-column-select{appearance:auto!important;-webkit-appearance:menulist!important;background:white!important;cursor:pointer}
.online-table thead .online-column-filters .ts-wrapper{display:none!important}
.online-table thead .online-column-filters th:nth-child(2){min-width:126px}
.online-table thead .online-column-filters th:nth-child(3){min-width:110px}
.online-table thead .online-column-filters th:nth-child(5),.online-table thead .online-column-filters th:nth-child(6),.online-table thead .online-column-filters th:nth-child(7),.online-table thead .online-column-filters th:nth-child(8){min-width:145px}
.online-table thead .online-column-filters th:nth-child(9){min-width:95px}
.online-table td{vertical-align:middle}
.online-table thead th:last-child{width:72px;min-width:72px;text-align:center}
.online-table tbody tr:nth-child(even){background:#f7fbf8}
.online-ref{font-weight:700;color:#087a38}
.online-badge{display:inline-block;padding:3px 7px;border-radius:4px;font-size:11px;font-weight:700}
.online-receipt{background:#e0f4e9;color:#087139;border:1px solid #96d3ae}
.online-payment{background:#ffebdd;color:#995000;border:1px solid #f0bc93}
.online-pending{background:#6c757d;color:white}
.online-confirmed{background:#168348;color:white}
.online-register-page .ts-wrapper{width:100%;min-width:0}
.online-register-page .ts-control{min-height:31px;border-radius:4px;padding:4px 9px;font-size:12px}
#online-entry-modal .ts-wrapper{width:100%;min-width:0}
#online-entry-modal .ts-control{min-height:31px;border-radius:4px;padding:4px 9px;font-size:13px}
.online-register-page select.tomselected,#online-entry-modal select.tomselected{display:none!important;position:absolute!important;width:1px!important;height:1px!important}
#online-entry-modal .modal-dialog{max-width:970px}
#online-confirm-modal .modal-header,#online-history-modal .modal-header{background:#eaf7ee;border-bottom:2px solid #168348}
#online-confirm-modal .modal-title,#online-history-modal .modal-title{color:#087a38;font-weight:700}
#online-confirm-modal .modal-body label{font-size:12px;font-weight:700}
.online-confirm-error{font-size:12px;color:#dc3545}
.online-history-table{max-height:55vh;overflow:auto}
.online-history-table thead th{position:sticky;top:0;background:#eaf7ee}

#online-entry-modal .modal-header{background:#eaf7ee;border-bottom:2px solid #168348}
#online-entry-modal .modal-title{color:#087a38;font-weight:700}
#online-entry-modal label{font-size:12px;font-weight:700}
#online-entry-modal .modal-body{max-height:75vh;overflow-y:auto}
#online-entry-form .is-invalid,#online-entry-form .ts-wrapper.is-invalid .ts-control{border-color:#dc3545!important}
#online-entry-form .online-field-error{font-size:11px;color:#dc3545;display:block;margin-top:3px}
.online-upload{min-height:66px;border:1px dashed #8cbea0;border-radius:7px;background:#f3faf5;padding:9px;text-align:center;cursor:pointer}
.online-upload input{position:absolute;opacity:0;width:1px;height:1px}
.online-upload label{cursor:pointer;display:block}
#online-toast{position:fixed;top:85px;right:20px;z-index:2200;display:none;max-width:390px}
.swal2-container{z-index:20000!important}
@media(max-width:1199px){.online-filter-row{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:650px){.online-filter-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="content-wrapper bm-ui online-register-page">
 <div class="container-fluid px-2 pt-2">
  <div class="online-heading bm-page-heading d-flex justify-content-between align-items-center flex-wrap">
   <div><h4 class="mb-0">Online Transaction Management</h4><small class="text-muted">Selected project records · Pending Source Register</small></div>
   @can('create voucher')<button class="btn btn-success btn-sm" type="button" id="online-add"><i class="fas fa-plus mr-1"></i> Add Transaction</button>@endcan
  </div>
  <div class="online-filter bm-card bm-filter mt-2">
   <form id="online-filters" autocomplete="off">
    <div class="online-filter-row">
     <div><label for="online-from">From</label><input type="date" name="from" id="online-from" class="form-control form-control-sm"></div>
     <div><label for="online-to">To</label><input type="date" name="to" id="online-to" class="form-control form-control-sm"></div>
     <div><label for="online-account-filter">Child Account</label><select id="online-account-filter" name="account_mapping_id" class="js-tomselect form-select form-select-sm"><option value="">All accounts</option>@foreach($mappings as $account)<option value="{{ $account['id'] }}">{{ $account['head_name'] }} / {{ $account['child_name'] }}</option>@endforeach</select></div>
     <div><label for="online-type-filter">Type</label><select id="online-type-filter" name="type" class="js-tomselect form-select form-select-sm"><option value="">All types</option><option value="receipt">Receipt (IN)</option><option value="payment">Payment (OUT)</option></select></div>
     <div><label for="online-status-filter">Status</label><select id="online-status-filter" name="status" class="js-tomselect form-select form-select-sm"><option value="">All statuses</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option></select></div>
     <div><label for="online-search">Search</label><input name="search" id="online-search" class="form-control form-control-sm" placeholder="Reference, bank, party, purpose"></div>
     <div class="online-filter-actions"><button type="submit" class="btn btn-success btn-sm">Apply</button><button type="button" class="btn btn-outline-secondary btn-sm" id="online-reset">Reset</button></div>
    </div>
   </form>
  </div>
  <div class="online-table bm-card">
   <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
    <div class="d-flex align-items-center"><strong class="mr-2">Show</strong><select id="online-per-page" class="js-tomselect form-select form-select-sm mr-2" style="width:80px"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select><span>entries</span></div>
    <div><label for="online-quick-search" class="mb-0">Search:</label><input id="online-quick-search" class="form-control form-control-sm ml-1 d-inline-block" style="width:185px" placeholder="Search transactions"></div>
   </div>
   <div class="table-responsive">
    <table class="table bm-table table-bordered table-hover table-sm">
     <thead>
      <tr><th>#</th><th>Date</th><th>Transaction Ref</th><th>Type</th><th>Sending Bank</th><th>Receiving Bank</th><th>Sender / Receiver</th><th>Purpose</th><th>Amount</th><th>Status</th><th>Proof</th><th>Actions</th></tr>
      <tr class="online-column-filters">
       <th></th>
       <th><input type="date" class="form-control form-control-sm" data-column="transaction_date" aria-label="Filter by transaction date"></th>
       <th><input type="text" class="form-control form-control-sm" data-column="reference" placeholder="Filter" aria-label="Filter transaction reference" maxlength="120"></th>
       <th><select class="form-control form-control-sm online-native-column-select" data-column="type" aria-label="Filter transaction type"><option value="">All</option><option value="receipt">IN</option><option value="payment">OUT</option></select></th>
       <th><input type="text" class="form-control form-control-sm" data-column="sending_bank" placeholder="Filter" aria-label="Filter sending bank" maxlength="150"></th>
       <th><input type="text" class="form-control form-control-sm" data-column="receiving_bank" placeholder="Filter" aria-label="Filter receiving bank" maxlength="150"></th>
       <th><input type="text" class="form-control form-control-sm" data-column="party" placeholder="Filter" aria-label="Filter sender or receiver" maxlength="150"></th>
       <th><input type="text" class="form-control form-control-sm" data-column="purpose" placeholder="Filter" aria-label="Filter purpose" maxlength="150"></th>
       <th><input type="number" class="form-control form-control-sm" data-column="amount" placeholder="Amount" aria-label="Filter exact amount" min="0" step="0.01"></th>
       <th><select class="form-control form-control-sm online-native-column-select" data-column="status" aria-label="Filter status"><option value="">All</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option></select></th>
       <th></th><th></th>
      </tr>
     </thead>
     <tbody id="online-rows"><tr><td colspan="12" class="text-muted text-center">Loading transactions...</td></tr></tbody>
    </table>
   </div>
   <div class="d-flex justify-content-between align-items-center mt-2"><small class="text-muted" id="online-count">Loading...</small><div id="online-pages" class="btn-group btn-group-sm"></div></div>
  </div>
 </div>
</div>
@can('create voucher')
<div class="modal fade" id="online-entry-modal" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Online Transaction</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="online-entry-form" method="POST" action="{{ route('finance.online.store') }}" enctype="multipart/form-data" novalidate>@csrf
   <div class="modal-body"><div class="row">
    <div class="form-group col-md-4"><label>Transaction Type *</label><select id="online-entry-type" name="type" class="js-tomselect form-select form-select-sm" required><option value="receipt">Receipt (IN)</option><option value="payment">Payment (OUT)</option></select></div>
    <div class="form-group col-md-4"><label>Transaction ID / Reference *</label><input name="reference" class="form-control form-control-sm" maxlength="120" required placeholder="e.g. TXN-2026-123"></div>
    <div class="form-group col-md-4"><label>Transaction Date *</label><input type="date" name="transaction_date" class="form-control form-control-sm" required value="{{ date('Y-m-d') }}"></div>
    <div class="form-group col-md-4"><label>Amount (Rs.) *</label><input type="number" min="0.01" step="0.01" name="amount" class="form-control form-control-sm" required placeholder="0.00"></div>
    <div class="form-group col-md-4"><label>Sending Bank *</label><input name="sending_bank" class="form-control form-control-sm" required maxlength="150"></div>
    <div class="form-group col-md-4"><label>Sending Account *</label><input name="sending_account" class="form-control form-control-sm" required maxlength="150"></div>
    <div class="form-group col-md-4"><label>Receiving Bank *</label><input name="receiving_bank" class="form-control form-control-sm" required maxlength="150"></div>
    <div class="form-group col-md-4"><label>Receiving Account *</label><input name="receiving_account" class="form-control form-control-sm" required maxlength="150"></div>
    <div class="form-group col-md-4"><label>Sender Name *</label><input name="sender_name" class="form-control form-control-sm" required maxlength="150"></div>
    <div class="form-group col-md-4"><label>Receiver Name *</label><input name="receiver_name" class="form-control form-control-sm" required maxlength="150"></div>
    <div class="form-group col-md-4"><label>Head Account *</label><select id="online-head" name="head_accounting_id" class="js-tomselect form-select form-select-sm" required><option value="">Select Head</option>@foreach($heads as $head)<option value="{{ $head['head_id'] }}">{{ $head['head_name'] }}</option>@endforeach</select></div>
    <div class="form-group col-md-4"><label>Child Account *</label><select id="online-child" name="subhead_accounting_id" class="js-tomselect form-select form-select-sm" required><option value="">Select Child</option>@foreach($mappings as $account)<option value="{{ $account['child_id'] }}">{{ $account['child_name'] }} — {{ $account['head_name'] }}</option>@endforeach</select></div>
    <div class="form-group col-md-8"><label>Description / Purpose *</label><textarea name="purpose" class="form-control form-control-sm" maxlength="500" rows="2" required></textarea></div>
    <div class="form-group col-md-4"><label>Supporting Proof (PDF / JPG / PNG, max 5 MB)</label><div class="online-upload"><label for="online-proof"><i class="fas fa-cloud-upload-alt text-success"></i> <strong class="text-success">Choose file</strong><span id="online-file-name" class="d-block small text-muted">No file selected</span></label><input type="file" name="proof" id="online-proof" accept=".pdf,.jpg,.jpeg,.png"></div></div>
   </div>
   <div id="online-errors" class="text-danger small" role="alert"></div>
   <small class="text-muted">Saving only creates a Pending source. No bank posting or accounting voucher will be generated.</small>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" id="online-save" class="btn btn-success">Save Transaction</button></div>
  </form>
 </div></div>
</div>
@endcan
<div class="modal fade" id="online-history-modal" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Online Transaction History</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body"><p class="font-weight-bold" id="online-history-ref"></p>
   <div id="online-eligibility-status" class="alert alert-light border small" role="status">Loading source eligibility...</div>
   <div class="table-responsive online-history-table"><table class="table table-sm table-bordered">
    <thead><tr><th>Time / Actor</th><th>From → To</th><th>Bank Statement Ref</th><th>Verification Date</th><th>Original Amount</th><th>Remarks</th></tr></thead>
    <tbody id="online-history-rows"></tbody></table></div>
  </div>
 </div></div>
</div>
@can('create voucher')
<div class="modal fade" id="online-confirm-modal" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Confirm Online Transaction</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="online-confirm-form" novalidate>@csrf
   <div class="modal-body">
    <p><strong>Reference:</strong> <span id="online-confirm-ref"></span></p>
    <p><strong>Amount (fixed):</strong> Rs. <span id="online-confirm-amount"></span></p>
    <div id="online-confirm-notice" class="alert alert-warning small"></div>
    <div class="form-group"><label for="online-verify-date">Bank Verification Date *</label>
     <input type="date" id="online-verify-date" name="verification_date" class="form-control form-control-sm" required></div>
    <div class="form-group"><label for="online-statement-reference">Bank Statement / Audit Reference *</label>
     <input id="online-statement-reference" name="statement_reference" class="form-control form-control-sm" maxlength="150" required></div>
    <div class="custom-control custom-checkbox mb-3">
     <input type="checkbox" class="custom-control-input" id="online-verified" name="statement_verified" value="1" required>
     <label class="custom-control-label" for="online-verified">Maine bank statement / account audit mein payment verify ki hai *</label>
    </div>
    <div class="form-group"><label for="online-verify-remarks">Verification Remarks (optional)</label>
     <textarea name="remarks" id="online-verify-remarks" maxlength="2000" rows="2" class="form-control form-control-sm"></textarea></div>
    <div class="online-confirm-error" id="online-confirm-errors" role="alert"></div>
    <small class="text-muted">Confirmation sirf status/audit update karegi. Bank voucher ya ledger posting nahi hogi.</small>
   </div>
   <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button>
    <button class="btn btn-success" id="online-confirm-submit" type="submit">Verify & Confirm</button></div>
  </form>
 </div></div>
</div>
@endcan
<div id="online-toast" class="alert" role="status"></div>
@endsection
@push('script')
{{-- Same SweetAlert2 asset used by the existing CR/Cheque voucher dialogs. --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
 'use strict';
 $('html,body').addClass('online-page-active');
 var $entry=$('#online-entry-form'),$modal=$('#online-entry-modal'),$rows=$('#online-rows');
 var request=null,sequence=0,busy=false,searchTimer=null,confirmBusy=false,confirmRecord=null;
 var confirmUrlTemplate=@json(route('finance.online.confirm',['id'=>'__ONLINE_ID__']));
 var historyUrlTemplate=@json(route('finance.online.history',['id'=>'__ONLINE_ID__']));
 var mappings=@json($mappings);
 function fit(){
  var $page=$('.online-register-page');
  if(!$page.length||!$page.offset())return;
  var footer=$('.main-footer').outerHeight()||40;
  $page.css('--online-height',Math.max(230,$(window).height()-$page.offset().top-footer-1)+'px');
 }
 fit();$(window).on('resize.onlinePage',fit);
 function toast(message,error){
  if(window.toastr&&$.isFunction(window.toastr.error)){
   if(error)window.toastr.error(message);else window.toastr.success(message);
  } else $('#online-toast').toggleClass('alert-danger',!!error).toggleClass('alert-success',!error)
     .text(message).stop(true,true).fadeIn(150).delay(3500).fadeOut(200);
 }
 function tsSet($field,value){
  if(!$field.length)return;
  var ts=$field[0].tomselect;
  if(ts)ts.setValue(String(value||''),true);else $field.val(String(value||''));
 }
 function initSelects(){
  if(typeof window.TomSelect==='undefined'){toast('TomSelect failed to load. Refresh the page.',true);return;}
  $('#online-filters select,#online-per-page,#online-entry-modal select').each(function(){
   if(this.tomselect)return;
   var el=this;
   new TomSelect(el,{persist:false,create:false,maxItems:1,allowEmptyOption:true,
    onChange:function(){$(el).trigger('change');}});
  });
 }
 function children(headId,keep){
  var $field=$('#online-child'),old=keep?String($field.val()||''):'';
  var ts=$field.length?$field[0].tomselect:null;
  if(ts){ts.clear(true);ts.clearOptions();}
  $field.empty().append($('<option>').val('').text('Select Child'));
  if(ts)ts.addOption({value:'',text:'Select Child'});
  $.each(mappings,function(_,item){
   if(!headId||String(item.head_id)===String(headId)){
    var text=item.child_name+' — '+item.head_name;
    $field.append($('<option>').val(String(item.child_id)).text(text));
    if(ts)ts.addOption({value:String(item.child_id),text:text});
   }
  });
  var exists=old&&$field.find('option').filter(function(){return $(this).val()===old;}).length;
  tsSet($field,exists?old:'');
  if(ts)ts.refreshOptions(false);
 }
 $('#online-head').on('change',function(){if(!$(this).data('syncing'))children($(this).val(),false);});
 $('#online-child').on('change',function(){
  var value=$(this).val();if(!value)return;
  var $head=$('#online-head'),match=$.grep(mappings,function(item){
   return String(item.child_id)===String(value)&&(!$head.val()||String(item.head_id)===String($head.val()));
  })[0]||$.grep(mappings,function(item){return String(item.child_id)===String(value);})[0];
  if(match&&String($head.val())!==String(match.head_id)){
   $head.data('syncing',true);tsSet($head,match.head_id);$head.removeData('syncing');
   children(match.head_id,true);tsSet($('#online-child'),value);
  }
 });
 initSelects();
 function fieldError($field,message){
  $field.addClass('is-invalid').attr('aria-invalid','true');
  $field.next('.ts-wrapper').addClass('is-invalid');
  var $group=$field.closest('.form-group');
  $group.find('.online-field-error').remove();
  $('<small>').addClass('online-field-error').text(message).appendTo($group);
 }
 function clearErrors(){
  $entry.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
  $entry.find('.ts-wrapper.is-invalid').removeClass('is-invalid');
  $entry.find('.online-field-error').remove();
  $('#online-errors').empty();
 }
 $entry.on('input change','input[name],textarea[name],select[name]',function(){
  if($(this).hasClass('is-invalid')){
   $(this).removeClass('is-invalid').removeAttr('aria-invalid');
   $(this).next('.ts-wrapper').removeClass('is-invalid');
   $(this).closest('.form-group').find('.online-field-error').remove();
  }
 });
 function validate(){
  clearErrors();
  var valid=true,first=null;
  $entry.find('input[name],select[name],textarea[name]').not('[type=hidden]').each(function(){
   var $f=$(this),val=$.trim(String($f.val()||'')),msg='';
   if($f.prop('required')&&!val)msg='This field is required.';
   else if(val&&this.validity&&!this.validity.valid)msg='Please enter a valid value.';
   if(msg){fieldError($f,msg);valid=false;if(!first)first=$f;}
  });
  var $amount=$entry.find('[name=amount]'),num=Number($amount.val());
  if(!$amount.hasClass('is-invalid')&&(!Number.isFinite(num)||num<=0)){
   fieldError($amount,'Enter a positive amount.');valid=false;if(!first)first=$amount;
  }
  var files=$('#online-proof').prop('files');
  if(files&&files.length&&(!/\.(pdf|jpg|jpeg|png)$/i.test(files[0].name)||files[0].size>5*1024*1024)){
   fieldError($('#online-proof'),'Proof must be PDF/JPG/PNG and at most 5 MB.');valid=false;
   if(!first)first=$('#online-proof');
  }
  if(first)first.trigger('focus');
  return valid;
 }
 $('#online-proof').on('change',function(){
  var files=$(this).prop('files');
  $('#online-file-name').text(files&&files.length?files[0].name:'No file selected');
 });
 function resetEntry(){
  $entry.trigger('reset');
  tsSet($('#online-entry-type'),'receipt');
  tsSet($('#online-head'),'');
  children('',false);
  $('#online-file-name').text('No file selected');
  clearErrors();
 }
 $('#online-add').on('click',function(){clearErrors();$modal.modal('show');});
 function filters(page){
  var payload={page:page,per_page:$('#online-per-page').val()};
  $.each($('#online-filters').serializeArray(),function(_,f){if(f.value)payload[f.name]=f.value;});
  var quick=$.trim($('#online-quick-search').val());
  if(quick)payload.search=quick;
  $('.online-column-filters [data-column]').each(function(){
   var value=$.trim(String($(this).val()||''));
   if(value)payload['column_'+$(this).attr('data-column')]=value;
  });
  return payload;
 }
 function renderRow(x,idx){
  var $r=$('<tr>');
  $('<td>').text(idx).appendTo($r);
  $('<td>').text(x.transaction_date||'').appendTo($r);
  $('<td>').addClass('online-ref').text(x.reference||'').appendTo($r);
  $('<td>').append($('<span>').addClass('online-badge online-'+x.type).text(x.type==='receipt'?'Receipt IN':'Payment OUT')).appendTo($r);
  $('<td>').text(x.sending_bank||'').appendTo($r);
  $('<td>').text(x.receiving_bank||'').appendTo($r);
  $('<td>').text((x.sender_name||'')+' / '+(x.receiver_name||'')).appendTo($r);
  $('<td>').text(x.purpose||'').appendTo($r);
  $('<td>').addClass('text-right').text(Number(x.amount).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2})).appendTo($r);
  $('<td>').append($('<span>').addClass('online-badge online-'+x.status).text(x.status||'pending')).appendTo($r);
  var $proof=$('<td>');
  if(x.has_proof && x.proof_url)$('<a>').attr({href:x.proof_url,target:'_blank',rel:'noopener'}).text('Download').addClass('btn btn-outline-primary btn-sm').appendTo($proof);
  else $proof.addClass('text-muted').text('—');
  $proof.appendTo($r);
  var $actions=$('<td>').addClass('blue-actions-cell');
  @can('create voucher')
  if(x.status==='pending'){
   $('<button type="button">').addClass('btn btn-outline-success btn-sm mr-1 online-confirm-button')
    .attr({'data-id':x.id,'data-reference':x.reference,'data-amount':x.amount,
     'data-type':x.type,'data-date':x.transaction_date})
    .text('Confirm').appendTo($actions);
  }
  @endcan
  $('<button type="button">').addClass('btn btn-outline-secondary btn-sm online-history-button')
   .attr({'data-id':x.id,'data-reference':x.reference}).text('History').appendTo($actions);
  $actions.appendTo($r);
  return $r;
 }
 function pageLinks(data){
  var $nav=$('#online-pages').empty(),page=data.current_page||1,last=data.last_page||1;
  function btn(text,p,disabled,active){
   $('<button type="button">').addClass('btn '+(active?'btn-success':'btn-outline-success'))
    .prop('disabled',disabled).text(text).on('click',function(){load(p);}).appendTo($nav);
  }
  btn('Previous',page-1,page<=1,false);
  for(var p=Math.max(1,page-2);p<=Math.min(last,page+2);p++)btn(String(p),p,false,p===page);
  btn('Next',page+1,page>=last,false);
  $('#online-count').text(data.total?'Showing '+data.from+'–'+data.to+' of '+data.total+' records':'Showing 0 records');
 }
 function load(page){
  var seq=++sequence;
  if(request)request.abort();
  $.blueTableSkeleton.show($rows,{rows:10,columns:12,tone:'credit'});
  $('#online-count').text('Loading records...');$('#online-pages').empty();
  request=$.ajax({url:@json(route('finance.online.data')),dataType:'json',data:filters(page)})
   .done(function(data){
    if(seq!==sequence)return;
    $.blueTableSkeleton.clear($rows);
    $.blueActionMenu.close();
    $rows.empty();
    $.each(data.data||[],function(i,x){$rows.append(renderRow(x,(data.current_page-1)*data.per_page+i+1));});
    if(!(data.data||[]).length)$rows.append($('<tr>').append($('<td colspan="12" class="text-center text-muted py-4">').text('No matching online transactions.')));
    $.blueActionMenu.enhance($rows.find('> tr > td.blue-actions-cell'));
    pageLinks(data);
   }).fail(function(xhr,status){
    if(status==='abort'||seq!==sequence)return;
    $.blueTableSkeleton.clear($rows);
    $rows.empty().append($('<tr>').append($('<td colspan="12" class="text-danger text-center py-4">').text('Unable to load online transactions.')));
    $('#online-count').text('Could not load records.');
    toast('Online register could not be loaded.',true);
   });
 }
 $('#online-filters').on('submit',function(e){e.preventDefault();load(1);});
 $('#online-reset').on('click',function(){
  $('#online-filters').trigger('reset');
  $.each(['#online-account-filter','#online-type-filter','#online-status-filter'],function(_,id){tsSet($(id),'');});
  $('#online-quick-search').val('');
  $('.online-column-filters [data-column]').val('');
  load(1);
 });
 $('.online-column-filters').on('input change','[data-column]',function(){
  var el=this;
  clearTimeout(searchTimer);
  if($(el).is('select,[type=date]'))load(1);
  else searchTimer=setTimeout(function(){load(1);},300);
 });
 $('#online-per-page').on('change',function(){load(1);});
 $('#online-quick-search').on('input',function(){
  clearTimeout(searchTimer);searchTimer=setTimeout(function(){load(1);},300);
 });
 $entry.on('submit',function(e){
  e.preventDefault();if(busy||!validate())return;
  if(!window.Swal||!$.isFunction(window.Swal.fire)){toast('Confirmation popup unavailable.',true);return;}
  window.Swal.fire({
   icon:'question',title:'Confirm Online Transaction',
   text:'Save this '+($('#online-entry-type').val()==='receipt'?'Receipt':'Payment')+' as Pending? No bank voucher or ledger posting will be created.',
   showCancelButton:true,confirmButtonText:'Yes, Save Pending',cancelButtonText:'Cancel',confirmButtonColor:'#168348'
  }).then(function(result){
   if(!result.isConfirmed||busy)return;
   busy=true;$('#online-save').prop('disabled',true);
   $.ajax({
    url:$entry.attr('action'),method:'POST',data:new FormData($entry[0]),
    processData:false,contentType:false,dataType:'json',headers:{Accept:'application/json'}
   }).done(function(data){
    toast(data.message||'Online transaction saved.',false);
    load(1);
    window.Swal.fire({icon:'success',title:'Pending Transaction Saved',text:data.message||'Saved.'}).then(function(){
     $modal.modal('hide');resetEntry();
    });
   }).fail(function(xhr){
    var msg=xhr.responseJSON&&xhr.responseJSON.message?xhr.responseJSON.message:'Unable to save online transaction.';
    if(xhr.responseJSON&&xhr.responseJSON.errors){
     $.each(xhr.responseJSON.errors,function(name,messages){
      var $field=$entry.find('[name="'+name+'"]');
      if($field.length)fieldError($field,messages[0]||msg);
      msg=messages[0]||msg;
     });
    }
    toast(msg,true);window.Swal.fire({icon:'error',title:'Online Transaction Not Saved',text:msg});
   }).always(function(){busy=false;$('#online-save').prop('disabled',false);});
  });
 });
 // 02.2: read-only project-scoped history for all records.
 $rows.on('click','.online-history-button',function(){
  var $button=$(this),id=String($button.attr('data-id'));
  $('#online-history-ref').text('Reference: '+($button.attr('data-reference')||''));
  $('#online-eligibility-status').removeClass('alert-success alert-warning').addClass('alert-light')
   .text('Checking confirmed-source eligibility...');
  var $body=$('#online-history-rows').empty();
  $('<tr>').append($('<td colspan="6" class="text-center">').text('Loading history...')).appendTo($body);
  $('#online-history-modal').modal('show');
  $.ajax({url:historyUrlTemplate.replace('__ONLINE_ID__',id),dataType:'json'})
   .done(function(result){
    $body.empty();
    var eligibility=result.eligibility||{};
    $('#online-eligibility-status')
      .removeClass('alert-light alert-success alert-warning')
      .addClass(eligibility.eligible?'alert-success':'alert-warning')
      .text((eligibility.eligible?'Eligible for future bank voucher: ':'Not eligible: ')+(eligibility.reason||'Verification pending.')+' No posting is performed here.');
    var events=result.events||[];
    if(!events.length){
     $('<tr>').append($('<td colspan="6" class="text-muted text-center">')
      .text('Pending source recorded. No bank confirmation yet.')).appendTo($body);
    }
    $.each(events,function(_,e){
     var $tr=$('<tr>');
     $('<td>').text((e.confirmed_at||'')+' / '+(e.actor_name||'User #'+e.actor_id)).appendTo($tr);
     $('<td>').text(e.from_status+' → '+e.to_status).appendTo($tr);
     $('<td>').text(e.statement_reference||'').appendTo($tr);
     $('<td>').text(e.verification_date||'').appendTo($tr);
     $('<td>').addClass('text-right').text(Number(e.original_amount).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2})).appendTo($tr);
     $('<td>').text(e.remarks||'—').appendTo($tr);
     $body.append($tr);
    });
   }).fail(function(xhr){
    var msg=xhr.responseJSON&&xhr.responseJSON.message?xhr.responseJSON.message:'Unable to load history.';
    $('#online-eligibility-status').removeClass('alert-success').addClass('alert-warning').text('Eligibility could not be checked.');
    $body.empty().append($('<tr>').append($('<td colspan="6" class="text-danger">').text(msg)));
   });
 });
 @can('create voucher')
 $rows.on('click','.online-confirm-button',function(){
  var $button=$(this);
  confirmRecord={id:String($button.attr('data-id')),type:$button.attr('data-type'),
   reference:$button.attr('data-reference'),amount:$button.attr('data-amount'),date:$button.attr('data-date')};
  var $form=$('#online-confirm-form');
  $form.trigger('reset');
  $('#online-confirm-errors').empty();
  $('#online-confirm-ref').text(confirmRecord.reference);
  $('#online-confirm-amount').text(Number(confirmRecord.amount).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2}));
  $('#online-verify-date').val(@json(date('Y-m-d'))).attr('min',confirmRecord.date);
  $('#online-confirm-notice').text(confirmRecord.type==='receipt'
   ?'Receipt IN: bank statement par receiving bank mein credit entry confirm karein. Sirf actual received amount verify hone par Confirm karein.'
   :'Payment OUT: bank statement par sending bank se debit entry confirm karein. Sirf actual deducted amount verify hone par Confirm karein.');
  $('#online-confirm-modal').modal('show');
 });
 $('#online-confirm-form').on('submit',function(e){
  e.preventDefault();
  if(confirmBusy||!confirmRecord)return;
  var errors=[];
  var date=$('#online-verify-date').val(),reference=$.trim($('#online-statement-reference').val());
  if(!date)errors.push('Verification date is required.');
  if(date&&date<confirmRecord.date)errors.push('Verification date cannot be earlier than transaction date.');
  if(!reference)errors.push('Bank statement reference is required.');
  if(!$('#online-verified').prop('checked'))errors.push('Bank statement verification is mandatory.');
  if(errors.length){$('#online-confirm-errors').text(errors.join(' '));return;}
  $('#online-confirm-errors').empty();
  if(!window.Swal||!$.isFunction(window.Swal.fire)){toast('Confirmation dialog unavailable.',true);return;}
  window.Swal.fire({
   icon:'warning',title:'Verify Bank Statement Before Confirmation',
   text:confirmRecord.type==='receipt'
    ?'Kya aap ne bank statement se verify kar liya hai ke amount relevant bank account mein credit ho chuka hai?'
    :'Kya aap ne bank statement se verify kar liya hai ke amount relevant bank account se debit ho chuka hai?',
   showCancelButton:true,confirmButtonText:'Yes, Bank Verified',cancelButtonText:'Cancel',
   confirmButtonColor:'#168348',allowOutsideClick:false
  }).then(function(result){
   if(!result.isConfirmed||confirmBusy)return;
   confirmBusy=true;
   $('#online-confirm-submit').prop('disabled',true);
   $.ajax({
    url:confirmUrlTemplate.replace('__ONLINE_ID__',confirmRecord.id),method:'POST',
    data:$('#online-confirm-form').serialize(),dataType:'json',headers:{Accept:'application/json'}
   }).done(function(data){
    toast(data.message||'Transaction confirmed.',false);
    $('#online-confirm-modal').modal('hide');
    load(1);
    window.Swal.fire({icon:'success',title:'Bank Verification Recorded',text:data.message||'Confirmed.'});
   }).fail(function(xhr){
    var msg=xhr.responseJSON&&xhr.responseJSON.message?xhr.responseJSON.message:'Unable to confirm online transaction.';
    if(xhr.responseJSON&&xhr.responseJSON.errors){
     $.each(xhr.responseJSON.errors,function(_,messages){msg=messages[0]||msg;return false;});
    }
    $('#online-confirm-errors').text(msg);
    toast(msg,true);
    window.Swal.fire({icon:'error',title:'Transaction Not Confirmed',text:msg});
   }).always(function(){confirmBusy=false;$('#online-confirm-submit').prop('disabled',false);});
  });
 });
 @endcan
 load(1);
});
</script>
@endpush
