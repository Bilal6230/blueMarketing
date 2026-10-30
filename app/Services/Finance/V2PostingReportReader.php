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
        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();
        if (!str_starts_with($database, 'bluemarketing_v2_') &&
            !($connection->getDriverName() === 'sqlite' && $database === ':memory:')) {
            throw new \RuntimeException('V2 reports refused outside isolated V2 database.');
        }
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
            ->leftJoin('project_head_subheads as account',
                'account.id', '=', 'line.project_head_subhead_id')
            ->where('batch.project_id', $projectId)
            ->where(function ($query) use ($projectId) {
                $query->whereNull('claim.posting_batch_id')
                    ->orWhereNull('account.id')
                    ->orWhere('account.project_id', '!=', $projectId);
            })->exists();
        if ($inconsistent) {
            throw new \DomainException('V2_REPORT_SOURCE_OR_PROJECT_INTEGRITY_FAILURE');
        }
        $q = DB::table('finance_posting_lines as line')
            ->join('finance_posting_batches as batch', 'batch.id', '=', 'line.posting_batch_id')
            ->join('finance_posting_source_claims as claim',
                'claim.posting_batch_id', '=', 'batch.id')
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
                'claim.source_kind',
            ])->map(static fn ($line): array => (array) $line)->all();

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
            $wanted[$kind][(int) $line['posting_batch_id']] = true;
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
            $line['source_id'] = $actual[(int) $line['posting_batch_id']];
        }
        unset($line);
        return $lines;
    }
}
