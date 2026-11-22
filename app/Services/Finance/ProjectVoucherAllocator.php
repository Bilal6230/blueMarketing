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
        return max($counter, $this->highestExisting($projectId, $series)) + 1;
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
        $numeric('customer_ledger', 'type_id', ['transaction_type' => $series]);
        if (Schema::hasTable('ledgers') && Schema::hasTable('project_head_subheads')) {
            $max = max($max, (int) DB::table('ledgers as l')
                ->join('project_head_subheads as p', 'p.id', '=', 'l.project_head_subheads_id')
                ->where('p.project_id', $projectId)->where('l.type', $series)->max('l.voucher_number'));
        }
        $numeric('finance_posting_batches', 'voucher_number', ['voucher_series' => $series]);
        $text('project_voucher_display_numbers', 'display_number', ['series' => $series]);
        if (in_array($series, ['JV','SV'], true)) $numeric('journal_vouchers', 'voucher_number', ['type' => $series]);
        if ($series === 'CV') $text('commision_vouchers', 'voucher_number');
        if ($series === 'PPR') {
            $numeric('booking_vouchers', 'voucher_number', ['voucher_series' => 'PPR']);
            $numeric('finance_posting_batches', 'voucher_number', ['voucher_series' => 'PR']);
            $text('finance_plot_receipts', 'receipt_number');
        }
        if ($series === 'PP') $text('finance_plot_purchases', 'purchase_number');
        if (in_array($series, ['PPR','PP'], true)) {
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
        $highestExisting = max($this->highestExisting($projectId, $series), $additionalMax);

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
