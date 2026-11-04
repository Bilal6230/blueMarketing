<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAttendanceRecord;
use App\Models\EmployeeProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EmployeePayrollPreviewController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('employee_profiles')
            && Schema::hasTable('employee_employment_revisions')
            && Schema::hasTable('employee_attendance_records'), 409,
            'Employee setup and attendance migrations are required before payroll preview.');

        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!$request->user()->hasRole('superadmin')) {
            abort_unless((int) $request->user()->project_id === $projectId, 403);
        }

        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $month = $data['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
        abort_if($start->startOfMonth()->gt(now()->startOfMonth()), 422, 'Future payroll periods are not available.');
        $end = $start->endOfMonth()->min(now()->endOfDay());

        $employeeOptions = EmployeeProfile::with('user:id,name')->where('project_id', $projectId)
            ->orderBy('employee_code')->get();
        $profiles = EmployeeProfile::with(['user:id,name', 'department:id,name', 'designation:id,name', 'revisions'])
            ->where('project_id', $projectId)
            ->when(!empty($data['employee_id']), fn ($query) => $query->whereKey((int) $data['employee_id']))
            ->orderBy('employee_code')->get();

        $profileIds = $profiles->modelKeys();
        $attendance = $profileIds
            ? EmployeeAttendanceRecord::where('project_id', $projectId)->whereIn('employee_profile_id', $profileIds)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->get()->groupBy('employee_profile_id')
            : collect();

        $rows = $profiles->map(fn (EmployeeProfile $profile) => $this->previewEmployee(
            $profile, $attendance->get($profile->id, collect()), $start, $end
        ));

        if ($request->query('export') === 'csv') {
            $csvRows = $rows->map(fn ($row) => [
                $projectId, $month, $row['employee'], $row['code'], $row['department'], $row['designation'],
                $this->money($row['basic']), $this->money($row['allowances']), $this->money($row['other_additions']),
                $this->money($row['attendance_deductions']), $this->money($row['late_fine']),
                $this->money($row['early_departure_deduction']), $this->money($row['overtime_addition']),
                $this->money($row['other_deductions']), $this->money($row['gross']), $this->money($row['net']),
                json_encode($row['counts'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $row['ready'] ? 'Ready for review' : 'Review required', implode('; ', $row['flags']),
            ]);
            return response()->streamDownload(function () use ($csvRows): void {
                $stream = fopen('php://output', 'wb');
                fputcsv($stream, ['Project ID', 'Month', 'Employee', 'Employee code', 'Department', 'Designation', 'Basic', 'Allowances', 'Other additions', 'Attendance deductions', 'Late fines', 'Early-departure deduction', 'Overtime addition', 'Other deductions', 'Gross preview', 'Net preview', 'Attendance counts', 'Readiness', 'Review flags']);
                foreach ($csvRows as $values) {
                    foreach ([2, 3, 4, 5, 16, 17, 18] as $column) $values[$column] = $this->safeCsvText($values[$column]);
                    fputcsv($stream, $values);
                }
                fclose($stream);
            }, 'employee-payroll-preview-'.$month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('admin.employees.payroll-preview', [
            'title' => 'Monthly Payroll Preview', 'projectId' => $projectId, 'month' => $month,
            'employees' => $employeeOptions->map(fn ($profile) => ['id' => $profile->id, 'name' => $profile->user?->name ?? $profile->employee_code]),
            'rows' => $rows, 'totals' => $this->sumRows($rows),
            'canCreateSnapshot' => $request->user()->can('create payroll'),
            'canApprovePayroll' => $request->user()->can('approve payroll'),
            'runs' => Schema::hasTable('employee_payroll_runs')
                ? DB::table('employee_payroll_runs')->where('project_id', $projectId)->where('payroll_month', $month)->orderByDesc('run_number')->get()
                : collect(),
        ]);
    }

    public function snapshot(Request $request)
    {
        abort_unless($request->user()->can('create payroll'), 403);
        abort_unless(Schema::hasTable('employee_payroll_runs') && Schema::hasTable('employee_payroll_run_items')
            && Schema::hasTable('employee_payroll_run_audits'), 409, 'Payroll snapshot migration is required.');
        abort_if($request->filled('employee_id'), 422, 'Select all employees to create a consolidated payroll snapshot.');
        $preview = $this->index($request);
        abort_unless($preview instanceof \Illuminate\Contracts\View\View, 422, 'Payroll preview is unavailable.');
        $data = $preview->getData();
        $rows = $data['rows'];
        abort_if($rows->isEmpty(), 422, 'No employee payroll rows are available to snapshot.');
        abort_if($rows->contains(fn ($row) => !$row['ready']), 422, 'Resolve every payroll review flag before creating a snapshot.');
        $projectId = (int) $data['projectId'];
        $month = (string) $data['month'];
        abort_if($month >= now()->format('Y-m'), 422, 'Payroll snapshots can only be created for a completed month.');
        $snapshotAt = now();
        $created = DB::transaction(function () use ($projectId, $month, $rows, $snapshotAt): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $next = ((int) DB::table('employee_payroll_runs')->where('project_id', $projectId)->where('payroll_month', $month)->max('run_number')) + 1;
            $payload = $rows->map(fn ($row) => $this->snapshotPayload($row))->values()->all();
            usort($payload, fn ($a, $b) => (int) $a['employee_profile_id'] <=> (int) $b['employee_profile_id']);
            $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $runId = DB::table('employee_payroll_runs')->insertGetId([
                'project_id' => $projectId, 'payroll_month' => $month, 'run_number' => $next,
                'status' => 'pending_approval', 'employee_count' => count($payload),
                'gross_total' => $this->money((int) $rows->sum('gross')),
                'net_total' => $this->money((int) $rows->sum('net')),
                'snapshot_hash' => hash('sha256', $canonical), 'created_by' => auth()->id(),
                'created_at' => $snapshotAt, 'updated_at' => $snapshotAt,
            ]);
            foreach ($payload as $item) {
                $itemJson = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                DB::table('employee_payroll_run_items')->insert([
                    'project_id' => $projectId, 'payroll_run_id' => $runId,
                    'employee_profile_id' => $item['employee_profile_id'],
                    'gross_amount' => $this->money($item['gross']), 'net_amount' => $this->money($item['net']),
                    'snapshot_data' => $itemJson, 'source_hash' => hash('sha256', $itemJson), 'created_at' => $snapshotAt,
                ]);
            }
            DB::table('employee_payroll_run_audits')->insert([
                'project_id' => $projectId, 'payroll_run_id' => $runId, 'action' => 'snapshot_created',
                'details' => json_encode(['month' => $month, 'run_number' => $next, 'snapshot_hash' => hash('sha256', $canonical)], JSON_THROW_ON_ERROR),
                'actor_id' => auth()->id(), 'created_at' => $snapshotAt,
            ]);
            return $runId;
        }, 3);

        return redirect()->route('employee-payroll.preview', ['month' => $month])
            ->with('success', 'Immutable payroll snapshot #'.$created.' created for approval. No voucher or payment was posted.');
    }

    public function approve(Request $request, int $runId)
    {
        abort_unless($request->user()->can('approve payroll'), 403);
        abort_unless(Schema::hasTable('employee_payroll_runs') && Schema::hasTable('employee_payroll_run_audits'), 409,
            'Payroll snapshot migration is required.');
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!$request->user()->hasRole('superadmin')) abort_unless((int) $request->user()->project_id === $projectId, 403);

        DB::transaction(function () use ($projectId, $runId): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $run = DB::table('employee_payroll_runs')->where('project_id', $projectId)->where('id', $runId)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'pending_approval', 409, 'Only a pending payroll snapshot can be approved.');
            $alreadyApproved = DB::table('employee_payroll_runs')->where('project_id', $projectId)
                ->where('payroll_month', $run->payroll_month)->where('status', 'approved')->exists();
            abort_if($alreadyApproved, 409, 'An approved payroll run already exists for this project and month.');
            $items = DB::table('employee_payroll_run_items')->where('project_id', $projectId)->where('payroll_run_id', $runId)->get();
            abort_if($items->count() !== (int) $run->employee_count || $items->isEmpty(), 409, 'Payroll snapshot integrity check failed.');
            $canonicalItems = [];
            $grossTotal = 0;
            $netTotal = 0;
            foreach ($items as $item) {
                abort_unless(hash_equals((string) $item->source_hash, hash('sha256', (string) $item->snapshot_data)), 409, 'Payroll snapshot integrity check failed.');
                $canonicalItems[] = json_decode((string) $item->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
                $grossTotal += $this->cents($item->gross_amount);
                $netTotal += $this->cents($item->net_amount);
            }
            abort_unless($grossTotal === $this->cents($run->gross_total) && $netTotal === $this->cents($run->net_total), 409, 'Payroll snapshot integrity check failed.');
            usort($canonicalItems, fn ($a, $b) => (int) $a['employee_profile_id'] <=> (int) $b['employee_profile_id']);
            $canonical = json_encode($canonicalItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            abort_unless(hash_equals((string) $run->snapshot_hash, hash('sha256', $canonical)), 409, 'Payroll snapshot integrity check failed.');
            $now = now();
            DB::table('employee_payroll_runs')->where('id', $runId)->where('project_id', $projectId)->update([
                'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('employee_payroll_run_audits')->insert([
                'project_id' => $projectId, 'payroll_run_id' => $runId, 'action' => 'approved',
                'details' => json_encode(['snapshot_hash' => $run->snapshot_hash, 'employee_count' => $run->employee_count], JSON_THROW_ON_ERROR),
                'actor_id' => auth()->id(), 'created_at' => $now,
            ]);
        }, 3);

        return back()->with('success', 'Payroll snapshot approved and locked. No voucher or payment was posted.');
    }

    public function showRun(Request $request, int $runId)
    {
        abort_unless($request->user()->can('read payroll'), 403);
        abort_unless(Schema::hasTable('employee_payroll_runs') && Schema::hasTable('employee_payroll_run_items'), 409,
            'Payroll snapshot migration is required.');
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!$request->user()->hasRole('superadmin')) abort_unless((int) $request->user()->project_id === $projectId, 403);

        $run = DB::table('employee_payroll_runs')->where('project_id', $projectId)->where('id', $runId)->first();
        abort_unless($run, 404);
        $items = DB::table('employee_payroll_run_items')->where('project_id', $projectId)->where('payroll_run_id', $runId)
            ->orderBy('employee_profile_id')->get()->map(function ($item) {
                $item->data = json_decode((string) $item->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals((string) $item->source_hash, hash('sha256', (string) $item->snapshot_data)), 409,
                    'Payroll snapshot integrity check failed.');
                return $item;
            });
        $filters = $request->validate([
            'employee' => ['nullable', 'integer', 'min:1'],
            'department' => ['nullable', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:120'],
        ]);
        $departments = $items->map(fn ($item) => $item->data['department'] ?? '—')->unique()->sort()->values();
        $designations = $items->map(fn ($item) => $item->data['designation'] ?? '—')->unique()->sort()->values();
        $filtered = $items->filter(function ($item) use ($filters): bool {
            $data = $item->data;
            if (!empty($filters['employee']) && (int) $item->employee_profile_id !== (int) $filters['employee']) return false;
            if (!empty($filters['department']) && ($data['department'] ?? '—') !== $filters['department']) return false;
            if (!empty($filters['designation']) && ($data['designation'] ?? '—') !== $filters['designation']) return false;
            return true;
        })->values();

        return view('admin.employees.payroll-run', [
            'title' => 'Payroll Run #'.$run->run_number, 'projectId' => $projectId, 'run' => $run,
            'items' => $filtered, 'employees' => $items->map(fn ($item) => [
                'id' => $item->employee_profile_id, 'name' => ($item->data['employee'] ?? '').' ('.($item->data['code'] ?? '').')',
            ])->values(), 'departments' => $departments, 'designations' => $designations,
            'filters' => $filters,
        ]);
    }

    private function snapshotPayload(array $row): array
    {
        return $row;
    }

    private function previewEmployee(EmployeeProfile $profile, $records, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $revisions = $profile->revisions->sortBy('effective_from')->values();
        $revisionAt = static fn (CarbonImmutable $date) => $revisions->last(
            fn ($revision) => $revision->effective_from->toDateString() <= $date->toDateString()
        );
        $revision = $revisionAt($end);
        $flags = [];
        if (!$revision) {
            return $this->emptyRow($profile, ['No effective salary revision exists for this period.']);
        }
        if ($revision->salary_status !== 'active') $flags[] = 'Salary is on hold.';
        if ($profile->joining_date && $profile->joining_date->gt($end)) $flags[] = 'Employee joining date is after this payroll period.';

        $byDate = $records->keyBy(fn ($record) => $record->work_date->toDateString());
        $counts = array_fill_keys(['present', 'absent', 'leave', 'half_day', 'short_leave', 'holiday', 'weekly_off', 'unreviewed', 'late'], 0);
        $lateFineByRevision = [];
        $absenceDeduction = 0;
        $leaveDeduction = 0;
        $halfDayDeduction = 0;
        $shortLeaveDeduction = 0;
        $earlyDepartureDeduction = 0;
        $overtimeAddition = 0;
        $join = $profile->joining_date?->toDateString();

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            if ($join && $date < $join) continue;
            $dayRevision = $revisionAt($day);
            if (!$dayRevision) { $flags[] = 'Employment terms are missing for part of this month.'; continue; }
            if ($dayRevision->salary_status !== 'active') $flags[] = 'Salary was on hold during this month.';
            if ($dayRevision->salary_status !== 'active') $flags[] = 'Salary was on hold during this month.';
            $record = $byDate->get($date);
            if (!$record) {
                $offDays = array_map('intval', $dayRevision->weekly_off_days ?? []);
                if (!in_array($day->dayOfWeekIso - 1, $offDays, true)) $flags[] = 'Attendance snapshot is missing for a scheduled workday.';
                continue;
            }
            $status = $record->status;
            if (!array_key_exists($status, $counts)) $status = 'unreviewed';
            $counts[$status]++;
            $configuredOffDays = array_map('intval', $dayRevision->weekly_off_days ?? []);
            $scheduledWeeklyOff = $status === 'weekly_off'
                && in_array($day->dayOfWeekIso - 1, $configuredOffDays, true);
            if ($status === 'unreviewed' || (!$record->reviewed_at && !$scheduledWeeklyOff)) $flags[] = 'Attendance includes unreviewed days.';
            if ($status === 'half_day') $halfDayDeduction += $this->cents($dayRevision->half_day_deduction);
            if ($status === 'short_leave') {
                if ($dayRevision->short_leave_method === 'fixed') {
                    $shortLeaveDeduction += $this->cents($dayRevision->short_leave_deduction);
                } elseif ($dayRevision->short_leave_method === null) {
                    $flags[] = 'Short-leave deduction rule is not configured.';
                }
            }
            $hasEarlyPolicy = $dayRevision->early_departure_method && $this->cents($dayRevision->early_departure_deduction) > 0;
            $hasOvertimePolicy = $dayRevision->overtime_method && $this->cents($dayRevision->overtime_rate) > 0;
            if ($status === 'present' && ($hasEarlyPolicy || $hasOvertimePolicy)) {
                $shiftEnd = $dayRevision->duty_end ? $this->shiftEnd($date, $dayRevision) : null;
                if (!$record->punch_out || !$shiftEnd) {
                    if ($hasEarlyPolicy) $flags[] = 'Early-departure rule cannot be checked because shift end or punch-out is missing.';
                    if ($hasOvertimePolicy) $flags[] = 'Overtime rule cannot be checked because shift end or punch-out is missing.';
                } else {
                    $punchOut = CarbonImmutable::parse($record->punch_out);
                    $earlyMinutes = max(0, (int) $punchOut->diffInMinutes($shiftEnd, false));
                    if ($earlyMinutes > 0 && $hasEarlyPolicy) {
                        $rate = $this->cents($dayRevision->early_departure_deduction);
                        $earlyDepartureDeduction += $dayRevision->early_departure_method === 'fixed' ? $rate : $rate * $earlyMinutes;
                    }
                    $overtimeMinutes = max(0, (int) $shiftEnd->diffInMinutes($punchOut, false));
                    if ($overtimeMinutes > 0 && $hasOvertimePolicy) {
                        $rate = $this->cents($dayRevision->overtime_rate);
                        if ($dayRevision->overtime_method === 'fixed') {
                            $overtimeAddition += $rate;
                        } elseif ($dayRevision->overtime_method === 'hourly') {
                            $overtimeAddition += (int) round($rate * $overtimeMinutes / 60);
                        } elseif ($dayRevision->overtime_method === 'multiplier') {
                            $scheduledDays = $this->scheduledDays($start, $start->endOfMonth(), $dayRevision->weekly_off_days ?? []);
                            $dailyHours = (float) ($dayRevision->daily_hours ?? 0);
                            if ($scheduledDays < 1 || $dailyHours <= 0) {
                                $flags[] = 'Overtime multiplier needs scheduled days and daily hours.';
                            } else {
                                $base = $this->cents($dayRevision->basic_salary);
                                $overtimeAddition += (int) round($base * $overtimeMinutes * $rate / ($scheduledDays * $dailyHours * 60 * 100));
                            }
                        }
                    }
                }
            }

            if ($status === 'absent') $absenceDeduction += $this->cents($dayRevision->absence_deduction);
            if ($status === 'leave') $leaveDeduction += $this->cents($dayRevision->leave_deduction);
            $latePolicy = $dayRevision->late_fine_policy ?? ['method' => 'none'];
            $lateMethod = $latePolicy['method'] ?? 'none';
            if ($status === 'present' && $record->punch_in && $dayRevision->duty_start) {
                $shiftStart = CarbonImmutable::parse($date.' '.$dayRevision->duty_start)->addMinutes((int) $dayRevision->grace_minutes);
                $lateMinutes = max(0, (int) $shiftStart->diffInMinutes(CarbonImmutable::parse($record->punch_in), false));
                if ($lateMinutes > 0) {
                    $counts['late']++;
                    $fine = match ($lateMethod) {
                        'fixed' => $this->cents($latePolicy['amount'] ?? 0),
                        'range' => $this->rangeFine($latePolicy['ranges'] ?? [], $lateMinutes),
                        'percentage' => $this->percentageLateFine($latePolicy, $dayRevision, $start),
                        default => 0,
                    };
                    if ($lateMethod === 'percentage' && empty($latePolicy['base'])) $flags[] = 'Percentage late fine base is not configured.';
                    if ($lateMethod === 'percentage' && ($latePolicy['base'] ?? null) === 'daily_basic_salary'
                        && $this->scheduledDays($start, $start->endOfMonth(), $dayRevision->weekly_off_days ?? []) < 1) {
                        $flags[] = 'Percentage late fine cannot be calculated because the schedule has no workdays.';
                    }
                    $dailyCap = isset($latePolicy['daily_cap']) ? $this->cents($latePolicy['daily_cap']) : null;
                    if ($dailyCap !== null) $fine = min($fine, $dailyCap);
                    $lateFineByRevision[$dayRevision->id] = ($lateFineByRevision[$dayRevision->id] ?? 0) + $fine;
                }
            }
        }

        $attendanceDeductions = $absenceDeduction + $leaveDeduction + $halfDayDeduction + $shortLeaveDeduction;
        $lateFine = 0;
        foreach ($revisions as $fineRevision) {
            $fine = $lateFineByRevision[$fineRevision->id] ?? 0;
            $cap = $fineRevision->late_fine_policy['monthly_cap'] ?? null;
            $lateFine += $cap === null || $cap === '' ? $fine : min($fine, $this->cents($cap));
        }

        $prorationByRevision = [];
        $fullMonthEnd = $start->endOfMonth();
        for ($day = $start; $day->lte($fullMonthEnd); $day = $day->addDay()) {
            if ($profile->joining_date && $day->lt($profile->joining_date)) continue;
            $dayRevision = $revisionAt($day);
            if (!$dayRevision) { $flags[] = 'Employment terms are missing for part of this month.'; continue; }
            $prorationByRevision[$dayRevision->id]['revision'] = $dayRevision;
            $prorationByRevision[$dayRevision->id]['calendar_days'] = ($prorationByRevision[$dayRevision->id]['calendar_days'] ?? 0) + 1;
            $offDays = array_map('intval', $dayRevision->weekly_off_days ?? []);
            if (!in_array($day->dayOfWeekIso - 1, $offDays, true)) {
                $prorationByRevision[$dayRevision->id]['scheduled_days'] = ($prorationByRevision[$dayRevision->id]['scheduled_days'] ?? 0) + 1;
            }
        }
        $proratedBasic = 0;
        $proratedAllowances = 0;
        $proratedOtherAdditions = 0;
        $proratedOtherDeductions = 0;
        $hasUnconfiguredProration = false;
        foreach ($prorationByRevision as $portion) {
            $portionRevision = $portion['revision'];
            $method = $portionRevision->salary_proration_method ?? 'manual_review';
            if ($method === 'calendar_days') {
                $numerator = $portion['calendar_days']; $denominator = $fullMonthEnd->day;
            } elseif ($method === 'scheduled_days') {
                $denominator = 0;
                $offDays = array_map('intval', $portionRevision->weekly_off_days ?? []);
                for ($calendarDay = $start; $calendarDay->lte($fullMonthEnd); $calendarDay = $calendarDay->addDay()) {
                    if (!in_array($calendarDay->dayOfWeekIso - 1, $offDays, true)) $denominator++;
                }
                if ($denominator < 1) {
                    $flags[] = 'Scheduled-day salary proration has no scheduled days in this month.';
                    $hasUnconfiguredProration = true;
                } else {
                    $numerator = $portion['scheduled_days'];
                }
            } else {
                $hasUnconfiguredProration = true;
            }
            if (isset($numerator, $denominator) && $denominator > 0) {
                $proratedBasic += (int) round($this->cents($portionRevision->basic_salary) * $numerator / $denominator);
                $proratedAllowances += (int) round($this->sumNamedAmounts($portionRevision->allowances ?? []) * $numerator / $denominator);
                $proratedOtherAdditions += (int) round($this->sumNamedAmounts(array_filter(
                    $portionRevision->other_adjustments ?? [], fn ($item) => ($item['type'] ?? '') === 'addition'
                )) * $numerator / $denominator);
                $proratedOtherDeductions += (int) round($this->sumNamedAmounts(array_filter(
                    $portionRevision->other_adjustments ?? [], fn ($item) => ($item['type'] ?? '') === 'deduction'
                )) * $numerator / $denominator);
            }
            unset($numerator, $denominator);
        }
        if ($hasUnconfiguredProration) {
            $flags[] = 'Salary proration must be configured for this month.';
            $proratedBasic = $this->cents($revision->basic_salary);
            $proratedAllowances = $this->sumNamedAmounts($revision->allowances ?? []);
            $proratedOtherAdditions = $this->sumNamedAmounts(array_filter(
                $revision->other_adjustments ?? [], fn ($item) => ($item['type'] ?? '') === 'addition'
            ));
            $proratedOtherDeductions = $this->sumNamedAmounts(array_filter(
                $revision->other_adjustments ?? [], fn ($item) => ($item['type'] ?? '') === 'deduction'
            ));
        }
        $basic = $proratedBasic;
        $allowances = $proratedAllowances;
        $otherAdditions = $proratedOtherAdditions;
        $gross = $basic + $allowances + $otherAdditions + $overtimeAddition;
        $otherDeductions = $proratedOtherDeductions;
        $deductions = $attendanceDeductions + $lateFine + $otherDeductions;
        return [
            'employee_profile_id' => (int) $profile->id,
            'source_attendance_ids' => $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'source_revision_ids' => $revisions->filter(fn ($item) => $item->effective_from->toDateString() <= $end->toDateString())->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'employee' => $profile->user?->name ?? $profile->employee_code,
            'code' => $profile->employee_code, 'department' => $profile->department?->name ?? '—',
            'designation' => $profile->designation?->name ?? '—', 'basic' => $basic,
            'allowances' => $allowances, 'other_additions' => $otherAdditions, 'attendance_deductions' => $attendanceDeductions,
            'late_fine' => $lateFine, 'early_departure_deduction' => $earlyDepartureDeduction,
            'overtime_addition' => $overtimeAddition, 'other_deductions' => $otherDeductions,
            'gross' => $gross, 'net' => $gross - $deductions - $earlyDepartureDeduction,
            'counts' => $counts, 'flags' => array_values(array_unique($flags)), 'ready' => !$flags && $revision->salary_status === 'active',
        ];
    }

    private function rangeFine(array $ranges, int $minutes): int
    {
        foreach ($ranges as $range) {
            if ($minutes >= (int) ($range['from_minutes'] ?? PHP_INT_MAX)
                && (!isset($range['to_minutes']) || $range['to_minutes'] === '' || $minutes <= (int) $range['to_minutes'])) {
                return $this->cents($range['amount'] ?? 0);
            }
        }
        return 0;
    }

    private function percentageLateFine(array $policy, $revision, CarbonImmutable $monthStart): int
    {
        if (($policy['base'] ?? null) !== 'daily_basic_salary') return 0;
        $scheduledDays = $this->scheduledDays($monthStart, $monthStart->endOfMonth(), $revision->weekly_off_days ?? []);
        if ($scheduledDays < 1) return 0;
        $dailyBasicCents = $this->cents($revision->basic_salary) / $scheduledDays;
        $percentageCents = $this->cents($policy['amount'] ?? 0);
        return (int) round($dailyBasicCents * $percentageCents / 10000);
    }

    private function scheduledDays(CarbonImmutable $start, CarbonImmutable $end, array $weeklyOffDays): int
    {
        $offDays = array_map('intval', $weeklyOffDays);
        $count = 0;
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if (!in_array($date->dayOfWeekIso - 1, $offDays, true)) $count++;
        }
        return $count;
    }

    private function shiftEnd(string $date, $revision): CarbonImmutable
    {
        $end = CarbonImmutable::parse($date.' '.$revision->duty_end);
        if ($revision->duty_start && $revision->duty_end <= $revision->duty_start) $end = $end->addDay();
        return $end;
    }

    private function sumNamedAmounts(array $items): int
    {
        return array_reduce($items, fn (int $sum, $item) => $sum + $this->cents(is_array($item) ? ($item['amount'] ?? 0) : 0), 0);
    }

    private function sumRows($rows): array
    {
        $sum = ['gross' => 0, 'net' => 0, 'ready' => 0, 'review' => 0];
        foreach ($rows as $row) {
            $sum['gross'] += $row['gross']; $sum['net'] += $row['net'];
            $row['ready'] ? $sum['ready']++ : $sum['review']++;
        }
        return $sum;
    }

    private function emptyRow(EmployeeProfile $profile, array $flags): array
    {
        return ['employee_profile_id' => (int) $profile->id, 'source_attendance_ids' => [], 'source_revision_ids' => [],
            'employee' => $profile->user?->name ?? $profile->employee_code, 'code' => $profile->employee_code,
            'department' => $profile->department?->name ?? '—', 'designation' => $profile->designation?->name ?? '—',
            'basic' => 0, 'allowances' => 0, 'other_additions' => 0, 'attendance_deductions' => 0, 'late_fine' => 0,
            'early_departure_deduction' => 0, 'overtime_addition' => 0,
            'other_deductions' => 0, 'gross' => 0, 'net' => 0, 'counts' => [], 'flags' => $flags, 'ready' => false];
    }

    private function cents(mixed $value): int
    {
        $text = (string) ($value ?? '0');
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $text)) return 0;
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function money(int $cents): string
    {
        $absolute = abs($cents);
        return ($cents < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function safeCsvText(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $text) ? "'".$text : $text;
    }
}
