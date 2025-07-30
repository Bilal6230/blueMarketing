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
                                <h3 class="card-title">Booking Form </h3>
                            </div><!-- /.card-header -->

                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Display customer details here -->
                                        <p><strong>Name:</strong> {{ $data->customer->first_name }} {{ $data->customer->last_name }} {{ $data->customer->relate }}  {{ $data->customer->father_name }}</p>
                                        <p><strong>Phone:</strong> {{ $data->customer->phone_number }}</p>
                                        <p><strong>CNIC:</strong> {{ $data->customer->cnic }}</p>
                                        <p><strong>Plot Number:</strong> {{ $data->plot->name }}</p>
                                        <p><strong>Sale Rate:</strong> {{ $data->plot_rate }}</p>
                                        <!-- Add more customer details here -->
                                    </div>
                                    <div class="col-md-6">
                                        <!-- Display customer details here -->
                                        <p><strong>CNIC:</strong> {{ $data->customer->nic_number }}</p>
                                        <p><strong>Mobile:</strong> {{ $data->customer->mobile_number }}</p>
                                        <p><strong>Address:</strong> {{ $data->customer->office_address }}</p>
                                        <p><strong>Plot Size:</strong> {{ $data->plot_size }}</p>
                                        <p><strong>Total Amount:</strong> {{ $data->total_price }}</p>
                                        <!-- Add more customer details here -->
                                    </div>

                                    

                                </div>
                                
                                
                                <form method="POST" action="{{ route('payment_schedule.store') }}">
                                    @csrf
                                    <input type="text" name='booking_id' value="{{ $data->id }}" hidden required>
                                    <input type="text" name='total_price' value="{{ $data->total_price }}" hidden>
                                    <div class="row">
                                        <div class="col-md-12">

                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="instalment">Total Instalment</label>
                                                <input type="number" class="form-control" id="instalment" name="instalment" placeholder="Total Number of instalment" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="payment_plan">Plan</label>
                                                <select class="form-control select2" name="payment_plan" id="payment_plan">
                                                    <option value="1">Monthly</option>
                                                    <option value="3">Quarterly</option>
                                                    <option value="6">Semi-annually</option>
                                                    <option value="12">Annually</option>

                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="start_date">Start Date</label>
                                                <div class="input-group">
                                                    <input type="text" name="start_date" class="date form-control" data-input>
                                                    @error('start_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <hr>
                                    <table id="payment_schedule_table">
                                        <thead>
                                            <tr>
                                                <th>Installment</th>
                                                <th>Amount</th>
                                                <th>Due Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <select class="form-control select2" name="installment_number[]" required>
                                                        @foreach (getInstallmentOptions() as $key => $value)
                                                            <option value="{{ $key }}">{{ $value }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="number" name="amount[]" step="0.01" required class="form-control"></td>
                                                <td><input type="date" name="due_date[]" required class="date form-control" data-input></td>
                                                <td><button type="button" class="btn btn-danger remove-row">Remove</button></td>

                                            </tr>
                                        </tbody>
                                    </table>
                                    <button type="button" onclick="addRow()" class="btn btn-danger">Add Row</button>
                                    <button type="submit" class="btn btn-danger">Save</button>
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
    var installmentOptions = [
        @foreach (getInstallmentOptions() as $key => $value)
            { value: '{{ $key }}', text: '{{ $value }}' },
        @endforeach
    ];

    function addRow() {
        var table = document.getElementById("payment_schedule_table");
        var newRow = table.insertRow();

        var cell1 = newRow.insertCell(0);
        var cell2 = newRow.insertCell(1);
        var cell3 = newRow.insertCell(2);
        var cell4 = newRow.insertCell(3);

        // Populate installment number dropdown
        var selectHTML = '<select class="form-control select2" name="installment_number[]" required>';
            for (var i = 0; i < installmentOptions.length; i++) {
            selectHTML += '<option value="' + installmentOptions[i].value + '">' + installmentOptions[i].text + '</option>';
        }
        
        selectHTML += '</select>';
        cell1.innerHTML = selectHTML;

        // Other cells remain the same
        cell2.innerHTML = '<input type="number" name="amount[]" step="0.01" required class="form-control">';
        cell3.innerHTML = '<input type="date" name="due_date[]" required class="date form-control" data-input >';
        cell4.innerHTML = '<td><button type="button" class="btn btn-danger remove-row">Remove</button></td>';
    }

    // Remove row
    $(document).on('click', '.remove-row', function() {
            $(this).closest('tr').remove();
        });
</script>

@endsection
