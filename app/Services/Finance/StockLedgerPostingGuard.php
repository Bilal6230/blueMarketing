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

        $customerLedger = DB::table('customer_ledger')->where('id', $customerLedgerIds->first())->first();
        if (!$customerLedger || (int) $customerLedger->project_id !== $projectId ||
            (string) $customerLedger->reference !== $reference ||
            (string) $customerLedger->transaction_type !== 'JV') {
            throw new \DomainException('STOCK_LEDGER_SOURCE_CONFLICT');
        }

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

    private function minorUnits($value): int
    {
        $text = trim((string) ($value ?? '0'));
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $text)) {
            throw new \DomainException('STOCK_LEDGER_INVALID_AMOUNT');
        }

        $negative = str_starts_with($text, '-');
        $parts = explode('.', ltrim($text, '-'));
        $minor = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');

        return $negative ? -$minor : $minor;
    }
}
