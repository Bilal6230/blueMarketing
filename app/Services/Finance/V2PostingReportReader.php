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
        return $q->orderBy('batch.effective_date')
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
    }
}
