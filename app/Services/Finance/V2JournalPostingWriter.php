<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ISOLATED V2 PILOT ONLY. No production routes call this service.
 * All posting identities come from persisted exact JV detail IDs, not type_id.
 */
final class V2JournalPostingWriter
{
    private function cents($value): int
    {
        $v = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?$/D', $v, $m)) {
            throw new \DomainException('V2_INVALID_MONEY');
        }
        return ((int) $m[1] * 100) + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function assertIsolated(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['finance_posting_batches', 'finance_posting_lines',
                  'finance_posting_source_claims', 'finance_posting_journal_sources', 'finance_journal_line_sources'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException('V2 finance migration missing: ' . $table);
            }
        }
    }

    public function post(
        int $projectId, int $journalId, string $requestKey,
        ?int $actorId = null, string $expectedSeries = 'JV'
    ): int
    {
        $this->assertIsolated();
        if (!in_array($expectedSeries, ['JV', 'SV'], true)) {
            throw new \DomainException('V2_JOURNAL_SOURCE_SERIES_INVALID');
        }
        if ($projectId < 1 || $journalId < 1 ||
            !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey)) {
            throw new \DomainException('V2_POSTING_IDENTITY_INVALID');
        }

        return DB::transaction(function () use ($projectId, $journalId, $requestKey, $actorId, $expectedSeries): int {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_PROJECT_NOT_FOUND');
            }
            $header = DB::table('journal_vouchers')
                ->where('id', $journalId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$header || (string) ($header->type ?? '') !== $expectedSeries ||
                ($header->deleted_at ?? null) !== null) {
                throw new \DomainException('V2_JV_HEADER_NOT_ACTIVE');
            }

            $rows = DB::table('journal_voucher_details')
                ->where('journal_voucher_id', $journalId)
                ->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            if ($rows->count() < 2) {
                throw new \DomainException('V2_JV_REQUIRES_MULTIPLE_LINES');
            }

            $debits = 0;
            $credits = 0;
            $lines = [];
            foreach ($rows as $detail) {
                $accountId = (int) $detail->account_id;
                if (!DB::table('project_head_subheads')
                    ->where('id', $accountId)->where('project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_JV_ACCOUNT_PROJECT_MISMATCH');
                }
                $d = $this->cents($detail->debit);
                $c = $this->cents($detail->credit);
                if (($d > 0) === ($c > 0)) {
                    throw new \DomainException('V2_JV_LINE_MUST_HAVE_ONE_POSITIVE_SIDE');
                }
                $debits += $d;
                $credits += $c;
                $lines[] = [
                    'detail_id' => (int) $detail->id, 'account_id' => $accountId,
                    'debit' => $this->decimal($d), 'credit' => $this->decimal($c),
                    'description' => $detail->description ?? null,
                ];
            }
            if ($debits < 1 || $debits !== $credits ||
                $debits !== $this->cents($header->total_debit) ||
                $credits !== $this->cents($header->total_credit)) {
                throw new \DomainException('V2_JV_UNBALANCED');
            }

            $payloadHash = hash('sha256', json_encode([
                'project_id' => $projectId, 'journal_id' => $journalId,
                'series' => $expectedSeries,
                'voucher_number' => (string) $header->voucher_number,
                'effective_date' => (string) $header->date,
                'lines' => $lines,
            ], JSON_THROW_ON_ERROR));

            $existing = DB::table('finance_posting_batches')
                ->where('project_id', $projectId)->where('request_key', $requestKey)
                ->lockForUpdate()->first();
            if ($existing) {
                $claim = DB::table('finance_posting_journal_sources')
                    ->where('posting_batch_id', $existing->id)
                    ->where('journal_voucher_id', $journalId)->exists();
                $kindMatches = DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id', $existing->id)
                    ->where('source_kind', 'journal')->exists();
                if (!$claim || !$kindMatches || (string) $existing->voucher_series !== $expectedSeries
                    || (string) $existing->voucher_number !== (string) $header->voucher_number
                    || (string) $existing->effective_date !== (string) $header->date
                    || $existing->reversal_of_batch_id !== null
                    || !hash_equals((string) $existing->payload_sha256, $payloadHash)) {
                    throw new \DomainException('V2_IDEMPOTENCY_CONFLICT');
                }
                if ((int) DB::table('finance_posting_lines')
                    ->where('posting_batch_id', $existing->id)->count() !== count($lines)) {
                    throw new \DomainException('V2_REPLAY_DETAILS_MISSING');
                }
                // A replay must re-verify the actual posted amounts/account
                // and exact source IDs, not just the presence of two links.
                $posted = DB::table('finance_journal_line_sources as src')
                    ->join('finance_posting_lines as line', 'line.id', '=', 'src.posting_line_id')
                    ->where('line.posting_batch_id', $existing->id)
                    ->get(['src.journal_voucher_detail_id', 'line.project_head_subhead_id',
                        'line.debit', 'line.credit', 'line.description']);
                if ($posted->count() !== count($lines)) {
                    throw new \DomainException('V2_REPLAY_DETAILS_MISSING');
                }
                $expectedByDetail = [];
                foreach ($lines as $line) {
                    $expectedByDetail[$line['detail_id']] = $line;
                }
                foreach ($posted as $posting) {
                    $detailId = (int) $posting->journal_voucher_detail_id;
                    $expected = $expectedByDetail[$detailId] ?? null;
                    if (!$expected || (int) $posting->project_head_subhead_id !== $expected['account_id'] ||
                        $this->cents($posting->debit) !== $this->cents($expected['debit']) ||
                        $this->cents($posting->credit) !== $this->cents($expected['credit']) ||
                        ($posting->description ?? null) !== ($expected['description'] ?? null)) {
                        throw new \DomainException('V2_REPLAY_LEDGER_TAMPERED');
                    }
                }
                return (int) $existing->id;
            }

            $batchId = DB::table('finance_posting_batches')->insertGetId([
                'project_id' => $projectId, 'voucher_series' => $expectedSeries,
                'voucher_number' => $header->voucher_number, 'request_key' => $requestKey,
                'payload_sha256' => $payloadHash, 'effective_date' => $header->date,
                'posted_by' => $actorId, 'posted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('finance_posting_source_claims')->insert([
                'posting_batch_id' => $batchId, 'source_kind' => 'journal',
            ]);
            DB::table('finance_posting_journal_sources')->insert([
                'posting_batch_id' => $batchId, 'journal_voucher_id' => $journalId,
            ]);
            foreach ($lines as $line) {
                $lineId = DB::table('finance_posting_lines')->insertGetId([
                    'posting_batch_id' => $batchId,
                    'project_head_subhead_id' => $line['account_id'],
                    'debit' => $line['debit'], 'credit' => $line['credit'],
                    'description' => $line['description'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('finance_journal_line_sources')->insert([
                    'posting_line_id' => $lineId,
                    'journal_voucher_detail_id' => $line['detail_id'],
                ]);
            }
            return (int) $batchId;
        }, 3);
    }
}
