@extends('admin.layouts.master')

@section('content')
<style>
/* CV retains its existing blue identity while sharing CR/DR density. */
.cv-directory{--voucher-accent:#087cfa;--voucher-tint:#e7efff}
.cv-directory .cv-shell{background:transparent!important;box-shadow:none!important}
.cv-directory .cv-shell>.card-header{background:#007bff!important;color:#fff!important;border-bottom:2px solid #087cfa;padding:12px 15px}
.cv-directory .cv-shell>.card-header h4{font-size:19px;font-weight:700;margin:0}
.cv-directory .cv-shell>.card-body{padding:10px 0}
.cv-directory #filtersSection .row{row-gap:8px;align-items:end}
.cv-directory #filtersSection .col-md-3{margin:0}
.cv-directory #filtersSection label{font-size:12px;font-weight:700;margin-bottom:3px}
.cv-directory #filtersSection .btn-sm{font-size:12px}
.cv-directory .bm-voucher-table-card{overflow:hidden;padding:8px}
.cv-directory #journalTable{width:100%!important;margin:0}
.cv-directory #journalTable thead th{background:#e7efff!important;border-bottom:2px solid #087cfa!important;color:#21334e!important;font-size:12px}
.cv-directory .dataTables_wrapper .row{margin-left:0;margin-right:0}
.cv-directory .dataTables_wrapper .dataTables_info,.cv-directory .dataTables_wrapper .dataTables_paginate{padding:8px 0;font-size:12px}
.cv-directory .blue-actions-toggle{color:#087cfa}
</style>
    <div class="content-wrapper bm-ui bm-voucher-directory cv-directory" style="--voucher-accent:#087cfa;--voucher-tint:#e7efff">
        <div class="content">
            <div class="container-fluid mt-1">
                <div class="card shadow-sm border-0 cv-shell">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0">{{ $title }}</h4><small>Selected project records · Commission voucher register</small></div>
                        <x-finance.voucher-directory-actions :approvals="true" />
                    </div>
                    <div class="card-body">


                        <!-- Filter Section -->
                        <x-finance.voucher-filter-card id="filtersSection" class="mb-3">
                        <div class="row">
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

                            <!-- Amount -->
                            <div class="col-md-3">
                                <label for="filter_amount">Amount</label>
                                <input type="number" id="filter_amount" class="form-control" placeholder="Amount">
                            </div>

                            <!-- Reference -->
                            <div class="col-md-3">
                                <label for="filter_reference">Reference</label>
                                <input type="text" id="filter_reference" class="form-control" placeholder="Reference">
                            </div>

                            <div class="col-md-3 mt-4">
                                <button id="applyFilters" class="btn btn-primary btn-sm">Apply Filters</button>
                                <button id="resetFilters" class="btn btn-secondary btn-sm">Reset</button>
                                <a href="{{ route('commision.voucher.create') }}" class="btn btn-primary btn-sm ml-1"><i class="fas fa-plus mr-1"></i> New voucher</a>
                            </div>
                        </div>
                        </x-finance.voucher-filter-card>

                        <!-- Table -->
                        <x-finance.voucher-table-card class="mb-3">
                        <div id="cv-list-error" class="alert alert-warning d-none" role="alert" aria-live="polite"></div>
                        <table id="journalTable" class="table table-bordered">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>ID</th>
                                    <th>Voucher Number</th>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    {{-- Keep the table schema stable for read-only and print-only users.
                                         Action buttons remain gated in actions.blade.php. --}}
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be dynamically loaded by AJAX -->
                            </tbody>
                        </table>
                        </x-finance.voucher-table-card>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    {{-- Reversal is a lifecycle write: keep the request key and the original
         DELETE route, but put the form fields inside the modal body. --}}
    <div class="modal fade fvc-modern-modal fvc-modal-blue" id="deleteModal"
         tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Reverse Commission Voucher</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \\Illuminate\\Support\\Str::uuid()) }}">
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            Reverse this posted Commission Voucher? Its original accounting
                            entries will remain in history.
                        </p>
                        <div class="form-group">
                            <label for="reversalDate" class="font-weight-bold">Reversal date *</label>
                            <input id="reversalDate" type="date" name="effective_date"
                                   class="form-control" value="{{ old('effective_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="form-group mb-0">
                            <label for="reversalReason" class="font-weight-bold">Reason *</label>
                            <textarea id="reversalReason" name="reason" rows="3"
                                      class="form-control" maxlength="200" required
                                      placeholder="Explain why this voucher is being reversed">{{ old('reason') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">Reverse voucher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        let isDateChanged = false;

        $(document).ready(function() {
            // Bind before the very first AJAX request so initial loading and
            // subsequent column-filter reloads share the same table skeleton.
            if ($.financeVoucherFeedback) {
                $.financeVoucherFeedback.bindTableSkeleton('#journalTable');
            }
            renderDataTable();

            // Toggle filter section visibility
            $('#toggleFilters').on('click', function() {
                $('#filtersSection').toggle();
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
            $('#filter_voucher_number, #filter_amount, #filter_reference').on('change', function() {
                renderDataTable();
            });

            // Reset Filters
            $('#resetFilters').on('click', function() {
                $('#filter_voucher_number').val('');
                $('#filter_amount').val('');
                $('#filter_reference').val('');
                $('#filter_date').val(today); // Reset to today's date
                isDateChanged = false;
                renderDataTable();
            });
        });
        // Delete Button Click
        $(document).on('click', '.delete-btn', function() {
            const id = $(this).data('id');
            const deleteUrl = `{{ route('commision.voucher.delete', ':id') }}`.replace(':id', id);
            $('#deleteForm').attr('action', deleteUrl);
            $('#deleteModal').modal('show');
        });

        function renderDataTable() {
            $('#cv-list-error').addClass('d-none').text('');
            const filter_voucher_number = $('#filter_voucher_number').val();
            const filter_amount = $('#filter_amount').val();
            const filter_reference = $('#filter_reference').val();
            const filter_date = $('#filter_date').val();

            // Preserve the shared column-filter adapter and pagination DOM.
            // Reinitialize only once; subsequent filter changes reload existing AJAX data.
            if ($.blueActionMenu) $.blueActionMenu.close();
            if ($.fn.DataTable.isDataTable('#journalTable')) {
                $('#journalTable').DataTable().ajax.reload(null,true);
                return;
            }

            // Initialize DataTable with AJAX
            $('#journalTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                pageLength:25,
                lengthMenu:[[10,25,50,100],[10,25,50,100]],
                drawCallback:function(){
                    if ($.blueVoucherRegisterActions && $.blueVoucherRegisterActions.enhance) {
                        $.blueVoucherRegisterActions.enhance($('#journalTable'));
                    }
                },
                ajax: {
                    url: '{{ route('commision.voucher.index') }}', // Route to get data
                    type: 'GET',
                    data: function(d) {
                        d.filter_voucher_number = $('#filter_voucher_number').val();
                        d.filter_amount = $('#filter_amount').val();
                        d.filter_reference = $('#filter_reference').val();
                        if ($.financeVoucherDataColumns) {
                            const columnState = $.financeVoucherDataColumns.values('#journalTable');
                            d.fvd_column_filters = columnState.filters;
                            d.fvd_date_from = columnState.dates.from;
                            d.fvd_date_to = columnState.dates.to;
                            d.fvd_sort_column = columnState.sort;
                            d.fvd_sort_direction = columnState.direction;
                        }
                        if (isDateChanged) {
                            d.filter_date = $('#filter_date').val(); // Use current date filter
                        }
                    },
                    dataSrc: 'data',
                    error: function(xhr) {
                        // DataTables owns the processing indicator; on failed
                        // requests explain the failure without leaking response
                        // payloads or leaving a spinner blocking the directory.
                        $('#journalTable_processing, #journalTable_wrapper .dataTables_processing').hide();
                        var message = xhr.status === 403 ? 'You do not have access to this voucher register.'
                            : xhr.status === 419 ? 'Session expired. Refresh the page and sign in again.'
                            : 'Commission vouchers could not be loaded. Please retry.';
                        $('#cv-list-error').text(message).removeClass('d-none');
                    }
                },
                columns: [{
                        data: 'id'
                    },
                    {
                        data: 'voucher_number'
                    },
                    {
                        data: 'reference'
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'description'
                    },
                    {
                        data: 'total_debit'
                    },
                    {
                        data: 'actions'
                    } // Action column for edit, delete, and print
                ]
            });
        }
    </script>
@endsection
