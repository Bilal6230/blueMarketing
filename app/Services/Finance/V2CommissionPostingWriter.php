<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exact CV commission document/detail posting, not a legacy ledger.type_id join.
 * Source varchar voucher_number is kept in payload, NEVER coerced to an ID.
 * V2-only, original source rows are locked but never modified.
 */
final class V2CommissionPostingWriter
{
    private function cents(mixed $v): int
    {
        $s = (string) $v;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $s, $m)) {
            throw new \DomainException('V2_CV_MONEY_INVALID');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function post(int $projectId, int $commissionVoucherId, string $key, ?int $actorId = null): int
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if ($projectId < 1 || $commissionVoucherId < 1
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $key)) {
            throw new \DomainException('V2_CV_INVALID_IDENTITY');
        }
        foreach (['projects', 'commision_vouchers', 'commision_voucher_details',
            'project_head_subheads', 'finance_posting_batches', 'finance_posting_lines',
            'finance_posting_source_claims', 'finance_posting_commission_sources',
            'finance_commission_line_sources'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException('V2_CV_SCHEMA_REQUIRED: ' . $table);
            }
        }

        return DB::transaction(function () use ($projectId, $commissionVoucherId, $key, $actorId): int {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_CV_PROJECT_MISSING');
            }
            $header = DB::table('commision_vouchers')
                ->where('id', $commissionVoucherId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$header || $header->deleted_at !== null
                || (string) $header->status !== 'approved') {
                throw new \DomainException('V2_CV_SOURCE_NOT_APPROVED');
            }
            $details = DB::table('commision_voucher_details')
                ->where('commision_voucher_id', $commissionVoucherId)
                ->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            if ($details->count() < 2) {
                throw new \DomainException('V2_CV_DETAILS_REQUIRED');
            }

            $rows = [];
            $debit = 0;
            $credit = 0;
            foreach ($details as $d) {
                $account = (int) $d->account_id;
                if (!DB::table('project_head_subheads')
                    ->where('id', $account)->where('project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_CV_CROSS_PROJECT_ACCOUNT');
                }
                $dr = $this->cents($d->debit);
                $cr = $this->cents($d->credit);
                if (($dr > 0) === ($cr > 0)) {
                    throw new \DomainException('V2_CV_INVALID_LEG');
                }
                if ($debit > PHP_INT_MAX - $dr || $credit > PHP_INT_MAX - $cr) {
                    throw new \DomainException('V2_CV_OVERFLOW');
                }
                $debit += $dr;
                $credit += $cr;
                $rows[] = [
                    'detail_id' => (int) $d->id,
                    'account_id' => $account,
                    'debit' => $this->money($dr),
                    'credit' => $this->money($cr),
                    'description' => $d->description,
                ];
            }
            if ($debit < 1 || $debit !== $credit
                || $debit !== $this->cents($header->total_debit)
                || $credit !== $this->cents($header->total_credit)) {
                throw new \DomainException('V2_CV_NOT_BALANCED');
            }
            $fingerprint = hash('sha256', json_encode([
                'source_kind' => 'commission',
                'project_id' => $projectId,
                'source_id' => $commissionVoucherId,
                'source_number_text' => (string) $header->voucher_number,
                'source_date' => (string) $header->date,
                'source_status' => (string) $header->status,
                'legs' => $rows,
            ], JSON_THROW_ON_ERROR));

            // CV number is varchar in actual source; preserve exact text in
            // source header. Only positive numeric series numbers fit BIGINT.
            $number = (string) $header->voucher_number;
            $batchNumber = preg_match('/^[1-9][0-9]{0,17}$/D', $number)
                ? $number : null;
            $existing = DB::table('finance_posting_batches')
                ->where('project_id', $projectId)->where('request_key', $key)
                ->lockForUpdate()->first();
            if ($existing) {
                $claim = DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id', $existing->id)
                    ->where('source_kind', 'commission')->exists();
                $typed = DB::table('finance_posting_commission_sources')
                    ->where('posting_batch_id', $existing->id)
                    ->where('commision_voucher_id', $commissionVoucherId)->exists();
                if (!$claim || !$typed || $existing->reversal_of_batch_id !== null
                    || (string) $existing->voucher_series !== 'CV'
                    || (string) $existing->effective_date !== (string) $header->date
                    || (string) ($existing->voucher_number ?? '') !== (string) ($batchNumber ?? '')
                    || !hash_equals((string) $existing->payload_sha256, $fingerprint)) {
                    throw new \DomainException('V2_CV_REPLAY_CONFLICT');
                }
                $actual = DB::table('finance_commission_line_sources as src')
                    ->join('finance_posting_lines as l', 'l.id', '=', 'src.posting_line_id')
                    ->where('l.posting_batch_id', $existing->id)
                    ->get(['src.commision_voucher_detail_id', 'l.project_head_subhead_id',
                        'l.debit', 'l.credit', 'l.description']);
                if ($actual->count() !== count($rows)
                    || (int) DB::table('finance_posting_lines')
                        ->where('posting_batch_id', $existing->id)->count() !== count($rows)) {
                    throw new \DomainException('V2_CV_REPLAY_TAMPERED');
                }
                $expected = [];
                foreach ($rows as $r) {
                    $expected[(int) $r['detail_id']] = $r;
                }
                foreach ($actual as $line) {
                    $row = $expected[(int) $line->commision_voucher_detail_id] ?? null;
                    if (!$row || (int) $line->project_head_subhead_id !== $row['account_id']
                        || $this->cents($line->debit) !== $this->cents($row['debit'])
                        || $this->cents($line->credit) !== $this->cents($row['credit'])
                        || $line->description !== $row['description']) {
                        throw new \DomainException('V2_CV_REPLAY_TAMPERED');
                    }
                    unset($expected[(int) $line->commision_voucher_detail_id]);
                }
                if ($expected !== []) {
                    throw new \DomainException('V2_CV_REPLAY_TAMPERED');
                }
                return (int) $existing->id;
            }

            $batch = DB::table('finance_posting_batches')->insertGetId([
                'project_id' => $projectId, 'voucher_series' => 'CV',
                'voucher_number' => $batchNumber,
                'request_key' => $key, 'payload_sha256' => $fingerprint,
                'effective_date' => $header->date, 'posted_by' => $actorId,
                'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('finance_posting_source_claims')->insert([
                'posting_batch_id' => $batch, 'source_kind' => 'commission',
            ]);
            DB::table('finance_posting_commission_sources')->insert([
                'posting_batch_id' => $batch, 'commision_voucher_id' => $commissionVoucherId,
            ]);
            foreach ($rows as $row) {
                $id = DB::table('finance_posting_lines')->insertGetId([
                    'posting_batch_id' => $batch, 'project_head_subhead_id' => $row['account_id'],
                    'debit' => $row['debit'], 'credit' => $row['credit'],
                    'description' => $row['description'], 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('finance_commission_line_sources')->insert([
                    'posting_line_id' => $id,
                    'commision_voucher_detail_id' => $row['detail_id'],
                ]);
            }
            return (int) $batch;
        }, 3);
    }
}
