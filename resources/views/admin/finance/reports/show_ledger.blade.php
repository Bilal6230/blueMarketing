@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
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
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="custom_card">
                            <div class="card-body">
                                <div class="card_header mb-3 d-flex align-items-center ">
                                    <i class="fa fa-filter mr-2"></i>
                                    <h4 class="mb-0"> Filter </h4>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 col-xl-3 mb-3 mb-md-0">
                                        <label for="startdate" class="form-label"> Start Date </label>
                                        <input type="date" class="form-control" name="startdate" id="startdate"
                                            placeholder="Start Date">
                                    </div>
                                    <div class="col-md-4 col-xl-3">
                                        <label for="enddate" class="form-label"> End Date </label>
                                        <input type="date" class="form-control" name="enddate" id="enddate"
                                            placeholder="End Date">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-12">
                        <div class="custom_card">
                            <div class="card-body table-responsive">
                                <table id="your-datatable-id" class="table table-striped table-bordered "
                                    style="width:100%">

                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Voucher Number</th>
                                            <th>Date</th>
                                            <th>Accountant</th>
                                            <th>Head Account</th>
                                            <th>Party Account</th>
                                            <th style="width: 5px">Ref</th>
                                            <th>Detail</th>
                                            <th>Cash In</th>
                                            <th>Cash Out</th>
                                            <th>Balance</th>

                                            <!-- Add more columns based on your data model -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Table body content -->
                                    </tbody>
                                    <tfoot>
                                        <tr id='page'>
                                            <th colspan="7" style="text-align:right; color:red">Page:</th>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>

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
        $(document).ready(function () {
            var lastbalanceAmount = 0;
            var table = $('#your-datatable-id').DataTable({
                ajax: {
                    url: "{{ route('fetch-data-url') }}",
                    type: "POST",
                    data: function (d) {
                        d._token = "{{ csrf_token() }}";
                        d.start_date = $('#startdate').val();
                        d.end_date = $('#enddate').val();
                    }
                },
                columns: [{
                    data: 'id',
                    name: 'id',
                    orderable: false
                },
                {
                    data: 'display_voucher_number',
                    name: 'display_voucher_number',
                    orderable: false
                },
                {
                    data: 'date',
                    name: 'date',
                    orderable: false
                },
                {
                    data: 'accountent_name',
                    name: 'accountent_name',
                    orderable: false
                },
                {
                    data: 'head_account_name',
                    name: 'project_head_subheads.headAccounting.name',
                    orderable: false
                },
                {
                    data: 'subhead_account_name',
                    name: 'project_head_subheads.subheadAccounting.name',
                    orderable: false
                },
                {
                    data: 'reference',
                    name: 'reference',
                    orderable: false
                },
                {
                    data: 'detail',
                    name: 'detail',
                    orderable: false
                },
                {
                    data: 'amount_in',
                    name: 'amount_in',
                    orderable: false
                },
                {
                    data: 'amount_out',
                    name: 'amount_out',
                    orderable: false
                },
                {
                    // New column for balance amount
                    data: null,
                    name: 'balance_amount',
                    render: function (data, type, row) {
                        var amountIn = parseFloat(row.amount_in) || 0;
                        var amountOut = parseFloat(row.amount_out) || 0;
                        var balanceAmount = (lastbalanceAmount + amountIn) - amountOut;

                        // Update last balance amount for the next row
                        lastbalanceAmount = balanceAmount;

                        return balanceAmount.toFixed(2);
                    }
                },
                    // Add more columns based on your data model
                ],
                createdRow: function (row, data, dataIndex) {
                    // Handle the text color change for 'head_account_name' column
                    changeColumnTextColor(row, data, 'amount_in', 'green');
                    changeColumnTextColor(row, data, 'amount_out', 'red');
                    changeColumnTextColor(row, data, 'balance_amount', 'red');
                },
                dom: 'Bfrtip', // Enable Buttons
                buttons: [{
                    extend: 'copy',
                    text: 'Copy',
                    className: 'btn btn-info-light'
                },
                {
                    extend: 'csv',
                    text: 'CSV',
                    className: 'btn btn-primary-light'
                },
                {
                    extend: 'excel',
                    text: 'Excel',
                    className: 'btn btn-success-light'
                },
                {
                    extend: 'pdf',
                    text: 'PDF',
                    className: 'btn btn-danger-light'
                },
                {
                    extend: 'print',
                    text: 'Print me!',
                    className: 'btn btn-info'
                },
                {
                    extend: 'pageLength',
                    className: 'btn btn-info'
                },
                {
                    extend: 'colvis',
                    className: 'btn btn-info'
                },
                ],
                columnDefs: [
                    // { targets: [3, 4], visible: false } // Indexes of columns to hide (0-indexed)
                ],
                initComplete: function () {
                    var table = this.api();

                    table.columns().every(function () {
                        var column = this;

                        // Skip adding input for the "ID" column
                        if (column.index() == 1 || column.index() == 5) {
                            var input = document.createElement("input");
                            $(input).addClass('form-control').appendTo($(column.header())).on('keyup change',
                                function () {
                                    column.search($(this).val()).draw();
                                }).css('width', '100px'); // Adjust the width as needed
                        }
                    });
                },
                footerCallback: function (row, data, start, end, display) {
                    var api = this.api();

                    // Calculate the sum of the "amount_in" column for the current page
                    var amountInSumPage = api.column(7, {
                        page: 'current'
                    }).data().reduce(function (acc, value) {
                        return acc + parseFloat(value);
                    }, 0);

                    // Calculate the sum of the "amount_out" column for the current page
                    var amountOutSumPage = api.column(8, {
                        page: 'current'
                    }).data().reduce(function (acc, value) {
                        return acc + parseFloat(value);
                    }, 0);

                    // Calculate the sum of the "amount_in" column for all pages
                    var amountInSumAll = api.column(7, {
                        search: 'applied'
                    }).data().reduce(function (acc, value) {
                        return acc + parseFloat(value);
                    }, 0);

                    // Calculate the sum of the "amount_out" column for all pages
                    var amountOutSumAll = api.column(8, {
                        search: 'applied'
                    }).data().reduce(function (acc, value) {
                        return acc + parseFloat(value);
                    }, 0);


                    // Set the sum in the footer
                    $(api.column(7, {
                        page: 'current'
                    }).footer()).html('Cash in: ' + amountInSumPage.toFixed(2));
                    $(api.column(8, {
                        page: 'current'
                    }).footer()).html('Cash out: ' + amountOutSumPage.toFixed(2));
                    var balance = amountInSumAll.toFixed(2) - amountOutSumAll.toFixed(2);

                    $(api.column(6).footer()).html('Total In: ' + amountInSumAll.toFixed(2) +
                        '<br> Total Out:' + amountOutSumAll.toFixed(2) + '<hr>Balance: ' + balance);
                }
            });

            $('#startdate, #enddate').on('change', function () {
                lastbalanceAmount = 0; // Reset running balance
                table.ajax.reload();
            });
        });

        // Function to change text color of a specific column by name
        function changeColumnTextColor(row, data, columnName, color) {
            var columnIndex = getColumnIndex(columnName);

            if (columnIndex !== null) {
                $('td', row).eq(columnIndex).css('color', color);
            }
        }

        // Function to get the column index by name
        function getColumnIndex(columnName) {
            var table = $('#your-datatable-id').DataTable();
            var columnIndex = null;

            $.each(table.settings().init().columns, function (index, column) {
                if (column.name === columnName) {
                    columnIndex = index;
                    return false; // Break the loop
                }
            });

            return columnIndex;
        }
    </script>
@endsection