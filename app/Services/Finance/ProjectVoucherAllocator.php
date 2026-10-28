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
    /**
     * JV header numbers are shared by general and labour journal writers.
     * Journal headers remain the canonical JV sequence; ledger numbers that
     * survived a damaged/missing header must also reserve their number.
     */
    public function nextJournal(int $projectId): int
    {
        $ledgerNext = $this->next($projectId, 'JV');
        $headerMax = (int) DB::table('journal_vouchers')
            ->where('project_id', $projectId)->where('type', 'JV')
            ->max('voucher_number');
        return max($ledgerNext, $headerMax + 1);
    }
    /**
     * PPR has an additional persisted BookingVoucher display identifier.
     * Preserve those numbers even when the original customer source or ledger
     * has been removed; never reuse historical receipt identities.
     */
    public function nextBookingReceipt(int $projectId): int
    {
        $next = $this->next($projectId, 'PPR');
        $bookingMax = (int) DB::table('booking_vouchers')
            ->where('project_id', $projectId)
            ->where('voucher_series', 'PPR')
            ->max('voucher_number');
        return max($next, $bookingMax + 1);
    }
}
