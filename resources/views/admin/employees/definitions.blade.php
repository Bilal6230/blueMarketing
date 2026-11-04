@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center">
        <div><h1>Employee Setup</h1><div class="text-muted">Project {{ getSelectedTown() }} · definitions and effective-dated employment settings</div></div>
        <div>@can('read attendance')<a class="btn btn-outline-primary" href="{{ route('employee-attendance.index') }}">Attendance Review</a>@endcan <a class="btn btn-outline-primary" href="{{ route('user.index') }}">Employee Accounts</a></div>
    </div></section>
    <section class="content"><div class="container-fluid">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if (!$users->count())<div class="alert alert-warning">There are no user accounts assigned to the selected project.</div>@endif
        <div class="row">
            @foreach (['department' => 'Departments', 'designation' => 'Designations', 'category' => 'Employee Categories'] as $kind => $heading)
                <div class="col-xl-4 mb-3"><div class="card h-100">
                    <div class="card-header"><strong>{{ $heading }}</strong></div><div class="card-body">
                        @can('create user')
                        <form method="POST" action="{{ route('employee-definitions.store') }}" class="form-row">@csrf
                            <input type="hidden" name="kind" value="{{ $kind }}">
                            <div class="form-group col-sm-4"><label>Code</label><input class="form-control" name="code" maxlength="40" required></div>
                            <div class="form-group col-sm-8"><label>Name</label><input class="form-control" name="name" maxlength="120" required></div>
                            <div class="col-12"><button class="btn btn-primary btn-sm">Add definition</button></div>
                        </form>
                        @endcan
                        <hr>
                        @forelse ($definitions->get($kind, collect()) as $definition)
                            @can('update user')
                            <form method="POST" action="{{ route('employee-definitions.update', $definition->id) }}" class="border rounded p-2 mb-2">@csrf @method('PUT')
                                <div class="d-flex justify-content-between"><strong>{{ $definition->code }}</strong><span class="badge badge-{{ $definition->is_active ? 'success' : 'secondary' }}">{{ $definition->is_active ? 'Active' : 'Inactive' }}</span></div>
                                <input class="form-control form-control-sm mt-1" name="name" value="{{ $definition->name }}" required>
                                <select class="form-control form-control-sm mt-1" name="is_active"><option value="1" @selected($definition->is_active)>Active</option><option value="0" @selected(!$definition->is_active)>Inactive</option></select>
                                <input class="form-control form-control-sm mt-1" name="reason" maxlength="500" placeholder="Reason for change" required>
                                <button class="btn btn-outline-primary btn-sm mt-1">Save</button>
                            </form>
                            @endcan
                        @empty <div class="text-muted">No definitions yet.</div> @endforelse
                    </div>
                </div></div>
            @endforeach
        </div>

        <div class="card mb-3"><div class="card-header"><strong>Employee Profile & Employment Settings</strong></div><div class="card-body">
            <div class="alert alert-info">Only this project's user accounts can be selected. Every save creates a dated revision; existing payroll/history is not recalculated.</div>
            @canany(['create user', 'update user'])
            <form id="employee-profile-form" method="POST" action="{{ route('employee-profiles.store') }}">@csrf
                <h5>Employee identity</h5><div class="form-row">
                    <div class="form-group col-md-4"><label>Employee user</label><select class="form-control" name="user_id" required><option value="">Select employee</option>@foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></div>
                    <div class="form-group col-md-2"><label>Employee code</label><input class="form-control" name="employee_code" maxlength="40" required></div>
                    <div class="form-group col-md-2"><label>Joining date</label><input class="form-control" type="date" name="joining_date" required></div>
                    @foreach (['department' => 'Department', 'designation' => 'Designation', 'category' => 'Category'] as $kind => $label)
                        <div class="form-group col-md-2"><label>{{ $label }}</label><select class="form-control" name="{{ $kind === 'category' ? 'employee_category_definition_id' : $kind.'_definition_id' }}" {{ $kind !== 'category' ? 'required' : '' }}><option value="">{{ $kind === 'category' ? 'Not set' : 'Select' }}</option>@foreach ($definitions->get($kind, collect())->where('is_active', true) as $definition)<option value="{{ $definition->id }}">{{ $definition->name }}</option>@endforeach</select></div>
                    @endforeach
                </div>
                <h5>Salary and attendance policy</h5><div class="form-row">
                    <div class="form-group col-md-3"><label>Effective from</label><input class="form-control" type="date" name="effective_from" required></div>
                    <div class="form-group col-md-3"><label>Basic salary</label><input class="form-control" type="number" min="0" step="0.01" name="basic_salary" value="0" required></div>
                    <div class="form-group col-md-3"><label>Payment method</label><input class="form-control" name="payment_method" maxlength="40" placeholder="Cash / bank / other" required></div>
                    <div class="form-group col-md-3"><label>Salary status</label><select class="form-control" name="salary_status"><option value="active">Active</option><option value="hold">Hold</option></select></div>
                    <div class="form-group col-md-4"><label>Salary proration</label><select class="form-control" name="salary_proration_method"><option value="manual_review">Require review (not configured)</option><option value="calendar_days">Calendar days</option><option value="scheduled_days">Scheduled workdays</option></select><small class="text-muted">Used for joining dates and salary changes within a month.</small></div>
                    <div class="form-group col-md-12"><label>Allowances / additions</label></div>
                    @foreach ([0, 1, 2, 3] as $allowance)
                        <div class="form-group col-md-3"><label>Allowance name</label><input class="form-control" name="allowances[{{ $allowance }}][name]" maxlength="80"></div>
                        <div class="form-group col-md-3"><label>Amount</label><input class="form-control" type="number" min="0" step="0.01" name="allowances[{{ $allowance }}][amount]"></div>
                    @endforeach
                    <div class="form-group col-md-3"><label>Duty start</label><input class="form-control" type="time" name="duty_start"></div>
                    <div class="form-group col-md-3"><label>Duty end</label><input class="form-control" type="time" name="duty_end"></div>
                    <div class="form-group col-md-2"><label>Break minutes</label><input class="form-control" type="number" min="0" max="1440" name="break_minutes" value="0" required></div>
                    <div class="form-group col-md-2"><label>Daily hours</label><input class="form-control" type="number" min="0.01" max="24" step="0.01" name="daily_hours"></div>
                    <div class="form-group col-md-2"><label>Work days/week</label><input class="form-control" type="number" min="1" max="7" name="working_days_per_week"></div>
                    <div class="form-group col-md-12"><label>Weekly off days</label><div>@foreach ([0 => 'Mon', 1 => 'Tue', 2 => 'Wed', 3 => 'Thu', 4 => 'Fri', 5 => 'Sat', 6 => 'Sun'] as $day => $label)<label class="mr-3"><input type="checkbox" name="weekly_off_days[]" value="{{ $day }}"> {{ $label }}</label>@endforeach</div></div>
                    <div class="form-group col-md-3"><label>Grace minutes</label><input class="form-control" type="number" min="0" max="1440" name="grace_minutes" value="0" required></div>
                    <div class="form-group col-md-3"><label>Late fine method</label><select class="form-control" name="late_fine_policy[method]"><option value="none">None</option><option value="fixed">Fixed amount</option><option value="range">Slab/range</option><option value="percentage">Percentage</option></select></div>
                    <div class="form-group col-md-3"><label>Late fine amount / rate (%)</label><input class="form-control" type="number" min="0" step="0.01" name="late_fine_policy[amount]" value="0"></div>
                    <div class="form-group col-md-3"><label>Percentage fine base</label><select class="form-control" name="late_fine_policy[base]"><option value="">Select base if percentage</option><option value="daily_basic_salary">Daily basic salary</option></select></div>
                    <div class="form-group col-md-3"><label>Late fine daily cap</label><input class="form-control" type="number" min="0" step="0.01" name="late_fine_policy[daily_cap]"></div>
                    <div class="form-group col-md-3"><label>Late fine monthly cap</label><input class="form-control" type="number" min="0" step="0.01" name="late_fine_policy[monthly_cap]"></div>
                    <div class="form-group col-md-12"><label>Late-fine ranges (used when method is Slab/range)</label></div>
                    @foreach ([0, 1, 2] as $range)
                        <div class="form-group col-md-2"><label>Range {{ $range + 1 }} from (min)</label><input class="form-control" type="number" min="0" max="1440" name="late_fine_policy[ranges][{{ $range }}][from_minutes]"></div>
                        <div class="form-group col-md-2"><label>To (min)</label><input class="form-control" type="number" min="0" max="1440" name="late_fine_policy[ranges][{{ $range }}][to_minutes]"></div>
                        <div class="form-group col-md-2"><label>Fine amount</label><input class="form-control" type="number" min="0" step="0.01" name="late_fine_policy[ranges][{{ $range }}][amount]"></div>
                    @endforeach
                    <div class="form-group col-md-3"><label>Absence deduction</label><input class="form-control" type="number" min="0" step="0.01" name="absence_deduction" value="0" required></div>
                    <div class="form-group col-md-3"><label>Leave deduction</label><input class="form-control" type="number" min="0" step="0.01" name="leave_deduction" value="0" required></div>
                    <div class="form-group col-md-3"><label>Half-day after (minutes)</label><input class="form-control" type="number" min="0" max="1440" name="half_day_after_minutes"></div>
                    <div class="form-group col-md-3"><label>Half-day deduction per day</label><input class="form-control" type="number" min="0" step="0.01" name="half_day_deduction" value="0" required></div>
                    <div class="form-group col-md-3"><label>Short leave allowance (minutes)</label><input class="form-control" type="number" min="0" max="1440" name="short_leave_minutes"></div>
                    <div class="form-group col-md-3"><label>Short-leave deduction rule</label><select class="form-control" name="short_leave_method"><option value="">Require review</option><option value="none">No deduction</option><option value="fixed">Fixed amount per event</option></select></div>
                    <div class="form-group col-md-3"><label>Short-leave deduction amount</label><input class="form-control" type="number" min="0" step="0.01" name="short_leave_deduction" value="0" required></div>
                    <div class="form-group col-md-2"><label>Early departure method</label><select class="form-control" name="early_departure_method"><option value="">None</option><option value="fixed">Fixed per early day</option><option value="per_minute">Amount per minute</option></select></div>
                    <div class="form-group col-md-2"><label>Early departure amount</label><input class="form-control" type="number" min="0" step="0.01" name="early_departure_deduction" value="0" required></div>
                    <div class="form-group col-md-3"><label>Overtime method</label><select class="form-control" name="overtime_method"><option value="">None</option><option value="fixed">Fixed per overtime day</option><option value="hourly">Amount per hour</option><option value="multiplier">Basic hourly rate × factor</option></select></div>
                    <div class="form-group col-md-3"><label>Overtime amount / factor</label><input class="form-control" type="number" min="0" step="0.01" name="overtime_rate" value="0" required></div>
                    @foreach ([0, 1] as $adjustment)
                        <div class="form-group col-md-3"><label>Other addition/deduction name</label><input class="form-control" name="other_adjustments[{{ $adjustment }}][name]" maxlength="80"></div>
                        <div class="form-group col-md-2"><label>Type</label><select class="form-control" name="other_adjustments[{{ $adjustment }}][type]"><option value="addition">Addition</option><option value="deduction">Deduction</option></select></div>
                        <div class="form-group col-md-3"><label>Amount</label><input class="form-control" type="number" min="0" step="0.01" name="other_adjustments[{{ $adjustment }}][amount]"></div>
                    @endforeach
                    <div class="form-group col-12"><label>Reason for this revision</label><input class="form-control" name="reason" maxlength="500" required></div>
                </div>
                <button class="btn btn-primary">Save profile and revision</button>
            </form>
            @endcanany
        </div></div>

        <div class="row">
            <div class="col-xl-6 mb-3"><div class="card h-100">
                <div class="card-header"><strong>Definition Change History</strong><small class="text-muted float-right">Latest {{ $definitionAudits->count() }} entries</small></div>
                <div class="card-body table-responsive p-0"><table class="table table-sm table-striped mb-0">
                    <thead><tr><th>When / Who</th><th>Definition</th><th>Change / Reason</th></tr></thead><tbody>
                    @forelse ($definitionAudits as $audit)
                        <tr><td>{{ $audit->created_at }}<br>{{ $audit->actor_name ?? 'Former user' }}</td>
                            <td>{{ ucfirst($audit->kind) }} · {{ $audit->code }}<br>{{ $audit->definition_name }}</td>
                            <td><strong>{{ str_replace('_', ' ', ucfirst($audit->action)) }}</strong><br>{{ $audit->reason ?: '—' }}
                                <details><summary>Values</summary><small>Before: <pre class="mb-1">{{ $audit->old_values ? json_encode(json_decode($audit->old_values, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre>After: <pre class="mb-0">{{ $audit->new_values ? json_encode(json_decode($audit->new_values, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></small></details>
                            </td></tr>
                    @empty <tr><td colspan="3" class="text-center text-muted p-3">No definition changes recorded.</td></tr> @endforelse
                    </tbody>
                </table></div>
            </div></div>
            <div class="col-xl-6 mb-3"><div class="card h-100">
                <div class="card-header"><strong>Employee Profile Change History</strong><small class="text-muted float-right">Latest {{ $profileAudits->count() }} entries</small></div>
                <div class="card-body table-responsive p-0"><table class="table table-sm table-striped mb-0">
                    <thead><tr><th>When / Who</th><th>Employee</th><th>Change / Reason</th></tr></thead><tbody>
                    @forelse ($profileAudits as $audit)
                        <tr><td>{{ $audit->created_at }}<br>{{ $audit->actor_name ?? 'Former user' }}</td>
                            <td>{{ $audit->employee_name ?? 'Unavailable user' }}<br>{{ $audit->employee_code }}</td>
                            <td>{{ $audit->reason }}<details><summary>Before / after values</summary><small>Before: <pre class="mb-1">{{ $audit->old_values ? json_encode(json_decode($audit->old_values, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'New profile' }}</pre>After: <pre class="mb-0">{{ json_encode(json_decode($audit->new_values, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></small></details></td></tr>
                    @empty <tr><td colspan="3" class="text-center text-muted p-3">No profile changes recorded.</td></tr> @endforelse
                    </tbody>
                </table></div>
            </div></div>
        </div>

        <div class="card"><div class="card-header"><strong>Saved Employee Profiles</strong></div><div class="card-body table-responsive"><table class="table table-bordered table-striped">
            <thead><tr><th>Employee</th><th>Code</th><th>Joining</th><th>Department</th><th>Designation</th><th>Category</th><th>Current terms / history</th><th>Form</th></tr></thead><tbody>
            @forelse ($profiles as $profile)
                @php($revision = $profile->revisions->first())
                <tr>
                    <td>{{ $profile->user?->name ?? 'Unavailable user' }}</td><td>{{ $profile->employee_code }}</td>
                    <td>{{ $profile->joining_date?->format('Y-m-d') ?? '—' }}</td><td>{{ $profile->department?->name ?? '—' }}</td>
                    <td>{{ $profile->designation?->name ?? '—' }}</td><td>{{ $profile->category?->name ?? '—' }}</td>
                    <td>
                        @if ($revision)
                            Effective {{ $revision->effective_from->format('Y-m-d') }} · Salary {{ number_format((float) $revision->basic_salary, 2) }} · {{ ucfirst($revision->salary_status) }}
                            <details class="mt-1"><summary>Revision history ({{ $profile->revisions->count() }})</summary>
                                <div class="table-responsive mt-2"><table class="table table-sm table-bordered"><thead><tr><th>Effective</th><th>Salary</th><th>Schedule</th><th>Attendance policy</th><th>Allowances/adjustments</th><th>Reason</th></tr></thead><tbody>
                                    @foreach ($profile->revisions as $revisionItem)
                                        <tr>
                                            <td>{{ $revisionItem->effective_from->format('Y-m-d') }}<br>{{ ucfirst($revisionItem->salary_status) }} · {{ $revisionItem->payment_method }}</td>
                                            <td>{{ number_format((float) $revisionItem->basic_salary, 2) }}<br>Proration: {{ str_replace('_', ' ', $revisionItem->salary_proration_method ?? 'manual review') }}<br>Absence {{ number_format((float) $revisionItem->absence_deduction, 2) }} · Leave {{ number_format((float) $revisionItem->leave_deduction, 2) }}</td>
                                            <td>{{ $revisionItem->duty_start }}–{{ $revisionItem->duty_end }}<br>{{ $revisionItem->daily_hours }} hours · {{ $revisionItem->working_days_per_week }} days/week<br>Off: {{ implode(', ', $revisionItem->weekly_off_days ?? []) }}</td>
                                            <td>Grace {{ $revisionItem->grace_minutes }}m<br>Late fine: {{ json_encode($revisionItem->late_fine_policy) }}<br>Half day {{ $revisionItem->half_day_after_minutes }}m / {{ number_format((float) $revisionItem->half_day_deduction, 2) }}<br>Short leave {{ $revisionItem->short_leave_minutes }}m / {{ $revisionItem->short_leave_method ?? 'review required' }} {{ number_format((float) $revisionItem->short_leave_deduction, 2) }}<br>Early deduction {{ $revisionItem->early_departure_method }} {{ $revisionItem->early_departure_deduction }} · OT {{ $revisionItem->overtime_method }} {{ $revisionItem->overtime_rate }}</td>
                                            <td>{{ json_encode($revisionItem->allowances) }}<br>{{ json_encode($revisionItem->other_adjustments) }}</td>
                                            <td>{{ $revisionItem->reason }}<br><small>By user #{{ $revisionItem->changed_by }}</small></td>
                                        </tr>
                                    @endforeach
                                </tbody></table></div>
                            </details>
                        @else No employment revision @endif
                    </td>
                    <td>@canany(['create user', 'update user'])<button type="button" class="btn btn-sm btn-outline-primary load-employee-setup" data-user-id="{{ $profile->user_id }}">Edit setup</button>@endcanany</td>
                </tr>
            @empty <tr><td colspan="8" class="text-center text-muted">No profiles assigned yet. Existing accounts remain unchanged until assigned.</td></tr> @endforelse
            </tbody></table></div></div>
    </div></section>
</div>
@canany(['create user', 'update user'])
<script>
(() => {
    const form = document.getElementById('employee-profile-form');
    if (!form) return;
    const saved = @json($profiles->keyBy('user_id')->map(function ($profile) {
        $revision = $profile->revisions->first();
        return [
            'identity' => $profile->only(['user_id', 'employee_code', 'joining_date', 'department_definition_id', 'designation_definition_id', 'employee_category_definition_id']),
            'revision' => $revision?->only(['effective_from', 'basic_salary', 'allowances', 'payment_method', 'salary_status', 'salary_proration_method', 'duty_start', 'duty_end', 'break_minutes', 'daily_hours', 'working_days_per_week', 'weekly_off_days', 'grace_minutes', 'late_fine_policy', 'absence_deduction', 'leave_deduction', 'half_day_after_minutes', 'half_day_deduction', 'short_leave_minutes', 'short_leave_method', 'short_leave_deduction', 'early_departure_method', 'early_departure_deduction', 'overtime_method', 'overtime_rate', 'other_adjustments']),
        ];
    }));
    const assign = (name, value) => {
        const field = Array.from(form.elements).find((item) => item.name === name);
        if (field) field.value = value ?? '';
    };
    const fill = (prefix, value) => {
        if (Array.isArray(value)) {
            if (value.every((item) => typeof item !== 'object' || item === null)) {
                const boxes = Array.from(form.querySelectorAll(`[name="${prefix}[]"]`));
                boxes.forEach((box) => { box.checked = value.map(String).includes(box.value); });
            } else {
                value.forEach((item, index) => fill(`${prefix}[${index}]`, item));
            }
            return;
        }
        if (value && typeof value === 'object') {
            Object.entries(value).forEach(([key, item]) => fill(prefix ? `${prefix}[${key}]` : key, item));
            return;
        }
        assign(prefix, value);
    };
    document.querySelectorAll('.load-employee-setup').forEach((button) => button.addEventListener('click', () => {
        const setup = saved[button.dataset.userId];
        if (!setup) return;
        form.reset();
        fill('', setup.identity);
        fill('', setup.revision || {});
        if (setup.revision?.effective_from) {
            const [year, month, day] = setup.revision.effective_from.slice(0, 10).split('-').map(Number);
            const next = new Date(year, month - 1, day + 1);
            assign('effective_from', `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`);
        }
        assign('reason', '');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
})();
</script>
@endcanany
@endsection
