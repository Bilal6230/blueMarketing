<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isolated V2-only immutable reversal: never updates or deletes original lines.
 * Reversal batches link to the original batch by a real FK, and inherit the
 * source identity through that original batch (never a reused type_id).
 */
final class V2PostingReversalWriter
{
    private function cents(mixed $amount): int
    {
        $value = (string) $amount;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new \DomainException('V2_REVERSAL_INVALID_MONEY');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $value): string
    {
        return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    public function reverse(
        int $projectId,
        int $originalBatchId,
        string $requestKey,
        string $effectiveDate,
        string $reason,
        ?int $actorId = null
    ): int {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['finance_posting_batches', 'finance_posting_lines',
                  'finance_posting_source_claims', 'project_head_subheads'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException('V2 reversal baseline missing ' . $table);
            }
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate);
        if ($projectId < 1 || $originalBatchId < 1
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey)
            || !$date || $date->format('Y-m-d') !== $effectiveDate
            || trim($reason) !== $reason || $reason === ''
            || strlen($reason) > 200 || preg_match('/[\x00-\x1F\x7F]/', $reason)) {
            throw new \DomainException('V2_REVERSAL_INVALID_INPUT');
        }

        return DB::transaction(function () use (
            $projectId, $originalBatchId, $requestKey, $effectiveDate, $reason, $actorId
        ): int {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_REVERSAL_PROJECT_NOT_FOUND');
            }
            $original = DB::table('finance_posting_batches')
                ->where('id', $originalBatchId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$original || $original->reversal_of_batch_id !== null
                || $original->posted_at === null) {
                throw new \DomainException('V2_REVERSAL_ORIGINAL_NOT_POSTED');
            }

            $claim = DB::table('finance_posting_source_claims')
                ->where('posting_batch_id', $originalBatchId)->first();
            $sourceTables = [
                'journal' => 'finance_posting_journal_sources',
                'cash' => 'finance_posting_cash_sources',
                'commission' => 'finance_posting_commission_sources',
                'stock_entry' => 'finance_posting_stock_entry_sources',
                'stock_bill' => 'finance_posting_stock_bill_sources',
                'booking' => 'finance_posting_booking_sources',
                'labour' => 'finance_posting_labour_sources',
            ];
            if (!$claim || !isset($sourceTables[$claim->source_kind])
                || !DB::table($sourceTables[$claim->source_kind])
                    ->where('posting_batch_id', $originalBatchId)->exists()) {
                throw new \DomainException('V2_REVERSAL_TYPED_SOURCE_MISSING');
            }

            $originalLines = DB::table('finance_posting_lines')
                ->where('posting_batch_id', $originalBatchId)
                ->orderBy('id')->lockForUpdate()->get();
            if ($originalLines->count() < 2) {
                throw new \DomainException('V2_REVERSAL_ORIGINAL_LINES_MISSING');
            }
            $expected = [];
            $debit = 0;
            $credit = 0;
            foreach ($originalLines as $line) {
                if (!DB::table('project_head_subheads')
                    ->where('id', $line->project_head_subhead_id)
                    ->where('project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_REVERSAL_ACCOUNT_PROJECT_MISMATCH');
                }
                $d = $this->cents($line->debit);
                $c = $this->cents($line->credit);
                if (($d > 0) === ($c > 0)) {
                    throw new \DomainException('V2_REVERSAL_INVALID_LEG');
                }
                $debit += $d;
                $credit += $c;
                $expected[] = [
                    'account_id' => (int) $line->project_head_subhead_id,
                    'debit' => $this->money($c),
                    'credit' => $this->money($d),
                    'description' => 'Reversal: ' . $reason,
                ];
            }
            if ($debit < 1 || $debit !== $credit) {
                throw new \DomainException('V2_REVERSAL_ORIGINAL_UNBALANCED');
            }
            $hash = hash('sha256', json_encode([
                'project_id' => $projectId, 'original_id' => $originalBatchId,
                'date' => $effectiveDate, 'reason' => $reason,
                'original_payload' => (string) $original->payload_sha256,
                'legs' => $expected,
            ], JSON_THROW_ON_ERROR));

            $existing = DB::table('finance_posting_batches')
                ->where('project_id', $projectId)->where('request_key', $requestKey)
                ->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->reversal_of_batch_id !== $originalBatchId
                    || !hash_equals((string) $existing->payload_sha256, $hash)
                    || (string) $existing->effective_date !== $effectiveDate
                    || (string) $existing->voucher_series !== 'REV') {
                    throw new \DomainException('V2_REVERSAL_IDEMPOTENCY_CONFLICT');
                }
                $actual = DB::table('finance_posting_lines')
                    ->where('posting_batch_id', $existing->id)->get();
                if ($actual->count() !== count($expected)) {
                    throw new \DomainException('V2_REVERSAL_REPLAY_TAMPERED');
                }
                $canon = static fn (array $l): string => json_encode($l, JSON_THROW_ON_ERROR);
                $target = array_map($canon, $expected);
                $actualCanonical = [];
                foreach ($actual as $line) {
                    $actualCanonical[] = $canon([
                        'account_id' => (int) $line->project_head_subhead_id,
                        'debit' => $this->money($this->cents($line->debit)),
                        'credit' => $this->money($this->cents($line->credit)),
                        'description' => (string) $line->description,
                    ]);
                }
                sort($target);
                sort($actualCanonical);
                if ($target !== $actualCanonical) {
                    throw new \DomainException('V2_REVERSAL_REPLAY_TAMPERED');
                }
                return (int) $existing->id;
            }

            if (DB::table('finance_posting_batches')
                ->where('reversal_of_batch_id', $originalBatchId)->exists()) {
                throw new \DomainException('V2_REVERSAL_ALREADY_EXISTS');
            }
            $id = DB::table('finance_posting_batches')->insertGetId([
                'project_id' => $projectId, 'voucher_series' => 'REV',
                'voucher_number' => $original->voucher_number,
                'request_key' => $requestKey, 'payload_sha256' => $hash,
                'effective_date' => $effectiveDate, 'posted_by' => $actorId,
                'posted_at' => now(), 'reversal_of_batch_id' => $originalBatchId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($expected as $line) {
                DB::table('finance_posting_lines')->insert([
                    'posting_batch_id' => $id,
                    'project_head_subhead_id' => $line['account_id'],
                    'debit' => $line['debit'], 'credit' => $line['credit'],
                    'description' => $line['description'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return (int) $id;
        }, 3);
    }
}
