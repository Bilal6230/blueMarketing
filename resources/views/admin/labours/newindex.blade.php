@extends('admin.layouts.master')
@section('content')
    <style>
        :root {
            --bg: #eef3fb;
            --card: #ffffff;
            --muted: #6b7280;
            --accent: linear-gradient(135deg, #246bff, #1e54d9);
            --accent-600: #1e54d9;
            --glass: rgba(255, 255, 255, 0.6);
            --radius: 12px;
            --shadow: 0 12px 30px rgba(16, 24, 40, 0.08);
            --max-width: 1200px;
            --gap: 18px;
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            background: radial-gradient(1200px 400px at 10% 10%, rgba(36, 107, 255, 0.06), transparent),
                linear-gradient(180deg, #f7fbff, #eef3fb 60%);
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 28px;
            font-size: 15px;
            line-height: 1.4;
        }

        .container {
            width: 94%;
            max-width: var(--max-width);
            margin: 0 auto;
        }

        /* Header */
        .module-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .brand {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .logo {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: 0 8px 30px rgba(30, 84, 217, 0.18);
            font-size: 18px;
        }

        .brand h1 {
            margin: 0;
            font-size: 1.05rem
        }

        .brand small {
            display: block;
            color: var(--muted);
            font-size: .85rem
        }

        /* Tabs */
        .tabs {
            background: transparent;
            padding: 12px;
            border-radius: 12px;
        }

        .tab-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .tab-btn {
            border: 0;
            background: transparent;
            padding: 8px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            color: var(--muted);
            transition: all .18s ease;
            box-shadow: none;
        }

        .tab-btn.active {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.85));
            color: var(--accent-600);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        /* Panels card */
        .tab-panels {
            background: linear-gradient(180deg, #ffffff, #fbfdff);
            border-radius: 14px;
            padding: 18px;
            box-shadow: var(--shadow);
        }

        /* Layouts for two-card pages */
        .two-col {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: var(--gap);
            align-items: start;
        }

        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid rgba(15, 23, 42, 0.04);
            box-shadow: 0 6px 20px rgba(16, 24, 40, 0.03);
        }

        .card h3 {
            margin: 0 0 8px 0;
            font-size: 1.02rem
        }

        .small-muted {
            color: var(--muted);
            font-size: .9rem
        }

        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px
        }

        .field {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px
        }

        .field label {
            font-size: .85rem;
            margin-bottom: 6px;
            font-weight: 600
        }

        .field input,
        .field select,
        .field textarea {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            font-size: .95rem;
            background: linear-gradient(180deg, #fff, #fcfeff);
        }

        .field input[type="number"] {
            max-width: 220px
        }

        textarea {
            resize: vertical;
            min-height: 84px
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 0;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 8px 28px rgba(36, 107, 255, 0.14)
        }

        .btn-ghost {
            background: transparent;
            color: var(--accent-600);
            border: 1px solid rgba(30, 84, 217, 0.12);
            font-weight: 700
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px
        }

        .count-pill {
            background: linear-gradient(90deg, #f0f6ff, #fff);
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.04);
            font-weight: 700
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px
        }

        thead th {
            font-size: .85rem;
            text-align: left;
            padding: 10px 12px;
            color: var(--muted);
            font-weight: 700;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06)
        }

        tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.03);
            vertical-align: middle
        }

        tbody tr:nth-child(even) {
            background: linear-gradient(90deg, rgba(36, 107, 255, 0.02), transparent)
        }

        .muted {
            color: var(--muted);
            font-size: .9rem
        }

        .badge {
            display: inline-block;
            padding: 6px 8px;
            border-radius: 8px;
            font-weight: 700;
            font-size: .85rem
        }

        /* Price & numbers */
        .num {
            font-weight: 700
        }

        /* Report filters */
        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 12px
        }

        .filters .field {
            min-width: 160px
        }

        .report-actions {
            display: flex;
            gap: 8px;
            align-items: center
        }

        /* Attendance grid */
        .attendance-grid {
            overflow: auto
        }

        .attendance-table thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(180deg, #fff, #fbfdff);
            z-index: 2
        }

        .day-cell {
            width: 36px;
            text-align: center;
            font-size: .85rem;
            padding: 6px
        }

        .hour-input {
            width: 56px;
            padding: 6px;
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            text-align: center
        }

        /* responsive */
        @media(max-width:980px) {
            .two-col {
                grid-template-columns: 1fr;
            }

            .field input[type="number"] {
                max-width: 100%
            }

            .tab-list {
                justify-content: center
            }
        }

        @media(max-width:560px) {
            body {
                padding: 18px
            }

            .brand h1 {
                font-size: 1rem
            }

            .logo {
                width: 48px;
                height: 48px
            }

            .day-cell {
                width: 30px;
                padding: 4px;
                font-size: .75rem
            }
        }

        /* small helpers */
        .text-right {
            text-align: right
        }

        .muted-sm {
            font-size: .85rem;
            color: var(--muted)
        }

        .spaced {
            margin-top: 12px
        }
    </style>
    <div class="content-wrapper pt-4">
        <section class="tabs">
            <div class="tab-list" role="tablist" aria-label="Labour module tabs">
                <button class="tab-btn active" role="tab" aria-controls="tab-add-labour">Add Labour</button>
                <button class="tab-btn" role="tab" aria-controls="tab-site-list">Site List</button>
                <button class="tab-btn" role="tab" aria-controls="tab-site-report">Site Report</button>
                <button class="tab-btn" role="tab" aria-controls="tab-person-report">Person wise Report</button>
                <button class="tab-btn" role="tab" aria-controls="tab-attendance">Attendance</button>
            </div>

            <div class="tab-panels">
                <!-- ADD LABOUR -->
                <article id="tab-add-labour" class="tab-panel">
                    <div class="two-col">
                        <div class="card">
                            <h3>Add Labour</h3>
                            <div class="small-muted">Enter labour details</div>

                            <div class="spaced">
                                <div class="field"><label>Name</label><input id="lab-name" placeholder="Full name">
                                </div>
                                <div class="form-row">
                                    <div class="field" style="flex:1"><label>Mobile</label><input id="lab-mobile"
                                            placeholder="+92 3xx xxx xxxx"></div>
                                    <div class="field" style="width:160px"><label>Designation</label><input
                                            id="lab-designation" placeholder="e.g. Helper"></div>
                                </div>
                                <div class="form-row">
                                    <div class="field"><label>Rate (per 8 hours)</label><input id="lab-rate"
                                            type="number" min="0" placeholder="e.g. 1200"></div>
                                    <div class="field"><label>Advance</label><input id="lab-advance" type="number"
                                            min="0" placeholder="e.g. 500"></div>
                                </div>
                                <div class="field"><label>CNIC Number</label><input id="lab-cnic"
                                        placeholder="xxxxx-xxxxxxx-x"></div>

                                <div style="display:flex;gap:8px;margin-top:8px">
                                    <button id="save-labour" class="btn btn-primary">Save Labour</button>
                                    <button id="reset-labour" class="btn btn-ghost">Reset</button>
                                </div>
                                <div id="labour-status" class="muted-sm spaced"></div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="meta-row">
                                <h3>All Labour</h3>
                                <div class="count-pill" id="lab-count">4 total</div>
                            </div>

                            <div class="muted-sm">Current labour list</div>
                            <table id="labour-table" aria-label="labour list">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Labour</th>
                                        <th>Mobile</th>
                                        <th>Designation</th>
                                        <th class="text-right">Rate</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </article>

                <!-- SITE LIST -->
                <article id="tab-site-list" class="tab-panel" hidden>
                    <div class="two-col">
                        <div class="card">
                            <h3>Add Site</h3>
                            <div class="small-muted">Create new site</div>

                            <div class="spaced">
                                <div class="field"><label>Site Name</label><input id="site-name"
                                        placeholder="e.g. North Plaza"></div>
                                <div class="field"><label>Address</label><textarea id="site-address"
                                        placeholder="Full address"></textarea></div>

                                <div style="display:flex;gap:8px;margin-top:8px">
                                    <button id="save-site" class="btn btn-primary">Save Site</button>
                                    <button id="reset-site" class="btn btn-ghost">Reset</button>
                                </div>
                                <div id="site-status" class="muted-sm spaced"></div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="meta-row">
                                <h3>All Sites</h3>
                                <div class="count-pill" id="site-count">4 total</div>
                            </div>

                            <div class="muted-sm">Registered sites</div>
                            <table id="site-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Site</th>
                                        <th>Address</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </article>

                <!-- SITE REPORT -->
                <article id="tab-site-report" class="tab-panel" hidden>
                    <div class="card">
                        <div class="meta-row">
                            <h3>Site Report</h3>
                            <div class="small-muted">Filter and run to view totals</div>
                        </div>

                        <div class="filters">
                            <div class="field">
                                <label>Site</label>
                                <select id="report-site">
                                    <option value="all">All Sites</option>
                                </select>
                            </div>

                            <div class="field"><label>From</label><input id="report-from" type="date"></div>
                            <div class="field"><label>To</label><input id="report-to" type="date"></div>

                            <div class="report-actions">
                                <button id="run-site-report" class="btn btn-primary">Run</button>
                                <div id="report-total" class="muted-sm" style="padding-left:10px"></div>
                            </div>
                        </div>

                        <div style="overflow:auto;margin-top:8px">
                            <table id="report-table">
                                <thead>
                                    <tr>
                                        <th>Labour</th>
                                        <th>Mobile</th>
                                        <th>Designation</th>
                                        <th>Rate</th>
                                        <th>Days</th>
                                        <th>Over Time</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-right muted-sm">Total</td>
                                        <td class="text-right num" id="report-grand-total">0</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </article>

                <!-- PERSON WISE REPORT -->
                <article id="tab-person-report" class="tab-panel" hidden>
                    <div class="card">
                        <div class="meta-row">
                            <h3>Person wise Report</h3>
                            <div class="small-muted">Select labour or site & date range</div>
                        </div>

                        <div class="filters">
                            <div class="field">
                                <label>Labour</label>
                                <select id="person-select">
                                    <option value="all">All Labour</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Site</label>
                                <select id="person-site">
                                    <option value="all">All Sites</option>
                                </select>
                            </div>

                            <div class="field"><label>From</label><input id="person-from" type="date"></div>
                            <div class="field"><label>To</label><input id="person-to" type="date"></div>

                            <div class="report-actions">
                                <button id="run-person-report" class="btn btn-primary">Run</button>
                                <div id="person-total" class="muted-sm" style="padding-left:10px"></div>
                            </div>
                        </div>

                        <div style="overflow:auto;margin-top:8px">
                            <table id="person-report-table">
                                <thead>
                                    <tr>
                                        <th>Labour</th>
                                        <th>Mobile</th>
                                        <th>Designation</th>
                                        <th>Rate</th>
                                        <th>Days</th>
                                        <th>Over Time</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-right muted-sm">Total</td>
                                        <td class="text-right num" id="person-grand-total">0</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </article>

                <!-- ATTENDANCE -->
                <article id="tab-attendance" class="tab-panel" hidden>
                    <div class="card">
                        <div class="meta-row">
                            <h3>Attendance</h3>
                            <div class="small-muted">Mark daily hours & overtime (demo)</div>
                        </div>

                        <div class="filters" style="align-items:center">
                            <div class="field"><label>Month</label><input id="att-month" type="month" value=""></div>
                            <div class="field">
                                <label>Site</label>
                                <select id="att-site">
                                    <option value="all">All Sites</option>
                                </select>
                            </div>
                            <div style="margin-left:auto;display:flex;gap:8px">
                                <button id="load-att" class="btn btn-primary">Load</button>
                                <button id="save-att" class="btn btn-ghost">Save (demo)</button>
                            </div>
                        </div>

                        <div class="attendance-grid" style="margin-top:12px">
                            <table id="attendance-table" class="attendance-table">
                                <thead>
                                    <tr id="att-head">
                                        <th style="min-width:220px">Labour</th>
                                        <!-- day headers injected -->
                                    </tr>
                                </thead>
                                <tbody id="att-body">
                                    <!-- rows injected -->
                                </tbody>
                            </table>
                        </div>

                    </div>
                </article>

            </div>
        </section>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addLabourModal" tabindex="-1" aria-labelledby="addLabourModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form action="{{ route('labours.store') }}" method="POST" class="modal-content shadow-lg border-0 rounded-3">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addLabourModalLabel">
                        <i class="fas fa-plus-circle mr-2"></i> Add Labour
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @include('admin.labours.form')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editLabourModal" tabindex="-1" aria-labelledby="editLabourModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" id="editLabourForm" class="modal-content shadow-lg border-0 rounded-3">
                @csrf @method('PUT')
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editLabourModalLabel"> <i class="fas fa-edit mr-2"></i> Edit Labour</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @include('admin.labours.form')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check mr-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $("#labourTable").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            })
        });
        document.addEventListener('DOMContentLoaded', function() {
            $('.editBtn').on('click', function() {
                let id = $(this).data('id');
                let updateUrl = "{{ route('labours.update', ':id') }}".replace(':id', id);
                $('#editLabourForm').attr('action', updateUrl)
                $('input[name="name"]').val($(this).data('name'));
                $('input[name="cnic"]').val($(this).data('cnic'));
                $('input[name="phone"]').val($(this).data('phone'));
                $('input[name="daily_wage"]').val($(this).data('wage'));
                $('input[name="join_date"]').val($(this).data('date'));
                $('select[name="status"]').val($(this).data('status'));
            });
        });
    </script>
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end', // top right
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
    <script>
        function confirmDeleteLabour(labourId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This labour will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-labour-' + labourId).submit();
                }
            });
        }
    </script>
    <script>
        (function($) {
            // --- Dummy data
            const sampleLabours = [{
                    id: 1,
                    name: "Mohammad Ali",
                    mobile: "+92 300 1112223",
                    designation: "Skilled",
                    rate: 1800,
                    advance: 0,
                    cnic: "42101-1234567-1"
                },
                {
                    id: 2,
                    name: "Aslam Khan",
                    mobile: "+92 300 2223334",
                    designation: "Helper",
                    rate: 900,
                    advance: 200,
                    cnic: "42101-2345678-2"
                },
                {
                    id: 3,
                    name: "Zafar Iqbal",
                    mobile: "+92 300 3334445",
                    designation: "Supervisor",
                    rate: 3000,
                    advance: 500,
                    cnic: "42101-3456789-3"
                },
                {
                    id: 4,
                    name: "Bilal Shah",
                    mobile: "+92 300 4445556",
                    designation: "Helper",
                    rate: 950,
                    advance: 0,
                    cnic: "42101-4567890-4"
                }
            ];
            const sampleSites = [{
                    id: 1,
                    name: "North Plaza",
                    address: "Street 12, Phase 4, City"
                },
                {
                    id: 2,
                    name: "Green Heights",
                    address: "Block A, Main Road"
                },
                {
                    id: 3,
                    name: "River View",
                    address: "Canal Bank, Sector 5"
                },
                {
                    id: 4,
                    name: "Sunset Tower",
                    address: "Mall Road"
                }
            ];

            let labours = JSON.parse(JSON.stringify(sampleLabours));
            let sites = JSON.parse(JSON.stringify(sampleSites));

            // --- Utils
            function fmtNumber(n) {
                return Number(n).toLocaleString();
            }

            function refreshLabourTable() {
                const $tb = $('#labour-table tbody').empty();
                labours.forEach((l, i) => {
                    $tb.append(`<tr>
            <td>${i+1}</td>
            <td><strong>${l.name}</strong></td>
            <td class="muted-sm">${l.mobile}</td>
            <td>${l.designation}</td>
            <td class="text-right num">${fmtNumber(l.rate)}</td>
          </tr>`);
                });
                $('#lab-count').text(`${labours.length} total`);
                // populate person-select
                const $ps = $('#person-select').empty().append('<option value="all">All Labour</option>');
                labours.forEach(l => $ps.append(`<option value="${l.id}">${l.name}</option>`));
            }

            function refreshSiteTable() {
                const $tb = $('#site-table tbody').empty();
                sites.forEach((s, i) => {
                    $tb.append(`<tr>
            <td>${i+1}</td>
            <td><strong>${s.name}</strong></td>
            <td class="muted-sm">${s.address}</td>
          </tr>`);
                });
                $('#site-count').text(`${sites.length} total`);
                // populate site selects
                ['#report-site', '#person-site', '#att-site'].forEach(sel => {
                    const $sel = $(sel).empty().append('<option value="all">All Sites</option>');
                    sites.forEach(s => $sel.append(`<option value="${s.id}">${s.name}</option>`));
                });
                // also for run-site-report default option
                $('#report-site').prepend('<option value="all">All Sites</option>');
            }

            // init
            $(function() {
                // populate initial tables
                refreshLabourTable();
                refreshSiteTable();

                // TAB SWITCHING
                $('.tab-btn').on('click', function(e) {
                    e.preventDefault();
                    const $b = $(this);
                    if ($b.hasClass('active')) return;
                    $('.tab-btn').removeClass('active');
                    $b.addClass('active');
                    $('.tab-panel').attr('hidden', true);
                    const id = $b.attr('aria-controls');
                    $('#' + id).removeAttr('hidden');

                    // if attendance tab activated, set default month if empty
                    if (id === 'tab-attendance') {
                        if (!$('#att-month').val()) {
                            const d = new Date();
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const yyyy = d.getFullYear();
                            $('#att-month').val(`${yyyy}-${mm}`);
                        }
                        loadAttendanceGrid();
                    }
                });

                // SAVE LABOUR (demo)
                $('#save-labour').on('click', function() {
                    const name = $('#lab-name').val().trim();
                    const mobile = $('#lab-mobile').val().trim();
                    const designation = $('#lab-designation').val().trim() || 'Worker';
                    const rate = Number($('#lab-rate').val()) || 0;
                    const advance = Number($('#lab-advance').val()) || 0;
                    const cnic = $('#lab-cnic').val().trim() || '';

                    if (!name || !mobile) {
                        $('#labour-status').text('Please provide name & mobile').css('color',
                        'crimson');
                        return;
                    }

                    const id = labours.length ? Math.max(...labours.map(l => l.id)) + 1 : 1;
                    labours.push({
                        id,
                        name,
                        mobile,
                        designation,
                        rate,
                        advance,
                        cnic
                    });
                    refreshLabourTable();
                    $('#labour-status').text('Saved successfully (demo)').css('color', 'green');
                    $('#lab-name,#lab-mobile,#lab-designation,#lab-rate,#lab-advance,#lab-cnic').val(
                    '');
                });

                $('#reset-labour').on('click', function() {
                    $('#lab-name,#lab-mobile,#lab-designation,#lab-rate,#lab-advance,#lab-cnic').val(
                    '');
                    $('#labour-status').text('');
                });

                // SAVE SITE (demo)
                $('#save-site').on('click', function() {
                    const name = $('#site-name').val().trim();
                    const address = $('#site-address').val().trim();
                    if (!name) {
                        $('#site-status').text('Please provide site name').css('color', 'crimson');
                        return;
                    }
                    const id = sites.length ? Math.max(...sites.map(s => s.id)) + 1 : 1;
                    sites.push({
                        id,
                        name,
                        address
                    });
                    refreshSiteTable();
                    $('#site-status').text('Saved successfully (demo)').css('color', 'green');
                    $('#site-name,#site-address').val('');
                });
                $('#reset-site').on('click', function() {
                    $('#site-name,#site-address').val('');
                    $('#site-status').text('');
                });

                // RUN SITE REPORT (demo generation)
                $('#run-site-report').on('click', function() {
                    const siteId = $('#report-site').val();
                    const from = $('#report-from').val();
                    const to = $('#report-to').val();
                    // create demo rows - filter by site if not 'all' (we just vary amount)
                    const $tb = $('#report-table tbody').empty();
                    let grand = 0;
                    labours.forEach((l) => {
                        // simple demo: days = random 5-22 within date range; overtime random 0-8
                        const days = Math.floor(Math.random() * 18) + 5;
                        const ot = Math.floor(Math.random() * 7);
                        const amount = (l.rate * days) + Math.round((ot * (l.rate / 8)));
                        // apply fake site filter: if site selected, show only two entries
                        if (siteId !== 'all' && (l.id % 2 === 0)) {
                            // show some, skip others to simulate filter
                        }
                        $tb.append(`<tr>
              <td>${l.name}</td>
              <td class="muted-sm">${l.mobile}</td>
              <td>${l.designation}</td>
              <td class="num">${fmtNumber(l.rate)}</td>
              <td>${days}</td>
              <td>${ot}</td>
              <td class="text-right num">${fmtNumber(amount)}</td>
            </tr>`);
                        grand += amount;
                    });
                    $('#report-grand-total').text(fmtNumber(grand));
                    $('#report-total').text(`Showing ${labours.length} rows`).css('color',
                        'var(--muted)');
                });

                // RUN PERSON REPORT (demo)
                $('#run-person-report').on('click', function() {
                    const pid = $('#person-select').val();
                    const siteId = $('#person-site').val();
                    const $tb = $('#person-report-table tbody').empty();
                    let grand = 0;
                    labours.forEach((l) => {
                        if (pid !== 'all' && String(l.id) !== String(pid)) return;
                        const days = Math.floor(Math.random() * 18) + 5;
                        const ot = Math.floor(Math.random() * 7);
                        const amount = (l.rate * days) + Math.round((ot * (l.rate / 8)));
                        $tb.append(`<tr>
              <td>${l.name}</td>
              <td class="muted-sm">${l.mobile}</td>
              <td>${l.designation}</td>
              <td class="num">${fmtNumber(l.rate)}</td>
              <td>${days}</td>
              <td>${ot}</td>
              <td class="text-right num">${fmtNumber(amount)}</td>
            </tr>`);
                        grand += amount;
                    });
                    $('#person-grand-total').text(fmtNumber(grand));
                    $('#person-total').text(`Rows: ${$('#person-report-table tbody tr').length}`).css(
                        'color', 'var(--muted)');
                });

                // Attendance: build a grid for selected month
                $('#load-att').on('click', loadAttendanceGrid);
                $('#att-month').on('change', loadAttendanceGrid);

                function loadAttendanceGrid() {
                    const monthStr = $('#att-month').val();
                    const monthDate = monthStr ? new Date(monthStr + '-01') : new Date();
                    const year = monthDate.getFullYear();
                    const month = monthDate.getMonth(); // 0-index
                    const daysInMonth = new Date(year, month + 1, 0).getDate();

                    // header
                    const $head = $('#att-head').empty();
                    $head.append('<th style="min-width:220px">Labour</th>');
                    for (let d = 1; d <= daysInMonth; d++) {
                        $head.append(`<th class="day-cell">${d}</th>`);
                    }

                    // body
                    const $body = $('#att-body').empty();
                    labours.forEach(l => {
                        const $tr = $('<tr></tr>');
                        $tr.append(
                            `<td><strong>${l.name}</strong><div class="muted-sm">${l.designation} • ${l.mobile}</div></td>`
                            );
                        for (let d = 1; d <= daysInMonth; d++) {
                            // demo: random hours between 0 and 9
                            const hours = Math.floor(Math.random() * 10); // 0-9
                            const ot = Math.max(0, hours - 8);
                            $tr.append(
                                `<td class="day-cell"><input type="number" min="0" max="24" class="hour-input" value="${hours}" data-lid="${l.id}" data-day="${d}" title="${ot} OT"></td>`
                                );
                        }
                        $body.append($tr);
                    });
                }

                // Save attendance demo (just show message)
                $('#save-att').on('click', function() {
                    // gather some values (demo)
                    const values = [];
                    $('#attendance-table .hour-input').each(function() {
                        const v = $(this).val();
                        values.push(Number(v || 0));
                    });
                    alert('Attendance saved (demo). Rows: ' + labours.length + ', inputs: ' + values
                        .length);
                });

                // Export CSV (very simple demo): export labour list
                $('#export-sample').on('click', function() {
                    let csv = 'Name,Mobile,Designation,Rate,Advance,CNIC\n';
                    labours.forEach(l => csv +=
                        `"${l.name}","${l.mobile}","${l.designation}",${l.rate},${l.advance},"${l.cnic}"\n`
                        );
                    const blob = new Blob([csv], {
                        type: 'text/csv'
                    });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'labours.csv';
                    a.click();
                    URL.revokeObjectURL(url);
                });

                // Add sample (adds a random sample labour & site) - demo convenience
                $('#add-sample').on('click', function() {
                    const nl = {
                        id: Math.max(...labours.map(x => x.id)) + 1,
                        name: `New Labour ${labours.length+1}`,
                        mobile: '+92 300 5556667',
                        designation: 'Helper',
                        rate: 950,
                        advance: 0,
                        cnic: ''
                    };
                    const ns = {
                        id: Math.max(...sites.map(x => x.id)) + 1,
                        name: `New Site ${sites.length+1}`,
                        address: 'Somewhere'
                    };
                    labours.push(nl);
                    sites.push(ns);
                    refreshLabourTable();
                    refreshSiteTable();
                });

                // initialize attendance month
                const now = new Date();
                $('#att-month').val(`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`);
                loadAttendanceGrid();

            }); // doc ready

        })(jQuery);
    </script>
@endsection
