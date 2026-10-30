<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approved-account allocation for V2 booking/labour/stock documents.
 * Does NOT derive accounting accounts from source type_id, party, or site.
 * Stock sources require a separate, authenticated project approval because
 * physical stock tables have no project_id.
 */
final class V2ApprovedSourcePostingWriter
{
    private const SOURCES = [
        'booking' => ['booking_vouchers', 'finance_posting_booking_sources', 'booking_voucher_id'],
        'labour' => ['labour_ledgers', 'finance_posting_labour_sources', 'labour_ledger_id'],
        'stock_entry' => ['stock_entries', 'finance_posting_stock_entry_sources', 'stock_entry_id'],
        'stock_bill' => ['stock_bills', 'finance_posting_stock_bill_sources', 'stock_bill_id'],
    ];

    private function cents(mixed $v): int
    {
        $s = (string) $v;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $s, $m)) {
            throw new \DomainException('V2_APPROVED_SOURCE_INVALID_AMOUNT');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function add(int $a, int $b): int
    {
        if ($b > 0 && $a > PHP_INT_MAX - $b) {
            throw new \DomainException('V2_APPROVED_SOURCE_AMOUNT_OVERFLOW');
        }
        return $a + $b;
    }

    public function post(
        int $projectId,
        string $kind,
        int $sourceId,
        string $requestKey,
        array $approvedAllocation,
        ?int $actorId = null
    ): int {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!isset(self::SOURCES[$kind]) || $projectId < 1 || $sourceId < 1
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey)
            || count($approvedAllocation) < 2) {
            throw new \DomainException('V2_APPROVED_SOURCE_IDENTITY_INVALID');
        }
        [$table, $typedTable, $typedFk] = self::SOURCES[$kind];
        foreach (['projects', 'project_head_subheads', $table,
            'finance_posting_batches', 'finance_posting_lines',
            'finance_posting_source_claims', $typedTable] as $required) {
            if (!Schema::hasTable($required)) {
                throw new \RuntimeException('V2_APPROVED_SOURCE_SCHEMA_MISSING: ' . $required);
            }
        }

        return DB::transaction(function () use (
            $projectId, $kind, $sourceId, $requestKey, $approvedAllocation,
            $actorId, $table, $typedTable, $typedFk
        ): int {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_APPROVED_SOURCE_PROJECT_MISSING');
            }
            $source = DB::table($table)->where('id', $sourceId)->lockForUpdate()->first();
            if (!$source) {
                throw new \DomainException('V2_APPROVED_SOURCE_NOT_FOUND');
            }
            $sourceDescriptor = null;
            $total = 0;
            $date = '';
            $series = '';
            $number = null;

            if ($kind === 'booking') {
                if ((int) $source->project_id !== $projectId
                    || (int) $source->is_active !== 1 || (int) $source->is_approve !== 1) {
                    throw new \DomainException('V2_BOOKING_SOURCE_NOT_APPROVED_OR_PROJECT');
                }
                $total = $this->cents($source->amount);
                $date = (string) $source->receipt_date;
                $series = (string) $source->voucher_series;
                $number = $source->voucher_number;
                $sourceDescriptor = [
                    'receipt_date' => $date, 'series' => $series,
                    'source_number' => $number,
                    'amount' => $this->money($total),
                    'customer_ledger_id' => $source->customer_ledger_id,
                    'ledger_id' => $source->ledger_id,
                ];
                if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/D', $series)) {
                    throw new \DomainException('V2_BOOKING_SERIES_INVALID');
                }
                if ($source->customer_ledger_id !== null &&
                    !DB::table('customer_ledger')->where('id', $source->customer_ledger_id)
                        ->where('project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_BOOKING_CROSS_PROJECT_SOURCE');
                }
                if ($source->customer_ledger_id !== null
                    && DB::table('finance_posting_cash_sources')
                        ->where('customer_ledger_id', $source->customer_ledger_id)->exists()) {
                    throw new \DomainException('V2_BOOKING_LINKED_CASH_ALREADY_POSTED');
                }
                if ($source->ledger_id !== null
                    && !DB::table('ledgers as original_ledger')
                        ->join('project_head_subheads as linked_account',
                            'linked_account.id', '=', 'original_ledger.project_head_subheads_id')
                        ->where('original_ledger.id', $source->ledger_id)
                        ->where('linked_account.project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_BOOKING_LINKED_LEDGER_CROSS_PROJECT');
                }
            } elseif ($kind === 'labour') {
                if ((int) $source->project_id !== $projectId
                    || !DB::table('labours')->where('id', $source->labour_id)
                        ->where('project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_LABOUR_PROJECT_SOURCE_MISMATCH');
                }
                $total = $this->cents($source->amount);
                $date = (string) $source->date;
                $series = 'LAB';
                $number = $sourceId;
                $sourceDescriptor = [
                    'labour_id' => (int) $source->labour_id,
                    'date' => $date, 'amount' => $this->money($total),
                    'details' => (string) $source->details,
                ];
            } else {
                $snapshot = (new V2StockSourceSnapshot())->read($kind, $sourceId);
                $approvalTable = $kind === 'stock_entry'
                    ? 'finance_stock_entry_project_approvals'
                    : 'finance_stock_bill_project_approvals';
                if (!Schema::hasTable($approvalTable)) {
                    throw new \DomainException('V2_STOCK_PROJECT_APPROVAL_SCHEMA_REQUIRED');
                }
                $approval = DB::table($approvalTable)
                    ->where($typedFk, $sourceId)
                    ->where('project_id', $projectId)
                    ->lockForUpdate()->first();
                if (!$approval || !$approval->approved_at
                    || !hash_equals((string) $approval->source_sha256, $snapshot['fingerprint'])) {
                    throw new \DomainException('V2_STOCK_PROJECT_APPROVAL_MISSING_OR_STALE');
                }
                $total = $snapshot['amount_cents'];
                $date = $snapshot['date'];
                $series = $snapshot['series'];
                $sourceDescriptor = [
                    'snapshot' => $snapshot['fingerprint'],
                    'approval_ref' => $approval->evidence_reference,
                    'approved_by' => (int) $approval->approved_by,
                ];
            }
            $calendar = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($total < 1 || !$calendar || $calendar->format('Y-m-d') !== $date) {
                throw new \DomainException('V2_APPROVED_SOURCE_DATE_OR_TOTAL_INVALID');
            }

            $lines = [];
            $debit = 0;
            $credit = 0;
            foreach ($approvedAllocation as $leg) {
                if (!is_array($leg)
                    || !isset($leg['account_id'], $leg['debit'], $leg['credit'])) {
                    throw new \DomainException('V2_APPROVED_SOURCE_ALLOCATION_INVALID');
                }
                $rawId = $leg['account_id'];
                if ((!is_int($rawId) && !is_string($rawId))
                    || !preg_match('/^[1-9][0-9]*$/D', (string) $rawId)
                    || (string) (int) $rawId !== (string) $rawId) {
                    throw new \DomainException('V2_APPROVED_SOURCE_ACCOUNT_INVALID');
                }
                $account = (int) $rawId;
                if (!DB::table('project_head_subheads')
                    ->where('id', $account)->where('project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_APPROVED_SOURCE_CROSS_PROJECT_ACCOUNT');
                }
                $dr = $this->cents($leg['debit']);
                $cr = $this->cents($leg['credit']);
                if (($dr > 0) === ($cr > 0)) {
                    throw new \DomainException('V2_APPROVED_SOURCE_SINGLE_SIDED_LEG_REQUIRED');
                }
                $debit = $this->add($debit, $dr);
                $credit = $this->add($credit, $cr);
                $description = $leg['description'] ?? null;
                if ($description !== null
                    && (!is_string($description) || strlen($description) > 1000)) {
                    throw new \DomainException('V2_APPROVED_SOURCE_DESCRIPTION_INVALID');
                }
                $lines[] = [
                    'account_id' => $account,
                    'debit' => $this->money($dr),
                    'credit' => $this->money($cr),
                    'description' => $description,
                ];
            }
            if ($debit !== $total || $credit !== $total) {
                throw new \DomainException('V2_APPROVED_SOURCE_ALLOCATION_NOT_BALANCED');
            }
            $fingerprint = hash('sha256', json_encode([
                'kind' => $kind, 'project' => $projectId,
                'source_id' => $sourceId,
                'descriptor' => $sourceDescriptor,
                'effective_date' => $date, 'series' => $series,
                'voucher_number' => $number, 'lines' => $lines,
            ], JSON_THROW_ON_ERROR));
            $existing = DB::table('finance_posting_batches')
                ->where('project_id', $projectId)->where('request_key', $requestKey)
                ->lockForUpdate()->first();
            if ($existing) {
                $correctClaim = DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id', $existing->id)
                    ->where('source_kind', $kind)->exists();
                $correctSource = DB::table($typedTable)
                    ->where('posting_batch_id', $existing->id)
                    ->where($typedFk, $sourceId)->exists();
                if (!$correctClaim || !$correctSource
                    || (string) $existing->voucher_series !== $series
                    || (string) ($existing->voucher_number ?? '') !== (string) ($number ?? '')
                    || (string) $existing->effective_date !== $date
                    || $existing->reversal_of_batch_id !== null
                    || !hash_equals((string) $existing->payload_sha256, $fingerprint)) {
                    throw new \DomainException('V2_APPROVED_SOURCE_REPLAY_CONFLICT');
                }
                $persisted = DB::table('finance_posting_lines')
                    ->where('posting_batch_id', $existing->id)
                    ->get(['project_head_subhead_id', 'debit', 'credit', 'description']);
                if ($persisted->count() !== count($lines)) {
                    throw new \DomainException('V2_APPROVED_SOURCE_REPLAY_TAMPERED');
                }
                $target = [];
                foreach ($lines as $leg) {
                    $target[] = json_encode($leg, JSON_THROW_ON_ERROR);
                }
                $actual = [];
                foreach ($persisted as $leg) {
                    $actual[] = json_encode([
                        'account_id' => (int) $leg->project_head_subhead_id,
                        'debit' => $this->money($this->cents($leg->debit)),
                        'credit' => $this->money($this->cents($leg->credit)),
                        'description' => $leg->description,
                    ], JSON_THROW_ON_ERROR);
                }
                sort($target);
                sort($actual);
                if ($actual !== $target) {
                    throw new \DomainException('V2_APPROVED_SOURCE_REPLAY_TAMPERED');
                }
                return (int) $existing->id;
            }
            $batch = DB::table('finance_posting_batches')->insertGetId([
                'project_id' => $projectId, 'voucher_series' => $series,
                'voucher_number' => $number,
                'request_key' => $requestKey, 'payload_sha256' => $fingerprint,
                'effective_date' => $date, 'posted_by' => $actorId,
                'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('finance_posting_source_claims')->insert([
                'posting_batch_id' => $batch, 'source_kind' => $kind,
            ]);
            DB::table($typedTable)->insert([
                'posting_batch_id' => $batch, $typedFk => $sourceId,
            ]);
            foreach ($lines as $leg) {
                DB::table('finance_posting_lines')->insert([
                    'posting_batch_id' => $batch,
                    'project_head_subhead_id' => $leg['account_id'],
                    'debit' => $leg['debit'], 'credit' => $leg['credit'],
                    'description' => $leg['description'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return (int) $batch;
        }, 3);
    }
}
