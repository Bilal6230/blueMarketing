<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class ProjectVoucherAllocator
{
    /**
     * Allocate a project/series number while holding the durable project lock.
     * Must execute inside the caller's posting transaction. Numbers from both
     * source and ledger tables remain reserved even after financial deactivation.
     */
    public function next(int $projectId, string $series): int
    {
        if (DB::transactionLevel() < 1) {
            throw new \DomainException('VOUCHER_ALLOCATION_REQUIRES_TRANSACTION');
        }
        if ($projectId < 1 || !preg_match('/^[A-Z]{2,4}$/D', $series)) {
            throw new \DomainException('INVALID_VOUCHER_ALLOCATION_SCOPE');
        }
        if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
            throw new \DomainException('VOUCHER_PROJECT_NOT_FOUND');
        }
        $sourceMax = (int) DB::table('customer_ledger')
            ->where('project_id', $projectId)->where('transaction_type', $series)
            ->max('type_id');
        $ledgerMax = (int) DB::table('ledgers as l')
            ->join('project_head_subheads as p', 'p.id', '=', 'l.project_head_subheads_id')
            ->where('p.project_id', $projectId)->where('l.type', $series)
            ->max('l.voucher_number');
        return max($sourceMax, $ledgerMax) + 1;
    }
}
