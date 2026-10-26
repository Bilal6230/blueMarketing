<?php

namespace App\Services\Finance;

use App\Models\CommisionVoucher;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Commission Voucher header/detail inspection.
 * Never infer ledger provenance from legacy JV type_id collisions.
 */
final class CommissionVoucherIntegrityInspector
{
    public function inspect(int $projectId, int $voucherId): array
    {
        $header = CommisionVoucher::withTrashed()->where('project_id', $projectId)
            ->findOrFail($voucherId);
        $details = DB::table('commision_voucher_details')
            ->where('commision_voucher_id', $voucherId)->whereNull('deleted_at')
            ->get(['debit', 'credit']);
        $debit = 0;
        $credit = 0;
        $issues = [];
        foreach ($details as $detail) {
            $d = $this->cents($detail->debit);
            $c = $this->cents($detail->credit);
            if ($d === null || $c === null || ($d === 0 && $c === 0) || ($d > 0 && $c > 0)) {
                $issues[] = 'invalid_detail_amount';
                continue;
            }
            $debit += $d;
            $credit += $c;
        }
        if ($details->isEmpty()) {
            $issues[] = 'missing_active_details';
        }
        if ($debit === 0 || $debit !== $credit) {
            $issues[] = 'details_unbalanced';
        }
        $headerDebit = $this->cents($header->total_debit);
        if ($headerDebit === null || $headerDebit !== $debit) {
            $issues[] = 'header_detail_mismatch';
        }
        // Some legacy CV schemas only store total_debit, not total_credit.
        // Do not presume a missing column exists.
        $issues = array_values(array_unique($issues));
        return [
            'project_id' => $projectId,
            'commission_voucher_id' => $voucherId,
            'active_detail_count' => $details->count(),
            'detail_debit_cents' => $debit,
            'detail_credit_cents' => $credit,
            'header_details_consistent' => $issues === [],
            'issues' => $issues,
            'ledger_provenance' => 'unverified_legacy_cv_may_have_jv_ledger_identity',
        ];
    }

    private function cents($amount): ?int
    {
        $value = (string) $amount;
        if (!preg_match('/^\\d{1,12}(?:\\.\\d{1,2})?$/D', $value)) {
            return null;
        }
        $parts = explode('.', $value);
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
