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
        if ($projectId < 1 || $voucherId < 1 || !in_array($type,['JV','SV','CV'],true)) {
            throw new \DomainException('Invalid linked voucher scope.');
        }
        $parent=$type==='CV'?'commision_vouchers':'journal_vouchers';
        $details=$type==='CV'?'commision_voucher_details':'journal_voucher_details';
        $detailFk=$type==='CV'?'commision_voucher_id':'journal_voucher_id';
        $header=DB::table($parent)->where('id',$voucherId)
            ->where('project_id',$projectId)->whereNull('deleted_at')->lockForUpdate()->first();
        if (!$header || ($type!=='CV' && (string)$header->type!==$type))
            throw new \DomainException('Voucher is missing, another series, or outside the selected project.');

        $activeDetails=DB::table($details)->where($detailFk,$voucherId)
            ->whereNull('deleted_at')->lockForUpdate()->get(['id','account_id','debit','credit']);
        if ($activeDetails->isEmpty()) {
            throw new \DomainException('Voucher details are missing; no rows modified.');
        }
        // Include all linked legs (including already inactive ones) so that
        // foreign-project or mismatched detail references cannot be overlooked.
        $legFields=['id','project_head_subheads_id','journal_voucher_detail_id',
            'amount_in','amount_out','is_active'];
        $hasCommissionDetailLink=Schema::hasColumn('ledgers','commision_voucher_detail_id');
        if ($hasCommissionDetailLink) $legFields[]='commision_voucher_detail_id';
        $legs=DB::table('ledgers')->where('type',$type)->where('type_id',$voucherId)
            ->lockForUpdate()->get($legFields);
        $active=$legs->filter(fn($l)=>(int)$l->is_active===1);
        if ($active->isEmpty()) throw new \DomainException('No active ledger legs; nothing to retire.');
        $accountIds=$legs->pluck('project_head_subheads_id')->unique()->all();
        $validAccounts=DB::table('project_head_subheads')->where('project_id',$projectId)
            ->whereIn('id',$accountIds)->count();
        if ($validAccounts!==count($accountIds)) {
            throw new \DomainException('Linked voucher has cross-project or missing accounts; no rows modified.');
        }
        if ($type==='JV'||$type==='SV') {
            $detailIds=$activeDetails->pluck('id')->map(fn($id)=>(int)$id)->all();
            foreach ($active as $leg) {
                $detail=(int)($leg->journal_voucher_detail_id??0);
                if ($detail && !in_array($detail,$detailIds,true)) {
                    throw new \DomainException('Journal ledger points to a different voucher detail; no rows modified.');
                }
            }
        }
        if ($type==='CV' && $hasCommissionDetailLink) {
            $detailsById=$activeDetails->keyBy('id');
            $seenLinked=[];
            foreach ($active as $leg) {
                $detailId=(int)($leg->commision_voucher_detail_id??0);
                // NULL means a historical CV leg from before the FK existed.
                // It remains covered by the exact account/amount multiset.
                if (!$detailId) continue;
                $detail=$detailsById->get($detailId);
                if (!$detail || isset($seenLinked[$detailId])) {
                    throw new \DomainException('Commission ledger points to a different or duplicate voucher detail; no rows modified.');
                }
                $signature=static fn($account,$debit,$credit): string =>
                    (int)$account.'|'.number_format((float)$debit,2,'.','').'|'.number_format((float)$credit,2,'.','');
                if ($signature($detail->account_id,$detail->debit,$detail->credit)!==
                    $signature($leg->project_head_subheads_id,$leg->amount_in,$leg->amount_out)) {
                    throw new \DomainException('Commission ledger detail account or amount mismatch; no rows modified.');
                }
                $seenLinked[$detailId]=true;
            }
        }
        // A ledger set with old reversal legs or unrelated rows must not be
        // deactivated as though it were exactly this voucher's current details.
        // Use a multiset: duplicate equal-value lines are valid but count.
        $signature=static fn($account,$debit,$credit): string =>
            (int)$account.'|'.number_format((float)$debit,2,'.','').'|'.number_format((float)$credit,2,'.','');
        $expected=[];
        foreach ($activeDetails as $detail) {
            $key=$signature($detail->account_id,$detail->debit,$detail->credit);
            $expected[$key]=($expected[$key]??0)+1;
        }
        $actual=[];
        foreach ($active as $leg) {
            $key=$signature($leg->project_head_subheads_id,$leg->amount_in,$leg->amount_out);
            $actual[$key]=($actual[$key]??0)+1;
        }
        ksort($expected);
        ksort($actual);
        if ($expected!==$actual) {
            throw new \DomainException('Voucher detail/ledger amounts or ownership mismatch; no rows modified.');
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
