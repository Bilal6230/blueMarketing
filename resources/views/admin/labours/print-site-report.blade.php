<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Labour Site Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            color: #000;
            background: #fff;
        }

        /* ================= PAGE ================= */
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        /* ================= HEADER ================= */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }

        .print-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        .print-meta {
            font-size: 12px;
            text-align: right;
        }

        /* ================= TABLE ================= */
        .table-scroll {
            width: 100%;
            overflow-x: auto;
            margin-top: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 11px;
            text-align: left;
            vertical-align: middle;
        }

        th {
            font-weight: 700;
            background: #f0f0f0;
            text-align: center;
        }

        td.right {
            text-align: right;
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
        <h2>Labour Site Report</h2>
        <div style="font-size:12px;">
            Site: {{ $site_name ?? 'All Sites' }}
        </div>
    </div>

    <div class="print-meta">
        Week: {{  Carbon::parse($start)->format('d-m-Y') }} to {{ Carbon::parse($end)->format('d-m-Y') }}<br>
        Printed: {{ $today }}
    </div>
</div>

<!-- ================= TABLE ================= -->
<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th style="width:25%;">Labour</th>
                <th>Mobile</th>
                <th style="width:10%;">Designation</th>
                <th class="right" style="width:10%;">Rate</th>
                <th class="right" style="width:7%;">Days</th>
                <th class="right" style="width:10%;">Overtime</th>
                <th class="right" style="width:15%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reports as $report)
                <tr>
                    <td class="labour-name">{{ $report['name'] ?? '' }} ({{ substr($report['cnic'], -4) }})</td>
                    <td>{{ $report['mobile'] ?? '' }}</td>
                    <td>{{ $report['designation'] ?? '' }}</td>
                    <td class="right">PKR {{ $report['rate'] ?? 0 }}</td>
                    <td class="right">{{ $report['days'] ?? 0 }}</td>
                    <td class="right">{{ $report['overtime'] ?? 0 }}</td>
                    <td class="right">PKR {{ $report['amount'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="right">Total</td>
                <td class="right">PKR {{ number_format($total_amount ?? 0) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
    window.addEventListener('load', () => {
        setTimeout(() => window.print(), 500);
    });
</script>

</body>
</html>
