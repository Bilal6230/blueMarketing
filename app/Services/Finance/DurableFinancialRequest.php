<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Durable, database-unique idempotent execution for future financial posting.
 * Used by new JV posting when approved finance metadata schema is present.
 */
final class DurableFinancialRequest
{
    /**
     * @param callable():int $post Creates one voucher, all its details/ledgers and
     *                           typed source rows, in the SAME DB transaction.
     * @return array{replayed:bool,voucher_id:int}
     */
    public function execute(
        string $sourceKind,
        int $projectId,
        string $requestKey,
        string $payloadHash,
        callable $post
    ): array {
        if (!in_array($sourceKind, ['journal_voucher', 'commission_voucher'], true)
            || $projectId < 1
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey)
            || !preg_match('/^[a-f0-9]{64}$/D', $payloadHash)) {
            throw new \InvalidArgumentException('Invalid financial request identity.');
        }

        return DB::transaction(function () use ($sourceKind, $projectId, $requestKey, $payloadHash, $post) {
            // Project row lock serializes writers on MySQL; unique constraint is
            // the final backstop against concurrent duplicate submissions.
            $project = DB::table('projects')->where('id', $projectId)->lockForUpdate()->first(['id']);
            if (!$project) {
                throw new \DomainException('Project does not exist.');
            }

            $query = DB::table('financial_posting_requests')
                ->where('project_id', $projectId)->where('source_kind', $sourceKind)
                ->where('request_key', $requestKey);
            $existing = (clone $query)->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw new \DomainException('Idempotency key reused with a different financial payload.');
                }
                if ($existing->status !== 'committed' || !$existing->voucher_id) {
                    throw new \RuntimeException('Previous financial request has not committed.');
                }
                // A committed request is not enough if its voucher has since
                // disappeared or is in a different project. Never return a
                // false successful replay for an orphaned financial request.
                $headerTable = $sourceKind === 'journal_voucher'
                    ? 'journal_vouchers' : 'commision_vouchers';
                if (!DB::table($headerTable)->where('id', $existing->voucher_id)
                    ->where('project_id', $projectId)->exists()) {
                    throw new \RuntimeException('Previous financial request references a missing or mismatched voucher.');
                }
                return ['replayed' => true, 'voucher_id' => (int) $existing->voucher_id];
            }

            DB::table('financial_posting_requests')->insert([
                'project_id' => $projectId,
                'source_kind' => $sourceKind,
                'request_key' => $requestKey,
                'payload_hash' => $payloadHash,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $voucherId = $post();
            if (!is_int($voucherId) || $voucherId < 1) {
                throw new \RuntimeException('Financial callback must return a committed voucher ID.');
            }

            DB::table('financial_posting_requests')
                ->where('project_id', $projectId)->where('source_kind', $sourceKind)
                ->where('request_key', $requestKey)
                ->update(['voucher_id' => $voucherId, 'status' => 'committed', 'updated_at' => now()]);

            return ['replayed' => false, 'voucher_id' => $voucherId];
        }, 3);
    }
}
