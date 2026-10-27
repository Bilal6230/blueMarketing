<?php

namespace App\Services\Finance;

/**
 * Builds an immutable debit/credit reversal proposal, never posts or mutates.
 * Only verified typed source lines may enter this builder.
 */
final class FinancialReversalPlanner
{
    /**
     * @param array<int,array{account_id:int,debit:string,credit:string}> $lines
     */
    public function plan(string $sourceKind, int $projectId, int $sourceId, array $lines, bool $sourceVerified): array
    {
        if (!in_array($sourceKind, ['journal_voucher', 'commission_voucher'], true)
            || $projectId < 1 || $sourceId < 1 || !$sourceVerified || count($lines) < 2) {
            throw new \InvalidArgumentException('Verified typed source, project, voucher and at least two lines required.');
        }

        $debits = $credits = 0;
        $reversal = [];
        foreach ($lines as $line) {
            $account = $line['account_id'] ?? null;
            $d = $this->cents($line['debit'] ?? null);
            $c = $this->cents($line['credit'] ?? null);
            if (!is_int($account) || $account < 1 || $d === null || $c === null ||
                ($d > 0 && $c > 0) || ($d === 0 && $c === 0)) {
                throw new \InvalidArgumentException('Invalid debit/credit line or account.');
            }
            $debits += $d;
            $credits += $c;
            $reversal[] = [
                'account_id' => $account,
                'debit_cents' => $c,
                'credit_cents' => $d,
            ];
        }

        if ($debits === 0 || $debits !== $credits) {
            throw new \InvalidArgumentException('Original posting must balance before reversal planning.');
        }

        return [
            'source_kind' => $sourceKind,
            'project_id' => $projectId,
            'source_id' => $sourceId,
            'original_debit_cents' => $debits,
            'original_credit_cents' => $credits,
            'reversal_lines' => $reversal,
            'requires_approval' => true,
            'posting_enabled' => false,
        ];
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
