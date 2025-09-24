@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper pt-4">
        <section class="content">
            <div class="container-fluid">
                <div class="custom_card h-100">
                    <div class="card-body">
                        @if ($accountants->isNotEmpty())
                            <table class="table table-hover" id="accountantTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>NIC</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Ledgers</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($accountants as $idx => $user)
                                        <tr>
                                            <td>{{ $idx + 1 }}</td>
                                            <td>{{ $user->name ?? 'N/A' }}</td>
                                            <td>{{ $user->nic_number ?? 'N/A' }}</td>
                                            <td>{{ $user->email ?? 'N/A' }}</td>
                                            <td>{{ $user->phone_number ?? 'N/A' }}</td>
                                            <td>{{ $user->matching_ledgers_count ?? '—' }}</td>
                                            <td>
                                                <button class="btn btn-info btn-sm viewLedgersBtn"
                                                    data-id="{{ $user->id }}" data-name="{{ $user->name }}">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="notfound bg-white p-3">
                                <div class="d-flex flex-wrap justify-content-center align-items-center">
                                    <div class="image-notfound mr-3">
                                        <img src="{{ asset('dist/images/not-found.png') }}" class="img-fluid"
                                            alt="No data">
                                    </div>
                                    <div class="text-notfound text-center">
                                        <h4 class="mb-0 f-20 text-dark">{{ __('Sorry! No data found.') }}</h4>
                                        <p class="mb-0 f-16 text-gray-100 mt-2">
                                            {{ __('The requested data does not exist for this feature overview.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Ledger Modal --}}
    <div class="modal fade" id="ledgerModal" tabindex="-1" aria-labelledby="ledgerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Ledgers — <span id="ledgerUserName"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>

                <div class="modal-body">
                    <div id="ledgerSummary" class="row g-3 mb-3" style="display:none;">
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Entries</small>
                                <strong id="sumEntries">0</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Total In</small>
                                <strong id="sumIn">0</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Total Out</small>
                                <strong id="sumOut">0</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Net Total (In − Out)</small>
                                <strong id="sumNet">0</strong>
                            </div>
                        </div>
                    </div>

                    <div id="ledgerLoading" class="text-center my-4">
                        <div class="spinner-border" role="status"></div>
                        <div class="mt-2">Loading ledgers…</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped" id="ledgerTable" style="display:none;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Detail</th>
                                    <th>Date</th>
                                    <th>Amount In</th>
                                    <th>Amount Out</th>
                                    <th>Running Total</th>
                                </tr>
                            </thead>
                            <tbody id="ledgerTbody"></tbody>
                        </table>
                    </div>

                    <div id="ledgerEmpty" class="text-center py-4" style="display:none;">
                        <em>No ledger records found for this user.</em>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (function () {
        "use strict";

        // -------- Main accountants table --------
        $(function () {
            $("#accountantTable").DataTable({
                responsive: true,
                lengthChange: true,
                autoWidth: false,
                buttons: ["copy", "csv", "excel", "pdf", "colvis"]
            }).buttons().container().appendTo('#accountantTable_wrapper .col-md-6:eq(0)');
        });

        // -------- Helpers --------
        function fmt(num) {
            if (num === null || num === undefined || num === '') return '0.00';
            var n = Number(num);
            if (isNaN(n)) n = 0;
            return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function parseAmount(v) {
            var n = Number(v);
            return isNaN(n) ? 0 : n;
        }

        // -------- Modal DataTable (init once, reuse) --------
        var ledgerDt = null;

        function ensureLedgerDataTable() {
            if (ledgerDt) return ledgerDt;
            ledgerDt = $("#ledgerTable").DataTable({
                responsive: true,
                lengthChange: true,
                autoWidth: false,
                paging: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1],[10, 25, 50, 100, "All"]],
                searching: true,
                ordering: true,
                order: [[3, "asc"]], // Date column
                dom: 'Bfrtip',
                buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
                stateSave: false // make sure it doesn't persist page/search/order across reloads
            });
            ledgerDt.buttons().container().appendTo('#ledgerTable_wrapper .col-md-6:eq(0)');
            return ledgerDt;
        }

        // Adjust columns after modal is visible (BS4)
        $('#ledgerModal').on('shown.bs.modal', function () {
            if (ledgerDt) ledgerDt.columns.adjust();
        });

        // Clear & reset when modal hides
        $('#ledgerModal').on('hidden.bs.modal', function () {
            if (ledgerDt) {
                ledgerDt
                    .clear()
                    .search('')
                    .order([])        // reset ordering (optional)
                    .page(0)          // go to first page
                    .draw(true);      // full draw to reset paging
            }
            $('#ledgerSummary, #ledgerEmpty, #ledgerLoading').hide();
        });

        // -------- Open modal + load ledgers --------
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.viewLedgersBtn');
            if (!btn) return;

            var userId   = btn.dataset.id;
            var userName = btn.dataset.name || 'User';

            // Reset modal UI
            $('#ledgerUserName').text(userName);
            $('#ledgerLoading').show();
            $('#ledgerEmpty').hide();
            $('#ledgerSummary').hide();
            $('#ledgerTable').show();

            // Show modal (Bootstrap 4)
            $('#ledgerModal').modal('show');

            // Ensure DT exists and HARD reset to page 1
            var dt = ensureLedgerDataTable();
            dt.clear().search('').order([[3,'asc']]).page(0).draw(true);

            var url = "{{ route('accounting.Accountant.ledgers', ':id') }}".replace(':id', userId);

            $.ajax({
                method: 'GET',
                url: url,
                data: { project_id: "{{ getSelectedTown() }}" },
                cache: false
            })
            .done(function (resp) {
                var rows = Array.isArray(resp) ? resp : (resp.ledgers || []);
                $('#ledgerLoading').hide();

                if (!rows.length) {
                    $('#ledgerEmpty').show();
                    return;
                }

                // Sort by date asc if needed
                rows.sort(function(a, b){ return (new Date(a.date) - new Date(b.date)); });

                var running = 0, sumIn = 0, sumOut = 0;

                var dtRows = rows.map(function (row, i) {
                    var ain  = parseAmount(row.amount_in);
                    var aout = parseAmount(row.amount_out);
                    sumIn  += ain;
                    sumOut += aout;
                    running += (ain - aout);
                    return [
                        i + 1,
                        row.type || '',
                        (row.detail || '').toString(),
                        row.date || '',
                        fmt(ain),
                        fmt(aout),
                        fmt(running)
                    ];
                });

                // Add fresh rows, then force page to first
                dt.rows.add(dtRows);
                dt.page(0).draw(false); // draw without resetting ordering, but jump to page 1

                // Summary
                $('#sumEntries').text(rows.length);
                $('#sumIn').text(fmt(sumIn));
                $('#sumOut').text(fmt(sumOut));
                $('#sumNet').text(fmt(sumIn - sumOut));
                $('#ledgerSummary').show();
            })
            .fail(function (xhr) {
                $('#ledgerLoading').hide();
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Failed to load ledgers.',
                    text: (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Please try again.',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        });

    })();
    </script>

    @if ($errors->any())
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: `{!! implode('<br>', $errors->all()) !!}`,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        });
        </script>
    @endif

    @if (session('success'))
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: '{{ session('success') }}',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        });
        </script>
    @endif
@endsection

