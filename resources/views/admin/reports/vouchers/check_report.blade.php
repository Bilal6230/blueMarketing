@extends('admin.layouts.master')
@section('content')

    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            @can('cheque report')
                            <div class="card-header">
                                <h3 class="card-title">
                                </h3>
                                <form action="{{ route('report.check.post') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                <div class="row">


                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer">Customer</label>
                                            <select class="form-control select2" name="customer_id" id="customer_id">
                                                <option value="">Select Customer</option>
                                            </select>
                                            @error('customer_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="customer">Plot</label>
                                            <select class="form-control select2" name="plot_id" id="plot_id">
                                                <option value=""> < All Plot > </option>
                                            </select>
                                            @error('plot_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="customer">Status</label>
                                            <select class="form-control select2" name="passing_status" id="passing_status">
                                                <option value=""> < All > </option>
                                                @foreach (check_status() as $v)
                                                    <option value="{{ $v['id'] }}" {{ $old_passing_status == $v['id'] ? 'selected' : '' }}>{{ $v['name'] }}</option>
                                                @endforeach
                                            </select>
                                            @error('passing_status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>


                                </div>
                                <div class="row">

                                    <div class="col-sm-3" >
                                        <div class="input-group bank_group">
                                            <label class="fbox">Bank</label>
                                            <div class="input-group">
                                                <select class="form-control select2" name="bank_id" id="bank_id">
                                                    <option value="">All Banks</option>

                                                    @foreach (getPakistanBanks() as $v)
                                                        <option value="{{ $v['id'] }}" {{ $old_bank_id == $v['id'] ? 'selected' : '' }}>{{ $v['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-sm-3" >
                                        <div class="input-group bank_group">
                                            <label class="fbox">Type</label>
                                            <div class="input-group">
                                                <select class="form-control select2" name="bank_id" id="bank_id">
                                                    <option value="">Payment Type</option>


                                                </select>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">From Date</label>
                                            <div class="input-group">
                                                <input type="date" name="fdate" class=" form-control" data-input value="{{ old('fdate') }}">
                                                @error('fdate')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">To Date</label>
                                            <div class="input-group">
                                                <input type="date" name="tdate" class="form-control" data-input value="{{ old('tdate') }}">
                                                @error('tdate')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="input-group d-flex align-items-end">
                                            <button class="btn btn-primary" id="searchfilter">Filter</button>
                                        </div>
                                    </div>
                                </div>

                                </form>

                            </div>
                            @endcan
                            <!-- Total Sale Amount Card -->
                            <div class="col-md-4 " style="padding-top: 25px;">
                                <div class="card" style="background-color: #fd7e14; color: #fff; border-radius: 10px; ">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Sale Amount</h5>
                                        <p class="card-text">
                                            <strong>{{ Setting::formatAmount($totalAmountOut) }}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>


                            <!-- /.card-header -->
                            <div class="card-body table-responsive">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Customer</th>
                                            <th>Phone</th>
                                            <th>Plot</th>
                                            <th>Type</th>
                                            <th>Transaction Type</th>
                                            <th>Bank</th>
                                            <th>Cheque No.</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Note</th>
                                            @canany(['pass cheque'])
                                                <th>Action</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    {{ $i->customer_list->first_name }} {{ $i->customer_list->last_name }}
                                                </td>
                                                <td>{{ $i->customer_list->phone_number }} </td>
                                                <td>{{ Setting::getPlotTypeShort($i->plot_list->type) }}-{{ $i->plot_list->name }} </td>
                                                <td>
                                                    <span class="badge {{ getPaymentTypeDetails($i->payment_type)['badge'] }}" style="width: 80%">
                                                        {{ getPaymentTypeDetails($i->payment_type)['name'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $i->transaction_type ?? 'N/A' }}</td>
                                                <td>{{ getBankNameById($i->bank_id) }}</td>
                                                <td>
                                                    <a href="{{ route('admin.reports.check_history', ['id' => $i->id]) }}" target="_blank" class="btn btn-link">
                                                        {{ $i->t_number }}
                                                    </a>
                                                </td>
                                                <td>{{ Setting::formatAmount($i->amount_out) }}</td>

                                                <td>
                                                    <span class="badge {{ collect(check_status())->firstWhere('id', $i->passing_status)['badge'] }}" style="width: 80px">
                                                        {{ collect(check_status())->firstWhere('id', $i->passing_status)['name'] }}
                                                    </span>
                                                </td>

                                                <td>{{ Setting::getShortDate($i->passing_date) }}</td>
                                                <td>{{ $i->note }}</td>
                                                {{-- @canany(['pass cheque']) --}}
                                                    <td>
                                                        {{-- @if ($i->passing_status != 1) --}}
                                                            <div class="btn-group">
                                                                @can('pass cheque')
                                                                    <button class="btn btn-sm btn-primary btn-edit" data-id="{{ $i->id }}"><i class="fas fa-pencil-alt"></i></button>
                                                                @endcan

                                                            </div>
                                                        {{-- @endif --}}


                                                    </td>
                                                {{-- @endcanany --}}
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->


                    </div>
                    <!-- /.col -->
                </div>
                <!-- /.row -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection

@section('js')


    <script>
        // Define a JavaScript variable to hold installment options
        $(document).ready(function () {
            fetchCustomers('{{ getSelectedTown() }}');
            function fetchCustomers(projectId) {
                $.ajax({
                    url: '/admin/get-customers-byplot', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}', // Add CSRF token
                        project_id: projectId // Pass project ID to server
                    },
                    success: function(data) {
                        // Populate customer dropdown with retrieved data
                        $('#customer_id').empty();
                        $('#customer_id').append('<option value="">Select customer</option>');
                        $.each(data, function(key, customer) {
                            $('#customer_id').append('<option value="' + customer.id + '">' + customer.first_name + ' ' + customer.last_name +' '+ customer.relate + ' '+ customer.father_name + ' - ' + customer.phone_number + '</option>');
                        });
                    }
                });
            }

            $('#customer_id').change(function (e) {
                e.preventDefault();
                var customerId = $(this).val();

                if (customerId) {
                    fetchPlots(customerId);
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
                        // Populate customer dropdown with retrieved data
                        $('#plot_id').empty();
                        $('#plot_id').append('<option value="">Select Plots</option>');
                        $.each(data, function(key, plot) {
                            var plotType = (plot.type == 1) ? 'R- ' : 'C- ';
                            $('#plot_id').append('<option value="' + plot.plot_id + '">' + plotType + ' ' + plot.name +  '  </option>');
                        });
                    }
                });
            }

            $(document).on("click", '.btn-edit', function() {
                debugger;
                let id = $(this).attr("data-id");
                $('#modal-loading').modal({backdrop: 'static', keyboard: false, show: true});
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
                        console.log('data in the database  = ',data);
                        $("#id").val(data.id);
                        $("#reference").val(data.reference);
                        $('#e_projects_id').val(data.project_head_subhead.project_id).trigger('change');
                        // $('#e_accounts_id').val(data.project_head_subhead.head_accounting_id).trigger('change');

                        // Set the default date in the date input field using flatpickr
                        flatpickr('#date', {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                            defaultDate: data.date // Set the fetched date as the default date
                        });

                        $("#voucher").val(data.type+'-0000'+data.type_id);

                        if (data.type == 'CR') {
                            $("#amount").val(data.amount_in);

                        } else if(data.type == 'CP') {
                            $("#amount").val(data.amount_out);

                        }

                        $("#detail").val(data.detail);
                        console.log(data.detail);

                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({backdrop: 'static', keyboard: false, show: true});
                    },
                });
            });

            $('#e_accounts_id').change(function () {
                var accountID = $(this).val();
                var projectID = {{ getSelectedTown(); }};


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
                        success: function (data) {
                            $('#e_subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var filteredData = data.filter(function(item) {
                                return item.head_accounting_id  == accountID;
                            });
                            $('#e_subaccounts_id').append('<option value="">Select an option</option>');

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $.each(filteredData, function(key, value) {
                                $('#e_subaccounts_id').append('<option value="' + value.subhead_accounting_id + '">' + value.subhead_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#e_subaccounts_id').empty();
                }
            });
        });
    </script>
@endsection

@section('modal')

{{-- Modal Update --}}
<div class="modal fade" id="modal-edit">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header ">
                <h4 class="modal-title">Update Cheque</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('report.check.bank.posting') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method("PUT")
                    <div class="row">

                        <div class="col-sm-12">
                            <div class="row">

                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <label class="fbox">Voucher No.</label>
                                        <div class="input-group">
                                            <input type="text" value="1" name="action" hidden />
                                            <input type="text"  class="form-control " name="voucher" value="{{'BR'}}-{{get_new_voucher_number('BR')}}" autocomplete="off" readonly>

                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="customer">Status</label>
                                        <select class="form-control select2" name="passing_status" id="passing_status">
                                            <option value=""> All </option>
                                            @foreach (check_status() as $v)
                                                <option value="{{ $v['id'] }}" {{ $old_passing_status == $v['id'] ? 'selected' : '' }}>{{ $v['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('passing_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="input-group">
                                        <label class="fbox">Passing Date</label>
                                        <div class="input-group">
                                            <input type="text" id="date" name="bank_post_at" class="date_database form-control" data-input>
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
                        <div class="col-sm-12">
                            <div class="input-group">
                                <label class="fbox">Detail</label>
                                <div class="input-group">
                                    <textarea id="note" class="form-control @error('note') is-invalid @enderror" placeholder="Note" name="note" style=" height: 150px;" maxlength="255" >{{ old('note') }}</textarea>
                                    @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="fbox">Head</label>
                                <select class="form-control " name="accounts_id" id="e_accounts_id">
                                    <option value=""> Passing Account  </option>
                                    @foreach ($head_account_list as $head_account)
                                        <option value="{{ $head_account->head_accounting_id }}">
                                            {{ $head_account->headAccounting->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('accounts_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="input-group">
                                <label class="fbox">Party Account</label>
                                <div class="input-group">
                                    <select class="form-control select2" name="subaccounts_id" id="e_subaccounts_id">
                                        <option value="">Select an option</option>
                                    </select>
                                    @error('subaccounts_id')
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



@endsection
