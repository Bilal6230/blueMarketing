@extends('admin.layouts.master')
@section('title','Voucher Correction')
@section('content')
@php
    $editStatus = $approval?->status ?? 'posted';
    // The displayed DR voucher is historically stored as CP in customer_ledger.
    // Keep the original type for persistence/approval; normalize only the Edit UI layout.
    $editLayoutSeries = (string) $revisionPolicyType === 'CP' ? 'DR' : (string) $revisionPolicyType;
    $matchAddLayout = in_array($editLayoutSeries, ['CR','DR','BOR','BOP','CCR','CDR'], true);
    $isCashCounterLayout = in_array($editLayoutSeries, ['CCR','CDR'], true);
    $usesApproval = app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class)
        ->policy((int)getSelectedTown(),$revisionPolicyType) !== null;
@endphp
@if(request()->boolean('embed'))
<style>
.main-sidebar,.main-header,.main-footer,.navbar,.control-sidebar,#load_screen,.preloader{display:none!important}
.main-content.page-content,.main-content.page-content>.content-wrapper{margin:0!important;padding:0!important;width:100%!important;max-width:100%!important}
body{overflow:auto!important}
</style>
@endif
<style>
.cr-revise{max-width:none;margin:0;background:#fff;padding:16px 18px;font-size:13px;min-height:0}
.cr-revise h4{font-size:16px;color:{{ $editStatus === 'approved' ? '#1b7d52' : '#b17408' }};font-weight:700;margin:0 0 12px}
.cr-revise label{font-size:13px;font-weight:700}
.cr-revise .form-control{font-size:14px;min-height:34px;border-radius:5px}
.cr-revise .fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:8px}.cr-revise .fields>.cr-revise-counter-field{grid-column:1/-1}.cr-revise .fields>.form-group{min-width:0}.cr-revise .form-group{margin-bottom:10px}.cr-revise textarea.form-control{min-height:72px}.cr-revise .alert{padding:10px 12px}.cr-revise button[type=submit]{background:{{ $editStatus === 'approved' ? '#18824a' : '#cf911a' }};border-color:transparent}.cr-revise .reason-label{font-weight:700}
@media(max-width:650px){.cr-revise .fields{grid-template-columns:1fr}}

@if($matchAddLayout)
.cr-revise.add-matched-edit #cash-revision-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 14px;align-items:start}
.cr-revise.add-matched-edit #cash-revision-form>.fields{display:contents!important}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group{grid-column:span 1!important;min-width:0;margin:0!important}
.cr-revise.add-matched-edit #cash-revision-form>.fields:first-of-type>.form-group:first-child{order:1}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.cr-revise-words-field{order:5}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-date){order:2}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-amount){order:4}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-payment-type){order:6}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-type){order:7}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-head){order:8}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-account){order:9}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-number){order:10}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-bank){order:11}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-passing-date){order:12}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-counter-account){order:3}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-bank-counter){order:3}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-legacy-counter){order:3}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-cheque-purpose){order:13}
.cr-revise.add-matched-edit #cash-revision-form>.alert{grid-column:1/-1;order:14;margin:0}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-description){grid-column:1/-1;order:15;margin:0}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-attachment){grid-column:1/-1;order:16;margin:0}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-reason){grid-column:1/-1;order:17;margin:0}
.cr-revise.add-matched-edit #cash-revision-form>#cash-revision-error{grid-column:1/-1;order:18;margin:0}
.cr-revise.add-matched-edit #cash-revision-form>.text-right{grid-column:1/-1;order:19}
.cr-revise.add-matched-edit #cash-revision-form label{display:block;margin-bottom:5px}
.cr-revise.add-matched-edit #cash-revision-form .form-control{width:100%;min-width:0}
.cr-revise.add-matched-edit #cr-revise-amount-words{background:#f1f5f9;color:#20364d;font-size:15px;font-weight:600;opacity:1}
.cr-revise.add-matched-edit select.js-tomselect{display:block;width:100%;min-height:35px;border:1px solid #ced4da;border-radius:5px;background:#fff;padding:6px 10px;font-size:14px}
.cr-revise.add-matched-edit select.js-tomselect.tomselected{display:none!important}
@if($isCashCounterLayout)
/* CCR/CDR Edit mirrors Add: identity/counter, account hierarchy, amount/words/file. */
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-type){order:4}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-head){order:5}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-account){order:6}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-amount){order:7}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.cr-revise-words-field{order:8}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-attachment){
    grid-column:span 1;order:9;margin:0;min-width:0
}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-description){order:10}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-reason){order:11}
.cr-revise.add-matched-edit #cash-revision-form>#cash-revision-error{order:12}
.cr-revise.add-matched-edit #cash-revision-form>.text-right{order:13}
.cr-revise.add-matched-edit #cash-revision-form>.fields>.form-group:has(#cr-revise-payment-type){display:none}
.cr-revise.add-matched-edit #cash-revision-form>.form-group:has(#cr-revise-attachment) .text-muted{display:block}
@endif
/* Tom Select copies the original select's form-control class to its wrapper.
   Reset Bootstrap's outer input box; only .ts-control draws the field border. */
