<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Ledger Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Arial', sans-serif;
        }

        .container {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .header {
            /* text-align: center; */
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .header p {
            font-size: 14px;
            color: #555;
            margin-bottom: 0rem;
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            border-left: 5px solid #3498db;
            padding-left: 10px;
            margin-bottom: 15px;
        }

        .table {
            background: #ffffff;
            border-radius: 5px;
            overflow: hidden;
        }

        .table thead th {
            background-color: #3498db;
            color: #ffffff;
            border: none;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table tbody td {
            color: #333;
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
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
        .table tbody td {
            color: #333;
            padding: 1px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>{{ $reportTitle }}</h1>
            @foreach($party_details as $index => $party)
              <b>{{ $party['subhead_accounting'] }}, </b> 
            @endforeach
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
        <div class="mt-4">
            <div class="section-title">Ledger Details</div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Voucher</th>
                            <th>Details</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Balance</th>
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
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($ledger->date)->format('d-m-y') }}</td>
                                <td>{{ $ledger->type }}-{{ str_pad($ledger->type_id, 7, '0', STR_PAD_LEFT);  }}</td>
                                <td>{{ $ledger->detail }}</td>
                                <td>{{ number_format($ledger->amount_in, 0) }}</td>
                                <td>{{ number_format($ledger->amount_out, 0) }}</td>
                                <td class="{{ $balanceClass }}">{{ number_format($balance, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Print Button -->
        <div class="text-end mt-4 no-print">
            <button class="btn btn-primary" onclick="window.print()">Print</button>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; {{ date('Y') }} Party Ledger Report. All rights reserved.
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
