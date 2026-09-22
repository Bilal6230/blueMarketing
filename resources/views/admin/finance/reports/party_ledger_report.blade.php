<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Ledger Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
/* =========================================================
   PARTY LEDGER REPORT
   COMPLETE SCREEN + PRINT CSS
   ========================================================= */


/* =========================================================
   PRINT PAGE SETTINGS
   ========================================================= */

@page {
    size: A4 portrait;
    margin: 8mm 7mm;
}


/* =========================================================
   GENERAL PAGE STYLES
   ========================================================= */

body {
    background-color: #f5f7fa;
    font-family: Arial, sans-serif;
    color: #222;
    font-size: 13px;
    line-height: 1.25;
    margin: 0;
    padding: 0;
}


/* =========================================================
   MAIN CONTAINER
   ========================================================= */

.container {
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);

    padding: 14px 16px;

    margin-top: 12px;
    margin-bottom: 10px;

    max-width: 1100px;
}


/* =========================================================
   REPORT HEADER
   ========================================================= */

.header {
    margin-bottom: 12px;
}

.header h1 {
    font-size: 24px;
    font-weight: bold;
    color: #333;

    margin-top: 0;
    margin-bottom: 4px;
}

.header p {
    font-size: 13px;
    color: #555;

    margin-top: 0;
    margin-bottom: 2px;

    line-height: 1.2;
}


/* =========================================================
   PARTY LIST
   ========================================================= */

.party-list {
    margin-bottom: 4px;

    font-size: 13px;
    line-height: 1.2;

    color: #333;
}


/* =========================================================
   SECTION TITLE
   ========================================================= */

.section-title {
    font-size: 16px;
    font-weight: bold;

    color: #2c3e50;

    border-left: 4px solid #3498db;

    padding-left: 8px;

    margin-top: 0;
    margin-bottom: 8px;

    line-height: 1.1;
}


/* =========================================================
   REPORT BLOCK
   ========================================================= */

.report-block {
    margin-top: 8px;
}


/* =========================================================
   TABLE RESPONSIVE WRAPPER
   ========================================================= */

.table-responsive {
    width: 100%;
}


/* =========================================================
   TABLE
   ========================================================= */

.table {
    width: 100%;

    background: #ffffff;

    border-radius: 5px;

    overflow: hidden;

    margin-bottom: 0;

    table-layout: auto;
}


/* =========================================================
   TABLE HEADER
   ========================================================= */

.table thead th {
    background-color: #3498db;

    color: #ffffff;

    border: none;

    padding: 6px 8px;

    font-size: 12px;

    line-height: 1.1;

    white-space: nowrap;

    vertical-align: middle;
}


/* =========================================================
   TABLE BODY
   ========================================================= */

.table tbody td {
    color: #333;

    padding: 4px 6px;

    font-size: 12px;

    line-height: 1.15;

    vertical-align: top;
}


/* =========================================================
   ALTERNATE ROW BACKGROUND
   ========================================================= */

.table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}


/* =========================================================
   OPENING BALANCE
   ========================================================= */

.opening-balance-row {
    font-weight: bold;

    color: #6c757d;
}


/* =========================================================
   BALANCE COLORS
   ========================================================= */

.balance-negative {
    color: red !important;
}

.balance-positive {
    color: green !important;
}


/* =========================================================
   TABLE COLUMN WIDTHS
   ========================================================= */

.col-index {
    width: 42px;
    white-space: nowrap;
}

.col-date {
    width: 88px;
    white-space: nowrap;
}

.col-voucher {
    width: 112px;
    white-space: nowrap;
}

.col-amount {
    width: 86px;

    white-space: nowrap;

    text-align: right;
}


/* Prevent date / voucher wrapping */

.table tbody td.col-date,
.table tbody td.col-voucher {
    white-space: nowrap;
}


/* =========================================================
   BUTTON
   ========================================================= */

.btn-primary {
    background-color: #3498db;

    border: none;
}

.btn-primary:hover {
    background-color: #2c81ba;
}


/* =========================================================
   FOOTER
   ========================================================= */

.footer {
    text-align: center;

    margin-top: 10px;

    margin-bottom: 10px;

    font-size: 12px;

    color: #888;
}


/* =========================================================
   PRINT STYLES
   ========================================================= */

