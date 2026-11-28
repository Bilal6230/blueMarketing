@extends('admin.layouts.master')

@section('content')
@if(request()->boolean('embed'))
<style>
html,body{margin:0!important;padding:0!important;overflow-x:hidden!important;background:white!important}
.main-sidebar,.main-header,.main-footer,.navbar,.control-sidebar,#load_screen,.preloader{display:none!important;visibility:hidden!important;pointer-events:none!important}
.main-content.page-content{margin:0!important;padding:0!important;min-height:0!important;width:100%!important;max-width:100%!important;position:static!important}
html body.sidebar-mini.sidebar-collapse .main-content.page-content>.content-wrapper,
html body.sidebar-collapse .main-content.page-content>.content-wrapper,
html body.sidebar-mini .main-content.page-content>.content-wrapper,
html body .main-content.page-content>.content-wrapper{margin:0!important;padding:0!important;left:0!important;right:auto!important;width:100%!important;max-width:100%!important;min-height:0!important;box-sizing:border-box!important}
.content-wrapper>.content,.content-wrapper>.content>.container-fluid{padding:0!important;margin:0!important;max-width:100%!important;width:100%!important}
.content-wrapper .card{margin:0!important;border:0!important;box-shadow:none!important;border-radius:0!important}
.content-wrapper .card-header{display:none!important}
.content-wrapper .card-body{padding:14px 18px 20px!important}
#journalForm .row{row-gap:9px}
#journalForm label{font-size:13px;font-weight:700}
#journalForm .form-control{min-height:34px;font-size:13px}
@media(max-width:720px){.content-wrapper .card-body{padding:10px!important}}
</style>
@endif

    <div class="content-wrapper">
        <div class="content">
            <div class="container-fluid mt-1">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-warning text-white">
                        <h4 class="mb-0">{{ $title }}</h4>
                    </div>
                    <div class="card-body">
                        @if($readOnly ?? false)
                            <div class="alert alert-info mb-3" role="status">
                                <strong>Approved Commission Voucher — View only.</strong>
                                The voucher has been posted to the ledgers. Its accounting entries are
                                protected from direct editing; use the authorized correction/reversal
                                workflow to change a posted voucher.
                            </div>
                        @endif
                        <form id="journalForm" action="{{ route('commision.voucher.update', request()->boolean('embed') ? ['id'=>$voucher->id,'embed'=>1] : ['id'=>$voucher->id]) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <fieldset @if($readOnly ?? false) disabled @endif>
                            <!-- Voucher Details -->
                            <div class="row mb-4">
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <label class="fbox">Voucher No.</label>
                                        <div class="input-group">
                                            <input type="text" value="1" name="action" hidden />
                                            <input type="text" class="form-control" name="voucher_number"
                                                value="{{ \App\Services\Finance\CommissionVoucherNumber::display($voucher->voucher_number) }}"
                                                autocomplete="off" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <label class="fbox">Reference</label>
                                        <div class="input-group">
                                            <input type="text"
                                                class="form-control @error('reference') is-invalid @enderror"
                                                name="reference" value="{{ $voucher->reference }}" autocomplete="off">
                                            @error('reference')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <label class="fbox">Date</label>
                                        <div class="input-group">
                                            <input type="text" name="date" class="date form-control"
                                                value="{{ $voucher->date }}" data-input>
                                            <input type="hidden" id="hiddenDate" name="hiddenDate">
                                            @error('date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="description" class="form-label"><strong>Description</strong></label>
                                    <textarea id="description" name="description" class="form-control" rows="3" required>{{ $voucher->description }}</textarea>
                                </div>
                            </div>

                            <!-- Dynamic Table for Journal Entries -->
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered fvc-entry-table" id="journalTable">
                                    <thead class="bg-warning text-white">
                                        <tr>
                                            <th>Account</th>
                                            <th>Sub-Account</th>
                                            <th>Description</th>
                                            <th>Debit (Out)</th>
                                            <th>Credit (IN)</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($voucher->details as $index => $detail)
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="detail_ids[]" value="{{ $detail->id }}">
                                                    <select name="accounts[]" class="form-control account-select fvc-tom-select"
                                                        required>
                                                        <option value="">Select Account</option>
                                                        @foreach ($accounts as $account)
                                                            <option value="{{ $account->head_accounting_id }}"
                                                                {{ $detail->account->head_accounting_id == $account->head_accounting_id ? 'selected' : '' }}>
                                                                {{ $account->headAccounting->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    @php
                                                        $headSubHeadAccounts = App\Models\ProjectHeadSubhead::with([
                                                            'subheadAccounting',
                                                            'headAccounting',
                                                        ])
                                                            ->withSum('ledgers as total_in', 'amount_in')
                                                            ->withSum('ledgers as total_out', 'amount_out')
                                                            ->where([
                                                                'head_accounting_id' =>
                                                                    $detail->account->head_accounting_id,
                                                            ])
                                                            ->where(['project_id' => $selectedProjectId])
                                                            ->get();
                                                    @endphp
                                                    <select name="sub_accounts[]"
                                                        class="form-control sub-account-select fvc-tom-select" required>
                                                        <option value="">Select Sub-Account</option>
                                                        @if ($detail->account)
                                                            @foreach ($headSubHeadAccounts as $subheadaccount)
                                                                <option value="{{ $subheadaccount->id }}"
                                                                    {{ $detail->account->subhead_accounting_id == $subheadaccount->subheadAccounting->id ? 'selected' : '' }}>
                                                                    {{ $subheadaccount->subheadAccounting->name ?? 'N/A' }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="line_description[]" class="form-control"
                                                        value="{{ $detail->description }}">
                                                </td>
                                                <td>
                                                    <input type="number" name="credit[]" class="form-control"
                                                        step="0.01" value="{{ $detail->credit }}">
                                                </td>
                                                <td>
                                                    <input type="number" name="debit[]" class="form-control" step="0.01"
                                                        value="{{ $detail->debit }}">
                                                </td>

                                                <td>
                                                    <button type="button" class="btn btn-success btn-sm add-row">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm remove-row">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td><input type="text" id="totalCredit" class="form-control"
                                                    value="{{ $voucher->total_credit }}" readonly></td>
                                            <td><input type="text" id="totalDebit" class="form-control"
                                                    value="{{ $voucher->total_debit }}" readonly></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <div class="form-group mt-2">
                                <label for="journalAmountWords" class="fbox">Amount in Words</label>
                                <input id="journalAmountWords" class="form-control" type="text"
                                    placeholder="Amount in words will appear automatically" disabled readonly
                                    style="background:#f1f5f9;color:#20364d;font-size:15px;font-weight:600;opacity:1">
                            </div>
                            </div>

                            <!-- Submit Button -->
                            @unless($readOnly ?? false)
                            <div class="text-center">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Update Commision Voucher
                                </button>
                            </div>
                            @endunless
                            </fieldset>
                        </form>
                        @if(!($readOnly ?? false) && \App\Services\Finance\FinanceWorkflowAccess::enabled() && $voucher->status === 'draft')
                            @can('can approve')
                                <form id="commissionApproveForm" action="{{ route('commision.voucher.approve', request()->boolean('embed') ? ['id'=>$voucher->id,'embed'=>1] : ['id'=>$voucher->id]) }}" method="POST" class="text-center mt-3">
                                    @csrf
                                    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                                    <button type="submit" class="btn btn-success">Approve saved voucher and post</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Validation Error</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Total Debit and Credit must be equal. Please review your entries.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Submission</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to Update this Journal Voucher?</p>
                </div>
                   <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmSubmit">Yes, Submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        $(document).ready(function() {
            var fvcFeedback=$.financeVoucherFeedback;
            function updateSelects($scope){
                if(fvcFeedback)fvcFeedback.enhanceSelects($scope.find('select.fvc-tom-select'));
            }
            updateSelects($('#journalForm'));
            @if($readOnly ?? false)
            // Dynamic TomSelect controls also need explicit disabling. This
            // is UX-only; the server still rejects posted voucher mutations.
            $('#journalForm').find('select.fvc-tom-select').each(function(){
                $(this).prop('disabled',true);
                if(this.tomselect)this.tomselect.disable();
            });
            @endif
            // Add a new row to the table
            $(document).on('click', '.add-row', function() {
                const newRow = `
                <tr>
                    <td>
                                    <input type="hidden" name="detail_ids[]" value="">
                        <select name="accounts[]" class="form-control account-select fvc-tom-select" required>
                            <option value="">Select Account</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->head_accounting_id }}">{{ $account->headAccounting->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="sub_accounts[]" class="form-control sub-account-select fvc-tom-select" required>
                            <option value="">Select Sub-Account</option>
                        </select>
                    </td>
                    <td><input type="text" name="line_description[]" class="form-control"></td>
                    <td><input type="number" name="credit[]" class="form-control" step="0.01"></td>
                    <td><input type="number" name="debit[]" class="form-control" step="0.01"></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-row">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
                $('#journalTable tbody').append(newRow);
                updateSelects($('#journalTable tbody tr:last'));
            });

            // Remove a row from the table
            $(document).on('click', '.remove-row', function() {
                $(this).closest('tr').find('select.fvc-tom-select').each(function(){if(this.tomselect)this.tomselect.destroy();});
                $(this).closest('tr').remove();
                calculateTotals();
            });

            // Update Total Debit and Credit
            $(document).on('input', 'input[name="debit[]"], input[name="credit[]"]', function() {
                calculateTotals();
            });

            // Calculate Totals
            function calculateTotals() {
                let totalDebit = 0;
                let totalCredit = 0;

                $('input[name="debit[]"]').each(function() {
                    totalDebit += parseFloat($(this).val()) || 0;
                });
                $('input[name="credit[]"]').each(function() {
                    totalCredit += parseFloat($(this).val()) || 0;
                });

                $('#totalDebit').val(totalDebit.toFixed(2));
                $('#totalCredit').val(totalCredit.toFixed(2));
                var wordingAmount=totalDebit>0 ? totalDebit : totalCredit;
                $('#journalAmountWords').val(wordingAmount>0 && window.numberToWords
                    && typeof numberToWords.toWords==='function' ? numberToWords.toWords(wordingAmount) : '');
            }

            $(function(){ calculateTotals(); });

            // Trigger Submission Confirmation Modal
            $('#journalForm').on('submit', function(e) {
                e.preventDefault();
                const totalDebit = parseFloat($('#totalDebit').val()) || 0;
                const totalCredit = parseFloat($('#totalCredit').val()) || 0;

                if (totalDebit !== totalCredit) {
                    // Show Error Modal
                    $('#errorModal').modal('show');
                } else {
                    // Show Confirmation Modal
                    $('#confirmationModal').modal('show');
                }
            });

            // AJAX only: show toast, close parent modal and refresh listing
            // through the same event contract as the CV Create form.
            var cvUpdateBusy=false;
            $('#confirmSubmit').on('click.fvcCommissionEdit', function() {
                if(cvUpdateBusy)return;
                var $form=$('#journalForm'),$button=$(this),$body=$form.closest('.card-body');
                cvUpdateBusy=true;$button.prop('disabled',true);
                $('#confirmationModal').modal('hide');
                if(fvcFeedback)fvcFeedback.skeleton($body,true);
                $.ajax({
                    url:$form.attr('action'),type:'POST',
                    data:$form.serialize(),dataType:'json',
                    headers:{Accept:'application/json'}
                }).done(function(response){
                    if(response && response.status==='success'){
                        if(window.parent!==window&&window.parent.jQuery){
                            var parentJQ=window.parent.jQuery;
                            parentJQ(window.parent.document)
                                .trigger('finance:commission-saved',[response]);
                            // Do not leave the edited iframe visible after an
                            // AJAX success, even if an older host page has no
                            // event listener for the shared voucher event.
                            var $modals=parentJQ(window.parent.document).find('.modal.show');
                            $modals.each(function(){
                                if(parentJQ.fn.modal)parentJQ(this).modal('hide');
                            });
                            var refreshed=false;
                            if(parentJQ.fn.dataTable){
                                parentJQ(window.parent.document).find('table').each(function(){
                                    if(parentJQ.fn.dataTable.isDataTable(this)){
                                        var table=parentJQ(this).DataTable();
                                        if(table.ajax&&typeof table.ajax.reload==='function'){
                                            table.ajax.reload(null,false);
                                        }else{
                                            table.draw(false);
                                        }
                                        refreshed=true;
                                    }
                                });
                            }
                            if(!refreshed)window.parent.location.reload();
                        }else{
                            if(fvcFeedback)fvcFeedback.toast('success',response.message||'Commission voucher updated.');
                            window.location.assign(response.redirect||@json(route('commision.voucher.index')));
                        }
                    }else if(fvcFeedback){
                        fvcFeedback.toast('error','Commission update was not confirmed.');
                    }
                }).fail(function(xhr){
                    if(fvcFeedback)fvcFeedback.toast('error',
                        fvcFeedback.error(xhr,'Commission voucher could not be updated.'));
                }).always(function(){
                    cvUpdateBusy=false;$button.prop('disabled',false);
                    if(fvcFeedback)fvcFeedback.skeleton($body,false);
                });
            });

            // Approval has the same JSON/feedback contract as Edit. Never
            // navigate an iframe to a Laravel 409 debug exception page.
            var cvApprovalBusy=false;
            $('#commissionApproveForm').on('submit.fvcCommissionApprove',function(event){
                event.preventDefault();
                if(cvApprovalBusy)return;
                var $form=$(this),$button=$form.find('button[type=submit]'),
                    $body=$form.closest('.card-body');
                cvApprovalBusy=true;$button.prop('disabled',true);
                if(fvcFeedback)fvcFeedback.skeleton($body,true);
                $.ajax({
                    url:$form.attr('action'),type:'POST',
                    data:$form.serialize(),dataType:'json',
                    headers:{Accept:'application/json'}
                }).done(function(response){
                    if(!response||response.status!=='success'){
                        var error=(response&&response.message)||'Commission approval was not confirmed.';
                        if(fvcFeedback)fvcFeedback.toast('error',error);
                        $('#errorModal .modal-body').text(error);
                        $('#errorModal').modal('show');
                        return;
                    }
                    if(window.parent!==window&&window.parent.jQuery){
                        var parentJQ=window.parent.jQuery;
                        parentJQ(window.parent.document).trigger('finance:commission-saved',[response]);
                        parentJQ(window.parent.document).find('.modal.show').each(function(){
                            if(parentJQ.fn.modal)parentJQ(this).modal('hide');
                        });
                        // Reload the shared register: approved status and
                        // ledger-linked balances are immediately reflected.
                        window.parent.location.reload();
                    }else{
                        if(fvcFeedback)fvcFeedback.toast('success',response.message);
                        window.location.assign(response.redirect||@json(route('commision.voucher.index')));
                    }
                }).fail(function(xhr){
                    var message=(xhr.responseJSON&&xhr.responseJSON.message)
                        ||(fvcFeedback?fvcFeedback.error(xhr,'Commission approval failed.'):
                            'Commission approval failed. Please check voucher details.');
                    if(fvcFeedback)fvcFeedback.toast('error',message);
                    $('#errorModal .modal-body').text(message);
                    $('#errorModal').modal('show');
                }).always(function(){
                    cvApprovalBusy=false;$button.prop('disabled',false);
                    if(fvcFeedback)fvcFeedback.skeleton($body,false);
                });
            });

            // Fetch Sub-Accounts dynamically via AJAX when account is selected
            $(document).on('change', '.account-select', function() {
                const accountID = $(this).val();
                const subAccountSelect = $(this).closest('tr').find('.sub-account-select');
                if(subAccountSelect[0]&&subAccountSelect[0].tomselect)subAccountSelect[0].tomselect.destroy();

                if (accountID) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: @json((int) $selectedProjectId),
                            accountID: accountID,
                            action: 'get_child',
                            active_only: true,
                            _token: '{{ csrf_token() }}' // CSRF token for Laravel
                        },
                        success: function(response) {
                            // Clear and populate the sub-account dropdown
                            subAccountSelect.empty().append(
                                '<option value="">Select Sub-Account</option>');

                            if (response.length > 0) {
                                $.each(response, function(index, item) {
                                    if (item.subhead_accounting) {
                                        subAccountSelect.append('<option value="' + item
                                            .id + '">' + item.subhead_accounting
                                            .name + '</option>');
                                    }
                                });
                            } else {
                                alert('No sub-accounts found for the selected account.');
                            }
                        },
                        error: function(xhr, status, error) {
                            if(fvcFeedback)fvcFeedback.toast('error','Unable to load sub-accounts.');
                        },
                        complete: function(){updateSelects(subAccountSelect.closest('tr'));}
                    });
                } else {
                    // Reset the sub-account dropdown if no account is selected
                    subAccountSelect.empty().append('<option value="">Select Sub-Account</option>');
                    updateSelects(subAccountSelect.closest('tr'));
                }
            });
        });
    </script>
@endsection
