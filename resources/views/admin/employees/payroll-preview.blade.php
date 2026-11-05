@extends('admin.layouts.master')

@section('content')
<style>
body:has([data-ajax-panel="payroll-preview-panel"]) .content-wrapper .card,body:has([data-ajax-panel="payroll-preview-panel"]) .small-box{border:1px solid #e3eaf4;border-radius:15px;box-shadow:0 8px 24px rgba(26,48,83,.06);overflow:hidden}body:has([data-ajax-panel="payroll-preview-panel"]) .content-wrapper .card-header{background:linear-gradient(110deg,#f5f9ff,#fff)}body:has([data-ajax-panel="payroll-preview-panel"]) .form-control{border-radius:9px}body:has([data-ajax-panel="payroll-preview-panel"]) .table thead{background:#eff5fc;color:#304a6c}
@media print {
    .main-sidebar, .main-header, .main-footer, .payroll-actions, .payroll-filter, .control-sidebar { display: none !important; }
    .content-wrapper { margin-left: 0 !important; min-height: 0 !important; padding-top: 0 !important; }
    .content-wrapper, .card, .card-body { background: #fff !important; box-shadow: none !important; }
    .card { break-inside: avoid; border: 1px solid #bbb !important; }
    .table { font-size: 9pt; }
    .table td, .table th { padding: 4px !important; }
}
</style>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">
        <div><h1>Monthly Payroll Preview</h1><div class="text-muted">Project {{ $projectId }} · {{ $month }} · Preview only; no payroll voucher or payment is created.</div></div>
        <div class="payroll-actions btn-group">
            <a class="btn btn-outline-success" href="{{ route('employee-payroll.preview', array_merge(request()->query(), ['export' => 'csv'])) }}">Export CSV</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print / Save PDF</button>
            <a class="btn btn-outline-primary" href="{{ route('employee-attendance.index', ['month' => $month]) }}">Attendance review</a>
        </div>
    </div></section>
    <section class="content"><div class="container-fluid" data-ajax-panel="payroll-preview-panel">
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="alert alert-info"><strong>Calculation scope:</strong> salary proration follows each effective revision's selected calendar-day or scheduled-workday policy. Late-fine percentage uses the configured daily-basic-salary base; early departure supports fixed-per-day or per-minute deduction; overtime supports fixed-per-day, hourly, or basic-hourly-rate multiplier. Reviewed attendance is required; scheduled weekly offs are accepted from the effective schedule. Missing punch data or required multiplier inputs keeps the employee marked for review.</div>
        <div class="card"><div class="card-body">
            <form method="GET" action="{{ route('employee-payroll.preview') }}" class="form-row align-items-end payroll-filter">
                <div class="form-group col-md-3"><label for="month">Payroll month</label><input class="form-control" id="month" name="month" type="month" value="{{ $month }}" required></div>
                <div class="form-group col-md-5"><label for="employee_id">Employee</label><select class="form-control" id="employee_id" name="employee_id"><option value="">All employees</option>@foreach ($employees as $employee)<option value="{{ $employee['id'] }}" @selected((string) request('employee_id') === (string) $employee['id'])>{{ $employee['name'] }}</option>@endforeach</select></div>
                <div class="form-group col-md-2"><button class="btn btn-primary btn-block">Refresh preview</button></div>
            </form>
        </div></div>
        <div class="row mb-3">
            @foreach ([['Employees ready', $totals['ready']], ['Need review', $totals['review']], ['Gross preview', number_format($totals['gross'] / 100, 2)], ['Net preview', number_format($totals['net'] / 100, 2)]] as [$label, $value])
                <div class="col-6 col-xl-3"><div class="small-box bg-light"><div class="inner"><h4>{{ $value }}</h4><p>{{ $label }}</p></div></div></div>
            @endforeach
        </div>
        @if ($canCreateSnapshot && !request('employee_id') && $rows->isNotEmpty())
            <div class="card border-primary"><div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                <div><strong>Consolidated payroll snapshot</strong><div class="text-muted">Stores a fixed copy of this month’s ready preview for approval. It does not create accounting entries or payments.</div></div>
                <form method="POST" action="{{ route('employee-payroll.snapshot') }}">@csrf<input type="hidden" name="month" value="{{ $month }}"><button class="btn btn-primary" @disabled($totals['review'] > 0)>Create immutable snapshot</button></form>
            </div></div>
        @endif
        @if ($runs->isNotEmpty())
            <div class="card"><div class="card-header"><strong>Payroll run history · {{ $month }}</strong></div><div class="card-body table-responsive">
                <table class="table table-bordered table-sm mb-0"><thead><tr><th>Run</th><th>Status</th><th>Employees</th><th>Gross</th><th>Net</th><th>Created</th><th>Action</th></tr></thead><tbody>
                @foreach ($runs as $run)
                    <tr><td>#{{ $run->run_number }}</td><td><span class="badge badge-{{ $run->status === 'approved' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $run->status)) }}</span></td><td>{{ $run->employee_count }}</td><td>{{ number_format((float) $run->gross_total, 2) }}</td><td>{{ number_format((float) $run->net_total, 2) }}</td><td>{{ $run->created_at }}</td><td>
                        <a class="btn btn-sm btn-outline-primary mb-1" href="{{ route('employee-payroll.run', $run->id) }}">View slips</a>
                        @if ($run->status === 'pending_approval' && $canApprovePayroll)
                            <form data-ajax="off" method="POST" action="{{ route('employee-payroll.approve', $run->id) }}" onsubmit="return confirm('Approve this locked payroll snapshot? No payment will be posted.');">@csrf<button class="btn btn-sm btn-success">Approve and lock</button></form>
                        @elseif ($run->status === 'approved')<span class="text-muted">Locked</span>@else<span class="text-muted">Awaiting approver</span>@endif
                    </td></tr>
                @endforeach
                </tbody></table>
            </div></div>
        @endif
        <div class="card"><div class="card-header"><strong>Employee-wise preview</strong></div><div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-sm"><thead><tr><th>Employee</th><th>Department / Designation</th><th>Attendance</th><th>Basic</th><th>Allowances / additions</th><th>Attendance deductions</th><th>Late fine</th><th>Early departure</th><th>Overtime</th><th>Other deductions</th><th>Gross</th><th>Net preview</th><th>Readiness</th></tr></thead><tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['employee'] }}<br><small>{{ $row['code'] }}</small></td>
                    <td>{{ $row['department'] }} / {{ $row['designation'] }}</td>
                    <td>@foreach ($row['counts'] as $status => $count){{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}<br>@endforeach</td>
                    <td class="text-right">{{ number_format($row['basic'] / 100, 2) }}</td>
                    <td class="text-right">{{ number_format(($row['allowances'] + $row['other_additions']) / 100, 2) }}</td>
                    <td class="text-right">{{ number_format($row['attendance_deductions'] / 100, 2) }}</td>
                    <td class="text-right">{{ number_format($row['late_fine'] / 100, 2) }}</td>
                    <td class="text-right">{{ number_format($row['early_departure_deduction'] / 100, 2) }}</td>
                    <td class="text-right">{{ number_format($row['overtime_addition'] / 100, 2) }}</td>
                    <td class="text-right">{{ number_format($row['other_deductions'] / 100, 2) }}</td>
                    <td class="text-right">{{ number_format($row['gross'] / 100, 2) }}</td>
                    <td class="text-right font-weight-bold">{{ number_format($row['net'] / 100, 2) }}</td>
                    <td>@if ($row['ready'])<span class="badge badge-success">Ready for review</span>@else<span class="badge badge-warning">Review required</span>@endif
                        @foreach ($row['flags'] as $flag)<div><small>{{ $flag }}</small></div>@endforeach
                    </td>
                </tr>
            @empty<tr><td colspan="13" class="text-center text-muted">No employee profiles found in this project.</td></tr>@endforelse
            </tbody></table>
        </div></div>
    </div></section>
</div>
@endsection
