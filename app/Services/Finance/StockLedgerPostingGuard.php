<?php

namespace App\Services\Finance;

use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Fail-closed check for existing stock journal legs.
 * This does not assign historical JV ownership or repair any rows.
 */
class StockLedgerPostingGuard
{
    /**
     * @param array<int, array{project_head_subheads_id:int, amount_in:mixed, amount_out:mixed}> $expectedLegs
     */
    public function alreadyPosted(string $reference, int $projectId, string $businessDate, array $expectedLegs): bool
    {
        if ($projectId <= 0 || count($expectedLegs) !== 2) {
            throw new \DomainException('STOCK_LEDGER_INVALID_EXPECTATION');
        }

        if (!preg_match('/^STOCK-(?:ENTRY|BILL):[1-9][0-9]*$/D', $reference) ||
            !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $businessDate)) {
            throw new \DomainException('STOCK_LEDGER_INVALID_SOURCE_IDENTITY_OR_DATE');
        }

        // Serialize stock financial postings across web and mobile for the
        // selected project. Both callers use an enclosing DB transaction;
        // the shared project row lock remains held through both leg INSERTs.
        // Locking only existing ledger legs cannot protect a first insert.
        $project = DB::table('projects')
            ->where('id', $projectId)
            ->lockForUpdate()
            ->first(['id']);

        if (!$project) {
            throw new \DomainException('STOCK_LEDGER_PROJECT_MISSING');
        }

        // Validate BOTH expected accounts even for a first-time posting.
        // A foreign/missing account cannot become a new financial leg.
        foreach ($expectedLegs as $leg) {
            $accountId = (int) ($leg['project_head_subheads_id'] ?? 0);
            if ($accountId <= 0 ||
                (int) DB::table('project_head_subheads')->where('id', $accountId)->value('project_id') !== $projectId) {
                throw new \DomainException('STOCK_LEDGER_EXPECTED_ACCOUNT_PROJECT_CONFLICT');
            }
            $this->minorUnits($leg['amount_in'] ?? null);
            $this->minorUnits($leg['amount_out'] ?? null);
        }

        // The two stock legs must be balanced, positive and on distinct accounts.
        // Without this, a malformed caller could post a balanced-looking JV with
        // negative debit/credit values or both legs on one account.
        $debit = 0;
        $credit = 0;
        $accountIds = [];
        foreach ($expectedLegs as $leg) {
            $in = $this->minorUnits($leg['amount_in'] ?? 0);
            $out = $this->minorUnits($leg['amount_out'] ?? 0);
            if ($in < 0 || $out < 0 || ($in > 0) === ($out > 0)) {
                throw new \DomainException('STOCK_LEDGER_INVALID_DEBIT_CREDIT');
            }
            $debit += $in;
            $credit += $out;
            $accountIds[] = (int) $leg['project_head_subheads_id'];
        }
        if ($debit <= 0 || $debit !== $credit || count(array_unique($accountIds)) !== 2) {
            throw new \DomainException('STOCK_LEDGER_UNBALANCED_OR_SAME_ACCOUNT');
        }

        // Lock matching postings within the caller's source transaction.
        // Never scope the lookup to the project: a foreign-project collision
        // must be detected rather than hidden.
        $existing = Ledger::query()
            ->where('type', 'JV')
            ->where('reference', $reference)
            ->lockForUpdate()
            ->get();

        if ($existing->isEmpty()) {
            // A failed earlier posting may have left a source customer-ledger
            // row behind. Validate it before permitting a fresh ledger posting.
            // Checking without a project filter also exposes cross-project
            // reference collisions rather than hiding them.
            $sources = DB::table('customer_ledger')
                ->where('transaction_type', 'JV')
                ->where('reference', $reference)
                ->lockForUpdate()
                ->get();

            if ($sources->count() > 1) {
                throw new \DomainException('STOCK_CUSTOMER_LEDGER_DUPLICATE_SOURCE');
            }

            if ($sources->count() === 1) {
                $source = $sources->first();
                if ((int) $source->project_id !== $projectId ||
                    (int) $source->is_active !== 1 ||
                    substr((string) $source->date, 0, 10) !== substr($businessDate, 0, 10)) {
                    throw new \DomainException('STOCK_CUSTOMER_LEDGER_SOURCE_CONFLICT');
                }
            }

            if ($sources->count() === 1) {
                $this->assertSourceAmount($sources->first(), $debit);
            }

            return false;
        }

        if ($existing->count() !== 2) {
            throw new \DomainException('STOCK_LEDGER_INCOMPLETE_OR_DUPLICATE');
        }

        $voucherNumbers = $existing->pluck('voucher_number')->unique();
        $sourceIds = $existing->pluck('type_id')->unique();
        $customerLedgerIds = $existing->pluck('customer_ledger_id')->unique();

