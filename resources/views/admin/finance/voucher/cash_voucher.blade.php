@extends('admin.layouts.master')
@section('content')

    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <span class="{{$class}}">
                            {{ $title }}
                        </span>
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
                            @can('create voucher')
                                <div class="card-header">
                                    <div>
                                        <form action="{{ route('ledger.store') }}" method="POST" enctype="multipart/form-data" id="voucherForm">
                                            @csrf

                                            <div class="row">

                                                <div class="col-sm-12">
                                                    <div class="row">
                                                        <div class="col-sm-3">
                                                            <div class="input-group">
                                                                <label class="fbox">Voucher No.</label>
                                                                <div class="input-group">
                                                                    <input type="text" value="1" name="action" hidden /> 
                                                                    <input type="text"  class="form-control " name="voucher" value="{{$type}}-{{get_new_voucher_number($type)}}" autocomplete="off" readonly>
                                                                    
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-3">
                                                            <div class="input-group">
                                                                <label class="fbox">Reference</label>
                                                                <div class="input-group">
                                                                   
                                                                    <input type="text" class="form-control @error('reference') is-invalid @enderror" name="reference" value="{{ old('reference') }}" autocomplete="off">
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
                                                                    <input type="text" name="date" class="date form-control" data-input>
                                                                    <!-- Add a hidden input to store the selected date in a format you want -->
                                                                    <input type="hidden" id="hiddenDate" name="hiddenDate">
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
                                                                    <select class="form-control select2" name="projects_id" id="projects_id">
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
                                                                    <select class="form-control select2" name="accounts_id" id="accounts_id">
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
                                                                    <select class="form-control select2" name="subaccounts_id" id="subaccounts_id">
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
                                                                    <input id="numberInput" oninput="formatAmount(this)" type="text" class="form-control @error('amount') is-invalid @enderror" placeholder="Amount" name="amount" value="{{ old('amount') }}"  >
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

                                                <div class="col-sm-12">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <div class="input-group {{$bg_voucher}}">
                                                                <div class="input-group">
                                                                    <textarea class="form-control @error('detail') is-invalid @enderror" placeholder="Detail" name="detail" style=" height: 150px;" maxlength="255" >{{ old('detail') }}</textarea>
                                                                    @error('detail')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-6">
                                                            <div class="{{$bg_voucher}}">
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
                                                            <button type="button" class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#confirmModal">Save</button>


                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                            <!-- /.card-header -->
                            @can('read voucher')
                                <div class="card-body table-responsive">
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

                                                @canany(['update voucher','delete voucher'])
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
                                                    
                                                    
                                                    @canany(['update voucher','delete voucher'])
                                                        <td>
                                                            
                                                            <div class="btn-group">
                                                                @if ($i->type != 'BO')
                                                                    @can('update voucher')
                                                                        <button class="btn btn-sm btn-primary btn-edit" data-id="{{ $i->id }}"><i class="fas fa-pencil-alt"></i></button>
                                                                    @endcan
                                                                    @can('delete voucher')
                                                                        <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $i->id }}" data-name="{{ $i->name }}"><i class="fas fa-trash"></i></button>
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

            function submitForm() {
                document.getElementById('voucherForm').submit();
            }


        $(document).ready(function() {

            $('#subaccounts_id').change(function () {
                var subaccountId = $(this).val();

                if (subaccountId !== '') {
                    $.ajax({
                        url: '{{ route('get-subaccount-details') }}',
                        type: 'POST',
                        data: { 
                                subaccounts_id: subaccountId,
                                _token: '{{ csrf_token() }}' 
                        },
                        success: function (response) {
                            console.log(response); // Log the response to the console
                            // Update the UI based on the response
                            $('#cnic').text(response['cnic']);  // Access data using the keys
                            $('#phone').text(response['phone']);
                        },
                        error: function (error) {
                            console.log(error);
                        }
                    });
                }
            });

            
            $('#projects_id').change(function () {
                var projectID = $(this).val();

                console.log(projectID);
                if (projectID) {
                    $.ajax({
                        url: '{{ route('get_account') }}', // Replace with your actual route
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: projectID,
                            action: 'get_head',
                            _token: '{{ csrf_token() }}' // Include CSRF token for Laravel
                        },
                        success: function (data) {
                            $('#accounts_id').empty();
                            $('#subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var filteredData = data.filter(function(item) {
                                return item.project_id == projectID;
                            });

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $('#accounts_id').append('<option value="">Select an option</option>');
                            $.each(filteredData, function(key, value) {
                                $('#accounts_id').append('<option value="' + value.head_accounting_id + '">' + value.head_accounting.name + '</option>');
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
            $('#accounts_id').change(function () {
                var accountID = $(this).val();
                var projectID = $('#projects_id').val();


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
                            $('#subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var filteredData = data.filter(function(item) {
                                return item.head_accounting_id  == accountID;
                            });
                            $('#subaccounts_id').append('<option value="">Select an option</option>');

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $.each(filteredData, function(key, value) {
                                $('#subaccounts_id').append('<option value="' + value.subhead_accounting_id + '">' + value.subhead_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#subaccounts_id').empty();
                }
            });

            $('#e_projects_id').change(function () {
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
                        success: function (data) {
                            $('#e_accounts_id').empty();
                            $('#e_subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var e_filteredData = data.filter(function(item) {
                                return item.project_id == e_projectID;
                            });

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $('#e_accounts_id').append('<option value="">Select an option</option>');
                            $.each(e_filteredData, function(key, value) {
                                $('#e_accounts_id').append('<option value="' + value.head_accounting_id + '">' + value.head_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#e_accounts_id').empty();
                    $('#e_subaccounts_id').empty();
                }
            });

            $('#e_accounts_id').change(function () {
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

            $(document).on("click", '.btn-delete', function() {
                let id = $(this).attr("data-id");
                let name = $(this).attr("data-name");
                $("#did").val(id);
                $('#modal-delete').modal({backdrop: 'static', keyboard: false, show: true});
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
                <div class="modal-header {{$class}}">
                    <h4 class="modal-title">Edit Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('ledger.update') }}" method="POST" enctype="multipart/form-data">
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
                                                <input id="voucher" type="text"  class="form-control " name="voucher" autocomplete="off" readonly >
                                                
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Reference</label>
                                            <div class="input-group">
                                               
                                                <input id="reference" type="text" class="form-control @error('reference') is-invalid @enderror" name="reference" value="{{ old('reference') }}" autocomplete="off">
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
                                                <input type="text" id="date" name="date" class="date_database form-control" data-input>
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
                                                <select class="form-control select2" name="projects_id" id="e_projects_id">
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
                                                <select class="form-control select2" name="accounts_id" id="e_accounts_id">
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
                                                <select class="form-control select2" name="subaccounts_id" id="e_subaccounts_id">
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
                                                <input id="amount" oninput="formatAmount(this)" type="text" class="form-control @error('amount') is-invalid @enderror" placeholder="Amount" name="amount" value="{{ old('amount') }}"  >
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
                                        <textarea id="detail" class="form-control @error('detail') is-invalid @enderror" placeholder="Detail" name="detail" style=" height: 150px;" maxlength="255" >{{ old('detail') }}</textarea>
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
