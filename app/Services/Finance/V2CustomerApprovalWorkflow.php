<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2CustomerApprovalWorkflow
{
    public function approveAndPost(int $projectId, int $sourceId, string $key, array $lines, ?int $actorId): int
    {
        V2IsolatedConnectionGuard::assertAllowed();
        return DB::transaction(function () use ($projectId, $sourceId, $key, $lines, $actorId): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $source = DB::table('customer_ledger')->where('id', $sourceId)
                ->where('project_id', $projectId)->lockForUpdate()->first();
            if (!$source || (int) $source->is_active !== 1
                || !in_array($source->transaction_type, ['CR', 'CP', 'PPR', 'EC', 'ECP'], true)) {
                throw new \DomainException('V2_CUSTOMER_SOURCE_NOT_APPROVABLE');
            }
            if (DB::getSchemaBuilder()->hasTable('ledgers') && DB::table('ledgers')
                ->where('customer_ledger_id', $sourceId)->exists()) {
                throw new \DomainException('V2_CUSTOMER_LEGACY_POSTING_EXISTS');
            }
            DB::table('customer_ledger')->where('id', $sourceId)->update(['is_approve' => 1]);
            $gateway = new V2PostingGateway();
            return in_array($source->transaction_type, ['CR', 'CP'], true)
                ? $gateway->postCash($projectId, $sourceId, $key, $lines, $actorId)
                : $gateway->postCustomerVoucher($projectId, $sourceId, $source->transaction_type, $key, $lines, $actorId);
        }, 3);
    }
}
