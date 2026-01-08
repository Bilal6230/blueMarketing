<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Labour Attendance Report</title>

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
            border-bottom: 2px solid #000;
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
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 11px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            font-weight: 700;
            background: #f0f0f0;
        }

        .labour-col {
            text-align: left;
            width: 240px;
        }

        .lab-name {
            font-weight: 600;
        }

        .lab-meta {
            font-size: 10px;
            color: #333;
        }

        .today {
            background: #d1fae5;
        }

        /* ================= ICONS ================= */
        .ico {
            display: inline-block;
            width: 22px;
            height: 22px;
            line-height: 22px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 700;
        }

        .tick {
            background: #bbf7d0;
        }

        .cross {
            background: #fecaca;
        }

        .leave {
            background: #fed7aa;
        }

        .none {
            background: #e5e7eb;
        }

        .ot {
            margin-top: 2px;
            font-size: 10px;
            display: block;
        }

        /* ================= PRINT SAFETY ================= */
        tr {
            page-break-inside: avoid;
        }

        * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    </style>
</head>

<body>

    @php
        use Carbon\Carbon;
        [$startDate, $endDate, $weekDays] = loadAttendanceWeek($week ?? null);
        $today = Carbon::today()->format('Y-m-d');
    @endphp

    <!-- ================= HEADER ================= -->
    <div class="print-header">
        <div>
            <h2>Labour Attendance Report</h2>
            <div style="font-size:12px;">
                Site: {{ $site_name ?? 'All Sites' }}
            </div>
        </div>

        <div class="print-meta">
            Week: {{  Carbon::parse($start)->format('d-m-Y') }} to {{ Carbon::parse($end)->format('d-m-Y') }}<br>
            Printed: {{ now()->format('d-m-Y h:i A') }}
        </div>
    </div>

    <!-- ================= ATTENDANCE TABLE ================= -->
    <table>
        <thead>
            <tr>
                <th class="labour-col">Labour</th>
                @foreach ($weekDays as $day)
                    @php $carbon = Carbon::parse($day); @endphp
                    <th class="{{ $day === $today ? 'today' : '' }}">
                        {{ strtoupper($carbon->format('D')) }}<br>
                        {{ $carbon->format('d') }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($attendance_labours as $labour)
                <tr>
                    <td class="labour-col">
                        <div class="lab-name">
                            {{ $labour->name }} ({{ substr($labour->cnic, -4) }})
                        </div>
                        <div class="lab-meta">
                            {{ $labour->phone }} · {{ $labour->role }} · Rs {{ $labour->daily_wage }}
                        </div>
                    </td>

                    @foreach ($weekDays as $day)
                        @php
                            $attendance = $labour->attendances->where('date', $day)->first();

                            $status = 'present';
                            $icon = '-';
                            $iconClass = 'none';

                            if ($attendance) {
                                if ($attendance->hours == 4) {
                                    $status = 'leave';
                                    $icon = 'H';
                                    $iconClass = 'leave';
                                } elseif ($attendance->status === 'present') {
                                    $status = 'present';
                                    $icon = '✓';
                                    $iconClass = 'tick';
                                } else {
                                    $status = 'absent';
                                    $icon = '✗';
                                    $iconClass = 'cross';
                                }
                            }

                            $isSiteDisabled =
                                $attendance?->site_id && $selectedSiteId && $attendance->site_id != $selectedSiteId;
                        @endphp

                        <td>
                            <span class="ico {{ $isSiteDisabled ? 'none' : $iconClass }}"
                                style="{{ $isSiteDisabled ? 'background:#9db4dd;' : '' }}"
                                data-tooltips="{{ $attendance?->site->site_name ?? '' }}">
                                {{ $isSiteDisabled ? '-' : $icon }}
                            </span>

                            @if ($attendance && $attendance->ot_hours > 0)
                                <span class="ico tick ot-badge">
                                    {{ number_format($attendance->ot_hours) }}
                                </span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 500);
        });
    </script>

</body>

</html>
