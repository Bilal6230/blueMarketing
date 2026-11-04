<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class AccountGroupAudit
{
    public function record(int $projectId, int $groupId, string $event, ?int $actorId, ?int $headId, ?array $before, ?array $after): void
    {
        if ($projectId < 1 || $groupId < 1 || !in_array($event, ['created', 'updated', 'head_assigned', 'merge_applied'], true)) {
            throw new \DomainException('ACCOUNT_GROUP_AUDIT_INPUT_INVALID');
        }

        DB::table('account_group_audits')->insert([
            'account_group_id' => $groupId,
            'project_id' => $projectId,
            'head_accounting_id' => $headId,
            'event' => $event,
            'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_data' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'changed_by' => $actorId,
            'changed_at' => now(),
        ]);
    }
}
