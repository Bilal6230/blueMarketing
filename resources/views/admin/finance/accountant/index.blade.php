@extends('admin.layouts.master')
@section('title', 'Accountants List')
@push('style')
<link rel="stylesheet" href="{{ asset('css/modern-account-directory.css') }}">
<style>
/* Accountant listing shares the verified Main/Party/Joint card, row, filter and pagination contract. */
.bm-modern-accounts.bm-accountants .content-header{margin-bottom:12px!important}
.bm-modern-accounts.bm-accountants .bm-account-card{border-top:3px solid #2563eb!important}
.bm-modern-accounts.bm-accountants .bm-account-table tbody td{height:48px!important}
.bm-modern-accounts.bm-accountants .bm-account-table td:last-child{text-align:center}
.bm-modern-accounts.bm-accountants .bm-account-table .viewLedgersBtn{
  border:1px solid #b9dcca!important;background:#fff!important;color:#087747!important;
  border-radius:9px!important;width:32px;height:32px;padding:0!important;
  display:inline-flex;align-items:center;justify-content:center;
  box-shadow:0 2px 6px #173d2210;font-size:14px;
}
.bm-modern-accounts.bm-accountants .bm-account-table .viewLedgersBtn:hover{background:#e8f8ef!important}
.bm-modern-accounts.bm-accountants .bm-account-empty{padding:24px;text-align:center;color:#60758e}
#ledgerModal .modal-content{border:1px solid #c5d3e5;border-top:3px solid #2563eb;border-radius:12px;overflow:hidden}
#ledgerModal .modal-header{background:#f5f9ff;border-bottom:1px solid #cbd8e8;padding:15px 20px}
#ledgerModal .modal-title{font-size:17px;font-weight:750;color:#193d73}
#ledgerModal .modal-body{padding:18px 20px}
#ledgerModal #ledgerSummary .border{background:#f7faff;border-color:#d5e2f0!important}
#ledgerModal #ledgerSummary strong{font-size:17px;color:#184783}
#ledgerModal #ledgerTable{font-size:13px}
#ledgerModal #ledgerTable thead th{background:#eaf2fa;border-bottom:2px solid #2563eb}
</style>
@endpush

@section('content')
    <div class="content-wrapper bm-modern-accounts bm-accountants" data-account-kind="accountant">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Accountants List</h1>
                    </div>
                    <div class="col-sm-6"></div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card bm-account-card">
                            <div class="bm-modern-export-toolbar bm-export-placeholder" aria-label="Table export toolbar loading">
  <button type="button" class="bm-modern-export-btn" disabled>Copy</button>
  <button type="button" class="bm-modern-export-btn" disabled>CSV</button>
  <button type="button" class="bm-modern-export-btn" disabled>Excel</button>
  <button type="button" class="bm-modern-export-btn" disabled>PDF</button>
  <button type="button" class="bm-modern-export-btn" disabled>Print</button>
  <button type="button" class="bm-modern-export-btn bm-modern-col-toggle" disabled>Column Visibility</button>
</div>
    <div class="card-body bm-account-table-wrap">
                                
                                <table class="table table-bordered bm-account-table bm-pending-directory" id="bmAccountDirectory" data-initial-skeleton="true">
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
                                        @for ($skeletonIndex = 0; $skeletonIndex < 6; $skeletonIndex++)
                                            <tr class="bm-cell-skeleton-row" aria-hidden="true">
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                            </tr>
                                        @endfor
                                        @foreach ($accountants as $idx => $user)
                                            <tr>
                                                <td>{{ $idx + 1 }}</td>
                                                <td>{{ $user->name ?? 'N/A' }}</td>
                                                <td>{{ $user->nic_number ?? 'N/A' }}</td>
                                                <td>{{ $user->email ?? 'N/A' }}</td>
                                                <td>{{ $user->phone_number ?? 'N/A' }}</td>
                                                <td>{{ $user->matching_ledgers_count ?? '—' }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm viewLedgersBtn"
                                                        data-id="{{ $user->id }}" data-name="{{ $user->name }}"
                                                        aria-label="View ledgers for {{ $user->name }}" title="View ledgers">
                                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
<div class="bm-account-footer bm-account-footer-placeholder" aria-label="Table pagination loading">
  <div class="bm-account-footer-left">
    <small class="text-muted">Loading records...</small>
    <select disabled aria-label="Rows per page"><option selected>25</option></select>
    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Reset filters</button>
  </div>
  <div class="bm-account-pages"><button type="button" class="dv-page-btn" disabled>1</button></div>
</div>
                        </div>
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

        // Main listing uses the unified jQuery directory; ledger modal keeps its own DataTable.

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
        $(document).on('click', function (e) {
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
        $(function() {
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
        $(function() {
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
    <script src="{{ asset('js/modern-account-directory.js') }}"></script>
@endsection