        if ($voucherNumbers->count() !== 1 || $sourceIds->count() !== 1 ||
            $customerLedgerIds->count() !== 1 || $customerLedgerIds->first() === null) {
            throw new \DomainException('STOCK_LEDGER_IDENTITY_CONFLICT');
        }

        // Check every source sharing the stock reference, not just the one
        // linked by existing legs. Hidden duplicate sources make ownership
        // ambiguous even when the two visible legs are otherwise correct.
        $matchingSources = DB::table('customer_ledger')
            ->where('transaction_type', 'JV')
            ->where('reference', $reference)
            ->lockForUpdate()
            ->get();
        if ($matchingSources->count() !== 1 ||
            (int) $matchingSources->first()->id !== (int) $customerLedgerIds->first()) {
            throw new \DomainException('STOCK_LEDGER_DUPLICATE_OR_MISSING_SOURCE');
        }

        $customerLedger = DB::table('customer_ledger')->where('id', $customerLedgerIds->first())->first();
        if (!$customerLedger || (int) $customerLedger->project_id !== $projectId ||
            (string) $customerLedger->reference !== $reference ||
            (string) $customerLedger->transaction_type !== 'JV') {
            throw new \DomainException('STOCK_LEDGER_SOURCE_CONFLICT');
        }

        $this->assertSourceAmount($customerLedger, $debit);

        // A linked customer-ledger record cannot silently disagree with the stock
        // posting's business date or be marked inactive.
        if (substr((string) $customerLedger->date, 0, 10) !== substr($businessDate, 0, 10) ||
            (int) $customerLedger->is_active !== 1) {
            throw new \DomainException('STOCK_CUSTOMER_LEDGER_DATE_OR_STATUS_CONFLICT');
        }

        $unused = array_values($expectedLegs);
        foreach ($existing as $ledger) {
            $owner = DB::table('project_head_subheads')
                ->where('id', $ledger->project_head_subheads_id)
                ->value('project_id');

            if ((int) $owner !== $projectId || (int) $ledger->is_active !== 1 ||
                substr((string) $ledger->date, 0, 10) !== substr($businessDate, 0, 10)) {
                throw new \DomainException('STOCK_LEDGER_PROJECT_DATE_OR_STATUS_CONFLICT');
            }

            $matched = false;
            foreach ($unused as $key => $leg) {
                if ((int) $ledger->project_head_subheads_id === (int) $leg['project_head_subheads_id'] &&
                    $this->minorUnits($ledger->amount_in) === $this->minorUnits($leg['amount_in']) &&
                    $this->minorUnits($ledger->amount_out) === $this->minorUnits($leg['amount_out'])) {
                    unset($unused[$key]);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                throw new \DomainException('STOCK_LEDGER_AMOUNT_OR_ACCOUNT_CONFLICT');
            }
        }

        return $unused === [];
    }

    private function assertSourceAmount(object $source, int $expectedMinor): void
    {
        $sourceIn = $this->minorUnits($source->amount_in ?? 0);
        $sourceOut = $this->minorUnits($source->amount_out ?? 0);
        // Stock IN is stored on the source out-side; Stock OUT on the
        // source in-side. Exactly one source amount must match the journal.
        if ($sourceIn < 0 || $sourceOut < 0 ||
            !(($sourceIn === $expectedMinor && $sourceOut === 0) ||
              ($sourceOut === $expectedMinor && $sourceIn === 0))) {
            throw new \DomainException('STOCK_CUSTOMER_LEDGER_AMOUNT_CONFLICT');
        }
    }

    private function minorUnits($value): int
    {
        $text = trim((string) ($value ?? '0'));
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $text)) {
            throw new \DomainException('STOCK_LEDGER_INVALID_AMOUNT');
        }

        $negative = str_starts_with($text, '-');
        $parts = explode('.', ltrim($text, '-'));
        // Avoid integer overflow or silent saturation of untrusted amounts.
        $whole = ltrim($parts[0], '0');
        $whole = $whole === '' ? '0' : $whole;
        $cents = (int) str_pad($parts[1] ?? '', 2, '0');
        if (strlen($whole) > strlen((string) intdiv(PHP_INT_MAX, 100)) ||
            (strlen($whole) === strlen((string) intdiv(PHP_INT_MAX, 100)) &&
             strcmp($whole, (string) intdiv(PHP_INT_MAX, 100)) > 0)) {
            throw new \DomainException('STOCK_LEDGER_AMOUNT_OVERFLOW');
        }
        $minor = ((int) $whole * 100) + $cents;
        if ($minor < 0) {
            throw new \DomainException('STOCK_LEDGER_AMOUNT_OVERFLOW');
        }

        return $negative ? -$minor : $minor;
    }
}
