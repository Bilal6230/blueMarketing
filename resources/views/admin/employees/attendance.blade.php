@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center">
        <div><h1>Employee Attendance Review</h1><div class="text-muted">Project {{ $projectId }} · {{ $month }}</div></div>
        <div>@can('read payroll')<a class="btn btn-outline-success mr-2" href="{{ route('employee-payroll.preview', ['month' => $month]) }}">Payroll Preview</a>@endcan @can('read user')<a class="btn btn-outline-primary" href="{{ route('employee-definitions.index') }}">Employee Setup</a>@endcan</div>
    </div></section>
    <section class="content"><div class="container-fluid">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="card"><div class="card-body d-flex flex-wrap justify-content-between align-items-end">
            <form method="GET" action="{{ route('employee-attendance.index') }}" class="form-inline mb-2">
                <label for="month" class="mr-2">Month</label><input id="month" class="form-control mr-2" type="month" name="month" value="{{ $month }}">
                <button class="btn btn-outline-primary">View</button>
            </form>
            @if ($canSnapshot)
                <form method="POST" action="{{ route('employee-attendance.prepare') }}" class="mb-2">@csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button class="btn btn-primary">Prepare missing attendance snapshots</button>
                </form>
            @endif
        </div></div>
        <div class="row">
            @foreach (['unreviewed' => 'Needs review', 'present' => 'Present', 'absent' => 'Absent', 'leave' => 'Leave', 'half_day' => 'Half day', 'short_leave' => 'Short leave', 'holiday' => 'Holiday', 'weekly_off' => 'Weekly off'] as $key => $label)
                <div class="col-6 col-md-3 col-xl-2 mb-2"><div class="small-box bg-light mb-0"><div class="inner"><h4>{{ (int) ($counts[$key] ?? 0) }}</h4><p>{{ $label }}</p></div></div></div>
            @endforeach
        </div>
        @if ($unreviewed > 0)<div class="alert alert-warning">{{ $unreviewed }} scheduled workday(s) have no punch and need a reviewed status before payroll.</div>@endif
        <div class="card"><div class="card-header"><strong>Attendance snapshots</strong></div><div class="card-body table-responsive">
            <table class="table table-bordered table-striped"><thead><tr><th>Date</th><th>Employee</th><th>Department / Designation</th><th>Punch in</th><th>Punch out</th><th>Source events</th><th>Status</th><th>Review</th></tr></thead><tbody>
            @forelse ($records as $record)
                <tr>
                    <td>{{ $record->work_date->format('Y-m-d') }}</td>
                    <td>{{ $record->user?->name ?? 'Unavailable user' }}<br><small>{{ $record->employee?->employee_code }}</small></td>
                    <td>{{ $record->employee?->department?->name ?? '—' }} / {{ $record->employee?->designation?->name ?? '—' }}</td>
                    <td>{{ $record->punch_in?->format('H:i:s') ?? '—' }}</td><td>{{ $record->punch_out?->format('H:i:s') ?? '—' }}</td>
                    <td>{{ count($record->source_punch_ids ?? []) }} punch(es)</td>
                    <td><span class="badge badge-{{ $record->status === 'unreviewed' ? 'warning' : 'success' }}">{{ str_replace('_', ' ', ucfirst($record->status)) }}</span>
                        @if ($record->review_reason)<br><small>{{ $record->review_reason }}</small>@endif
                    </td>
                    <td>
                        @if ($canReview)
                            <form method="POST" action="{{ route('employee-attendance.review', $record->id) }}" class="d-flex" style="min-width:280px">@csrf @method('PATCH')
                                <select class="form-control form-control-sm mr-1" name="status" required>
                                    <option value="">Select status</option>
                                    @foreach (['present', 'absent', 'leave', 'half_day', 'short_leave', 'holiday', 'weekly_off'] as $status)
                                        <option value="{{ $status }}" @selected($record->status === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                    @endforeach
                                </select>
                                <input class="form-control form-control-sm mr-1" name="reason" maxlength="500" placeholder="Review reason" required>
                                <button class="btn btn-sm btn-outline-primary">Save</button>
                            </form>
                        @else — @endif
                    </td>
                </tr>
            @empty <tr><td colspan="8" class="text-center text-muted">No snapshots for this month. Prepare attendance to capture existing punch events.</td></tr> @endforelse
            </tbody></table>{{ $records->links() }}
        </div></div>
    </div></section>
</div>
@endsection
