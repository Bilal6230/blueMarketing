<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Reverse one exact original bank transfer batch, not a voucher-number lookup.
 * A linked cleared cheque remains in custody until an explicit release path
 * exists; silently reversing its bank posting would misstate cheque status.
 */
final class BankTransferLifecycleService
{
    public function reverse(
        int $projectId,
        string $kind,
        int $batchId,
        string $requestKey,
        string $effectiveDate,
        string $reason,
        ?int $actorId
    ): int {
        FinanceConnectionGuard::assertAllowed();
        if ($projectId < 1 || $batchId < 1 || !in_array($kind, ['deposit', 'withdrawal'], true)) {
            throw new \DomainException('BANK_TRANSFER_REVERSAL_IDENTITY_INVALID');
        }
        return DB::transaction(function () use ($projectId, $kind, $batchId, $requestKey, $effectiveDate, $reason, $actorId): int {
            $source = DB::table('finance_bank_transfer_sources as transfer')
                ->join('finance_posting_batches as batch', 'batch.id', '=', 'transfer.posting_batch_id')
                ->join('finance_posting_source_claims as claim', 'claim.posting_batch_id', '=', 'batch.id')
                ->where('transfer.posting_batch_id', $batchId)
                ->where('transfer.project_id', $projectId)
                ->where('transfer.transfer_kind', $kind)
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('finance_bank_transfer_sources','is_active'),
                    fn($q)=>$q->where('transfer.is_active',1))
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('finance_bank_transfer_sources','deleted_at'),
                    fn($q)=>$q->whereNull('transfer.deleted_at'))
                ->where('batch.project_id', $projectId)
                ->where('batch.voucher_series', $kind === 'deposit' ? 'BD' : 'BW')
                ->whereNull('batch.reversal_of_batch_id')
                ->where('claim.source_kind', 'bank_transfer')
                ->lockForUpdate()
                ->first(['transfer.linked_customer_ledger_id', 'batch.id']);
            if (!$source) {
                throw new \DomainException('BANK_TRANSFER_REVERSAL_SOURCE_NOT_FOUND');
            }
            if ($source->linked_customer_ledger_id !== null) {
                throw new \DomainException('BANK_TRANSFER_LINKED_CHEQUE_REQUIRES_CUSTODY_RELEASE');
            }
            if(\Illuminate\Support\Facades\Schema::hasTable('finance_bank_voucher_allocations')
                && DB::table('finance_bank_voucher_allocations')
                    ->where('project_id',$projectId)
                    ->where('voucher_type',$kind==='deposit'?'BD':'BW')
                    ->where('voucher_id',$batchId)->exists()){
                throw new \DomainException('BANK_TRANSFER_VERIFIED_SOURCE_REQUIRES_CUSTODY_RELEASE');
            }
            return (new PostingGateway())->reversePostedBatch(
                $projectId, $batchId, $requestKey, $effectiveDate, $reason, $actorId
            );
        }, 3);
    }
}
