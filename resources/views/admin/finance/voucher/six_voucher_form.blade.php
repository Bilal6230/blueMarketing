@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@php
    $cashOnly = in_array($type, ['CCR','CDR'], true);
    $bankOnly = in_array($type, ['BOR','BOP'], true);
@endphp
<style>
.six-voucher-shell{max-width:1180px;margin:16px auto;padding:0 16px}
.six-voucher-card{border:1px solid #d8e5f3;border-radius:12px;background:#fff;box-shadow:0 5px 22px rgba(20,58,103,.06);overflow:hidden}
.six-voucher-header{background:#1767a8;color:#fff;padding:18px 22px}
.six-voucher-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;padding:22px}
.six-voucher-grid .full-span{grid-column:1/-1}
.six-voucher-grid label{display:block;font-weight:600;color:#29435d;font-size:13px;margin-bottom:6px}
.six-voucher-grid .form-control{min-height:40px;border-radius:6px}
.six-voucher-foot{padding:15px 22px;border-top:1px solid #e6edf5}
@media(max-width:850px){.six-voucher-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.six-voucher-grid{grid-template-columns:minmax(0,1fr)}}
</style>
<div class="six-voucher-shell" id="sixVoucherShell" data-voucher-type="{{ $type }}">
    <div class="mb-3 d-flex flex-wrap align-items-center" style="gap:8px">
        @foreach(['CR','DR','CCR','CDR','BOR','BOP'] as $code)
            <a class="btn btn-sm {{ $type === $code ? 'btn-primary':'btn-outline-primary' }}"
               href="{{ route('finance.voucher.six-form', ['type'=>strtolower($code)]) }}">{{ $code }}</a>
        @endforeach
    </div>
    <div class="six-voucher-card">
        <div class="six-voucher-header">
            <h4 class="mb-1">{{ $title }} ({{ $type }})</h4>
            <small>Project {{ $projectId }} · Form layout preparation</small>
        </div>
        <div class="alert alert-warning m-3 mb-0" role="status">
            This new form is being integrated with protected financial posting. Saving is disabled
            until voucher numbering, approval and ledger mapping are verified.
            @if($legacyRoute)
                Existing {{ $type === 'DR' ? 'Cash Out / CP' : 'Cash In / CR' }} entry is still available
                <a href="{{ $legacyRoute }}">here</a>; historical records remain unchanged.
            @endif
        </div>
        <form id="sixVoucherForm" method="post" action="#" novalidate>
            @csrf
            <input type="hidden" name="voucher_type" value="{{ $type }}">
            <div class="six-voucher-grid">
                <div>
                    <label for="sixVoucherNo">Voucher No</label>
                    <input type="text" id="sixVoucherNo" class="form-control" value="{{ $type }} — assigned on safe save" readonly>
                </div>
                <div>
                    <label for="sixVoucherDate">Date</label>
                    <input type="date" id="sixVoucherDate" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
                <div>
                    <label for="sixVoucherAmount">Amount</label>
                    <input type="number" id="sixVoucherAmount" name="amount" class="form-control" min="0.01" step="0.01" required>
                </div>
                <div>
                    <label for="sixAccountType">Account Type</label>
                    <select id="sixAccountType" name="acct_type" class="form-control" required>
                        <option value="">Select Account Type</option>
                        @foreach($accountTypes as $accountType)
                            <option value="{{ $accountType->id }}">{{ $accountType->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sixHeadAccount">Accounts</label>
                    <select id="sixHeadAccount" name="accounts_id" class="form-control" required>
                        <option value="">Select Account</option>
                    </select>
                </div>
                <div>
                    <label for="sixChildAccount">Child Account</label>
                    <select id="sixChildAccount" name="subaccounts_id" class="form-control" required>
                        <option value="">Select Child Account</option>
                    </select>
                </div>
                @unless($cashOnly)
                    <div>
                        <label for="sixPaymentType">Payment Type</label>
                        <select id="sixPaymentType" name="payment_type" class="form-control">
                            @unless($bankOnly)<option value="1">Cash</option>@endunless
                            <option value="2">Online</option>
                            <option value="3">Cheque</option>
                        </select>
                    </div>
                    <div class="six-bank-field" style="display:none">
                        <label for="sixTransactionNo">Transaction / Cheque Number</label>
                        <input type="text" id="sixTransactionNo" name="t_number" class="form-control">
                    </div>
                    <div class="six-bank-field" style="display:none">
                        <label for="sixBank">Bank</label>
                        <select id="sixBank" name="bank_id" class="form-control">
                            <option value="">Select Bank</option>
                            @foreach(getPakistanBanks() as $bank)
                                <option value="{{ $bank['id'] }}">{{ $bank['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="six-bank-field" style="display:none">
                        <label for="sixPassingDate">Passing Date</label>
                        <input type="date" id="sixPassingDate" name="passing_date" class="form-control">
                    </div>
                    <div class="six-cheque-field" style="display:none">
                        <label for="sixChequePurpose">Cheque Purpose</label>
                        <input type="text" id="sixChequePurpose" name="cheque_purpose" maxlength="500" class="form-control">
                    </div>
                    @if($bankOnly)
                        <div class="full-span">
                            <label for="sixVerifiedSource">Verified Online/Cheque Source</label>
                            <select id="sixVerifiedSource" name="verified_source_id" class="form-control" disabled>
                                <option value="">Eligible verified sources available after protected integration</option>
                            </select>
                        </div>
                    @endif
                    @if($type === 'DR')
                        <div class="full-span">
                            <div class="alert alert-info mb-0">Existing Cash Out pending-receipt clearance, locked amount and Pass/Return/Bounce remain on the legacy workflow until safely migrated.</div>
                        </div>
                    @endif
                @endunless
                <div>
                    <label for="sixAttachment">Attachment / File Uploader (optional)</label>
                    <input type="file" id="sixAttachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <small class="text-muted">PDF or image; server validation will apply when saving is enabled.</small>
                </div>
                <div class="full-span">
                    <label for="sixNarration">Description / Narration</label>
                    <textarea id="sixNarration" name="detail" rows="3" maxlength="255" class="form-control" required></textarea>
                </div>
            </div>
            <div class="six-voucher-foot text-right">
                <button type="submit" class="btn btn-primary" disabled title="Protected financial posting not yet integrated">Save Voucher (integration pending)</button>
            </div>
        </form>
    </div>
</div>
<script>
$(function(){
    var $root=$('#sixVoucherShell');
    var accounts=@json($accounts);
    function putOptions($select, items, placeholder){
        $select.empty().append($('<option>').val('').text(placeholder));
        $.each(items,function(_,item){$select.append($('<option>').val(item.id).text(item.name));});
    }
    function syncHeads(){
        var selected=String($root.find('#sixAccountType').val()||'');
        var seen={};var options=[];
        $.each(accounts,function(_,item){
            if(String(item.account_type)!==selected||seen[item.head_id])return;
            seen[item.head_id]=true;options.push({id:item.head_id,name:item.head_name});
        });
        putOptions($root.find('#sixHeadAccount'),options,'Select Account');
        putOptions($root.find('#sixChildAccount'),[],'Select Child Account');
    }
    function syncChildren(){
        var head=String($root.find('#sixHeadAccount').val()||'');
        var selected=String($root.find('#sixAccountType').val()||'');
        var seen={};var options=[];
        $.each(accounts,function(_,item){
            if(String(item.head_id)!==head||String(item.account_type)!==selected||seen[item.child_id])return;
            seen[item.child_id]=true;options.push({id:item.child_id,name:item.child_name});
        });
        putOptions($root.find('#sixChildAccount'),options,'Select Child Account');
    }
    function syncPayment(){
        var method=String($root.find('#sixPaymentType').val()||'1');
        var bank=method==='2'||method==='3';
        $root.find('.six-bank-field').toggle(bank);
        $root.find('.six-cheque-field').toggle(method==='3');
        $root.find('#sixTransactionNo,#sixBank,#sixPassingDate').prop('required',bank);
        $root.find('#sixChequePurpose').prop('required',method==='3');
    }
    $root.on('change','#sixAccountType',syncHeads)
         .on('change','#sixHeadAccount',syncChildren)
         .on('change','#sixPaymentType',syncPayment);
    $root.find('#sixVoucherForm').on('submit',function(e){e.preventDefault();});
    syncHeads();syncPayment();
});
</script>
@endsection
