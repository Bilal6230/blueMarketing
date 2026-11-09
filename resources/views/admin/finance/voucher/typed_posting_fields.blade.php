@if(\App\Services\Finance\V2OriginalWorkflowAccess::enabled())
    @php($postingAccounts = $postingAccounts ?? app('finance.original.accounts'))
    <fieldset class="border rounded p-2 my-2 w-100" data-original-posting-fields>
        <legend class="h6">Posting accounts</legend>
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        @if($includePostingDate ?? false)
            <label>Payment date <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required></label>
        @endif
        <div class="row">
            @foreach(['Debit account', 'Credit account'] as $index => $label)
                <div class="col-md-6">
                    <label>{{ $label }}</label>
                    <select name="posting_lines[{{ $index }}][account_id]" class="form-control" data-posting-allocation required>
                        <option value="">Select account</option>
                        @foreach($postingAccounts as $postingAccount)
                            <option value="{{ $postingAccount->id }}" @selected((string) old('posting_lines.'.$index.'.account_id') === (string) $postingAccount->id)>
                                {{ $postingAccount->headAccounting?->name }} / {{ $postingAccount->subheadAccounting?->name }} (#{{ $postingAccount->id }})
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="posting_lines[{{ $index }}][debit]" value="0.00" data-posting-allocation data-posting-side="{{ $index === 0 ? 'amount' : 'zero' }}">
                    <input type="hidden" name="posting_lines[{{ $index }}][credit]" value="0.00" data-posting-allocation data-posting-side="{{ $index === 1 ? 'amount' : 'zero' }}">
                </div>
            @endforeach
        </div>
        <small>Choose the accounts before posting. Pending bank receipts are posted when cleared.</small>
    </fieldset>
    @once
        @push('script')
<script>
$(function(){
    const normalized=value=>{
        const raw=String(value??'').replaceAll(',','').trim();
        if(!/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/.test(raw))return null;
        const parts=raw.split('.');
        return String(BigInt(parts[0]))+'.'+(parts[1]||'').padEnd(2,'0');
    };
    window.originalPostingTotal=values=>{
        let cents=0n;
        for(const value of values){
            const amount=normalized(value);
            if(amount===null)return null;
            cents+=BigInt(amount.replace('.',''));
        }
        return String(cents/100n)+'.'+String(cents%100n).padStart(2,'0');
    };
    window.syncOriginalPostingForm=form=>{
        const $form=$(form);
        if(!$form.length||!$form.find('[data-original-posting-fields]').length)return;
        const payment=String($form.find('[name="payment_type"]').val()||'');
        const $status=$form.find('select[name="passing_status"],input[name="passing_status"],[name="pending_status"]').first();
        const status=$form.attr('data-posting-decision')||$status.val();
        const bank=['2','3'].includes(payment);
        const clearing=$form.find('[name="action"]').val()==='extra_charge_clearance'||
            !!$form.find('[name="selected_pending_payment_id"]').val();
        const enabled=!bank||(clearing&&status==='1');
        $form.find('[data-bank-settlement-account]')
            .prop('disabled',!enabled).prop('required',enabled&&bank);
        const amount=normalized($form.find('[name="amount"]').val())||'0.00';
        $form.find('[data-posting-allocation]').each(function(){
            const $input=$(this);
            $input.prop('disabled',!enabled);
            const side=$input.attr('data-posting-side');
            if(side)$input.val(side==='amount'?amount:'0.00');
        });
    };
    window.originalPostingPayload=form=>{
        window.syncOriginalPostingForm(form);
        return Object.fromEntries(new FormData(form).entries());
    };
    window.refreshOriginalPostingKey=form=>{
        const $key=$(form).find('[name="request_key"]');
        if($key.length)$key.val(Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join(''));
    };
    $(document).on('input.originalPosting change.originalPosting','input,select,textarea',function(){
        window.syncOriginalPostingForm(this.form);
    });
    $(document).on('change.originalPosting','[data-bank-settlement-account]',function(){
        const $field=$(this),mapping=$field.find('option:selected').attr('data-account-mapping-id');
        const $account=$field.closest('form').find('[name="posting_lines[0][account_id]"]');
        if(mapping&&$account.length)$account.val(mapping).trigger('change');
    });
    $(document).on('click.originalPosting','button[name="passing_status"],button[type="submit"],input[type="submit"],button:not([type])',function(){
        if(!this.form)return;
        if($(this).is('button[name="passing_status"]'))$(this.form).attr('data-posting-decision',$(this).val());
        window.syncOriginalPostingForm(this.form);
    });
    $(document).on('reset.originalPosting','form',function(){
        const form=this;
        queueMicrotask(()=>{
            window.refreshOriginalPostingKey(form);
            window.syncOriginalPostingForm(form);
        });
    });
    $(document).on('submit.originalPosting','form',function(){
        window.syncOriginalPostingForm(this);
    });
    $('[data-original-posting-fields]').each(function(){
        window.syncOriginalPostingForm($(this).closest('form')[0]);
    });
    $(document).on('ajax:panel-updated.originalPosting',function(_event,detail){
        $(detail?.panel||document).find('[data-original-posting-fields]').each(function(){
            window.syncOriginalPostingForm($(this).closest('form')[0]);
        });
    });
});
</script>
@endpush
    @endonce
@endif
