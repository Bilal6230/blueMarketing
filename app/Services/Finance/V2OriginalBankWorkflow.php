<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2OriginalBankWorkflow
{
    private function cents(mixed $value): int
    {
        $value = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new \DomainException('V2_BANK_AMOUNT_INVALID');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
    public function settle(int $projectId, int $id, int $status, string $key, string $date, string $note, array $legs, ?int $actor, ?int $bankAccountId = null): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!in_array($status, [1, 2, 3], true) || trim($note) === '' || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $key)) { throw new \DomainException('V2_BANK_INPUT_INVALID'); }
        if ($status === 1) {
            $calendar = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$calendar || $calendar->format('Y-m-d') !== $date) { throw new \DomainException('V2_BANK_DATE_INVALID'); }
        }
        if ($status === 1 && (!$bankAccountId || $bankAccountId < 1)) { throw new \DomainException('V2_BANK_ACCOUNT_REQUIRED'); }
        $hash = hash('sha256', json_encode([$id, $status, $status === 1 ? $date : null, $note, $legs, $bankAccountId], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($projectId, $id, $status, $key, $date, $note, $legs, $actor, $hash, $bankAccountId): array {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $source = DB::table('customer_ledger')->where('id', $id)->where('project_id', $projectId)->lockForUpdate()->first();
            $custody = DB::table('finance_original_customer_requests')->where('customer_ledger_id', $id)->where('project_id', $projectId)->first();
            if (!$source || !in_array((int) $source->payment_type, [2, 3], true)
                || !in_array($source->transaction_type, ['CR', 'CP', 'PPR', 'ECP'], true)
                || !$custody || !hash_equals($custody->source_sha256, V2OriginalCustomerWorkflow::fingerprint($source))) {
                throw new \DomainException('V2_BANK_SOURCE_NOT_VERIFIED');
            }
            if (DB::table('ledgers')->where('customer_ledger_id', $id)->exists()) {
                throw new \DomainException('V2_BANK_LEGACY_POSTING_EXISTS');
            }
            if ($status === 1) {
                $bank = DB::table('project_bank_accounts')->where('id', $bankAccountId)
                    ->where('project_id', $projectId)->where('is_active', 1)->lockForUpdate()->first();
                if (!$bank) { throw new \DomainException('V2_BANK_ACCOUNT_PROJECT_MISMATCH'); }
                $sourceDebit = $this->cents($source->amount_in);
                $sourceCredit = $this->cents($source->amount_out);
                if (($sourceDebit > 0) === ($sourceCredit > 0)) {
                    throw new \DomainException('V2_BANK_SOURCE_AMOUNT_INVALID');
                }
                $sourceAmount = max($sourceDebit, $sourceCredit);
                // Customer-ledger sides describe the customer account, not
                // the bank account. Receipt series debit bank; payment series
                // credit bank. In particular, booking PPR receipts are stored
                // in amount_out because they reduce the customer's balance.
                $bankDebit = in_array($source->transaction_type, ['CR', 'PPR', 'ECP'], true);
                $debit = 0; $credit = 0; $bankLegs = 0;
                foreach ($legs as $leg) {
                    $d = $this->cents($leg['debit'] ?? ''); $c = $this->cents($leg['credit'] ?? '');
                    $isBankAccount = (int) ($leg['account_id'] ?? 0) === (int) $bank->account_mapping_id;
                    if ($isBankAccount) {
                        $bankLegs++;
                        if (($bankDebit && ($d !== $sourceAmount || $c !== 0))
                            || (!$bankDebit && ($c !== $sourceAmount || $d !== 0))) {
                            throw new \DomainException('V2_BANK_AMOUNT_OR_DIRECTION_MISMATCH');
                        }
                    }
                    $debit += $d;
                    $credit += $c;
                }
                if ($bankLegs !== 1 || $debit !== $credit || $debit !== $sourceAmount) {
                    throw new \DomainException('V2_BANK_ALLOCATION_TOTAL_MISMATCH');
                }
            }
            $event = DB::table('finance_bank_settlement_events')->where('project_id', $projectId)->where('request_key', $key)->first();
            if ($event) {
                if (!hash_equals($event->payload_sha256, $hash)) { throw new \DomainException('V2_BANK_REQUEST_CONFLICT'); }
                if ($event->posting_batch_id !== null) {
                    if ((int) $source->passing_status !== 1 || (string) $source->note !== $note || substr((string) $source->bank_post_at, 0, 10) !== $date) {
                        throw new \DomainException('V2_BANK_REPLAY_CHANGED');
                    }
                    $batch = (new V2OriginalCustomerWorkflow())->post($projectId, $id, $source->transaction_type, $key, $legs, $actor);
                    if ((int) $event->posting_batch_id !== $batch) { throw new \DomainException('V2_BANK_REPLAY_CHANGED'); }
                }
                return ['source_id' => $id, 'posting_batch_id' => $event->posting_batch_id ? (int) $event->posting_batch_id : null,
                    'passing_status' => $status, 'current_status' => (int) $source->passing_status, 'replayed' => true];
            }
            $current = (int) $source->passing_status;
            if (!in_array($current, [0, 2], true)) {
                throw new \DomainException('V2_BANK_SETTLEMENT_IMMUTABLE');
            }
            if (in_array($current, [0, 2], true)) {
                DB::table('customer_ledger')->where('id', $id)->update([
                    'passing_status' => $status, 'note' => $note,
                    'bank_post_at' => $status === 1 ? $date : null,
                    'is_approve' => $status === 1 ? 1 : 0, 'is_active' => $status === 3 ? 0 : 1,
                    'updated_at' => now(),
                ]);
            }
            $batch = $status === 1 ? (new V2OriginalCustomerWorkflow())->post(
                $projectId, $id, $source->transaction_type, $key, $legs, $actor
            ) : null;
            // Preserve a unique, project-scoped bank source link for the
            // isolated posting/reporting schema when it has been installed.
            if ($batch !== null && $bankAccountId !== null
                && \Illuminate\Support\Facades\Schema::hasTable('finance_bank_transfer_sources')) {
                DB::table('finance_bank_transfer_sources')->insert([
                    'posting_batch_id' => $batch,
                    'project_id' => $projectId,
                    'bank_account_id' => $bankAccountId,
                    'linked_customer_ledger_id' => $id,
                    'transfer_kind' => in_array($source->transaction_type, ['CR', 'PPR', 'ECP'], true) ? 'receipt' : 'payment',
                    'amount' => $this->money($sourceAmount),
                    'narration' => $note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if (DB::getSchemaBuilder()->hasColumn('booking_vouchers', 'customer_ledger_id')) {
                if (DB::table('booking_vouchers')->where('customer_ledger_id', $id)->where('project_id', '!=', $projectId)->exists()) {
                    throw new \DomainException('V2_BANK_RECEIPT_PROJECT_CONFLICT');
                }
                DB::table('booking_vouchers')->where('customer_ledger_id', $id)->where('project_id', $projectId)->update([
                    'is_approve' => $status === 1 ? 1 : 0, 'is_active' => $status === 3 ? 0 : 1, 'updated_at' => now(),
                ]);
            }
            if ($source->transaction_type === 'ECP') {
                $chargeId = DB::table('finance_extra_charge_payment_sources')->where('customer_ledger_id', $id)->value('charge_customer_ledger_id');
                $charge = $chargeId ? \App\Models\CustomerLedger::query()->whereKey($chargeId)->where('project_id', $projectId)->first() : null;
                if (!$charge) { throw new \DomainException('V2_BANK_CHARGE_CUSTODY_MISSING'); }
                (new V2ExtraChargeBalance())->read($charge);
            }
            $event = [
                'project_id' => $projectId, 'customer_ledger_id' => $id, 'actor_id' => $actor, 'posting_batch_id' => $batch,
                'request_key' => $key, 'payload_sha256' => $hash, 'from_status' => $current, 'to_status' => $status,
                'effective_date' => $status === 1 ? $date : null, 'note' => $note, 'created_at' => now(),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('finance_bank_settlement_events', 'bank_account_id')) {
                $event['bank_account_id'] = $status === 1 ? $bankAccountId : null;
            }
            DB::table('finance_bank_settlement_events')->insert($event);
            if ((int) $source->payment_type === 3) {
                $cheque = \App\Models\CustomerLedger::query()
                    ->whereKey($id)->where('project_id', $projectId)->firstOrFail();
                app(ChequeLifecycleHistory::class)->record(
                    $cheque,
                    match ($status) { 1 => 'cleared', 2 => 'returned', 3 => 'rejected' },
                    $current,
                    $status,
                    $note
                );
            } elseif ((int) $source->payment_type === 2) {
                $online = \App\Models\CustomerLedger::query()
                    ->whereKey($id)->where('project_id', $projectId)->firstOrFail();
                app(OnlineLifecycleHistory::class)->record(
                    $online,
                    match ($status) { 1 => 'confirmed', 2 => 'rejected', 3 => 'cancelled' },
                    $current,
                    $status,
                    $note
                );
            }
            return ['source_id' => $id, 'posting_batch_id' => $batch, 'passing_status' => $status, 'replayed' => false];
        }, 3);
    }
}
