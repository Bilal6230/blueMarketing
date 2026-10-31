<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2OriginalCustomerWorkflow
{
    public static function fingerprint(object $source): string
    {
        $values = [];
        foreach (['project_id', 'transaction_type', 'type_id', 'date', 'customer_id', 'plot_id', 'reference',
            'description', 'payment_type', 't_number', 'bank_id', 'passing_date'] as $field) {
            $values[$field] = isset($source->$field) ? (string) $source->$field : null;
        }
        $values['amount_in'] = self::money($source->amount_in); $values['amount_out'] = self::money($source->amount_out);
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public static function money(mixed $value): string
    {
        $s = str_replace(',', '', trim((string) $value));
        if (!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D', $s, $m)) {
            throw new \DomainException('V2_SOURCE_INVALID_MONEY');
        }
        return (string) (int) $m[1].'.'.str_pad($m[2] ?? '', 2, '0');
    }

    public function create(int $projectId, string $series, string $key, array $source, array $legs, ?int $actor): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!in_array($series, ['CR', 'CP', 'PPR', 'EC', 'ECP'], true)
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $key)) {
            throw new \DomainException('V2_SOURCE_REQUEST_INVALID');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($source['date'] ?? ''));
        $amount = self::money($source['amount'] ?? '');
        if (!$date || $date->format('Y-m-d') !== $source['date'] || $amount === '0.00') {
            throw new \DomainException('V2_SOURCE_DATE_OR_AMOUNT_INVALID');
        }
        $metadata = array_intersect_key($source, array_flip([
            'reference', 'description', 'customer_id', 'plot_id', 'payment_type', 't_number', 'bank_id', 'passing_date',
        ]));
        $paymentType = (int) ($metadata['payment_type'] ?? 1);
        if (!in_array($paymentType, [0, 1, 2, 3], true) || ($paymentType === 0 && $series !== 'EC')) {
            throw new \DomainException('V2_SOURCE_PAYMENT_INVALID');
        }
        $pending = in_array($paymentType, [2, 3], true);
        if ($pending && (empty($metadata['t_number']) || empty($metadata['bank_id']) || empty($metadata['passing_date']))) {
            throw new \DomainException('V2_SOURCE_BANK_DETAILS_REQUIRED');
        }
        foreach ($legs as &$leg) {
            $leg = ['account_id' => (int) ($leg['account_id'] ?? 0),
                'debit' => self::money($leg['debit'] ?? ''), 'credit' => self::money($leg['credit'] ?? '')];
        }
        unset($leg);
        ksort($metadata);
        $hash = hash('sha256', json_encode([$series, $date->format('Y-m-d'), $amount, $metadata, $legs, $source['request_context'] ?? null], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($projectId, $series, $key, $source, $metadata, $amount, $legs, $pending, $hash, $actor): array {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $existing = DB::table('finance_original_customer_requests')->where('project_id', $projectId)
                ->where('request_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals($existing->payload_sha256, $hash)) { throw new \DomainException('V2_SOURCE_REQUEST_CONFLICT'); }
                $row = DB::table('customer_ledger')->where('id', $existing->customer_ledger_id)->where('project_id', $projectId)->first();
                if (!$row || (int) $row->is_active !== 1 || $row->transaction_type !== $series) {
                    throw new \DomainException('V2_SOURCE_REPLAY_NOT_ACTIVE');
                }
                if (!hash_equals($existing->source_sha256, self::fingerprint($row))) { throw new \DomainException('V2_SOURCE_REPLAY_CHANGED'); }
                $incoming = in_array($series, ['CR', 'EC', 'ECP'], true);
                if ((string) $row->date !== $source['date']
                    || self::money($row->amount_in) !== ($incoming ? $amount : '0.00')
                    || self::money($row->amount_out) !== ($incoming ? '0.00' : $amount)) {
                    throw new \DomainException('V2_SOURCE_REPLAY_CHANGED');
                }
                foreach ($metadata as $field => $value) {
                    if ((string) ($row->$field ?? '') !== (string) ($value ?? '')) {
                        throw new \DomainException('V2_SOURCE_REPLAY_CHANGED');
                    }
                }
                $batch = DB::table('finance_posting_cash_sources')->where('customer_ledger_id', $row->id)->value('posting_batch_id');
                if (!$pending) {
                    $batch = $this->post($projectId, (int) $row->id, $series, $key, $legs, $actor);
                }
                return ['source_id' => (int) $row->id, 'posting_batch_id' => $batch ? (int) $batch : null,
                    'pending_bank' => $pending && (int) $row->passing_status === 0 && !$batch, 'replayed' => true];
            }
            if (DB::table('finance_posting_batches')->where('project_id', $projectId)->where('request_key', $key)->exists()) {
                throw new \DomainException('V2_SOURCE_KEY_ALREADY_USED');
            }
            if (!empty($metadata['reference']) && DB::table('customer_ledger')->where('project_id', $projectId)
                ->where('transaction_type', $series)->where('reference', $metadata['reference'])
                ->where('customer_id', $metadata['customer_id'] ?? null)->where('plot_id', $metadata['plot_id'] ?? null)->exists()) {
                throw new \DomainException('V2_SOURCE_REFERENCE_ALREADY_USED');
            }
            $allocator = new ProjectVoucherAllocator();
            $number = $series === 'PPR' ? $allocator->nextBookingReceipt($projectId) : $allocator->next($projectId, $series);
            // Preserve the original customer-subledger polarity, independent
            // of the explicitly approved general-ledger account allocation.
            $incoming = in_array($series, ['CR', 'EC', 'ECP'], true);
            $id = DB::table('customer_ledger')->insertGetId($metadata + [
                'project_id' => $projectId, 'transaction_type' => $series, 'type_id' => $number, 'date' => $source['date'],
                'amount_in' => $incoming ? $amount : '0.00', 'amount_out' => $incoming ? '0.00' : $amount,
                'is_active' => 1, 'is_approve' => $pending ? 0 : 1,
                'passing_status' => $pending ? 0 : null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('finance_original_customer_requests')->insert([
                'project_id' => $projectId, 'request_key' => $key, 'payload_sha256' => $hash,
                'source_sha256' => self::fingerprint(DB::table('customer_ledger')->where('id', $id)->first()),
                'customer_ledger_id' => $id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $batch = $pending ? null : $this->post($projectId, (int) $id, $series, $key, $legs, $actor);
            return ['source_id' => (int) $id, 'posting_batch_id' => $batch, 'pending_bank' => $pending, 'replayed' => false];
        }, 3);
    }

    public function post(int $projectId, int $id, string $series, string $key, array $legs, ?int $actor): int
    {
        $gateway = new V2PostingGateway();
        return in_array($series, ['CR', 'CP'], true)
            ? $gateway->postCash($projectId, $id, $key, $legs, $actor)
            : $gateway->postCustomerVoucher($projectId, $id, $series, $key, $legs, $actor);
    }
}
