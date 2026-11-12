<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only history of voucher allocations tied to a passed Online/Cheque
 * management source. Released allocations remain visible as audit evidence.
 */
final class BankSourceLinkedVoucherHistory
{
    public function forSource(int $projectId, string $method, int $sourceId): array
    {
        if ($projectId < 1 || $sourceId < 1 || !in_array($method,['online','cheque'],true)) {
            throw new \InvalidArgumentException('Invalid bank management source.');
        }
        if (!Schema::hasTable('finance_bank_voucher_allocations')) {
            return [];
        }

        $allocations=DB::table('finance_bank_voucher_allocations')
            ->where('project_id',$projectId)
            ->where('source_method',$method)
            ->where('source_id',$sourceId)
            ->orderBy('id')
            ->get(['id','voucher_type','voucher_id','amount','allocated_at']);

        if ($allocations->isEmpty()) return [];
        $hasReleases=Schema::hasTable('finance_bank_voucher_allocation_releases');
        $hasApprovals=Schema::hasTable('daily_voucher_approvals');
        $hasNumbers=Schema::hasTable('project_voucher_display_numbers');

        return $allocations->map(function ($allocation) use ($projectId,$hasReleases,$hasApprovals,$hasNumbers): array {
            $release=$hasReleases
                ? DB::table('finance_bank_voucher_allocation_releases')
                    ->where('project_id',$projectId)
                    ->where('allocation_id',$allocation->id)
                    ->first(['action','reason','released_at','released_by']) : null;
            $approval=$hasApprovals
                ? DB::table('daily_voucher_approvals')
                    ->where('project_id',$projectId)
                    ->where('source_table','customer_ledger')
                    ->where('source_id',$allocation->voucher_id)
                    ->where('voucher_type',$allocation->voucher_type)
                    ->first(['status','decided_at']) : null;
            $display=$hasNumbers
                ? DB::table('project_voucher_display_numbers')
                    ->where('project_id',$projectId)
                    ->where('source_kind','customer_ledger')
                    ->where('source_id',$allocation->voucher_id)
                    ->where('series',$allocation->voucher_type)
                    ->value('display_number') : null;

            return [
                'allocation_id'=>(int)$allocation->id,
                'voucher_type'=>(string)$allocation->voucher_type,
                'voucher_id'=>(int)$allocation->voucher_id,
                'voucher_number'=>$display ?: null,
                'amount'=>(string)$allocation->amount,
                'allocated_at'=>(string)$allocation->allocated_at,
                'approval_status'=>$approval?->status,
                'released'=>$release!==null,
                'release_action'=>$release?->action,
                'release_reason'=>$release?->reason,
                'released_at'=>$release?->released_at,
            ];
        })->all();
    }
}
