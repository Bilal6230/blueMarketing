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
        <script>
            (() => {
                const normalized = value => {
                    const raw = String(value ?? '').replaceAll(',', '').trim();
                    if (!/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/.test(raw)) return null;
                    const parts = raw.split('.');
                    return String(BigInt(parts[0])) + '.' + (parts[1] || '').padEnd(2, '0');
                };
                window.originalPostingTotal = values => {
                    let cents = 0n;
                    for (const value of values) {
                        const amount = normalized(value);
                        if (amount === null) return null;
                        cents += BigInt(amount.replace('.', ''));
                    }
                    return String(cents / 100n) + '.' + String(cents % 100n).padStart(2, '0');
                };
                window.syncOriginalPostingForm = form => {
                    if (!form) return;
                    const fieldset = form.querySelector('[data-original-posting-fields]');
                    if (!fieldset) return;
                    const payment = form.querySelector('[name="payment_type"]')?.value;
                    const statusField = form.querySelector('select[name="passing_status"], input[name="passing_status"], [name="pending_status"]');
                    const status = form.dataset.postingDecision || statusField?.value;
                    const bank = ['2', '3'].includes(payment);
                    const clearing = form.querySelector('[name="action"]')?.value === 'extra_charge_clearance' || !!form.querySelector('[name="selected_pending_payment_id"]')?.value;
                    const enabled = !bank || (clearing && status === '1');
                    const amount = normalized(form.querySelector('[name="amount"]')?.value) || '0.00';
                    fieldset.querySelectorAll('[data-posting-allocation]').forEach(input => {
                        input.disabled = !enabled;
                        if (input.dataset.postingSide) input.value = input.dataset.postingSide === 'amount' ? amount : '0.00';
                    });
                };
                window.originalPostingPayload = form => {
                    window.syncOriginalPostingForm(form);
                    return Object.fromEntries(new FormData(form).entries());
                };
                window.refreshOriginalPostingKey = form => {
                    const key = form?.querySelector('[name="request_key"]');
                    if (key) key.value = Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
                };
                document.addEventListener('input', event => window.syncOriginalPostingForm(event.target.form), true);
                document.addEventListener('change', event => window.syncOriginalPostingForm(event.target.form), true);
                document.addEventListener('click', event => {
                    const button = event.target.closest('button[name="passing_status"]');
                    if (button?.form) {
                        button.form.dataset.postingDecision = button.value;
                        window.syncOriginalPostingForm(button.form);
                    }
                    const submit = event.target.closest('button[type="submit"], input[type="submit"], button:not([type])');
                    if (submit?.form) window.syncOriginalPostingForm(submit.form);
                }, true);
                document.addEventListener('reset', event => queueMicrotask(() => {
                    window.refreshOriginalPostingKey(event.target);
                    window.syncOriginalPostingForm(event.target);
                }), true);
                document.addEventListener('submit', event => window.syncOriginalPostingForm(event.target), true);
                const initialize = () => document.querySelectorAll('[data-original-posting-fields]').forEach(el => window.syncOriginalPostingForm(el.closest('form')));
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
                else queueMicrotask(initialize);
            })();
        </script>
    @endonce
@endif
