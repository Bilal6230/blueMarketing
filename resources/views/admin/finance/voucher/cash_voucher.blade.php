@extends('admin.layouts.master')
@section('content')

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
                        <div class="d-flex gap-2 mb-3" style="gap: 10px;" id="voucher-tabs-container">
                            <button class="btn btn-success btn-sm" id="add-new-voucher-btn">
                                <i class="fas fa-plus"></i> Add New Cash Voucher
                            </button>
                            <div id="voucher-tabs" class="d-flex gap-2" style="gap: 10px;">
                                <div class="voucher-tab-wrapper position-relative">
                                    <button class="btn btn-primary btn-sm active voucher-tab"
                                        data-tab="1">Voucher#1</button>
                                </div>
                            </div>
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

                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold"> {{ $title }}</h5>
                                </div>

                                @can('create voucher')
                                    <form action="{{ route('ledger.store') }}" method="POST" enctype="multipart/form-data"
                                        id="voucherForm">
                                        @csrf
                                        <div class="row">



                                            <div class="mb-3 col-sm-4">
                                                <div class="input-group">
                                                    <label class="fbox">Voucher No</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control " name="voucher_number"
                                                            id="voucher_number"
                                                            value="{{ $type }}-{{ $latest_voucher_number ?? '' }}"
                                                            autocomplete="off" readonly>
                                                        @error('reference')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3 col-sm-4">
                                                <div class="input-group">
                                                    <label class="fbox">Reference No</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control " name="voucher"
                                                            value="{{ $type }}-{{ get_new_voucher_number($type) }}"
                                                            autocomplete="off" readonly>
                                                        @error('reference')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3 col-sm-4">
                                                <div class="input-group">
                                                    <label class="fbox">Date</label>
                                                    <div class="input-group">
                                                        <input type="text" name="date" class="date form-control"
                                                            data-input>
                                                        <input type="hidden" id="hiddenDate" name="hiddenDate">
                                                        @error('date')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3 col-sm-4">
                                                <div class="input-group">
                                                    <label class="fbox">Serial No.</label>
                                                    <div class="input-group">
                                                        <input type="text" value="1" name="action" hidden />
                                                        <input type="text"
                                                            class="form-control @error('reference') is-invalid @enderror"
                                                            name="reference" value="{{ old('reference') }}" autocomplete="off"
                                                            required>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3 col-sm-4">
                                                <div class="input-group">
                                                    <label class="fbox">Account Type</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect @error('acct_type') is-invalid @enderror"
                                                            placeholder=" " autocomplete="off" name="acct_type"
                                                            id="acct_type">
                                                            <option value=""></option>
                                                            <option value="0">Update Please</option>
                                                            <option value="1">Assets</option>
                                                            <option value="2">Owner</option>
                                                            <option value="3">Recovery</option>
                                                            <option value="4">Expence</option>
                                                            <option value="5">Amanat Pyments</option>
                                                        </select>
                                                        @error('is_active')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3 col-sm-4">
                                                <div class="input-group">
                                                    <label class="fbox">Accounts</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect @error('accounts_id') is-invalid @enderror"
                                                            placeholder=" " name="accounts_id" id="accounts_id">
                                                            <option value=""></option>
                                                            @foreach ($headaccounts as $v)
                                                                <option value="{{ $v->head_accounting_id }}">
                                                                    {{ $v->headAccounting->name ?? '' }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('accounts_id')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3 col-6">
                                                <div class="input-group">
                                                    <label class="fbox">Child Account</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " name="subaccounts_id"
                                                            id="subaccounts_id">
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
                                            <div class="mb-3 col-6">
                                                <div class="input-group">
                                                    <label class="fbox">Amount</label>
                                                    <div class="input-group">
                                                        <input id="numberInput" oninput="formatAmount(this)" type="text"
                                                            class="form-control @error('amount') is-invalid @enderror"
                                                            placeholder="Amount" name="amount" value="{{ old('amount') }}">
                                                        @error('amount')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            {{-- <div class="mb-3 col-sm-3">
                                                <div class="input-group">
                                                    <label class="fbox">Payment Type</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " name="payment_type"
                                                            id="payment_type">
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
                                                <div class="input-group bank_group" style="display: none">
                                                    <label class="fbox">Number</label>
                                                    <div class="input-group">
                                                        <input id="t_number" type="text"
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
                                                <div class="input-group bank_group" style="display: none">
                                                    <label class="fbox">Bank</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " name="bank_id"
                                                            id="bank_id">
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
                                                <div class="input-group bank_group" style="display: none">
                                                    <label class="fbox">Passing Date</label>
                                                    <div class="input-group">
                                                        <input type="text" name="passing_date" class="date form-control"
                                                            data-input>
                                                        @error('passing_date')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div> --}}

                                            {{-- <div class="mb-3 col-sm-6">
                                                <div class="input-group">
                                                    <label class="fbox">Customer</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " name="customer_id"
                                                            id="customer_id">
                                                            <option value="">Select Customer</option>
                                                            @foreach ($customers as $v)
                                                                <option value="{{ $v->id }}"
                                                                    data-phone="{{ $v->mobile_number }}"
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
                                                        <select class="js-tomselect" placeholder=" " name="plot_id"
                                                            id="plot_id">
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


                                            <div class="mb-3 col-sm-12">
                                                <div id="wordingAmount"></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-card ">
                                                    <h3 class="info-card-title"> Details</h3>
                                                    <div class="input-field  ">
                                                        <textarea id="detail" class="text-area @error('detail') input-error @enderror"
                                                            placeholder="Enter your details here..." name="detail" maxlength="255" required>{{ old('detail') }}</textarea>
                                                        @error('detail')
                                                            <div class="error-message">{{ $message }}</div>
                                                        @enderror
                                                        <div class="char-counter"><span id="char-count">0</span>/255
                                                            characters</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="info-card ">
                                                    <h3 class="info-card-title">Party Information</h3>
                                                    <div class="info-grid">
                                                        <div class="info-item">
                                                            <span class="info-label">Phone:</span>
                                                            <span id="phone" class="info-value">Not
                                                                provided</span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="info-label">Address:</span>
                                                            <span id="address" class="info-value">Not
                                                                provided</span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="info-label">CNIC:</span>
                                                            <span id="cnic" class="info-value">Not
                                                                provided</span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="info-label">Balance:</span>
                                                            <span id="balance" class="info-value">$0.00</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end mt-5" style="gap: 10px">
                                            <button type="button" class="btn btn-secondary" data-toggle="modal"
                                                data-target="#confirmModal" onclick="saveAsDraft()">Save as Draft</button>
                                            <button type="button" class="btn btn-primary " id="submit-button">Save</button>
                                        </div>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <!-- Cash Voucher List -->
                    <div class="col-12 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold">Cash Voucher List</h5>
                                </div>
                                @can('read voucher')
                                    <div class=" table-responsive">
                                        <table id="vouchersTable" class="table table-bordered table-striped"
                                            data-source="{{ $table_data_route }}">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Date</th>
                                                    <th>Project</th>
                                                    <th>Head Account</th>
                                                    <th>Sub Head Account</th>
                                                    <th>Detail</th>
                                                    <th>Amount</th>

                                                    @canany(['update voucher', 'delete voucher'])
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
                    </div>
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

    <style>
        .card-loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            border-radius: 8px;
        }

        .custom_card {
            position: relative;
        }

        .voucher-tab-wrapper {
            position: relative;
        }

        .voucher-tab-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            border: none;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .voucher-tab-remove:hover {
            background: #c82333;
        }

        .voucher-tab-remove i {
            font-size: 10px;
        }
    </style>

    <script>
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
        function submitForm() {
            document.getElementById('voucherForm').submit();
        }

        function saveAsDraft() {
            const form = document.getElementById('voucherForm');
            form.action = "{{ route('ledger.save_as_draft') }}";
        }
        $(document).ready(function() {
            const $tbl = $('#vouchersTable');
            const src = $tbl.data('source');

            // Destroy existing instance if already initialized
            if ($.fn.DataTable.isDataTable('#vouchersTable')) {
                $tbl.DataTable().clear().destroy();
            }

            $tbl.DataTable({
                destroy: true, // allow re-init if some other script touched it
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
                    dataSrc: 'data',
                    error: function(xhr, status, err) {
                        console.error('DataTables AJAX error:', status, err, xhr.responseText);
                        alert('Failed to load data. Check console for details.');
                    }
                },
                columns: [{
                        data: 'id',
                        render: (_, __, ___, meta) => meta.row + meta.settings._iDisplayStart + 1
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'project'
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
                    @canany(['update voucher', 'delete voucher'])
                        {
                            data: null,
                            orderable: false,
                            render: row => {
                                if (row.type === 'BO') return 'Plot Booking Invoice';
                                const canDirectUpdate =
                                    {{ Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update') ? 'true' : 'false' }};
                                // ✅ Always include Edit and Delete buttons
                                let buttons = `
                                    <div class="btn-group">
                                        @can('update voucher')
                                        <button class="btn btn-sm btn-primary btn-edit" data-id="${row.id}">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        @endcan

                                        @can('delete voucher')
                                        <button class="btn btn-sm btn-danger btn-delete" data-id="${row.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endcan
                                `;

                                // ✅ Blade resolves permissions once, passed as a boolean for JS


                                // ➡️ Show approval controls only for users with direct-update or super-admin
                                if (canDirectUpdate === true || canDirectUpdate === 'true') {
                                    if (row.status === 'Pending') {
                                        buttons += `
                                    <div class="d-flex admin_approval">
                                        <button class="btn btn-sm btn-outline-info btn-view-changes"
                                            data-old='${JSON.stringify(row.old_values)}'
                                            data-new='${JSON.stringify(row.new_values)}'
                                            data-submitted_by="${row.submitted_by}"
                                            data-record_id="${row.id}">
                                            <i class="fas fa-eye"></i> Approval Required
                                        </button>
                                    </div>`;
                                    }
                                }
                                // ❗ For users without permission, show Needs Admin Approval badge if Pending
                                else {
                                    if (row.status === 'Pending') {
                                        buttons += `
                                    <span class="badge bg-secondary px-3 py-2"
                                        style="cursor: pointer;"
                                        data-bs-toggle="tooltip"
                                        title="Needs Admin Approval">
                                        <i class="fas fa-lock me-1"></i>
                                    </span>`;
                                    }
                                }

                                buttons += '</div>';
                                return buttons;
                            }
                        },
                    @endcanany
                ]
            });

        });


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





            $('#acct_type').change(function() {
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
                            let accountsEl = $('#accounts_id')[0]; // DOM element
                            let tsAccounts = accountsEl.tomselect; // TomSelect instance

                            let subAccountsEl = $('#subaccounts_id')[0];
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
                    let tsAccounts = $('#accounts_id')[0].tomselect;
                    let tsSubAccounts = $('#subaccounts_id')[0].tomselect;
                    if (tsAccounts) tsAccounts.clearOptions();
                    if (tsSubAccounts) tsSubAccounts.clearOptions();
                }
            });


            // Similar change event for 'accounts_id' dropdown to fetch subaccounts based on account selection
            $('#accounts_id').change(function() {
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
                            let subAccountsSelect = $('#subaccounts_id')[0].tomselect;

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

                            // Auto-select acct_type if found
                            if (filteredData.length > 0) {
                                let acct_type = filteredData[0].head_accounting.acct_type;
                                let acctTypeSelect = $('#acct_type')[0].tomselect;

                                // Only set value if it's different
                                if (!acctTypeSelect.getValue()) {
                                    acctTypeSelect.setValue(acct_type, true);
                                }
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
                            let acct_type = response.acct_type;
                            let acctTypeSelect = $('#acct_type')[0].tomselect;
                            if (!acctTypeSelect.getValue()) {
                                acctTypeSelect.setValue(acct_type, true);
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
            $('#payment_type').change(function(e) {

                e.preventDefault();

                if ($(this).val() != '1') {

                    $('.bank_group').css('display', 'block');
                } else {

                    $('.bank_group').css('display', 'none');
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

                console.log(acctType);
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

                            // Auto-select acct_type if found
                            if (filteredData.length > 0) {
                                let acct_type = filteredData[0].head_accounting.acct_type;
                                let acctTypeSelect = $('#e_acct_type')[0].tomselect;
                                if (!acctTypeSelect.getValue()) {
                                    acctTypeSelect.setValue(acct_type, true);
                                }
                            }
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
                            if (!acctSelect.setValue()) {
                                acctSelect.setValue(headId, true);
                            }
                            let acct_type = response.acct_type;
                            let acctTypeSelect = $('#e_acct_type')[0].tomselect;
                            if (!acctTypeSelect.setValue()) {
                                acctTypeSelect.setValue(acct_type, true);
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
                        console.log(data);

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
                checkField("[name='reference']", "Reference No is required.");
                checkField("[name='date']", "Date is required.");
                checkField("[name='acct_type']", "Account Type is required.");
                checkField("[name='accounts_id']", "Accounts is required.");
                checkField("[name='subaccounts_id']", "Child Account is required.");
                checkField("[name='customer_id']", "Customer is required.");
                checkField("[name='plot_id']", "Plot selection is required.");
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
                    $('#confirmModal').modal('show');
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
            const LS_KEY = 'paysavo_voucher_tabs_v1';
            const FORM_ID = '#voucherForm';
            const TS_SEL = '.js-tomselect';

            // Store shape:
            // { tabs: { [n]: { formData:{}, selects:{ [name]: {options:[{value,text,disabled}], selected:''} } } }, currentTab:1, nextTabNumber:2 }
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

            // --- utils ---
            const persist = () => localStorage.setItem(LS_KEY, JSON.stringify(store));
            const hydrate = () => {
                try {
                    const raw = localStorage.getItem(LS_KEY);
                    if (!raw) return;
                    const parsed = JSON.parse(raw);
                    if (parsed && parsed.tabs) {
                        store = parsed;
                        currentTab = store.currentTab || 1;
                        nextTabNumber = store.nextTabNumber || 2;
                    }
                } catch (e) {
                    console.warn('hydrate failed', e);
                }
            };
            const ensureTab = n => {
                if (!store.tabs[n]) store.tabs[n] = {
                    formData: {},
                    selects: {}
                };
            };

            // throttle to avoid save storms
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

            // Serialize form into store for a specific tab
            function saveFormForTab(tabNo, $form) {
                ensureTab(tabNo);
                const formData = {};
                const selects = {};


                // inputs + textarea
                $form.find('input, textarea').each(function() {
                    const name = $(this).attr('name');
                    // console.log(name);
                    if (!name) return;
                    formData[name] = $(this).val();
                });

                // selects (include options & selected)
                $form.find('select').each(function() {
                    const $el = $(this);
                    const name = $el.attr('name');
                    if (!name) return;

                    const options = [];
                    // console.log(name);
                    $el.find('option').each(function() {

                        options.push({
                            value: $(this).attr('value') ?? '',
                            text: $(this).text(),
                            disabled: !!$(this).prop('disabled')
                        });
                    });

                    // prefer tomselect-selected if present
                    let selected = $el.val() ?? '';
                    if ($el[0] && $el[0].tomselect) {
                        const ts = $el[0].tomselect;
                        selected = (ts.getValue && ts.getValue()) || '';
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

            // Rebuild options then set value
            function restoreSelect($el, snap) {
                if (!$el.length || !snap) return;
                const selected = snap.selected ?? '';
                const opts = snap.options || [];

                if ($el[0].tomselect) {
                    const ts = $el[0].tomselect;
                    // console.log(selected);

                    ts.clear(true);
                    ts.clearOptions();
                    opts.forEach(o => ts.addOption({
                        value: String(o.value ?? ''),
                        text: String(o.text ?? '')
                    }));
                    ts.refreshOptions(false);
                    console.log('selected', selected, ts.inputId);

                    ts.setValue(String(selected), true);
                } else {
                    $el.empty();
                    opts.forEach(o => $el.append($('<option/>').attr('value', o.value ?? '').prop('disabled', !!o
                        .disabled).text(o.text ?? '')));
                    if (selected !== '') $el.val(String(selected));
                }
                // $el.trigger('change');
            }

            function resetFormUI($form) {
                console.log($('#voucher_number').val());
                $form[0].reset();
                console.log($('#voucher_number').val());
                $form.find('select').each(function() {
                    const $el = $(this);
                    if ($el[0].tomselect) {
                        $el[0].tomselect.clear(true);
                    }
                });
            }

            function loadFormForTab(tabNo) {
                ensureTab(tabNo);
                const {
                    formData = {}, selects = {}
                } = store.tabs[tabNo];
                const $form = $(FORM_ID);

                resetFormUI($form);

                // restore selects first (options → value)
                Object.keys(selects).forEach(name => {
                    restoreSelect($form.find(`[name="${cssEscape(name)}"]`), selects[name]);
                });

                // restore other fields
                Object.keys(formData).forEach(name => {
                    const $el = $form.find(`[name="${cssEscape(name)}"]`);
                    if (!$el.length) return;
                    if ($el.is('select') && $el[0].tomselect) {
                        // if not in selects snapshot, still set value
                        if (!selects[name]) withTomSelect($el, ts => {
                            try {
                                ts.setValue(String(formData[name] ?? ''), true);
                            } catch {}
                        });
                    } else {
                        $el.val(formData[name] ?? '');
                    }
                });
            }

            function cssEscape(s) {
                return String(s).replace(/"/g, '\\"');
            }

            function rebuildTabsUI() {
                const $wrap = $('#voucher-tabs').empty();
                const nums = Object.keys(store.tabs).map(n => parseInt(n, 10)).sort((a, b) => a - b);
                nums.forEach(n => {
                    const isActive = (n === store.currentTab);
                    $wrap.append(`
                <div class="voucher-tab-wrapper position-relative">
                <button class="btn btn-primary btn-sm voucher-tab ${isActive?'active':''}" data-tab="${n}">Voucher#${n}</button>
                ${n===1 ? '' : `<button class="voucher-tab-remove" data-tab="${n}" title="Remove Tab"><i class="fas fa-times"></i></button>`}
                </div>
            `);
                });
                currentTab = store.currentTab || 1;
                nextTabNumber = store.nextTabNumber || (Math.max(...nums, 1) + 1);
            }



            // --- DOM ready ---
            // $(document).ready(function() {

            hydrate();
            rebuildTabsUI();
            switchToTab(currentTab, {
                ensureSaved: false
            });

            // Add voucher tab
            $('#add-new-voucher-btn').off('click').on('click', function() {
                const $form = $(FORM_ID);

                // Save current tab before reset
                const prevTab = currentTab;
                saveFormForTab(prevTab, $form);
                persist();

                // Create new tab
                const newNo = nextTabNumber;
                ensureTab(newNo);
                store.tabs[newNo] = {
                    formData: {},
                    selects: {}
                };
                store.nextTabNumber = newNo + 1;
                nextTabNumber = store.nextTabNumber; // ✅ sync local variable
                persist();

                // Reset form for new tab
                // console.log($form.find('input[name="voucher_number"]').val());
                resetFormUI($form);
                // console.log($form.find('input[name="voucher_number"]').val());

                // 🔥 Increment voucher number
                // const $voucherInput = $form.find('input[name="voucher_number"]');
                // const prevVoucher = $voucherInput.val(); // e.g. "CR-1823"

                // if (prevVoucher && prevVoucher.includes('-')) {
                //     const parts = prevVoucher.split('-');
                //     const prefix = parts[0];
                //     const num = parseInt(parts[1]) || 0;
                //     console.log(`${prefix}-${num + 1}`);
                //     $voucherInput.val(`${prefix}-${num + 1}`);
                // }
                // Add new button
                $('#voucher-tabs').append(`
                        <div class="voucher-tab-wrapper position-relative">
                            <button class="btn btn-primary btn-sm voucher-tab active" data-tab="${newNo}">Voucher#${newNo}</button>
                            <button class="voucher-tab-remove" data-tab="${newNo}" title="Remove Tab"><i class="fas fa-times"></i></button>
                        </div>
                    `);
                $('.voucher-tab').removeClass('active');
                $(`.voucher-tab[data-tab="${newNo}"]`).addClass('active');

                switchToTab(newNo, {
                    ensureSaved: false
                });

                // ✅ Now safely update voucher number
                const $voucherInput = $("#voucher_number");
                console.log('Voucher input after switchToTab:', $voucherInput);

                const prevVoucher = $voucherInput.val();
                console.log('Prev voucher after tab load:', prevVoucher);

                if (prevVoucher && prevVoucher.includes('-')) {
                    const [prefix, numStr] = prevVoucher.split('-');
                    const nextNum = parseInt(numStr || '0') + 1;
                    const nextVoucher = `${prefix}-${nextNum}`;
                    console.log('Final updated voucher:', nextVoucher);

                    $voucherInput.val(nextVoucher)
                        .attr('value', nextVoucher)
                        .trigger('input')
                        .trigger('change');

                    console.log('✅ Voucher updated in field:', $voucherInput.val());
                }



                // }, 150);
            });


            // Tab click
            $(document).on('click', '.voucher-tab', function() {
                const to = parseInt($(this).data('tab'), 10);
                console.log('a');

                if (to === currentTab) return;

                showFormCardLoader();
                switchToTab(to, {
                    ensureSaved: true
                });
                hideFormCardLoader();
            });

            // Remove tab
            $(document).on('click', '.voucher-tab-remove', function(e) {
                e.stopPropagation();
                const tabNo = parseInt($(this).data('tab'), 10);
                if (tabNo === 1) return;

                showFormCardLoader();
                setTimeout(function() {
                    // Save current form for safety
                    saveFormForTab(currentTab, $(FORM_ID));

                    delete store.tabs[tabNo];
                    persist();

                    // If removing current tab, pick smallest existing (prefer 1)
                    if (currentTab === tabNo) {
                        const remaining = Object.keys(store.tabs).map(n => parseInt(n, 10))
                            .sort((a, b) => a - b);
                        const fallback = remaining.includes(1) ? 1 : remaining[0];
                        switchToTab(fallback, {
                            ensureSaved: false
                        });
                    }

                    // Rebuild UI to reflect removal
                    rebuildTabsUI();
                    hideFormCardLoader();
                }, 150);
            });

            function switchToTab(tabNo, opts = {}) {
                const {
                    ensureSaved = false
                } = opts;
                const $form = $(FORM_ID);
                if (ensureSaved) {

                    // Save against the *previous* tab before switching pointer
                    const prevTab = currentTab;
                    console.log('b', prevTab, tabNo);
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

            // --- Autosave binding (throttled) ---
            const autoSave = throttle(function() {
                const $form = $(FORM_ID);
                // saveFormForTab(currentTab, $form);
                persist();
            }, 250);

            function bindAutoSaveEvents() {
                $(document)
                    .off('input.voucherAutosave change.voucherAutosave blur.voucherAutosave')
                    .on('input.voucherAutosave change.voucherAutosave blur.voucherAutosave',
                        `${FORM_ID} input, ${FORM_ID} textarea, ${FORM_ID} select`, autoSave);

                // For Tom Select, also listen to its change
                $(document).off('change.voucherTS').on('change.voucherTS', TS_SEL, autoSave);
            }
            bindAutoSaveEvents();

            // Keep your loaders (no-op wrappers here; replace with your own)
            function showFormCardLoader() {
                $('#form-card-loader').show();
            }

            function hideFormCardLoader() {
                $('#form-card-loader').hide();
            }
            // });
        })(jQuery);

        localStorage.removeItem('paysavo_voucher_tabs_v1'); // your key
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
                                                    <option value=""></option>
                                                    <option value="0">Update Please</option>
                                                    <option value="1">Assets</option>
                                                    <option value="2">Owner</option>
                                                    <option value="3">Recovery</option>
                                                    <option value="4">Expence</option>
                                                    <option value="5">Amanat Pyments</option>
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
                                                            {{ $v->headAccounting->name ?? '' }}</option>
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
                            <div class="mb-3 col-sm-12">
                                <div id="eWordingAmount"></div>
                            </div>
                            <div class="mb-3 col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Customer</label>
                                    <div class="input-group">
                                        <select class="js-tomselect" placeholder=" " name="customer_id"
                                            id="e_customer_id">
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
                            </div>
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
@endsection
