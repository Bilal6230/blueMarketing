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
                                <table id="your-datatable-id" class="table table-striped table-bordered small-rows" style="width:100%">
                                    <thead>
                                        <tr>
                                           
                                            <th>Project</th>
                                            <th>Head Account</th>
                                            <th>Party Account</th>
                                            
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
                                            <th colspan="3" style="text-align:right; color:red">Page:</th>
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
    $(document).ready(function() {
        var lastbalanceAmount = 0;
        $('#your-datatable-id').DataTable({
            ajax: {
                url: "{{ route('fetch-data_by_party') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}"
                }
            },
            columns: [
               
                { data: 'project_name', name: 'project_head_subheads.project.project' ,orderable: false  },
                { data: 'head_account_name', name: 'project_head_subheads.headAccounting.name' ,orderable: false  },
                { data: 'subhead_account_name', name: 'project_head_subheads.subheadAccounting.name' ,orderable: false },
               
                { data: 'total_amount_in', name: 'total_amount_in' , orderable: false },
                { data: 'total_amount_out', name: 'total_amount_out' ,orderable: false  },
                { data: 'balance_amount', name: 'balance_amount' ,orderable: false  },
                
                // Add more columns based on your data model
            ],
            createdRow: function(row, data, dataIndex) {
                // Handle the text color change for 'head_account_name' column
                changeColumnTextColor(row, data, 'total_amount_in', 'green');
                changeColumnTextColor(row, data, 'total_amount_out', 'red');
                changeColumnTextColor(row, data, 'balance_amount', 'red');
            },
            dom: 'Bfrtip', // Enable Buttons
            buttons: [
                {
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
            
            initComplete: function () {
                var table = this.api();

                table.columns().every(function () {
                    var column = this;

                    // Skip adding input for the "ID" column
                    if (column.index() !== 0) {
                        var input = document.createElement("input");
                        $(input).appendTo($(column.header())).on('keyup change', function () {
                            column.search($(this).val()).draw();
                        }).css('width', '150px'); // Adjust the width as needed
                    }
                });
            }
            

        });
    });

    // Function to change text color of a specific column by name
    function changeColumnTextColor(row, data, columnName, color) {
        var columnIndex = getColumnIndex(columnName);

        if (columnIndex !== null) {
            var cell = $('td', row).eq(columnIndex);
            var value = parseFloat(data[columnName]);

            // Check the value and set the color accordingly
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
