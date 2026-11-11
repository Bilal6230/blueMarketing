@extends('admin.layouts.master')
@section('title','Voucher Correction')
@section('content')
@if(request()->boolean('embed'))
<style>
.main-sidebar,.main-header,.main-footer,.navbar,.control-sidebar,#load_screen,.preloader{display:none!important}
.main-content.page-content,.main-content.page-content>.content-wrapper{margin:0!important;padding:0!important;width:100%!important;max-width:100%!important}
body{overflow:auto!important}
</style>
@endif
<style>
.cr-revise{max-width:none;margin:0;background:#fff;padding:16px 18px;font-size:13px;min-height:0}
.cr-revise h4{font-size:16px;color:{{ $approval->status === 'approved' ? '#1b7d52' : '#b17408' }};font-weight:700;margin:0 0 12px}
.cr-revise label{font-size:13px;font-weight:700}
.cr-revise .form-control{font-size:14px;min-height:34px;border-radius:5px}
.cr-revise .fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:8px}.cr-revise .fields>.form-group{min-width:0}.cr-revise .form-group{margin-bottom:10px}.cr-revise textarea.form-control{min-height:72px}.cr-revise .alert{padding:10px 12px}.cr-revise button[type=submit]{background:{{ $approval->status === 'approved' ? '#18824a' : '#cf911a' }};border-color:transparent}.cr-revise .reason-label{font-weight:700}
@media(max-width:650px){.cr-revise .fields{grid-template-columns:1fr}}
</style>
<div class="content-wrapper" style="margin:0!important;min-height:0!important;"><div class="cr-revise">
<h4>{{ $approval->status === 'approved' ? 'Correct Approved' : 'Revise Rejected' }} {{ $source->transaction_type }} Voucher</h4>
<div class="alert alert-warning">
@if($approval->status === 'approved')
Original approved voucher #{{ $source->type_id }} remains active while this correction is Pending. On approval the old voucher will be soft-deactivated and only the revised voucher will be posted. If rejected, the original remains active.
@else
Original voucher #{{ $source->type_id }} will remain rejected. Reason: {{ $approval->rejection_reason ?? 'Not recorded' }}. Revised voucher gets a new number and remains Pending until approved.
@endif
</div>
<form id="cash-revision-form" action="{{ route('finance.daily-vouchers.revise.store',$source->id) }}" method="post">
@csrf
<div class="fields">
<div class="form-group"><label>Voucher No</label><input class="form-control" value="{{ ($source->transaction_type === 'CP' ? 'DR' : $source->transaction_type).'-'.$source->type_id }}" readonly></div>
<div class="form-group"><label for="cr-revise-date">Date *</label><input id="cr-revise-date" name="date" class="form-control" type="date" value="{{ substr((string)$source->date,0,10) }}" required></div>
<div class="form-group"><label for="cr-revise-amount">Amount *</label><input id="cr-revise-amount" name="amount" class="form-control" type="number" min="0.01" step="0.01" value="{{ max((float)$source->amount_in,(float)$source->amount_out) }}" required></div>
</div>
@php
    $currentAccount = $accounts->firstWhere('id', (int) ($leg?->project_head_subheads_id ?? 0));
    $currentHead = (int) ($currentAccount?->head_accounting_id ?? 0);
    $currentType = (int) ($currentAccount?->acct_type ?? 0);
