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
                    <div class="col-12 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold"> {{ $title }}</h5>
                                </div>

                                @can('create voucher')
                                    <form action="{{ route('ledger.store') }}" method="POST" enctype="multipart/form-data"
                                        id="voucherForm">
                                        @csrf
                                        <div class="row">

                                            <div class="mb-3 col-sm-3">
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

                                            <div class="mb-3 col-sm-3">
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

                                            <div class="mb-3 col-sm-6">
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
                                            <div class="mb-3 col-sm-6">
                                                <div class="input-group">
                                                    <label class="fbox">Account Type</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " 
                                                            autocomplete="off" name="acct_type" id="acct_type">
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
                                            <div class="mb-3 col-sm-6">
                                                <div class="input-group">
                                                    <label class="fbox">Accounts</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " name="accounts_id" id="accounts_id">
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

                                            <div class="mb-3 col-sm-6">
                                                <div class="input-group">
                                                    <label class="fbox">Child Account</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " name="subaccounts_id" id="subaccounts_id">
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
                                            <div class="mb-3 col-sm-6">
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
                                            <div class="mb-3 col-sm-12">
                                                <div id="wordingAmount"></div>
                                            </div>
                                            <div class="mb-3 col-sm-6">
                                                <div class="input-group">
                                                    <label class="fbox">Customer</label>
                                                    <div class="input-group">
                                                        <select class="js-tomselect" placeholder=" " name="customer_id" id="customer_id">
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
                                                        <select class="js-tomselect" placeholder=" " name="plot_id" id="plot_id">
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
                                                        <select class="js-tomselect" placeholder=" " name="payment_type" id="payment_type">
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
                                                        <select class="js-tomselect" placeholder=" " name="bank_id" id="bank_id">
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
                                            <button type="button" class="btn btn-primary " data-toggle="modal" id="submit-button"
                                                data-target="#confirmModal">Save</button>
                                        </div>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold">Cash Voucher List</h5>
                                </div>
                                @can('read voucher')
                                    <div class=" table-responsive">
                                        <table id="example1" class="table table-bordered table-striped">
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
                                                @foreach ($data as $i)
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>
                                                            {{ $i->date ?? '' }}
                                                        </td>
                                                        <td>
                                                            {{ $i->projectHeadSubhead->project->project ?? '' }}
                                                        </td>
                                                        <td>
                                                            {{ $i->projectHeadSubhead->headAccounting->name ?? '' }}
                                                        </td>
                                                        <td>
                                                            {{ $i->projectHeadSubhead->subheadAccounting->name ?? '' }}

                                                        </td>
                                                        <td>
                                                            {{ $i->detail }}
                                                        </td>
                                                        <td>
                                                            {{ $i->amount }}
                                                        </td>


                                                        @canany(['update voucher', 'delete voucher'])
                                                            <td>

                                                                <div class="btn-group">
                                                                    @if ($i->type != 'BO')
                                                                        @can('update voucher')
                                                                            <button class="btn btn-sm btn-primary btn-edit"
                                                                                data-id="{{ $i->id }}"><i
                                                                                    class="fas fa-pencil-alt"></i></button>
                                                                        @endcan
                                                                        @can('delete voucher')
                                                                            <button class="btn btn-sm btn-danger btn-delete"
                                                                                data-id="{{ $i->id }}"
                                                                                data-name="{{ $i->name }}"><i
                                                                                    class="fas fa-trash"></i></button>
                                                                        @endcan
                                                                    @else
                                                                        Plot Booking Invoice
                                                                    @endif

                                                                </div>
                                                            </td>
                                                        @endcanany
                                                    </tr>
                                                @endforeach


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

@endsection

@section('js')
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




            $('#acct_type').change(function() {
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
                                acctTypeSelect.setValue(acct_type, true);
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
                            acctSelect.setValue(headId, true);
                            let acct_type = response.acct_type;
                            let acctTypeSelect = $('#acct_type')[0].tomselect;
                            acctTypeSelect.setValue(acct_type, true);
                            console.log(headId, acct_type);

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
            $('#payment_type').change(function(e) {

                e.preventDefault();

                if ($(this).val() != '1') {

                    $('.bank_group').css('display', 'block');
                } else {

                    $('.bank_group').css('display', 'none');
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
                                acctTypeSelect.setValue(acct_type, true);
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
                            acctSelect.setValue(headId, true);
                            let acct_type = response.acct_type;
                            let acctTypeSelect = $('#e_acct_type')[0].tomselect;
                            acctTypeSelect.setValue(acct_type, true);
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
                        // $('#e_acct_type').val(data.project_head_subhead.head_accounting
                        //     .acct_type).trigger(
                        //     'change');

                        flatpickr('#e_date', {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                            defaultDate: data
                                .date
                        });

                        $("#e_voucher").val(data.type + '-0000' + data.type_id);

                        if (data.type == 'CR') {
                            $("#e_amount").val(data.amount_in);

                        } else if (data.type == 'CP') {
                            $("#e_amount").val(data.amount_out);

                        }

                        $("#e_detail").val(data.detail);

                        // let acct_type = data.project_head_subhead.head_accounting.acct_type;
                        // // Update the UI based on the response
                        // let $selectacc = $('#e_acct_type');
                        // let $optionsacc = $selectacc.find('option');
                        // let $matchingOptionacc = $optionsacc.filter(function() {
                        //     return $(this).val() == acct_type;
                        // });
                        // if ($matchingOptionacc.length > 0) {
                        //     $optionsacc.prop('selected',
                        //     false); // clear previous selections
                        //     $matchingOptionacc.prop('selected',
                        //     true); // select matching one
                        //     $matchingOptionacc.detach().appendTo($selectacc);
                        // }
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
                        // Update the UI based on the response
                        // let $select = $('#e_accounts_id');
                        // let $options = $select.find('option');
                        // let $matchingOption = $options.filter(function() {
                        //     return $(this).val() == headId;
                        // });
                        // if ($matchingOption.length > 0) {
                        //     $options.prop('selected', false); // clear previous selections
                        //     $matchingOption.prop('selected', true); // select matching one
                        //     $matchingOption.detach().appendTo($select);
                        // }
                        let subheadId = data.project_head_subhead.subhead_accounting.id;
                        let eSubAccSelect = $('#e_subaccounts_id')[0].tomselect;
                        eSubAccSelect.setValue(subheadId, true);
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
            $(document).on("click", "#submit-button",function(e) {
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
                }
            });


        });


        function convertToWords() {
            var numberInput = document.getElementById('numberInput').value;

            var numericValue = numberInput.replace(/,/g, '');

            var wordingAmount = numberToWords.toWords(numericValue);

            document.getElementById('wordingAmount').innerText = wordingAmount;
        }

        function formatAmount(input) {
            let value = input.value.replace(/[^0-9]/g, '').replace(/^0+/, '');

            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            input.value = value;
        }
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
                                                <select class="js-tomselect" placeholder=" " name="acct_type" id="e_acct_type">
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
                                                <select class="js-tomselect" placeholder=" " name="accounts_id" id="e_accounts_id">
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
                                                <select class="js-tomselect" placeholder=" " name="subaccounts_id" id="e_subaccounts_id">
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