.cr-revise.add-matched-edit #cash-revision-form .ts-wrapper,
.cr-revise.add-matched-edit #cash-revision-form .ts-wrapper.form-control{
 display:block!important;width:100%!important;max-width:100%!important;min-width:0!important;
 height:auto!important;min-height:0!important;margin:0!important;padding:0!important;
 border:0!important;border-radius:0!important;background:transparent!important;
 background-image:none!important;box-shadow:none!important;line-height:normal!important;
}
.cr-revise.add-matched-edit #cash-revision-form .ts-wrapper .ts-control{
 width:100%!important;min-height:35px!important;box-sizing:border-box!important;
 border:1px solid #ced4da!important;border-radius:5px!important;
 padding:6px 10px!important;font-size:14px;box-shadow:none!important;
 background:#fff!important;
}
.cr-revise.add-matched-edit #cash-revision-form .ts-wrapper .ts-control input{
 border:0!important;box-shadow:none!important;min-height:0!important;padding:0!important;
 margin:0!important;width:auto!important;display:inline-block!important;
}
.cr-revise.add-matched-edit #cash-revision-form select.js-tomselect.tomselected{
 display:none!important;position:absolute!important;visibility:hidden!important;
}
.cr-revise.add-matched-edit .ts-wrapper.focus .ts-control{border-color:#80bdff!important;box-shadow:0 0 0 .15rem rgba(0,123,255,.15)}
.cr-revise.add-matched-edit .ts-dropdown{font-size:14px;z-index:1100}
@media(max-width:767px){.cr-revise.add-matched-edit #cash-revision-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.cr-revise.add-matched-edit #cash-revision-form{grid-template-columns:1fr}}
@endif
</style>
<div class="content-wrapper" style="margin:0!important;min-height:0!important;"><div class="cr-revise {{ $matchAddLayout ? 'add-matched-edit' : '' }}">
<h4>{{ $editStatus === 'rejected' ? 'Revise Rejected' : 'Edit' }} {{ $voucherDisplay }} Voucher</h4>

<form id="cash-revision-form" action="{{ route('finance.daily-vouchers.revise.store',$source->id) }}" method="post" enctype="multipart/form-data">
@csrf
<div class="fields">
<div class="form-group"><label>Voucher No</label><input class="form-control" value="{{ $voucherDisplay }}" readonly></div>
<div class="form-group"><label for="cr-revise-date">Date *</label><input id="cr-revise-date" name="date" class="form-control" type="date" value="{{ substr((string)$source->date,0,10) }}" required></div>
<div class="form-group"><label for="cr-revise-amount">Amount *</label><input id="cr-revise-amount" name="amount" class="form-control" type="number" min="0.01" step="0.01" value="{{ max((float)$source->amount_in,(float)$source->amount_out) }}" {{ $isBankRevision ? 'readonly' : '' }} required></div>
@if($matchAddLayout)
<div class="form-group cr-revise-words-field"><label for="cr-revise-amount-words">Amount in Words</label><input id="cr-revise-amount-words" class="form-control" type="text" disabled readonly placeholder="Amount in words will appear automatically"></div>
@endif
</div>
@php
    $currentAccount = $accounts->firstWhere('id', (int) ($leg?->project_head_subheads_id ?? 0));
    $currentHead = (int) ($currentAccount?->head_accounting_id ?? 0);
    $currentType = (int) ($currentAccount?->acct_type ?? 0);
@endphp
<div class="fields">
<div class="form-group"><label for="cr-revise-type">Account Type *</label>
<select id="cr-revise-type" class="js-tomselect" required>
<option value="">Select account type</option>
@foreach($accountTypes as $accountType)<option value="{{ $accountType->id }}" @selected($currentType === (int)$accountType->id)>{{ $accountType->name }}</option>@endforeach
</select></div>
<div class="form-group"><label for="cr-revise-head">Accounts *</label>
<select id="cr-revise-head" class="js-tomselect" required><option value="">Select account</option>
@foreach($accounts->unique('head_accounting_id') as $account)
<option value="{{ $account->head_accounting_id }}" data-acct-type="{{ $account->acct_type }}" @selected($currentHead === (int)$account->head_accounting_id)>{{ $account->head_name }}</option>
@endforeach
</select></div>
<div class="form-group"><label for="cr-revise-account">Child Account *</label><select class="js-tomselect" id="cr-revise-account" name="account_id" required>
<option value="">Select child account</option>
@foreach($accounts as $account)
<option value="{{ $account->id }}" data-head-id="{{ $account->head_accounting_id }}" data-acct-type="{{ $account->acct_type }}" @selected((int)($leg?->project_head_subheads_id ?? 0)===(int)$account->id)>{{ $account->sub_name }}</option>
@endforeach
</select></div>
</div>
@if($counterLeg && !$isBankRevision)
<div class="fields">
 <div class="form-group cr-revise-counter-field">
  <label for="cr-revise-counter-account">{{ in_array((string)$source->transaction_type,['CR','CCR'],true) ? 'Credit Account' : 'Debit Account' }} *</label>
  <select id="cr-revise-counter-account" name="counter_account_id" class="js-tomselect" required>
   <option value="">Select opposite posting account</option>
   @foreach($accounts as $counterOption)
    <option value="{{ $counterOption->id }}"
      @selected((int)$counterAccountId===(int)$counterOption->id)>
     {{ $counterOption->head_name }} / {{ $counterOption->sub_name }}
    </option>
   @endforeach
  </select>


 </div>
</div>
@elseif($isBankRevision && $counterLeg)
<div class="fields">
 <div class="form-group">
  <label for="cr-revise-bank-counter">{{ $revisionPolicyType==='BOR' ? 'Debit Account (Bank)' : 'Credit Account (Bank)' }}</label>
  <input id="cr-revise-bank-counter" class="form-control" type="text"
    value="{{ $bankCounterLabel ?? ('Linked bank account #'.$counterLeg->project_head_subheads_id) }}" readonly>
  <small class="text-muted">Verified bank posting account is linked to the original source. Account changes require audited bank correction.</small>
 </div>
</div>
@elseif(!$counterLeg)
<div class="fields">
 <div class="form-group">
  <label for="cr-revise-legacy-counter">Opposite Posting Account</label>
  <input id="cr-revise-legacy-counter" class="form-control" type="text"
    value="Legacy voucher — no linked counter-account" readonly>
  <small class="text-muted">This historical single-leg voucher is preserved; Edit cannot silently invent a second posting.</small>
 </div>
</div>
@endif
<div class="fields"><div class="form-group"><label for="cr-revise-payment-type">Payment Type</label><input id="cr-revise-payment-type" class="form-control" value="{{ [1=>'Cash',2=>'Online',3=>'Cheque'][(int)$source->payment_type] ?? 'Unknown' }}" readonly></div></div>
@if((int)$source->payment_type !== 1)
<div class="fields">
<div class="form-group"><label for="cr-revise-number">{{ (int)$source->payment_type === 3 ? 'Cheque Number' : 'Transaction Reference' }} *</label>
<input id="cr-revise-number" name="t_number" class="form-control" value="{{ $source->t_number }}" maxlength="255" {{ $isBankRevision ? 'readonly' : '' }} required></div>
<div class="form-group"><label for="cr-revise-bank">Bank ID *</label>
<select id="cr-revise-bank" name="bank_id" class="js-tomselect" {{ $isBankRevision ? 'disabled' : '' }} required>
<option value="">Select bank</option>
@foreach(getPakistanBanks() as $bank)
<option value="{{ $bank['id'] }}" @selected((int)$source->bank_id===(int)$bank['id'])>{{ $bank['name'] }}</option>
@endforeach
</select>
@if($isBankRevision)<input type="hidden" name="bank_id" value="{{ (int)$source->bank_id }}">@endif
</div>
<div class="form-group"><label for="cr-revise-passing-date">Bank / Passing Date *</label>
<input id="cr-revise-passing-date" name="passing_date" type="date" class="form-control" value="{{ $source->passing_date ? substr((string)$source->passing_date,0,10) : substr((string)$source->date,0,10) }}" {{ $isBankRevision ? 'readonly' : '' }} required></div>
</div>
@if((int)$source->payment_type === 3)
<div class="form-group"><label for="cr-revise-cheque-purpose">Cheque Purpose *</label>
<input id="cr-revise-cheque-purpose" name="cheque_purpose" class="form-control" maxlength="500" value="{{ $source->cheque_purpose }}" {{ $isBankRevision ? 'readonly' : '' }} required></div>
@endif
<div class="alert alert-info">
@if($isBankRevision)
Rejected BOR/BOP revision: original verified source, Amount, Cheque/Online Number, Bank, Passing Date and Cheque Purpose stay unchanged. You may revise voucher Date, Account/Child Account and Description. The same voucher number and allocation are retained; with approval ON, revised posting remains inactive until the authorized approver accepts it.
@else
Bank/cheque revision requires verified instrument allocation and a supported voucher identity.
@endif
</div>
@endif
<div class="form-group"><label for="cr-revise-description">Purpose / Description *</label><textarea id="cr-revise-description" name="description" class="form-control" rows="3" required>{{ $source->description }}</textarea></div>
<div class="form-group">
 <label for="cr-revise-attachment">Attachment / File Uploader (optional)</label>
 <input type="file" name="attachment" id="cr-revise-attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
 @unless($isCashCounterLayout)
 <small class="text-muted">PDF/image up to 5 MB. If approval is enabled, uploaded file becomes visible only after approval.</small>
 @endunless
 @if($currentAttachment)
 <div class="mt-1">Current attachment: <a target="_blank" rel="noopener noreferrer" href="{{ route('finance.voucher.cash-only.attachment',['attachment'=>$currentAttachment->id]) }}">{{ $currentAttachment->original_name }}</a></div>
 @endif
</div>
<div class="form-group"><label class="reason-label" for="cr-revise-reason">{{ $editStatus === 'rejected' ? 'Reason for revision *' : 'Reason for Edit *' }}</label><textarea id="cr-revise-reason" name="revision_reason" class="form-control" minlength="3" maxlength="500" rows="2" required></textarea></div>
<div class="text-danger mb-2" id="cash-revision-error" role="alert"></div>
<div class="text-right"><button type="submit" class="btn btn-primary" id="cash-revision-save">{{ $usesApproval ? ($editStatus === 'rejected' ? 'Revise & Resubmit for Approval' : 'Submit Correction for Approval') : 'Save Correction' }}</button></div>
</form></div></div>
@endsection
@section('js')
<script>
$(function(){
 var $form=$('#cash-revision-form');
 var $type=$('#cr-revise-type'),$head=$('#cr-revise-head'),$child=$('#cr-revise-account');
 // Keep the source option metadata for cascading Tom Select dropdowns.
 var allHeads=$head.find('option').map(function(){return {
  value:String(this.value),text:$(this).text().trim(),
  type:String($(this).attr('data-acct-type')||'')
 };}).get();
 var allChildren=$child.find('option').map(function(){return {
  value:String(this.value),text:$(this).text().trim(),
  type:String($(this).attr('data-acct-type')||''),
  head:String($(this).attr('data-head-id')||'')
 };}).get();
 function setFiltered($select,choices,selected){
  var ts=$select[0]&&$select[0].tomselect,value=String(selected||'');
  if(ts){
   ts.clear(true);ts.clearOptions();
   choices.forEach(function(o){ts.addOption({value:o.value,text:o.text});});
   ts.refreshOptions(false);
   if(value&&choices.some(function(o){return o.value===value;}))ts.setValue(value,true);
  }else{
   $select.empty();
   choices.forEach(function(o){$select.append(new Option(o.text,o.value));});
   $select.val(value&&choices.some(function(o){return o.value===value;})?value:'');
  }
 }
 function filterHeads(preserve){
  var type=String($type.val()||''),selected=preserve?String($head.val()||''):'';
  setFiltered($head,allHeads.filter(function(o){return !o.value||o.type===type;}),selected);
  filterChildren(preserve);
 }
 function filterChildren(preserve){
  var type=String($type.val()||''),head=String($head.val()||'');
  var selected=preserve?String($child.val()||''):'';
  setFiltered($child,allChildren.filter(function(o){return !o.value||(o.type===type&&o.head===head);}),selected);
 }
 // Keep the main party account distinct from the opposite posting leg.
 var $opposite=$('#cr-revise-counter-account');
 function validateOppositeAccount(){
   if(!$opposite.length)return true;
   var party=String($child.val()||''),opposite=String($opposite.val()||'');
   if(party && opposite && party===opposite){
     $opposite[0].setCustomValidity('Debit and Credit accounts must be different.');
     return false;
   }
   $opposite[0].setCustomValidity('');
   return true;
 }
 $child.on('change',validateOppositeAccount);
 $opposite.on('change',validateOppositeAccount);
 // Initialize the same Tom Select controls as Add while retaining prefilled values.
 if(typeof window.TomSelect==='function'){
  $form.find('select.js-tomselect').each(function(){
   if(this.tomselect)return;
   new TomSelect(this,{
    create:false,persist:false,maxItems:1,allowEmptyOption:true,
    onChange:function(){$(this.input).trigger('change');}
   });
  });
 }
 $type.on('change',function(){filterHeads(false);validateOppositeAccount();});
 $head.on('change',function(){filterChildren(false);validateOppositeAccount();});
 filterHeads(true);

 @if($matchAddLayout)
 function revisionWords(raw) {
  const n=Number(String(raw||'').replace(/,/g,''));
  if(!Number.isFinite(n)||n<0||n>999999999999.99)return '';
  const u=['Zero','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  const t=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  function chunk(n){let a=[];if(n>=100){a.push(u[Math.floor(n/100)]+' Hundred');n%=100;}if(n>=20){a.push(t[Math.floor(n/10)]);n%=10;}if(n)a.push(u[n]);return a.join(' ');}
  let whole=Math.floor(n),paisa=Math.round((n-whole)*100),parts=[];
  [['Billion',1e9],['Million',1e6],['Thousand',1e3]].forEach(function(p){const c=Math.floor(whole/p[1]);if(c){parts.push(chunk(c)+' '+p[0]);whole%=p[1];}});
  if(whole)parts.push(chunk(whole));
  return (parts.join(' ')||'Zero')+(paisa?' and '+chunk(paisa)+' Paisa':'');
 }
 function updateRevisionWords(){$('#cr-revise-amount-words').val(revisionWords($('#cr-revise-amount').val()));}
 $('#cr-revise-amount').on('input change',updateRevisionWords);
 updateRevisionWords();
 @endif
 if(window.parent!==window)window.parent.postMessage({type:'daily-voucher-edit-ready'},window.location.origin);
 $form.on('submit',function(event){
  event.preventDefault();var $button=$('#cash-revision-save');
  if($button.prop('disabled'))return;
  validateOppositeAccount();
  if(!$form[0].checkValidity()){$form[0].reportValidity();return;}
  $button.prop('disabled',true);$('#cash-revision-error').text('');
  $.ajax({url:$form.attr('action'),type:'POST',
   data:new FormData($form[0]),processData:false,contentType:false,dataType:'json'})
  .done(function(result){
   if(window.parent!==window)window.parent.postMessage({type:'daily-voucher-edit-saved',message:result.message},window.location.origin);
   else window.location.assign(@json(route('finance.daily-vouchers.index',['type'=>$isBankRevision ? $revisionPolicyType : $source->transaction_type])));
  }).fail(function(xhr){
   var errors=xhr.responseJSON&&xhr.responseJSON.errors;
   $('#cash-revision-error').text(errors?Object.values(errors).flat().join(' '):(xhr.responseJSON&&xhr.responseJSON.message)||'Revision could not be saved.');
  }).always(function(){$button.prop('disabled',false);});
 });
});
</script>
@endsection