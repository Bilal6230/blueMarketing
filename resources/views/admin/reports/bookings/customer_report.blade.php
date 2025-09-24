@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>{{ $title }}</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div><!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Customer Report  </h3>
                            </div><!-- /.card-header -->

                            <div class="card-body">

                                <form method="POST" action="{{ route('booking.customer.report.display') }}">
                                    @csrf
                                    {{-- <input type="text" name='booking_id' value="{{ $data->id }}" hidden required>
                                    <input type="text" name='total_price' value="{{ $data->total_price }}" hidden> --}}
                                    <div class="row">
                                        <div class="col-md-12">

                                        </div>
                                    </div>
                                    <div class="row">


                                        {{-- <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="start_date">From Date</label>
                                                <div class="input-group">
                                                    <input type="text" name="start_date" class="date form-control" data-input>
                                                    @error('start_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="start_date">To Date</label>
                                                <div class="input-group">
                                                    <input type="text" name="start_date" class="date form-control" data-input>
                                                    @error('start_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div> --}}


                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="customer">Customer</label>
                                                <select class="form-control select2" name="customer_id" id="customer_id">
                                                    <option value="">Select Customer</option>
                                                    @foreach ($customers as $v)
                                                        <option value="{{ $v->id }}">{{ $v->first_name }} {{ $v->last_name }} {{ $v->relate }} {{ $v->father_name }} - {{ $v->phone_number }}</option>
                                                    @endforeach
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

                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="action">Report Type</label>
                                                <select class="form-control select2" name="action" id="action">
                                                    <option value=""> Select Report </option>
                                                    <option value="booking_file"> Booking File </option>
                                                    {{-- <option value="customer_report"> Customer Report </option>
                                                    <option value="recovery_report"> Customer Recovery Report </option> --}}
                                                    <option value="recovery_report_details"> Customer Recovery Report </option>
                                                    <option value="customer_ledger">Customer Ledger</option>

                                                </select>
                                                @error('action')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>



                                    </div>
                                    <hr>

                                    <button type="submit" class="btn btn-danger">View</button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>


    </div><!-- /.content-wrapper -->
@endsection

@section('js')

<script>
    // Define a JavaScript variable to hold installment options
    $(document).ready(function () {
        // fetchCustomers('{{ getSelectedTown() }}');
        // function fetchCustomers(projectId) {
        //     $.ajax({
        //         url: '/admin/get-customers-byplot', // URL to your route
        //         type: 'POST', // Use POST method for sending data
        //         data: {
        //             _token: '{{ csrf_token() }}', // Add CSRF token
        //             project_id: projectId // Pass project ID to server
        //         },
        //         success: function(data) {
        //             // Populate customer dropdown with retrieved data
        //             $('#customer_id').empty();
        //             $('#customer_id').append('<option value="">Select customer</option>');
        //             $.each(data, function(key, customer) {
        //                 $('#customer_id').append('<option value="' + customer.id + '">' + customer.first_name + ' ' + customer.last_name +' '+ customer.relate + ' '+ customer.father_name + ' - ' + customer.phone_number + '</option>');
        //             });
        //         }
        //     });
        // }

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
                        // let customerSelect = $('#customer_id')[0].tomselect;
                        // customerSelect.setValue(customerId, true);
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
    });
</script>

@endsection
