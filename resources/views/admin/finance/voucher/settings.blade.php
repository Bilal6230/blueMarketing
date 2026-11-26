@extends('admin.layouts.master')
@section('title','Voucher Settings')
@section('content')
<style>
.vs-no-outer-scroll{overflow:hidden!important}
.main-content:has(> .voucher-settings-page){overflow:hidden!important}
.voucher-settings-page{background:#f3f7fb;min-height:0!important;padding:12px;height:calc(100dvh - 125px);overflow:hidden!important;display:flex;flex-direction:column;box-sizing:border-box}
.voucher-settings-page>.container-fluid{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
.voucher-settings-page .vs-head{flex:none}
.voucher-settings-page .vs-panel{flex:1;min-height:0;display:flex;flex-direction:column}
.voucher-settings-page .vs-panel-head{flex:none}
.voucher-settings-page .vs-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:#e9f7ed;border-bottom:2px solid #128044;border-radius:7px;padding:12px 16px;margin-bottom:12px}
.voucher-settings-page h4{margin:0;color:#08763e;font-weight:700;font-size:21px}
.voucher-settings-page .vs-panel{border:1px solid #b8d0c3;border-top:3px solid #168348;border-radius:8px;background:#fff;overflow:hidden}
.voucher-settings-page .vs-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #b8d0c3}
.voucher-settings-page .vs-panel-head h5{margin:0;color:#12663d;font-size:15px;font-weight:700}
.voucher-settings-page .vs-panel-head small{color:#68766e;font-size:12px}
.voucher-settings-page .vs-table-wrap{flex:1;min-height:0;max-height:none;overflow:auto;overscroll-behavior:contain}
.voucher-settings-page .vs-table{width:100%;min-width:930px;margin:0;border-collapse:separate;border-spacing:0;font-size:13px}
.voucher-settings-page .vs-table thead th{position:sticky;top:0;z-index:3;background:#eaf3ed;color:#235c40;border-bottom:2px solid #96bba6;padding:11px 12px;text-align:left;white-space:nowrap}
.voucher-settings-page .vs-table tbody td{padding:9px 12px;vertical-align:middle;border-bottom:1px solid #c4d4ca}
.voucher-settings-page .vs-table tbody tr:nth-child(even){background:#f1f6f3}
.voucher-settings-page .vs-table tbody tr:hover{background:#e5f3e9}
.voucher-settings-page .vs-table tbody tr:last-child td{border-bottom:0}
.voucher-settings-page .vs-voucher{font-size:13px;font-weight:700;color:#1c2b21}
.voucher-settings-page .vs-code{font-size:11px;color:#5d7566;margin-left:4px;font-weight:600}
.voucher-settings-page .vs-switch{display:inline-flex;align-items:center;min-width:55px;margin-left:2px}
.voucher-settings-page .vs-switch label{cursor:pointer;font-size:12px;font-weight:600;margin:0}
.voucher-settings-page .vs-approver{width:100%}
.voucher-settings-page .ts-wrapper{width:100%}
.voucher-settings-page .ts-control{min-height:34px;border-radius:5px;font-size:13px}
.voucher-settings-page select.tomselected{display:none!important}
.voucher-settings-page .vs-field-error{font-size:11px;line-height:1.25}
.voucher-settings-page .vs-save,.voucher-settings-page .vs-reversal-save{white-space:nowrap;font-size:12px}
.voucher-settings-page .vs-muted{color:#84938a;font-size:12px}
.voucher-settings-page .vs-toast{position:fixed;right:18px;top:78px;z-index:2150;display:none;max-width:400px}
.voucher-settings-page .vs-table .vs-group-row td{background:#e3ede7!important;color:#2b6746;font-size:12px;font-weight:700;letter-spacing:.015em;padding:7px 12px;border-bottom:1px solid #bbd2c1}
.voucher-settings-page .vs-table .vs-group-row:hover{background:#e3ede7}
.voucher-settings-page .vs-table .vs-reversal-form,.voucher-settings-page .vs-table .vs-policy-form{margin:0}
.voucher-settings-page .vs-disabled{opacity:.65}
@media(max-width:767px){.voucher-settings-page{padding:8px}.voucher-settings-page .vs-head{padding:10px}.voucher-settings-page .vs-panel-head{align-items:flex-start;flex-direction:column}.voucher-settings-page .vs-table-wrap{max-height:none}}
</style>
<div class="content-wrapper voucher-settings-page">
 <div class="container-fluid">
  <div class="vs-head">
   <div><h4>Voucher Settings</h4><small class="text-muted">Project-wise voucher approval and reversal controls</small></div>
   <a class="btn btn-outline-success btn-sm" href="{{ route('finance.daily-vouchers.approvals.index') }}">Approval Dashboard</a>
  </div>
  <section class="vs-panel" aria-labelledby="vs-table-title">
   <div class="vs-panel-head">
    <h5 id="vs-table-title">Voucher Policies</h5>
    <small>{{ count($types) }} voucher types · Changes apply to new approvals; reversal is independent</small>
   </div>
   <div class="vs-table-wrap">
    <table class="vs-table">
     <thead>
      <tr>
       <th style="width:27%">Voucher Type</th>
       <th style="width:12%">Approval</th>
       <th style="width:26%">Authorized Approver</th>
       <th style="width:12%">Approval Action</th>
       <th style="width:10%">Reversal</th>
       <th style="width:13%">Reversal Action</th>
      </tr>
     </thead>
     <tbody>
      @foreach([
       ['title'=>'Approval + Reversal','codes'=>$reversalTypes],
       ['title'=>'Approval Only','codes'=>array_values(array_diff(array_keys($types),$reversalTypes))]
      ] as $group)
       <tr class="vs-group-row"><td colspan="6">{{ $group['title'] }} · {{ count($group['codes']) }} voucher types</td></tr>
       @foreach($group['codes'] as $type)
        @php($definition=$types[$type])
        @php($policy=$policies->get($type))
        @php($editable=auth()->user()->can('update setting'))
        <tr class="vs-policy-row {{ $definition['supported'] ? '' : 'vs-disabled' }}" data-voucher-type="{{ $type }}">
         <td>
          <div class="vs-voucher">{{ $definition['label'] }} <span class="vs-code">({{ $type }})</span></div>
          @if($definition['supported'])
           <form id="vs-policy-form-{{ $type }}" method="post" class="vs-policy-form" action="{{ route('finance.voucher-settings.save') }}">
            @csrf
            <input type="hidden" name="voucher_type" value="{{ $type }}">
           </form>
          @endif
          @if(in_array($type,$reversalTypes,true))
           <form id="vs-reversal-form-{{ $type }}" method="post" class="vs-reversal-form" action="{{ route('finance.voucher-settings.reversal.save') }}">
            @csrf
            <input type="hidden" name="voucher_type" value="{{ $type }}">
           </form>
          @endif
         </td>
         <td>
          @if($definition['supported'])
           <div class="custom-control custom-switch vs-switch">
            <input type="checkbox" class="custom-control-input vs-required" id="vs-required-{{ $type }}" form="vs-policy-form-{{ $type }}" name="approval_required" value="1"
             {{ $policy?->approval_required ? 'checked':'' }} {{ $editable ? '' : 'disabled' }}>
            <label class="custom-control-label" for="vs-required-{{ $type }}">{{ $policy?->approval_required ? 'ON':'OFF' }}</label>
           </div>
          @else <span class="vs-muted">Unavailable</span> @endif
         </td>
         <td>
          @if($definition['supported'])
           <select id="vs-approver-{{ $type }}" name="approver_user_id" form="vs-policy-form-{{ $type }}" class="form-select form-select-sm vs-approver"
            {{ $editable ? '' : 'disabled' }}>
            <option value="">Select project approver</option>
            @foreach($approvers as $user)
             <option value="{{ $user->id }}" {{ (int)($policy?->approver_user_id??0)===(int)$user->id?'selected':'' }}>{{ $user->name }}</option>
            @endforeach
           </select>
           <small class="text-danger vs-field-error d-block" role="alert"></small>
          @else <span class="vs-muted">—</span> @endif
         </td>
         <td>
          @if($definition['supported'] && $editable)
           <button type="submit" form="vs-policy-form-{{ $type }}" class="btn btn-sm btn-success vs-save">Save</button>
          @else <span class="vs-muted">—</span> @endif
         </td>
         <td>
          @if(in_array($type,$reversalTypes,true))
           <div class="custom-control custom-switch vs-switch">
            <input type="checkbox" class="custom-control-input vs-reversal-enabled" id="vs-reversal-{{ $type }}" form="vs-reversal-form-{{ $type }}" name="reversal_enabled" value="1"
             {{ ($policy?->reversal_enabled ?? true) ? 'checked':'' }} {{ $editable ? '' : 'disabled' }}>
            <label class="custom-control-label" for="vs-reversal-{{ $type }}">{{ ($policy?->reversal_enabled ?? true) ? 'ON':'OFF' }}</label>
           </div>
          @else <span class="vs-muted">Not available</span> @endif
         </td>
         <td>
          @if(in_array($type,$reversalTypes,true) && $editable)
           <button type="submit" form="vs-reversal-form-{{ $type }}" class="btn btn-sm btn-outline-success vs-reversal-save">Save</button>
          @else <span class="vs-muted">—</span> @endif
         </td>
        </tr>
       @endforeach
      @endforeach
     </tbody>
    </table>
   </div>
  </section>
 </div>
</div>
<div class="alert vs-toast" role="status" id="vs-toast"></div>
@endsection
@push('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
 'use strict';
 // Lock document scrolling only on this screen. The table remains the
 // single vertical scroller, and its size follows the actual navbar offset.
 $('html, body').addClass('vs-no-outer-scroll');
 $(window).scrollTop(0);
 function sizeVoucherSettings(){
  var $page=$('.voucher-settings-page');
  if(!$page.length)return;
  var top=$page.offset().top;
  var available=Math.max(280,$(window).height()-top-8);
  $page.css('height',available+'px');
 }
 sizeVoucherSettings();
 $(window).on('resize.voucherSettings',sizeVoucherSettings);
 $('.vs-approver').each(function(){
  if(typeof window.TomSelect!=='undefined'&&!this.tomselect)
   new TomSelect(this,{create:false,persist:false,maxItems:1,allowEmptyOption:true});
 });
 function toast(msg,error){
  if(window.toastr&&$.isFunction(window.toastr.error)){
   if(error)window.toastr.error(msg);else window.toastr.success(msg);
  }else $('#vs-toast').removeClass('alert-danger alert-success')
   .addClass(error?'alert-danger':'alert-success').text(msg)
   .stop(true,true).fadeIn(130).delay(3500).fadeOut(180);
 }
 $('.vs-reversal-form').on('submit',function(event){
  event.preventDefault();
  var $form=$(this),$row=$form.closest('tr'),$checkbox=$row.find('.vs-reversal-enabled');
  var enabled=$checkbox.prop('checked'),type=$form.find('[name=voucher_type]').val();
  if(!window.Swal||!$.isFunction(window.Swal.fire)){toast('Confirmation popup unavailable.',true);return;}
  window.Swal.fire({icon:'question',title:'Change Reversal Setting?',
   text:'Set '+type+' reversal '+(enabled?'ON':'OFF')+' for the selected project? Existing posted vouchers will not change.',
   showCancelButton:true,confirmButtonText:'Save Reversal Setting',cancelButtonText:'Cancel'
  }).then(function(answer){
   if(!answer.isConfirmed)return;
   var $button=$row.find('.vs-reversal-save').prop('disabled',true);
   $.ajax({url:$form.attr('action'),method:'POST',
    data:{_token:$form.find('[name=_token]').val(),voucher_type:type,reversal_enabled:enabled?'1':'0'},
    headers:{Accept:'application/json'},dataType:'json'})
    .done(function(response){
     $row.find('.vs-reversal-enabled').siblings('.custom-control-label').text(enabled?'ON':'OFF');
     toast(response.message||'Reversal setting saved.',false);
    }).fail(function(xhr){
     var message=xhr.responseJSON&&xhr.responseJSON.message||'Unable to save reversal setting.';
     toast(message,true);
     window.Swal.fire({icon:'error',title:'Reversal Setting Not Saved',text:message});
    }).always(function(){$button.prop('disabled',false);});
  });
 });
 $('.vs-policy-form').on('submit',function(event){
  event.preventDefault();
  var $form=$(this),$row=$form.closest('tr'),type=$form.find('[name=voucher_type]').val(),enabled=$row.find('.vs-required').prop('checked');
  var $select=$row.find('.vs-approver'),userId=$select.val(),$error=$row.find('.vs-field-error');
  $error.text('');$select.next('.ts-wrapper').removeClass('is-invalid');
  if(enabled&&!userId){
   $error.text('Select an authorized project approver.');
   $select.next('.ts-wrapper').addClass('is-invalid');return;
  }
  if(!window.Swal||!$.isFunction(window.Swal.fire)){toast('Confirmation popup unavailable.',true);return;}
  window.Swal.fire({
   icon:'question',title:'Save Voucher Approval Settings?',
   text:'Update '+type+' approval to '+(enabled?'ON':'OFF')+' for new vouchers in this project?',
   showCancelButton:true,confirmButtonText:'Yes, Save Settings',cancelButtonText:'Cancel',
   confirmButtonColor:'#168348'
  }).then(function(answer){
   if(!answer.isConfirmed)return;
   var $btn=$row.find('.vs-save').prop('disabled',true);
   $.ajax({url:$form.attr('action'),method:'POST',
    data:{_token:$form.find('[name=_token]').val(),voucher_type:type,approval_required:enabled?'1':'0',approver_user_id:userId},
    headers:{Accept:'application/json'},dataType:'json'})
    .done(function(resp){
     toast(resp.message||'Approval policy saved.',false);
     $row.find('.vs-required').siblings('.custom-control-label').text(enabled?'ON':'OFF');
     window.Swal.fire({icon:'success',title:'Settings Saved',text:resp.message||'Saved.'});
    }).fail(function(xhr){
     var msg=xhr.responseJSON&&xhr.responseJSON.message?xhr.responseJSON.message:'Unable to save policy.';
     if(xhr.responseJSON&&xhr.responseJSON.errors){
      $.each(xhr.responseJSON.errors,function(_,items){msg=items[0]||msg;return false;});
     }
     $error.text(msg);toast(msg,true);
     window.Swal.fire({icon:'error',title:'Policy Not Saved',text:msg});
    }).always(function(){$btn.prop('disabled',false);});
  });
 });
});
</script>
@endpush
