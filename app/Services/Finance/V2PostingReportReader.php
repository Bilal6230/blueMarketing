<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isolated V2 read adapter. Not wired to legacy report controllers.
 * Never links a financial document through overloaded ledgers.type_id.
 */
final class V2PostingReportReader
{
    private function guard(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['finance_posting_batches', 'finance_posting_lines',
                  'finance_posting_source_claims', 'project_head_subheads'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException('V2 report schema missing: ' . $table);
            }
        }
    }

    private function validDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('V2 report requires ISO date YYYY-MM-DD.');
        }
        return $date;
    }

    private function amountCents(mixed $amount): int
    {
        $value = (string) $amount;
        if (!preg_match('/^(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new \DomainException('V2_REPORT_INVALID_MONEY');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    /**
     * Each result contains a physical account/line/batch/source claim.
     * The caller must also authorize project access; this service does not
     * replace controller permission checks.
     */
    public function lines(int $projectId, ?string $from = null, ?string $through = null): array
    {
        $this->guard();
        if ($projectId < 1) {
            throw new \InvalidArgumentException('V2 report project ID required.');
        }
        $from = $this->validDate($from);
        $through = $this->validDate($through);
        if ($from !== null && $through !== null && $from > $through) {
            throw new \InvalidArgumentException('V2 report start date is after end date.');
        }
        // An inner join alone could silently hide an unclaimed or foreign
        // account posting. Fail instead of returning a misleading balance.
        $inconsistent = DB::table('finance_posting_lines as line')
            ->join('finance_posting_batches as batch', 'batch.id', '=', 'line.posting_batch_id')
            ->leftJoin('finance_posting_source_claims as claim',
                'claim.posting_batch_id', '=', 'batch.id')
            ->leftJoin('finance_posting_batches as original',
                'original.id', '=', 'batch.reversal_of_batch_id')
            ->leftJoin('finance_posting_source_claims as original_claim',
                'original_claim.posting_batch_id', '=', 'original.id')
            ->leftJoin('project_head_subheads as account',
                'account.id', '=', 'line.project_head_subhead_id')
            ->where('batch.project_id', $projectId)
            ->where(function ($query) use ($projectId) {
                $query->whereNull('account.id')
                    ->orWhere('account.project_id', '!=', $projectId)
                    ->orWhere(function ($normal) {
                        $normal->whereNull('batch.reversal_of_batch_id')
                            ->whereNull('claim.posting_batch_id');
                    })
                    ->orWhere(function ($reversal) use ($projectId) {
                        $reversal->whereNotNull('batch.reversal_of_batch_id')
                            ->where(function ($invalid) use ($projectId) {
                                $invalid->whereNull('original.id')
                                    ->orWhereNull('original_claim.posting_batch_id')
                                    ->orWhereNotNull('claim.posting_batch_id')
                                    ->orWhereNotNull('original.reversal_of_batch_id')
                                    ->orWhere('original.project_id', '!=', $projectId)
                                    ->orWhere('batch.voucher_series', '!=', 'REV');
                            });
                    });
            })->exists();
        if ($inconsistent) {
            throw new \DomainException('V2_REPORT_SOURCE_OR_PROJECT_INTEGRITY_FAILURE');
        }
        $emptyBatch = DB::table('finance_posting_batches as batch')
            ->leftJoin('finance_posting_lines as pl', 'pl.posting_batch_id', '=', 'batch.id')
            ->where('batch.project_id', $projectId)->whereNull('pl.id')->exists();
        if ($emptyBatch) {
            throw new \DomainException('V2_REPORT_EMPTY_POSTING_BATCH');
        }
        $q = DB::table('finance_posting_lines as line')
            ->join('finance_posting_batches as batch', 'batch.id', '=', 'line.posting_batch_id')
            ->leftJoin('finance_posting_source_claims as claim',
                'claim.posting_batch_id', '=', 'batch.id')
            ->leftJoin('finance_posting_batches as original',
                'original.id', '=', 'batch.reversal_of_batch_id')
            ->leftJoin('finance_posting_source_claims as original_claim',
                'original_claim.posting_batch_id', '=', 'original.id')
            ->join('project_head_subheads as account',
                'account.id', '=', 'line.project_head_subhead_id')
            ->where('batch.project_id', $projectId)
            ->where('account.project_id', $projectId);
        if ($from !== null) {
            $q->where('batch.effective_date', '>=', $from);
        }
        if ($through !== null) {
            $q->where('batch.effective_date', '<=', $through);
        }
        $lines = $q->orderBy('batch.effective_date')
            ->orderBy('batch.id')->orderBy('line.id')
            ->get([
                'line.id as posting_line_id',
                'line.posting_batch_id',
                'line.project_head_subhead_id',
                'line.legacy_ledger_id',
                'line.debit', 'line.credit', 'line.description',
                'batch.effective_date',
                'batch.voucher_series',
                'batch.voucher_number',
                'batch.reversal_of_batch_id',
                'claim.source_kind as direct_source_kind',
                'original_claim.source_kind as inherited_source_kind',
            ])->map(static function ($row): array {
                $line = (array) $row;
                $reversedId = $line['reversal_of_batch_id'];
                $line['is_reversal'] = $reversedId !== null;
                $line['source_kind'] = $line['is_reversal']
                    ? $line['inherited_source_kind'] : $line['direct_source_kind'];
                $line['source_batch_id'] = $line['is_reversal']
                    ? (int) $reversedId : (int) $line['posting_batch_id'];
                unset($line['direct_source_kind'], $line['inherited_source_kind']);
                return $line;
            })->all();

        $totals = [];
        foreach ($lines as $line) {
            $debit = $this->amountCents($line['debit']);
            $credit = $this->amountCents($line['credit']);
            if (($debit > 0) === ($credit > 0)) {
                throw new \DomainException('V2_REPORT_INVALID_POSTING_LINE');
            }
            $batchId = (int) $line['posting_batch_id'];
            $totals[$batchId] ??= ['debit' => 0, 'credit' => 0];
            $totals[$batchId]['debit'] += $debit;
            $totals[$batchId]['credit'] += $credit;
        }
        foreach ($totals as $total) {
            if ($total['debit'] < 1 || $total['debit'] !== $total['credit']) {
                throw new \DomainException('V2_REPORT_UNBALANCED_POSTING_BATCH');
            }
        }

        // A source-claim without its actual typed FK child is not sufficient
        // to provide a voucher URL or audited balance. Verify every batch in
        // seven bulk queries (not N+1), then expose the REAL source PK.
        $tables = [
            'journal' => ['finance_posting_journal_sources', 'journal_voucher_id'],
            'commission' => ['finance_posting_commission_sources', 'commision_voucher_id'],
            'stock_entry' => ['finance_posting_stock_entry_sources', 'stock_entry_id'],
            'stock_bill' => ['finance_posting_stock_bill_sources', 'stock_bill_id'],
            'cash' => ['finance_posting_cash_sources', 'customer_ledger_id'],
            'booking' => ['finance_posting_booking_sources', 'booking_voucher_id'],
            'labour' => ['finance_posting_labour_sources', 'labour_ledger_id'],
        ];
        $wanted = [];
        foreach ($lines as $line) {
            $kind = (string) $line['source_kind'];
            if (!isset($tables[$kind])) {
                throw new \DomainException('V2_REPORT_UNKNOWN_SOURCE_KIND');
            }
            $wanted[$kind][(int) $line['source_batch_id']] = true;
        }
        $actual = [];
        foreach ($wanted as $kind => $batchIds) {
            [$table, $idColumn] = $tables[$kind];
            $fetched = DB::table($table)
                ->whereIn('posting_batch_id', array_keys($batchIds))
                ->pluck($idColumn, 'posting_batch_id')
                ->all();
            if (count($fetched) !== count($batchIds)) {
                throw new \DomainException('V2_REPORT_MISSING_TYPED_SOURCE_LINK');
            }
            foreach ($fetched as $batchId => $sourceId) {
                $actual[(int) $batchId] = (int) $sourceId;
            }
        }
        foreach ($lines as &$line) {
            $line['source_id'] = $actual[(int) $line['source_batch_id']];
            unset($line['source_batch_id']);
        }
        unset($line);
        return $lines;
    }
}
