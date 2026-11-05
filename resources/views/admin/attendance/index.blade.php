@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper attendance-page">
    <section class="content pt-3">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div><h3 class="mb-1">Employee Attendance</h3><div class="text-muted">Project #{{ $projectId }} · Attendance records</div></div>
                <a href="{{ route('setting.index', ['tab'=>'attendance-machine']) }}" class="btn btn-outline-primary"><i class="fas fa-cog mr-1"></i> Attendance Machine Settings</a>
            </div>
            @php($canManage = auth()->user()->hasAnyRole(['admin', 'superadmin']))
            <div class="card attendance-records" data-ajax-panel="attendance-records-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-check mr-2"></i>Attendance Records</h3><span class="badge badge-primary float-right">{{ number_format($punches->total()) }} records</span></div>
                <div class="card-body">
                    <form data-ajax-form method="GET" class="form-row align-items-end mb-3">
                        <div class="form-group col-sm-4 col-lg-2"><label>From</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div>
                        <div class="form-group col-sm-4 col-lg-2"><label>To</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div>
                        @if($canManage)<div class="form-group col-sm-8 col-lg-4"><label>Employee</label><select name="user_id" class="form-control"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)request('user_id') === (string)$employee->id)>{{ $employee->name }}</option>@endforeach</select></div>@endif
                        <div class="form-group col-sm-4 col-lg-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i> Apply filter</button></div>
                    </form>
                    <div class="table-responsive"><table class="table table-hover table-bordered mb-0"><thead class="thead-light"><tr><th>Employee</th><th>Date</th><th>Punch in</th><th>Punch out</th><th>Hours</th></tr></thead><tbody>
                        @forelse($punches as $punch)
                            @php($minutes = $punch->punch_out ? max(0, \Carbon\Carbon::parse($punch->punch_in)->diffInMinutes(\Carbon\Carbon::parse($punch->punch_out))) : null)
                            <tr><td>{{ $punch->user?->name ?? 'Employee unavailable' }}</td><td>{{ \Carbon\Carbon::parse($punch->punch_in)->format('d M Y') }}</td><td>{{ \Carbon\Carbon::parse($punch->punch_in)->format('h:i A') }}</td><td>{{ $punch->punch_out ? \Carbon\Carbon::parse($punch->punch_out)->format('h:i A') : '—' }}</td><td>{{ $minutes === null ? 'In progress' : number_format($minutes / 60, 2) }}</td></tr>
                        @empty<tr><td colspan="5" class="text-center text-muted py-4">No attendance records found for these filters.</td></tr>@endforelse
                    </tbody></table></div>
                    <div class="mt-3">{{ $punches->links() }}</div>
                </div>
            </div>
        </div>
    </section>
</div>
<style>.attendance-page .attendance-records{border:0;border-radius:12px;box-shadow:0 4px 18px rgba(26,48,83,.08);overflow:hidden}.attendance-page .card-header{background:#fff;border-bottom:1px solid #e8eef7;padding:15px 18px}.attendance-page .card-title{font-weight:700;color:#1b3152}.attendance-page .form-control{border-radius:7px}.attendance-page .table thead th{white-space:nowrap;color:#233b5d}</style>
@endsection
