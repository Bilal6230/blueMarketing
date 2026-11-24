<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Routes generic authenticated batch reversals through source-specific
 * lifecycle guards. A bare counter-posting cannot safely mutate linked
 * inventory, cheque custody, labour payroll or customer-ledger projections.
 */
final class FinanceSourceReversalRouter
{
    public function reverse(int $projectId, int $batchId, string $requestKey,
        string $effectiveDate, string $reason, ?int $actorId): int
    {
        FinanceConnectionGuard::assertAllowed();
        if ($projectId < 1 || $batchId < 1) {
            throw new \DomainException('FINANCE_REVERSAL_SOURCE_INVALID');
        }

        $claim=DB::table('finance_posting_source_claims as claim')
            ->join('finance_posting_batches as batch','batch.id','=','claim.posting_batch_id')
            ->where('batch.id',$batchId)->where('batch.project_id',$projectId)
            ->whereNull('batch.reversal_of_batch_id')
            ->first(['claim.source_kind','batch.voucher_series']);
        if (!$claim) {
            throw new \DomainException('FINANCE_REVERSAL_SOURCE_NOT_FOUND');
        }

        $kind=(string)$claim->source_kind;
        $lookups=[
            'journal'=>['finance_posting_journal_sources','journal_voucher_id'],
            'commission'=>['finance_posting_commission_sources','commision_voucher_id'],
            'cash'=>['finance_posting_cash_sources','customer_ledger_id'],
            'plot_receipt'=>['finance_plot_receipt_postings','source_id'],
            'plot_purchase'=>['finance_plot_purchase_postings','source_id'],
        ];
        if (isset($lookups[$kind])) {
            [$table,$field]=$lookups[$kind];
            $sourceId=(int) DB::table($table)->where('posting_batch_id',$batchId)->value($field);
            if ($sourceId < 1) {
                throw new \DomainException('FINANCE_REVERSAL_SOURCE_INCONSISTENT');
            }
            return match ($kind) {
                'journal','commission'=>(new VoucherLifecycleService())->reverse(
                    $projectId,$kind,$sourceId,$requestKey,$effectiveDate,$reason,$actorId),
                'cash'=>(new CashVoucherLifecycleService())->reverse(
                    $projectId,$sourceId,$requestKey,$effectiveDate,$reason,$actorId),
                'plot_receipt','plot_purchase'=>(new PlotEntryLifecycleService())->reverse(
                    $projectId,$kind,$sourceId,$requestKey,$effectiveDate,$reason,$actorId),
            };
        }

        if ($kind==='bank_transfer') {
            $transfer=DB::table('finance_bank_transfer_sources')
                ->where('posting_batch_id',$batchId)->where('project_id',$projectId)
                ->first(['transfer_kind']);
            if (!$transfer || !in_array($transfer->transfer_kind,['deposit','withdrawal'],true)) {
                throw new \DomainException('FINANCE_REVERSAL_SOURCE_INCONSISTENT');
            }
            return (new BankTransferLifecycleService())->reverse(
                $projectId,(string)$transfer->transfer_kind,$batchId,
                $requestKey,$effectiveDate,$reason,$actorId
            );
        }

        // These source kinds have inventory/payroll/booking dependencies.
        // Until a dedicated source-aware reversal lifecycle exists, the
        // generic API must refuse rather than write a GL-only counter-posting.
        throw new \DomainException('FINANCE_REVERSAL_SOURCE_WORKFLOW_REQUIRED');
    }
}
