@extends('admin.layouts.master')
@section('content')

    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <span class="{{ $class }}">
                            {{ $title }}
                        </span>
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
                    <div class="col-md-12 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold">Cash In Voucher</h5>
                                </div>
                                {{-- form --}}
                                @can('create voucher')
                                    <form action="{{ route('ledger.store') }}" method="POST" enctype="multipart/form-data"
                                        id="voucherForm">
                                        @csrf
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="row">
                                                    <div class="col-sm-3">
                                                        <div class="input-group">
                                                            <label class="fbox">Serial No.</label>
                                                            <div class="input-group">
                                                                <input type="text" value="1" name="action" hidden />
                                                                <input type="text"
                                                                    class="form-control @error('reference') is-invalid @enderror"
                                                                    name="reference" value="{{ old('reference') }}"
                                                                    autocomplete="off">

                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-3">
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

                                                    <div class="col-sm-6">
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
                                                </div>
                                            </div>

                                        </div>

                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <div class="input-group">
                                                            <label class="fbox">Account Type</label>
                                                            <div class="input-group">
                                                                <select class="form-control select2" name="acct_type"
                                                                    id="acct_type">
                                                                    <option value="">Select an option</option>
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
                                                    <div class="col-sm-6">
                                                        <div class="input-group">
                                                            <label class="fbox">Accounts</label>
                                                            <div class="input-group">
                                                                <select class="form-control select2" name="accounts_id"
                                                                    id="accounts_id">
                                                                    <option value="">Select an option</option>
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

                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <div class="input-group">
                                                            <label class="fbox">Child Account</label>
                                                            <div class="input-group">
                                                                <select class="form-control select2" name="subaccounts_id"
                                                                    id="subaccounts_id">
                                                                    <option value="">Select an option</option>
                                                                    @foreach ($partyaccounts as $v)
                                                                        @php
                                                                            $balance = $v->balance ?? 0;
                                                                            $balanceClass =
                                                                                $balance < 0
                                                                                    ? 'text-danger'
                                                                                    : 'text-success';
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
                                                    <div class="col-sm-6">
                                                        <div class="input-group">
                                                            <label class="fbox">Amount</label>
                                                            <div class="input-group">
                                                                <input id="numberInput" oninput="formatAmount(this)"
                                                                    type="text"
                                                                    class="form-control @error('amount') is-invalid @enderror"
                                                                    placeholder="Amount" name="amount"
                                                                    value="{{ old('amount') }}">
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
                                                <div id="wordingAmount"></div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="input-group">
                                                    <label class="fbox">Customer</label>
                                                    <div class="input-group">
                                                        <select class="form-control select2" name="customer_id"
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
                                            <div class="col-sm-6">
                                                <div class="input-group">
                                                    <label class="fbox">Plot No.</label>
                                                    <div class="input-group">
                                                        <select class="form-control select2" name="plot_id" id="plot_id">
                                                            <option value="">Select Plot</option>
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
                                            <div class="col-sm-3">
                                                <div class="input-group">
                                                    <label class="fbox">Payment Type</label>
                                                    <div class="input-group">
                                                        <select class="form-control select2" name="payment_type"
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
                                            <div class="col-sm-3">
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
                                            <div class="col-sm-3">
                                                <div class="input-group bank_group" style="display: none">
                                                    <label class="fbox">Bank</label>
                                                    <div class="input-group">
                                                        <select class="form-control select2" name="bank_id" id="bank_id">
                                                            <option value="">Bank</option>
                                                            @foreach (getPakistanBanks() as $v)
                                                                <option value="{{ $v['id'] }}">{{ $v['name'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                </div>
                                            </div>
                                            <div class="col-sm-3">
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
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <div class="input-group {{ $bg_voucher }}">
                                                            <div class="input-group">
                                                                <textarea class="form-control @error('detail') is-invalid @enderror" placeholder="Detail" name="detail"
                                                                    style=" height: 150px;" maxlength="255">{{ old('detail') }}</textarea>
                                                                @error('detail')
                                                                    <div class="invalid-feedback">{{ $message }}
                                                                    </div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <div class="{{ $bg_voucher }}">
                                                            <div class="party-info">

                                                                <div class="info-set">
                                                                    <strong class="info-label">Phone:</strong>
                                                                    <span id="phone" class="info-data"></span><br>
                                                                </div>

                                                                <div class="info-set">
                                                                    <strong class="info-label">Address:</strong>
                                                                    <span id="address" class="info-data"></span><br>
                                                                </div>

                                                                <div class="info-set">
                                                                    <strong class="info-label">CNIC:</strong>
                                                                    <span id="cnic" class="info-data"></span><br>
                                                                </div>

                                                                <div class="info-set">
                                                                    <strong class="info-label">Balance:</strong>
                                                                    <span id="balance" class="info-data"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-12">


                                                <div class="col-sm-3">
                                                    <div class="modal-footer justify-content-between">
                                                        <button type="button" class="btn btn-primary btn-lg btn-block"
                                                            data-toggle="modal" data-target="#confirmModal">Save</button>


                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>
                    {{-- details table --}}
                    <div class="col-md-12 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold">Cash Voucher</h5>
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
        function submitForm() {
            document.getElementById('voucherForm').submit();
        }

        function saveAsDraft() {
            const form = document.getElementById('voucherForm');
            form.action = "{{ route('ledger.save_as_draft') }}";
            // form.submit();
        }


        $(document).ready(function() {

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
                            // console.log(response); // Log the response to the console
                            let headId = response.headId;
                            // Update the UI based on the response
                            let $select = $('#accounts_id');
                            let $options = $select.find('option');
                            let $matchingOption = $options.filter(function() {
                                return $(this).val() == headId;
                            });
                            if ($matchingOption.length > 0) {
                                $options.prop('selected', false); // clear previous selections
                                $matchingOption.prop('selected', true); // select matching one
                                $matchingOption.detach().appendTo($select);
                            }
                            let acct_type = response.acct_type;
                            // Update the UI based on the response
                            let $selectacc = $('#acct_type');
                            let $optionsacc = $selectacc.find('option');
                            let $matchingOptionacc = $optionsacc.filter(function() {
                                return $(this).val() == acct_type;
                            });
                            if ($matchingOptionacc.length > 0) {
                                $optionsacc.prop('selected',
                                false); // clear previous selections
                                $matchingOptionacc.prop('selected',
                                true); // select matching one
                                $matchingOptionacc.detach().appendTo($selectacc);
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


            $('#acct_type').change(function() {
                var acctType = $(this).val();

                console.log(acctType);
                if (acctType) {
                    $.ajax({
                        url: '{{ route('get_account') }}', // Replace with your actual route
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            acct_type: acctType,
                            action: 'get_head',
                            _token: '{{ csrf_token() }}' // Include CSRF token for Laravel
                        },
                        success: function(data) {
                            $('#accounts_id').empty();
                            $('#subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            // var filteredData = data.filter(function(item) {
                            //     return item.project_id == projectID;
                            // });

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $('#accounts_id').append(
                                '<option value="">Select an option</option>');
                            $.each(data, function(key, value) {
                                $('#accounts_id').append('<option value="' + value
                                    .head_accounting_id + '">' + value
                                    .head_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#accounts_id').empty();
                    $('#subaccounts_id').empty();
                }
            });

            // Similar change event for 'accounts_id' dropdown to fetch subaccounts based on account selection
            $('#accounts_id').change(function() {
                var accountID = $(this).val();


                if (accountID) {
                    // Implement AJAX call to fetch subheadaccounts based on the selected account
                    $.ajax({
                        url: '{{ route('get_account') }}', // Replace with your actual route
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            accountID: accountID,
                            action: 'get_child',

                            _token: '{{ csrf_token() }}' // Include CSRF token for Laravel
                        },
                        success: function(data) {
                            $('#subaccounts_id').empty();
                            // Filter data to match selected project ID
                            var filteredData = data.filter(function(item) {
                                return item.head_accounting_id == accountID;
                            });
                            $('#subaccounts_id').append(
                                '<option value="">Select an option</option>');

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $.each(filteredData, function(key, value) {
                                $('#subaccounts_id').append('<option value="' + value
                                    .subhead_accounting_id + '">' + value
                                    .subhead_accounting.name +
                                    '& Balance = <span class="">' + value.balance +
                                    '</span></option>');
                            });
                            let acct_type = filteredData[0].head_accounting.acct_type;
                            // Update the UI based on the response
                            let $select = $('#acct_type');
                            let $options = $select.find('option');
                            let $matchingOption = $options.filter(function() {
                                return $(this).val() == acct_type;
                            });
                            if ($matchingOption.length > 0) {
                                $options.prop('selected', false); // clear previous selections
                                $matchingOption.prop('selected', true); // select matching one
                                $matchingOption.detach().appendTo($select);
                            }

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#subaccounts_id').empty();
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
                            let $select = $('#customer_id');
                            let $options = $select.find('option');
                            let $matchingOption = $options.filter(function() {
                                return $(this).val() == customerId;
                            });
                            if ($matchingOption.length > 0) {
                                $options.prop('selected', false); // clear previous selections
                                $matchingOption.prop('selected', true); // select matching one
                                $matchingOption.detach().appendTo($select);
                            }
                        }
                    });
                }
            });
            $('#customer_id').change(function(e) {
                e.preventDefault();
                var customerId = $(this).val();

                if (customerId) {
                    fetchPlots(customerId);
                    // console.log($(this).data('phone'));
                    // $('#phone').text( 'asdas');
                } else {
                    // If no project is selected, empty the customer dropdown
                    $('#plot_id').empty();
                    $('#plot_id').append('<option value="">Select plots</option>');
                }

            });

            function fetchPlots(customerId) {
                $.ajax({
                    url: '/admin/get-plots-list', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}', // Add CSRF token
                        project_id: '{{ getSelectedTown() }}',
                        customer_id: customerId // Pass project ID to server
                    },
                    success: function(data) {
                        $('#plot_id').empty();
                        $('#plot_id').append('<option value="">Select Plots</option>');
                        $.each(data, function(key, plot) {
                            var plotType = (plot.type == 1) ? 'R- ' : 'C- ';
                            $('#plot_id').append('<option value="' + plot.plot_id + '">' +
                                plotType + ' ' + plot.name + '  </option>');
                        });
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

            $('#e_projects_id').change(function() {
                var e_projectID = $(this).val();

                console.log(e_projectID);
                if (e_projectID) {
                    $.ajax({
                        url: '{{ route('get_account') }}', // Replace with your actual route
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: e_projectID,
                            action: 'get_head',
                            _token: '{{ csrf_token() }}' // Include CSRF token for Laravel
                        },
                        success: function(data) {
                            $('#e_accounts_id').empty();
                            $('#e_subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var e_filteredData = data.filter(function(item) {
                                return item.project_id == e_projectID;
                            });

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $('#e_accounts_id').append(
                                '<option value="">Select an option</option>');
                            $.each(e_filteredData, function(key, value) {
                                $('#e_accounts_id').append('<option value="' + value
                                    .head_accounting_id + '">' + value
                                    .head_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#e_accounts_id').empty();
                    $('#e_subaccounts_id').empty();
                }
            });

            $('#e_accounts_id').change(function() {
                var accountID = $(this).val();
                var projectID = $('#e_projects_id').val();


                if (accountID) {
                    // Implement AJAX call to fetch subheadaccounts based on the selected account
                    $.ajax({
                        url: '{{ route('get_account') }}', // Replace with your actual route
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: projectID,
                            accountID: accountID,
                            action: 'get_child',

                            _token: '{{ csrf_token() }}' // Include CSRF token for Laravel
                        },
                        success: function(data) {
                            $('#e_subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var filteredData = data.filter(function(item) {
                                return item.head_accounting_id == accountID;
                            });
                            $('#e_subaccounts_id').append(
                                '<option value="">Select an option</option>');

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $.each(filteredData, function(key, value) {
                                $('#e_subaccounts_id').append('<option value="' + value
                                    .subhead_accounting_id + '">' + value
                                    .subhead_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#e_subaccounts_id').empty();
                }
            });





            $(document).on("click", '.btn-edit', function() {
                debugger;
                let id = $(this).attr("data-id");
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
                        debugger;
                        var data = data.data;
                        $("#id").val(data.id);
                        $("#reference").val(data.reference);
                        $('#e_projects_id').val(data.project_head_subhead.project_id).trigger(
                            'change');
                        // $('#e_accounts_id').val(data.project_head_subhead.head_accounting_id).trigger('change');

                        // Set the default date in the date input field using flatpickr
                        flatpickr('#date', {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                            defaultDate: data
                                .date // Set the fetched date as the default date
                        });

                        $("#voucher").val(data.type + '-0000' + data.type_id);

                        if (data.type == 'CR') {
                            $("#amount").val(data.amount_in);

                        } else if (data.type == 'CP') {
                            $("#amount").val(data.amount_out);

                        }

                        $("#detail").val(data.detail);
                        console.log(data.detail);

                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
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


        });

        $(document).ready(function() {


            // Other code...
        });

        function convertToWords() {
            // Get the value entered in the input field
            var numberInput = document.getElementById('numberInput').value;

            // Remove commas for thousands separator
            var numericValue = numberInput.replace(/,/g, '');

            // Convert the entered number to English wording
            var wordingAmount = numberToWords.toWords(numericValue);

            // Display the English wording amount in the container
            document.getElementById('wordingAmount').innerText = wordingAmount;
        }

        function formatAmount(input) {
            // Remove non-numeric characters and leading zeros
            let value = input.value.replace(/[^0-9]/g, '').replace(/^0+/, '');

            // Add commas for thousands separator
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Update the input value
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
                                            <label class="fbox">Voucher No.</label>
                                            <div class="input-group">
                                                <input type="text" value="1" name="action" hidden />
                                                <input id="voucher" type="text" class="form-control "
                                                    name="voucher" autocomplete="off" readonly>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Reference</label>
                                            <div class="input-group">

                                                <input id="reference" type="text"
                                                    class="form-control @error('reference') is-invalid @enderror"
                                                    name="reference" value="{{ old('reference') }}" autocomplete="off">
                                                @error('reference')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Date</label>
                                            <div class="input-group">
                                                <input type="text" id="date" name="date"
                                                    class="date_database form-control" data-input>
                                                <!-- Add a hidden input to store the selected date in a format you want -->
                                                {{-- <input type="hidden" id="hiddenDate" name="hiddenDate"> --}}
                                                {{-- <input type="text" id="datepicker" class="form-control @error('date') is-invalid @enderror" name="date" value="{{ old('date') ?: date('d-m-yy') }}" autocomplete="off"> --}}
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
                            <div class="col-sm-6">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Projects</label>
                                            <div class="input-group">
                                                <select class="form-control select2" name="projects_id"
                                                    id="e_projects_id">
                                                    <option value="">Select an option</option>

                                                    @foreach ($projects as $v)
                                                        <option value="{{ $v->id }}">{{ $v->project }}</option>
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
                                                <select class="form-control select2" name="accounts_id"
                                                    id="e_accounts_id">
                                                    <option value="">Select an option</option>

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
                            <div class="col-sm-6">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Party Account</label>
                                            <div class="input-group">
                                                <select class="form-control select2" name="subaccounts_id"
                                                    id="e_subaccounts_id">
                                                    <option value="">Select an option</option>
                                                </select>
                                                @error('subaccounts_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Amount</label>
                                            <div class="input-group">
                                                <input id="amount" oninput="formatAmount(this)" type="text"
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
                                        <textarea id="detail" class="form-control @error('detail') is-invalid @enderror" placeholder="Detail"
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
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
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
                        <input type="hidden" name="id" id="did">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger">Yes</button>
                </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
@endsection
