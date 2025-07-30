@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <div class="content">
        <div class="container-fluid mt-1">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0">{{ $title }}</h4>
                </div>
                <div class="card-body">
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
                                        <input type="text" class="form-control" name="voucher_number" value="{{$type}}-{{get_jv_number($voucher->voucher_number)}}" autocomplete="off" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <label class="fbox">Reference</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('reference') is-invalid @enderror" name="reference" value="{{ $voucher->reference }}" autocomplete="off">
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
                                        <input type="text" name="date" class="date form-control" value="{{ $voucher->date }}" data-input>
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
                            <table class="table table-bordered" id="journalTable">
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
                                                <select name="accounts[]" class="form-control select2 account-select" required>
                                                    <option value="">Select Account</option>
                                                    @foreach($accounts as $account)
                                                        <option value="{{ $account->head_accounting_id }}" {{ $detail->account_id == $account->head_accounting_id ? 'selected' : '' }}>
                                                            {{ $account->headAccounting->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="sub_accounts[]" class="form-control select2 sub-account-select" required>
                                                    <option value="">Select Sub-Account</option>
                                                    @if($detail->account)
                                                        <option value="{{ $detail->account_id }}" selected>
                                                            {{ $detail->account->subheadAccounting->name ?? 'N/A' }}
                                                        </option>
                                                    @endif
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="line_description[]" class="form-control" value="{{ $detail->description }}">
                                            </td>
                                            <td>
                                                <input type="number" name="credit[]" class="form-control" step="0.01" value="{{ $detail->credit }}">
                                            </td>
                                            <td>
                                                <input type="number" name="debit[]" class="form-control" step="0.01" value="{{ $detail->debit }}">
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
                                        <td><input type="text" id="totalCredit" class="form-control" value="{{ $voucher->total_credit }}" readonly></td>
                                        <td><input type="text" id="totalDebit" class="form-control" value="{{ $voucher->total_debit }}" readonly></td>
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

<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
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

            if (accountID) {
                $.ajax({
                    url: '{{ route('get_account') }}',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        projectID: 3, // Replace with the dynamic project ID if applicable
                        accountID: accountID,
                        action: 'get_child',
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