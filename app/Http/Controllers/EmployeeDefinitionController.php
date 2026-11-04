<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDefinition;
use App\Models\EmployeeEmploymentRevision;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EmployeeDefinitionController extends Controller
{
    private const KINDS = ['department', 'designation', 'category'];

    private function projectId(): int
    {
        $id = (int) getSelectedTown();
        abort_if($id < 1, 409, 'Select a project before managing employee definitions.');
        return $id;
    }

    private function requireSchema(): void
    {
        abort_unless(Schema::hasTable('employee_definitions')
            && Schema::hasTable('employee_profiles')
            && Schema::hasTable('employee_employment_revisions')
            && Schema::hasTable('employee_definition_audits')
            && Schema::hasTable('employee_profile_audits')
            && Schema::hasColumn('employee_employment_revisions', 'salary_proration_method')
            && Schema::hasColumn('employee_employment_revisions', 'half_day_deduction')
            && Schema::hasColumn('employee_employment_revisions', 'short_leave_method')
            && Schema::hasColumn('employee_employment_revisions', 'short_leave_deduction'),
            409, 'Employee setup and payroll policy migrations are required before using this page.');
    }

    public function index()
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $definitions = EmployeeDefinition::where('project_id', $projectId)->orderBy('kind')->orderBy('name')->get()->groupBy('kind');
        $profiles = EmployeeProfile::with(['user', 'department', 'designation', 'category', 'revisions'])
            ->where('project_id', $projectId)->orderBy('employee_code')->get();
        $users = User::where('project_id', $projectId)->orderBy('name')->get(['id', 'name', 'email']);
        $definitionAudits = DB::table('employee_definition_audits as audit')
            ->join('employee_definitions as definition', function ($join): void {
                $join->on('definition.id', '=', 'audit.employee_definition_id')
                    ->on('definition.project_id', '=', 'audit.project_id');
            })
            ->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
            ->where('audit.project_id', $projectId)
            ->orderByDesc('audit.created_at')->orderByDesc('audit.id')->limit(200)
            ->get([
                'audit.id', 'audit.action', 'audit.old_values', 'audit.new_values', 'audit.reason', 'audit.created_at',
                'definition.kind', 'definition.code', 'definition.name as definition_name', 'actor.name as actor_name',
            ]);
        $profileAudits = DB::table('employee_profile_audits as audit')
            ->join('employee_profiles as profile', function ($join): void {
                $join->on('profile.id', '=', 'audit.employee_profile_id')
                    ->on('profile.project_id', '=', 'audit.project_id');
            })
            ->leftJoin('users as employee', 'employee.id', '=', 'profile.user_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
            ->where('audit.project_id', $projectId)
            ->orderByDesc('audit.created_at')->orderByDesc('audit.id')->limit(200)
            ->get([
                'audit.id', 'audit.old_values', 'audit.new_values', 'audit.reason', 'audit.created_at',
                'profile.employee_code', 'employee.name as employee_name', 'actor.name as actor_name',
            ]);

        return view('admin.employees.definitions', compact('definitions', 'profiles', 'users', 'definitionAudits', 'profileAudits'))
            ->with('title', 'Employee Setup');
    }

    public function storeDefinition(Request $request)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'code' => ['required', 'string', 'max:40', Rule::unique('employee_definitions')->where(fn ($q) => $q->where('project_id', $projectId)->where('kind', $request->input('kind')))],
            'name' => ['required', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($projectId, $data, $request): void {
            abort_unless(DB::table('projects')->where('id', $projectId)->lockForUpdate()->first(), 404,
                'Selected project was not found.');
            abort_if(EmployeeDefinition::where('project_id', $projectId)->where('kind', $data['kind'])
                ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($data['code']))])->exists(),
                422, 'A definition with this code already exists in this project.');

            $definition = EmployeeDefinition::create([
                'project_id' => $projectId, 'kind' => $data['kind'], 'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']), 'is_active' => true, 'created_by' => $request->user()->id,
            ]);
            DB::table('employee_definition_audits')->insert([
                'project_id' => $projectId, 'employee_definition_id' => $definition->id, 'action' => 'created',
                'new_values' => json_encode($definition->only(['kind', 'code', 'name', 'is_active']), JSON_THROW_ON_ERROR),
                'actor_id' => $request->user()->id, 'created_at' => now(),
            ]);
        });

        return back()->with('success', ucfirst($data['kind']).' created.');
    }

    public function updateDefinition(Request $request, int $definitionId)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($projectId, $definitionId, $data, $request): void {
            $definition = EmployeeDefinition::where('project_id', $projectId)->lockForUpdate()->findOrFail($definitionId);
            $old = $definition->only(['name', 'is_active']);
            $definition->name = trim($data['name']);
            $definition->is_active = (bool) $data['is_active'];
            $definition->updated_by = $request->user()->id;
            $definition->save();
            DB::table('employee_definition_audits')->insert([
                'project_id' => $projectId, 'employee_definition_id' => $definition->id, 'action' => 'updated',
                'old_values' => json_encode($old, JSON_THROW_ON_ERROR),
                'new_values' => json_encode($definition->only(['name', 'is_active']), JSON_THROW_ON_ERROR),
                'reason' => $data['reason'], 'actor_id' => $request->user()->id, 'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Employee definition updated.');
    }

    public function saveProfile(Request $request)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $existingProfile = null;
        if (filter_var($request->input('user_id'), FILTER_VALIDATE_INT)) {
            $existingProfile = EmployeeProfile::where('project_id', $projectId)
                ->where('user_id', (int) $request->input('user_id'))->first();
        }
        $request->merge(['employee_code' => strtoupper(trim((string) $request->input('employee_code')))]);
        $request->merge([
            'salary_proration_method' => $request->input('salary_proration_method') ?: 'manual_review',
        ]);
        $request->merge([
            'allowances' => collect($request->input('allowances', []))
                ->filter(fn ($row) => is_array($row) && (filled($row['name'] ?? null) || filled($row['amount'] ?? null)))->values()->all(),
            'other_adjustments' => collect($request->input('other_adjustments', []))
                ->filter(fn ($row) => is_array($row) && (filled($row['name'] ?? null) || filled($row['amount'] ?? null)))->values()->all(),
        ]);
        if (is_array($request->input('late_fine_policy.ranges'))) {
            $policy = $request->input('late_fine_policy');
            $policy['ranges'] = collect($policy['ranges'])
                ->filter(fn ($row) => is_array($row) && (filled($row['from_minutes'] ?? null) || filled($row['to_minutes'] ?? null) || filled($row['amount'] ?? null)))->values()->all();
            $request->merge(['late_fine_policy' => $policy]);
        }
        $employeeCodeRule = Rule::unique('employee_profiles')->where('project_id', $projectId);
        if ($existingProfile) $employeeCodeRule->ignore($existingProfile->id);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('project_id', $projectId)],
            'employee_code' => ['required', 'string', 'max:40', $employeeCodeRule],
            'joining_date' => ['required', 'date'],
            'department_definition_id' => ['required', 'integer', Rule::exists('employee_definitions', 'id')->where(fn ($q) => $q->where('project_id', $projectId)->where('kind', 'department')->where('is_active', true))],
            'designation_definition_id' => ['required', 'integer', Rule::exists('employee_definitions', 'id')->where(fn ($q) => $q->where('project_id', $projectId)->where('kind', 'designation')->where('is_active', true))],
            'employee_category_definition_id' => ['nullable', 'integer', Rule::exists('employee_definitions', 'id')->where(fn ($q) => $q->where('project_id', $projectId)->where('kind', 'category')->where('is_active', true))],
            'effective_from' => ['required', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.name' => ['required_with:allowances', 'string', 'max:80'],
            'allowances.*.amount' => ['required_with:allowances', 'numeric', 'min:0', 'max:9999999999.99'],
            'payment_method' => ['required', 'string', 'max:40'],
            'salary_status' => ['required', Rule::in(['active', 'hold'])],
            'salary_proration_method' => ['required', Rule::in(['manual_review', 'calendar_days', 'scheduled_days'])],
            'duty_start' => ['nullable', 'date_format:H:i'], 'duty_end' => ['nullable', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'daily_hours' => ['nullable', 'numeric', 'gt:0', 'max:24'],
            'working_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'weekly_off_days' => ['nullable', 'array'], 'weekly_off_days.*' => ['integer', 'distinct', 'between:0,6'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'late_fine_policy' => ['nullable', 'array'],
            'late_fine_policy.method' => ['nullable', Rule::in(['none', 'fixed', 'range', 'percentage'])],
            'late_fine_policy.base' => ['nullable', Rule::in(['daily_basic_salary'])],
            'late_fine_policy.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'late_fine_policy.daily_cap' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'late_fine_policy.monthly_cap' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'late_fine_policy.ranges' => ['nullable', 'array', 'max:10'],
            'late_fine_policy.ranges.*.from_minutes' => ['required_with:late_fine_policy.ranges', 'integer', 'min:0', 'max:1440'],
            'late_fine_policy.ranges.*.to_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'late_fine_policy.ranges.*.amount' => ['required_with:late_fine_policy.ranges', 'numeric', 'min:0', 'max:9999999999.99'],
            'absence_deduction' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'leave_deduction' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'half_day_after_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'half_day_deduction' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'short_leave_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'short_leave_method' => ['nullable', Rule::in(['none', 'fixed'])],
            'short_leave_deduction' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'early_departure_method' => ['nullable', Rule::in(['fixed', 'per_minute'])],
            'early_departure_deduction' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'overtime_method' => ['nullable', Rule::in(['fixed', 'hourly', 'multiplier'])],
            'overtime_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'other_adjustments' => ['nullable', 'array', 'max:20'],
            'other_adjustments.*.name' => ['required_with:other_adjustments', 'string', 'max:80'],
            'other_adjustments.*.type' => ['required_with:other_adjustments', Rule::in(['addition', 'deduction'])],
            'other_adjustments.*.amount' => ['required_with:other_adjustments', 'numeric', 'min:0', 'max:9999999999.99'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (($data['late_fine_policy']['method'] ?? 'none') === 'percentage'
            && (float) ($data['late_fine_policy']['amount'] ?? 0) > 100) {
            throw ValidationException::withMessages(['late_fine_policy.amount' => 'Percentage late fine must be between 0 and 100.']);
        }
        if (($data['late_fine_policy']['method'] ?? 'none') === 'range'
            && empty($data['late_fine_policy']['ranges'])) {
            throw ValidationException::withMessages(['late_fine_policy.ranges' => 'Add at least one late-fine range or choose another fine method.']);
        }
        $ranges = collect($data['late_fine_policy']['ranges'] ?? [])->sortBy('from_minutes')->values();
        $previousEnd = -1;
        foreach ($ranges as $index => $range) {
            $from = (int) $range['from_minutes'];
            $to = isset($range['to_minutes']) && $range['to_minutes'] !== '' ? (int) $range['to_minutes'] : null;
            if ($from <= $previousEnd || ($to !== null && $to < $from) || ($to === null && $index !== $ranges->count() - 1)) {
                throw ValidationException::withMessages(['late_fine_policy.ranges' => 'Fine ranges must be ordered, non-overlapping, and any open-ended range must be last.']);
            }
            $previousEnd = $to ?? 1440;
        }

        $offDays = array_unique(array_map('intval', $data['weekly_off_days'] ?? []));
        if (isset($data['working_days_per_week']) && count($offDays) > 7 - (int) $data['working_days_per_week']) {
            throw ValidationException::withMessages(['weekly_off_days' => 'Weekly off days cannot exceed the non-working days in the selected schedule.']);
        }
        if (!empty($data['duty_start']) && !empty($data['duty_end']) && $data['duty_start'] === $data['duty_end']) {
            throw ValidationException::withMessages(['duty_end' => 'Duty end must differ from duty start.']);
        }
        if ($data['effective_from'] < $data['joining_date']) {
            throw ValidationException::withMessages(['effective_from' => 'Employment terms cannot start before the employee joining date.']);
        }

        $profile = DB::transaction(function () use ($data, $projectId, $request): EmployeeProfile {
            User::whereKey($data['user_id'])->where('project_id', $projectId)->lockForUpdate()->firstOrFail();
            // Recheck and lock each referenced definition at write time. The
            // request validation alone leaves a race with concurrent archive.
            foreach ([
                'department_definition_id' => 'department',
                'designation_definition_id' => 'designation',
                'employee_category_definition_id' => 'category',
            ] as $field => $kind) {
                if (empty($data[$field])) {
                    continue;
                }

                EmployeeDefinition::where('project_id', $projectId)
                    ->where('kind', $kind)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($data[$field]);
            }

            $profile = EmployeeProfile::where('project_id', $projectId)->where('user_id', $data['user_id'])
                ->lockForUpdate()->first();
            if ($profile) {
                $oldProfile = $profile->only([
                    'employee_code', 'joining_date', 'department_definition_id', 'designation_definition_id', 'employee_category_definition_id',
                ]);
                $profile->update(collect($data)->only([
                    'employee_code', 'joining_date', 'department_definition_id', 'designation_definition_id', 'employee_category_definition_id',
                ])->all());
                DB::table('employee_profile_audits')->insert([
                    'project_id' => $projectId, 'employee_profile_id' => $profile->id,
                    'old_values' => json_encode($oldProfile, JSON_THROW_ON_ERROR),
                    'new_values' => json_encode($profile->only(array_keys($oldProfile)), JSON_THROW_ON_ERROR),
                    'reason' => $data['reason'], 'actor_id' => $request->user()->id, 'created_at' => now(),
                ]);
            } else {
                $profile = EmployeeProfile::create(collect($data)->only([
                    'user_id', 'employee_code', 'joining_date', 'department_definition_id', 'designation_definition_id', 'employee_category_definition_id',
                ])->all() + ['project_id' => $projectId]);
                DB::table('employee_profile_audits')->insert([
                    'project_id' => $projectId, 'employee_profile_id' => $profile->id,
                    'new_values' => json_encode($profile->only([
                        'employee_code', 'joining_date', 'department_definition_id', 'designation_definition_id', 'employee_category_definition_id',
                    ]), JSON_THROW_ON_ERROR),
                    'reason' => $data['reason'], 'actor_id' => $request->user()->id, 'created_at' => now(),
                ]);
            }

            if (EmployeeEmploymentRevision::where('employee_profile_id', $profile->id)->whereDate('effective_from', $data['effective_from'])->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'An employment revision already exists for this effective date.']);
            }
            $revisionFields = [
                'effective_from', 'basic_salary', 'allowances', 'payment_method', 'salary_status', 'salary_proration_method', 'duty_start', 'duty_end',
                'break_minutes', 'daily_hours', 'working_days_per_week', 'weekly_off_days', 'grace_minutes', 'late_fine_policy',
                'absence_deduction', 'half_day_after_minutes', 'half_day_deduction', 'short_leave_minutes', 'short_leave_method', 'short_leave_deduction', 'early_departure_deduction',
                'leave_deduction', 'early_departure_method', 'overtime_method', 'overtime_rate', 'other_adjustments', 'reason',
            ];
            EmployeeEmploymentRevision::create(collect($data)->only($revisionFields)->all() + [
                'project_id' => $projectId, 'employee_profile_id' => $profile->id, 'changed_by' => $request->user()->id,
            ]);

            return $profile;
        });

        return back()->with('success', 'Employee profile and effective-dated employment settings saved for '.$profile->employee_code.'.');
    }
}
