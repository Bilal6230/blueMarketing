<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Ledger Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            size: auto;
            margin: 8mm 7mm;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: #fff !important;
                font-size: 10px;
            }

            .no-print {
                display: none !important;
            }

            .container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 6px 8px !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .header {
                margin-bottom: 8px !important;
            }

            .header h1 {
                font-size: 18px !important;
                margin-bottom: 2px !important;
            }

            .header p,
            .party-list,
            .footer {
                font-size: 10px !important;
                line-height: 1.2 !important;
            }

            .section-title {
                font-size: 13px !important;
                margin-bottom: 6px !important;
                padding-left: 6px !important;
            }

            .table {
                margin-bottom: 0 !important;
                table-layout: fixed;
            }

            .table th,
            .table td {
                padding: 3px 5px !important;
                font-size: 9.5px !important;
                line-height: 1.15 !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            .report-block {
                page-break-inside: avoid;
            }

            .footer {
                margin-top: 6px !important;
            }
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Arial', sans-serif;
            color: #222;
            font-size: 13px;
            line-height: 1.25;
        }

        .container {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 14px 16px;
            margin-top: 12px;
            margin-bottom: 10px;
            max-width: 1100px;
        }

        .header {
            margin-bottom: 12px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 13px;
            color: #555;
            margin-bottom: 2px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            border-left: 4px solid #3498db;
            padding-left: 8px;
            margin-bottom: 8px;
            line-height: 1.1;
        }

        .table {
            background: #ffffff;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 0;
            table-layout: auto;
        }

        .table thead th {
            background-color: #3498db;
            color: #ffffff;
            border: none;
            padding: 6px 8px;
            font-size: 12px;
            line-height: 1.1;
            white-space: nowrap;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .opening-balance-row {
            font-weight: bold;
            color: #6c757d; /* Light black color */
        }

        /* Balance Color Classes */
        .balance-negative {
            color: red !important; /* Using !important to override any other styling */
        }

        .balance-positive {
            color: green !important; /* Using !important to override any other styling */
        }

        .btn-primary {
            background-color: #3498db;
            border: none;
        }

        .btn-primary:hover {
            background-color: #2c81ba;
        }

        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 12px;
            color: #888;
        }

        .table tbody td {
            color: #333;
            padding: 4px 6px;
            font-size: 12px;
            line-height: 1.15;
            vertical-align: top;
        }

        .party-list {
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .report-block {
            margin-top: 8px;
        }

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

        .table tbody td.col-date,
        .table tbody td.col-voucher {
            white-space: nowrap;
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
