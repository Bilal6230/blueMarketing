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
            box-shadow: var(--shadow);
            height: 580px;
            overflow: hidden;
        }

        .table-scroll {
            /* max-height: 350px; */
            /* adjust height */
            overflow-y: auto;
            border: 1px solid #ddd;
            /* border-radius: 14px; */
        }

        .max-height {
            max-height: 350px;
            /* adjust height */
            /* border-radius: 14px; */
        }

        .table-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .table-scroll::-webkit-scrollbar-thumb {
            background: #aaa;
            border-radius: 10px;
        }


        .table thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
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
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 10px
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
            grid-auto-columns: 145px;
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
            grid-auto-columns: 145px
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

        #createVoucherForm {
            display: inline-block;

        }

        thead {
            /* display: table; */
            width: 100%;
            table-layout: fixed;
            background: #f1f1f1;
            /* optional */
            position: sticky;
            top: 0;
            z-index: 10;
        }


        .btns {
            display: flex;
            width: 100%;
            justify-content: space-between;
        }

        .site-report-actions {
            display: flex;
            justify-content: space-between;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e74c3c;
            /* red for unpaid */
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #2ecc71;
            /* green for paid */
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .paid-label {
            margin-left: 8px;
            font-weight: bold;
        }

        .star-rating {
            font-size: 28px;
            cursor: pointer;
            color: #ccc;
        }

        .star-rating .selected {
            color: gold;
        }

        .star-rating span:hover,
        .star-rating span:hover~span {
            color: #ccc !important;
        }

        .star-rating span:hover,
        .star-rating span:hover~span {
            color: gold !important;
        }

        .stars {
            --star-size: 24px;
            --star-color: #ccc;
            --star-fill: gold;
            --percent: calc(var(--rating) / 5 * 100%);

            display: inline-block;
            font-size: var(--star-size);
            font-family: Times;
            line-height: 1;

            background:
                linear-gradient(90deg,
                    var(--star-fill) var(--percent),
                    var(--star-color) var(--percent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stars::before {
            content: "★★★★★";
        }

        .labour-tooltip {
            position: relative;
            cursor: pointer;
            display: inline-block;
        }

        /* Tooltip box */
        .labour-tooltip:hover::after {
            content: attr(data-tooltip);
            white-space: pre-line;

            position: absolute;
            top: 120%;
            left: 50%;
            transform: translateX(-50%);

            background: #1f2937;
            color: #fff;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.4;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            z-index: 999;

            width: max-content;
            max-width: 250px;
        }

        /* Tooltip arrow */
        .labour-tooltip:hover::before {
            content: '';
            position: absolute;
            top: 110%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-bottom-color: #1f2937;
        }

        .ts-dropdown,
        .ts-dropdown.single,
        .ts-dropdown.multi {
            z-index: 99999 !important;
        }

        /* Sometimes needed if a parent creates a stacking context */
        .ts-wrapper {
            position: relative;
            z-index: 99999;
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
        @php
            use Carbon\Carbon;
            [$start, $end, $weekDays] = loadAttendanceWeek($week ?? now()->format('Y-m-d'));
        @endphp

        <!-- Add Labour -->
        <section id="addLabour" class="page">
            <div class="grid cols-2">
                <div class="card">
                    <div class="hd"><b>Add a Labour</b></div>
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
                                id="labCount">{{ $count }}</span>
                            total</span>
                    </div>
                    <div class="bd table-scroll">
                        <!-- Dummy labour rows moved to HTML. Javascript will parse these rows on boot. -->
                        <table id="labourTable table">
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
                        <form action="{{ route('labours.sitestore') }}" method="post" class="addSiteForm">
                            @csrf
                            <div class="row">
                                <div class="col">
                                    <label>Head Accounts</label>
                                    <select class="form-control select2" name="accounts_id" id="accounts_id">
                                        <option value="">Select Head</option>
                                        @foreach ($headaccounts as $head)
                                            <option value="{{ $head->head_accounting_id }}">
                                                {{ $head->headAccounting->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-danger error"></small>
                                    @error('accounts_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col">
                                    <label>Sub Head Accounts</label>
                                    <select class="form-control select2" name="subaccounts_id" id="subaccounts_id">
                                        <option value="">Select Sub Head</option>
                                    </select>
                                    <small class="text-danger error"></small>
                                    @error('subaccounts_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col"><label>Site Name</label><input id="siteName" name="site_name"
                                        placeholder="e.g., Green Villas" />
                                    <small class="text-danger error"></small>
                                </div>
                                <div class="col"><label>Address</label><input id="siteAddr" name="site_address"
                                        placeholder="Street, City" />
                                    <small class="text-danger error"></small>
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
                    <div class="bd table-scroll">
                        <table id="siteTable table">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th>Address</th>
                                    <th>Edit</th>
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
                    <div class="row" style="margin-bottom:12px">
                        <div class="col-3">
                            <select id="repSite" class=" js-tomselect">
                                <option value="">All Sites</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->site_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 align-items-center mb-2">
                            <div class="row">
                                <div class="col-8">
                                    <input type="week" id="repFrom" class="form-control" />
                                    <label id="weekLabel" class="fw-bold text-primary" style="font-size: 14px"></label>
                                </div>
                                <div class="col-4 text-end">
                                    <button type="button" id="prevWeek" class="btn btn-sm btn-outline-secondary">←
                                        Prev</button>
                                    <button type="button" id="nextWeek" class="btn btn-sm btn-outline-secondary">Next
                                        →</button>
                                </div>
                            </div>
                        </div>

                        {{-- <input type="week" id="repFrom" class="col-6 form-control"
                            value="{{ now()->format('o-\WW') }}" /> --}}
                    </div>
                    <div class="site-report-actions">
                        <div class="site-report-btns">
                            <button class="btn" id="reportBtnSiteRun">Report</button>
                            <button class="btn" id="btnSiteExport">Export CSV</button>
                        </div>
                        <div class="site-report-voucher-btns">
                            <button class="btn" id="voucherBtnSiteRun">Run</button>
                            <form action="" method="post" id="createVoucherForm">
                                @csrf
                                <input type="hidden" name="attendance_ids" id="attendance_ids" required>
                                <input type="hidden" name="detail" id="detail">
                                <input type="hidden" name="site_id" id="site_id" required>
                                <input type="hidden" name="amount" id="amount" required>
                                <button class="btn" id="createVoucher">Create Voucher</button>
                            </form>
                        </div>
                    </div>
                    <div class="chips">
                        <span class="pill">Total Days: <b id="siteDays">0</b></span>
                        <span class="pill">Total Overtime Hrs: <b id="siteOt">0</b></span>
                        <span class="pill">Total Cost: <b id="siteTotal">0</b></span>
                    </div>
                    <div class="table-scroll" style="max-height: 290px;">
                        <table id="siteReportTable table">
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
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Person Report -->
        <section id="personReport" class="page hid">
            <div class="card">
                <div class="hd"><b>Person Wise Report</b><span class="caps">details & totals</span></div>
                <div class="bd">
                    <div class="row" style="margin-bottom:12px">
                        <div class="col-4">
                            <input id="personQuery" class="" placeholder="Search by name or mobile"
                                style="min-width:260px" />
                            <label class="fw-bold text-primary" style="font-size: 14px"></label>
                        </div>
                        <div class="col-4">
                            <input type="week" id="pFrom" class="form-control"
                                value="{{ now()->format('o-\WW') }}" />
                            <label class="fw-bold text-primary pForm-weekLabel" style="font-size: 14px"></label>
                        </div>
                        <div class="col-4 text-end">
                            <button type="button" id="prevWeek_pFrom" class="btn btn-sm btn-outline-secondary">←
                                Prev</button>
                            <button type="button" id="nextWeek_pFrom" class="btn btn-sm btn-outline-secondary">Next
                                →</button>
                        </div>

                    </div>
                    <div class="site-report-actions">
                        <button class="btn" id="btnPersonRun">Run</button>
                        <button class="btn" id="btnPersonExport">Export CSV</button>
                    </div>
                    <div id="personHeader" class="row" style="margin-bottom:10px"></div>
                    <div style="margin-top:14px" class="help">On first load, all labours are listed below. Use search to
                        pick someone.</div>
                    <div style="margin-top:8px" class="table-scroll max-height">
                        <table id="personAll table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Designation</th>
                                    <th>Rate</th>
                                    <th>Days</th>
                                    <th>Over Time</th>
                                    {{-- <th>status</th> --}}
                                    <th>Ratings</th>
                                    <th>Amount</th>
                                    <th>Over All Balance</th>
                                    <th>Payments</th>
                                </tr>
                            </thead>
                            <tbody id="personAllBody"> @include('admin.labours.person-wise-report') {{-- personAll will be
                                populated from labour table on boot --}} </tbody>
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
                    <div class="row" style="margin-bottom:12px">
                        <div class="col-3">
                            <select id="attnSiteFilter" class=" js-tomselect">
                                <option value="">All Sites</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->site_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-9 align-items-center mb-2">
                            <div class="row">
                                <div class="col-4">
                                    <input id="attnSearch" class="form-control" placeholder="Search by name or mobile" />
                                </div>
                                <div class="col-4">

                                    {{-- //labur --}}
                                    <input type="week" id="attnWeek" class="form-control"
                                        value="{{ now()->format('o-\WW') }}" />
                                    <label id="lab-weekLabel" class="fw-bold text-primary"
                                        style="font-size: 14px"></label>
                                </div>
                                <div class="col-4 text-end">
                                    <button type="button" id="btnAttnPrev" class="btn btn-sm btn-outline-secondary">←
                                        Prev</button>
                                    <button type="button" id="btnAttnNext" class="btn btn-sm btn-outline-secondary">Next
                                        →</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        id="btnExportAttn">Export
                                        CSV</button>

                                </div>
                            </div>
                        </div>

                        {{-- <input type="week" id="repFrom" class="col-6 form-control"
                            value="{{ now()->format('o-\WW') }}" /> --}}
                    </div>
                    <div class="table-scroll max-height" id="attnBoard">
                        @include('admin.labours.attn-board')
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
            <div class="hd"><b>Mark Attendance</b><button class=" btn ghost" id="btnCloseModal">✕</button></div>
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
                            <input id="mRole" disabled name="role" />
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

                    <!-- ⭐ NEW RATING FIELD -->
                    <div class="row">
                        <div class="col">
                            <label>Rating</label>
                            <div class="star-rating">
                                <span data-value="1">★</span>
                                <span data-value="2">★</span>
                                <span data-value="3">★</span>
                                <span data-value="4">★</span>
                                <span data-value="5">★</span>
                            </div>
                            <input type="hidden" name="ratings" id="mRating" value="3"> <!-- default 3 -->
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
            <div class="hd"><b>Edit Labour</b><button class=" btn ghost" id="editBtnCloseModal">✕</button></div>
            <form action="{{ route('labours.update', 1) }}" method="post" id="editLabourForm" style="padding: 20px;">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col"><label>Name</label><input id="editlabName" name="name"
                            placeholder="e.g., John Peter" required />
                        {{-- <small class="text-danger error error-name d-none">Name is already exist</small> --}}
                    </div>
                    <div class="col"><label>Father Name</label><input id="editfatherName" name="father_name"
                            placeholder="e.g., John Peter" required />
                        {{-- <small class="text-danger error"></small> --}}
                    </div>
                </div>
                <div class="row">
                    <div class="col"><label>Mobile</label><input class="" id="editlabMobile" name="phone"
                            placeholder="0300 1234567" required />
                        {{-- <small class="text-danger error error-phone d-none">Mobile is already exist</small> --}}
                    </div>
                    <div class="col"><label>Designation</label><input id="editlabRole" name="role"
                            placeholder="Mason / Helper" required />
                        {{-- <small class="text-danger error"></small> --}}
                    </div>

                </div>
                <div class="row">
                    <div class="col"><label>Rate (per 8 hours)</label><input id="editlabRate" name="daily_wage"
                            type="number" placeholder="1000" required />
                        {{-- <small class="text-danger error"></small> --}}
                    </div>
                    <div class="col"><label>CNIC</label><input id="editcnic" name="cnic"
                            placeholder="12345-6789012-3" required />
                        {{-- <small class="text-danger error error-cnic d-none">CNIC is already exist</small> --}}
                    </div>
                </div>
                <div class="row">
                    <div class="col"><label>Advance</label><input id="editlabadvance" name="advance" type="number"
                            placeholder="1000" />
                        {{-- <small class="text-danger error"></small> --}}
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
    <!-- Edit Site Modal -->
    <div class="modal" id="editSiteModal" aria-hidden="true">
        <div class="panel">
            <div class="hd"><b>Edit Site</b><button class=" btn ghost" id="editSiteBtnCloseModal">✕</button></div>
            <form action="{{ route('labours.sitestore') }}" method="post" class="addSiteForm" style="padding: 20px;">
                @csrf
                <div class="row">
                    <div class="col">
                        <input type="hidden" name="site_id" id="edit_site_id">
                        <label>Head Accounts</label>
                        <select class="form-control select2" name="accounts_id" id="edit_accounts_id">
                            <option value="">Select Head</option>
                            @foreach ($headaccounts as $head)
                                <option value="{{ $head->head_accounting_id }}">
                                    {{ $head->headAccounting->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-danger error"></small>
                        @error('accounts_id')
                            <div class="text-danger">{{ $message }}
                            </div>
                        @enderror
                    </div>
                    <div class="col">
                        <label>Sub Head Accounts</label>
                        <select class="form-control select2" name="subaccounts_id" id="edit_subaccounts_id">
                            <option value="">Select Sub Head</option>
                        </select>
                        <small class="text-danger error"></small>
                        @error('subaccounts_id')
                            <div class="text-danger">{{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col"><label>Site Name</label><input id="editSiteName" name="site_name"
                            placeholder="e.g., Green Villas" />
                        <small class="text-danger error"></small>
                    </div>
                    <div class="col"><label>Address</label><input id="editSiteAddr" name="site_address"
                            placeholder="Street, City" />
                        <small class="text-danger error"></small>
                    </div>
                </div>
                <div class="row" style="margin-top:10px">
                    <button class="btn pri" id="btnUpdateSite">Update Site</button>
                    <span class="help">Adds into DOM. Replace with AJAX to persist.</span>
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
        $(document).ready(function() {
            let repSiteSelect = new TomSelect('#repSite', {
                create: false,
                allowEmptyOption: true,
                placeholder: 'All Sites',
                maxOptions: 500,
                onInitialize() {
                    this.clear(); // default to "All Sites"
                }
            });
            let repSiteSelect1 = new TomSelect('#attnSiteFilter', {
                create: false,
                allowEmptyOption: true,
                placeholder: 'All Sites',
                maxOptions: 500,
                onInitialize() {
                    this.clear(); // default to "All Sites"
                }
            });

            function getWeekDates(year, week) {
                const simple = new Date(year, 0, 1 + (week - 1) * 7);
                const dow = simple.getDay();
                const ISOweekStart = simple;

                if (dow <= 4)
                    ISOweekStart.setDate(simple.getDate() - simple.getDay() + 1);
                else
                    ISOweekStart.setDate(simple.getDate() + 8 - simple.getDay());

                const start = new Date(ISOweekStart);
                const end = new Date(ISOweekStart);
                end.setDate(start.getDate() + 6);

                return {
                    start,
                    end
                };
            }

            function updateWeekLabel(value) {
                if (!value) return;

                const [year, week] = value.split('-W');
                const {
                    start,
                    end
                } = getWeekDates(parseInt(year), parseInt(week));

                const options = {
                    day: '2-digit',
                    month: 'short'
                };

                const startText = start.toLocaleDateString('en-GB', options);
                const endText = end.toLocaleDateString('en-GB', options);
                $('#weekLabel').text(
                    `Week ${week}, ${year} (${startText} – ${endText})`
                );
            }

            function updateWeekLabel_pForm(value) {
                if (!value) return;

                const [year, week] = value.split('-W');
                const {
                    start,
                    end
                } = getWeekDates(parseInt(year), parseInt(week));

                const options = {
                    day: '2-digit',
                    month: 'short'
                };

                const startText = start.toLocaleDateString('en-GB', options);
                const endText = end.toLocaleDateString('en-GB', options);
                $('.pForm-weekLabel').text(
                    `Week ${week}, ${year} (${startText} – ${endText})`
                );
            }

            function updateWeekLabel_lab(value) {
                if (!value) return;

                const [year, week] = value.split('-W');
                const {
                    start,
                    end
                } = getWeekDates(parseInt(year), parseInt(week));

                const options = {
                    day: '2-digit',
                    month: 'short'
                };

                const startText = start.toLocaleDateString('en-GB', options);
                const endText = end.toLocaleDateString('en-GB', options);
                $('#lab-weekLabel').text(
                    `Week ${week}, ${year} (${startText} – ${endText})`
                );
            }

            function getFridayWeekInfo(date = new Date()) {
                const d = new Date(date);
                d.setHours(0, 0, 0, 0);

                // 0=Sun ... 5=Fri ... 6=Sat
                const day = d.getDay();

                // How many days to go back to Friday
                const diffToFriday = (day >= 5) ? day - 5 : day + 2;

                const weekStart = new Date(d);
                weekStart.setDate(d.getDate() - diffToFriday);

                const yearStart = new Date(weekStart.getFullYear(), 0, 1);
                yearStart.setHours(0, 0, 0, 0);

                const weekNumber = Math.floor(
                    (weekStart - yearStart) / (7 * 86400000)
                ) + 1;

                return {
                    year: weekStart.getFullYear(),
                    week: weekNumber
                };
            }

            function getWeekDates(year, week) {
                const yearStart = new Date(year, 0, 1);
                yearStart.setHours(0, 0, 0, 0);

                // Move to first Friday of the year
                const firstFriday = new Date(yearStart);
                const day = firstFriday.getDay(); // 0–6
                const offset = (day <= 5) ? 5 - day : 12 - day;
                firstFriday.setDate(firstFriday.getDate() + offset);

                // Calculate week start
                const start = new Date(firstFriday);
                start.setDate(firstFriday.getDate() + (week - 1) * 7);

                // Week ends on Thursday
                const end = new Date(start);
                end.setDate(start.getDate() + 6);

                return {
                    start,
                    end
                };
            }

            const weekInput = $('#repFrom');
            const weekInput_pFrom = $('#pFrom');
            const weekInput_labFrom = $('#attnWeek');

            const {
                year,
                week
            } = getFridayWeekInfo();

            const currentWeek = `${year}-W${week.toString().padStart(2, '0')}`;

            weekInput.val(currentWeek).attr('value', currentWeek);
            weekInput_pFrom.val(currentWeek).attr('value', currentWeek);
            weekInput_labFrom.val(currentWeek).attr('value', currentWeek);

            updateWeekLabel(currentWeek);
            updateWeekLabel_pForm(currentWeek);
            updateWeekLabel_lab(currentWeek);


            weekInput.on('change', function() {
                updateWeekLabel(this.value);
            });
            weekInput_pFrom.on('change', function() {
                updateWeekLabel_pForm(this.value);
            });
            weekInput_labFrom.on('change', function() {
                updateWeekLabel_lab(this.value);
            });

            function changeWeekSite(step) {
                let value = weekInput.val();
                if (!value) return;

                let [year, week] = value.split('-W');
                year = parseInt(year);
                week = parseInt(week) + step;

                if (week < 1) {
                    week = 52;
                    year--;
                } else if (week > 52) {
                    week = 1;
                    year++;
                }

                const newValue = `${year}-W${week.toString().padStart(2, '0')}`;
                weekInput.val(newValue).trigger('change');
            }

            function changeWeekSite_pForm(step) {
                let value = weekInput_pFrom.val();
                if (!value) return;

                let [year, week] = value.split('-W');
                year = parseInt(year);
                week = parseInt(week) + step;

                if (week < 1) {
                    week = 52;
                    year--;
                } else if (week > 52) {
                    week = 1;
                    year++;
                }

                const newValue = `${year}-W${week.toString().padStart(2, '0')}`;
                weekInput_pFrom.val(newValue).trigger('change');
            }

            function changeWeekSite_lab(step) {
                let value = weekInput_labFrom.val();
                if (!value) return;

                let [year, week] = value.split('-W');
                year = parseInt(year);
                week = parseInt(week) + step;

                if (week < 1) {
                    week = 52;
                    year--;
                } else if (week > 52) {
                    week = 1;
                    year++;
                }

                const newValue = `${year}-W${week.toString().padStart(2, '0')}`;
                weekInput_labFrom.val(newValue).trigger('change');
            }

            $('#prevWeek').on('click', () => changeWeekSite(-1));
            $('#nextWeek').on('click', () => changeWeekSite(1));
            $('#prevWeek_pFrom').on('click', () => changeWeekSite_pForm(-1));
            $('#nextWeek_pFrom').on('click', () => changeWeekSite_pForm(1));
            $('#btnAttnPrev').on('click', () => changeWeekSite_lab(-1));
            $('#btnAttnNext').on('click', () => changeWeekSite_lab(1));


            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $(document).on('click', '.star-rating span', function() {
                let rating = $(this).data('value');

                // Update hidden input
                $('#mRating').val(rating);

                // Update colors
                $('.star-rating span').removeClass('selected');
                $('.star-rating span').each(function() {
                    if ($(this).data('value') <= rating) {
                        $(this).addClass('selected');
                    }
                });
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
            $(document).on('click', '#createVoucher', function(e) {
                e.preventDefault();
                let hasError = false;

                // Clear previous errors
                $('#createVoucherForm .error').text('');

                // Check required fields
                $('#createVoucherForm [name]').each(function() {
                    let field = $(this);
                    let value = field.val()?.trim();

                    if (field.prop('required') && value === '') {
                        field.siblings('.error').removeClass('d-none').text(
                            'This field is required');
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

                        let formData = $('#createVoucherForm').serialize();

                        $.ajax({
                            url: "{{ route('labours.create.voucher') }}",
                            type: "POST",
                            data: formData,

                            success: function(res) {
                                if (res.success) {
                                    // --- Success Alert ---
                                    Swal.fire({
                                        title: "Saved!",
                                        text: "Voucher created successfully.",
                                        icon: "success",
                                        timer: 1500,
                                        showConfirmButton: false
                                    });
                                        // ✅ Clear form
                                    $('#createVoucherForm')[0].reset();

                                    // ✅ Clear validation errors (if any)
                                    $('#createVoucherForm .error')
                                        .text('')
                                        .addClass('d-none');

                                    // Optional: clear table
                                    $('#siteReportTableBody').html('');

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
            })
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
            $(document).on('click', '.cell', function() {


                let data = $(this).attr('data-user');
                if (!data) return; // safety

                let obj;

                try {
                    obj = JSON.parse(data);
                } catch (e) {
                    console.error("Invalid JSON data-user:", data);
                    return;
                }

                // Determine site (priority: record → filter → default:1)
                let site = obj.site && obj.site != "" ?
                    obj.site :
                    ($('#attnSiteFilter').val() || 1);

                // Fill modal fields
                $('#mLabourid').val(obj.id);
                $('#mDate').val(obj.date);
                $('#mLabour').val(obj.name);
                $('#mStatus').val(obj.status);
                $('#mSite').val(site);
                $('#mRole').val(obj.role);
                $('#mRate').val(obj.rate);
                $('#mHours').val(parseFloat(obj.hours) || 0);
                $('#mOT').val(obj.ot || 0);
                $('#mAmount').val(obj.amount || obj.rate);

                // Show modal
                $('#attnModal').modal('show');
            });

            $('#btnCloseModal').on('click', function() {
                $('#attnModal').modal('hide');
            });
            $('#editBtnCloseModal').on('click', function() {
                $('#editLabourModal').modal('hide');
            });
            $('#editSiteBtnCloseModal').on('click', function() {
                $('#editSiteModal').modal('hide');
            });
            $(document).on('change', '#mHours', function() {
                let hours = $(this).val();
                let ot = $('#mOT').val();
                hours = parseFloat(hours) + parseFloat(ot);
                let rate = $('#mRate').val();
                let perHour = rate / 8;
                let amount = hours * perHour;
                $('#mAmount').val(amount);
            })
            $(document).on('input', '#mOT', function() {
                let hours = $(this).val();
                let ot = $('#mHours').val();
                hours = parseFloat(hours) + parseFloat(ot);
                let rate = $('#mRate').val();
                let perHour = rate / 8;
                let amount = hours * perHour;
                $('#mAmount').val(amount);
            })
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
                $('#editlabadvance').val(labour.advance);

                // Dynamic Form Action
                let updateUrl = "{{ route('labours.update', ':id') }}";
                updateUrl = updateUrl.replace(':id', labour.id);
                $('#editLabourForm').attr('action', updateUrl);

                // Show Modal
                $('#editLabourModal').modal('show');
            });
            $(document).on('click', '.editSiteBtn', function() {

                let site = $(this).attr('SiteData');
                site = JSON.parse(site);

                // Fill fields
                $('#edit_site_id').val(site.id);
                $('#editSiteName').val(site.site_name);
                $('#editSiteAddr').val(site.site_address);
                $('#edit_accounts_id').val(site.head_accounting_id).trigger('change');
                setTimeout(() => {
                    $('#edit_subaccounts_id').val(site.subhead_accounting_id).trigger('change');
                }, 1000);
                $('#editSiteModal').modal('show');
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
                        field.siblings('.error').removeClass('d-none').text(
                            'This field is required');
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
                        field.siblings('.error').removeClass('d-none').text(
                            'This field is required');
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
                        let actionUrl = $('#editLabourForm').attr(
                            'action'); // dynamic URL already set

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


            $(document).on('click', '#btnAddSite, #btnUpdateSite', function(e) {
                e.preventDefault();

                let btn = $(this); // clicked button
                let form = btn.closest('form'); // get respective form
                let actionType = btn.attr('id'); // btnAddSite or btnUpdateSite

                let hasError = false;

                // Clear previous errors
                form.find('.error').text('');

                // Validate required fields
                form.find('[name]').each(function() {
                    let field = $(this);
                    let value = field.val()?.trim();

                    if (value === '') {
                        field.siblings('.error').removeClass('d-none').text(
                            'This field is required');
                        hasError = true;
                    }
                });

                if (hasError) return;

                // Confirmation message depends on button
                let confirmText = actionType === 'btnAddSite' ?
                    "Do you want to add this site?" :
                    "Do you want to update this site?";

                Swal.fire({
                    title: "Are you sure?",
                    text: confirmText,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, continue!",
                    cancelButtonText: "Cancel",
                    reverseButtons: true
                }).then((result) => {

                    if (result.isConfirmed) {

                        let formData = form.serialize();

                        $.ajax({
                            url: "{{ route('labours.sitestore') }}", // you can change based on button too
                            type: "POST",
                            data: formData,
                            success: function(res) {
                                if (res.success) {

                                    $('#siteTableBody').html('');
                                    $('#siteTableBody').append(res.view);
                                    form[0].reset();
                                    actionType === 'btnUpdateSite' ?
                                        $('#editSiteModal').modal('hide') :
                                        "";
                                    Swal.fire({
                                        title: "Success!",
                                        text: actionType === 'btnAddSite' ?
                                            "Site added successfully." :
                                            "Site updated successfully.",
                                        icon: "success",
                                        timer: 1500,
                                        showConfirmButton: false
                                    });
                                }
                            },
                            error: function(err) {
                                Swal.fire("Error", "Something went wrong.", "error");
                                console.log(err.responseText);
                            }
                        });
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

                            let row = res.data;

                            // Find cell using safer selectors
                            let cell = $(
                                `.cell[data-id="${row.labour_id}"][data-date="${row.date}"]`
                            );

                            if (cell.length) {

                                // Determine icon + class
                                let icon = '-';
                                let iconClass = 'none';

                                if (row.hours == 4 || row.status === 'leave') {
                                    icon = 'H';
                                    iconClass = 'leave';
                                } else if (row.status === 'present') {
                                    icon = '✓';
                                    iconClass = 'tick';
                                } else if (row.status === 'absent') {
                                    icon = '✗';
                                    iconClass = 'cross';
                                }

                                // Update main icon
                                let mainIcon = cell.find('.ico').first();
                                mainIcon.text(icon)
                                    .removeClass('tick cross leave none')
                                    .addClass(iconClass);

                                // Remove old OT badge
                                cell.find('.ot-badge').remove();

                                // Add new OT badge if OT > 0
                                if (row.ot_hours > 0) {
                                    cell.append(
                                        `<span class="ico tick ot-badge" style="margin-left:3px">${parseFloat(row.ot_hours)}</span>`
                                    );
                                }

                                // Update stored JSON inside data-user
                                let updatedData = {
                                    id: row.labour_id,
                                    name: row.name,
                                    role: row.role,
                                    date: row.date,
                                    status: row.status,
                                    hours: row.hours,
                                    ot: parseFloat(row.ot_hours),
                                    site: row.site_id,
                                    rate: row.rate,
                                    amount: row.amount,
                                };

                                cell.attr("data-user", JSON.stringify(updatedData));

                                // highlight cell
                                cell.addClass("updated");
                                setTimeout(() => cell.removeClass("updated"), 1500);
                            }
                        }

                        $('#attnModal').modal('hide');
                    },
                    error: function() {
                        alert('Error saving attendance!');
                    }
                });
            });
            $(document).on('click', '#reportBtnSiteRun, #voucherBtnSiteRun', function(e) {
                e.preventDefault();

                $('#site_id').val('');
                $('#attendance_ids').val('');
                $('#detail').val('');
                $('#amount').val('');

                let id = $(this).attr('id');
                let week = $('#repFrom').val();
                let site = $('#repSite').val();

                let data = {
                    _token: "{{ csrf_token() }}",
                    week: week,
                    site_id: site,
                    id: id
                };

                $.ajax({
                    url: "{{ route('labours.report') }}",
                    type: "POST",
                    data: data,
                    success: function(res) {
                        if (res.success) {
                            if (res.attendanceIds.length === 0) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'No Attendance',
                                    text: 'No attendance records found for the selected week and site.',
                                    timer: 2000
                                });
                                return;
                            }
                            $('#siteReportTableBody').html('');
                            $('#siteReportTableBody').append(res.view);

                            if (id === 'voucherBtnSiteRun') {
                                $('#site_id').val(res.site_id);
                                $('#attendance_ids').val(res.attendanceIds);
                                $('#detail').val(res.reports);
                                $('#amount').val(res.total_amount);
                            }
                        }
                    },
                    error: function(xhr) {

                        let message = 'Something went wrong.';

                        // Laravel validation error (422)
                        if (xhr.status === 422 && xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }

                            if (xhr.responseJSON.errors) {
                                message = '';
                                $.each(xhr.responseJSON.errors, function(key, value) {
                                    message += value[0] + '<br>';
                                });
                            }
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            html: message,
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });

            $(document).on('click', '#btnPersonRun', function(e) {
                e.preventDefault();

                let week = $('#pFrom').val();
                let search = $('#personQuery').val();

                let data = {
                    _token: "{{ csrf_token() }}",
                    week: week,
                    search: search,
                };

                $.ajax({
                    url: "{{ route('labours.person.report') }}",
                    type: "POST",
                    data: data,

                    success: function(res) {

                        // ✅ No records case (Total = 0)
                        if (res.success && res.view && res.view.includes('<td>0</td>')) {
                            $('#personAllBody').html('');

                            Swal.fire({
                                icon: 'info',
                                title: 'No Records Found',
                                text: 'No labour record found for the selected week.',
                                confirmButtonText: 'OK'
                            });

                            return;
                        }

                        // ✅ Normal success
                        if (res.success) {
                            $('#personAllBody').html('');
                            $('#personAllBody').append(res.view);
                        }
                    },

                    error: function(xhr) {
                        let message = 'Something went wrong.';

                        // ✅ Laravel validation error
                        if (xhr.status === 422 && xhr.responseJSON) {

                            if (xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }

                            if (xhr.responseJSON.errors) {
                                message = '';
                                $.each(xhr.responseJSON.errors, function(key, value) {
                                    message += value[0] + '\n';
                                });
                            }
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: message,
                            confirmButtonText: 'OK'
                        });
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
            $(document).on('change', '#edit_accounts_id', function() {
                const accountID = $(this).val();
                const subAccountSelect = $('#edit_subaccounts_id');

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

            // function changeWeek(offset) {
            //     let current = $('#attnWeek').val(); // "2025-W05"
            //     if (!current) return;

            //     let [year, week] = current.split('-W');
            //     week = parseInt(week) + offset;

            //     if (week < 1) {
            //         year--;
            //         week = 52;
            //     }
            //     if (week > 52) {
            //         year++;
            //         week = 1;
            //     }

            //     let newWeek = `${year}-W${String(week).padStart(2, '0')}`;
            //     $('#attnWeek').val(newWeek).attr('value', newWeek);

            //     loadWeekData();
            // }

            // $('#btnAttnPrev').on('click', () => changeWeek(-1));
            // $('#btnAttnNext').on('click', () => changeWeek(1));


            $('#attnWeek, #attnSiteFilter').on('change', loadWeekData);
            $('#attnSearch').on('input', loadWeekData);
            $(document).on('click', '#createLabourVoucher', function() {

                // Array to store all labour data
                let allLabours = [];
                let week = $('#pFrom').val();
                let search = $('#personQuery').val();

                // Loop through each table row
                $('.labour-row').each(function() {

                    let row = $(this);
                    let labourId = row.data('id');

                    let labourAmount = row.find('.labour_amount').val();
                    labourAmount = labourAmount ? parseFloat(labourAmount) : 0;

                    // Skip empty or zero amounts
                    if (labourAmount <= 0) return;

                    allLabours.push({
                        id: labourId,
                        name: row.data('name'),
                        mobile: row.data('mobile'),
                        role: row.data('role'),
                        rate: row.data('rate'),
                        advance: row.data('advance'),
                        attendanceDates: row.data('attendance-dates'),
                        amount: labourAmount
                    });
                });

                // =============================
                // VALIDATION BEFORE CONFIRMATION
                // =============================
                if (allLabours.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Amount Entered',
                        text: 'Please enter amount for at least one labour.',
                        timer: 2000
                    });
                    return;
                }

                // =============================
                // SWEETALERT CONFIRMATION
                // =============================
                Swal.fire({
                    title: "Are you sure?",
                    text: "Do you want to create voucher for selected labours?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, create voucher!",
                    cancelButtonText: "Cancel",
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('labours.payment') }}",
                            type: "POST",
                            data: {
                                labours: allLabours,
                                week: week,
                                search: search,
                                _token: '{{ csrf_token() }}'
                            },

                            success: function(res) {

                                // Backend explicitly returned failure
                                if (res.success === false) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: res.message && res.message
                                            .trim() ?
                                            res.message :
                                            'Something went wrong.',
                                    });
                                    return;
                                }

                                // Success case
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: res.message && res.message.trim() ?
                                        res.message :
                                        'Operation completed successfully.',
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                if (res.view) {
                                    $('#personAllBody').html(res.view);
                                }

                                $('.labour_amount').val('');
                            },

                            error: function(xhr) {
                                let message = 'Something went wrong.';
                                if (xhr.responseJSON?.message) {
                                    message = xhr.responseJSON.message;
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: message
                                });
                            }
                        });
                    }
                });
            });
        });

        function loadWeekData() {
            let attnWeek = $('#attnWeek').val() || $('#attnWeek').attr('value');
            console.log(attnWeek);

            $.ajax({
                url: "{{ route('attendance.week.load') }}",
                type: "GET",
                data: {
                    week: attnWeek,
                    site_id: $('#attnSiteFilter').val(),
                    search: $('#attnSearch').val(),
                },
                success: function(res) {
                    // $('#attnWeek').val(attnWeek).attr('value', attnWeek);
                    $('#attnBoard').html(res.view); // replace table with new week
                    console.log(res);
                }
            });
        }
    </script>
@endsection
