@extends('admin.layouts.master')
@section('title','Voucher Settings')
@section('content')
<style>
.voucher-settings-page{background:#f3f7fb;min-height:calc(100vh - 140px);padding:14px}
.voucher-settings-page .vs-head{background:#e9f7ed;border-bottom:2px solid #128044;border-radius:7px;padding:14px 17px;margin-bottom:12px}
.voucher-settings-page h4{margin:0;color:#08763e;font-weight:700}
.voucher-settings-page .vs-card{border:1px solid #c5e5d0;border-top:3px solid #168348;border-radius:9px;background:white;margin-bottom:12px}
.voucher-settings-page .vs-card-body{padding:14px}
.voucher-settings-page label{font-size:12px;font-weight:700}
.voucher-settings-page .vs-status{font-size:11px;font-weight:700}
.voucher-settings-page .ts-wrapper{width:100%}
.voucher-settings-page .ts-control{min-height:34px;border-radius:4px;font-size:13px}
.voucher-settings-page select.tomselected{display:none!important}
.voucher-settings-page .vs-disabled{opacity:.8;background:#f8f9fa}
.voucher-settings-page .vs-disabled .vs-card-body{color:#5f6b76}
.voucher-settings-page .vs-toolbar{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.voucher-settings-page .vs-toast{position:fixed;right:18px;top:78px;z-index:2150;display:none;max-width:400px}
</style>
<div class="content-wrapper voucher-settings-page">
 <div class="container-fluid">
  <div class="vs-head vs-toolbar">
   <div><h4>Voucher Settings</h4><small class="text-muted">Selected project · Centralized approval configuration</small></div>
   <a class="btn btn-outline-success btn-sm" href="{{ route('finance.daily-vouchers.approvals.index') }}">Approval Dashboard</a>
  </div>
  <div class="alert alert-info small">
   Approval policy applies to <strong>new vouchers only</strong>. CR, CP, CCR, CDR and JV now use the guarded approval engine. Approvers must have the <code>voucher approver</code> permission through their role or direct assignment. Super Admin has universal decision authority.
   CCR and CDR have independent approval switches; existing Posted vouchers stay unchanged when a switch is enabled. Remaining voucher types are locked until their posting workflows are safely integrated.
  </div>
  <div class="row">
   @foreach($types as $type=>$definition)
   @php($policy=$policies->get($type))
   <div class="col-xl-4 col-lg-6">
    <div class="vs-card {{ $definition['supported'] ? '' : 'vs-disabled' }}">
     <div class="vs-card-body">
      <div class="vs-toolbar mb-2"><strong>{{ $definition['label'] }} ({{ $type }})</strong>
       @if($definition['supported'])
        <span class="badge badge-success vs-status">Approval supported</span>
       @else
        <span class="badge badge-secondary vs-status">Integration pending</span>
       @endif
      </div>
      @if($definition['supported'])
       <form method="post" class="vs-policy-form" action="{{ route('finance.voucher-settings.save') }}">@csrf
        <input type="hidden" name="voucher_type" value="{{ $type }}">
        <div class="custom-control custom-switch mb-3">
         <input type="checkbox" class="custom-control-input vs-required" id="vs-required-{{ $type }}" name="approval_required" value="1" {{ $policy?->approval_required ? 'checked':'' }} {{ auth()->user()->can('update setting') ? '' : 'disabled' }}>
         <label class="custom-control-label" for="vs-required-{{ $type }}">Approval Required</label>
        </div>
        <div class="form-group mb-2">
         <label for="vs-approver-{{ $type }}">Authorized Approver</label>
         <select id="vs-approver-{{ $type }}" name="approver_user_id" class="form-select form-select-sm vs-approver" {{ auth()->user()->can('update setting') ? '' : 'disabled' }}>
          <option value="">Select project approver</option>
          @foreach($approvers as $user)
          <option value="{{ $user->id }}" {{ (int)($policy?->approver_user_id??0)===(int)$user->id?'selected':'' }}>{{ $user->name }}</option>
          @endforeach
         </select>
         <small class="text-danger vs-field-error d-block" role="alert"></small>
        </div>
        <div class="vs-toolbar">
         <small class="text-muted">{{ $policy?->approval_required ? 'ON · New vouchers require authorization' : 'OFF · Existing permission rules apply' }}</small>
         @can('update setting')<button type="submit" class="btn btn-sm btn-success vs-save">Save Settings</button>@endcan
        </div>
       </form>
      @else
       <div class="small mb-2">Approval configuration locked for this voucher type.</div>
       <div class="small text-muted">No policy write or financial posting changes allowed at this stage.</div>
      @endif
     </div>
    </div>
   </div>
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
 $('.vs-policy-form').on('submit',function(event){
  event.preventDefault();
  var $form=$(this),type=$form.find('[name=voucher_type]').val(),enabled=$form.find('.vs-required').prop('checked');
  var $select=$form.find('.vs-approver'),userId=$select.val(),$error=$form.find('.vs-field-error');
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
   var $btn=$form.find('.vs-save').prop('disabled',true);
   $.ajax({url:$form.attr('action'),method:'POST',data:$form.serialize(),
    headers:{Accept:'application/json'},dataType:'json'})
    .done(function(resp){
     toast(resp.message||'Approval policy saved.',false);
     $form.find('.vs-toolbar small').text(enabled
      ?'ON · New vouchers require authorization':'OFF · Existing permission rules apply');
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
