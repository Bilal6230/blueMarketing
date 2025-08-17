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
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold">Draft Voucher List</h5>
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
                                                    <th>Type</th>

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
                                                        <td>
                                                            @if ($i->type == 'CR')
                                                                <span class="text-success">Cash In</span>
                                                            @elseif($i->type == 'CP')
                                                                <span class="text-danger">Cash Out</span>
                                                            @endif
                                                        </td>


                                                        @canany(['update voucher', 'delete voucher'])
                                                            <td>

                                                                <div class="btn-group">
                                                                    @if ($i->type != 'BO')
                                                                        @can('update voucher')
                                                                            <button class="btn btn-sm btn-info btn-edit"
                                                                                vocherType="{{ $i->type }}"
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
                    allowEmptyOption: false,
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

        function saveAsLedger() {
            const form = document.getElementById('editForm');
            form.action = "{{ route('ledger.store') }}";
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



            $('#e_acct_type').change(function() {
                var acctType = $(this).val();

                // console.log(e_projectID);
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
                            // $('#e_accounts_id').empty();
                            // $('#e_subaccounts_id').empty();
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
                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    if (tsAccounts) {
                        tsAccounts.setValue('', true);
                        tsAccounts.clearOptions(); // also clear subaccounts
                    }
                    if (tsSubAccounts) {
                        tsSubAccounts.setValue('', true);
                        tsSubAccounts.clearOptions(); // also clear subaccounts
                    }
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
                    $('#subaccounts_id')[0].tomselect.clearOptions();
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
                            console.log(headId, acct_type);
                        },
                        error: function(error) {
                            console.log(error);
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
                    }
                });
            }

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
            $('#e_payment_type').change(function(e) {

                e.preventDefault();

                if ($(this).val() != '1') {
                    $('.bank_group').css('display', 'block');
                } else {

                    $('.bank_group').css('display', 'none');
                }
            });

            $(document).on("click", '.btn-edit', function() {
                let id = $(this).attr("data-id");
                $('#id').val(id);
                let vocherType = $(this).attr("vocherType");
                if (vocherType == 'CP') {
                    $('.pass-class').removeClass('cash-in');
                    $('.pass-class').addClass('cash-out');
                } else if (vocherType == 'CR') {
                    $('.pass-class').removeClass('cash-out');
                    $('.pass-class').addClass('cash-in');
                }
                $('#modal-loading').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
                $.ajax({
                    url: "{{ route('draft.ledger.show') }}",
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

                        if (data.type == 'CR') {
                            $("#e_amount").val(data.amount_in);

                        } else if (data.type == 'CP') {
                            $("#e_amount").val(data.amount_out);

                        }

                        $("#e_detail").val(data.detail);
                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                        let acct_type = data?.project_head_subhead?.head_accounting?.acct_type;
                        let eAccTypeSelect = $('#e_acct_type')[0].tomselect;
                        eAccTypeSelect.setValue(acct_type, true);


                        let headId = data?.project_head_subhead?.head_accounting?.id;
                        let eAccSelect = $('#e_accounts_id')[0].tomselect;
                        eAccSelect.setValue(headId, true);
                        let subheadId = data?.project_head_subhead?.subhead_accounting?.id;
                        let eSubAccSelect = $('#e_subaccounts_id')[0].tomselect;
                        eSubAccSelect.setValue(subheadId, true);

                        let customerId = data.customer_id;
                        let customerSelect = $('#e_customer_id')[0].tomselect;
                        customerSelect.setValue(customerId, true);

                        let plotId = data.plot_id;
                        let plotSelect = $('#e_plot_id')[0].tomselect;
                        plotSelect.setValue(plotId, true);

                        let paymentType = data.payment_type;
                        let paymentTypeSelect = $('#e_payment_type')[0].tomselect;
                        paymentTypeSelect.setValue(paymentType, true);
                        if (paymentType == '1' || paymentType == null || paymentType == '') {
                            $('.bank_group').css('display', 'none');
                        } else if (paymentType != '1') {
                            $('.bank_group').css('display', 'block');
                        }
                        let bank_id = data.bank_id;
                        let bankSelect = $('#e_bank_id')[0].tomselect;
                        bankSelect.setValue(bank_id, true);

                        let t_number = data.t_number;
                        $('#e_t_number').val(t_number);

                        let passing_date = data.passing_date;
                        $('#e_passing_date').val(passing_date);
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
    <div class="modal fade"  tabindex="-1" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h4 class="modal-title">Edit Voucher</h4>
                      <button type="button" class="close text-white" data-dismiss="modal"
                                        aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('ledger.save_as_draft') }}" method="POST" enctype="multipart/form-data"
                        id="editForm">
                        @csrf
                        <div class="row">
                            <div class="col-sm-12 mb-2">
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
                                                <input id="e_voucher" type="text" class="form-control " name="voucher"
                                                    autocomplete="off" readonly>

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
                            <div class="col-sm-12 mb-2">
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
                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="mb-3 col-sm-12">
                                        <div id="wordingAmount"></div>
                                    </div>
                                    <div class="mb-3 col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Customer</label>
                                            <div class="input-group">
                                                <select class="js-tomselect" name="customer_id" id="e_customer_id">
                                                    <option value=""></option>
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
                                                <select class="js-tomselect" placeholder=" " name="payment_type" id="e_payment_type">
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
                                        <div class="input-group bank_group" style="display: none">
                                            <label class="fbox">Bank</label>
                                            <div class="input-group">
                                                <select class="js-tomselect" name="bank_id" id="e_bank_id">
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
                                                <input type="text" id="e_passing_date" name="passing_date"
                                                    class="date form-control" data-input>
                                                @error('passing_date')
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
                            <div class="update-buttons">
                                <button type="submit" class="btn btn-success">Update</button>
                                <button type="submit" class="btn btn-primary" onclick="saveAsLedger()">Save as
                                    Voucher</button>
                            </div>
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
                    <form action="{{ route('draft.ledger.destroy') }}" method="POST" enctype="multipart/form-data">
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
        </div>
    </div>
@endsection
