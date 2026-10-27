<?php

namespace App\Services\Finance;

/**
 * Deterministic financial request identity.
 * This component NEVER writes. Database uniqueness/transactions must enforce replay safety.
 */
final class FinancialPostingFingerprint
{
    /**
     * @param array<int,array{account_id:int,debit:string,credit:string}> $lines
     */
    public function fingerprint(string $sourceKind, int $projectId, string $requestKey, array $lines): array
    {
        if (!in_array($sourceKind, ['journal_voucher', 'commission_voucher'], true)
            || $projectId < 1 || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey)
            || count($lines) < 2 || count($lines) > 1000) {
            throw new \InvalidArgumentException('Invalid typed source, project, idempotency key or line count.');
        }

        $debits = $credits = 0;
        $normalized = [];
        foreach ($lines as $line) {
            $account = $line['account_id'] ?? null;
            $debit = $this->cents($line['debit'] ?? null);
            $credit = $this->cents($line['credit'] ?? null);
            if (!is_int($account) || $account < 1 || $debit === null || $credit === null
                || ($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw new \InvalidArgumentException('Posting requires valid single-sided decimal account lines.');
            }
            $debits += $debit;
            $credits += $credit;
            $normalized[] = [$account, $debit, $credit];
        }
        if ($debits === 0 || $debits !== $credits) {
            throw new \InvalidArgumentException('Posting is not balanced.');
        }

        // Preserve row ordering, which may be material for voucher narration.
        $payload = json_encode([$sourceKind, $projectId, $normalized], JSON_THROW_ON_ERROR);
        return [
            'scope' => hash('sha256', json_encode([$sourceKind, $projectId, $requestKey], JSON_THROW_ON_ERROR)),
            'payload_hash' => hash('sha256', $payload),
            'amount_cents' => $debits,
            'line_count' => count($normalized),
        ];
    }

    public function classifyReplay(array $previous, array $current): string
    {
        if (!isset($previous['scope'], $previous['payload_hash'], $current['scope'], $current['payload_hash'])) {
            throw new \InvalidArgumentException('Complete prior and current idempotency identities required.');
        }
        if (!hash_equals($previous['scope'], $current['scope'])) {
            return 'new_request';
        }
        return hash_equals($previous['payload_hash'], $current['payload_hash'])
            ? 'same_request'
            : 'conflicting_payload';
    }

    private function cents($amount): ?int
    {
        if (!is_string($amount) || !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $amount)) {
            return null;
        }
        $parts = explode('.', $amount);
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
