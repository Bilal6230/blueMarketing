@extends('admin.layouts.master')

@section('content')
<style>
.payroll-slip{border:1px solid #cbd5e1;border-radius:8px;margin-bottom:18px;background:#fff;overflow:hidden}.payroll-slip__head{background:#13243a;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.payroll-slip__body{padding:16px}.payroll-slip__totals{font-size:1.1rem;font-weight:700;background:#eef4fa}.payroll-slip table{margin-bottom:0}@media print{.main-sidebar,.main-header,.main-footer,.payroll-actions,.payroll-filter,.content-header{display:none!important}.content-wrapper{margin:0!important;padding:0!important;min-height:0!important}.payroll-slip{break-inside:avoid;page-break-inside:avoid;box-shadow:none!important}.payroll-slip:not(:last-child){page-break-after:always}}
</style>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center flex-wrap"><div><h1>Payroll Run #{{ $run->run_number }}</h1><div class="text-muted">Project {{ $projectId }} · {{ $run->payroll_month }} · {{ ucfirst(str_replace('_', ' ', $run->status)) }}</div></div><div class="payroll-actions"><a class="btn btn-outline-primary" href="{{ route('employee-payroll.preview', ['month' => $run->payroll_month]) }}">Back to payroll</a> <button class="btn btn-secondary" onclick="window.print()">Print slips</button></div></div></section>
<section class="content"><div class="container-fluid">
    <div class="card payroll-filter"><div class="card-body"><form method="GET" class="form-row align-items-end">
        <div class="form-group col-md-4"><label>Employee</label><select class="form-control" name="employee"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee['id'] }}" @selected((string)($filters['employee'] ?? '') === (string)$employee['id'])>{{ $employee['name'] }}</option>@endforeach</select></div>
        <div class="form-group col-md-3"><label>Department</label><select class="form-control" name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(($filters['department'] ?? '') === $department)>{{ $department }}</option>@endforeach</select></div>
        <div class="form-group col-md-3"><label>Designation</label><select class="form-control" name="designation"><option value="">All designations</option>@foreach($designations as $designation)<option value="{{ $designation }}" @selected(($filters['designation'] ?? '') === $designation)>{{ $designation }}</option>@endforeach</select></div>
        <div class="form-group col-md-2"><button class="btn btn-primary btn-block">Filter slips</button></div>
    </form></div></div>
    @forelse($items as $item) @php($s = $item->data)
        <article class="payroll-slip"><header class="payroll-slip__head"><div><strong>{{ $s['employee'] }}</strong> · {{ $s['code'] }}<br><small>{{ $s['department'] }} / {{ $s['designation'] }}</small></div><div>{{ $run->payroll_month }} · Run #{{ $run->run_number }}</div></header><div class="payroll-slip__body">
            <div class="table-responsive"><table class="table table-bordered table-sm"><tbody>
                <tr><th>Basic salary</th><td class="text-right">{{ number_format($s['basic'] / 100, 2) }}</td><th>Allowances</th><td class="text-right">{{ number_format($s['allowances'] / 100, 2) }}</td></tr>
                <tr><th>Other additions</th><td class="text-right">{{ number_format($s['other_additions'] / 100, 2) }}</td><th>Overtime</th><td class="text-right">{{ number_format($s['overtime_addition'] / 100, 2) }}</td></tr>
                <tr><th>Attendance deductions</th><td class="text-right">{{ number_format($s['attendance_deductions'] / 100, 2) }}</td><th>Late fines</th><td class="text-right">{{ number_format($s['late_fine'] / 100, 2) }}</td></tr>
                <tr><th>Early departure</th><td class="text-right">{{ number_format($s['early_departure_deduction'] / 100, 2) }}</td><th>Other deductions</th><td class="text-right">{{ number_format($s['other_deductions'] / 100, 2) }}</td></tr>
                <tr><th>Gross</th><td class="text-right">{{ number_format($s['gross'] / 100, 2) }}</td><th>Net salary</th><td class="text-right payroll-slip__totals">{{ number_format($s['net'] / 100, 2) }}</td></tr>
            </tbody></table></div><small class="text-muted">Attendance: @foreach($s['counts'] as $status => $count){{ ucfirst(str_replace('_',' ',$status)) }} {{ $count }}@if(!$loop->last) · @endif @endforeach</small>
        </div></article>
    @empty<div class="alert alert-info">No payroll slips match these filters.</div>@endforelse
</div></section></div>
@endsection
