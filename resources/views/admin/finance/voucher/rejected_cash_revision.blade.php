@extends('admin.layouts.master')
@section('title','Revise Rejected Cash Voucher')
@section('content')
@if(request()->boolean('embed'))
<style>
.main-sidebar,.main-header,.main-footer,.navbar,.control-sidebar,#load_screen,.preloader{display:none!important}
.main-content.page-content,.main-content.page-content>.content-wrapper{margin:0!important;padding:0!important;width:100%!important;max-width:100%!important}
body{overflow:auto!important}
</style>
@endif
<style>
.cr-revise{max-width:960px;margin:0 auto;background:#fff;padding:18px;font-size:13px}
.cr-revise h4{font-size:18px;color:#086fe0;font-weight:700}
.cr-revise label{font-size:13px;font-weight:700}
.cr-revise .form-control{font-size:14px}
.cr-revise .fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
@media(max-width:650px){.cr-revise .fields{grid-template-columns:1fr}}
</style>
<div class="content-wrapper"><div class="cr-revise">
<h4>Revise Rejected {{ $source->transaction_type }} Voucher</h4>
<div class="alert alert-warning">Original voucher #{{ $source->type_id }} will remain rejected. Reason: {{ $approval->rejection_reason ?? 'Not recorded' }}. Revised voucher gets a new number and remains Pending until approved.</div>
<form id="cash-revision-form" action="{{ route('finance.daily-vouchers.revise.store',$source->id) }}" method="post">
@csrf
<div class="fields">
<div class="form-group"><label for="cr-revise-date">Date *</label><input id="cr-revise-date" name="date" class="form-control" type="date" value="{{ substr((string)$source->date,0,10) }}" required></div>
<div class="form-group"><label for="cr-revise-amount">Amount *</label><input id="cr-revise-amount" name="amount" class="form-control" type="number" min="0.01" step="0.01" value="{{ max((float)$source->amount_in,(float)$source->amount_out) }}" required></div>
<div class="form-group"><label for="cr-revise-account">Child Account *</label><select class="form-control" id="cr-revise-account" name="account_id" required>
<option value="">Select account</option>
@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((int)$leg?->project_head_subheads_id===(int)$account->id)>{{ $account->head_name }} / {{ $account->sub_name }}</option>@endforeach
</select></div>
</div>
@if((int)$source->payment_type !== 1)
<div class="fields">
<div class="form-group"><label for="cr-revise-number">{{ (int)$source->payment_type === 3 ? 'Cheque Number' : 'Transaction Reference' }} *</label>
<input id="cr-revise-number" name="t_number" class="form-control" value="{{ $source->t_number }}" maxlength="255" required></div>
<div class="form-group"><label for="cr-revise-bank">Bank ID *</label>
<input id="cr-revise-bank" type="number" min="1" name="bank_id" class="form-control" value="{{ $source->bank_id }}" required></div>
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
<div class="form-group"><label for="cr-revise-reason">Reason for revision *</label><textarea id="cr-revise-reason" name="revision_reason" class="form-control" minlength="3" maxlength="500" rows="2" required></textarea></div>
<div class="text-danger mb-2" id="cash-revision-error" role="alert"></div>
<div class="text-right"><button type="submit" class="btn btn-primary" id="cash-revision-save">Revise & Resubmit for Approval</button></div>
</form></div></div>
@endsection
@section('js')
<script>
$(function(){
 var $form=$('#cash-revision-form');
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