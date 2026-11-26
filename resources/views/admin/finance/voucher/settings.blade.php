@extends('admin.layouts.master')
@section('title','Voucher Settings')
@section('content')
<style>
.voucher-settings-page{background:#f3f7fb;padding:12px;box-sizing:border-box;min-height:0!important;overflow:visible}
.voucher-settings-page>.container-fluid{display:flex;flex-direction:column;height:100%;min-height:0;padding:0}
.voucher-settings-page .vs-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;flex:none;background:#e9f7ed;border-bottom:2px solid #128044;border-radius:7px;padding:12px 16px;margin-bottom:12px}
.voucher-settings-page h4{margin:0;color:#08763e;font-weight:700;font-size:21px}
.voucher-settings-page .vs-panels{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;flex:1;min-height:0}
.voucher-settings-page .vs-panel{border:1px solid #b8d0c3;border-top:3px solid #168348;border-radius:8px;background:#fff;overflow:hidden;display:flex;flex-direction:column;min-width:0;min-height:0}
.voucher-settings-page .vs-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex:none;padding:10px 12px;border-bottom:1px solid #b8d0c3}
.voucher-settings-page .vs-panel-head h5{margin:0;color:#12663d;font-size:15px;font-weight:700}
.voucher-settings-page .vs-panel-head small{font-size:12px;color:#68766e;white-space:nowrap}
.voucher-settings-page .vs-table-wrap{flex:1;min-height:0;overflow:auto;overscroll-behavior:contain}
.voucher-settings-page .vs-table{width:100%;min-width:0;margin:0;border-collapse:separate;border-spacing:0;font-size:12px;table-layout:fixed}
.voucher-settings-page .vs-table thead th{position:sticky;top:0;z-index:3;background:#eaf3ed;color:#235c40;border-bottom:2px solid #96bba6;padding:9px 6px;text-align:left;white-space:normal;line-height:1.25}
.voucher-settings-page .vs-table tbody td{padding:8px 6px;vertical-align:middle;border-bottom:1px solid #c4d4ca}
.voucher-settings-page .vs-table tbody tr:nth-child(even){background:#f1f6f3}
.voucher-settings-page .vs-table tbody tr:hover{background:#e5f3e9}
.voucher-settings-page .vs-voucher{font-size:12px;font-weight:700;line-height:1.3;color:#1c2b21}
.voucher-settings-page .vs-code{font-size:11px;color:#5d7566;font-weight:600;white-space:nowrap}
.voucher-settings-page .vs-switch{display:inline-flex;align-items:center;min-width:48px;margin-left:1px}
.voucher-settings-page .vs-switch label{font-size:11px;font-weight:600;margin:0;cursor:pointer}
.voucher-settings-page .vs-approver{width:100%}
.voucher-settings-page .ts-wrapper{width:100%;min-width:0}
.voucher-settings-page .ts-control{min-height:32px;border-radius:5px;font-size:12px;padding:5px 7px}
.voucher-settings-page select.tomselected{display:none!important}
.voucher-settings-page .vs-field-error{font-size:11px;line-height:1.25}
.voucher-settings-page .vs-save,.voucher-settings-page .vs-reversal-save{white-space:nowrap;font-size:11px;padding:5px 7px}
.voucher-settings-page .vs-muted{color:#84938a;font-size:11px}
.voucher-settings-page .vs-toast{position:fixed;right:18px;top:78px;z-index:2150;display:none;max-width:400px}
.voucher-settings-page .vs-disabled{opacity:.65}
@media(max-width:1199.98px){
 .voucher-settings-page .vs-panels{grid-template-columns:minmax(0,1fr)}
 .voucher-settings-page .vs-panel{min-height:260px}
 .voucher-settings-page .vs-table-wrap{overflow:auto}
}
@media(max-width:767.98px){
 .voucher-settings-page{padding:8px}
 .voucher-settings-page .vs-head{padding:10px}
 .voucher-settings-page .vs-panel-head{flex-wrap:wrap}
 .voucher-settings-page .vs-table{min-width:590px}
}
</style>
<div class="content-wrapper voucher-settings-page">
 <div class="container-fluid">
  <div class="vs-head">
   <div><h4>Voucher Settings</h4><small class="text-muted">Project-wise voucher approval and reversal controls</small></div>
   <a class="btn btn-outline-success btn-sm" href="{{ route('finance.daily-vouchers.approvals.index') }}">Approval Dashboard</a>
  </div>
  <div class="vs-panels">
   @foreach([
    ['id'=>'reversal','title'=>'Approval + Reversal','codes'=>$reversalTypes,'has_reversal'=>true],
    ['id'=>'approval','title'=>'Approval Only','codes'=>array_values(array_diff(array_keys($types),$reversalTypes)),'has_reversal'=>false]
   ] as $group)
   <section class="vs-panel" aria-labelledby="vs-title-{{ $group['id'] }}">
    <div class="vs-panel-head">
     <h5 id="vs-title-{{ $group['id'] }}">{{ $group['title'] }}</h5>
     <small>{{ count($group['codes']) }} voucher types</small>
    </div>
    <div class="vs-table-wrap" tabindex="0" aria-label="{{ $group['title'] }} voucher settings">
     <table class="vs-table {{ $group['has_reversal'] ? 'vs-reversal-table' : 'vs-approval-table' }}">
      @if($group['has_reversal'])
       <colgroup><col style="width:23%"><col style="width:11%"><col style="width:27%"><col style="width:9%"><col style="width:17%"><col style="width:13%"></colgroup>
      @else
       <colgroup><col style="width:30%"><col style="width:14%"><col style="width:42%"><col style="width:14%"></colgroup>
      @endif
      <thead>
       <tr><th>Voucher</th><th>Approval</th><th>Approver</th><th>Save</th>
       @if($group['has_reversal'])<th>Reversal</th><th>Save</th>@endif
       </tr>
      </thead>
      <tbody>
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
          @if($group['has_reversal'])
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
          @else <span class="vs-muted">N/A</span> @endif
         </td>
         <td>
          @if($definition['supported'])
           <select id="vs-approver-{{ $type }}" name="approver_user_id" form="vs-policy-form-{{ $type }}" class="form-select form-select-sm vs-approver"
            {{ $editable ? '' : 'disabled' }}>
            <option value="">Select approver</option>
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
         @if($group['has_reversal'])
          <td>
           <div class="custom-control custom-switch vs-switch">
            <input type="checkbox" class="custom-control-input vs-reversal-enabled" id="vs-reversal-{{ $type }}" form="vs-reversal-form-{{ $type }}" name="reversal_enabled" value="1"
             {{ ($policy?->reversal_enabled ?? true) ? 'checked':'' }} {{ $editable ? '' : 'disabled' }}>
            <label class="custom-control-label" for="vs-reversal-{{ $type }}">{{ ($policy?->reversal_enabled ?? true) ? 'ON':'OFF' }}</label>
           </div>
          </td>
          <td>
           @if($editable)
            <button type="submit" form="vs-reversal-form-{{ $type }}" class="btn btn-sm btn-outline-success vs-reversal-save">Save</button>
           @else <span class="vs-muted">—</span> @endif
          </td>
         @endif
        </tr>
       @endforeach
      </tbody>
     </table>
    </div>
   </section>
   @endforeach
  </div>
 </div>
</div>
<div class="alert vs-toast" role="status" id="vs-toast"></div>
@endsection
@push('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
 'use strict';
 // Keep the shared footer in view; only the two cards should scroll.
 function sizeVoucherPanels(){
  var $page=$('.voucher-settings-page');
  if(!$page.length)return;
  var footerHeight=$('.main-footer').outerHeight(true)||42;
  var top=$page.offset().top;
  var available=$(window).height()-top-footerHeight-10;
  if(available>=320){
   $page.css('height',available+'px');
   $page.find('.vs-panels').css('min-height','0');
  }else{
   $page.css('height','auto');
   $page.find('.vs-panels').css('min-height','520px');
  }
 }
 sizeVoucherPanels();
 $(window).on('resize.voucherSettings',sizeVoucherPanels);
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
