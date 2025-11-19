@extends('admin.layouts.master')
@section('content')
    <style>
        :root {
            --bg: #f6f8fb;
            --card: #ffffff;
            --ink: #0f172a;
            --muted: #6b7280;
            --line: #e5e7eb;
            --pri: #2563eb;
            --pri-ink: #ffffff;
            --ok: #16a34a;
            --warn: #f59e0b;
            --err: #ef4444;
            --chip: #eef2ff;
            --chip-ink: #3730a3;
            --shadow: 0 6px 22px rgba(2, 6, 23, .08);
        }

        a {
            color: inherit
        }

        .wrap {
            max-width: 1350px;
            margin: 24px auto;
            padding: 0 16px
        }

        .topbar {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px
        }

        .title {
            font-size: 20px;
            font-weight: 700
        }

        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px
        }

        .tab {
            appearance: none;
            border: 1px solid var(--line);
            background: #fff;
            color: #111827;
            padding: 8px 12px;
            border-radius: 10px;
            cursor: pointer
        }

        .tab[aria-selected="true"] {
            background: var(--pri);
            border-color: var(--pri);
            color: var(--pri-ink)
        }

        .grid {
            display: grid;
            gap: 16px
        }

        @media(min-width:900px) {
            .grid.cols-2 {
                grid-template-columns: 1fr 1fr
            }
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: var(--shadow)
        }

        .card .hd {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .card .hd b {
            font-size: 16px
        }

        .card .bd {
            padding: 16px
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px
        }

        .row>.col {
            flex: 1 1 200px
        }

        label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px
        }

        input,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 10px 14px;
            border-radius: 12px;
            cursor: pointer
        }

        .btn.pri {
            background: var(--pri);
            border-color: var(--pri);
            color: var(--pri-ink)
        }

        .btn.ghost {
            background: transparent
        }

        .btn.ok {
            background: var(--ok);
            border-color: var(--ok);
            color: #fff
        }

        .btn.warn {
            background: var(--warn);
            border-color: var(--warn);
            color: #111
        }

        .btn.err {
            background: var(--err);
            border-color: var(--err);
            color: #fff
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--chip);
            color: var(--chip-ink);
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 10px 12px;
            text-align: left
        }

        th {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: .04em
        }

        tr:hover td {
            background: #fafafa
        }

        .muted {
            color: var(--muted)
        }

        .right {
            text-align: right
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px
        }

        .hid {
            display: none
        }

        .inline {
            display: inline-block
        }

        .small {
            font-size: 12px
        }

        .tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            background: #f3f4f6;
            color: #111
        }

        /* Attendance board */
        .attn {
            display: grid;
            grid-template-columns: 260px 1fr;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            box-shadow: var(--shadow)
        }

        .attn .left {
            border-right: 1px solid var(--line);
            max-height: 520px;
            overflow: auto
        }

        .attn .right {
            max-height: 520px;
            overflow: auto
        }

        .attn .left .lab {
            padding: 0.5px 12px;
            border-bottom: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            gap: 2px
        }

        .attn .left .lab .nm {
            font-weight: 600
        }

        .attn .left .lab.active {
            background: #f8fafc
        }

        .days-head {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: 60px;
            border-bottom: 1px solid var(--line);
            background: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 1
        }

        .days-head .d {
            padding: 10px 0;
            text-align: center;
            font-size: 12px;
            color: var(--muted)
        }

        .rows {
            display: grid
        }

        .row-days {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: 60px
        }

        .cell {
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid var(--line);
            cursor: pointer
        }

        .cell .ico {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%
        }

        .ico.tick {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #d1fae5
        }

        .ico.cross {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2
        }

        .ico.leave {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #ffedd5
        }

        .ico.none {
            background: #eaeef5;
            color: #111;
            border: 1px dashed #cbd5e1
        }

        /* Modal */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, .55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px
        }

        .modal.show {
            display: flex !important
        }

        .modal .panel {
            width: min(720px, 100%);
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--line)
        }

        .modal .panel .hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line)
        }

        .modal .panel .bd {
            padding: 16px
        }

        .modal .panel .ft {
            padding: 14px 16px;
            border-top: 1px solid var(--line);
            display: flex;
            gap: 10px;
            justify-content: flex-end
        }

        .caps {
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: 12px;
            color: var(--muted)
        }

        .help {
            font-size: 12px;
            color: var(--muted)
        }

        .stat {
            display: flex;
            gap: 10px;
            align-items: center
        }

        .stat b {
            font-size: 18px
        }

        .today {
            background: darkgrey !important;
            color: aliceblue !important;
        }

        .validate-border {
            border: 1px solid red !important;
        }

        .validate-border:focus {
            border: 1px solid red !important;
            box-shadow: none !important;
            /* Bootstrap shadow remove (optional) */
            outline: none !important;
            /* Removes blue outline */
        }
    </style>

    <div class="wrap">
        <div class="topbar">
            <div class="title">Labour Management Module</div>
            <div class="tabs" role="tablist" aria-label="Pages">
                <button class="tab" data-tab="addLabour" aria-selected="true">Add Labour</button>
                <button class="tab" data-tab="sites">Site List</button>
                <button class="tab" data-tab="siteReport">Site Report</button>
                <button class="tab" data-tab="personReport">Person Wise Report</button>
                <button class="tab" data-tab="attendance">Attendance</button>
            </div>
        </div>

        <!-- Add Labour -->
        <section id="addLabour" class="page">
            <div class="grid cols-2">
                <div class="card">
                    <div class="hd"><b>Add a Labour</b><span class="caps">dummy form</span></div>
                    <div class="bd">
                        <form action="{{ route('labours.store') }}" method="post" id="addLabourForm">
                            @csrf
                            <div class="row">
                                <div class="col"><label>Name</label><input id="labName" name="name"
                                        placeholder="e.g., John Peter" required />
                                    <small class="text-danger error error-name d-none">Name is already exist</small>
                                </div>
                                <div class="col"><label>Father Name</label><input id="fatherName" name="father_name"
                                        placeholder="e.g., John Peter" required />
                                    <small class="text-danger error"></small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col"><label>Mobile</label><input class="" id="labMobile" name="phone"
                                        placeholder="0300 1234567" required />
                                    <small class="text-danger error error-phone d-none">Mobile is already exist</small>
                                </div>
                                <div class="col"><label>Designation</label><input id="labRole" name="role"
                                        placeholder="Mason / Helper" required />
                                    <small class="text-danger error"></small>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col"><label>Rate (per 8 hours)</label><input id="labRate" name="daily_wage"
                                        type="number" placeholder="1000" required />
                                    <small class="text-danger error"></small>
                                </div>
                                <div class="col"><label>CNIC</label><input id="cnic" name="cnic"
                                        placeholder="12345-6789012-3" required />
                                    <small class="text-danger error error-cnic d-none">CNIC is already exist</small>
                                </div>
                            </div>
                            <div class="row" style="margin-top:10px">
                                <button class="btn pri" id="btnAddLabour">Add Labour</button>
                                <span class="help">Adds into in-memory list for demo. Replace with AJAX to save to
                                    server.</span>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="hd"><b>All Labours</b> <span class="pill"><span
                                id="labCount">{{ $count }}</span> total</span>
                    </div>
                    <div class="bd">
                        <!-- Dummy labour rows moved to HTML. Javascript will parse these rows on boot. -->
                        <table id="labourTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Designation</th>
                                    <th class="right">Rate</th>
                                    <th class="right">Advance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="labourTableBody">
                                {{-- Dummy data in HTML as requested --}}
                                @include('admin.labours.labour')
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Site List -->
        <section id="sites" class="page hid">
            <div class="grid cols-2">
                <div class="card">
                    <div class="hd"><b>Add Site</b></div>
                    <div class="bd">
                        <form action="{{ route('labours.sitestore') }}" method="post" id="addSiteForm">
                            @csrf
                            <div class="row">
                                <div class="col">
                                    <label>Head Accounts</label>
                                    <select class="form-control select2" name="accounts_id" id="accounts_id">
                                        <option value="">Select Head</option>
                                        @foreach ($headaccounts as $head)
                                            <option value="{{ $head->head_accounting_id }}">
                                                {{ $head->headAccounting->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('accounts_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col">
                                    <label>Sub Head Accounts</label>
                                    <select class="form-control select2" name="subaccounts_id" id="subaccounts_id">
                                        <option value="">Select Sub Head</option>
                                    </select>
                                    @error('subaccounts_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col"><label>Site Name</label><input id="siteName" name="site_name"
                                        placeholder="e.g., Green Villas" /></div>
                                <div class="col"><label>Address</label><input id="siteAddr" name="site_address"
                                        placeholder="Street, City" />
                                </div>
                            </div>
                            <div class="row" style="margin-top:10px">
                                <button class="btn pri" id="btnAddSite">Add Site</button>
                                <span class="help">Adds into DOM. Replace with AJAX to persist.</span>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="hd"><b>Sites</b> <span class="pill"><span id="siteCount">{{ $sitecount }}</span>
                            total</span>
                    </div>
                    <div class="bd">
                        <table id="siteTable">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th>Address</th>
                                </tr>
                            </thead>
                            <tbody id="siteTableBody">
                                {{-- Dummy sites in HTML --}}
                                @include('admin.labours.sites')
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Site Report -->
        <section id="siteReport" class="page hid">
            <div class="card">
                <div class="hd"><b>Site Report</b><span class="caps">filter & total cost</span></div>
                <div class="bd">
                    <div class="toolbar" style="margin-bottom:12px">
                        <select id="repSite">
                            <option value="">All Sites</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->site_name }}</option>
                            @endforeach
                        </select>
                        <input type="date" id="repFrom" />
                        <input type="date" id="repTo" />
                        <button class="btn" id="btnSiteRun">Run</button>
                        <button class="btn" id="btnSiteExport">Export CSV</button>
                    </div>
                    <div class="chips" style="margin-bottom:10px">
                        <span class="pill">Total Days: <b id="siteDays">0</b></span>
                        <span class="pill">Total Overtime Hrs: <b id="siteOt">0</b></span>
                        <span class="pill">Total Cost: <b id="siteTotal">0</b></span>
                    </div>
                    <table id="siteReportTable">
                        <thead>
                            <tr>
                                <th>Labour</th>
                                <th>Mobile</th>
                                <th>Designation</th>
                                <th class="right">Rate</th>
                                <th class="right">Days</th>
                                <th class="right">Overtime</th>
                                <th class="right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="siteReportTableBody">
                            {{-- @include('admin.labours.site-report') --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Person Report -->
        <section id="personReport" class="page hid">
            <div class="card">
                <div class="hd"><b>Person Wise Report</b><span class="caps">details & totals</span></div>
                <div class="bd">
                    <div class="toolbar" style="margin-bottom:12px">
                        <input id="personQuery" placeholder="Search by name or mobile" style="min-width:260px" />
                        <input type="date" id="pFrom" />
                        <input type="date" id="pTo" />
                        <button class="btn" id="btnPersonRun">Run</button>
                        <button class="btn" id="btnPersonExport">Export CSV</button>
                    </div>
                    <div id="personHeader" class="row" style="margin-bottom:10px"></div>
                    <table id="personReportTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Site</th>
                                <th>Designation</th>
                                <th class="right">Hours</th>
                                <th class="right">Overtime</th>
                                <th class="right">Amount</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div class="row" style="margin-top:12px">
                        <div class="col stat"><span class="muted">Total Days</span> <b id="pDays">0</b></div>
                        <div class="col stat"><span class="muted">Total Amount</span> <b id="pTotal">0</b></div>
                        <div class="col stat"><span class="muted">Advance</span> <b id="pAdv">0</b></div>
                        <div class="col stat"><span class="muted">Net</span> <b id="pNet">0</b></div>
                    </div>
                    <div style="margin-top:14px" class="help">On first load, all labours are listed below. Use search to
                        pick someone.</div>
                    <div style="margin-top:8px">
                        <table id="personAll">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Designation</th>
                                    <th class="right">Rate</th>
                                    <th class="right">Advance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- personAll will be populated from labour table on boot --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Attendance -->
        <section id="attendance" class="page hid">
            <div class="card">
                <div class="hd"><b>Attendance</b><span class="caps">mark daily hours & overtime</span></div>
                <div class="bd">
                    <div class="toolbar" style="margin-bottom:10px">
                        <input type="month" id="attnMonth" />
                        <select id="attnSiteFilter">
                            <option value="">All Sites</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->site_name }}</option>
                            @endforeach
                        </select>
                        <button class="btn" id="btnAttnPrev">◀ Prev</button>
                        <button class="btn" id="btnAttnNext">Next ▶</button>
                        <button class="btn" id="btnExportAttn">Export CSV</button>
                    </div>
                    <div class="attn" id="attnBoard">
                        <div class="left" id="attnLabours">
                            <div class="days-head" id="attnDays">
                                <div class="d"> Labours
                                </div>
                            </div>
                            @foreach ($labours as $labour)
                                <div class="lab" data-id="{{ $labour->id }}"><span
                                        class="nm">{{ $labour->name }}</span><span
                                        class="muted small">{{ $labour->phone }} · {{ $labour->role }} ·
                                        Rs&nbsp;{{ $labour->daily_wage }}</span></div>
                            @endforeach
                        </div>
                        @php
                            use Carbon\Carbon;

                            $today = Carbon::now();
                            $todayDate = $today->day;
                            $daysInMonth = $today->daysInMonth;
                            $monthStart = $today->copy()->startOfMonth();
                        @endphp

                        <div class="right">
                            {{-- DAYS HEADER --}}
                            <div class="days-head" id="attnDays">
                                @for ($i = 1; $i <= $daysInMonth; $i++)
                                    <div class="d {{ $todayDate == $i ? 'today' : '' }}">
                                        {{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</div>
                                @endfor
                            </div>

                            {{-- LABOUR ROWS --}}
                            <div class="rows" id="attnRows">
                                @foreach ($labours as $labour)
                                    <div class="row-days">
                                        @for ($i = 0; $i < $daysInMonth; $i++)
                                            @php
                                                $date = $monthStart->copy()->addDays($i)->format('Y-m-d');
                                                // Placeholder data (replace later with actual attendance logic)
                                                $status = 'not-marked';
                                                $icon = '-';
                                                $iconClass = 'none';
                                                $tooltip = "{$labour->name} — {$date} Not marked";

                                                $attendance = $labour->attendances()->where('date', $date)->first();
                                                if ($attendance) {
                                                    $status = $attendance->status;
                                                    $icon = $status == 'present' ? '✓' : '✗';
                                                    $iconClass = $status == 'present' ? 'tick' : 'cross';
                                                    if ($attendance?->hours == '4.00') {
                                                        $icon = 'H';
                                                        $iconClass = 'leave';
                                                    }
                                                    $tooltip = "{$labour->name} — {$date} {$status}";
                                                }

                                                $userData = [
                                                    'id' => $labour->id,
                                                    'name' => $labour->name,
                                                    'date' => $date,
                                                    'status' => $status == 'not-marked' ? 'present' : $status,
                                                    'site' => $attendance?->site_id ?? 1,
                                                    'desi' => $labour->role ?? '',
                                                    'rate' => $labour->daily_wage ?? '',
                                                    'hours' => $attendance?->hours == '4.00' ? 4 : 8,
                                                    'ot' => 0,
                                                    'amount' => $attendance?->amount ?? $labour->daily_wage,
                                                ];
                                            @endphp

                                            <div class="cell" title="{{ $tooltip }}"
                                                data-user='{{ json_encode($userData) }}'>
                                                <span class="ico {{ $iconClass }}">{{ $icon }}</span>
                                            </div>
                                        @endfor
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                    <div class="help" style="margin-top:10px">Legend: <span class="ico tick">✓</span> Present · <span
                            class="ico cross">✕</span> Absent · <span class="ico leave">L</span> Leave · <span
                            class="ico none">-</span> Not marked — <b>click any square</b> to mark or edit.</div>
                </div>
            </div>
        </section>
    </div>

    <!-- Attendance Modal -->
    <div class="modal" id="attnModal" aria-hidden="true">
        <div class="panel">
            <div class="hd"><b>Mark Attendance</b><button class="btn ghost" id="btnCloseModal">✕</button></div>
            <form action="{{ route('labours.attendance') }}" method="post" id="addAttendanceForm">
                @csrf
                <div class="bd">
                    <div class="row">
                        <div class="col">
                            <label>Date</label>
                            <input id="mDate" name="date" readonly />
                        </div>
                        <div class="col">
                            <label>Labour</label>
                            <input id="mLabourid" hidden name="labour_id" readonly />
                            <input id="mLabour" disabled />
                        </div>
                        <div class="col">
                            <label>Status</label>
                            <select id="mStatus" name="status">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>Site</label>
                            <select id="mSite" name="site_id">
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->site_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col">
                            <label>Designation</label>
                            <input id="mRole" name="role" />
                        </div>
                        <div class="col">
                            <label>Rate (per 8h)</label>
                            <input id="mRate" type="number" name="rate" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>Shift</label>
                            <select id="mHours" name="hours">
                                <option value="8">Full Day</option>
                                <option value="4">Half Day</option>
                            </select>
                        </div>
                        <div class="col">
                            <label>Overtime (hrs)</label>
                            <input id="mOT" type="number" min="0" max="12" value="0"
                                name="ot_hours" />
                        </div>
                        <div class="col">
                            <label>Amount (auto)</label>
                            <input id="mAmount" disabled name="amount" />
                        </div>
                    </div>
                    <div class="help">Amount = (Rate/8) x (Hours + Overtime). Base day is 8 hours.</div>
                </div>
                <div class="ft">
                    <button class="btn ok" id="btnSaveAttn">Save</button>
                </div>
            </form>

        </div>
    </div>
    <!-- Edit Labour Modal -->
    <div class="modal" id="editLabourModal" aria-hidden="true">
        <div class="panel">
            <div class="hd"><b>Edit Labour</b><button class="btn ghost" id="editBtnCloseModal">✕</button></div>
            <form action="{{ route('labours.update', 1) }}" method="post" id="editLabourForm" style="padding: 20px;">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col"><label>Name</label><input id="editlabName" name="name"
                            placeholder="e.g., John Peter" required />
                        <small class="text-danger error error-name d-none">Name is already exist</small>
                    </div>
                    <div class="col"><label>Father Name</label><input id="editfatherName" name="father_name"
                            placeholder="e.g., John Peter" required />
                        <small class="text-danger error"></small>
                    </div>
                </div>
                <div class="row">
                    <div class="col"><label>Mobile</label><input class="" id="editlabMobile" name="phone"
                            placeholder="0300 1234567" required />
                        <small class="text-danger error error-phone d-none">Mobile is already exist</small>
                    </div>
                    <div class="col"><label>Designation</label><input id="editlabRole" name="role"
                            placeholder="Mason / Helper" required />
                        <small class="text-danger error"></small>
                    </div>

                </div>
                <div class="row">
                    <div class="col"><label>Rate (per 8 hours)</label><input id="editlabRate" name="daily_wage"
                            type="number" placeholder="1000" required />
                        <small class="text-danger error"></small>
                    </div>
                    <div class="col"><label>CNIC</label><input id="editcnic" name="cnic"
                            placeholder="12345-6789012-3" required />
                        <small class="text-danger error error-cnic d-none">CNIC is already exist</small>
                    </div>
                </div>
                <div class="row" style="margin-top:10px">
                    <button class="btn pri" id="editBtnAddLabour">Update Labour</button>
                    <span class="help">Adds into in-memory list for demo. Replace with AJAX to save to
                        server.</span>
                </div>
            </form>
        </div>
    </div>

    {{-- In-memory attendance records stored as JSON in DOM for demo. Initially empty (seeded below by JS). --}}
    <script type="application/json" id="initial-attendance">[]</script>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- jQuery is required. If your admin master already loads jQuery, remove the following script line. --}}
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $('.tab').on('click', function() {
            // Remove active state from all tabs
            $('.tab').attr('aria-selected', 'false');
            // Hide all pages
            $('.page').addClass('hid');

            // Get the tab target (from data-tab)
            const target = $(this).data('tab');

            // Set clicked tab as active
            $(this).attr('aria-selected', 'true');
            // Show the target section
            $('#' + target).removeClass('hid');
        });

        function debounce(func, delay) {
            let timer;
            return function() {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, arguments), delay);
            };
        }

        $('#labName, #labMobile, #cnic').on('keyup', debounce(function() {
            let formData = new FormData();
            let $this = $(this);
            let name = $this.attr('name');
            formData.append(name, $this.val());
            formData.append('_token', '{{ csrf_token() }}'); // REQUIRED

            $.ajax({
                url: "{{ route('labours.check.validate') }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.exists == true) {
                        $this.addClass('validate-border');
                        $('.error-' + name).removeClass('d-none');
                        $('#btnAddLabour').prop('disabled', true);
                    } else {
                        $('.error-' + name).addClass('d-none');
                        $this.removeClass('validate-border');
                        $('#btnAddLabour').prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                }
            });

        }, 500));



        // Initialize first tab (optional safety)
        $('.tab[aria-selected="true"]').trigger('click');
        $('.cell').on('click', function() {
            let data = $(this).attr('data-user');

            let obj = JSON.parse(data);
            $('#mLabourid').val(obj.id);
            $('#mDate').val(obj.date);
            $('#mLabour').val(obj.name);
            $('#mStatus').val(obj.status);
            $('#mSite').val(obj.site);
            $('#mRole').val(obj.desi);
            $('#mRate').val(obj.rate);
            $('#mHours').val(obj.hours);
            $('#mOT').val(obj.ot);
            $('#mAmount').val(obj.amount);

            $('#attnModal').modal('show');
        })
        $('#btnCloseModal').on('click', function() {
            $('#attnModal').modal('hide');
        });
        $('#editBtnCloseModal').on('click', function() {
            $('#editLabourModal').modal('hide');
        });
        $(document).on('click', '.editLabourBtn', function() {

            let labour = $(this).attr('labourData');
            labour = JSON.parse(labour);

            // Fill fields
            $('#editlabName').val(labour.name);
            $('#editfatherName').val(labour.father_name);
            $('#editlabMobile').val(labour.phone);
            $('#editlabRole').val(labour.role);
            $('#editlabRate').val(labour.daily_wage);
            $('#editcnic').val(labour.cnic);

            // Dynamic Form Action
            let updateUrl = "{{ route('labours.update', ':id') }}";
            updateUrl = updateUrl.replace(':id', labour.id);
            $('#editLabourForm').attr('action', updateUrl);

            // Show Modal
            $('#editLabourModal').modal('show');
        });



        // ============================
        // ADD LABOUR
        // ============================
        $('#addLabourForm').on('submit', function(e) {
            e.preventDefault();
            let hasError = false;

            // Clear previous errors
            $('#addLabourForm .error').text('');

            // Check required fields
            $('#addLabourForm [name]').each(function() {
                let field = $(this);
                let value = field.val()?.trim();

                if (field.prop('required') && value === '') {
                    field.siblings('.error').removeClass('d-none').text('This field is required');
                    hasError = true;
                }
            });

            if (hasError) return; // stop submit if validation fails

            // --- Confirm Before Submit ---
            Swal.fire({
                title: "Are you sure?",
                text: "Do you want to save this Labour?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, save it!",
                cancelButtonText: "Cancel",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {

                    let formData = $('#addLabourForm').serialize();

                    $.ajax({
                        url: "{{ route('labours.store') }}",
                        type: "POST",
                        data: formData,

                        success: function(res) {
                            if (res.success) {
                                $('#labourTableBody').html('');
                                $('#labourTableBody').append(res.view);
                                $('#addLabourForm')[0].reset();

                                // --- Success Alert ---
                                Swal.fire({
                                    title: "Saved!",
                                    text: "Labour added successfully.",
                                    icon: "success",
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        },

                        error: function(err) {
                            Swal.fire({
                                title: "Error",
                                text: "Error saving labour.",
                                icon: "error"
                            });
                            console.log(err.responseText);
                        }
                    });

                }
            });
        });
        $('#editLabourForm').on('submit', function(e) {
            e.preventDefault();
            let hasError = false;

            // Clear all previous errors
            $('#editLabourForm .error').text('');

            // Validate required fields
            $('#editLabourForm [name]').each(function() {
                let field = $(this);
                let value = field.val()?.trim();

                if (field.prop('required') && value === '') {
                    field.siblings('.error').removeClass('d-none').text('This field is required');
                    hasError = true;
                }
            });

            if (hasError) return; // stop if validation fails


            // Ask for confirmation
            Swal.fire({
                title: "Are you sure?",
                text: "Do you want to update this Labour?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, update!",
                cancelButtonText: "Cancel",
                reverseButtons: true
            }).then((result) => {

                if (result.isConfirmed) {

                    let formData = $('#editLabourForm').serialize();
                    let actionUrl = $('#editLabourForm').attr('action'); // dynamic URL already set

                    $.ajax({
                        url: actionUrl,
                        type: "POST", // Laravel PUT works with POST + _method
                        data: formData,

                        success: function(res) {
                            if (res.success) {

                                // Reload table
                                $('#labourTableBody').html('');
                                $('#labourTableBody').append(res.view);

                                // Close modal
                                $('#editLabourModal').modal('hide');

                                // Success alert
                                Swal.fire({
                                    title: "Updated!",
                                    text: "Labour updated successfully.",
                                    icon: "success",
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        },

                        error: function(err) {
                            Swal.fire({
                                title: "Error",
                                text: "Error updating labour.",
                                icon: "error"
                            });
                            console.log(err.responseText);
                        }
                    });
                }
            });
        });


        $('#addSiteForm').on('submit', function(e) {
            e.preventDefault();
            let formData = $(this).serialize();
            $.ajax({
                url: "{{ route('labours.sitestore') }}",
                type: "POST",
                data: formData,
                success: function(res) {
                    if (res.success) {
                        $('#siteTableBody').html('');
                        $('#siteTableBody').append(res.view);
                        $('#addSiteForm')[0].reset();
                        alert('Site added successfully!');
                    }
                },
                error: function(err) {
                    alert('Error saving labour.');
                    console.log(err.responseText);
                }
            });
        });

        // ============================
        // EDIT LABOUR
        // ============================
        function editLabour(id) {
            $.ajax({
                url: `/labours/edit/${id}`,
                type: "GET",
                success: function(labour) {
                    $('#editLabourId').val(labour.id);
                    $('#edit_name').val(labour.name);
                    $('#edit_father_name').val(labour.father_name);
                    $('#edit_cnic').val(labour.cnic);
                    $('#edit_contact_no').val(labour.contact_no);
                    $('#edit_trade').val(labour.trade);
                    $('#edit_site').val(labour.site);
                    $('#edit_joining_date').val(labour.joining_date);
                    $('#editLabourModal').modal('show');
                }
            });
        }


        // ============================
        // DELETE LABOUR
        // ============================
        function deleteLabour(id) {
            if (!confirm('Are you sure you want to delete this labour?')) return;

            $.ajax({
                url: `/labours/delete/${id}`,
                type: "DELETE",
                success: function(res) {
                    if (res.success) {
                        loadLabours();
                        alert('Deleted successfully!');
                    }
                },
                error: function(err) {
                    alert('Error deleting!');
                }
            });
        }

        // ============================
        // ATTENDANCE
        // ============================
        function loadAttendance() {
            $.ajax({
                url: "{{ route('labours.index') }}",
                type: "GET",
                success: function(data) {
                    let tbody = $('#attendanceTable tbody');
                    tbody.empty();
                    data.forEach((labour, i) => {
                        tbody.append(`
                        <tr>
                            <td>${i + 1}</td>
                            <td>${labour.name}</td>
                            <td>
                                <select class="form-select attendance-status" data-id="${labour.id}">
                                    <option value="Present">Present</option>
                                    <option value="Absent">Absent</option>
                                    <option value="Leave">Leave</option>
                                </select>
                            </td>
                        </tr>
                    `);
                    });
                }
            });
        }

        $('#addAttendanceForm').on('submit', function(e) {
            e.preventDefault();

            let formData = $(this).serialize();

            $.ajax({
                url: "{{ route('labours.attendance') }}",
                type: "POST",
                data: formData,
                success: function(res) {
                    if (res.success) {
                        alert('Attendance saved!');

                        // Optional: highlight updated cell visually
                        let labourId = res.data.labour_id;
                        let date = res.data.date;
                        let status = res.data.status;
                        let hours = res.data.hours;

                        // Find matching cell by labour and date
                        $(`.cell[data-user*='"id":${labourId}'][data-user*='"date":"${date}"']`).each(
                            function() {
                                let iconSpan = $(this).find('.ico');
                                if (status === 'present' && hours === '8') {
                                    iconSpan.text('✓')
                                        .removeClass('cross none leave')
                                        .addClass('tick');
                                } else if (status === 'absent') {
                                    iconSpan.text('✗')
                                        .removeClass('tick none leave')
                                        .addClass('cross');
                                } else if (hours === '4' || status === 'half' || status ===
                                    'leave') {
                                    // Half-day or leave condition
                                    iconSpan.text('H')
                                        .removeClass('tick cross none')
                                        .addClass('leave');
                                } else {
                                    // Default: not marked
                                    iconSpan.text('-')
                                        .removeClass('tick cross leave')
                                        .addClass('none');
                                }


                                // Optional visual feedback (brief highlight)
                                $(this).addClass('updated');
                                setTimeout(() => $(this).removeClass('updated'), 1500);
                            });
                    }
                    $('#attnModal').modal('hide');
                },
                error: function() {
                    alert('Error saving attendance!');
                }
            });
        });
        $('#btnSiteRun').on('click', function(e) {
            e.preventDefault();
            let from = $('#repFrom').val();
            let to = $('#repTo').val();
            let site = $('#repSite').val();
            let data = {
                '_token': "{{ csrf_token() }}",
                'start_date': from,
                'end_date': to,
                'site_id': site
            }
            let formData = $(this).serialize();

            $.ajax({
                url: "{{ route('labours.report') }}",
                type: "POST",
                data: data,
                success: function(res) {
                    if (res.success) {
                        $('#siteReportTableBody').html('');
                        $('#siteReportTableBody').append(res.view);
                    }
                },
                error: function() {
                    alert('Error saving attendance!');
                }
            });
        });


        // ============================
        // REPORTS
        // ============================
        function loadReports() {
            $.ajax({
                url: "{{ route('labours.report') }}",
                type: "GET",
                success: function(data) {
                    let tbody = $('#reportTable tbody');
                    tbody.empty();
                    data.forEach((row, i) => {
                        tbody.append(`
                        <tr>
                            <td>${i + 1}</td>
                            <td>${row.labour.name}</td>
                            <td>${row.date}</td>
                            <td>${row.status}</td>
                        </tr>
                    `);
                    });
                },
                error: function(err) {
                    alert('Failed to load report');
                }
            });
        }

        // ============================
        // INIT ON PAGE LOAD
        // ============================
        $(document).ready(function() {
            // loadLabours();
            // loadAttendance();
            // loadReports();
            // Fetch Sub-Accounts dynamically via AJAX when account is selected
            $(document).on('change', '#accounts_id', function() {
                const accountID = $(this).val();
                const subAccountSelect = $('#subaccounts_id');

                if (accountID) {
                    $.ajax({
                        url: '{{ route('get_account') }}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: {{ getSelectedTown() }}, // Replace with the dynamic project ID if applicable
                            accountID: accountID,
                            action: 'get_child',
                            _token: '{{ csrf_token() }}' // CSRF token for Laravel
                        },
                        success: function(response) {
                            // Clear and populate the sub-account dropdown
                            subAccountSelect.empty().append(
                                '<option value="">Select Sub-Account</option>');

                            if (response.length > 0) {
                                $.each(response, function(index, item) {
                                    if (item.subhead_accounting) {
                                        subAccountSelect.append('<option value="' + item
                                            .id + '">' + item.subhead_accounting
                                            .name + '</option>');
                                    }
                                });
                            } else {
                                alert('No sub-accounts found for the selected account.');
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('Error: ' + error);
                        }
                    });
                } else {
                    // Reset the sub-account dropdown if no account is selected
                    subAccountSelect.empty().append('<option value="">Select Sub-Account</option>');
                }
            });
        });
    </script>
@endsection
