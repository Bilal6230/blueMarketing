@extends('admin.layouts.master')
@section('title','Voucher Settings')
@section('content')
<style>
body.vs-settings-mode{height:100dvh;overflow:hidden!important}
body.vs-settings-mode .wrapper{height:100dvh!important;min-height:0!important;overflow:hidden!important}
body.vs-settings-mode .main-content{overflow:hidden!important;min-height:0!important}
body.vs-settings-mode .main-footer{position:fixed!important;bottom:0;left:0;right:0;z-index:10}

.voucher-settings-page{background:#f3f7fb;padding:12px;box-sizing:border-box;min-height:0!important;overflow:visible}
.voucher-settings-page>.container-fluid{display:flex;flex-direction:column;height:100%;min-height:0;padding:0}
.voucher-settings-page .vs-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;flex:none;background:#e9f7ed;border-bottom:2px solid #128044;border-radius:7px;padding:12px 16px;margin-bottom:12px}
.voucher-settings-page h4{margin:0;color:#08763e;font-weight:700;font-size:21px}
.voucher-settings-page .vs-panels{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;flex:1;min-height:0}
.voucher-settings-page .vs-panel{border:1px solid #b8d0c3;border-top:3px solid #168348;border-radius:8px;background:#fff;overflow:hidden;display:flex;flex-direction:column;min-width:0;min-height:0}
.voucher-settings-page .vs-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex:none;padding:10px 12px;border-bottom:1px solid #b8d0c3}
.voucher-settings-page .vs-panel-head h5{margin:0;color:#12663d;font-size:15px;font-weight:700}
.voucher-settings-page .vs-panel-head small{font-size:14px;color:#68766e;white-space:nowrap}
.voucher-settings-page .vs-table-wrap{flex:1;min-height:0;overflow:auto;overscroll-behavior:contain}
.voucher-settings-page .vs-table{width:100%;min-width:0;margin:0;border-collapse:separate;border-spacing:0;font-size:14px;table-layout:fixed}
.voucher-settings-page .vs-table thead th{position:sticky;top:0;z-index:3;background:#eaf3ed;color:#235c40;border-bottom:2px solid #96bba6;padding:9px 6px;text-align:left;white-space:normal;line-height:1.25}
.voucher-settings-page .vs-table tbody td{padding:8px 6px;vertical-align:middle;border-bottom:1px solid #c4d4ca}
.voucher-settings-page .vs-table tbody tr:nth-child(even){background:#f1f6f3}
.voucher-settings-page .vs-table tbody tr:hover{background:#e5f3e9}
.voucher-settings-page .vs-voucher{font-size:14px;font-weight:700;line-height:1.3;color:#1c2b21}
.voucher-settings-page .vs-code{font-size:14px;color:#5d7566;font-weight:600;white-space:nowrap}
.voucher-settings-page .vs-switch{display:inline-flex;align-items:center;min-width:48px;margin-left:1px}
.voucher-settings-page .vs-switch label{font-size:14px;font-weight:600;margin:0;cursor:pointer}
.voucher-settings-page .vs-approver{width:100%}
.voucher-settings-page .ts-wrapper{width:100%;min-width:0}
.voucher-settings-page .ts-control{min-height:32px;border-radius:5px;font-size:14px;padding:5px 7px}
.voucher-settings-page select.tomselected{display:none!important}
.voucher-settings-page .vs-field-error{font-size:14px;line-height:1.25}
.voucher-settings-page .vs-save,.voucher-settings-page .vs-reversal-save{white-space:nowrap;font-size:14px;padding:5px 7px}
.voucher-settings-page .vs-muted{color:#84938a;font-size:14px}

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
       <colgroup><col style="width:27%"><col style="width:13%"><col style="width:40%"><col style="width:20%"></colgroup>
      @else
       <colgroup><col style="width:35%"><col style="width:14%"><col style="width:51%"></colgroup>
      @endif
      <thead>
       <tr><th>Voucher</th><th>Approval</th><th>Approver</th>
       @if($group['has_reversal'])<th>Reversal</th>@endif
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
         @if($group['has_reversal'])
          <td>
           <div class="custom-control custom-switch vs-switch">
            <input type="checkbox" class="custom-control-input vs-reversal-enabled" id="vs-reversal-{{ $type }}" form="vs-reversal-form-{{ $type }}" name="reversal_enabled" value="1"
             {{ ($policy?->reversal_enabled ?? true) ? 'checked':'' }} {{ $editable ? '' : 'disabled' }}>
            <label class="custom-control-label" for="vs-reversal-{{ $type }}">{{ ($policy?->reversal_enabled ?? true) ? 'ON':'OFF' }}</label>
           </div>
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
@endsection
@push('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
 'use strict';
 $('body').addClass('vs-settings-mode');
 function fit(){
  var $p=$('.voucher-settings-page'),f=$('.main-footer')[0];
  if(!$p.length)return;
  var bottom=f?f.getBoundingClientRect().top:$(window).height()-45;
  $p.css('height',Math.max(220,bottom-$p[0].getBoundingClientRect().top-8)+'px');
 }
 fit();$(window).on('resize.voucherSettings',fit);
 $('.vs-approver').each(function(){
  if(window.TomSelect&&!this.tomselect)new TomSelect(this,{create:false,persist:false,maxItems:1,allowEmptyOption:true});
 });
 function notify(message,error){
  if(window.toastr){if(error)window.toastr.error(message);else window.toastr.success(message);}
  else if(window.Swal)window.Swal.fire({toast:true,position:'top-end',timer:2800,showConfirmButton:false,icon:error?'error':'success',title:message});
 }
 function setApprover($select,value){
  if($select[0]&&$select[0].tomselect)$select[0].tomselect.setValue(value,true);
  else $select.val(value);
 }
 $('.vs-policy-form,.vs-reversal-form').on('submit',function(e){e.preventDefault();});
 $('.vs-policy-row').each(function(){
  var $r=$(this);
  $r.data('savedApproval',$r.find('.vs-required').prop('checked'));
  $r.data('savedApprover',$r.find('.vs-approver').val()||'');
  $r.data('savedReversal',$r.find('.vs-reversal-enabled').prop('checked'));
 });
 function rollbackApproval($r){
  var saved=!!$r.data('savedApproval');
  $r.find('.vs-required').prop('checked',saved).siblings('label').text(saved?'ON':'OFF');
  setApprover($r.find('.vs-approver'),$r.data('savedApprover')||'');
 }
 function rollbackReversal($r){
  var saved=!!$r.data('savedReversal');
  $r.find('.vs-reversal-enabled').prop('checked',saved).siblings('label').text(saved?'ON':'OFF');
 }
 $('.vs-required,.vs-approver').on('change',function(){
  var $r=$(this).closest('tr'),$f=$r.find('.vs-policy-form');
  if(!$f.length||$r.data('approvalBusy'))return;
  var enabled=$r.find('.vs-required').prop('checked'),$sel=$r.find('.vs-approver'),uid=$sel.val()||'';
  if(enabled===!!$r.data('savedApproval')&&uid===($r.data('savedApprover')||''))return;
  var $err=$r.find('.vs-field-error');$err.text('');$sel.next('.ts-wrapper').removeClass('is-invalid');
  if(enabled&&!uid){rollbackApproval($r);notify('Select an authorized project approver.',true);return;}
  if(!window.Swal){rollbackApproval($r);notify('Confirmation unavailable.',true);return;}
  var type=$f.find('[name=voucher_type]').val();
  $r.data('approvalBusy',true);
  window.Swal.fire({icon:'question',title:'Confirm Approval Setting',text:'Set '+type+' approval '+(enabled?'ON':'OFF')+' for this project?',
   showCancelButton:true,confirmButtonText:'Yes, Apply',cancelButtonText:'No',confirmButtonColor:'#168348'
  }).then(function(result){
   if(!result.isConfirmed){rollbackApproval($r);return;}
   $r.find('.vs-required').prop('disabled',true);
   if($sel[0]&&$sel[0].tomselect)$sel[0].tomselect.disable();else $sel.prop('disabled',true);
   return $.ajax({url:$f.attr('action'),method:'POST',headers:{Accept:'application/json'},dataType:'json',
    data:{_token:$f.find('[name=_token]').val(),voucher_type:type,approval_required:enabled?'1':'0',approver_user_id:uid}
   }).done(function(resp){
    $r.data('savedApproval',enabled);$r.data('savedApprover',enabled?uid:'');
    $r.find('.vs-required').siblings('label').text(enabled?'ON':'OFF');
    if(!enabled)setApprover($sel,'');
    notify(resp.message||'Approval setting updated.',false);
   }).fail(function(xhr){
    rollbackApproval($r);
    var msg=xhr.responseJSON&&xhr.responseJSON.message||'Approval not saved.';
    if(xhr.responseJSON&&xhr.responseJSON.errors)$.each(xhr.responseJSON.errors,function(_,a){msg=a[0]||msg;return false;});
    $err.text(msg);window.Swal.fire({icon:'error',title:'Approval Not Saved',text:msg});
   }).always(function(){
    $r.find('.vs-required').prop('disabled',false);
    if($sel[0]&&$sel[0].tomselect)$sel[0].tomselect.enable();else $sel.prop('disabled',false);
   });
  }).catch(function(){rollbackApproval($r);notify('Confirmation failed.',true);})
   .finally(function(){$r.data('approvalBusy',false);});
 });
 $('.vs-reversal-enabled').on('change',function(){
  var $r=$(this).closest('tr'),$f=$r.find('.vs-reversal-form');
  if(!$f.length||$r.data('reversalBusy'))return;
  var enabled=$(this).prop('checked');
  if(enabled===!!$r.data('savedReversal'))return;
  if(!window.Swal){rollbackReversal($r);notify('Confirmation unavailable.',true);return;}
  var type=$f.find('[name=voucher_type]').val();$r.data('reversalBusy',true);
  window.Swal.fire({icon:'question',title:'Confirm Reversal Setting',
   text:'Set '+type+' reversal '+(enabled?'ON':'OFF')+'? Existing posted vouchers are unchanged.',
   showCancelButton:true,confirmButtonText:'Yes, Apply',cancelButtonText:'No',confirmButtonColor:'#168348'
  }).then(function(result){
   if(!result.isConfirmed){rollbackReversal($r);return;}
   $r.find('.vs-reversal-enabled').prop('disabled',true);
   return $.ajax({url:$f.attr('action'),method:'POST',headers:{Accept:'application/json'},dataType:'json',
    data:{_token:$f.find('[name=_token]').val(),voucher_type:type,reversal_enabled:enabled?'1':'0'}
   }).done(function(resp){
    $r.data('savedReversal',enabled);
    $r.find('.vs-reversal-enabled').siblings('label').text(enabled?'ON':'OFF');
    notify(resp.message||'Reversal setting updated.',false);
   }).fail(function(xhr){
    rollbackReversal($r);
    var msg=xhr.responseJSON&&xhr.responseJSON.message||'Reversal not saved.';
    window.Swal.fire({icon:'error',title:'Reversal Not Saved',text:msg});
   }).always(function(){$r.find('.vs-reversal-enabled').prop('disabled',false);});
  }).catch(function(){rollbackReversal($r);notify('Confirmation failed.',true);})
   .finally(function(){$r.data('reversalBusy',false);});
 });
});
</script>
@endpush
