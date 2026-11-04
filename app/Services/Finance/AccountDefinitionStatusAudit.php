<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class AccountDefinitionStatusAudit
{
    public function record(
        string $type,
        int $id,
        int $oldStatus,
        int $newStatus,
        string $reason,
        ?int $actorId
    ): void {
        if (!in_array($type, ['head', 'subhead', 'site', 'stock_party'], true) || $id < 1 ||
            !in_array($oldStatus, [0, 1], true) || !in_array($newStatus, [0, 1], true) ||
            $oldStatus === $newStatus || trim($reason) === '' || mb_strlen(trim($reason)) > 500) {
            throw new \DomainException('ACCOUNT_STATUS_AUDIT_INPUT_INVALID');
        }

        DB::table('account_definition_status_audits')->insert([
            'definition_type' => $type,
            'definition_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => trim($reason),
            'changed_by' => $actorId,
            'changed_at' => now(),
        ]);
    }
}
