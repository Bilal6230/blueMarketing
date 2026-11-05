<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\Punch;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class EmployeeAttendanceSnapshotController extends Controller
{
    private const REVIEW_STATUSES = ['present', 'absent', 'leave', 'half_day', 'short_leave', 'holiday', 'weekly_off'];

    private function projectId(): int
    {
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!auth()->user()->hasRole('superadmin')) {
            abort_unless((int) auth()->user()->project_id === $projectId, 403);
        }
        return $projectId;
    }

    private function requireSchema(): void
    {
        abort_unless(Schema::hasTable('employee_attendance_records') && Schema::hasTable('employee_attendance_audits'),
            409, 'Employee attendance snapshot migration is required.');
    }

    public function index(Request $request)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $data['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
        abort_if($start->startOfMonth()->gt(now()->startOfMonth()), 422, 'Future attendance periods are not available.');
        $end = $start->endOfMonth()->min(now()->endOfDay());
        $query = EmployeeAttendanceRecord::with(['employee.department', 'employee.designation', 'user:id,name'])
            ->where('project_id', $projectId)->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $records = $query->orderBy('work_date')->orderBy('employee_profile_id')->paginate(50)->withQueryString();
        $hasProfiles = EmployeeProfile::where('project_id', $projectId)->exists();

        return view('admin.employees.attendance', [
            'title' => 'Employee Attendance Review', 'month' => $month, 'records' => $records,
            'counts' => $counts, 'projectId' => $projectId, 'canSnapshot' => auth()->user()->can('create attendance'),
            'canReview' => auth()->user()->can('update attendance'), 'unreviewed' => (int) ($counts['unreviewed'] ?? 0),
            'hasProfiles' => $hasProfiles,
        ]);
    }

    public function prepare(Request $request)
    {
        abort_unless(auth()->user()->can('create attendance'), 403);
        $this->requireSchema();
        $projectId = $this->projectId();
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['month'].'-01');
        abort_if($start->startOfMonth()->gt(now()->startOfMonth()), 422, 'Future attendance periods are not available.');
        $end = $start->endOfMonth()->min(now()->endOfDay());
        $profiles = EmployeeProfile::with(['revisions', 'user'])
            ->where('project_id', $projectId)->orderBy('id')->get();
        if ($profiles->isEmpty()) {
            return redirect()->route('employee-attendance.index', ['month' => $data['month']])
                ->with('warning', 'Create employee profiles in Employee Setup before preparing attendance.');
        }

        $punchesByUserDay = Punch::query()->where('project_id', $projectId)
            ->whereBetween('punch_in', [$start->startOfDay()->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('punch_in')->orderBy('id')->get(['id', 'user_id', 'punch_in', 'punch_out'])
            ->groupBy(fn ($punch) => (int) $punch->user_id.'|'.CarbonImmutable::parse($punch->punch_in)->toDateString());

        $batchId = (string) Str::uuid();
        $created = DB::transaction(function () use ($projectId, $profiles, $punchesByUserDay, $start, $end, $batchId): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $profileIds = $profiles->modelKeys();
            $existing = EmployeeAttendanceRecord::where('project_id', $projectId)->whereIn('employee_profile_id', $profileIds)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->get(['employee_profile_id', 'work_date'])
                ->mapWithKeys(fn ($row) => [(int) $row->employee_profile_id.'|'.(string) $row->work_date => true])->all();

            $snapshotAt = now()->startOfSecond();
            $rows = [];
            foreach ($profiles as $profile) {
                $joinDate = $profile->joining_date?->toDateString();
                for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                    $workDate = $day->toDateString();
                    if ($joinDate && $workDate < $joinDate) continue;
                    $revision = $profile->revisions->first(fn ($item) => $item->effective_from->toDateString() <= $workDate);
                    if (!$revision) continue;
                    $key = (int) $profile->id.'|'.$workDate;
                    if (isset($existing[$key])) continue;

                    $events = $punchesByUserDay->get((int) $profile->user_id.'|'.$workDate, collect());
                    $offDays = $revision->weekly_off_days ?? [];
                    $isWeeklyOff = in_array($day->dayOfWeekIso - 1, array_map('intval', $offDays), true);
                    $status = $events->isNotEmpty() ? 'present' : ($isWeeklyOff ? 'weekly_off' : 'unreviewed');
                    $timestamps = $events->pluck('punch_in')->filter()->map(fn ($value) => CarbonImmutable::parse($value));
                    $punchIn = $timestamps->isNotEmpty() ? $timestamps->min()->toDateTimeString() : null;
                    $punchOuts = $events->pluck('punch_out')->filter()->map(fn ($value) => CarbonImmutable::parse($value));
                    $punchOut = $punchOuts->isNotEmpty() ? $punchOuts->max()->toDateTimeString() : null;
                    $rows[] = [
                        'project_id' => $projectId, 'employee_profile_id' => $profile->id, 'user_id' => $profile->user_id,
                        'work_date' => $workDate, 'snapshot_batch_id' => $batchId, 'status' => $status, 'punch_in' => $punchIn, 'punch_out' => $punchOut,
                        'source_punch_ids' => json_encode($events->pluck('id')->map(fn ($id) => (int) $id)->values(), JSON_THROW_ON_ERROR),
                        'source_snapshot' => json_encode([
                            'kind' => 'punches_monthly_snapshot', 'employment_revision_id' => $revision->id,
                            'captured_at' => $snapshotAt->toIso8601String(),
                        ], JSON_THROW_ON_ERROR),
                        'snapshot_by' => auth()->id(), 'created_at' => $snapshotAt, 'updated_at' => $snapshotAt,
                    ];
                    $existing[$key] = true;
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('employee_attendance_records')->insert($chunk);
            }
            if (!$rows) return 0;

            $newRecords = EmployeeAttendanceRecord::where('project_id', $projectId)->whereIn('employee_profile_id', $profileIds)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->where('snapshot_batch_id', $batchId)->get(['id', 'project_id', 'status', 'work_date', 'employee_profile_id', 'source_punch_ids']);
            $audits = $newRecords->map(fn ($record) => [
                'project_id' => $projectId, 'employee_attendance_record_id' => $record->id, 'action' => 'snapshot_created',
                'new_values' => json_encode($record->only(['employee_profile_id', 'work_date', 'status', 'source_punch_ids']), JSON_THROW_ON_ERROR),
                'reason' => 'Monthly punch source snapshot', 'actor_id' => auth()->id(), 'created_at' => $snapshotAt,
            ])->all();
            foreach (array_chunk($audits, 500) as $chunk) DB::table('employee_attendance_audits')->insert($chunk);

            return count($rows);
        }, 3);

        return redirect()->route('employee-attendance.index', ['month' => $data['month']])
            ->with('success', $created.' attendance day(s) added. Existing snapshots were preserved; missing workdays need review.');
    }

    public function review(Request $request, int $recordId)
    {
        abort_unless(auth()->user()->can('update attendance'), 403);
        $this->requireSchema();
        $projectId = $this->projectId();
        $data = $request->validate([
            'status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($projectId, $recordId, $data): void {
            $record = EmployeeAttendanceRecord::where('project_id', $projectId)->lockForUpdate()->findOrFail($recordId);
            $old = ['status' => $record->status, 'reviewed_by' => $record->reviewed_by, 'reviewed_at' => $record->reviewed_at?->toIso8601String()];
            $record->status = $data['status'];
            $record->reviewed_by = auth()->id();
            $record->reviewed_at = now();
            $record->review_reason = $data['reason'];
            $record->save();
            DB::table('employee_attendance_audits')->insert([
                'project_id' => $projectId, 'employee_attendance_record_id' => $record->id, 'action' => 'status_reviewed',
                'old_values' => json_encode($old, JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['status' => $record->status, 'reviewed_by' => $record->reviewed_by, 'reviewed_at' => $record->reviewed_at->toIso8601String()], JSON_THROW_ON_ERROR),
                'reason' => $data['reason'], 'actor_id' => auth()->id(), 'created_at' => now(),
            ]);
        }, 3);

        return back()->with('success', 'Attendance status reviewed and audit history recorded.');
    }
}
