@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper">
        <div class="content">
            <div class="container-fluid mt-1">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">{{ $title }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <a href="{{ route('journal.voucher.create') }}" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add New Voucher
                            </a>
                        </div>

                        <!-- Filter Section -->
                        <div id="filtersSection" class="row mb-3">
                            <!-- Voucher Number -->
                            <div class="col-md-3">
                                <label for="filter_voucher_number">Voucher Number</label>
                                <input type="text" id="filter_voucher_number" class="form-control"
                                    placeholder="Voucher Number">
                            </div>

                            <!-- Date -->
                            <div class="col-md-3">
                                <label for="filter_date">Date</label>
                                <input type="text" id="filter_date" class="form-control date" placeholder="YYYY-MM-DD">
                            </div>

                            <!-- Amount Min -->
                            <div class="col-md-3">
                                <label for="filter_amount_min">Amount Min</label>
                                <input type="number" step="0.01" id="filter_amount_min" class="form-control"
                                    placeholder="Min Amount">
                            </div>

                            <!-- Amount Max -->
                            <div class="col-md-3 ">
                                <label for="filter_amount_max">Amount Max</label>
                                <input type="number" step="0.01" id="filter_amount_max" class="form-control"
                                    placeholder="Max Amount">
                            </div>


                            <!-- Reference -->
                            <div class="col-md-3">
                                <label for="filter_reference">Reference</label>
                                <input type="text" id="filter_reference" class="form-control" placeholder="Reference">
                            </div>

                            <div class="col-md-3" style="margin-top:2.2rem ">
                                <button id="applyFilters" class="btn btn-primary btn-sm">Apply Filters</button>
                                <button id="resetFilters" class="btn btn-secondary btn-sm">Reset</button>
                            </div>
                        </div>

                        <!-- Table -->
                        <!-- ✅ Table (FIXED: only ONE table, no nesting) -->
                        <table id="journalTable" class="table table-bordered">
                            <colgroup>
                                <col style="width: 5%">
                                <col style="width: 8%">
                                <col style="width: 7%">
                                <col style="width: 7%">
                                <col style="width: 50%">
                                <col style="width: 8%">
                                <col style="width: 15%">
                            </colgroup>

                            <thead class="bg-primary text-white">
                                <tr>
                                    <th style="background-color:black">ID</th>
                                    <th style="background-color:black">Voucher Number</th>
                                    <th style="background-color:black">Reference</th>
                                    <th style="background-color:black">Date</th>
                                    <th style="background-color:black">Description</th>
                                    <th style="background-color:black">Amount</th>
                                    @canany(['edit jv', 'delete jv'])
                                        <th style="background-color:black">Actions</th>
                                    @endcanany
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($vouchers as $voucher)
                                    <tr>
                                        <td>{{ $voucher->id }}</td>
                                        <td>JV-{{ get_jv_number($voucher->voucher_number) }}</td>
                                        <td>{{ $voucher->reference }}</td>
                                        <td>{{ $voucher->date }}</td>
                                        <td>{{ $voucher->description }}</td>
                                        <td>{{ $voucher->total_debit }}</td>
                                        @canany(['edit jv', 'delete jv', 'print jv'])
                                            <td>
                                                @can('edit jv')
                                                    <a href="{{ route('journal.voucher.edit', $voucher->id) }}"
                                                        class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                @endcan
                                                @can('delete jv')
                                                    <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $voucher->id }}">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                @endcan
                                                @can('print jv')
                                                    <a href="{{ route('journal.voucher.print', $voucher->id) }}" target="_blank"
                                                        class="btn btn-sm btn-info">
                                                        <i class="fas fa-print"></i> Print
                                                    </a>
                                                @endcan
                                            </td>
                                        @endcanany
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this Journal Voucher?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        let isDateChanged = false;
        $(document).ready(function() {
            // Toggle filter section visibility
            $('#toggleFilters').on('click', function() {
                $('#filtersSection').toggle();
            });

            // Apply Filters
            $('#applyFilters').on('click', function() {
                renderDataTable();
            });

            // Reset Filters
            $('#resetFilters').on('click', function() {
                $('#filter_voucher_number').val('');
                $('#filter_date').val('');
                $('#filter_amount_min').val('');
                $('#filter_amount_max').val('');
                $('#filter_reference').val('');
                renderDataTable();
            });

            // Date filter: Track if the date is changed by the user
            const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD format
            $('#filter_date').val(today); // Default to today's date

            // When the user changes the date, mark it as changed
            $('#filter_date').on('change', function() {
                isDateChanged = true; // Date was changed by the user
                renderDataTable(); // Re-render table with updated filters
            });

            // Apply Filters (Voucher number, Amount, and Reference)
            // ✅ FIXED selector
            $('#filter_voucher_number, #filter_reference, #filter_amount_min, #filter_amount_max').on('change',
                function() {
                    renderDataTable();
                });
        });

        // Delete Button Click
        $(document).on('click', '.delete-btn', function() {
            const id = $(this).data('id');
            const deleteUrl = `{{ route('journal.voucher.delete', ':id') }}`.replace(':id', id);
            $('#deleteForm').attr('action', deleteUrl);
            $('#deleteModal').modal('show');
        });

        function renderDataTable() {
            const filter_voucher_number = $('#filter_voucher_number').val();
            const filter_reference = $('#filter_reference').val();
            const filter_amount_min = $('#filter_amount_min').val();
            const filter_amount_max = $('#filter_amount_max').val();
            const filter_date = $('#filter_date').val();

            // Destroy existing DataTable if it exists
            $('#journalTable').DataTable().destroy();

            // Initialize DataTable with AJAX
            $('#journalTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('journal.voucher.index') }}', // Route to get data
                    type: 'GET',
                    data: function(d) {
                        d.filter_voucher_number = filter_voucher_number;
                        d.filter_amount_min = filter_amount_min;
                        d.filter_amount_max = filter_amount_max;
                        d.filter_reference = filter_reference;
                        if (isDateChanged) {
                            d.filter_date = filter_date; // Send date filter if it has changed
                        }
                    },
                    dataSrc: 'data'
                },
                columns: [{
                        data: 'id',
                        name: 'id'
                    },
                    {
                        data: 'voucher_number',
                        name: 'voucher_number'
                    },
                    {
                        data: 'reference',
                        name: 'reference'
                    },
                    {
                        data: 'date',
                        name: 'date'
                    },
                    {
                        data: 'description',
                        name: 'description',
                        orderable: false
                    },
                    {
                        data: 'total_debit',
                        name: 'total_debit'
                    },
                    {
                        data: 'actions',
                        orderable: false,
                        searchable: false
                    }
                ]
            });
        }
    </script>
@endsection
