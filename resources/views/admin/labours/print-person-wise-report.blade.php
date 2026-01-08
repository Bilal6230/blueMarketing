<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Person Wise Labour Report</title>

    {{-- PRINT SAFE FONT --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }

        /* ================= PAGE ================= */
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        /* ================= HEADER ================= */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .print-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        .print-meta {
            font-size: 11px;
            text-align: right;
            line-height: 1.4;
        }

        /* ================= TABLE ================= */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 12px;
            vertical-align: middle;
        }

        th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: center;
        }

        td.right {
            text-align: right;
        }

        td.center {
            text-align: center;
        }

        td.labour-name {
            font-weight: 600;
        }

        tfoot td {
            font-weight: 700;
            background: #f0f0f0;
        }

        tr {
            page-break-inside: avoid;
        }

        /* ================= PRINT SAFETY ================= */
        * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ================= SIGNATURE ================= */
        .signature-section {
            margin-top: 35px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 30%;
            text-align: center;
            font-size: 12px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }
    </style>
</head>

<body>

    @php
        use Carbon\Carbon;
        $today = Carbon::now()->format('d-m-Y h:i A');
    @endphp

    <!-- ================= HEADER ================= -->
    <div class="print-header">
        <div>
            <h2>Person Wise Labour Report</h2>
            <div style="font-size:11px;">
                Site: {{ $site_name ?? 'All Sites' }}
            </div>
        </div>

        <div class="print-meta">
            Period: {{ Carbon::parse($start)->format('d-m-Y') }}
            to {{ Carbon::parse($end)->format('d-m-Y') }} <br>
            Printed: {{ $today }}
        </div>
    </div>

    <!-- ================= TABLE ================= -->
    <table>
        <thead>
            <tr>
                <th style="width:18%;">Labour</th>
                <th style="width:10%;">Mobile</th>
                <th style="width:8%;">Designation</th>
                <th style="width:7%;">Rate</th>
                <th style="width:6%;">Days</th>
                <th style="width:6%;">OT</th>
                <th style="width:7%;">Rating</th>
                <th style="width:7%;">Amount</th>
                <th style="width:7%;">Over All Blance</th>
                <th style="width:7%;">Payments</th>
                <th style="width:10%;">Signature</th>
            </tr>
        </thead>

        <tbody>
            @php
                $totalpayable = 0
            @endphp
            @foreach ($personWiseReports as $report)
                @php
                    $payable = $request['labour_' . $report['id']] ?? 0;
                    $totalpayable += $payable;
                @endphp
                <tr>
                    <td class="labour-name">
                        {{ $report['name'] ?? '' }}
                        ({{ substr($report['cnic'], -4) }})
                    </td>
                    <td>{{ $report['mobile'] ?? '' }}</td>
                    <td>{{ $report['designation'] ?? '' }}</td>
                    <td class="right">RS {{ $report['rate'] ?? 0 }}</td>
                    <td class="center">{{ $report['days'] ?? 0 }}</td>
                    <td class="center">{{ $report['overtime'] ?? 0 }}</td>
                    <td class="center">{{ $report['ratings'] ?? '-' }}/5</td>
                    <td class="left">RS {{ $report['amount'] ?? 0 }}</td>
                    <td class="left">RS {{ $report['remaningAmount'] ?? 0 }}</td>
                    <td class="left">RS {{ $request['labour_' . $report['id']] ?? 0 }}</td>
                    <td class="left"></td>
                </tr>
            @endforeach
        </tbody>

        <tfoot>
            <tr>
                <td colspan="8" class="right">Total</td>
                <td class="right">PKR {{ $total_amount ?? 0 }}</td>
                <td>{{ $totalpayable }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <!-- ================= SIGNATURE ================= -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Prepared By</div>
        </div>

        <div class="signature-box">
            <div class="signature-line">Checked By</div>
        </div>

        <div class="signature-box">
            <div class="signature-line">Authorized Signature</div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 500);
        });
    </script>

</body>

</html>
