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
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">{{ $title }}</h4>
                    </div>
                    <div class="card-body">
                        @unless($commissionPilotCreateAllowed)
                        <div class="alert alert-warning" role="alert">
                            Commission Voucher creation is protected: the current connection is not an
                            approved isolated V2 finance pilot with writes enabled. No accounting data
                            has been changed. Ask the administrator to use the approved isolated
                            test database for this workflow.
                        </div>
                        @endunless
                        <div id="cv-entry-feedback" class="alert alert-danger d-none" role="alert" aria-live="polite"></div>
                        <form id="journalForm" novalidate action="{{ route('commision.voucher.store', request()->boolean('embed') ? ['embed'=>1] : []) }}" method="POST">
                            @csrf
                            <!-- Voucher Details -->
                            <div class="row mb-4">
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <label class="fbox">Voucher No.</label>
                                        <div class="input-group">
                                            <input type="text" value="1" name="action" hidden />
                                            <input type="text" class="form-control " name="voucher_number"
                                                value="{{ $nextCommissionNumber !== null ? \App\Services\Finance\CommissionVoucherNumber::display($nextCommissionNumber) : 'Select a project first' }}"
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
                                                name="reference" value="{{ old('reference') }}" autocomplete="off">
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
                                            <input type="text" name="date" class="date form-control" data-input>
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
                                    <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
                                </div>
                            </div>

                            <!-- Dynamic Table for Journal Entries -->
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered fvc-entry-table" id="journalTable">
                                    <thead class="text-white">
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
                                        <tr>
                                            <td>
                                                <select name="accounts[]" class="form-control account-select fvc-tom-select"
                                                    required>
                                                    <option value="">Select Account</option>
                                                    @foreach ($accounts as $account)
                                                        <option value="{{ $account->head_accounting_id }}">
                                                            {{ $account->headAccounting->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="sub_accounts[]"
                                                    class="form-control sub-account-select fvc-tom-select" required>
                                                    <option value="">Select Sub-Account</option>
                                                </select>
                                            </td>
                                            <td><input type="text" name="line_description[]" class="form-control"></td>
                                            <td><input type="number" name="debit[]" class="form-control" step="0.01">
                                            </td>
                                            <td><input type="number" name="credit[]" class="form-control" step="0.01">
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
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td><input type="text" id="totalDebit" class="form-control" readonly></td>
                                            <td><input type="text" id="totalCredit" class="form-control" readonly></td>
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
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary" @unless($commissionPilotCreateAllowed) disabled title="Requires approved isolated V2 pilot" @endunless>
                                    <i class="fas fa-save"></i> Save Commision Voucher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        $(document).ready(function() {
            var feedback=$.financeVoucherFeedback;
            function notify(type,message){if(feedback)feedback.toast(type,message);}
            function updateSelects($scope){if(feedback)feedback.enhanceSelects($scope.find('select.fvc-tom-select'));}
            updateSelects($('#journalForm'));
            // Add a new row to the table
            $(document).on('click', '.add-row', function() {
                const newRow = `
                <tr>
                    <td>
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
                    <td><input type="number" name="debit[]" class="form-control" step="0.01"></td>
                    <td><input type="number" name="credit[]" class="form-control" step="0.01"></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-row">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
                $('#journalTable tbody').append(newRow);
                updateSelects($('#journalTable tbody tr:last'));
            });
            // Populate the minimum two lines after the delegated handler exists.
            if ($('#journalTable tbody tr').length === 1) {
                $('#journalTable .add-row').first().trigger('click');
            }

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

            // Keep validation visible: native HTML validation cannot focus the
            // original required select after TomSelect has hidden it.
            // Embedded CV uses one AJAX submit, never a second nested modal.
            function cvDateValue(){
                var field=$('#journalForm [name="date"]')[0];
                if(!field)return '';
                if(field._flatpickr && field._flatpickr.selectedDates.length){
                    return field._flatpickr.formatDate(field._flatpickr.selectedDates[0],'Y-m-d');
                }
                var value=$.trim($(field).val());
                if(/^\\d{4}-\\d{2}-\\d{2}$/.test(value))return value;
                var parsed=new Date(value);
                if(isNaN(parsed.getTime()))return '';
                var pad=function(n){return String(n).padStart(2,'0');};
                return parsed.getFullYear()+'-'+pad(parsed.getMonth()+1)+'-'+pad(parsed.getDate());
            }
            function entryError(message,$field){
                var safe=String(message||'Please check the voucher fields.');
                $('#cv-entry-feedback').text(safe).removeClass('d-none');
                notify('error',safe);
                if($field && $field.length){
                    var $focus=$field;
                    if($field[0].tomselect)$focus=$($field[0].tomselect.control);
                    $focus.trigger('focus');
                }
            }
            $('#journalForm').on('submit.fvcCv',function(e){
                e.preventDefault();
                var $form=$(this),$button=$form.find('button[type="submit"]').first();
                if($form.data('fvc-busy')||$button.prop('disabled'))return;
                $('#cv-entry-feedback').addClass('d-none').text('');
                var description=$.trim($form.find('[name="description"]').val());
                if(!description){
                    entryError('Enter a description before saving.',$form.find('[name="description"]'));
                    return;
                }
                var date=cvDateValue();
                if(!date){
                    entryError('Select a valid voucher date.',$form.find('[name="date"]'));
                    return;
                }
                var $rows=$form.find('#journalTable tbody tr');
                if($rows.length<2){
                    entryError('Add at least two accounting lines.');return;
                }
                var invalid=null, debit=0,credit=0;
                $rows.each(function(){
                    var $row=$(this),$head=$row.find('.account-select'),
                        $child=$row.find('.sub-account-select');
                    if(!$head.val()||!$child.val()){
                        invalid=invalid||(!$head.val()?$head:$child);
                        return;
                    }
                    $row.find('input[name="debit[]"],input[name="credit[]"]').each(function(){
                        if(!$.trim($(this).val()))$(this).val('0.00');
                    });
                    debit+=parseFloat($row.find('input[name="debit[]"]').val())||0;
                    credit+=parseFloat($row.find('input[name="credit[]"]').val())||0;
                });
                if(invalid){entryError('Select Account and Sub-Account for every line.',invalid);return;}
                if(debit<=0||credit<=0||Math.abs(debit-credit)>0.005){
                    entryError('Debit and Credit must be greater than zero and equal.');return;
                }
                $form.data('fvc-busy',true);
                $button.prop('disabled',true);
                var $loading=$form.closest('.card-body');
                if(feedback)feedback.skeleton($loading,true);
                var data=$form.serializeArray().filter(function(item){return item.name!=='date';});
                data.push({name:'date',value:date});
                $.ajax({
                    url:$form.attr('action'),type:'POST',data:$.param(data),
                    dataType:'json',headers:{Accept:'application/json'}
                }).done(function(response){
                    if(window.parent!==window && window.parent.jQuery){
                        window.parent.jQuery(window.parent.document)
                            .trigger('finance:commission-saved',[response]);
                    }else{
                        notify('success',response.message||'Commission draft saved.');
                        window.location.href=response.redirect||'{{ route('commision.voucher.index') }}';
                    }
                }).fail(function(xhr){
                    var message=feedback?feedback.error(xhr,'Could not save Commission Voucher.'):
                        'Could not save Commission Voucher.';
                    entryError(message);
                }).always(function(){
                    $form.data('fvc-busy',false);
                    $button.prop('disabled',false);
                    if(feedback)feedback.skeleton($loading,false);
                });
            });

            // Fetch Sub-Accounts dynamically via AJAX when account is selected
            $(document).on('change', '.account-select', function() {
                const accountID = $(this).val();
                const subAccountSelect = $(this).closest('tr').find('.sub-account-select');
                // Rebuild TomSelect from server-authoritative child options.
                if(subAccountSelect[0]&&subAccountSelect[0].tomselect)subAccountSelect[0].tomselect.destroy();

                if (accountID) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: {{ getSelectedTown() }}, // Replace with the dynamic project ID if applicable
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
                                notify('warning','No sub-accounts found for the selected account.');
                            }
                        },
                        error: function(xhr, status, error) {
                            notify('error','Unable to load sub-accounts. Please retry.');
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



@section('modal')
    <!-- Error Modal -->
    <div class="modal fade fvc-modern-modal fvc-modal-blue" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="errorModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade fvc-modern-modal fvc-modal-blue" id="confirmationModal" tabindex="-1" role="dialog"
        aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Submission</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Save this Commission Voucher as an unposted draft?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmSubmit">Yes, Submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection
