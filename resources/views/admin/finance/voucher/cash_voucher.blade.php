@extends('admin.layouts.master')
@section('content')
    @php
        $voucherType = is_string($type) && in_array($type, ['CR', 'CP'], true) ? $type : '';
    @endphp
    <style>
        .page-item.active .page-link {
            background-color: {{ $pagination_color }} !important;
            color: #ffffff !important;
            border: 1px solid {{ $pagination_color }} !important;
        }
    </style>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        {{-- <span class="{{ $class }}">
                            {{ $title }}
                        </span> --}}
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <!-- Cash Voucher Form -->
                    <div class="col-12 mb-4">
                        <div class="buttons d-flex justify-content-between align-items-baseline">
                            <div class="d-flex gap-2 mb-3" style="gap: 10px;" id="voucher-tabs-container">
                                <button class="btn {{ $theme['primary'] }} btn-sm" id="add-new-voucher-btn">
                                    <i class="fas fa-plus"></i> Add New Cash Voucher
                                </button>
                                <div id="voucher-tabs" class="d-flex gap-2" style="gap: 10px;">
                                    <div class="voucher-tab-wrapper position-relative">
                                        <button class="btn btn-primary btn-sm active voucher-tab"
                                            data-tab="1">Voucher#1</button>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-danger btn-sm" id="clear-vouchers">
                                <i class="fas fa-minus"></i> Clear
                            </button>
                        </div>
                        <!-- Loader -->
                        <div id="voucher-loader" class="text-center" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Processing...</p>
                        </div>

                        <div class="custom_card">
                            <!-- Form Card Loader -->
                            <div id="form-card-loader" class="card-loader-overlay" style="display: none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Processing...</p>
                            </div>

                            <div class="card-body  {{ $theme['card_body'] }}">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold"> {{ $title }}</h5>

                                    {{-- Buttons --}}
                                    <div class="d-flex justify-content-end mt-3" style="gap: 10px">
                                        <button type="button " class="btn {{ $theme['secondary'] }}" data-toggle="modal"
                                            data-target="#confirmedModal" onclick="saveAsDraft()">Save as Draft</button>
                                        <button type="button" class="btn {{ $theme['primary'] }}" id="submit-button"
                                            tab-number="1">Save</button>
                                    </div>
                                </div>


                                @can('create voucher')
                                    <form action="{{ route('ledger.store') }}" method="POST" enctype="multipart/form-data"
                                        id="voucherForm">
                                        @csrf
                                        <div class="row">
                                            <div class="col-10">
                                                <div class="row g-2 align-items-end">

                                                    {{-- Serial No (hidden) --}}
                                                    <div class="col-12 d-none">
                                                        <label class="fbox mb-1">Serial No.</label>
                                                        <input type="text" value="1" name="action" hidden />
                                                        <input type="text"
                                                            class="form-control form-control-sm @error('reference') is-invalid @enderror"
                                                            name="reference" value="{{ old('reference') }}" autocomplete="off"
                                                            required>
                                                        @error('reference')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    {{-- Voucher No (small) --}}
                                                    <div class="col-6 col-md-3 col-lg-1">
                                                        <label class="fbox mb-1">Voucher No</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            name="voucher_number" id="voucher_number"
                                                            value="{{ $voucherType }}-{{ $latest_voucher_number ?? '' }}"
                                                            autocomplete="off" readonly>
                                                        @error('reference')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    {{-- Reference No (hidden) --}}
                                                    <div class="col-12 d-none">
                                                        <label class="fbox mb-1">Reference No</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            name="voucher"
                                                            value="{{ $voucherType }}-{{ $latest_voucher_number ?? '' }}"
                                                            autocomplete="off" readonly>
                                                        @error('reference')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    {{-- Date (small) --}}
                                                    <div class="col-6 col-md-3 col-lg-2">
                                                        <label class="fbox mb-1">Date</label>
                                                        <input type="text" name="date"
                                                            class="date voucher-date form-control form-control-sm" data-input>
                                                        <input type="hidden" id="hiddenDate" name="hiddenDate">
                                                        @error('date')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    {{-- Amount (small) --}}
                                                    <div class="col-6 col-md-3 col-lg-2">
                                                        <label class="fbox mb-1">Amount</label>
                                                        <input id="numberInput" oninput="formatAmount(this)" type="text"
                                                            class="form-control form-control-sm @error('amount') is-invalid @enderror"
                                                            placeholder="Amount" name="amount" value="{{ old('amount') }}">
                                                        @error('amount')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    {{-- Account Type (small) --}}
                                                    <div class="col-6 col-md-3 col-lg-2">
                                                        <label class="fbox mb-1">Account Type</label>
                                                            <select
                                                                class="js-tomselect form-select form-select-sm @error('acct_type') is-invalid @enderror"
                                                                placeholder=" " autocomplete="off" name="acct_type"
                                                                id="acct_type">
                                                                <option value="">Select Account Type</option>
                                                                @foreach ($accountTypes as $accountType)
                                                                    <option value="{{ $accountType->id }}">{{ $accountType->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        @error('acct_type')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    {{-- Accounts (wide) --}}
                                                    <div class="col-6 col-md-3 col-lg-2">
                                                        <label class="fbox mb-1">Accounts</label>
                                                        <select
                                                            class="js-tomselect form-select form-select-sm @error('accounts_id') is-invalid @enderror"
                                                            placeholder=" " name="accounts_id" id="accounts_id">
                                                            <option value=""></option>
                                                            @foreach ($headaccounts as $v)
                                                                <option value="{{ $v->head_accounting_id }}">
                                                                    {{ $v->headAccounting->name ?? '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('accounts_id')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    {{-- Child Account (wide) --}}
                                                    <div class="col-6 col-md-3 col-lg-3">
                                                        <label class="fbox mb-1">Child Account</label>
                                                        <select class="js-tomselect form-select form-select-sm"
                                                            placeholder=" " name="subaccounts_id" id="subaccounts_id">
                                                            <option value=""></option>
                                                            @foreach ($partyaccounts as $v)
                                                                @php
                                                                    $balance = $v->balance ?? 0;
                                                                    $balanceClass =
                                                                        $balance < 0 ? 'text-danger' : 'text-success';
                                                                @endphp
                                                                <option value="{{ $v->subhead_accounting_id }}">
                                                                    {{ $v->subheadAccounting->name ?? '' }}
                                                                    & Balance = {{ number_format($balance, 2) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('subaccounts_id')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="col-6 col-md-3 col-lg-2">

                                                        <label class="fbox mb-1">Payment Type</label>
                                                        <select class="js-tomselect form-select form-select-sm"
                                                            placeholder=" " name="payment_type" id="payment_type">
                                                            <option value="1">Cash</option>
                                                            <option value="2">Online</option>
                                                            <option value="3">Check</option>
                                                        </select>
                                                        @error('payment_type')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="col-6 col-md-3 col-lg-2 bank_group" style="display: none">
                                                        <label class="fbox mb-1">Number</label>
                                                        <input id="t_number" type="text"
                                                            class="form-control form-control-sm @error('t_number') is-invalid @enderror"
                                                            placeholder="Transaction Number" name="t_number"
                                                            value="{{ old('t_number') }}">
                                                        @error('t_number')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="col-6 col-md-3 col-lg-2 bank_group" style="display: none">
                                                        <label class="fbox mb-1">Bank</label>
                                                        <select class="js-tomselect form-select form-select-sm"
                                                            placeholder=" " name="bank_id" id="bank_id">
                                                            <option value="">Bank</option>
                                                            @foreach (getPakistanBanks() as $v)
                                                                <option value="{{ $v['id'] }}">{{ $v['name'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-md-3 col-lg-2 bank_group" style="display: none">
                                                        <label class="fbox mb-1">Passing Date</label>
                                                        <input type="text" name="passing_date"
                                                            class="passing_date date form-control form-control-sm" data-input>
                                                        @error('passing_date')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                    @if ($voucherType === 'CP')
                                                        <div class="col-12 mt-3" id="pendingPaymentsSection"
                                                            style="display: none;">
                                                            <div class="card border">
                                                                <div class="card-header py-2">
                                                                    <strong>Pending Online/Check Payments</strong>
                                                                </div>
                                                                <div class="card-body p-2">
                                                                    <div class="table-responsive mb-2">
                                                                        <table class="table table-sm table-bordered mb-0"
                                                                            id="pendingPaymentsTable">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>Select</th>
                                                                                    <th>Date</th>
                                                                                    <th>Ref</th>
                                                                                    <th>Customer</th>
                                                                                    <th>Plot</th>
                                                                                    <th>Bank</th>
                                                                                    <th>Number</th>
                                                                                    <th>Amount</th>
                                                                                    <th>Passing Date</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody></tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div class="row g-2 align-items-end">
                                                                        <div class="col-md-3">
                                                                            <label class="fbox mb-1">Status</label>
                                                                            <select class="form-control form-control-sm"
                                                                                id="pending_status">
                                                                                <option value="1">Pass</option>
                                                                                <option value="2">Return</option>
                                                                                <option value="3">Cheque Bounce</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-3 bank-ledger-fields">
                                                                            <label class="fbox mb-1">Head Account</label>
                                                                            <select class="form-control form-control-sm"
                                                                                id="pending_accounts_id">
                                                                                <option value="">Select head</option>
                                                                                @foreach ($headaccounts as $v)
                                                                                    <option
                                                                                        value="{{ $v->head_accounting_id }}">
                                                                                        {{ $v->headAccounting->name ?? '' }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-3 bank-ledger-fields">
                                                                            <label class="fbox mb-1">Bank Account</label>
                                                                            <select class="form-control form-control-sm"
                                                                                id="pending_subaccounts_id">
                                                                                <option value="">Select account</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <label class="fbox mb-1">Bank Post Date</label>
                                                                            <input type="date"
                                                                                class="form-control form-control-sm"
                                                                                id="pending_bank_post_at">
                                                                        </div>
                                                                        <div class="col-md-12">
                                                                            <label class="fbox mb-1">Note</label>
                                                                            <textarea class="form-control form-control-sm" id="pending_note" rows="2"
                                                                                maxlength="1000" placeholder="Reason/note"></textarea>
                                                                        </div>
                                                                        <div class="col-md-12">
                                                                            <button type="button"
                                                                                class="btn btn-sm btn-primary"
                                                                                id="submitPendingStatus">Update
                                                                                Pending Payment</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                    <div class="col-6 col-md-3 col-lg-10" style="margin-top: 10px">
                                                        <label for="detail" class="fbox mb-1">Details</label>
                                                        <textarea id="detail" class="form-control @error('detail') input-error @enderror"
                                                            placeholder="Enter your details here..." name="detail" maxlength="255" required>{{ old('detail') }}</textarea>
                                                        @error('detail')
                                                            <div class="error-message">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="col-md-12">
                                                    <div class="info-card">
                                                        <h3 class="info-card-title">Party Information</h3>
                                                        <div class="info-grid">
                                                            <div class="info-item">
                                                                <span class="info-label">Phone:</span>
                                                                <span id="phone" class="info-value">Not provided</span>
                                                            </div>
                                                            <div class="info-item">
                                                                <span class="info-label">Address:</span>
                                                                <span id="address" class="info-value">Not provided</span>
                                                            </div>
                                                            <div class="info-item">
                                                                <span class="info-label">CNIC:</span>
                                                                <span id="cnic" class="info-value">Not provided</span>
                                                            </div>
                                                            <div class="info-item">
                                                                <span class="info-label">Balance:</span>
                                                                <span id="balance" class="info-value">$0.00</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                @endcan
                                <!-- Cash Voucher List -->
                                <button type="button" class="btn {{ $theme['primary'] }}"
                                    id="toggleFilters">Filters</button>

                                <div id="filtersSection" class="col-12 mb-4" style="display:none;">
                                    <div class="custom_card h-100">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <h5 class="text-lg font-semibold mb-3">Cash Voucher List</h5>
                                                <div class="row g-3 align-items-end">
                                                    <!-- Head Account -->
                                                    <div class="col-md-3">
                                                        <label for="filter_head_account">Head Account</label>
                                                        <select id="filter_head_account" name="filter_head_account[]"
                                                            class="form-control select2" multiple>
                                                            @foreach ($headaccounts as $account)
                                                                <option value="{{ $account->head_accounting_id }}">
                                                                    {{ $account->headAccounting->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>


                                                    </div>

                                                    <!-- Subhead Account -->
                                                    <div class="col-md-3">
                                                        <label for="filter_subhead_account">Subhead Account</label>
                                                        <select id="filter_subhead_account"
                                                            name="filter_subhead_account[]" class="form-control select2"
                                                            multiple>
                                                            @foreach ($partyaccounts as $account)
                                                                <option value="{{ $account->subhead_accounting_id }}">
                                                                    {{ $account->subheadAccounting->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>


                                                    </div>


                                                    <!-- Voucher Number -->
                                                    <div class="col-md-3">
                                                        <label for="filter_voucher_number">Voucher Number</label>
                                                        <input type="text" id="filter_voucher_number"
                                                            class="form-control" placeholder="Voucher Number">
                                                    </div>

                                                    <!-- Amount -->
                                                    <!-- Amount Min/Max -->
                                                    <div class="col-md-3">
                                                        <label for="filter_amount_min">Amount (Min)</label>
                                                        <input type="number" step="0.01" min="0"
                                                            id="filter_amount_min" class="form-control"
                                                            placeholder="Min amount">
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label for="filter_amount_max">Amount (Max)</label>
                                                        <input type="number" step="0.01" min="0"
                                                            id="filter_amount_max" class="form-control"
                                                            placeholder="Max amount">
                                                    </div>

                                                    <!-- legacy single amount (hidden) - keeps old code/back-end safe if anything still reads it -->
                                                    <input type="hidden" id="filter_amount" value="">

                                                    <!-- Date -->
                                                    <!-- Date -->
                                                    {{-- <div class="col-md-3">
                                            <label for="filter_date">Date</label>
                                            <input type="text" id="filter_date" class="form-control date"
                                                placeholder="YYYY-MM-DD">
                                        </div> --}}
                                                    <!-- Date From -->
                                                    <div class="col-md-3">
                                                        <label for="filter_date_from">Date From</label>
                                                        <input type="text" id="filter_date_from"
                                                            class="form-control date" placeholder="YYYY-MM-DD">
                                                    </div>

                                                    <!-- Date To -->
                                                    <div class="col-md-3">
                                                        <label for="filter_date_to">Date To</label>
                                                        <input type="text" id="filter_date_to"
                                                            class="form-control date" placeholder="YYYY-MM-DD">
                                                    </div>


                                                    <div class="col-md-12 mt-3">
                                                        <button id="applyFilters"
                                                            class="btn {{ $theme['primary'] }} btn-sm">Apply
                                                            Filters</button>
                                                        <button id="resetFilters"
                                                            class="btn {{ $theme['secondary'] }} btn-sm">Reset</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Table -->
                    @can('read voucher')
                        <div class="table-responsive">
                            <table id="vouchersTable" class="table table-bordered table-striped"
                                data-source="{{ $table_data_route }}">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Voucher Number</th>
                                        <th>Head Account</th>
                                        <th>Sub Head Account</th>
                                        <th>Detail</th>
                                        <th>Amount</th>
                                        @canany(['update voucher', 'delete voucher', 'read voucher', 'print voucher'])
                                            <th>Action</th>
                                        @endcanany
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    @endcan



                </div>
            </div>
        </section>
    </div>
    <!-- View Changes Modal -->
    <div class="modal fade" id="viewChangesModal" tabindex="-1" aria-labelledby="viewChangesModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewChangesModalLabel">
                        <i class="fas fa-exchange-alt me-2"></i> Pending Changes
                    </h5>
                    <!-- Header close button removed -->
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Old Value</th>
                                <th>New Value</th>
                            </tr>
                        </thead>
                        <tbody id="changesTableBody">
                            <!-- Dynamically filled by JS -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <!-- Footer close button remains -->
                </div>
            </div>
        </div>
    </div>



@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let isDateChanged = false;
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.js-tomselect').forEach((el) => {
                if (el.tomselect) return; // prevent double init

                new TomSelect(el, {
                    allowEmptyOption: false, // keep empty option
                    create: false, // no free typing unless you want it
                    maxItems: el.multiple ? null : 1,
                    closeAfterSelect: !el.multiple,
                    placeholder: el.getAttribute('placeholder') || '',
                    render: {
                        option_create: null // disable "Create" in dropdown
                    }
                });

                // Ensure no option is selected by default
                el.tomselect.clear(true);

                // Focus input on click so user can type immediately
                el.tomselect.on('dropdown_open', () => {
                    el.tomselect.focus();
                });
            });
        });
        $(document).ready(function() {
            $(document).on('click', '.btn-view-changes', function() {
                const oldValues = $(this).data('old') || {};
                const newValues = $(this).data('new') || {};
                const submittedBy = $(this).data('submitted_by') || 'Unknown User';
                const record_id = $(this).data('record_id') || $(this).data('id'); // fallback to id
                const $tbody = $('#changesTableBody');
                const $modalFooter = $('#viewChangesModal .modal-footer');

                $tbody.empty();
                $modalFooter.find('.btn-approve, .btn-reject').remove(); // Remove old buttons if any

                // 🛑 Check if this is a delete request (only is_active changed to 0)
                if (Object.keys(newValues).length === 2 && newValues.is_active == 0) {
                    $tbody.html(`
                                        <tr>
                                            <td colspan="3" class="text-center text-danger fw-bold">
                                                <i class="fas fa-trash-alt me-2"></i>
                                                User <span class="text-primary">${submittedBy}</span> has requested to <strong>delete</strong> the ledger.
                                                ${newValues.delete_reason ? `<br><strong>Reason:</strong> ${newValues.delete_reason}` : ''}
                                            </td>
                                        </tr>
                                    `);
                } else {
                    // 📝 Show normal field changes
                    $.each(newValues, function(key, newVal) {
                        const oldVal = oldValues[key] ?? '<em class="text-muted">N/A</em>';
                        const safeNewVal = newVal ?? '<em class="text-muted">N/A</em>';
                        $tbody.append(`
                                            <tr>
                                                <td><strong>${key}</strong></td>
                                                <td>${oldVal}</td>
                                                <td class="text-primary fw-semibold">${safeNewVal}</td>
                                            </tr>
                                        `);
                    });
                }

                // ✅ Add Approve and Reject buttons dynamically
                const approveBtn = $(`
                                    <button class="btn btn-outline-success btn-approve" data-id="${record_id}" data-table="ledgers">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    `);
                const rejectBtn = $(`
                                        <button class="btn btn-outline-danger btn-reject" data-id="${record_id}" data-table="ledgers">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    `);

                // Append buttons before the Close button
                $modalFooter.prepend(approveBtn, rejectBtn);

                // Show Bootstrap modal
                const modal = new bootstrap.Modal($('#viewChangesModal')[0]);
                modal.show();
            });

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.text-area');
            const charCount = document.getElementById('char-count');

            if (textarea && charCount) {
                textarea.addEventListener('input', function() {
                    charCount.textContent = this.value.length;
                });
                charCount.textContent = textarea.value.length;
            }
        });
    </script>

    <script>
        function saveAsDraft() {
            const form = document.getElementById('voucherForm');
            form.action = "{{ route('ledger.save_as_draft') }}";
        }


        $(document).ready(function() {

            // Approve voucher
            $(document).on('click', '.btn-approve', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const table = $(this).data('table');
                const container = $(this).closest('.admin_approval'); // full container to remove

                Swal.fire({
                    title: 'Approve Voucher?',
                    text: 'Are you sure you want to approve this voucher?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Approve',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-success me-2',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('finance.voucher.approve', ['id' => 'ID_PLACEHOLDER']) }}"
                                .replace('ID_PLACEHOLDER', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                table: table
                            },
                            success: function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Approved!',
                                    text: 'Voucher approved successfully.',
                                    timer: 100,
                                    showConfirmButton: false
                                });
                                // 🗑 Remove container completely
                                window.location.reload();

                            },
                            error: function(xhr) {
                                const error = xhr.responseJSON?.message ||
                                    'Something went wrong.';
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error
                                });
                            }
                        });
                    }
                });
            });

            // Reject voucher
            $(document).on('click', '.btn-reject', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const table = $(this).data('table');
                const container = $(this).closest('.admin_approval');

                Swal.fire({
                    title: 'Reject Voucher?',
                    text: 'Are you sure you want to reject this voucher?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Reject',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-danger me-2',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('finance.voucher.reject', ['id' => 'ID_PLACEHOLDER']) }}"
                                .replace('ID_PLACEHOLDER', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                table: table
                            },
                            success: function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Rejected!',
                                    text: 'Voucher rejected successfully.',
                                    timer: 100,
                                    showConfirmButton: false
                                });
                                // 🗑 Remove container completely
                                window.location.reload();
                            },
                            error: function(xhr) {
                                const error = xhr.responseJSON?.message ||
                                    'Something went wrong.';
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error
                                });
                            }
                        });
                    }
                });
            });





            function clearPartyInfo() {
                $('#cnic').text('Not provided');
                $('#phone').text('Not provided');
                $('#address').text('Not provided');
                $('#balance').text('$0.00');
            }

            $('#acct_type').change(function() {
                var acctType = $(this).val();
                clearPartyInfo();
                if (acctType) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            acct_type: acctType,
                            action: 'get_head',
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            const $accounts = $('#accounts_id');
                            const $subAccounts = $('#subaccounts_id');

                            const accountsEl = $accounts[0];
                            const tsAccounts = accountsEl?.tomselect;

                            const subAccountsEl = $subAccounts[0];
                            const tsSubAccounts = subAccountsEl?.tomselect;

                            if (tsAccounts) {
                                // Clear TomSelect options and selection
                                tsAccounts.clear(true);
                                tsAccounts.clearOptions();

                                // Also clear underlying <select>
                                $accounts.empty();

                                // Add placeholder
                                $accounts.append('<option value=""></option>');

                                // Loop and append to both <select> and TomSelect
                                $.each(data, function(_, value) {
                                    const opt = new Option(value.head_accounting.name,
                                        value.head_accounting_id);
                                    $accounts.append(opt);
                                    tsAccounts.addOption({
                                        value: value.head_accounting_id,
                                        text: value.head_accounting.name
                                    });
                                });

                                // Refresh TomSelect
                                tsAccounts.refreshOptions(false);
                            }

                            if (tsSubAccounts) {
                                tsSubAccounts.clear(true);
                                tsSubAccounts.clearOptions();
                                $subAccounts.empty();
                                $subAccounts.append('<option value=""></option>');

                                // If you have subaccount data in response (optional)
                                if (data.subaccounts) {
                                    $.each(data.subaccounts, function(_, value) {
                                        const opt = new Option(value.name, value.id);
                                        $subAccounts.append(opt);
                                        tsSubAccounts.addOption({
                                            value: value.id,
                                            text: value.name
                                        });
                                    });
                                }

                                tsSubAccounts.refreshOptions(false);
                            }
                        },
                        error: function() {
                            console.log('Error fetching accounts');
                        }
                    });
                } else {
                    let tsAccounts = $('#accounts_id')[0].tomselect;
                    let tsSubAccounts = $('#subaccounts_id')[0].tomselect;
                    if (tsAccounts) tsAccounts.clearOptions();
                    if (tsSubAccounts) tsSubAccounts.clearOptions();
                }
            });


            // Similar change event for 'accounts_id' dropdown to fetch subaccounts based on account selection
            $('#accounts_id').change(function() {
                var accountID = $(this).val();
                clearPartyInfo();

                if (accountID) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            accountID: accountID,
                            action: 'get_child',
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            const $subAccounts = $('#subaccounts_id');
                            const subAccountsEl = $subAccounts[0];
                            const tsSubAccounts = subAccountsEl?.tomselect;

                            // Filter only matching items
                            const filteredData = $.grep(data, function(item) {
                                return item.head_accounting_id == accountID;
                            });

                            if (tsSubAccounts) {
                                // Clear TomSelect and <select>
                                tsSubAccounts.clear(true);
                                tsSubAccounts.clearOptions();
                                $subAccounts.empty();

                                // Add placeholder
                                $subAccounts.append(
                                    '<option value="">Select an option</option>');

                                // Append new options to both <select> and TomSelect
                                $.each(filteredData, function(_, value) {
                                    const text =
                                        `${value.subhead_accounting.name} — Balance: ${value.balance}`;
                                    const opt = new Option(text, value
                                        .subhead_accounting_id);
                                    $subAccounts.append(opt);

                                    tsSubAccounts.addOption({
                                        value: value.subhead_accounting_id,
                                        text: text
                                    });
                                });

                                // Refresh dropdown
                                tsSubAccounts.refreshOptions(false);
                            }

                        }

                    });
                } else {
                    $('#subaccounts_id')[0].tomselect.clearOptions();
                }
            });

            $('#subaccounts_id').change(function() {
                var subaccountId = $(this).val();

                if (subaccountId !== '') {
                    $.ajax({
                        url: '{{ route('get-subaccount-details') }}',
                        type: 'POST',
                        data: {
                            subaccounts_id: subaccountId,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            let headId = response.headId;
                            let acctSelect = $('#accounts_id')[0].tomselect;
                            if (!acctSelect.getValue()) {
                                acctSelect.setValue(headId, true);
                            }

                            // $('#accounts_id').val(response.headId).trigger('change');
                            $('#cnic').text(response['cnic']); // Access data using the keys
                            $('#phone').text(response['phone']);
                            $('#balance').text(response['balance']);
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                }
            });

            $('#customer_id').change(function(e) {
                e.preventDefault();
                var customerId = $(this).val();

                if (customerId) {
                    fetchPlots(customerId);
                } else {
                    $('#plot_id').empty();
                    $('#plot_id').append('<option value="">Select an options</option>');
                }

            });
            $('#plot_id').change(function(e) {
                e.preventDefault();
                var plotId = $(this).val();
                if (plotId) {
                    $.ajax({
                        url: '/admin/get-plot-customer', // URL to your route
                        type: 'POST', // Use POST method for sending data
                        data: {
                            _token: '{{ csrf_token() }}', // Add CSRF token
                            plot_id: plotId // Pass project ID to server
                        },
                        success: function(data) {
                            let customerId = data.id;
                            // Update the UI based on the response
                            // let headId = response.headId;
                            let customerSelect = $('#customer_id')[0].tomselect;
                            customerSelect.setValue(customerId, true);
                            // let $select = $('#customer_id');
                            // let $options = $select.find('option');
                            // let $matchingOption = $options.filter(function() {
                            //     return $(this).val() == customerId;
                            // });
                            // if ($matchingOption.length > 0) {
                            //     $options.prop('selected', false); // clear previous selections
                            //     $matchingOption.prop('selected', true); // select matching one
                            //     $matchingOption.detach().appendTo($select);
                            // }
                        }
                    });
                }
            });
            $('#e_customer_id').change(function(e) {
                e.preventDefault();
                var customerId = $(this).val();

                if (customerId) {
                    eFetchPlots(customerId);
                } else {
                    $('#plot_id').empty();
                    $('#plot_id').append('<option value="">Select an options</option>');
                }

            });
            $('#e_plot_id').change(function(e) {
                e.preventDefault();
                var plotId = $(this).val();
                if (plotId) {
                    $.ajax({
                        url: '/admin/get-plot-customer', // URL to your route
                        type: 'POST', // Use POST method for sending data
                        data: {
                            _token: '{{ csrf_token() }}', // Add CSRF token
                            plot_id: plotId // Pass project ID to server
                        },
                        success: function(data) {
                            let customerId = data.id;
                            // Update the UI based on the response
                            // let headId = response.headId;
                            let customerSelect = $('#e_customer_id')[0].tomselect;
                            customerSelect.setValue(customerId, true);
                            // let $select = $('#customer_id');
                            // let $options = $select.find('option');
                            // let $matchingOption = $options.filter(function() {
                            //     return $(this).val() == customerId;
                            // });
                            // if ($matchingOption.length > 0) {
                            //     $options.prop('selected', false); // clear previous selections
                            //     $matchingOption.prop('selected', true); // select matching one
                            //     $matchingOption.detach().appendTo($select);
                            // }
                        }
                    });
                }
            });


            function fetchPlots(customerId) {
                $.ajax({
                    url: '/admin/get-plots-list',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        project_id: '{{ getSelectedTown() }}',
                        customer_id: customerId
                    },
                    success: function(data) {
                        let plotEl = $('#plot_id')[0]; // DOM element
                        let tsPlot = plotEl.tomselect; // TomSelect instance
                        if (tsPlot) {
                            // tsPlot.addOption({
                            //     value: '',
                            //     text: 'Select an option'
                            // });
                            tsPlot.setValue('', true);
                            tsPlot.clearOptions(); // clear old options

                            $.each(data, function(key, plot) {
                                var plotType = (plot.type == 1) ? 'R- ' : 'C- ';
                                tsPlot.addOption({
                                    value: plot.plot_id,
                                    text: plotType + ' ' + plot.name
                                });
                            });

                            tsPlot.refreshOptions(false);
                        }
                        // $('#plot_id').empty();
                        // $('#plot_id').append('<option value="">Select an options</option>');
                        // $.each(data, function(key, plot) {
                        //     var plotType = (plot.type == 1) ? 'R- ' : 'C- ';
                        //     $('#plot_id').append('<option value="' + plot.plot_id + '">' +
                        //         plotType + ' ' + plot.name + '  </option>');
                        // });
                    }
                });
            }

            function eFetchPlots(customerId) {
                $.ajax({
                    url: '/admin/get-plots-list',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        project_id: '{{ getSelectedTown() }}',
                        customer_id: customerId
                    },
                    success: function(data) {
                        let plotEl = $('#e_plot_id')[0]; // DOM element
                        let tsPlot = plotEl.tomselect; // TomSelect instance
                        if (tsPlot) {
                            // tsPlot.addOption({
                            //     value: '',
                            //     text: 'Select an option'
                            // });
                            tsPlot.setValue('', true);
                            tsPlot.clearOptions(); // clear old options

                            $.each(data, function(key, plot) {
                                var plotType = (plot.type == 1) ? 'R- ' : 'C- ';
                                tsPlot.addOption({
                                    value: plot.plot_id,
                                    text: plotType + ' ' + plot.name
                                });
                            });

                            tsPlot.refreshOptions(false);
                        }
                        // $('#plot_id').empty();
                        // $('#plot_id').append('<option value="">Select an options</option>');
                        // $.each(data, function(key, plot) {
                        //     var plotType = (plot.type == 1) ? 'R- ' : 'C- ';
                        //     $('#plot_id').append('<option value="' + plot.plot_id + '">' +
                        //         plotType + ' ' + plot.name + '  </option>');
                        // });
                    }
                });
            }

            @if ($voucherType === 'CP')
                const pendingFetchUrl = "{{ route('finance.voucher.pending_bank_payments') }}";
                const pendingStatusUpdateUrlTemplate =
                    "{{ route('finance.voucher.pending_bank_payments.status', ['customerLedger' => 'LEDGER_ID']) }}";

                function populatePendingPaymentsTable(rows) {
                    const $tbody = $('#pendingPaymentsTable tbody');
                    $tbody.empty();

                    if (!rows || !rows.length) {
                        $tbody.append(
                            '<tr><td colspan="9" class="text-center text-muted">No pending online/check payments found for this project.</td></tr>'
                        );
                        return;
                    }

                    rows.forEach(function(item) {
                        const row = `<tr>
                            <td><input type="radio" name="selected_pending_payment" value="${item.id}"></td>
                            <td>${item.date || ''}</td>
                            <td>${(item.transaction_type || '')}-${(item.reference || '')}</td>
                            <td>${item.customer || ''}</td>
                            <td>${item.plot || ''}</td>
                            <td>${item.bank || ''}</td>
                            <td>${item.t_number || ''}</td>
                            <td>${item.amount || 0}</td>
                            <td>${item.passing_date || ''}</td>
                        </tr>`;
                        $tbody.append(row);
                    });
                }

                function loadPendingPayments() {
                    $.ajax({
                        url: pendingFetchUrl,
                        type: 'GET',
                        dataType: 'json',
                        success: function(res) {
                            if (res?.status === 'success') {
                                populatePendingPaymentsTable(res.data || []);
                            } else {
                                populatePendingPaymentsTable([]);
                            }
                        },
                        error: function() {
                            populatePendingPaymentsTable([]);
                        }
                    });
                }

                function togglePendingBankAccountFields() {
                    const status = $('#pending_status').val();
                    if (status === '1') {
                        $('.bank-ledger-fields').show();
                    } else {
                        $('.bank-ledger-fields').hide();
                        $('#pending_accounts_id').val('');
                        $('#pending_subaccounts_id').empty().append(
                            '<option value="">Select account</option>');
                    }
                }

                $('#pending_status').on('change', togglePendingBankAccountFields);
                togglePendingBankAccountFields();

                $('#pending_accounts_id').on('change', function() {
                    const accountID = $(this).val();
                    const $subaccounts = $('#pending_subaccounts_id');
                    $subaccounts.empty().append('<option value="">Select account</option>');
                    if (!accountID) {
                        return;
                    }
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            accountID: accountID,
                            action: 'get_child',
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            const filteredData = $.grep(data, function(item) {
                                return item.head_accounting_id == accountID;
                            });
                            $.each(filteredData, function(_, value) {
                                $subaccounts.append('<option value="' + value.subhead_accounting_id +
                                    '">' + value.subhead_accounting.name + '</option>');
                            });
                        }
                    });
                });

                $('#submitPendingStatus').on('click', function() {
                    const selectedId = $('input[name="selected_pending_payment"]:checked').val();
                    const passingStatus = $('#pending_status').val();
                    const note = $('#pending_note').val().trim();
                    const bankPostAt = $('#pending_bank_post_at').val();
                    const accountsId = $('#pending_accounts_id').val();
                    const subaccountsId = $('#pending_subaccounts_id').val();

                    if (!selectedId) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Select payment',
                            text: 'Please select a pending payment first.'
                        });
                        return;
                    }
                    if (!note) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Note required',
                            text: 'Please enter a note.'
                        });
                        return;
                    }
                    if (passingStatus === '1' && (!accountsId || !subaccountsId)) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Account required',
                            text: 'Please select head and bank account for pass.'
                        });
                        return;
                    }

                    const endpoint = pendingStatusUpdateUrlTemplate.replace('LEDGER_ID', selectedId);
                    $.ajax({
                        url: endpoint,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            _token: '{{ csrf_token() }}',
                            passing_status: passingStatus,
                            note: note,
                            bank_post_at: bankPostAt,
                            accounts_id: accountsId,
                            subaccounts_id: subaccountsId
                        },
                        success: function(res) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated',
                                text: res?.message || 'Status updated successfully.'
                            }).then(function() {
                                window.location.reload();
                            });
                        },
                        error: function(xhr) {
                            const msg = xhr.responseJSON?.message || 'Unable to update status.';
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: msg
                            });
                        }
                    });
                });
            @endif

            $('#payment_type').change(function(e) {

                e.preventDefault();

                if ($(this).val() != '1') {

                    $('.bank_group').css('display', 'block');
                    @if ($voucherType === 'CP')
                        $('#pendingPaymentsSection').show();
                        loadPendingPayments();
                    @endif
                } else {

                    $('.bank_group').css('display', 'none');
                    @if ($voucherType === 'CP')
                        $('#pendingPaymentsSection').hide();
                    @endif
                }
            });
            $('#e_payment_type').change(function(e) {

                e.preventDefault();

                if ($(this).val() != '1') {

                    $('.e_bank_group').css('display', 'block');
                } else {

                    $('.e_bank_group').css('display', 'none');
                }
            });

            $('#e_acct_type').change(function() {
                var acctType = $(this).val();
                if (acctType) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            acct_type: acctType,
                            action: 'get_head',
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            let accountsEl = $('#e_accounts_id')[0]; // DOM element
                            let tsAccounts = accountsEl.tomselect; // TomSelect instance

                            let subAccountsEl = $('#e_subaccounts_id')[0];
                            let tsSubAccounts = subAccountsEl.tomselect;

                            if (tsAccounts) {
                                // tsAccounts.addOption({
                                //     value: '',
                                //     text: 'Select an option'
                                // });
                                tsAccounts.setValue('', true);
                                tsAccounts.clearOptions(); // clear old options

                                $.each(data, function(key, value) {
                                    tsAccounts.addOption({
                                        value: value.head_accounting_id,
                                        text: value.head_accounting.name
                                    });
                                });

                                tsAccounts.refreshOptions(false);
                            }

                            if (tsSubAccounts) {
                                tsSubAccounts.setValue('', true);
                                tsSubAccounts.clearOptions(); // also clear subaccounts
                            }
                        },
                        error: function() {
                            console.log('Error fetching accounts');
                        }
                    });
                } else {
                    let tsAccounts = $('#e_accounts_id')[0].tomselect;
                    let tsSubAccounts = $('#e_subaccounts_id')[0].tomselect;
                    if (tsAccounts) tsAccounts.clearOptions();
                    if (tsSubAccounts) tsSubAccounts.clearOptions();
                }
            });

            $('#e_accounts_id').change(function() {
                var accountID = $(this).val();

                if (accountID) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            accountID: accountID,
                            action: 'get_child',
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            let subAccountsSelect = $('#e_subaccounts_id')[0].tomselect;

                            // Filter only matching items
                            let filteredData = $.grep(data, function(item) {
                                return item.head_accounting_id == accountID;
                            });

                            // Clear and add placeholder
                            // subAccountsSelect.addOption({
                            //     value: '',
                            //     text: 'Select an option'
                            // });
                            subAccountsSelect.setValue('', true);
                            subAccountsSelect.clearOptions();

                            // Append new options
                            $.each(filteredData, function(key, value) {
                                subAccountsSelect.addOption({
                                    value: value.subhead_accounting_id,
                                    text: value.subhead_accounting.name +
                                        ' — Balance: ' + value.balance
                                });
                            });

                            // Refresh TomSelect dropdown
                            subAccountsSelect.refreshOptions(false);

                        }
                    });
                } else {
                    $('#e_subaccounts_id')[0].tomselect.clearOptions();
                }
            });

            $('#e_subaccounts_id').change(function() {
                var subaccountId = $(this).val();

                if (subaccountId !== '') {
                    $.ajax({
                        url: '{{ route('get-subaccount-details') }}',
                        type: 'POST',
                        data: {
                            subaccounts_id: subaccountId,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            let headId = response.headId;
                            let acctSelect = $('#e_accounts_id')[0].tomselect;
                            if (!acctSelect.getValue()) {
                                acctSelect.setValue(headId, true);
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                }
            });


            $(document).on("click", '.btn-edit', function() {
                let id = $(this).attr("data-id");
                $('#id').val(id);
                $('#modal-loading').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
                $.ajax({
                    url: "{{ route('ledger.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        var data = data.data;
                        $("#e_reference").val(data.reference);

                        flatpickr('#e_date', {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                            defaultDate: data
                                .date
                        });

                        $("#e_voucher").val(data.type + '-0000' + data.type_id);
                        let amount = 0;
	                        if (data.type == 'CR') {
	                            amount = data.amount_in;
	                        } else if (data.type == 'CP') {
	                            amount = data.amount_out;
	                        }
	                        $("#e_amount").val(amount);
	                        $("#e_detail").val(data.detail ?? '');
	                        $('#eWordingAmount').text(numberToWords.toWords(amount));
	                        $('#modal-loading').modal('hide');
	                        $('#modal-edit').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                        let acct_type = data.project_head_subhead.head_accounting.acct_type;
                        let eAccTypeSelect = $('#e_acct_type')[0].tomselect;
                        eAccTypeSelect.setValue(acct_type, true);

                        let headId = data.project_head_subhead.head_accounting.id;
                        let eAccSelect = $('#e_accounts_id')[0].tomselect;
                        eAccSelect.setValue(headId, true);

                        let subheadId = data.project_head_subhead.subhead_accounting.id;
                        let eSubAccSelect = $('#e_subaccounts_id')[0].tomselect;
                        eSubAccSelect.setValue(subheadId, true);

                        // if (data.customer_ledger != null) {
                        // $('.customer-detail').removeClass('d-none');
                        let customerId = data.customer_ledger?.customer_id;
                        let eCustomerSelect = $('#e_customer_id')[0].tomselect;
                        eCustomerSelect.setValue(customerId, true);

                        let plotId = data.customer_ledger?.plot_id;
                        let ePlotSelect = $('#e_plot_id')[0].tomselect;
                        ePlotSelect.setValue(plotId, true);

                        let typeId = data.customer_ledger?.payment_type;
                        let eTypeSelect = $('#e_payment_type')[0].tomselect;
                        eTypeSelect.setValue(typeId, true);
                        if (typeId == 1 || typeId == null || typeId == '') {
                            $('.e_bank_group').css('display', 'none');
                        } else {
                            $('.e_bank_group').css('display', 'block');
                            let bankId = data.customer_ledger?.bank_id;
                            let eBankSelect = $('#e_bank_id')[0].tomselect;
                            eBankSelect.setValue(bankId, true);
                            let tNumber = data.customer_ledger?.t_number;
                            $('#e_t_number').val(tNumber);
                            let passingDate = data.customer_ledger?.passing_date;
                            $('#e_passing_date').val(passingDate);
                        }
                        // }else{
                        //     $('.customer-detail').addClass('d-none');
                        // }
                    },
                });
            });

            $(document).on("click", '.btn-delete', function() {
                let id = $(this).attr("data-id");
                let name = $(this).attr("data-name");
                $("#did").val(id);
                $('#modal-delete').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });

            $('#numberInput').on('input', function() {
                convertToWords();
            });
            $('#e_amount').on('input', function() {
                eConvertToWords();
            });
            $(document).on("click", "#submit-button", function(e) {
                e.preventDefault();
                let isValid = true;
                let messages = [];

                // Helper function
                function checkField(selector, message, extraCheck = null) {
                    let $field = $(selector);
                    let val = $field.val().trim();

                    if (!val || (extraCheck && !extraCheck(val))) {
                        isValid = false;
                        messages.push(message);
                        $field.addClass("is-invalid");
                    } else {
                        $field.removeClass("is-invalid");
                    }
                }

                // Validate fields
                // checkField("[name='reference']", "Reference No is required.");
                checkField("[name='date']", "Date is required.");
                checkField("[name='acct_type']", "Account Type is required.");
                checkField("[name='accounts_id']", "Accounts is required.");
                checkField("[name='subaccounts_id']", "Child Account is required.");
                checkField("[name='detail']", "Details are required.");

                // Amount special check
                checkField("[name='amount']", "Valid amount is required.", function(val) {
                    val = val.replace(/,/g, ""); // remove commas
                    return !isNaN(val) && parseFloat(val) > 0;
                });

                if (!isValid) {
                    e.preventDefault();
                    alert(messages.join("\n"));
                    return;
                } else {
                    $('#confirmedModal').modal('show');
                }
            });


        });


        function convertToWords() {
            var numberInput = document.getElementById('numberInput').value;

            var numericValue = numberInput.replace(/,/g, '');

            var wordingAmount = numberToWords.toWords(numericValue);

            document.getElementById('wordingAmount').innerText = wordingAmount;
        }

        function eConvertToWords() {
            var numberInput = document.getElementById('e_amount').value;

            var numericValue = numberInput.replace(/,/g, '');

            var wordingAmount = numberToWords.toWords(numericValue);

            document.getElementById('eWordingAmount').innerText = wordingAmount;
        }

        function formatAmount(input) {
            let value = input.value.replace(/[^0-9]/g, '').replace(/^0+/, '');

            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            input.value = value;
        }

        // Voucher Tab Management with LocalStorage + full Tom Select option persistence
        // Voucher Tabs with reliable saving on tab switch + new tab, including Tom Select options
        (async function($) {
            const FORM_ID = '#voucherForm';
            const TS_SEL = '.js-tomselect';
            const VOUCHER_TYPE = @json($voucherType);
            const INITIAL_VOUCHER_NUMBER = @json((int) ($latest_voucher_number ?? 1));
            const VOUCHER_STORAGE_KEY = `bluemarketing_voucher_tabs_${VOUCHER_TYPE}_${window.location.pathname}_v2`;

            function buildVoucherDisplay(number) {
                return `${VOUCHER_TYPE}-${number}`;
            }

            function setVoucherNumber(number) {
                const safeNumber = Math.max(1, parseInt(number, 10) || 1);
                const display = buildVoucherDisplay(safeNumber);
                $('#voucher_number').val(display);
                $('input[name="voucher"]').val(display);
            }

            function parseVoucherNumber(value) {
                if (typeof value !== 'string') return null;
                if (value.includes('{') || value.includes('}') || value.includes('&quot;')) return null;
                const match = value.match(/^(CR|CP)-(\d+)$/);
                if (!match || match[1] !== VOUCHER_TYPE) return null;
                return parseInt(match[2], 10);
            }

            // ---------- TomSelect init ----------
            function initTomSelects() {
                if (typeof window.TomSelect === 'undefined') {
                    console.warn('TomSelect not found. Skipping initTomSelects().');
                    return;
                }
                $(TS_SEL).each(function() {
                    if (this.tomselect) {
                        try {
                            this.tomselect.destroy();
                        } catch (e) {}
                    }
                    const ts = new TomSelect(this, {
                        persist: false,
                        create: false,
                        maxItems: 1,
                        allowEmptyOption: true,
                        onChange: function () {
                            $(this.input).trigger('change'); // TomSelect input
                            // or: $(this.$input).trigger('change'); depending on TS version
                        }
                    });
                    const current = $(this).val();
                    if (current != null && current !== '') {
                        try {
                            ts.setValue(String(current), true);
                        } catch (e) {}
                    }
                });
            }

            // ---------- store & pointers ----------
            let store = {
                tabs: {
                    1: {
                        formData: {},
                        selects: {}
                    }
                },
                currentTab: 1,
                nextTabNumber: 2
            };
            let currentTab = 1;
            let nextTabNumber = 2;

            // ---------- utils ----------
            const persist = () => localStorage.setItem(VOUCHER_STORAGE_KEY, JSON.stringify(store));

            const hydrate = () => {
                try {
                    const raw = localStorage.getItem(VOUCHER_STORAGE_KEY);
                    if (!raw) return;
                    const parsed = JSON.parse(raw);
                    if (parsed && parsed.tabs) {
                        const tabKeys = Object.keys(parsed.tabs || {});
                        for (const tabKey of tabKeys) {
                            const storedVoucher = parsed.tabs?.[tabKey]?.formData?.voucher_number ?? '';
                            if (storedVoucher && parseVoucherNumber(storedVoucher) === null) {
                                localStorage.removeItem(VOUCHER_STORAGE_KEY);
                                return;
                            }
                        }
                        store = parsed;
                        currentTab = store.currentTab || 1;
                        nextTabNumber = store.nextTabNumber || 2;
                    }
                } catch (e) {
                    console.warn('hydrate failed', e);
                }
            };

            const ensureTab = (n) => {
                if (!store.tabs[n]) store.tabs[n] = {
                    formData: {},
                    selects: {}
                };
            };

            function throttle(fn, wait) {
                let t, last = 0,
                    pending = null;
                return function() {
                    const now = Date.now();
                    const args = arguments,
                        ctx = this;
                    const run = () => {
                        last = now;
                        t = null;
                        fn.apply(ctx, args);
                    };
                    if (now - last >= wait) {
                        if (t) {
                            clearTimeout(t);
                            t = null;
                        }
                        run();
                    } else {
                        pending = () => run();
                        if (!t) t = setTimeout(() => {
                            pending && pending();
                            pending = null;
                        }, wait - (now - last));
                    }
                };
            }

            // ---------- snapshot (TomSelect-aware) ----------
            function saveFormForTab(tabNo, $form) {
                ensureTab(tabNo);
                const formData = {};
                const selects = {};

                // inputs + textarea
                $form.find('input, textarea').each(function() {
                    const name = $(this).attr('name');
                    if (!name) return;
                    formData[name] = $(this).val();
                });

                // selects
                $form.find('select').each(function() {
                    const $el = $(this);
                    const name = $el.attr('name');
                    if (!name) return;

                    let options = [];
                    let selected = '';

                    if ($el[0] && $el[0].tomselect) {
                        const ts = $el[0].tomselect;
                        selected = (ts.getValue && ts.getValue()) || '';
                        // Pull options from TomSelect cache
                        options = Object.values(ts.options || {}).map(o => ({
                            value: String(o.value ?? ''),
                            text: String(o.text ?? ''),
                            disabled: !!o.disabled
                        }));
                        // If TomSelect has no cache (edge case), fallback to DOM
                        if (!options.length) {
                            $el.find('option').each(function() {
                                options.push({
                                    value: $(this).attr('value') ?? '',
                                    text: $(this).text(),
                                    disabled: !!$(this).prop('disabled')
                                });
                            });
                        }
                    } else {
                        selected = $el.val() ?? '';
                        $el.find('option').each(function() {
                            options.push({
                                value: $(this).attr('value') ?? '',
                                text: $(this).text(),
                                disabled: !!$(this).prop('disabled')
                            });
                        });
                    }

                    selects[name] = {
                        options,
                        selected: selected === null ? '' : String(selected)
                    };
                    formData[name] = selected;
                });

                store.tabs[tabNo].formData = formData;
                store.tabs[tabNo].selects = selects;
                store.currentTab = currentTab;
            }

            function withTomSelect($el, fn) {
                if ($el[0] && $el[0].tomselect) {
                    fn($el[0].tomselect);
                }
            }

            // ---------- restore ----------
            function restoreSelect($el, snap) {
                if (!$el.length || !snap) return;
                const selected = snap.selected ?? '';
                const snapOpts = snap.options || [];

                if ($el[0] && $el[0].tomselect) {
                    const ts = $el[0].tomselect;

                    // If snapshot has options, rebuild; otherwise keep existing options
                    if (snapOpts.length) {
                        ts.clear(true);
                        ts.clearOptions();
                        snapOpts.forEach(o => ts.addOption({
                            value: String(o.value ?? ''),
                            text: String(o.text ?? '')
                        }));
                        ts.refreshOptions(false);
                    } else {
                        // Attempt to populate from DOM if TomSelect has no options
                        if (!Object.keys(ts.options || {}).length) {
                            const domOpts = [];
                            $el.find('option').each(function() {
                                domOpts.push({
                                    value: $(this).attr('value') ?? '',
                                    text: $(this).text()
                                });
                            });
                            if (domOpts.length) {
                                domOpts.forEach(o => ts.addOption({
                                    value: String(o.value ?? ''),
                                    text: String(o.text ?? '')
                                }));
                                ts.refreshOptions(false);
                            }
                        }
                    }

                    if (selected !== '') {
                        try {
                            ts.setValue(String(selected), true);
                        } catch (e) {}
                    } else {
                        ts.clear(true);
                    }
                } else {
                    // Native select path
                    if (snapOpts.length) {
                        $el.empty();
                        snapOpts.forEach(o => $el.append(
                            $('<option/>').attr('value', o.value ?? '').prop('disabled', !!o.disabled).text(
                                o.text ?? '')
                        ));
                    }
                    if (selected !== '') $el.val(String(selected));
                }
            }

            function resetFormUI($form) {
                $form[0].reset();
                $form.find('select').each(function() {
                    const $el = $(this);
                    if ($el[0].tomselect) {
                        $el[0].tomselect.clear(true);
                    }
                });
            }

            function cssEscape(s) {
                return String(s).replace(/"/g, '\\"');
            }

            function loadFormForTab(tabNo) {
                ensureTab(tabNo);
                const {
                    formData = {}, selects = {}
                } = store.tabs[tabNo];
                const $form = $(FORM_ID);

                // Reset once, then restore
                resetFormUI($form);

                // restore selects first
                Object.keys(selects).forEach(name => {
                    restoreSelect($form.find(`[name="${cssEscape(name)}"]`), selects[name]);
                });

                // restore other fields
                Object.keys(formData).forEach(name => {
                    const $el = $form.find(`[name="${cssEscape(name)}"]`);
                    if (!$el.length) return;

                    if ($el.is('select') && $el[0].tomselect) {
                        withTomSelect($el, ts => {
                            const value = formData[name];
                            const isPaymentType = $el.attr('id') ===
                                'payment_type'; // 👈 detect payment_type select

                            if (value !== undefined && value !== null && value !== '') {
                                try {
                                    ts.setValue(String(value), true);
                                } catch (e) {}

                                // 👇 special handling for payment_type field
                                if (isPaymentType) {
                                    if (String(value) !== '1') {
                                        $('.bank_group').css('display', 'block');
                                    } else {
                                        $('.bank_group').css('display', 'none');
                                    }
                                }
                            } else {
                                ts.clear(true);

                                // 👇 also handle payment_type when no value
                                if (isPaymentType) {
                                    $('.bank_group').css('display', 'none');
                                }
                            }
                        });
                    } else if ($el.hasClass('voucher-date') || $el.hasClass('passing_date')) {
                        let val = formData[name] ?? '';
                        if (val) {
                            const d = new Date(val);
                            if (!isNaN(d)) val = d.toISOString().split('T')[0];
                        }
                        flatpickr($el.get(0), {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                            altInput: true,
                            altFormat: "F j, Y",
                            defaultDate: val,
                            onChange: function(selectedDates, dateStr) {
                                const hidden = document.getElementById('hiddenDate');
                                if (hidden) hidden.value = dateStr;
                            }
                        });
                    } else {
                        $el.val(formData[name] ?? '');
                    }
                });

                const parsedVoucherNumber = parseVoucherNumber($('#voucher_number').val());
                if (parsedVoucherNumber !== null) {
                    setVoucherNumber(parsedVoucherNumber);
                }
            }

            function rebuildTabsUI() {
                const $wrap = $('#voucher-tabs').empty();
                const nums = Object.keys(store.tabs).map(n => parseInt(n, 10)).sort((a, b) => a - b);
                nums.forEach(n => {
                    const isActive = (n === store.currentTab);
                    $wrap.append(`
                                <div class="voucher-tab-wrapper position-relative">
                                  <button class="btn {{ $theme['secondary'] }} btn-sm voucher-tab ${isActive ? 'active' : ''}" data-tab="${n}">Voucher#${n}</button>
                                  ${n === 1 ? '' : `<button class="voucher-tab-remove" data-tab="${n}" title="Remove Tab"><i class="fas fa-times"></i></button>`}
                                </div>
                              `);
                });
                currentTab = store.currentTab || 1;
                nextTabNumber = store.nextTabNumber || (nums.length ? Math.max(...nums) + 1 : 2);
            }

            // ---------- Reindex tabs 1..N ----------
            function reindexTabs() {
                const nums = Object.keys(store.tabs).map(n => parseInt(n, 10)).sort((a, b) => a - b);
                const newTabs = {};
                let i = 1;
                nums.forEach(oldNum => {
                    newTabs[i++] = store.tabs[oldNum];
                });
                store.tabs = newTabs;

                const count = Object.keys(newTabs).length;
                store.currentTab = Math.min(store.currentTab, count) || 1;
                store.nextTabNumber = count + 1;

                currentTab = store.currentTab;
                nextTabNumber = store.nextTabNumber;

                persist();
                rebuildTabsUI();
            }

            // ---------- loaders ----------
            function showFormCardLoader() {
                $('#form-card-loader').show();
            }

            function hideFormCardLoader() {
                $('#form-card-loader').hide();
            }

            function switchToTab(tabNo, opts = {}) {
                const {
                    ensureSaved = false
                } = opts;
                const $form = $(FORM_ID);

                if (ensureSaved) {
                    const prevTab = currentTab;
                    saveFormForTab(prevTab, $form);
                    persist();
                }

                currentTab = tabNo;
                store.currentTab = tabNo;

                $('.voucher-tab').removeClass('active');
                $(`.voucher-tab[data-tab="${tabNo}"]`).addClass('active');

                loadFormForTab(tabNo);
                persist();
            }

            // ---------- BOOT ORDER (fixed) ----------
            hydrate();
            initTomSelects(); // 1) have TomSelect instances ready
            rebuildTabsUI(); // 2) render the tab buttons
            loadFormForTab(currentTab); // 3) restore snapshot into widgets (no extra reset)
            const restoredVoucherNumber = parseVoucherNumber($('#voucher_number').val());
            if (restoredVoucherNumber === null) {
                setVoucherNumber(INITIAL_VOUCHER_NUMBER);
            } else {
                setVoucherNumber(restoredVoucherNumber);
            }
            persist();

            // Snapshot once if empty (first run) so reload has data
            (function primeSnapshotIfEmpty() {
                const tab = store.tabs[currentTab] || {};
                const empty = !tab.selects || Object.keys(tab.selects).length === 0;
                if (empty) {
                    saveFormForTab(currentTab, $(FORM_ID));
                    persist();
                }
            })();

            // ---------- Add voucher tab ----------
            $('#add-new-voucher-btn').off('click').on('click', function(e) {
                e.preventDefault();
                showFormCardLoader();

                const voucher_val = $("#voucher_number").val();
                const voucher_num = (parseVoucherNumber(voucher_val) ?? INITIAL_VOUCHER_NUMBER) + 1;

                const data = {
                    type: VOUCHER_TYPE,
                    number: voucher_num,
                    _token: '{{ csrf_token() }}'
                };

                callAjax(
                    "{{ route('check_new_voucher_number') }}",
                    '{{ csrf_token() }}',
                    'POST',
                    data,
                    function(data) {
                        const nextVoucherNumber = data.latest_voucher_number;
                        hideFormCardLoader();

                        const $form = $(FORM_ID);
                        const prevTab = currentTab;
                        saveFormForTab(prevTab, $form);
                        persist();

                        const newNo = store.nextTabNumber || (Object.keys(store.tabs).length + 1);
                        store.tabs[newNo] = {
                            formData: {},
                            selects: {}
                        };
                        store.currentTab = newNo;
                        store.nextTabNumber = newNo + 1;
                        currentTab = newNo;
                        nextTabNumber = store.nextTabNumber;
                        persist();

                        $('#submit-button').attr('tab-number', newNo);
                        resetFormUI($form);

                        rebuildTabsUI();
                        switchToTab(newNo, {
                            ensureSaved: false
                        });

                        setVoucherNumber(nextVoucherNumber);
                        $("#voucher_number").trigger('input').trigger('change');

                        flatpickr('.date', {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                            altInput: true,
                            altFormat: "F j, Y",
                            defaultDate: "{{ session('last_submit_date', now()) }}",
                            onChange: function(selectedDates, dateStr) {
                                const hidden = document.getElementById('hiddenDate');
                                if (hidden) hidden.value = dateStr;
                            }
                        });
                    },
                    true
                );
            });

            // ---------- Tab click ----------
            $(document).on('click', '.voucher-tab', function() {
                const to = parseInt($(this).data('tab'), 10);
                $('#submit-button').attr('tab-number', to);
                if (to === currentTab) return;

                showFormCardLoader();
                switchToTab(to, {
                    ensureSaved: true
                });
                hideFormCardLoader();
            });

            // ---------- Remove tab ----------
            $(document).on('click', '.voucher-tab-remove', function(e) {
                e.stopPropagation();
                const tabNo = parseInt($(this).data('tab'), 10);
                removeTab(tabNo);
            });

            function removeTab(tabNo) {
                showFormCardLoader();

                delete store.tabs[tabNo];

                const remaining = Object.keys(store.tabs).map(n => parseInt(n, 10)).sort((a, b) => a - b);
                if (!remaining.length) {
                    store.tabs = {
                        1: {
                            formData: {},
                            selects: {}
                        }
                    };
                }
                if (!store.tabs[currentTab]) {
                    store.currentTab = remaining[0] || 1;
                    currentTab = store.currentTab;
                }

                reindexTabs();
                switchToTab(store.currentTab, {
                    ensureSaved: false
                });

                const tabCount = Object.keys(store.tabs).length;
                if (tabCount === 1) {
                    callAjax(
                        "{{ route('check_new_voucher_number') }}",
                        '{{ csrf_token() }}',
                        'POST', {
                            type: VOUCHER_TYPE,
                            number: 1,
                            _token: '{{ csrf_token() }}'
                        },
                        function(data) {
                            const nextVoucherNumber = data.latest_voucher_number;
                            setVoucherNumber(nextVoucherNumber);
                            $("#voucher_number").trigger('change');
                            hideFormCardLoader();
                        },
                        true,
                        function() {
                            hideFormCardLoader();
                        }
                    );
                } else {
                    hideFormCardLoader();
                }
            }
            $(document).on('click', '#clear-vouchers', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will clear all vouchers except the first one.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, clear all!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        try {
                            // 🔹 1. Remove all voucher tabs except the first one
                            $('.voucher-tab-wrapper').not(':first').remove();

                            // 🔹 2. Clear the first voucher's form
                            const $form = $('#voucherForm');
                            $form.trigger('reset');

                            // If you use TomSelect or flatpickr, reset them too
                            $form.find('select').each(function() {
                                if (this.tomselect) this.tomselect.clear(true);
                            });

                            // Reset date fields (flatpickr)
                            if (typeof flatpickr !== 'undefined') {
                                $form.find('.date').each(function() {
                                    const picker = this._flatpickr;
                                    if (picker) picker.clear();
                                });
                            }
                            flatpickr('.date', {
                                enableTime: false,
                                dateFormat: "Y-m-d",
                                altInput: true,
                                altFormat: "F j, Y",
                                defaultDate: "{{ session('last_submit_date', now()) }}",
                                onChange: function(selectedDates, dateStr) {
                                    const hidden = document.getElementById(
                                        'hiddenDate');
                                    if (hidden) hidden.value = dateStr;
                                }
                            });

                            // 🔹 3. Clear all stored voucher data from localStorage
                            // localStorage.removeItem('store'); // or whatever your LS_KEY is
                            // localStorage.removeItem('tabs');
                            // localStorage.removeItem('nextTabNumber');
                            // localStorage.removeItem('currentTab');
                            localStorage.removeItem(VOUCHER_STORAGE_KEY);
                            // Or to wipe all (careful, only if safe):
                            // localStorage.clear();

                            // 🔹 4. Reset your in-memory store object if you use one
                            if (typeof store !== 'undefined') {
                                store.tabs = {
                                    1: {
                                        formData: {},
                                        selects: {}
                                    }
                                };
                                store.nextTabNumber = 2;
                            }

                            setVoucherNumber(INITIAL_VOUCHER_NUMBER);

                            // ✅ Success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Cleared!',
                                text: `All ${VOUCHER_TYPE} vouchers cleared except the first one.`,
                            });

                            // If you have a function to persist the store
                            if (typeof persist === 'function') persist();

                            // Optional: switch back to first tab
                            $('.voucher-tab').removeClass('active');
                            $('.voucher-tab[data-tab="1"]').addClass('active');
                            $('.bank_group').css('display', 'none');
                            return
                            if (typeof switchToTab === 'function') switchToTab(1);
                        } catch (err) {
                            console.error('Error clearing vouchers:', err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Something went wrong while clearing vouchers.'
                            });
                        }
                    }
                });
            });

            // ---------- Autosave ----------
            const autoSave = throttle(function() {
                const $form = $(FORM_ID);
                saveFormForTab(currentTab, $form);
                persist();
            }, 250);

            function bindAutoSaveEvents() {
                $(document)
                    .off('input.voucherAutosave change.voucherAutosave blur.voucherAutosave')
                    .on('input.voucherAutosave change.voucherAutosave blur.voucherAutosave',
                        `${FORM_ID} input, ${FORM_ID} textarea, ${FORM_ID} select`, autoSave);

                $(document).off('change.voucherTS').on('change.voucherTS', TS_SEL, autoSave);
            }
            bindAutoSaveEvents();


            // Submit/save handler
            $(document).on('click', '#submitForm', function(e) {
                e.preventDefault();

                const tabNumber = parseInt($('#submit-button').attr('tab-number'), 10) || currentTab;
                const $form = $('#voucherForm');
                const formData = new FormData($form[0]);
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    url: $form.attr('action'),
                    type: $form.attr('method') || 'POST',
                    data: formData,
                    dataType: "JSON",
                    processData: false, // required for FormData
                    contentType: false, // required for FormData
                    beforeSend: function() {
                        $('#submitForm').prop('disabled', true).text('Saving...');
                        $('#submit-button').prop('disabled', true);
                    },
                    success: function(response) {
                        console.log(response, tabNumber);

                        // Remove the saved tab and keep indices contiguous
                        removeTab(tabNumber);
                        // reindexTabs();

                        // Refresh table
                        if (typeof renderDataTable === 'function') {
                            renderDataTable();
                        }

                        // Notify + reset form
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved!',
                            text: 'Voucher saved successfully.'
                        });
                        // $form.trigger('reset');

                        // Optional: clear TomSelect selections visually
                        // $form.find('select').each(function() {
                        //     if (this.tomselect) this.tomselect.clear(true);
                        // });
                    },
                    error: function(xhr) {
                        console.error('❌ Error:', xhr.responseText);
                        const msg = xhr.responseJSON?.message || 'Something went wrong while saving the voucher.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: msg
                        });
                    },
                    complete: function() {
                        $('#confirmedModal').modal('hide');
                        $('#submitForm').prop('disabled', false).text('Save');
                        $('#submit-button').prop('disabled', false);
                    }
                });
            });
            $(document).ready(function() {
                $('.select2').select2({
                    placeholder: 'Select options',
                    allowClear: true,
                    width: '100'
                });
            });


            $(document).ready(function() {
                $('#toggleFilters').on('click', function() {
                    $('#filtersSection').toggle();
                });
                const headAccounts = $('#filter_head_account').val() || [];
                const subAccounts = $('#filter_subhead_account').val() || [];

                $('#applyFilters').on('click', function() {
                    isInitialLoad = false; // Enable filters
                    const head = $('#filter_head_account').val() || [];
                    const sub = $('#filter_subhead_account').val() || [];

                    const headRegex = head.length ? head.join('|') : '';
                    const subRegex = sub.length ? sub.join('|') : '';

                    const table = $('#vouchersTable').DataTable();

                    table.column(3).search(headRegex, true, false); // Head Account column
                    table.column(4).search(subRegex, true, false); // Subhead column
                    table.draw();
                    renderDataTable();
                });

                $('#resetFilters').on('click', function() {
                    $('.select2').val(null).trigger('change');
                    $('#filter_voucher_number, #filter_amount, #filter_amount_min, #filter_amount_max, #filter_date_from, #filter_date_to')
                        .val('');


                    const table = $('#vouchersTable').DataTable();
                    table.search('').columns().search('').draw()
                    isInitialLoad = true; // Reset to show all data again
                    renderDataTable();
                });

                // 🔹 First load → show all data (no filters)
                renderDataTable();
            });

            // Function to render the DataTable with or without filters
            let isInitialLoad = true;

            function renderDataTable() {
                const $tbl = $('#vouchersTable');
                const src = $tbl.data('source');

                if ($.fn.DataTable.isDataTable('#vouchersTable')) {
                    $tbl.DataTable().clear().destroy();
                }

                $tbl.DataTable({
                    processing: true,
                    serverSide: true,
                    paging: true,
                    pageLength: 10,
                    searching: true,
                    order: [
                        [1, 'desc']
                    ],
                    ajax: {
                        url: src,
                        type: 'GET',
                        data: function(d) {
                            if (!isInitialLoad) {
                                d.date_from = $('#filter_date_from').val();
                                d.date_to = $('#filter_date_to').val();
                                d.head_account = $('#filter_head_account').val();
                                d.subhead_account = $('#filter_subhead_account').val();
                                d.voucher_number = $('#filter_voucher_number').val();

                                const minAmt = $('#filter_amount_min').val();
                                const maxAmt = $('#filter_amount_max').val();

                                let legacyAmount = '';
                                if (minAmt && maxAmt && String(minAmt) === String(maxAmt)) {
                                    legacyAmount = minAmt;
                                }
                                $('#filter_amount').val(legacyAmount);

                                d.amount_min = minAmt;
                                d.amount_max = maxAmt;
                                d.amount = legacyAmount;
                            }
                        },
                        dataSrc: 'data'
                    },
                    columns: [{
                            data: 'id',
                            render: (_, __, ___, meta) => meta.row + meta.settings._iDisplayStart + 1
                        },
                        {
                            data: 'date'
                        },
                        {
                            data: 'voucher_number'
                        },
                        {
                            data: 'head'
                        },
                        {
                            data: 'subhead'
                        },
                        {
                            data: 'detail'
                        },
                        {
                            data: 'amount',
                            render: d => Number(d).toLocaleString()
                        },
                        @canany(['update voucher', 'delete voucher', 'read voucher', 'print voucher'])
                            {
                                data: null,
                                orderable: false,
                                render: function(row) {

                                    // Create dynamic print URL
                                    let printUrl = "{{ route('finance.voucher.print', ':id') }}";
                                    printUrl = printUrl.replace(':id', row.id);

                                    let buttons = `<div class="btn-group">`;

                                    @can('update voucher')
                                        buttons +=
                                            `
                                                                                                                            <button class="btn btn-sm btn-primary btn-edit" data-id="${row.id}">
                                                                                                                                <i class="fas fa-pencil-alt"></i>
                                                                                                                            </button>
                                                                                                                        `;
                                    @endcan

                                    @can('delete voucher')
                                        buttons +=
                                            `
                                                                                                                            <button class="btn btn-sm btn-danger btn-delete" data-id="${row.id}">
                                                                                                                                <i class="fas fa-trash"></i>
                                                                                                                            </button>
                                                                                                                        `;
                                    @endcan

                                    @can('print voucher')
                                        buttons +=
                                            `
                                                                                                                            <a href="${printUrl}" target="_blank" class="btn btn-sm btn-info btn-print">
                                                                                                                                <i class="fas fa-print"></i>
                                                                                                                            </a>
                                                                                                                        `;
                                    @endcan

                                    buttons += `</div>`;

                                    return buttons;
                                }
                            },
                        @endcanany


                    ]
                });
            }

            // ---------- AJAX helper ----------
            function callAjax(route, csrf, method, data, callback, isFile = false, onError = null) {
                $.ajaxSetup({
                    headers: {
                        "X-CSRF-TOKEN": csrf
                    }
                });
                $.ajax({
                    url: route,
                    method: method,
                    data: data,
                    success: function(response) {
                        callback(response);
                    },
                    error: function(xhr) {
                        console.error('❌ AJAX Error:', xhr.responseText);
                        if (typeof onError === 'function') onError(xhr);
                    }
                });
            }
        })(jQuery);
    </script>
@endsection

@section('modal')
    {{-- Modal Update --}}
    <div class="modal fade" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header {{ $class }}">
                    <h4 class="modal-title">Edit Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('ledger.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">

                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Serial No.</label>
                                            <div class="input-group">

                                                <input id="e_reference" type="text"
                                                    class="form-control @error('reference') is-invalid @enderror"
                                                    name="reference" value="{{ old('reference') }}" autocomplete="off">
                                                @error('reference')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Reference No.</label>
                                            <div class="input-group">
                                                <input type="text" value="1" name="action" hidden />
                                                <input id="e_voucher" type="text" class="form-control "
                                                    name="voucher" autocomplete="off" readonly>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Date</label>
                                            <div class="input-group">
                                                <input type="text" id="e_date" name="date"
                                                    class="date_database form-control" data-input>
                                                @error('date')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Account Type</label>
                                            <div class="input-group">
                                                <select class="js-tomselect" placeholder=" " name="acct_type"
                                                    id="e_acct_type">
                                                    <option value="">Select Account Type</option>
                                                    @foreach ($accountTypes as $accountType)
                                                        <option value="{{ $accountType->id }}">{{ $accountType->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('projects_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Accounts</label>
                                            <div class="input-group">
                                                <select class="js-tomselect" placeholder=" " name="accounts_id"
                                                    id="e_accounts_id">
                                                    <option value=""></option>
                                                    @foreach ($headaccounts as $v)
                                                        <option value="{{ $v->head_accounting_id }}">
                                                            {{ $v->headAccounting->name ?? '' }}
                                                        </option>
                                                    @endforeach
                                                    {{-- @foreach ($headaccounts as $v)
                                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                                                    @endforeach --}}
                                                </select>
                                                @error('accounts_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="col-sm-8">
                                        <div class="input-group">
                                            <label class="fbox">Child Account</label>
                                            <div class="input-group">
                                                <select class="js-tomselect" placeholder=" " name="subaccounts_id"
                                                    id="e_subaccounts_id">
                                                    <option value=""></option>
                                                    @foreach ($partyaccounts as $v)
                                                        @php
                                                            $balance = $v->balance ?? 0;
                                                            $balanceClass =
                                                                $balance < 0 ? 'text-danger' : 'text-success';
                                                        @endphp
                                                        <option value="{{ $v->subhead_accounting_id }}">
                                                            {{ $v->subheadAccounting->name ?? '' }} &
                                                            Balance = <span
                                                                class="{{ $balanceClass }}">{{ number_format($balance, 2) }}</span>
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('subaccounts_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="input-group">
                                            <label class="fbox">Amount</label>
                                            <div class="input-group">
                                                <input id="e_amount" oninput="formatAmount(this)" type="text"
                                                    class="form-control @error('amount') is-invalid @enderror"
                                                    placeholder="Amount" name="amount" value="{{ old('amount') }}">
                                                @error('amount')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="customer-detail row">

                            {{-- <div class="mb-3 col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Customer</label>
                                    <div class="input-group">
                                        <select class="js-tomselect" placeholder=" " name="customer_id" id="e_customer_id">
                                            <option value="">Select Customer</option>
                                            @foreach ($customers as $v)
                                            <option value="{{ $v->id }}" data-phone="{{ $v->mobile_number }}"
                                                data-nic_number="{{ $v->nic_number }}"
                                                data-home_address="{{ $v->home_address }}">
                                                {{ $v->first_name }} {{ $v->last_name }}
                                                {{ $v->relate }} {{ $v->father_name }} -
                                                {{ $v->phone_number }}</option>
                                            @endforeach
                                        </select>
                                        @error('customer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Plot No.</label>
                                    <div class="input-group">
                                        <select class="js-tomselect" placeholder=" " name="plot_id" id="e_plot_id">
                                            <option value=""></option>
                                            @foreach ($plots as $v)
                                            @php
                                            $plotType = $v->type == 1 ? 'R- ' : 'C- ';
                                            $plotName = $plotType . $v->name;
                                            @endphp
                                            <option value="{{ $v->plot_id }}">
                                                {{ $plotName ?? '' }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('plot_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div> --}}
                            <div class="mb-3 col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">Payment Type</label>
                                    <div class="input-group">
                                        <select class="js-tomselect" placeholder=" " name="payment_type"
                                            id="e_payment_type">
                                            <option value="1">Cash</option>
                                            <option value="2">Online</option>
                                            <option value="3">Check</option>
                                        </select>
                                        @error('payment_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 col-sm-3">
                                <div class="input-group e_bank_group" style="display: none">
                                    <label class="fbox">Number</label>
                                    <div class="input-group">
                                        <input id="e_t_number" type="text"
                                            class="form-control @error('t_number') is-invalid @enderror"
                                            placeholder="Transaction Number" name="t_number"
                                            value="{{ old('t_number') }}">
                                        @error('amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 col-sm-3">
                                <div class="input-group e_bank_group" style="display: none">
                                    <label class="fbox">Bank</label>
                                    <div class="input-group">
                                        <select class="js-tomselect" placeholder=" " name="bank_id" id="e_bank_id">
                                            <option value="">Bank</option>

                                            @foreach (getPakistanBanks() as $v)
                                                <option value="{{ $v['id'] }}">{{ $v['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                </div>
                            </div>
                            <div class="mb-3 col-sm-3">
                                <div class="input-group e_bank_group" style="display: none">
                                    <label class="fbox">Passing Date</label>
                                    <div class="input-group">
                                        <input type="text" name="passing_date" class="date form-control"
                                            id="e_passing_date" data-input>
                                        @error('passing_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 col-sm-12">
                            <div id="eWordingAmount"></div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="input-group">
                                    <label class="fbox">Detail</label>
                                    <div class="input-group">
                                        <textarea id="e_detail" class="form-control @error('detail') is-invalid @enderror" placeholder="Detail"
                                            name="detail" style=" height: 150px;" maxlength="255">{{ old('detail') }}</textarea>
                                        @error('detail')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="modal-footer justify-content-between">
                            <input type="hidden" name="id" id="id">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal delete --}}
    <div class="modal fade" id="modal-delete">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Delete Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('ledger.destroy') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('DELETE')
                        <p class="modal-text">Are you sure you want to delete? <b id="delete-data"></b></p>
                        <textarea name="delete_reason" id="" cols="60"></textarea>
                        <input type="hidden" name="id" id="did">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger">Yes</button>
                </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="confirmedModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Save</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <center>
                        {{-- <img
                            src="https://mir-s3-cdn-cf.behance.net/project_modules/disp/cd514331234507.564a1d2324e4e.gif"
                            alt="555" class="img" width="200"> --}}
                        <br>
                        Are You Sure You Want To Save?
                    </center>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitForm">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection
