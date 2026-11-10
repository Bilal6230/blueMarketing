@extends('admin.layouts.master')

@section('content')
@if(request()->boolean('embed'))
<style>
.main-sidebar,.main-header,.main-footer,.navbar,.control-sidebar,#load_screen,.preloader{display:none!important;visibility:hidden!important;pointer-events:none!important}
html,body{width:100%!important;min-width:0!important;margin:0!important;padding:0!important;overflow-x:hidden!important;background:#fff!important}
.main-content.page-content{margin:0!important;padding:0!important;width:100%!important;max-width:none!important;min-height:0!important;height:auto!important;overflow:visible!important}
.content-wrapper{margin:0!important;padding:0!important;width:100%!important;max-width:none!important;min-height:0!important;background:#fff!important}
.content-wrapper>.content,.content-wrapper>.content>.container-fluid{margin:0!important;padding:0!important;width:100%!important;max-width:none!important}
.content-wrapper .card{width:100%!important;max-width:none!important;margin:0!important;border:0!important;border-radius:0!important;box-shadow:none!important}
.content-wrapper .card-header{display:none!important}
.content-wrapper .card-body{padding:14px 18px 22px!important}
#journalForm{width:100%!important;max-width:none!important;margin:0!important}
#journalForm>.row{margin-left:-6px!important;margin-right:-6px!important;margin-bottom:13px!important}
#journalForm>.row>[class*="col-"]{padding-left:6px!important;padding-right:6px!important}
#journalForm .input-group{display:flex!important;flex-direction:column!important;width:100%!important;min-width:0!important}
#journalForm .input-group>.form-control,#journalForm .input-group>input{width:100%!important;max-width:100%!important;flex:0 0 auto!important}
#journalForm .fbox,#journalForm label{font-size:12px!important;font-weight:700!important;color:#203758!important;margin-bottom:5px!important}
#journalForm .form-control{font-size:13px!important;min-height:34px;border-radius:5px!important}
#journalForm textarea{min-height:75px!important;resize:vertical}
#journalForm .table-responsive{width:100%!important;margin:0 0 12px!important;overflow-x:auto!important}
#journalForm #journalTable{width:100%!important;min-width:760px!important;table-layout:fixed!important;margin-bottom:0!important;font-size:12px}
#journalForm #journalTable th{background:#e8f1ff!important;color:#203758!important;border-bottom:2px solid #087cff!important;vertical-align:middle!important;padding:9px 6px!important}
#journalForm #journalTable td{vertical-align:middle!important;padding:6px!important}
#journalForm #journalTable td .form-control{min-width:0!important;width:100%!important}
#journalForm #journalTable .btn{padding:5px 8px!important}
#journalForm>.text-center{text-align:right!important;margin:0!important;padding-top:10px!important;border-top:1px solid #e3eaf6}
#journalForm>.text-center .btn-primary{background:#0876ef!important;border-color:#0876ef!important}
@media(max-width:720px){.content-wrapper .card-body{padding:10px!important}#journalForm #journalTable{min-width:760px!important}}
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
                        @if(session('success'))
                            <div class="alert alert-success" role="status">{{ session('success') }}</div>
                        @endif
                        <form id="journalForm" action="{{ route('journal.voucher.update', $voucher->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <!-- Voucher Details -->
                            <div class="row mb-4">
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <label class="fbox">Voucher No.</label>
                                        <div class="input-group">
                                            <input type="text" value="1" name="action" hidden />
                                            <input type="text" class="form-control" name="voucher_number"
                                                value="{{$type}}-{{$voucher->voucher_number}}"
                                                autocomplete="off" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <label class="fbox">Reference</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control @error('reference') is-invalid @enderror"
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
                                    <textarea id="description" name="description" class="form-control" rows="3"
                                        required>{{ $voucher->description }}</textarea>
                                </div>
                            </div>

                            <!-- Dynamic Table for Journal Entries -->
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered" id="journalTable">
                                    <colgroup>
                                        <col style="width: 15%"> <!-- Account (DECREASED) -->
                                        <col style="width: 15%"> <!-- Sub-Account (DECREASED) -->
                                        <col style="width: 35%"> <!-- Description (INCREASED) -->
                                        <col style="width: 10%"> <!-- Debit -->
                                        <col style="width: 10%"> <!-- Credit -->
                                        <col style="width: 15%"> <!-- Action -->
                                    </colgroup>
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
                                        @foreach($voucher->details as $index => $detail)
                                            <tr>
                                                <td>
                                                    <select name="accounts[]" class="form-control select2 account-select"
                                                        required>
                                                        <option value="">Select Account</option>
                                                        @foreach($accounts as $account)
                                                            <option value="{{ $account->head_accounting_id }}" {{ $detail->account->head_accounting_id == $account->head_accounting_id ? 'selected' : '' }}>
                                                                {{ $account->headAccounting->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    @php
                                                        $headSubHeadAccounts = App\Models\ProjectHeadSubhead::with(['subheadAccounting', 'headAccounting'])
                                                            ->withSum('ledgers as total_in', 'amount_in')
                                                            ->withSum('ledgers as total_out', 'amount_out')
                                                            ->where(['head_accounting_id' => $detail->account->head_accounting_id])
                                                            ->where(['project_id' => $selectedProjectId])
                                                            ->get();
                                                    @endphp
                                                    <select name="sub_accounts[]"
                                                        class="form-control select2 sub-account-select" required>
                                                        <option value="">Select Sub-Account</option>
                                                        @if($detail->account)
                                                            @foreach($headSubHeadAccounts as $subheadaccount)
                                                                <option value="{{ $subheadaccount->id }}" {{ $detail->account->subhead_accounting_id == $subheadaccount->subheadAccounting->id ? 'selected' : '' }}>
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
                                                    <input type="number" name="credit[]" class="form-control" step="0.01"
                                                        value="{{ $detail->credit }}">
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
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Update Journal Voucher
                                </button>
                            </div>
                        </form>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to Update this Journal Voucher?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmSubmit">Yes, Submit</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script>
        $(document).ready(function () {
            // Add a new row to the table
            $(document).on('click', '.add-row', function () {
                const newRow = `
                            <tr>
                                <td>
                                    <select name="accounts[]" class="form-control select2 account-select" required>
                                        <option value="">Select Account</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->head_accounting_id }}">{{ $account->headAccounting->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="sub_accounts[]" class="form-control select2 sub-account-select" required>
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
            });

            // Remove a row from the table
            $(document).on('click', '.remove-row', function () {
                $(this).closest('tr').remove();
                calculateTotals();
            });

            // Update Total Debit and Credit
            $(document).on('input', 'input[name="debit[]"], input[name="credit[]"]', function () {
                calculateTotals();
            });

            // Calculate Totals
            function calculateTotals() {
                let totalDebit = 0;
                let totalCredit = 0;

                $('input[name="debit[]"]').each(function () {
                    totalDebit += parseFloat($(this).val()) || 0;
                });
                $('input[name="credit[]"]').each(function () {
                    totalCredit += parseFloat($(this).val()) || 0;
                });

                $('#totalDebit').val(totalDebit.toFixed(2));
                $('#totalCredit').val(totalCredit.toFixed(2));
            }

            // Trigger Submission Confirmation Modal
            $('#journalForm').on('submit', function (e) {
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

            // Confirm and Submit the Form
            $('#confirmSubmit').on('click', function () {
                $('#journalForm')[0].submit();
            });

            // Fetch Sub-Accounts dynamically via AJAX when account is selected
            $(document).on('change', '.account-select', function () {
                const accountID = $(this).val();
                const subAccountSelect = $(this).closest('tr').find('.sub-account-select');
                const projectID = '{{getSelectedTown()}}';
                if (accountID) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: projectID, // Replace with the dynamic project ID if applicable
                            accountID: accountID,
                            action: 'get_child',
                            active_only: true,
                            _token: '{{ csrf_token() }}' // CSRF token for Laravel
                        },
                        success: function (response) {
                            // Clear and populate the sub-account dropdown
                            subAccountSelect.empty().append('<option value="">Select Sub-Account</option>');

                            if (response.length > 0) {
                                $.each(response, function (index, item) {
                                    if (item.subhead_accounting) {
                                        subAccountSelect.append('<option value="' + item.id + '">' + item.subhead_accounting.name + '</option>');
                                    }
                                });
                            } else {
                                alert('No sub-accounts found for the selected account.');
                            }
                        },
                        error: function (xhr, status, error) {
                            alert('Error: ' + error);
                        }
                    });
                } else {
                    // Reset the sub-account dropdown if no account is selected
                    subAccountSelect.empty().append('<option value="">Select Sub-Account</option>');
                }
            });
        });
    </script>
@endsection
