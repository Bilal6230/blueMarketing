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
                    <div class="col-12">
                        <div class="custom_card">
                            <div class="card-body table-responsive">
                                <table id="your-datatable-id" class="table table-striped table-bordered small-rows" style="width:100%">
                                    <thead>
                                        <tr>
                                           
                                            <th>Head Account</th>
                                            
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
                                        <tr id="page">
                                            <th style="text-align:right; color:red">Page Total:</th>
                                            <th id="total-in"></th>
                                            <th id="total-out"></th>
                                            <th id="total-balance"></th>
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
    $(document).ready(function() {
        var table = $('#your-datatable-id').DataTable({
            pageLength: 20, // Show 20 records per page
            lengthMenu: [[5,10,20, 50, 100, -1], [5,10,20, 50, 100, "All"]], // Dropdown options
            ajax: {
                url: "{{ route('fetch_data_by_head') }}",
                type: "POST",
                data: { _token: "{{ csrf_token() }}" }
            },
            columns: [
                { data: 'head_account_name', name: 'project_head_subheads.headAccounting.name', orderable: false },
                { data: 'total_amount_in', name: 'total_amount_in', orderable: false },
                { data: 'total_amount_out', name: 'total_amount_out', orderable: false },
                { data: 'balance_amount', name: 'balance_amount', orderable: false },
            ],
            createdRow: function(row, data, dataIndex) {
                changeColumnTextColor(row, data, 'total_amount_in', 'green');
                changeColumnTextColor(row, data, 'total_amount_out', 'red');
                changeColumnTextColor(row, data, 'balance_amount', 'red');
            },
            dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: 'Copy', className: 'btn btn-info-light' },
                { extend: 'csv', text: 'CSV', className: 'btn btn-primary-light' },
                { extend: 'excel', text: 'Excel', className: 'btn btn-success-light' },
                { extend: 'pdfHtml5', text: 'PDF', className: 'btn btn-danger-light', footer: true }, // Enable footer
                { extend: 'print', text: 'Print', className: 'btn btn-info', footer: true }, // Enable footer
                { extend: 'pageLength', className: 'btn btn-info' },
                { extend: 'colvis', className: 'btn btn-info' },
            ],
            footerCallback: function(row, data, start, end, display) {
                var api = this.api();

                var sumColumn = function(index) {
                    return api.column(index, { page: 'current' }).data().reduce(function(a, b) {
                        return (parseFloat(a) || 0) + (parseFloat(b) || 0);
                    }, 0);
                };

                var totalIn = sumColumn(1);
                var totalOut = sumColumn(2);
                var totalBalance = sumColumn(3);

                $(api.column(1).footer()).html(totalIn.toLocaleString());
                $(api.column(2).footer()).html(totalOut.toLocaleString());
                $(api.column(3).footer()).html(totalBalance.toLocaleString());
            }
        });
    });



    // Function to change text color of a specific column by name
    function changeColumnTextColor(row, data, columnName, color) {
        var columnIndex = getColumnIndex(columnName);

        if (columnIndex !== null) {
            var cell = $('td', row).eq(columnIndex);
            var value = parseFloat(data[columnName]);

            if (value < 0) {
                cell.css('color', 'red');
            } else if (value > 0) {
                cell.css('color', 'green');
            } else {
                cell.css('color', 'black');
            }
        }
    }

    // Function to get the column index by name
    function getColumnIndex(columnName) {
        var table = $('#your-datatable-id').DataTable();
        var columnIndex = null;

        $.each(table.settings().init().columns, function(index, column) {
            if (column.name === columnName) {
                columnIndex = index;
                return false; // Break the loop
            }
        });

        return columnIndex;
    }



</script>
@endsection
