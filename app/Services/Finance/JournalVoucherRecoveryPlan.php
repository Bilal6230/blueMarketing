<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Pure evaluation of the concrete JV recovery evidence. No mutation.
 */
final class JournalVoucherRecoveryPlan
{
    public function inspect(int $journalId): array
    {
        $j = DB::table('journal_vouchers')->where('id', $journalId)->first();
        if (!$j || $j->type !== 'JV' || $j->deleted_at !== null) {
            return ['eligible' => false, 'blockers' => ['missing_deleted_or_wrong_type_header']];
        }
        $rows = DB::table('journal_voucher_details as d')
            ->leftJoin('project_head_subheads as a', 'a.id', '=', 'd.account_id')
            ->where('d.journal_voucher_id', $journalId)->whereNull('d.deleted_at')
            ->orderBy('d.id')->get(['d.id','d.account_id','d.debit','d.credit','d.description',
                'a.project_id as account_project']);
        $blockers = [];
        if ($rows->count() < 2) {
            $blockers[] = 'too_few_details';
        }
        $debit = 0;
        $credit = 0;
        foreach ($rows as $r) {
            $in = (int) round((float) $r->debit * 100);
            $out = (int) round((float) $r->credit * 100);
            $debit += $in;
            $credit += $out;
            if ($r->account_project === null || (int) $r->account_project !== (int) $j->project_id) {
                $blockers[] = 'foreign_or_missing_account';
            }
            if (($in <= 0 && $out <= 0) || ($in > 0 && $out > 0)) {
                $blockers[] = 'invalid_detail_amount';
            }
        }
        if ($debit <= 0 || $debit !== $credit) {
            $blockers[] = 'unbalanced_details';
        }
        if ((int) round((float) $j->total_debit * 100) !== $debit ||
            (int) round((float) $j->total_credit * 100) !== $credit) {
            $blockers[] = 'header_detail_total_mismatch';
        }
        if (DB::table('ledgers')->where('type', 'JV')->where('type_id', $journalId)->exists()) {
            $blockers[] = 'polymorphic_type_id_already_in_use';
        }
        $competition = DB::table('ledgers as l')
            ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->where('l.type', 'JV')->where('a.project_id', $j->project_id)
            ->where('l.voucher_number', $j->voucher_number)->count();
        if ($competition > 0) {
            $blockers[] = 'displayed_voucher_number_reused';
        }
        return [
            'eligible' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'header_id' => $journalId,
            'project_id' => (int) $j->project_id,
            'voucher_number' => $j->voucher_number,
            'detail_count' => $rows->count(),
            'debit_cents' => $debit, 'credit_cents' => $credit,
            'read_only' => true,
        ];
    }
}
