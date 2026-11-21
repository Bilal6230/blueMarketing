<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Isolated V2 bank deposit/withdrawal writer; never called by legacy routes. */
final class BankTransferWriter
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
        string $amount, string $narration, array $allocations, ?int $actor = null, ?int $chequeSourceId = null,
        ?int $approvedDraftId = null): array
    {
        FinanceConnectionGuard::assertAllowed();
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

        return DB::transaction(function () use ($projectId, $bankAccountId, $kind, $key, $date, $narration, $lines, $total, $hash, $actor, $chequeSourceId, $approvedDraftId): array {
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
                    ->where('project_id', $projectId)->whereIn('transaction_type', ['CP','DR','CDR','BOP'])->where('payment_type', 3)
                    ->where('is_active', 1)->where('passing_status', 1)->lockForUpdate()->first();
                $custody = $cheque ? DB::table('finance_original_customer_requests')->where('project_id', $projectId)
                    ->where('customer_ledger_id', $chequeSourceId)->first() : null;
                if (!$cheque || !$custody || !hash_equals((string) $custody->source_sha256, OriginalCustomerWorkflow::fingerprint($cheque))) {
                    throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_NOT_VERIFIED');
                }
                // Do not post a second cash movement for a previously posted
                // or allocated cheque; direct withdrawal must be outgoing CP.
                $hasLedger = DB::table('ledgers')
                    ->where('customer_ledger_id', $chequeSourceId)->exists();
                $hasAllocation = Schema::hasTable('finance_bank_voucher_allocations')
                    && DB::table('finance_bank_voucher_allocations')
                        ->where('voucher_id', $chequeSourceId)->exists();
                BankTransferChequeSourceIntegrity::assert($cheque, $hasLedger, $hasAllocation);
                $retryBatchId = DB::table('finance_posting_batches')->where('project_id', $projectId)->where('request_key', $key)->value('id');
                if (DB::table('finance_bank_transfer_sources')->where('linked_customer_ledger_id', $chequeSourceId)
                    ->when($retryBatchId, fn ($q) => $q->where('posting_batch_id', '!=', $retryBatchId))->exists()) {
                    throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_ALREADY_LINKED');
                }
                $chequeCents = $this->cents($cheque->amount_out);
                if ($chequeCents !== $total || count($lines) !== 1) {
                    throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_AMOUNT_MISMATCH');
                }
            }
            foreach ($lines as $line) {
                $cash = \App\Models\ProjectHeadSubhead::availableForPosting()
                    ->where('project_id', $projectId)
                    ->where('id', $line['account_id'])
                    ->where(function ($query) {
                        $query->whereHas('headAccounting', fn ($head) => $head->where('name', 'LIKE', '%cash%'))
                            ->orWhereHas('subheadAccounting', fn ($subhead) => $subhead->where('name', 'LIKE', '%cash%'));
                    })->exists();
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
                if (!$source
                    || (Schema::hasColumn('finance_bank_transfer_sources','is_active')
                        && (int)$source->is_active!==1)
                    || (Schema::hasColumn('finance_bank_transfer_sources','deleted_at')
                        && $source->deleted_at!==null)
                    || !hash_equals((string) $existing->payload_sha256, $hash)
                    || $source->transfer_kind !== $kind || (int) $source->bank_account_id !== $bankAccountId
                    || (int) $source->project_id !== $projectId || (int) ($source->linked_customer_ledger_id ?? 0) !== (int) ($chequeSourceId ?? 0)) {
                    throw new \DomainException('V2_BANK_TRANSFER_IDEMPOTENCY_CONFLICT');
                }
                PostedVoucherLedgerGate::assertLinked($projectId, (int) $existing->id);
                return ['posting_batch_id' => (int) $existing->id, 'replayed' => true];
            }
            $series = $kind === 'deposit' ? 'BD' : 'BW';
            // Legacy manual bank transfers displayed global batch IDs when the
            // per-series number was NULL. Reserve those visible identities.
            $legacyMax = (int) DB::table('finance_posting_batches')
                ->where('project_id',$projectId)->where('voucher_series',$series)
                ->selectRaw('MAX(COALESCE(voucher_number,id)) AS maximum')->first()?->maximum;
            $approvedDraft=null;
            if($approvedDraftId!==null){
                $approvedDraft=DB::table('finance_bank_transfer_approval_drafts')
                    ->where('id',$approvedDraftId)->where('project_id',$projectId)
                    ->where('transfer_kind',$kind)->where('status','pending')
                    ->lockForUpdate()->first();
                if(!$approvedDraft || $approvedDraft->request_key!==$key
                    ||(int)$approvedDraft->bank_account_id!==$bankAccountId
                    ||(string)$approvedDraft->effective_date!==$date
                    ||$this->cents((string)$approvedDraft->amount)!==$total
                    ||trim((string)$approvedDraft->narration)!==trim($narration)
                    ||(int)$approvedDraft->cash_account_id!==(int)$lines[0]['account_id']
                    ||count($lines)!==1
                    ||(int)($approvedDraft->linked_customer_ledger_id??0)!==(int)($chequeSourceId??0)){
                    throw new \DomainException('BANK_APPROVAL_DRAFT_PAYLOAD_MISMATCH');
                }
                $voucherNumber=(int)$approvedDraft->voucher_number;
                if($voucherNumber<1 || DB::table('finance_posting_batches')
                    ->where('project_id',$projectId)->where('voucher_series',$series)
                    ->where('voucher_number',$voucherNumber)->exists()){
                    throw new \DomainException('BANK_APPROVAL_DRAFT_NUMBER_ALREADY_USED');
                }
            }else{
                $voucherNumber=(new ProjectVoucherAllocator())->next($projectId,$series,$legacyMax);
            }
            $batchId = DB::table('finance_posting_batches')->insertGetId([
                'project_id' => $projectId, 'voucher_series' => $series,
                'voucher_number' => $voucherNumber, 'request_key' => $key, 'payload_sha256' => $hash,
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
            // Same transaction: the existing general ledger is the accounting authority.
            // Test-only isolated SQLite fixtures without the legacy schema keep
            // their original isolated posting contract.
            if (Schema::hasTable('ledger_source_attributions')
                && Schema::hasColumn('ledgers','amount_in')) {
                app(TypedLedgerBackfill::class)->inspect($projectId,(int)$batchId,true);
            } elseif (!app()->environment('testing')) {
                throw new \DomainException('GENERAL_LEDGER_SCHEMA_REQUIRED');
            }
            PostedVoucherLedgerGate::assertLinked($projectId, (int) $batchId);
            return ['posting_batch_id' => (int) $batchId, 'replayed' => false];
        }, 3);
    }
}