@media print {

    /*
     * Basic document
     */

    html,
    body {
        width: 100% !important;

        margin: 0 !important;

        padding: 0 !important;

        background: #ffffff !important;

        font-size: 10px !important;

        overflow: visible !important;
    }


    /*
     * Hide print button and other non-print content
     */

    .no-print {
        display: none !important;
    }


    /*
     * Main report container
     */

    .container {
        width: 100% !important;

        max-width: 100% !important;

        margin: 0 !important;

        padding: 0 !important;

        background: #ffffff !important;

        box-shadow: none !important;

        border-radius: 0 !important;

        overflow: visible !important;
    }


    /*
     * Header
     *
     * Keep only the report header together.
     * DO NOT keep the entire report together.
     */

    .header {
        margin-top: 0 !important;

        margin-bottom: 6px !important;

        padding: 0 !important;

        break-inside: avoid !important;

        page-break-inside: avoid !important;
    }


    .header h1 {
        font-size: 18px !important;

        line-height: 1.1 !important;

        margin-top: 0 !important;

        margin-bottom: 2px !important;
    }


    .header p {
        font-size: 9px !important;

        line-height: 1.15 !important;

        margin-top: 0 !important;

        margin-bottom: 1px !important;
    }


    /*
     * Party names
     */

    .party-list {
        font-size: 9px !important;

        line-height: 1.15 !important;

        margin-top: 0 !important;

        margin-bottom: 2px !important;
    }


    /*
     * Ledger section title
     */

    .section-title {
        font-size: 11px !important;

        line-height: 1.1 !important;

        margin-top: 4px !important;

        margin-bottom: 4px !important;

        padding-left: 5px !important;

        border-left-width: 3px !important;

        /*
         * Keep Ledger Details title
         * with the following table.
         */

        break-after: avoid !important;

        page-break-after: avoid !important;
    }


    /*
     * =====================================================
     * IMPORTANT FIX
     * =====================================================
     *
     * Previously:
     *
     * .report-block {
     *     page-break-inside: avoid;
     * }
     *
     * That forced Chrome to move the COMPLETE ledger
     * table onto the following page.
     *
     * We now explicitly allow the report block to split.
     */

    .report-block {
        width: 100% !important;

        margin-top: 4px !important;

        padding: 0 !important;

        break-inside: auto !important;

        page-break-inside: auto !important;

        page-break-before: auto !important;

        break-before: auto !important;
    }


    /*
     * Responsive Bootstrap wrapper must not interfere
     * with printing.
     */

    .table-responsive {
        width: 100% !important;

        overflow: visible !important;

        margin: 0 !important;

        padding: 0 !important;

        break-inside: auto !important;

        page-break-inside: auto !important;
    }


    /*
     * Main table
     */

    .table {
        width: 100% !important;

        max-width: 100% !important;

        margin: 0 !important;

        padding: 0 !important;

        border-collapse: collapse !important;

        border-spacing: 0 !important;

        table-layout: fixed !important;

        overflow: visible !important;

        break-inside: auto !important;

        page-break-inside: auto !important;
    }


    /*
     * Repeat column headings on every printed page.
     */

    .table thead {
        display: table-header-group !important;
    }


    /*
     * Allow tbody to continue naturally
     * on following pages.
     */

    .table tbody {
        display: table-row-group !important;

        break-inside: auto !important;

        page-break-inside: auto !important;
    }


    /*
     * Do not split ONE transaction row
     * between two pages.
     */

    .table tr {
        break-inside: avoid !important;

        page-break-inside: avoid !important;

        page-break-after: auto !important;
    }


    /*
     * Table header cells
     */

    .table thead th {
        background-color: #3498db !important;

        color: #ffffff !important;

        -webkit-print-color-adjust: exact !important;

        print-color-adjust: exact !important;

        padding: 3px 4px !important;

        font-size: 8px !important;

        line-height: 1.05 !important;

        white-space: nowrap !important;

        vertical-align: middle !important;
    }


    /*
     * Table body cells
     */

    .table tbody td {
        padding: 2px 4px !important;

        font-size: 8px !important;

        line-height: 1.1 !important;

        vertical-align: top !important;
    }


    /*
     * Preserve alternating rows in PDF/printing
     */

    .table tbody tr:nth-child(even) {
        background-color: #f9f9f9 !important;

        -webkit-print-color-adjust: exact !important;

        print-color-adjust: exact !important;
    }


    /*
     * Opening balance
     */

    .opening-balance-row {
        font-weight: bold !important;

        break-inside: avoid !important;

        page-break-inside: avoid !important;
    }


    /*
     * Balance colors
     */

    .balance-negative {
        color: red !important;

        -webkit-print-color-adjust: exact !important;

        print-color-adjust: exact !important;
    }


    .balance-positive {
        color: green !important;

        -webkit-print-color-adjust: exact !important;

        print-color-adjust: exact !important;
    }


    /*
     * Column widths optimized for A4 portrait
     */

    .col-index {
        width: 5% !important;

        white-space: nowrap !important;
    }


    .col-date {
        width: 11% !important;

        white-space: nowrap !important;
    }


    .col-voucher {
        width: 14% !important;

        white-space: nowrap !important;
    }


    .col-amount {
        width: 11% !important;

        white-space: nowrap !important;

        text-align: right !important;
    }


    /*
     * Details column can wrap naturally
     */

    .table th:nth-child(4),
    .table td:nth-child(4) {
        width: auto !important;

        white-space: normal !important;

        word-break: normal !important;

        overflow-wrap: break-word !important;
    }


    /*
     * Make amount columns right aligned
     */

    .table th:nth-child(5),
    .table th:nth-child(6),
    .table th:nth-child(7),
    .table td:nth-child(5),
    .table td:nth-child(6),
    .table td:nth-child(7) {
        text-align: right !important;
    }


    /*
     * Footer
     */

    .footer {
        margin-top: 5px !important;

        margin-bottom: 0 !important;

        padding: 0 !important;

        font-size: 8px !important;

        line-height: 1.1 !important;

        break-inside: avoid !important;

        page-break-inside: avoid !important;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>{{ $reportTitle }}</h1>
            <div class="party-list">
                @foreach($party_details as $index => $party)
                    <b>{{ $party['subhead_accounting'] }}</b>@if(!$loop->last), @endif
                @endforeach
            </div>
            <p>Date Range: <strong>{{ \Carbon\Carbon::parse($fdate)->format('d-m-y') }} </strong> to <strong>{{ \Carbon\Carbon::parse($tdate)->format('d-m-y') }} </strong></p>
            <p>Project: <strong>{{ $projectName }}</strong></p>
        </div>


        <!-- Party Details Table -->
        {{-- <div class="mb-4 col-sm-4">
            <div class="section-title">Party Details</div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Party Account</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($party_details as $index => $party)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td> {{ $party['subhead_accounting'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div> --}}

        <!-- Ledger Details Table -->
        <div class="report-block">
            <div class="section-title">Ledger Details</div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th class="col-index">#</th>
                            <th class="col-date">Date</th>
                            <th class="col-voucher">Voucher</th>
                            <th>Details</th>
                            <th class="col-amount">In</th>
                            <th class="col-amount">Out</th>
                            <th class="col-amount">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="opening-balance-row">
                            <td colspan="5" class="text-center">Opening Balance</td>
                            <td>{{ number_format($opening_balance, 0) }}</td>
                        </tr>

                        <!-- Ledger Details Rows -->
                        @php $balance = $opening_balance; @endphp
                        @foreach($ledger_details as $index => $ledger)
                            @php
                                $balance += $ledger->amount_in - $ledger->amount_out;
                                $balanceClass = $balance < 0 ? 'balance-negative' : 'balance-positive';
                                  $voucherNumber = $ledger->voucher_number;

                if ($ledger->type === 'JV' && !empty($ledger->type_id)) {
                    $journalVoucher = App\Models\JournalVoucher::where('id', $ledger->type_id)
                        ->select('id', 'voucher_number')
                        ->first();

                    if ($journalVoucher && !empty($journalVoucher->voucher_number)) {
                        $voucherNumber = $journalVoucher->voucher_number;
                    }
                }

                $number =  ($ledger->type ?? '') . '-' . $voucherNumber;
                            @endphp
                            <tr>
                                <td class="col-index">{{ $index + 1 }}</td>
                                <td class="col-date">{{ \Carbon\Carbon::parse($ledger->date)->format('d-m-y') }}</td>
                                <td class="col-voucher">{{ $number ?? 'N/A' }}</td>
                                <td>{{ $ledger->detail }}</td>
                                <td class="col-amount">{{ number_format($ledger->amount_in, 0) }}</td>
                                <td class="col-amount">{{ number_format($ledger->amount_out, 0) }}</td>
                                <td class="col-amount {{ $balanceClass }}">{{ number_format($balance, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Print Button -->
        <div class="text-end mt-3 no-print">
            <button class="btn btn-primary btn-sm" onclick="window.print()">Print</button>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; {{ date('Y') }} Party Ledger Report. All rights reserved.
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
