<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use App\Models\Lead;
use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class MobileCrmController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function summary(Request $request)
    {
        $this->authorizePermission($request->user(), 'read lead');

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user = $request->user();
        $selectedProjectId = $this->resolveSelectedProjectId($request, $user);
        $query = $this->scopedLeadsQuery($user, $selectedProjectId);
        $query = $this->applyCreatedAtDateRange($query, $validated['date_from'] ?? null, $validated['date_to'] ?? null);
        $today = now()->toDateString();

        $data = [
            'total_leads' => (clone $query)->count(),
            'active_leads' => (clone $query)->where('is_active', 1)->count(),
            'today_followups' => (clone $query)->whereDate('follow_up', $today)->count(),
            'overdue_followups' => (clone $query)->whereDate('follow_up', '<', $today)->count(),
            'completed_or_future_followups' => (clone $query)->whereDate('follow_up', '>', $today)->count(),
        ];

        return $this->successResponse('CRM summary loaded.', $data, [
            'selected_project_id' => $selectedProjectId,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request->user(), 'read lead');

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable'],
            'follow_up_from' => ['nullable', 'date'],
            'follow_up_to' => ['nullable', 'date', 'after_or_equal:follow_up_from'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', Rule::in(['follow_up', 'created_at', 'first_name', 'last_name'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $user = $request->user();
        $selectedProjectId = $this->resolveSelectedProjectId($request, $user);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $sortBy = $validated['sort_by'] ?? 'follow_up';
        $sortOrder = $validated['sort_order'] ?? 'asc';

        $query = $this->scopedLeadsQuery($user, $selectedProjectId)
            ->with([
                'project:id,project',
                'users:id,name',
            ]);

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('nic_number', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null && $validated['status'] !== '') {
            $status = $validated['status'];

            if (is_numeric($status)) {
                $query->where('is_active', (int) $status);
            } elseif ($status === 'active') {
                $query->where('is_active', 1);
            } elseif ($status === 'inactive') {
                $query->where('is_active', 0);
            }
        }

        if (!empty($validated['follow_up_from'])) {
            $query->whereDate('follow_up', '>=', $validated['follow_up_from']);
        }

        if (!empty($validated['follow_up_to'])) {
            $query->whereDate('follow_up', '<=', $validated['follow_up_to']);
        }

        if (!empty($validated['assigned_user_id'])) {
            $this->ensurePrivilegedLeadReadFilter($user);
            $query->whereHas('users', function ($builder) use ($validated) {
                $builder->where('users.id', $validated['assigned_user_id']);
            });
        }

        $leads = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);

        return $this->successResponse(
            'Leads loaded.',
            collect($leads->items())->map(fn (Lead $lead) => $this->transformLeadListItem($lead))->all(),
            [
                'current_page' => $leads->currentPage(),
                'per_page' => $leads->perPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
                'selected_project_id' => $selectedProjectId,
            ]
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorizePermission($user, 'create lead');

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'first_name' => ['required', 'string', 'max:25'],
            'last_name' => ['required', 'string', 'max:25'],
            'phone_number' => ['required', 'string', 'max:25', 'unique:leads,phone_number'],
            'mobile_number' => ['nullable', 'digits:11', 'unique:leads,mobile_number'],
            'nic_number' => ['nullable', 'string', 'max:30'],
            'follow_up' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'gender' => ['nullable'],
            'area_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'integer'],
            'business' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'zone_id' => ['nullable', 'integer'],
            'home_address' => ['nullable', 'string'],
            'office_address' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $projectId = $this->resolveRequiredProjectId($request, $user);
        $assignedUserId = null;

        if (!empty($validated['assigned_user_id'])) {
            $this->authorizeLeadAssignment($user);
            $assignedUserId = $this->resolveAssignableUserId((int) $validated['assigned_user_id'], $projectId);
        }

        $lead = DB::transaction(function () use ($validated, $user, $projectId, $assignedUserId) {
            $lead = Lead::create([
                'project_id' => $projectId,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone_number' => $validated['phone_number'],
                'mobile_number' => $validated['mobile_number'] ?? null,
                'nic_number' => $validated['nic_number'] ?? null,
                'follow_up' => !empty($validated['follow_up']) ? Carbon::parse($validated['follow_up']) : now(),
                'follow_status' => 6,
                'gender' => $validated['gender'] ?? null,
                'area_id' => $validated['area_id'] ?? null,
                'type' => $validated['type'] ?? null,
                'business' => $validated['business'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'zone_id' => $validated['zone_id'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'office_address' => $validated['office_address'] ?? null,
                'is_active' => 1,
                'create_by' => $user->id,
            ]);

            $lead->users()->sync([$assignedUserId ?: $user->id]);

            if (!empty($validated['remarks'])) {
                Work::create([
                    'lead_id' => $lead->id,
                    'comment' => $validated['remarks'],
                    'user_id' => $user->id,
                    'follow_up' => $lead->follow_up,
                    'type' => 1,
                ]);
            }

            return $lead;
        });

        return $this->successResponse('Lead created successfully.', [
            'id' => $lead->id,
        ]);
    }

    public function show(Request $request, Lead $lead)
    {
        $this->authorizePermission($request->user(), 'read lead');

        $lead = $this->resolveAccessibleLead($request->user(), $lead->id);

        return $this->successResponse('Lead detail loaded.', $this->transformLeadDetail($lead));
    }

    public function update(Request $request, Lead $lead)
    {
        $user = $request->user();
        $this->authorizePermission($user, 'update lead');

        $lead = $this->resolveAccessibleLead($user, $lead->id);

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:25'],
            'last_name' => ['sometimes', 'string', 'max:25'],
            'phone_number' => ['sometimes', 'string', 'max:25', Rule::unique('leads', 'phone_number')->ignore($lead->id)],
            'mobile_number' => ['nullable', 'digits:11', Rule::unique('leads', 'mobile_number')->ignore($lead->id)],
            'nic_number' => ['nullable', 'string', 'max:30'],
            'follow_up' => ['nullable', 'date'],
            'gender' => ['nullable'],
            'area_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'integer'],
            'business' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'zone_id' => ['nullable', 'integer'],
            'home_address' => ['nullable', 'string'],
            'office_address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string'],
        ]);

        $changes = [];
        foreach ($validated as $field => $value) {
            if ($field === 'remarks') {
                continue;
            }

            $changes[$field] = $value;
        }

        DB::transaction(function () use ($lead, $changes, $validated, $user) {
            if (!empty($changes)) {
                $lead->fill($changes);
                $lead->save();
            }

            if (!empty($validated['remarks'])) {
                Work::create([
                    'lead_id' => $lead->id,
                    'comment' => $validated['remarks'],
                    'user_id' => $user->id,
                    'follow_up' => $lead->follow_up,
                    'type' => 1,
                ]);
            }
        });

        return $this->successResponse('Lead updated successfully.', [
            'id' => $lead->id,
        ]);
    }

    public function followUp(Request $request, Lead $lead)
    {
        $user = $request->user();
        $this->authorizePermission($user, 'update lead');

        $lead = $this->resolveAccessibleLead($user, $lead->id);

        $validated = $request->validate([
            'follow_up_date' => ['required', 'date'],
            'status' => ['nullable'],
            'remarks' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($lead, $validated, $user) {
            $lead->follow_up = Carbon::parse($validated['follow_up_date']);

            if (isset($validated['status']) && is_numeric($validated['status'])) {
                $lead->follow_status = (int) $validated['status'];
            }

            $lead->save();

            Work::create([
                'lead_id' => $lead->id,
                'comment' => $this->buildFollowUpComment($validated['remarks'] ?? null, $validated['status'] ?? null),
                'call_status' => is_numeric($validated['status'] ?? null) ? (int) $validated['status'] : null,
                'user_id' => $user->id,
                'follow_up' => $lead->follow_up,
                'type' => 1,
            ]);
        });

        return $this->successResponse('Follow-up updated successfully.', [
            'id' => $lead->id,
            'follow_up' => $this->formatDateTime($lead->follow_up),
        ]);
    }

    public function history(Request $request, Lead $lead)
    {
        $this->authorizePermission($request->user(), 'read lead');

        $lead = $this->resolveAccessibleLead($request->user(), $lead->id);

        return $this->successResponse(
            'Lead history loaded.',
            $lead->comments->map(fn (Work $work) => $this->transformHistoryItem($work))->values()->all()
        );
    }

    public function assign(Request $request, Lead $lead)
    {
        $user = $request->user();
        $this->authorizeLeadAssignment($user);

        $lead = $this->resolveAccessibleLead($user, $lead->id);

        $validated = $request->validate([
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignedUserId = $this->resolveAssignableUserId((int) $validated['assigned_user_id'], (int) $lead->project_id);

        $lead->users()->sync([$assignedUserId]);
        $lead->load(['users:id,name', 'project:id,project']);

        return $this->successResponse('Lead assigned successfully.', [
            'id' => $lead->id,
            'assigned_user' => $this->transformAssignedUser($lead),
        ]);
    }

    protected function authorizePermission(User $user, string $permission): void
    {
        if (!$user->can($permission)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    protected function authorizeLeadAssignment(User $user): void
    {
        if (Permission::where('name', 'assign lead')->exists()) {
            $this->authorizePermission($user, 'assign lead');

            return;
        }

        // TODO: Replace this fallback with a dedicated assign permission if/when it is added.
        if (!$user->can('update lead') || !$user->hasAnyRole(['admin', 'superadmin'])) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    protected function ensurePrivilegedLeadReadFilter(User $user): void
    {
        if (!$user->hasAnyRole(['admin', 'superadmin'])) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    protected function resolveRequiredProjectId(Request $request, User $user): int
    {
        $projectId = $this->resolveSelectedProjectId($request, $user);

        if ($projectId === null) {
            $request->validate([
                'project_id' => ['required'],
            ]);
        }

        return (int) $projectId;
    }

    protected function scopedLeadsQuery(User $user, ?int $selectedProjectId = null)
    {
        $query = Lead::query();

        if ($selectedProjectId !== null) {
            $query->where('project_id', $selectedProjectId);
        } else {
            $query->whereIn('project_id', $this->allowedProjects($user)->pluck('id')->all());
        }

        if (!$user->hasAnyRole(['admin', 'superadmin'])) {
            $query->whereHas('users', function ($builder) use ($user) {
                $builder->where('users.id', $user->id);
            });
        }

        return $query;
    }

    protected function resolveAccessibleLead(User $user, int $leadId): Lead
    {
        return $this->scopedLeadsQuery($user)
            ->with([
                'project:id,project',
                'users:id,name',
                'comments' => function ($builder) {
                    $builder->latest()->with('user:id,name');
                },
            ])
            ->findOrFail($leadId);
    }

    protected function resolveAssignableUserId(int $userId, int $projectId): int
    {
        $assignedUser = User::find($userId);

        if (!$assignedUser) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }

        if ((int) ($assignedUser->status_id ?? 1) === 0) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }

        if ($assignedUser->hasRole('superadmin')) {
            return $assignedUser->id;
        }

        if ((int) $assignedUser->project_id !== $projectId) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }

        return $assignedUser->id;
    }

    protected function applyCreatedAtDateRange($query, ?string $dateFrom, ?string $dateTo)
    {
        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    protected function transformLeadListItem(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'project' => [
                'id' => $lead->project_id,
                'name' => $lead->project->project ?? null,
            ],
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'phone_number' => $lead->phone_number,
            'mobile_number' => $lead->mobile_number,
            'follow_up' => $this->formatDateTime($lead->follow_up),
            'status' => (int) $lead->is_active === 1 ? 'active' : 'inactive',
            'assigned_user' => $this->transformAssignedUser($lead),
            'created_at' => optional($lead->created_at)->toDateTimeString(),
        ];
    }

    protected function transformLeadDetail(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'project' => [
                'id' => $lead->project_id,
                'name' => $lead->project->project ?? null,
            ],
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'phone_number' => $lead->phone_number,
            'mobile_number' => $lead->mobile_number,
            'nic_number_masked' => $this->maskNicNumber($lead->nic_number),
            'follow_up' => $this->formatDateTime($lead->follow_up),
            'status' => (int) $lead->is_active === 1 ? 'active' : 'inactive',
            'remarks' => optional($lead->comments->first())->comment,
            'assigned_user' => $this->transformAssignedUser($lead),
            'created_at' => optional($lead->created_at)->toDateTimeString(),
            'updated_at' => optional($lead->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformHistoryItem(Work $work): array
    {
        return [
            'id' => $work->id,
            'comment' => $work->comment,
            'call_duration' => $work->call_duration,
            'call_status' => $work->call_status,
            'follow_up' => $this->formatDateTime($work->follow_up),
            'user' => $work->user ? [
                'id' => $work->user->id,
                'name' => $work->user->name,
            ] : null,
            'created_at' => optional($work->created_at)->toDateTimeString(),
        ];
    }

    protected function transformAssignedUser(Lead $lead): ?array
    {
        $assignedUser = $lead->users->first();

        if (!$assignedUser) {
            return null;
        }

        return [
            'id' => $assignedUser->id,
            'name' => $assignedUser->name,
        ];
    }

    protected function maskNicNumber(?string $nicNumber): ?string
    {
        if (empty($nicNumber)) {
            return null;
        }

        $visible = substr($nicNumber, -4);
        $maskedLength = max(strlen($nicNumber) - 4, 5);

        return str_repeat('*', $maskedLength) . $visible;
    }

    protected function buildFollowUpComment(?string $remarks, $status): ?string
    {
        $parts = [];

        if ($status !== null && $status !== '') {
            $parts[] = 'Status: ' . (string) $status;
        }

        if ($remarks !== null && trim($remarks) !== '') {
            $parts[] = trim($remarks);
        }

        return empty($parts) ? null : implode(' | ', $parts);
    }

    protected function formatDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        return Carbon::parse($value)->toDateTimeString();
    }
}
