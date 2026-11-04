<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherAllocator
{
    /** Allocate a CustomerLedger type_id from its own retained sequence. */
    public function nextSourceId(int $projectId, string $series): int
    {
        if ($projectId < 1 || !preg_match('/^[A-Za-z]{2,4}$/D', $series)) {
            throw new \DomainException('INVALID_VOUCHER_ALLOCATION_SCOPE');
        }
        if (DB::transactionLevel() > 0
            && !DB::table('projects')->where('id', $projectId)->lockForUpdate()->first()) {
            throw new \DomainException('VOUCHER_PROJECT_NOT_FOUND');
        }

        $sourceMax = (int) DB::table('customer_ledger')->where('project_id', $projectId)
            ->where('transaction_type', $series)->max('type_id');

        // Source IDs and printed ledger voucher numbers are separate legacy
        // fields, so retain them in separate counter namespaces.
        $counterType = $series.':ID';
        if (DB::transactionLevel() > 0 && Schema::hasTable('ledger_voucher_counters')) {
            $counter = DB::table('ledger_voucher_counters')->where('project_id', $projectId)
                ->where('voucher_type', $counterType)->lockForUpdate()->first();
            $next = max((int) ($counter->last_number ?? 0), $sourceMax) + 1;
            $now = now();
            if ($counter) {
                DB::table('ledger_voucher_counters')->where('project_id', $projectId)->where('voucher_type', $counterType)
                    ->update(['last_number' => $next, 'updated_at' => $now]);
            } else {
                DB::table('ledger_voucher_counters')->insert([
                    'project_id' => $projectId, 'voucher_type' => $counterType, 'last_number' => $next,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return $next;
        }

        return $sourceMax + 1;
    }

    /** Allocate the legacy ledgers.type_id row identity for this project/series. */
    public function nextLedgerId(int $projectId, string $series): int
    {
        if ($projectId < 1 || !preg_match('/^[A-Z]{2,4}$/D', $series)) {
            throw new \DomainException('INVALID_VOUCHER_ALLOCATION_SCOPE');
        }
        if (DB::transactionLevel() > 0
            && !DB::table('projects')->where('id', $projectId)->lockForUpdate()->first()) {
            throw new \DomainException('VOUCHER_PROJECT_NOT_FOUND');
        }

        $rowMax = (int) DB::table('ledgers as l')->join('project_head_subheads as p', 'p.id', '=', 'l.project_head_subheads_id')
            ->where('p.project_id', $projectId)->where('l.type', $series)->max('l.type_id');
        $counterType = $series.':ROW';
        if (DB::transactionLevel() > 0 && Schema::hasTable('ledger_voucher_counters')) {
            $counter = DB::table('ledger_voucher_counters')->where('project_id', $projectId)
                ->where('voucher_type', $counterType)->lockForUpdate()->first();
            $next = max((int) ($counter->last_number ?? 0), $rowMax) + 1;
            $now = now();
            if ($counter) {
                DB::table('ledger_voucher_counters')->where('project_id', $projectId)->where('voucher_type', $counterType)
                    ->update(['last_number' => $next, 'updated_at' => $now]);
            } else {
                DB::table('ledger_voucher_counters')->insert([
                    'project_id' => $projectId, 'voucher_type' => $counterType, 'last_number' => $next,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return $next;
        }

        return $rowMax + 1;
    }

    /**
     * Allocate a project/series number while holding the durable project lock.
     * Must execute inside the caller's posting transaction. Numbers from both
     * source and ledger tables remain reserved even after financial deactivation.
     */
    public function next(int $projectId, string $series, int $additionalMax = 0): int
    {
        if (DB::transactionLevel() < 1) {
            throw new \DomainException('VOUCHER_ALLOCATION_REQUIRES_TRANSACTION');
        }
        if ($projectId < 1 || !preg_match('/^[A-Z]{2,4}$/D', $series)) {
            throw new \DomainException('INVALID_VOUCHER_ALLOCATION_SCOPE');
        }
        if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->first()) {
            throw new \DomainException('VOUCHER_PROJECT_NOT_FOUND');
        }
        $sourceMax = (int) DB::table('customer_ledger')
            ->where('project_id', $projectId)->where('transaction_type', $series)
            ->max('type_id');
        $ledgerMax = (int) DB::table('ledgers as l')
            ->join('project_head_subheads as p', 'p.id', '=', 'l.project_head_subheads_id')
            ->where('p.project_id', $projectId)->where('l.type', $series)
            ->max('l.voucher_number');
        $highestExisting = max($sourceMax, $ledgerMax, $additionalMax);

        // Share the retained counter with legacy getVocuherNumber() callers.
        // This keeps all migrated writers in one project/type lock domain and
        // never reuses a number when a source row is missing from ledgers.
        if (Schema::hasTable('ledger_voucher_counters')) {
            $counter = DB::table('ledger_voucher_counters')->where('project_id', $projectId)
                ->where('voucher_type', $series)->lockForUpdate()->first();
            $next = max((int) ($counter->last_number ?? 0), $highestExisting) + 1;
            $now = now();
            if ($counter) {
                DB::table('ledger_voucher_counters')->where('project_id', $projectId)->where('voucher_type', $series)
                    ->update(['last_number' => $next, 'updated_at' => $now]);
            } else {
                DB::table('ledger_voucher_counters')->insert([
                    'project_id' => $projectId, 'voucher_type' => $series, 'last_number' => $next,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return $next;
        }

        return $highestExisting + 1;
    }
    /**
     * JV header numbers are shared by general and labour journal writers.
     * Journal headers remain the canonical JV sequence; ledger numbers that
     * survived a damaged/missing header must also reserve their number.
     */
    public function nextJournal(int $projectId): int
    {
        $headerMax = (int) DB::table('journal_vouchers')
            ->where('project_id', $projectId)->where('type', 'JV')
            ->max('voucher_number');
        return $this->next($projectId, 'JV', $headerMax);
    }

    /** Sales voucher headers reserve their identifiers even without ledger legs. */
    public function nextSalesVoucher(int $projectId): int
    {
        $headerMax = (int) DB::table('journal_vouchers')
            ->where('project_id', $projectId)->where('type', 'SV')
            ->max('voucher_number');

        return $this->next($projectId, 'SV', $headerMax);
    }
    /**
     * PPR has an additional persisted BookingVoucher display identifier.
     * Preserve those numbers even when the original customer source or ledger
     * has been removed; never reuse historical receipt identities.
     */
    public function nextBookingReceipt(int $projectId): int
    {
        $bookingMax = (int) DB::table('booking_vouchers')
            ->where('project_id', $projectId)
            ->where('voucher_series', 'PPR')
            ->max('voucher_number');
        return $this->next($projectId, 'PPR', $bookingMax);
    }
}
