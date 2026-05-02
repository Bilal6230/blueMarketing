@extends('admin.layouts.master')

@section('content')
    {{-- PAGE STYLES --}}
    <style>
        .wrap {
            max-width: 1400px;
            margin: auto;
            padding: 20px;
        }

        .page-head h2 {
            margin: 0;
        }

        .muted {
            color: #777;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .06);
            margin-bottom: 20px;
        }

        .stat span {
            color: #777;
            font-size: 14px;
        }

        .stat b {
            font-size: 24px;
        }

        .stat.danger b {
            color: #e63946;
        }

        .filter .row {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .filter label {
            font-size: 13px;
            color: #555;
        }

        .filter-btn {
            padding-bottom: 2px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .table th {
            background: #fafafa;
            text-align: left;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            color: #fff;
        }

        .badge.present {
            background: #2a9d8f
        }

        .badge.absent {
            background: #e63946
        }

        .badge.leave {
            background: #f4a261
        }

        .badge.holiday {
            background: #457b9d
        }

        .badge.not-marked {
            background: #999
        }

        .badge.paid {
            background: #2a9d8f
        }

        .badge.unpaid {
            background: #e63946
        }

        .empty {
            text-align: center;
            color: #999;
        }

        pre {
            margin: 0;
            font-size: 12px;
            background: #f6f6f6;
            padding: 6px;
            border-radius: 6px;
        }
    </style>
    <div class="wrap">

        {{-- PAGE HEADER --}}
        <div class="page-head">
            <h2>{{ $labour->name }} Attendance History</h2>
            <p class="muted">Attendance & Payment Records</p>
        </div>

        {{-- SUMMARY CARDS --}}
        <div class="grid-4">
            <div class="card stat">
                <span>Total Days</span>
                <b>{{ $summary['total_days'] ?? 0 }}</b>
            </div>

            <div class="card stat">
                <span>Total Hours</span>
                <b>{{ $summary['total_hours'] ?? 0 }}</b>
            </div>

            <div class="card stat">
                <span>Total Earned</span>
                <b>Rs {{ number_format($summary['total_amount'] ?? 0, 2) }}</b>
            </div>

            <div class="card stat danger">
                <span>Unpaid Balance</span>
                <b>Rs {{ number_format($summary['unpaid'] ?? 0, 2) }}</b>
            </div>
        </div>

        {{-- ATTENDANCE HISTORY --}}
        <div class="card">
            <h3>Attendance History</h3>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Site</th>
                            <th>Status</th>
                            <th>Hours</th>
                            <th>OT</th>
                            <th>Rate</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $runningBalance = 0; @endphp

                    @forelse($labour->attendances as $att)
                        @php
                            $payment = $paymentsByDate[$att->date] ?? 0;
                            $runningBalance += $att->amount - $payment;
                        @endphp

                        <tr>
                            <td>{{ $att->date }}</td>
                            <td>{{ $att->site->site_name ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $att->status }}">
                                    {{ ucfirst($att->status) }}
                                </span>
                            </td>
                            <td>{{ $att->hours }}</td>
                            <td>{{ $att->ot_hours }}</td>
                            <td>{{ number_format($att->rate ?? 0, 2) }}</td>
                            <td>Rs {{ number_format($att->amount, 2) }}</td>
                            <td>Rs {{ number_format($payment, 2) }}</td>
                            <td>Rs {{ number_format($runningBalance, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty">No attendance records found</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection

@section('js')
    {{-- no JS required for now --}}
@endsection
