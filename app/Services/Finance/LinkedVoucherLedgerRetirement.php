<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Use inside the caller's database transaction. Retires ONLY a locked
 * voucher's exact legacy ledger legs; never manipulates unrelated vouchers.
 * Business history remains as inactive ledger rows; no balancing reversal
 * entries are generated for these legacy corrections/deletions.
 */
final class LinkedVoucherLedgerRetirement
{
    public function retire(int $projectId, string $type, int $voucherId): int
    {
        if ($projectId < 1 || $voucherId < 1 || !in_array($type,['JV','CV'],true)) {
            throw new \DomainException('Invalid linked voucher scope.');
        }
        $parent=$type==='JV'?'journal_vouchers':'commision_vouchers';
        $details=$type==='JV'?'journal_voucher_details':'commision_voucher_details';
        $detailFk=$type==='JV'?'journal_voucher_id':'commision_voucher_id';
        $header=DB::table($parent)->where('id',$voucherId)
            ->where('project_id',$projectId)->whereNull('deleted_at')->lockForUpdate()->first();
        if (!$header) throw new \DomainException('Voucher is missing or outside the selected project.');

        $activeDetails=DB::table($details)->where($detailFk,$voucherId)
            ->whereNull('deleted_at')->lockForUpdate()->get(['id','account_id','debit','credit']);
        if ($activeDetails->isEmpty()) {
            throw new \DomainException('Voucher details are missing; no rows modified.');
        }
        // Include all linked legs (including already inactive ones) so that
        // foreign-project or mismatched detail references cannot be overlooked.
        $legs=DB::table('ledgers')->where('type',$type)->where('type_id',$voucherId)
            ->lockForUpdate()->get(['id','project_head_subheads_id','journal_voucher_detail_id',
                'amount_in','amount_out','is_active']);
        $active=$legs->filter(fn($l)=>(int)$l->is_active===1);
        if ($active->isEmpty()) throw new \DomainException('No active ledger legs; nothing to retire.');
        $accountIds=$legs->pluck('project_head_subheads_id')->unique()->all();
        $validAccounts=DB::table('project_head_subheads')->where('project_id',$projectId)
            ->whereIn('id',$accountIds)->count();
        if ($validAccounts!==count($accountIds)) {
            throw new \DomainException('Linked voucher has cross-project or missing accounts; no rows modified.');
        }
        if ($type==='JV') {
            $detailIds=$activeDetails->pluck('id')->map(fn($id)=>(int)$id)->all();
            foreach ($active as $leg) {
                $detail=(int)($leg->journal_voucher_detail_id??0);
                if ($detail && !in_array($detail,$detailIds,true)) {
                    throw new \DomainException('Journal ledger points to a different voucher detail; no rows modified.');
                }
            }
        }
        $ids=$active->pluck('id')->all();
        // Only these explicit source-linked ledger IDs are retired. No bulk
        // type-only matching, no DB delete, no external account mutation.
        $affected=DB::table('ledgers')->whereIn('id',$ids)
            ->where('type',$type)->where('type_id',$voucherId)
            ->where('is_active',1)->update(['is_active'=>0,'updated_at'=>now()]);
        if ($affected!==count($ids)) {
            throw new \DomainException('Concurrent voucher ledger mutation; rolled back.');
        }
        return $affected;
    }
}
