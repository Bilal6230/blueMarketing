<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use App\Models\CustomerLedger;
use App\Models\DraftLedger;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\PendingUpdate;
use App\Models\ProjectHeadSubhead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MobileApprovalController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function summary(Request $request)
    {
        $this->authorizeApprovalRead($request->user());

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])],
            'table' => ['nullable', Rule::in($this->allowedApprovalTables())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = $this->baseApprovalQuery($request->user());
        $query = $this->applyApprovalFilters($query, $validated);

        $rows = (clone $query)->get(['table_name', 'status']);

        $byTable = collect($this->allowedApprovalTables())->map(function (string $table) use ($rows) {
            return [
                'table' => $table,
                'label' => $this->tableLabel($table),
                'pending_count' => $rows->where('table_name', $table)->where('status', 'pending')->count(),
            ];
        })->filter(fn ($row) => $row['pending_count'] > 0)->values()->all();

        return $this->successResponse('Approval summary loaded.', [
            'pending_count' => $rows->where('status', 'pending')->count(),
            'approved_count' => $rows->where('status', 'approved')->count(),
            'rejected_count' => $rows->where('status', 'rejected')->count(),
            'by_table' => $byTable,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizeApprovalRead($request->user());

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])],
            'table' => ['nullable', Rule::in($this->allowedApprovalTables())],
            'submitted_by' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $status = $validated['status'] ?? 'pending';

        $query = $this->baseApprovalQuery($request->user())
            ->with(['submittedBy:id,name', 'approvedBy:id,name'])
            ->orderByDesc('created_at');

        $filters = array_merge($validated, ['status' => $status]);
        $query = $this->applyApprovalFilters($query, $filters);

        if (!empty($validated['submitted_by'])) {
            $query->where('submitted_by', (int) $validated['submitted_by']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('table_name', 'like', "%{$search}%")
                    ->orWhere('record_id', 'like', "%{$search}%")
                    ->orWhereHas('submittedBy', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $approvals = $query->paginate($perPage);

        return $this->successResponse(
            'Approvals loaded.',
            collect($approvals->items())->map(fn (PendingUpdate $approval) => $this->transformApprovalListItem($approval))->all(),
            [
                'current_page' => $approvals->currentPage(),
                'per_page' => $approvals->perPage(),
                'last_page' => $approvals->lastPage(),
                'total' => $approvals->total(),
            ]
        );
    }

    public function show(Request $request, int $approval)
    {
        $this->authorizeApprovalRead($request->user());

        $pending = $this->findAccessibleApproval($request->user(), $approval);

        return $this->successResponse(
            'Approval detail loaded.',
            $this->transformApprovalDetail($pending)
        );
    }

    public function preview(Request $request, int $approval)
    {
        $this->authorizeApprovalRead($request->user());

        $pending = $this->findAccessibleApproval($request->user(), $approval);
        $oldValues = $this->decodeApprovalValues($pending->old_values);
        $newValues = $this->decodeApprovalValues($pending->new_values);
        $oldValues = $this->sanitizeApprovalValues($oldValues);
        $newValues = $this->sanitizeApprovalValues($newValues);
        $fields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        $changes = collect($fields)->map(function (string $field) use ($oldValues, $newValues) {
            $old = $oldValues[$field] ?? null;
            $new = $newValues[$field] ?? null;

            return [
                'field' => $field,
                'old' => $this->formatApprovalValue($old),
                'new' => $this->formatApprovalValue($new),
                'changed' => $old !== $new,
            ];
        })->values()->all();

        return $this->successResponse('Approval preview loaded.', [
            'approval_id' => (int) $pending->id,
            'table' => $pending->table_name,
            'record_id' => (int) $pending->record_id,
            'changes' => $changes,
        ]);
    }

    public function approve(Request $request, int $approval): JsonResponse
    {
        $user = $request->user();
        $this->authorizeApprovalAction($user);

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $pending = $this->findAccessibleApproval($user, $approval);

        try {
            DB::transaction(function () use ($pending, $user) {
                $this->applyPendingUpdate($pending, $user);
            });
        } catch (\DomainException $e) {
            return $this->mapApprovalDomainException($e);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('server_error', 'Unable to approve the pending update.', 500);
        }

        // TODO: pending_updates has no remarks column; ignore remarks until schema supports it.

        return $this->successResponse('Approval processed successfully.', [
            'id' => (int) $pending->id,
            'status' => 'approved',
        ]);
    }

    public function reject(Request $request, int $approval): JsonResponse
    {
        $user = $request->user();
        $this->authorizeApprovalAction($user);

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $pending = $this->findAccessibleApproval($user, $approval);

        try {
            DB::transaction(function () use ($pending, $user) {
                $locked = PendingUpdate::query()
                    ->where('id', $pending->id)
                    ->lockForUpdate()
                    ->first();

                if (!$locked || $locked->status !== 'pending') {
                    throw new \DomainException('approval_already_processed');
                }

                $this->assertAllowedApprovalTable($locked->table_name);

                $locked->update([
                    'status' => 'rejected',
                    'approved_by' => $user->id,
                ]);
            });
        } catch (\DomainException $e) {
            return $this->mapApprovalDomainException($e);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('server_error', 'Unable to reject the pending update.', 500);
        }

        // TODO: pending_updates has no remarks column; ignore remarks until schema supports it.

        return $this->successResponse('Approval rejected successfully.', [
            'id' => (int) $pending->id,
            'status' => 'rejected',
        ]);
    }

    protected function allowedApprovalTables(): array
    {
        return ['ledgers', 'draft_ledgers', 'customer_ledger', 'leads'];
    }

    protected function assertAllowedApprovalTable(string $table): void
    {
        if (!in_array($table, $this->allowedApprovalTables(), true)) {
            throw new \DomainException('invalid_approval_table');
        }
    }

    protected function authorizeApprovalRead(User $user): void
    {
        if ($user->can('direct-update')) {
            return;
        }

        // TODO: Add dedicated read approval permission to PermissionSeeder when available.
        if ($user->hasAnyRole(['super-admin', 'superadmin', 'admin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function authorizeApprovalAction(User $user): void
    {
        if ($user->can('direct-update')) {
            return;
        }

        if ($user->hasAnyRole(['super-admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function hasPrivilegedApprovalAccess(User $user): bool
    {
        return $user->can('direct-update')
            || $user->hasAnyRole(['super-admin', 'superadmin']);
    }

    protected function baseApprovalQuery(User $user)
    {
        $query = PendingUpdate::query()
            ->whereIn('table_name', $this->allowedApprovalTables());

        if ($this->hasPrivilegedApprovalAccess($user)) {
            return $query;
        }

        return $this->scopeAccessibleApprovals($query, $user);
    }

    protected function scopeAccessibleApprovals($query, User $user)
    {
        $projectIds = $this->getAllowedProjectIds($user)->all();

        if (empty($projectIds)) {
            return $query->whereRaw('1 = 0');
        }

        $leadIds = Lead::query()->whereIn('project_id', $projectIds)->pluck('id');
        $customerLedgerIds = CustomerLedger::query()->whereIn('project_id', $projectIds)->pluck('id');
        $draftLedgerIds = DraftLedger::query()->whereIn('project_id', $projectIds)->pluck('id');
        $projectHeadSubheadIds = ProjectHeadSubhead::query()->whereIn('project_id', $projectIds)->pluck('id');
        $ledgerIds = Ledger::query()->whereIn('project_head_subheads_id', $projectHeadSubheadIds)->pluck('id');

        return $query->where(function ($builder) use ($leadIds, $customerLedgerIds, $draftLedgerIds, $ledgerIds) {
            $builder->where(function ($q) use ($leadIds) {
                $q->where('table_name', 'leads')->whereIn('record_id', $leadIds);
            })->orWhere(function ($q) use ($customerLedgerIds) {
                $q->where('table_name', 'customer_ledger')->whereIn('record_id', $customerLedgerIds);
            })->orWhere(function ($q) use ($draftLedgerIds) {
                $q->where('table_name', 'draft_ledgers')->whereIn('record_id', $draftLedgerIds);
            })->orWhere(function ($q) use ($ledgerIds) {
                $q->where('table_name', 'ledgers')->whereIn('record_id', $ledgerIds);
            });
        });
    }

    protected function applyApprovalFilters($query, array $validated)
    {
        $status = $validated['status'] ?? 'pending';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($validated['table'])) {
            $query->where('table_name', $validated['table']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        return $query;
    }

    protected function findAccessibleApproval(User $user, int $approvalId): PendingUpdate
    {
        $pending = $this->baseApprovalQuery($user)
            ->with(['submittedBy:id,name', 'approvedBy:id,name'])
            ->find($approvalId);

        if (!$pending) {
            throw new NotFoundHttpException();
        }

        $this->ensureCanAccessApproval($pending, $user);

        return $pending;
    }

    protected function ensureCanAccessApproval(PendingUpdate $approval, User $user): void
    {
        $this->assertAllowedApprovalTable($approval->table_name);

        $projectId = $this->resolveApprovalProjectId($approval);

        if ($projectId === null) {
            if (!$this->hasPrivilegedApprovalAccess($user)) {
                throw new AuthorizationException('You do not have permission to perform this action.');
            }

            return;
        }

        if (!$this->hasPrivilegedApprovalAccess($user)) {
            $this->ensureProjectAccess((int) $projectId, $user);
        }
    }

    protected function ensureProjectAccess(int $projectId, User $user): void
    {
        if (!$this->getAllowedProjectIds($user)->contains($projectId)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    protected function getAllowedProjectIds(User $user): Collection
    {
        return $this->allowedProjects($user)->pluck('id');
    }

    protected function resolveApprovalProjectId(PendingUpdate $approval): ?int
    {
        return match ($approval->table_name) {
            'leads' => Lead::query()->where('id', $approval->record_id)->value('project_id'),
            'customer_ledger' => CustomerLedger::query()->where('id', $approval->record_id)->value('project_id'),
            'draft_ledgers' => DraftLedger::query()->where('id', $approval->record_id)->value('project_id'),
            'ledgers' => Ledger::query()
                ->with('projectHeadSubhead:id,project_id')
                ->find($approval->record_id)?->projectHeadSubhead?->project_id,
            default => null,
        };
    }

    protected function applyPendingUpdate(PendingUpdate $approval, User $user): void
    {
        $locked = PendingUpdate::query()
            ->where('id', $approval->id)
            ->lockForUpdate()
            ->first();

        if (!$locked || $locked->status !== 'pending') {
            throw new \DomainException('approval_already_processed');
        }

        $this->assertAllowedApprovalTable($locked->table_name);

        $table = $locked->table_name;
        $newValues = $this->decodeApprovalValues($locked->new_values);

        if (!is_array($newValues)) {
            throw new \DomainException('validation_error');
        }

        $recordExists = DB::table($table)->where('id', $locked->record_id)->exists();
        if (!$recordExists) {
            throw new \DomainException('target_record_missing');
        }

        if ($table === 'leads') {
            // TODO: Lead assignment approvals may have empty new_values; mirrors approveAdmin().
            $safeValues = $this->getSafeUpdateValues($table, $newValues);

            if (empty($safeValues)) {
                DB::table('lead_user')
                    ->where('lead_id', $locked->record_id)
                    ->update([
                        'user_id' => $locked->submitted_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table($table)->where('id', $locked->record_id)->update($safeValues);
            }
        } else {
            $safeValues = $this->getSafeUpdateValues($table, $newValues);

            if (empty($safeValues)) {
                throw new \DomainException('validation_error');
            }

            DB::table($table)->where('id', $locked->record_id)->update($safeValues);

            if ($table === 'ledgers') {
                $ledger = Ledger::query()->find($locked->record_id);
                if ($ledger && $ledger->customerLedger) {
                    $customerLedgerValues = $this->getCustomerLedgerSyncValues($safeValues);
                    if (!empty($customerLedgerValues)) {
                        $ledger->customerLedger->update($customerLedgerValues);
                    }
                }
            }
        }

        $locked->update([
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);
    }

    protected function decodeApprovalValues($values): array
    {
        if (is_array($values)) {
            return $values;
        }

        if (is_string($values)) {
            $decoded = json_decode($values, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function getCustomerLedgerSyncValues(array $ledgerValues): array
    {
        $allowed = [
            'amount_in',
            'amount_out',
            'date',
            'reference',
            'delete_reason',
            'is_active',
        ];

        $syncValues = collect($ledgerValues)
            ->only($allowed)
            ->all();

        if (array_key_exists('detail', $ledgerValues)) {
            $syncValues['description'] = $ledgerValues['detail'];
        }

        return $syncValues;
    }

    protected function getSafeUpdateValues(string $table, array $newValues): array
    {
        $blocked = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        return collect($newValues)
            ->reject(function ($value, $field) use ($blocked) {
                $field = (string) $field;

                if (in_array($field, $blocked, true)) {
                    return true;
                }

                return $this->isSensitiveApprovalField($field);
            })
            ->all();
    }

    protected function sanitizeApprovalValues(array $values): array
    {
        $sanitized = [];

        foreach ($values as $field => $value) {
            if ($this->isSensitiveApprovalField((string) $field)) {
                continue;
            }

            $sanitized[$field] = $this->sanitizeApprovalField((string) $field, $value);
        }

        return $sanitized;
    }

    protected function sanitizeApprovalField(string $field, $value)
    {
        if ($value === null) {
            return null;
        }

        $fieldLower = strtolower($field);

        if (str_contains($fieldLower, 'nic') || str_contains($fieldLower, 'cnic')) {
            return $this->maskSensitiveId((string) $value);
        }

        if (str_contains($fieldLower, 'phone') || str_contains($fieldLower, 'mobile')) {
            return $this->maskPhone((string) $value);
        }

        if (is_array($value)) {
            return $this->sanitizeApprovalValues($value);
        }

        return $value;
    }

    protected function isSensitiveApprovalField(string $field): bool
    {
        $field = strtolower($field);
        $needles = [
            'password',
            'pass',
            'token',
            'remember_token',
            'api_key',
            'secret',
            'private_key',
            'access_token',
            'refresh_token',
            'otp',
            'two_factor',
        ];

        foreach ($needles as $needle) {
            if (str_contains($field, $needle)) {
                return true;
            }
        }

        return false;
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

    protected function maskSensitiveId(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);

        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', max(5, strlen($value) - 4)) . substr($value, -4);
    }

    protected function tableLabel(string $table): string
    {
        return match ($table) {
            'ledgers' => 'Ledger',
            'draft_ledgers' => 'Draft Ledger',
            'customer_ledger' => 'Customer Ledger',
            'leads' => 'Lead',
            default => ucfirst(str_replace('_', ' ', $table)),
        };
    }

    protected function approvalChangedFields(PendingUpdate $approval): array
    {
        $newValues = $this->decodeApprovalValues($approval->new_values);
        $blocked = ['id', 'created_at', 'updated_at', 'deleted_at'];

        return collect(array_keys($newValues))
            ->reject(function (string $field) use ($blocked) {
                if (in_array($field, $blocked, true)) {
                    return true;
                }

                return $this->isSensitiveApprovalField($field);
            })
            ->values()
            ->all();
    }

    protected function approvalSummaryText(PendingUpdate $approval): string
    {
        return $this->tableLabel($approval->table_name) . ' update request';
    }

    protected function targetRecordSummary(PendingUpdate $approval): array
    {
        $table = $approval->table_name;
        $recordId = (int) $approval->record_id;

        $exists = DB::table($table)->where('id', $recordId)->exists();
        if (!$exists) {
            return [
                'exists' => false,
                'summary' => null,
            ];
        }

        $summary = match ($table) {
            'ledgers' => $this->ledgerSummary($recordId),
            'draft_ledgers' => $this->draftLedgerSummary($recordId),
            'customer_ledger' => $this->customerLedgerSummary($recordId),
            'leads' => $this->leadSummary($recordId),
            default => "Record #{$recordId}",
        };

        return [
            'exists' => true,
            'summary' => $summary,
        ];
    }

    protected function ledgerSummary(int $recordId): ?string
    {
        $ledger = Ledger::query()->select(['id', 'voucher_number', 'detail', 'type'])->find($recordId);

        if (!$ledger) {
            return null;
        }

        $label = $ledger->voucher_number
            ? "Voucher #{$ledger->voucher_number}"
            : trim((string) ($ledger->detail ?: "Ledger #{$ledger->id}"));

        return $ledger->type ? "{$ledger->type} {$label}" : $label;
    }

    protected function draftLedgerSummary(int $recordId): ?string
    {
        $draft = DraftLedger::query()->select(['id', 'reference', 'description', 'transaction_type'])->find($recordId);

        if (!$draft) {
            return null;
        }

        return trim((string) ($draft->reference ?: $draft->description ?: "Draft #{$draft->id}"));
    }

    protected function customerLedgerSummary(int $recordId): ?string
    {
        $ledger = CustomerLedger::query()->select(['id', 'reference', 'description', 'transaction_type'])->find($recordId);

        if (!$ledger) {
            return null;
        }

        return trim((string) ($ledger->reference ?: $ledger->description ?: "Customer ledger #{$ledger->id}"));
    }

    protected function leadSummary(int $recordId): ?string
    {
        $lead = Lead::query()->select(['id', 'first_name', 'last_name'])->find($recordId);

        if (!$lead) {
            return null;
        }

        return trim($lead->first_name . ' ' . $lead->last_name) ?: "Lead #{$lead->id}";
    }

    protected function transformApprovalListItem(PendingUpdate $approval): array
    {
        return [
            'id' => (int) $approval->id,
            'table' => $approval->table_name,
            'table_label' => $this->tableLabel($approval->table_name),
            'record_id' => (int) $approval->record_id,
            'status' => $approval->status,
            'submitted_by' => $approval->submittedBy ? [
                'id' => (int) $approval->submittedBy->id,
                'name' => $approval->submittedBy->name,
            ] : null,
            'approved_by' => $approval->approvedBy ? [
                'id' => (int) $approval->approvedBy->id,
                'name' => $approval->approvedBy->name,
            ] : null,
            'created_at' => optional($approval->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($approval->updated_at)->format('Y-m-d H:i:s'),
            'summary' => $this->approvalSummaryText($approval),
            'changed_fields' => $this->approvalChangedFields($approval),
        ];
    }

    protected function transformApprovalDetail(PendingUpdate $approval): array
    {
        $oldValues = $this->sanitizeApprovalValues($this->decodeApprovalValues($approval->old_values));
        $newValues = $this->sanitizeApprovalValues($this->decodeApprovalValues($approval->new_values));

        return [
            'id' => (int) $approval->id,
            'table' => $approval->table_name,
            'table_label' => $this->tableLabel($approval->table_name),
            'record_id' => (int) $approval->record_id,
            'status' => $approval->status,
            'submitted_by' => $approval->submittedBy ? [
                'id' => (int) $approval->submittedBy->id,
                'name' => $approval->submittedBy->name,
            ] : null,
            'approved_by' => $approval->approvedBy ? [
                'id' => (int) $approval->approvedBy->id,
                'name' => $approval->approvedBy->name,
            ] : null,
            'old_values' => (object) $oldValues,
            'new_values' => (object) $newValues,
            'changed_fields' => $this->approvalChangedFields($approval),
            'target_record' => $this->targetRecordSummary($approval),
            'created_at' => optional($approval->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($approval->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    protected function formatApprovalValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    protected function mapApprovalDomainException(\DomainException $e): JsonResponse
    {
        return match ($e->getMessage()) {
            'approval_already_processed' => $this->errorResponse(
                'approval_already_processed',
                'This approval has already been processed.',
                409
            ),
            'invalid_approval_table' => $this->errorResponse(
                'invalid_approval_table',
                'The approval table is not allowed.',
                422
            ),
            'target_record_missing' => $this->errorResponse(
                'target_record_missing',
                'The target record no longer exists.',
                404
            ),
            'validation_error' => $this->errorResponse(
                'validation_error',
                'No safe fields were available to apply for this approval.',
                422
            ),
            default => $this->errorResponse('server_error', 'Unable to process approval.', 500),
        };
    }
}