@endphp
<div class="fields">
<div class="form-group"><label for="cr-revise-type">Account Type *</label>
<select id="cr-revise-type" class="form-control" required>
<option value="">Select account type</option>
@foreach($accountTypes as $accountType)<option value="{{ $accountType->id }}" @selected($currentType === (int)$accountType->id)>{{ $accountType->name }}</option>@endforeach
</select></div>
<div class="form-group"><label for="cr-revise-head">Accounts *</label>
<select id="cr-revise-head" class="form-control" required><option value="">Select account</option>
@foreach($accounts->unique('head_accounting_id') as $account)
<option value="{{ $account->head_accounting_id }}" data-acct-type="{{ $account->acct_type }}" @selected($currentHead === (int)$account->head_accounting_id)>{{ $account->head_name }}</option>
@endforeach
</select></div>
<div class="form-group"><label for="cr-revise-account">Child Account *</label><select class="form-control" id="cr-revise-account" name="account_id" required>
<option value="">Select child account</option>
@foreach($accounts as $account)
<option value="{{ $account->id }}" data-head-id="{{ $account->head_accounting_id }}" data-acct-type="{{ $account->acct_type }}" @selected((int)($leg?->project_head_subheads_id ?? 0)===(int)$account->id)>{{ $account->sub_name }}</option>
@endforeach
</select></div>
</div>
<div class="fields"><div class="form-group"><label>Payment Type</label><input class="form-control" value="{{ [1=>'Cash',2=>'Online',3=>'Cheque'][(int)$source->payment_type] ?? 'Unknown' }}" readonly></div></div>
@if((int)$source->payment_type !== 1)
<div class="fields">
<div class="form-group"><label for="cr-revise-number">{{ (int)$source->payment_type === 3 ? 'Cheque Number' : 'Transaction Reference' }} *</label>
<input id="cr-revise-number" name="t_number" class="form-control" value="{{ $source->t_number }}" maxlength="255" required></div>
<div class="form-group"><label for="cr-revise-bank">Bank ID *</label>
<select id="cr-revise-bank" name="bank_id" class="form-control js-tomselect" required>
<option value="">Select bank</option>
@foreach(getPakistanBanks() as $bank)
<option value="{{ $bank['id'] }}" @selected((int)$source->bank_id===(int)$bank['id'])>{{ $bank['name'] }}</option>
@endforeach
</select></div>
<div class="form-group"><label for="cr-revise-passing-date">Bank / Passing Date *</label>
<input id="cr-revise-passing-date" name="passing_date" type="date" class="form-control" value="{{ $source->passing_date ? substr((string)$source->passing_date,0,10) : substr((string)$source->date,0,10) }}" required></div>
</div>
@if((int)$source->payment_type === 3)
<div class="form-group"><label for="cr-revise-cheque-purpose">Cheque Purpose *</label>
<input id="cr-revise-cheque-purpose" name="cheque_purpose" class="form-control" maxlength="500" value="{{ $source->cheque_purpose }}" required></div>
@endif
<div class="alert alert-info">Revised bank/cheque voucher will start with Pending clearance status. Existing bank decisions are never copied. Source with settled/bounced/returned history cannot be revised.</div>
@endif
<div class="form-group"><label for="cr-revise-description">Purpose / Description *</label><textarea id="cr-revise-description" name="description" class="form-control" rows="3" required>{{ $source->description }}</textarea></div>
<div class="form-group"><label class="reason-label" for="cr-revise-reason">{{ $approval->status === 'approved' ? 'Reason for Edit *' : 'Reason for revision *' }}</label><textarea id="cr-revise-reason" name="revision_reason" class="form-control" minlength="3" maxlength="500" rows="2" required></textarea></div>
<div class="text-danger mb-2" id="cash-revision-error" role="alert"></div>
<div class="text-right"><button type="submit" class="btn btn-primary" id="cash-revision-save">{{ $approval->status === 'approved' ? 'Submit Correction for Approval' : 'Revise & Resubmit for Approval' }}</button></div>
</form></div></div>
@endsection
@section('js')
<script>
$(function(){
 var $form=$('#cash-revision-form');
 var $type=$('#cr-revise-type'),$head=$('#cr-revise-head'),$child=$('#cr-revise-account');
 function filterHeads(){
   var type=String($type.val()||''),previous=String($head.val()||'');
   $head.find('option').each(function(){
     var $option=$(this),visible=!$option.val()||String($option.attr('data-acct-type'))===type;
     $option.prop('disabled',!visible).prop('hidden',!visible);
   });
   if($head.find('option:selected').prop('disabled'))$head.val('');
   filterChildren();
 }
 function filterChildren(){
   var type=String($type.val()||''),head=String($head.val()||'');
   $child.find('option').each(function(){
     var $option=$(this),visible=!$option.val()||
       (String($option.attr('data-acct-type'))===type && String($option.attr('data-head-id'))===head);
     $option.prop('disabled',!visible).prop('hidden',!visible);
   });
   if($child.find('option:selected').prop('disabled'))$child.val('');
 }
 $type.on('change',function(){$head.val('');filterHeads();});
 $head.on('change',function(){$child.val('');filterChildren();});
 filterHeads();
 if(window.parent!==window)window.parent.postMessage({type:'daily-voucher-edit-ready'},window.location.origin);
 $form.on('submit',function(event){
  event.preventDefault();var $button=$('#cash-revision-save');
  if($button.prop('disabled'))return;
  if(!$form[0].checkValidity()){$form[0].reportValidity();return;}
  $button.prop('disabled',true);$('#cash-revision-error').text('');
  $.ajax({url:$form.attr('action'),type:'POST',data:$form.serialize(),dataType:'json'})
  .done(function(result){
   if(window.parent!==window)window.parent.postMessage({type:'daily-voucher-edit-saved',message:result.message},window.location.origin);
   else window.location.assign(@json(route('finance.daily-vouchers.index',['type'=>$source->transaction_type])));
  }).fail(function(xhr){
   var errors=xhr.responseJSON&&xhr.responseJSON.errors;
   $('#cash-revision-error').text(errors?Object.values(errors).flat().join(' '):(xhr.responseJSON&&xhr.responseJSON.message)||'Revision could not be saved.');
  }).always(function(){$button.prop('disabled',false);});
 });
});
</script>
@endsection