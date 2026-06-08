<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use App\Models\Labour;
use App\Models\LabourAttendance;
use App\Models\Punch;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileAttendanceController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function staffToday(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $projectId = $this->resolveAttendanceProjectId($request, $user);

        $punchQuery = Punch::query()
            ->where('user_id', $user->id)
            ->whereDate('punch_in', $today);

        if ($projectId !== null) {
            $punchQuery->where('project_id', $projectId);
        } else {
            $punchQuery->whereIn('project_id', $this->allowedProjects($user)->pluck('id')->all());
        }

        $punch = $punchQuery->orderByDesc('punch_in')->first();

        if (!$punch) {
            return $this->successResponse('Today attendance loaded.', [
                'date' => $today,
                'status' => 'not_checked_in',
                'check_in_time' => null,
                'check_out_time' => null,
                'can_check_in' => true,
                'can_check_out' => false,
            ]);
        }

        $hasCheckOut = !empty($punch->punch_out);

        return $this->successResponse('Today attendance loaded.', [
            'date' => $today,
            'status' => $hasCheckOut ? 'checked_out' : 'checked_in',
            'check_in_time' => $this->formatDateTime($punch->punch_in),
            'check_out_time' => $hasCheckOut ? $this->formatDateTime($punch->punch_out) : null,
            'can_check_in' => false,
            'can_check_out' => !$hasCheckOut,
        ]);
    }

    public function staffCheckIn(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'latitude' => ['nullable'],
            'longitude' => ['nullable'],
            'remarks' => ['nullable', 'string'],
        ]);

        $projectId = $this->resolveAttendanceProjectId($request, $user, true);

        $existingPunch = Punch::query()
            ->where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->whereDate('punch_in', now()->toDateString())
            ->first();

        if ($existingPunch && empty($existingPunch->punch_out)) {
            return $this->errorResponse(
                'validation_error',
                'You have already checked in for today.',
                422,
                ['check_in' => ['You have already checked in for today.']]
            );
        }

        if ($existingPunch && !empty($existingPunch->punch_out)) {
            return $this->errorResponse(
                'validation_error',
                'You have already completed attendance for today.',
                422,
                ['check_in' => ['You have already completed attendance for today.']]
            );
        }

        $punch = Punch::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'punch_in' => now(),
            'post_by' => $user->id,
        ]);

        return $this->successResponse('Check-in recorded successfully.', [
            'date' => now()->toDateString(),
            'status' => 'checked_in',
            'check_in_time' => $this->formatDateTime($punch->punch_in),
            'check_out_time' => null,
            'can_check_in' => false,
            'can_check_out' => true,
        ], [
            'selected_project_id' => $projectId,
        ]);
    }

    public function staffCheckOut(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'latitude' => ['nullable'],
            'longitude' => ['nullable'],
            'remarks' => ['nullable', 'string'],
        ]);

        $today = now()->toDateString();

        $punch = Punch::query()
            ->where('user_id', $user->id)
            ->whereDate('punch_in', $today)
            ->whereNull('punch_out')
            ->orderByDesc('punch_in')
            ->first();

        if (!$punch) {
            return $this->errorResponse(
                'validation_error',
                'You must check in before checking out.',
                422,
                ['check_out' => ['You must check in before checking out.']]
            );
        }

        $punch->update([
            'punch_out' => now(),
            'post_by' => $user->id,
        ]);

        return $this->successResponse('Check-out recorded successfully.', [
            'date' => $today,
            'status' => 'checked_out',
            'check_in_time' => $this->formatDateTime($punch->punch_in),
            'check_out_time' => $this->formatDateTime($punch->punch_out),
            'can_check_in' => false,
            'can_check_out' => false,
        ], [
            'selected_project_id' => (int) $punch->project_id,
        ]);
    }

    public function staffHistory(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $projectId = $this->resolveAttendanceProjectId($request, $user);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Punch::query()
            ->where('user_id', $user->id)
            ->orderByDesc('punch_in');

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        } else {
            $query->whereIn('project_id', $this->allowedProjects($user)->pluck('id')->all());
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('punch_in', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('punch_in', '<=', $validated['date_to']);
        }

        $punches = $query->paginate($perPage);

        return $this->successResponse(
            'Attendance history loaded.',
            collect($punches->items())->map(fn (Punch $punch) => [
                'id' => $punch->id,
                'project_id' => (int) $punch->project_id,
                'date' => Carbon::parse($punch->punch_in)->toDateString(),
                'check_in_time' => $this->formatDateTime($punch->punch_in),
                'check_out_time' => $punch->punch_out ? $this->formatDateTime($punch->punch_out) : null,
                'status' => $punch->punch_out ? 'checked_out' : 'checked_in',
            ])->all(),
            [
                'current_page' => $punches->currentPage(),
                'per_page' => $punches->perPage(),
                'last_page' => $punches->lastPage(),
                'total' => $punches->total(),
                'selected_project_id' => $projectId,
            ]
        );
    }

    public function labourIndex(Request $request)
    {
        $this->authorizeLabourRead($request->user());

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $projectId = $this->resolveAttendanceProjectId($request, $user);

        if ($projectId === null) {
            $request->validate(['project_id' => ['required']]);
        }

        if (!empty($validated['site_id'])) {
            $this->ensureSiteBelongsToProject((int) $validated['site_id'], $projectId);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $today = now()->toDateString();

        $query = Labour::query()->where('project_id', $projectId);

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                if (preg_match('/\d/', $search)) {
                    $builder->where('phone', 'like', "%{$search}%");
                } else {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('father_name', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                }
            });
        }

        $labours = $query->orderBy('name')->paginate($perPage);
        $labourIds = collect($labours->items())->pluck('id')->all();

        $todayAttendances = LabourAttendance::query()
            ->where('project_id', $projectId)
            ->whereDate('date', $today)
            ->whereIn('labour_id', $labourIds)
            ->when(!empty($validated['site_id']), fn ($builder) => $builder->where('site_id', (int) $validated['site_id']))
            ->get()
            ->keyBy('labour_id');

        return $this->successResponse(
            'Labours loaded.',
            collect($labours->items())->map(function (Labour $labour) use ($todayAttendances) {
                return $this->transformLabour($labour, $todayAttendances->get($labour->id));
            })->all(),
            [
                'current_page' => $labours->currentPage(),
                'per_page' => $labours->perPage(),
                'last_page' => $labours->lastPage(),
                'total' => $labours->total(),
                'selected_project_id' => $projectId,
            ]
        );
    }

    public function markLabour(Request $request)
    {
        $user = $request->user();
        $this->authorizeLabourWrite($user);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.labour_id' => ['required', 'integer', 'exists:labours,id'],
            'records.*.status' => ['required', 'in:present,absent'],
            'records.*.hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'records.*.ot_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'records.*.rate' => ['nullable', 'numeric', 'min:0'],
            'records.*.remarks' => ['nullable', 'string'],
            'records.*.ratings' => ['nullable'],
        ]);

        $projectId = $this->resolveAttendanceProjectId($request, $user, true);
        $siteId = isset($validated['site_id']) ? (int) $validated['site_id'] : null;

        if ($siteId !== null) {
            $this->ensureSiteBelongsToProject($siteId, $projectId);
        }

        $created = 0;
        $updated = 0;
        $canUpdate = $this->canUpdateLabourAttendance($user);

        foreach ($validated['records'] as $record) {
            $labour = Labour::query()
                ->where('id', $record['labour_id'])
                ->where('project_id', $projectId)
                ->first();

            if (!$labour) {
                return $this->errorResponse('not_found', 'The requested record was not found.', 404);
            }

            $existing = $this->findLabourAttendanceRecord(
                (int) $record['labour_id'],
                $projectId,
                $validated['date'],
                $siteId
            );

            if ($existing && !$canUpdate) {
                return $this->errorResponse(
                    'duplicate_attendance',
                    'Attendance already exists for one or more labours on this date.',
                    422,
                    ['records' => ['Attendance already exists for labour ID ' . $record['labour_id'] . '.']]
                );
            }
        }

        DB::transaction(function () use ($validated, $user, $projectId, $siteId, &$created, &$updated) {
            foreach ($validated['records'] as $record) {
                $labour = Labour::query()
                    ->where('id', $record['labour_id'])
                    ->where('project_id', $projectId)
                    ->first();

                $hours = (float) ($record['hours'] ?? 0);
                $otHours = (float) ($record['ot_hours'] ?? 0);
                $rate = (float) ($record['rate'] ?? $labour->daily_wage ?? 0);
                $amount = $record['status'] === 'present'
                    ? $this->calculateLabourAmount($hours, $otHours, $rate)
                    : '0.00';

                $payload = [
                    'project_id' => $projectId,
                    'labour_id' => (int) $record['labour_id'],
                    'site_id' => $siteId,
                    'date' => $validated['date'],
                    'status' => $record['status'],
                    'hours' => $hours,
                    'ot_hours' => $otHours,
                    'rate' => $rate,
                    'amount' => $amount,
                    'remarks' => $record['remarks'] ?? null,
                    'ratings' => $record['ratings'] ?? 0,
                    'marked_by' => $user->id,
                    'voucher_status' => 'notcreated',
                    'paid_status' => 'unpaid',
                    'is_approved' => false,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    LabourAttendance::create($payload);
                    $created++;
                }
            }
        });

        return $this->successResponse('Labour attendance saved successfully.', [
            'created' => $created,
            'updated' => $updated,
            'date' => $validated['date'],
        ], [
            'selected_project_id' => $projectId,
        ]);
    }

    public function labourReport(Request $request)
    {
        $this->authorizeLabourRead($request->user());

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'labour_id' => ['nullable', 'integer', 'exists:labours,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $projectId = $this->resolveAttendanceProjectId($request, $user);

        if ($projectId === null) {
            $request->validate(['project_id' => ['required']]);
        }

        if (!empty($validated['site_id'])) {
            $this->ensureSiteBelongsToProject((int) $validated['site_id'], $projectId);
        }

        if (!empty($validated['labour_id'])) {
            $labourExists = Labour::query()
                ->where('id', $validated['labour_id'])
                ->where('project_id', $projectId)
                ->exists();

            if (!$labourExists) {
                return $this->errorResponse('not_found', 'The requested record was not found.', 404);
            }
        }

        $perPage = (int) ($validated['per_page'] ?? 20);

        $attendanceQuery = LabourAttendance::query()
            ->with(['labour:id,name,project_id'])
            ->where('project_id', $projectId)
            ->where('status', 'present');

        if (!empty($validated['site_id'])) {
            $attendanceQuery->where('site_id', (int) $validated['site_id']);
        }

        if (!empty($validated['labour_id'])) {
            $attendanceQuery->where('labour_id', (int) $validated['labour_id']);
        }

        if (!empty($validated['date_from'])) {
            $attendanceQuery->whereDate('date', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $attendanceQuery->whereDate('date', '<=', $validated['date_to']);
        }

        $attendanceRecords = $attendanceQuery->get();

        $grouped = $attendanceRecords
            ->groupBy('labour_id')
            ->map(function ($records, $labourId) {
                return $this->transformLabourAttendanceReport($records, (int) $labourId);
            })
            ->values();

        $page = (int) ($validated['page'] ?? 1);
        $total = $grouped->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $items = $grouped->slice($offset, $perPage)->values()->all();

        return $this->successResponse('Labour attendance report loaded.', $items, [
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'total' => $total,
            'selected_project_id' => $projectId,
        ]);
    }

    public function labourPaymentSummary(Request $request)
    {
        $this->authorizeLabourRead($request->user());

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'labour_id' => ['nullable', 'integer', 'exists:labours,id'],
        ]);

        $user = $request->user();
        $projectId = $this->resolveAttendanceProjectId($request, $user);

        if ($projectId === null) {
            $request->validate(['project_id' => ['required']]);
        }

        if (!empty($validated['site_id'])) {
            $this->ensureSiteBelongsToProject((int) $validated['site_id'], $projectId);
        }

        if (!empty($validated['labour_id'])) {
            $labourExists = Labour::query()
                ->where('id', $validated['labour_id'])
                ->where('project_id', $projectId)
                ->exists();

            if (!$labourExists) {
                return $this->errorResponse('not_found', 'The requested record was not found.', 404);
            }
        }

        $attendanceQuery = LabourAttendance::query()
            ->with(['labour:id,name,project_id'])
            ->where('project_id', $projectId)
            ->where('status', 'present')
            ->where('voucher_status', 'notcreated')
            ->whereDate('date', '>=', $validated['date_from'])
            ->whereDate('date', '<=', $validated['date_to']);

        if (!empty($validated['site_id'])) {
            $attendanceQuery->where('site_id', (int) $validated['site_id']);
        }

        if (!empty($validated['labour_id'])) {
            $attendanceQuery->where('labour_id', (int) $validated['labour_id']);
        }

        $attendanceRecords = $attendanceQuery->get();

        $items = $attendanceRecords
            ->groupBy('labour_id')
            ->map(function ($records) {
                $labour = $records->first()->labour;
                $totalHours = $records->sum('hours');
                $totalOtHours = $records->sum('ot_hours');
                $days = $totalHours / 8;

                return [
                    'labour_id' => (int) $records->first()->labour_id,
                    'labour_name' => $labour?->name,
                    'days' => $this->formatDecimal($days),
                    'hours' => $this->formatDecimal($totalHours),
                    'ot_hours' => $this->formatDecimal($totalOtHours),
                    'amount' => $this->formatDecimal($records->sum('amount')),
                ];
            })
            ->values()
            ->all();

        $attendanceIds = $attendanceRecords->pluck('id')->values()->all();

        return $this->successResponse('Labour payment summary loaded.', [
            'total_amount' => $this->formatDecimal($attendanceRecords->sum('amount')),
            'total_labours' => count($items),
            'attendance_ids' => $attendanceIds,
            'items' => $items,
        ], [
            'selected_project_id' => $projectId,
        ]);
    }

    protected function authorizeLabourRead(User $user): void
    {
        if ($user->can('read labour') || $user->can('read attendance')) {
            return;
        }

        // TODO: Add dedicated labour/attendance permissions to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function authorizeLabourWrite(User $user): void
    {
        if ($user->can('create attendance') || $user->can('create labour')) {
            return;
        }

        // TODO: Add dedicated labour/attendance permissions to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function canUpdateLabourAttendance(User $user): bool
    {
        if ($user->can('update attendance') || $user->can('update labour')) {
            return true;
        }

        // TODO: Add dedicated labour/attendance permissions to PermissionSeeder when available.
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    protected function resolveAttendanceProjectId(Request $request, User $user, bool $requiredForWrite = false): ?int
    {
        $projectId = $this->resolveSelectedProjectId($request, $user);

        if ($projectId === null && $requiredForWrite) {
            $request->validate([
                'project_id' => ['required'],
            ]);
        }

        return $projectId;
    }

    protected function ensureSiteBelongsToProject(?int $siteId, int $projectId): void
    {
        if ($siteId === null) {
            return;
        }

        $exists = Site::query()
            ->where('id', $siteId)
            ->where('project_id', $projectId)
            ->exists();

        if (!$exists) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    protected function transformLabour(Labour $labour, ?LabourAttendance $todayAttendance = null): array
    {
        $data = [
            'id' => $labour->id,
            'name' => $labour->name,
            'father_name' => $labour->father_name,
            'phone' => $this->maskPhone($labour->phone),
            'role' => $labour->role,
            'daily_wage' => $this->formatDecimal($labour->daily_wage),
            'today_attendance' => null,
        ];

        if ($todayAttendance) {
            $data['today_attendance'] = [
                'status' => $todayAttendance->status,
                'hours' => $this->formatDecimal($todayAttendance->hours),
                'ot_hours' => $this->formatDecimal($todayAttendance->ot_hours),
            ];
        }

        return $data;
    }

    protected function transformLabourAttendanceReport($records, int $labourId): array
    {
        $labour = $records->first()->labour;
        $totalHours = $records->sum('hours');
        $totalOtHours = $records->sum('ot_hours');

        return [
            'labour_id' => $labourId,
            'labour_name' => $labour?->name,
            'present_days' => $this->formatDecimal($records->count()),
            'total_hours' => $this->formatDecimal($totalHours),
            'total_ot_hours' => $this->formatDecimal($totalOtHours),
            'total_amount' => $this->formatDecimal($records->sum('amount')),
            'attendance_ids' => $records->pluck('id')->values()->all(),
        ];
    }

    protected function calculateLabourAmount($hours, $otHours, $rate): string
    {
        $hours = (float) ($hours ?? 0);
        $otHours = (float) ($otHours ?? 0);
        $rate = (float) ($rate ?? 0);

        if (function_exists('bcdiv') && function_exists('bcmul') && function_exists('bcadd')) {
            $dayPortion = bcmul(bcdiv((string) $hours, '8', 4), (string) $rate, 4);
            $hourlyRate = bcdiv((string) $rate, '8', 4);
            $otPortion = bcmul((string) $otHours, $hourlyRate, 4);
            $amount = bcadd($dayPortion, $otPortion, 2);

            return $amount;
        }

        $amount = (($hours / 8) * $rate) + ($otHours * ($rate / 8));

        return $this->formatDecimal($amount);
    }

    protected function findLabourAttendanceRecord(int $labourId, int $projectId, string $date, ?int $siteId): ?LabourAttendance
    {
        $query = LabourAttendance::query()
            ->where('labour_id', $labourId)
            ->where('project_id', $projectId)
            ->whereDate('date', $date);

        if ($siteId === null) {
            $query->whereNull('site_id');
        } else {
            $query->where('site_id', $siteId);
        }

        return $query->first();
    }

    protected function formatDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    protected function formatDecimal($value, int $decimals = 2): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', '');
    }

    protected function maskPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phone = trim($phone);

        if (strlen($phone) <= 4) {
            return $phone;
        }

        return substr($phone, 0, 2)
            . str_repeat('*', max(0, strlen($phone) - 4))
            . substr($phone, -2);
    }
}
