@extends('admin.layouts.master')

@section('content')
    @php
        $showActions = auth()->user()->canany(['edit jv', 'delete jv', 'print jv']);
    @endphp
    <div class="content-wrapper">
        <div class="content">
            <div class="container-fluid mt-1">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">{{ $title }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row {{ $type == 'SV' ? 'd-none' : '' }}">
                            <a href="{{ route('journal.voucher.create') }}" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add New Voucher
                            </a>
                        </div>

                        <div id="filtersSection" class="row mb-3">
                            <div class="col-md-3">
                                <label for="filter_voucher_number">Voucher Number</label>
                                <input type="text" id="filter_voucher_number" class="form-control"
                                    placeholder="Voucher Number">
                            </div>

                            <div class="col-md-3">
                                <label for="filter_date">Date</label>
                                <input type="text" id="filter_date" class="form-control date" placeholder="YYYY-MM-DD">
                            </div>

                            <div class="col-md-3">
                                <label for="filter_amount_min">Amount Min</label>
                                <input type="number" step="0.01" id="filter_amount_min" class="form-control"
                                    placeholder="Min Amount">
                            </div>

                            <div class="col-md-3">
                                <label for="filter_amount_max">Amount Max</label>
                                <input type="number" step="0.01" id="filter_amount_max" class="form-control"
                                    placeholder="Max Amount">
                            </div>

                            <div class="col-md-3">
                                <label for="filter_reference">Reference</label>
                                <input type="text" id="filter_reference" class="form-control" placeholder="Reference">
                            </div>

                            <div class="col-md-3" style="margin-top:2.2rem ">
                                <button id="applyFilters" class="btn btn-primary btn-sm">Apply Filters</button>
                                <button id="resetFilters" class="btn btn-secondary btn-sm">Reset</button>
                            </div>
                        </div>

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
                                    @if ($showActions)
                                        <th style="background-color:black">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
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
        const voucherDataUrl = @json($type === 'SV' ? route('sales.voucher.index') : route('journal.voucher.index'));
        const showActions = @json($showActions);
        const isSalesVoucherPage = @json($type === 'SV');
        let journalTable;

        $(document).ready(function() {
            if (isSalesVoucherPage) {
                $('#filter_date').val('');
            }

            journalTable = renderDataTable();

            $('#applyFilters').on('click', function() {
                journalTable.ajax.reload();
            });

            $('#resetFilters').on('click', function() {
                $('#filter_voucher_number').val('');
                $('#filter_date').val('');
                $('#filter_amount_min').val('');
                $('#filter_amount_max').val('');
                $('#filter_reference').val('');
                journalTable.search('').ajax.reload();
            });

            $('#filter_date, #filter_voucher_number, #filter_reference, #filter_amount_min, #filter_amount_max').on('change', function() {
                journalTable.ajax.reload();
            });
        });

        $(document).on('click', '.delete-btn', function() {
            const id = $(this).data('id');
            const deleteUrl = `{{ route('journal.voucher.delete', ':id') }}`.replace(':id', id);
            $('#deleteForm').attr('action', deleteUrl);
            $('#deleteModal').modal('show');
        });

        function renderDataTable() {
            if ($.fn.DataTable.isDataTable('#journalTable')) {
                $('#journalTable').DataTable().destroy();
            }

            return $('#journalTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: voucherDataUrl,
                    type: 'GET',
                    data: function(d) {
                        d.filter_voucher_number = $('#filter_voucher_number').val();
                        d.filter_amount_min = $('#filter_amount_min').val();
                        d.filter_amount_max = $('#filter_amount_max').val();
                        d.filter_reference = $('#filter_reference').val();

                        const filterDate = $('#filter_date').val();
                        if (filterDate) {
                            d.filter_date = filterDate;
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
                    }
                    @if ($showActions)
                    , {
                        data: 'actions',
                        orderable: false,
                        searchable: false
                    }
                    @endif
                ]
            });
        }
    </script>
@endsection
