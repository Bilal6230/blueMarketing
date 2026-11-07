<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Isolated V2 bank deposit/withdrawal writer; never called by legacy routes. */
final class V2BankTransferWriter
{
    private function cents(mixed $value): int
    {
        $value = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new \DomainException('V2_BANK_TRANSFER_AMOUNT_INVALID');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Allocations are the non-bank side: deposits credit them; withdrawals debit them.
     * @return array{posting_batch_id:int,replayed:bool}
     */
    public function post(int $projectId, int $bankAccountId, string $kind, string $key, string $date,
        string $amount, string $narration, array $allocations, ?int $actor = null, ?int $chequeSourceId = null): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!in_array($kind, ['deposit', 'withdrawal'], true) || $projectId < 1 || $bankAccountId < 1
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $key) || trim($narration) === '' || count($allocations) < 1) {
            throw new \DomainException('V2_BANK_TRANSFER_INPUT_INVALID');
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $total = $this->cents($amount);
        if (!$day || $day->format('Y-m-d') !== $date || $total < 1) {
            throw new \DomainException('V2_BANK_TRANSFER_DATE_OR_AMOUNT_INVALID');
        }
        $opposite = 0;
        $lines = [];
        foreach ($allocations as $leg) {
            if (!is_array($leg) || !isset($leg['account_id'], $leg['amount'])) {
                throw new \DomainException('V2_BANK_TRANSFER_ALLOCATION_INVALID');
            }
            $id = filter_var($leg['account_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $cents = $this->cents($leg['amount']);
            if (!$id || $cents < 1) { throw new \DomainException('V2_BANK_TRANSFER_ALLOCATION_INVALID'); }
            $opposite += $cents;
            $lines[] = ['account_id' => (int) $id, 'amount' => $this->money($cents)];
        }
        if ($opposite !== $total) { throw new \DomainException('V2_BANK_TRANSFER_ALLOCATION_TOTAL_MISMATCH'); }
        $hash = hash('sha256', json_encode([$projectId, $bankAccountId, $kind, $date, $this->money($total), trim($narration), $lines, $chequeSourceId], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($projectId, $bankAccountId, $kind, $key, $date, $narration, $lines, $total, $hash, $actor, $chequeSourceId): array {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $bank = DB::table('project_bank_accounts')->where('id', $bankAccountId)
                ->where('project_id', $projectId)->where('is_active', 1)->lockForUpdate()->first();
            if (!$bank) { throw new \DomainException('V2_BANK_TRANSFER_ACCOUNT_PROJECT_MISMATCH'); }
            $bankAccount = \App\Models\ProjectHeadSubhead::availableForPosting()
                ->where('id', $bank->account_mapping_id)->where('project_id', $projectId)->lockForUpdate()->first(['id']);
            if (!$bankAccount) { throw new \DomainException('V2_BANK_TRANSFER_ACCOUNT_MAPPING_INVALID'); }

            $cheque = null;
            if ($chequeSourceId !== null) {
                if ($kind !== 'withdrawal') { throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_WITHDRAWAL_ONLY'); }
                $cheque = DB::table('customer_ledger')->where('id', $chequeSourceId)
                    ->where('project_id', $projectId)->where('transaction_type', 'CP')->where('payment_type', 3)
                    ->where('is_active', 1)->where('passing_status', 1)->lockForUpdate()->first();
                $custody = $cheque ? DB::table('finance_original_customer_requests')->where('project_id', $projectId)
                    ->where('customer_ledger_id', $chequeSourceId)->first() : null;
                if (!$cheque || !$custody || !hash_equals((string) $custody->source_sha256, V2OriginalCustomerWorkflow::fingerprint($cheque))) {
                    throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_NOT_VERIFIED');
                }
                $retryBatchId = DB::table('finance_posting_batches')->where('project_id', $projectId)->where('request_key', $key)->value('id');
                if (DB::table('finance_bank_transfer_sources')->where('linked_customer_ledger_id', $chequeSourceId)
                    ->when($retryBatchId, fn ($q) => $q->where('posting_batch_id', '!=', $retryBatchId))->exists()) {
                    throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_ALREADY_LINKED');
                }
                $in = $this->cents($cheque->amount_in); $out = $this->cents($cheque->amount_out);
                if (($in > 0) === ($out > 0)) { throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_AMOUNT_INVALID'); }
                $chequeCents = max($in, $out);
                if ($chequeCents !== $total || count($lines) !== 1) {
                    throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_AMOUNT_MISMATCH');
                }
            }
            foreach ($lines as $line) {
                $cash = \App\Models\ProjectHeadSubhead::query()->where('project_head_subheads.id', $line['account_id'])->where('project_head_subheads.project_id', $projectId)
                    ->join('head_accountings as cash_head', 'cash_head.id', '=', 'project_head_subheads.head_accounting_id')
                    ->where('cash_head.name', 'LIKE', '%cash%')->exists();
                if (!$cash) { throw new \DomainException('V2_BANK_TRANSFER_CASH_ACCOUNT_REQUIRED'); }
            }

            $postingLines = [];
            $bankDebit = $kind === 'deposit';
            $postingLines[] = ['account_id' => (int) $bank->account_mapping_id,
                'debit' => $bankDebit ? $this->money($total) : '0.00',
                'credit' => $bankDebit ? '0.00' : $this->money($total), 'description' => trim($narration)];
            foreach ($lines as $line) {
                if ($line['account_id'] === (int) $bank->account_mapping_id) {
                    throw new \DomainException('V2_BANK_TRANSFER_DUPLICATE_BANK_ACCOUNT_ALLOCATION');
                }
                $account = \App\Models\ProjectHeadSubhead::availableForPosting()
                    ->where('id', $line['account_id'])->where('project_id', $projectId)->lockForUpdate()->first(['id']);
                if (!$account) { throw new \DomainException('V2_BANK_TRANSFER_ACCOUNT_PROJECT_MISMATCH'); }
                $postingLines[] = ['account_id' => $line['account_id'],
                    'debit' => $bankDebit ? '0.00' : $line['amount'],
                    'credit' => $bankDebit ? $line['amount'] : '0.00', 'description' => trim($narration)];
            }

            $existing = DB::table('finance_posting_batches')->where('project_id', $projectId)
                ->where('request_key', $key)->lockForUpdate()->first();
            if ($existing) {
                $source = DB::table('finance_bank_transfer_sources')->where('posting_batch_id', $existing->id)->first();
                if (!$source || !hash_equals((string) $existing->payload_sha256, $hash)
                    || $source->transfer_kind !== $kind || (int) $source->bank_account_id !== $bankAccountId
                    || (int) $source->project_id !== $projectId || (int) ($source->linked_customer_ledger_id ?? 0) !== (int) ($chequeSourceId ?? 0)) {
                    throw new \DomainException('V2_BANK_TRANSFER_IDEMPOTENCY_CONFLICT');
                }
                return ['posting_batch_id' => (int) $existing->id, 'replayed' => true];
            }
            $batchId = DB::table('finance_posting_batches')->insertGetId([
                'project_id' => $projectId, 'voucher_series' => $kind === 'deposit' ? 'BD' : 'BW',
                'voucher_number' => null, 'request_key' => $key, 'payload_sha256' => $hash,
                'effective_date' => $date, 'posted_by' => $actor, 'posted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('finance_posting_source_claims')->insert(['posting_batch_id' => $batchId, 'source_kind' => 'bank_transfer']);
            DB::table('finance_bank_transfer_sources')->insert([
                'posting_batch_id' => $batchId, 'project_id' => $projectId, 'bank_account_id' => $bankAccountId,
                'linked_customer_ledger_id' => $chequeSourceId, 'transfer_kind' => $kind, 'amount' => $this->money($total),
                'narration' => trim($narration), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($postingLines as $line) {
                DB::table('finance_posting_lines')->insert([
                    'posting_batch_id' => $batchId, 'project_head_subhead_id' => $line['account_id'],
                    'debit' => $line['debit'], 'credit' => $line['credit'], 'description' => $line['description'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return ['posting_batch_id' => (int) $batchId, 'replayed' => false];
        }, 3);
    }
}
