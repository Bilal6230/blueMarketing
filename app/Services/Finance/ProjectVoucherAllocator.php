<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherAllocator
{
    /**
     * Read-only preview. The final number is allocated again under the project
     * lock at save time. Preview must never consume a sequence.
     */
    public function preview(int $projectId, string $series): int
    {
        if (!preg_match('/^[A-Z]{2,4}$/D', $series)) {
            throw new \DomainException('INVALID_VOUCHER_ALLOCATION_SCOPE');
        }
        // Preview is used while the project selector may still be unset.
        // Avoid rendering an exception page; actual saves still require a valid project.
        if ($projectId < 1) {
            return 1;
        }
        $counter = Schema::hasTable('ledger_voucher_counters')
            ? (int) DB::table('ledger_voucher_counters')->where('project_id', $projectId)
                ->where('voucher_type', $series)->value('last_number') : 0;
        return max(in_array($series,['JV','PPR'],true) ? 0 : $counter, $this->highestExisting($projectId, $series)) + 1;
    }

    private function highestExisting(int $projectId, string $series): int
    {
        $max = 0;
        $numeric = function (string $table, string $column, array $scope = []) use ($projectId, &$max): void {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) return;
            $query = DB::table($table)->where('project_id', $projectId);
            foreach ($scope as $key => $value) $query->where($key, $value);
            $max = max($max, (int) $query->max($column));
        };
        $text = function (string $table, string $column, array $scope = []) use ($projectId, $series, &$max): void {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) return;
            $query = DB::table($table)->where('project_id', $projectId);
            foreach ($scope as $key => $value) $query->where($key, $value);
            foreach ($query->pluck($column) as $value) {
                if (preg_match('/^(?:'.preg_quote($series, '/').'-)?([0-9]{1,15})$/D', (string) $value, $match)) {
                    $max = max($max, (int) $match[1]);
                }
            }
        };
        // BOR/BOP are independent bank voucher series. Historical CR/CP-derived
        // source and ledger IDs must never inflate their displayed sequence.
        // JV also uses its journal headers, not unrelated typed ledger IDs.
        if (!in_array($series, ['BOR','BOP','JV','PPR'], true)) {
            $numeric('customer_ledger', 'type_id', ['transaction_type' => $series]);
        }
        if (!in_array($series, ['BOR','BOP','JV','PPR'], true) && Schema::hasTable('ledgers') && Schema::hasTable('project_head_subheads')) {
            $max = max($max, (int) DB::table('ledgers as l')
                ->join('project_head_subheads as p', 'p.id', '=', 'l.project_head_subheads_id')
                ->where('p.project_id', $projectId)->where('l.type', $series)->max('l.voucher_number'));
        }
        if (!in_array($series, ['JV','PPR'], true)) {
            $numeric('finance_posting_batches', 'voucher_number', ['voucher_series' => $series]);
            $text('project_voucher_display_numbers', 'display_number', ['series' => $series]);
        }
        if ($series === 'JV') $max = max($max, $this->journalSequenceBaseline($projectId));
        if ($series === 'SV') $numeric('journal_vouchers', 'voucher_number', ['type' => $series]);
        if ($series === 'CV') $text('commision_vouchers', 'voucher_number');
        if ($series === 'PPR') $max = max($max, $this->plotReceiptSequenceBaseline($projectId));
        if ($series === 'PP') $text('finance_plot_purchases', 'purchase_number');
        if ($series === 'PP') {
            $text('finance_plot_approval_drafts', 'source_number', ['voucher_type' => $series]);
        }
        if (in_array($series, ['BD','BW'], true)) {
            $numeric('finance_bank_transfer_approval_drafts', 'voucher_number',
                ['transfer_kind' => $series === 'BD' ? 'deposit' : 'withdrawal']);
            // Preserve previously displayed fallback batch IDs for old unnumbered transfers.
            if (Schema::hasTable('finance_posting_batches')) {
                $max = max($max, (int) DB::table('finance_posting_batches')
                    ->where('project_id', $projectId)->where('voucher_series', $series)
                    ->whereNull('voucher_number')->max('id'));
            }
        }
        return $max;
    }

    /**
     * Preserve the historical JV run when earlier fiscal-year imports or
     * erroneous ledger-only numbers jumped hundreds ahead of journal headers.
     * All existing headers remain untouched. A new number must be unused.
     */
    private function journalSequenceBaseline(int $projectId): int
    {
        if (!Schema::hasTable('journal_vouchers')) return 0;
        $numbers = DB::table('journal_vouchers')->where('project_id', $projectId)
            ->where('type', 'JV')->orderBy('voucher_number')
            ->pluck('voucher_number')->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)->unique()->values();
        $baseline = 0;
        foreach ($numbers as $number) {
            if ($baseline > 0 && $number - $baseline > 100) break;
            $baseline = $number;
        }
        return $baseline;
    }

    /** Booking voucher PPR history is the canonical original receipt series. */
    private function plotReceiptSequenceBaseline(int $projectId): int
    {
        $base = Schema::hasTable('booking_vouchers')
            ? (int) DB::table('booking_vouchers')->where('project_id',$projectId)
                ->where('voucher_series','PPR')->max('voucher_number') : 0;
        if (!Schema::hasTable('finance_plot_receipts')) return $base;
        $numbers = DB::table('finance_plot_receipts')->where('project_id',$projectId)
            ->pluck('receipt_number')->map(function($value) {
                return preg_match('/^PPR-([1-9][0-9]*)$/D',(string)$value,$m) ? (int)$m[1] : 0;
            })->filter()->sort()->values();
        foreach ($numbers as $number) {
            if ($base > 0 && $number-$base > 100) break;
            $base=max($base,$number);
        }
        return $base;
    }

    /** Allocate a CustomerLedger type_id from its own retained sequence. */
    public function nextSourceId(int $projectId, string $series): int
    {
        if (in_array($series, ['BO','BR'], true)) {
            VoucherAvailability::enforce($projectId, $series);
        }
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
        // BO/BR have no standalone page. Their creation must still respect the
        // project's active-state setting even through internal bank workflows.
        if (in_array($series, ['BO','BR'], true)) {
            VoucherAvailability::enforce($projectId, $series);
        }
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
        $highestExisting = max($this->highestExisting($projectId, $series),
            in_array($series,['JV','PPR'],true) ? 0 : $additionalMax);

        // Share the retained counter with legacy getVocuherNumber() callers.
        // This keeps all migrated writers in one project/type lock domain and
        // never reuses a number when a source row is missing from ledgers.
        if (Schema::hasTable('ledger_voucher_counters')) {
            $counter = DB::table('ledger_voucher_counters')->where('project_id', $projectId)
                ->where('voucher_type', $series)->lockForUpdate()->first();
            $next = max(in_array($series,['JV','PPR'],true) ? 0 : (int) ($counter->last_number ?? 0), $highestExisting) + 1;
            if ($series === 'JV') {
                while (DB::table('journal_vouchers')->where('project_id', $projectId)
                    ->where('type', 'JV')->where('voucher_number', $next)->exists()) $next++;
            }
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

        $next = $highestExisting + 1;
        if ($series === 'JV') {
            while (DB::table('journal_vouchers')->where('project_id', $projectId)
                ->where('type','JV')->where('voucher_number',$next)->exists()) $next++;
        }
        return $next;
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
