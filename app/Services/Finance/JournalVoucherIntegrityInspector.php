<?php

namespace App\Services\Finance;

use App\Models\JournalVoucher;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, project-scoped JV integrity inspection.
 *
 * Legacy ledgers.type='JV' may also represent Commission Voucher postings.
 * A matching type_id alone NEVER establishes ledger provenance.
 */
final class JournalVoucherIntegrityInspector
{
    public function inspect(int $projectId, int $journalVoucherId): array
    {
        $header = JournalVoucher::withTrashed()
            ->where('project_id', $projectId)->where('type', 'JV')
            ->findOrFail($journalVoucherId);

        $details = DB::table('journal_voucher_details')
            ->where('journal_voucher_id', $journalVoucherId)
            ->whereNull('deleted_at')->get(['debit', 'credit']);

        $debit = 0;
        $credit = 0;
        $invalidDetailAmounts = false;
        foreach ($details as $detail) {
            $d = $this->cents($detail->debit);
            $c = $this->cents($detail->credit);
            if ($d === null || $c === null || ($d > 0 && $c > 0) ||
                ($d === 0 && $c === 0)) {
                $invalidDetailAmounts = true;
                continue;
            }
            $debit += $d;
            $credit += $c;
        }

        $headerDebit = $this->cents($header->total_debit);
        $headerCredit = $this->cents($header->total_credit);
        $issues = [];
        if ($details->isEmpty()) {
            $issues[] = 'missing_active_details';
        }
        if ($invalidDetailAmounts || $headerDebit === null || $headerCredit === null) {
            $issues[] = 'invalid_amount';
        }
        if ($debit !== $credit || $debit === 0) {
            $issues[] = 'details_unbalanced';
        }
        if ($headerDebit !== $debit || $headerCredit !== $credit) {
            $issues[] = 'header_detail_mismatch';
        }

        // Do not sum these ledger rows as proof of a JV posting: historical CV
        // rows may carry type='JV' and numerically collide with this header ID.
        $candidateLedgerRows = DB::table('ledgers')
            ->where('type', 'JV')->where('type_id', $journalVoucherId)
            ->whereIn('project_head_subheads_id', function ($query) use ($projectId) {
                $query->select('id')->from('project_head_subheads')
                    ->where('project_id', $projectId);
            })->count();

        return [
            'project_id' => $projectId,
            'journal_voucher_id' => $journalVoucherId,
            'header_debit' => $header->total_debit,
            'header_credit' => $header->total_credit,
            'active_detail_count' => $details->count(),
            'detail_debit_cents' => $debit,
            'detail_credit_cents' => $credit,
            'header_details_consistent' => $issues === [],
            'issues' => $issues,
            'candidate_jv_ledger_rows' => $candidateLedgerRows,
            'ledger_provenance' => 'unverified_legacy_type_id_collision_possible',
        ];
    }

    private function cents($amount): ?int
    {
        $value = (string) $amount;
        if (!preg_match('/^\\d{1,12}(?:\\.\\d{1,2})?$/D', $value)) {
            return null;
        }
        $parts = explode('.', $value);
        return ((int) $parts[0] * 100) +
            (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
