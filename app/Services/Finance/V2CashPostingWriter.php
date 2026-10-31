<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V2-ONLY CR/CP writer. Not wired to legacy endpoints.
 * Requires explicit, approved account allocation for both sides.
 */
final class V2CashPostingWriter
{
    private function cents($amount): int
    {
        $value = (string) $amount;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $value, $match)) {
            throw new \DomainException('V2_CASH_INVALID_MONEY');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function asMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function post(
        int $projectId,
        int $sourceId,
        string $requestKey,
        array $allocation,
        ?int $actorId = null,
        ?string $expectedSeries = null
    ): int
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['projects', 'customer_ledger', 'project_head_subheads',
                  'finance_posting_batches', 'finance_posting_source_claims',
                  'finance_posting_cash_sources', 'finance_posting_lines'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException('V2 cash baseline missing ' . $table);
            }
        }
        if ($projectId < 1 || $sourceId < 1 ||
            !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey) ||
            count($allocation) < 2) {
            throw new \DomainException('V2_CASH_INVALID_POSTING_INPUT');
        }

        return DB::transaction(function () use ($projectId, $sourceId, $requestKey, $allocation, $actorId, $expectedSeries): int {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_CASH_PROJECT_NOT_FOUND');
            }
            $source = DB::table('customer_ledger')
                ->where('id', $sourceId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$source || (int) ($source->is_active ?? 1) !== 1 ||
                !in_array((string) $source->transaction_type, ['CR', 'CP', 'PPR', 'EC', 'ECP'], true)
                || ($expectedSeries === null
                    ? !in_array((string) $source->transaction_type, ['CR', 'CP'], true)
                    : (string) $source->transaction_type !== $expectedSeries)
                || (in_array((string) $source->transaction_type, ['PPR', 'EC', 'ECP'], true)
                    && (int) ($source->is_approve ?? 0) !== 1)) {
                throw new \DomainException('V2_CASH_SOURCE_NOT_ACTIVE');
            }
            $postingDate = (string) $source->date;
            $bank = in_array((int) ($source->payment_type ?? 1), [2, 3], true);
            if ($bank) {
                if ((int) ($source->passing_status ?? 0) !== 1 || empty($source->bank_post_at)) {
                    throw new \DomainException('V2_BANK_CLEARANCE_REQUIRED');
                }
                $postingDate = substr((string) $source->bank_post_at, 0, 10);
                $calendar = \DateTimeImmutable::createFromFormat('!Y-m-d', $postingDate);
                if (!$calendar || $calendar->format('Y-m-d') !== $postingDate) {
                    throw new \DomainException('V2_BANK_POSTING_DATE_INVALID');
                }
            }

            $receipt = $this->cents($source->amount_in);
            $payment = $this->cents($source->amount_out);
            if (($receipt > 0) === ($payment > 0)) {
                throw new \DomainException('V2_CASH_SOURCE_AMOUNT_INVALID');
            }
            $total = max($receipt, $payment);
            if (($source->transaction_type === 'CR' && $receipt === 0) ||
                ($source->transaction_type === 'CP' && $payment === 0)) {
                throw new \DomainException('V2_CASH_SOURCE_DIRECTION_INVALID');
            }

            $lines = [];
            $debit = 0;
            $credit = 0;
            foreach ($allocation as $leg) {
                if (!is_array($leg) || !isset($leg['account_id'], $leg['debit'], $leg['credit'])) {
                    throw new \DomainException('V2_CASH_LEG_INVALID');
                }
                $rawAccount = $leg['account_id'];
                if ((!is_int($rawAccount) && !is_string($rawAccount)) ||
                    !preg_match('/^[1-9][0-9]*$/D', (string) $rawAccount) ||
                    (string) (int) $rawAccount !== (string) $rawAccount) {
                    throw new \DomainException('V2_CASH_ACCOUNT_ID_INVALID');
                }
                $account = (int) $rawAccount;
                if (!\App\Models\ProjectHeadSubhead::query()
                    ->where('id', $account)->where('project_id', $projectId)->lockForUpdate()->first(['id'])) {
                    throw new \DomainException('V2_CASH_ACCOUNT_PROJECT_MISMATCH');
                }
                $d = $this->cents($leg['debit']);
                $c = $this->cents($leg['credit']);
                if (($d > 0) === ($c > 0)) {
                    throw new \DomainException('V2_CASH_LEG_MUST_HAVE_ONE_SIDE');
                }
                $debit += $d;
                $credit += $c;
                $lines[] = [
                    'account_id' => $account,
                    'debit' => $this->asMoney($d),
                    'credit' => $this->asMoney($c),
                ];
            }
            if ($debit !== $total || $credit !== $total) {
                throw new \DomainException('V2_CASH_UNBALANCED_OR_SOURCE_MISMATCH');
            }

            // Legacy type_id is a polymorphic voucher reference, NOT a real
            // voucher sequence for PPR/EC/ECP. Never cast it as identity.
            $batchNumber = in_array((string) $source->transaction_type, ['CR', 'CP'], true)
                ? $source->type_id : null;

            $payload = [
                'project' => $projectId,
                'source_id' => $sourceId,
                'transaction_type' => $source->transaction_type,
                'date' => (string) $source->date,
                'amount_in' => $this->asMoney($receipt),
                'amount_out' => $this->asMoney($payment),
                'legs' => $lines,
            ];
            if ($bank) { $payload['bank_posting_date'] = $postingDate; }
            $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

            $existing = DB::table('finance_posting_batches')
                ->where('project_id', $projectId)
                ->where('request_key', $requestKey)
                ->lockForUpdate()->first();
            if ($existing) {
                $claim = DB::table('finance_posting_cash_sources')
                    ->where('posting_batch_id', $existing->id)
                    ->where('customer_ledger_id', $sourceId)->exists();
                $canonicalClaim = DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id', $existing->id)
                    ->where('source_kind', 'cash')->exists();
                if (!$claim || !$canonicalClaim ||
                    (string) $existing->voucher_series !== (string) $source->transaction_type ||
                    (string) $existing->voucher_number !== (string) $batchNumber ||
                    (string) $existing->effective_date !== $postingDate ||
                    $existing->reversal_of_batch_id !== null ||
                    !hash_equals((string) $existing->payload_sha256, $fingerprint)) {
                    throw new \DomainException('V2_CASH_IDEMPOTENCY_CONFLICT');
                }
                $persisted = DB::table('finance_posting_lines')
                    ->where('posting_batch_id', $existing->id)
                    ->get(['project_head_subhead_id', 'debit', 'credit']);
                if ($persisted->count() !== count($lines)) {
                    throw new \DomainException('V2_CASH_REPLAY_LINE_COUNT_MISMATCH');
                }
                $canonical = static fn (array $line): string =>
                    (string) $line['account_id'] . ':' . $line['debit'] . ':' . $line['credit'];
                $expected = array_map($canonical, $lines);
                $actual = $persisted->map(fn ($r) => $canonical([
                    'account_id' => (int) $r->project_head_subhead_id,
                    'debit' => $this->asMoney($this->cents($r->debit)),
                    'credit' => $this->asMoney($this->cents($r->credit)),
                ]))->all();
                sort($expected);
                sort($actual);
                if ($expected !== $actual) {
                    throw new \DomainException('V2_CASH_REPLAY_LINES_TAMPERED');
                }
                return (int) $existing->id;
            }

            $batchId = DB::table('finance_posting_batches')->insertGetId([
                'project_id' => $projectId,
                'voucher_series' => (string) $source->transaction_type,
                'voucher_number' => $batchNumber,
                'request_key' => $requestKey,
                'payload_sha256' => $fingerprint,
                'effective_date' => $postingDate,
                'posted_by' => $actorId,
                'posted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('finance_posting_source_claims')->insert([
                'posting_batch_id' => $batchId, 'source_kind' => 'cash',
            ]);
            DB::table('finance_posting_cash_sources')->insert([
                'posting_batch_id' => $batchId, 'customer_ledger_id' => $sourceId,
            ]);
            foreach ($lines as $line) {
                DB::table('finance_posting_lines')->insert([
                    'posting_batch_id' => $batchId,
                    'project_head_subhead_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return (int) $batchId;
        }, 3);
    }
}
