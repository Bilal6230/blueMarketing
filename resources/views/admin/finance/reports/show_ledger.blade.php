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
                        <div class="card">

                            <div class="card-body table-responsive">
                                <table id="your-datatable-id" class="table table-striped table-bordered "
                                    style="width:100%">
                                    <input type="date" name="startdate" id="startdate" placeholder="Start Date">
                                    <input type="date" name="enddate" id="enddate" placeholder="End Date">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Accountent</th>
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
                                            <th colspan="6" style="text-align:right; color:red">Page:</th>
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
        columns: [
            { data: 'id', name: 'id', orderable: false },
            { data: 'date', name: 'date', orderable: false },
            { data: 'accountent_name', name: 'createdBy.name', orderable: false },
            { data: 'head_account_name', name: 'project_head_subheads.headAccounting.name', orderable: false },
            { data: 'subhead_account_name', name: 'project_head_subheads.subheadAccounting.name', orderable: false },
            { data: 'reference', name: 'reference', orderable: false },
            { data: 'detail', name: 'detail', orderable: false },
            { data: 'amount_in', name: 'amount_in', orderable: false },
            { data: 'amount_out', name: 'amount_out', orderable: false },
            {
                data: null,
                name: 'balance_amount',
                render: function (data, type, row) {
                    var amountIn = parseFloat(row.amount_in) || 0;
                    var amountOut = parseFloat(row.amount_out) || 0;
                    var balanceAmount = (lastbalanceAmount + amountIn) - amountOut;
                    lastbalanceAmount = balanceAmount;
                    return balanceAmount.toFixed(2);
                }
            }
        ],
        createdRow: function (row, data) {
            changeColumnTextColor(row, data, 'amount_in', 'green');
            changeColumnTextColor(row, data, 'amount_out', 'red');
            changeColumnTextColor(row, data, 'balance_amount', 'red');
        },
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: 'Copy', className: 'btn btn-info-light' },
            { extend: 'csv', text: 'CSV', className: 'btn btn-primary-light' },
            { extend: 'excel', text: 'Excel', className: 'btn btn-success-light' },
            { extend: 'pdf', text: 'PDF', className: 'btn btn-danger-light' },
            { extend: 'print', text: 'Print me!', className: 'btn btn-info' },
            { extend: 'pageLength', className: 'btn btn-info' },
            { extend: 'colvis', className: 'btn btn-info' },
        ],
        initComplete: function () {
            var api = this.api();
            api.columns().every(function () {
                var column = this;
                if (column.index() == 1 || column.index() == 5) {
                    var input = document.createElement("input");
                    $(input).appendTo($(column.header())).on('keyup change', function () {
                        column.search($(this).val()).draw();
                    }).css('width', '150px');
                }
            });
        }
    });

    // Date pickers change event → reload DataTable
    $('#startdate, #enddate').on('change', function () {
        table.ajax.reload();
    });
});

// Function to change text color of a specific column
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
            return false;
        }
    });
    return columnIndex;
}

    </script>
@endsection
