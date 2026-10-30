<?php

namespace App\Services\Finance;

/**
 * Isolated V2-only project trial balance. Reads typed source-linked V2
 * posting lines (including immutable reversals), never legacy type_id.
 *
 * All arithmetic is integer cents. No FLOAT, implicit currency conversion,
 * database mutation, V1 controller, or inferred historical source ownership.
 */
final class V2ProjectTrialBalanceReader
{
    private function cents(mixed $value): int
    {
        $v = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $v, $m)) {
            throw new \DomainException('V2_TRIAL_BALANCE_INVALID_MONEY');
        }
        return ((int) $m[1] * 100) + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        if ($cents === PHP_INT_MIN) {
            throw new \DomainException('V2_TRIAL_BALANCE_OVERFLOW');
        }
        $prefix = $cents < 0 ? '-' : '';
        $value = abs($cents);

        return $prefix . intdiv($value, 100) . '.'
            . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    private function add(int $a, int $b): int
    {
        if ($b > 0 && $a > PHP_INT_MAX - $b) {
            throw new \DomainException('V2_TRIAL_BALANCE_OVERFLOW');
        }
        if ($b < 0 && $a < PHP_INT_MIN - $b) {
            throw new \DomainException('V2_TRIAL_BALANCE_OVERFLOW');
        }
        return $a + $b;
    }

    private function validateDate(?string $date): void
    {
        if ($date === null) {
            return;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('V2_TRIAL_BALANCE_DATE_INVALID');
        }
    }

    /**
     * Returns account-level opening, selected-period, and closing balance
     * as signed debit-minus-credit strings. "opening" includes all V2
     * postings prior to $from; reversals apply on their effective date.
     *
     * This is posted-V2-only, NOT a V1/V2 historical account reconciliation.
     */
    public function summarize(
        int $projectId,
        ?string $from = null,
        ?string $through = null
    ): array {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('V2_TRIAL_BALANCE_PROJECT_INVALID');
        }
        $this->validateDate($from);
        $this->validateDate($through);
        if ($from !== null && $through !== null && $from > $through) {
            throw new \InvalidArgumentException('V2_TRIAL_BALANCE_DATE_ORDER_INVALID');
        }

        // V2PostingReportReader verifies account/project ownership, typed
        // sources, balance, reversal lineage and exact original inverse legs.
        $lines = (new V2PostingReportReader())->lines($projectId, null, $through);
        $accounts = [];
        $totals = [
            'opening_debit' => 0, 'opening_credit' => 0,
            'period_debit' => 0, 'period_credit' => 0,
        ];
        foreach ($lines as $line) {
            $account = (int) $line['project_head_subhead_id'];
            $period = ($from !== null && (string) $line['effective_date'] < $from)
                ? 'opening' : 'period';
            $accounts[$account] ??= [
                'account_id' => $account,
                'opening_debit' => 0, 'opening_credit' => 0,
                'period_debit' => 0, 'period_credit' => 0,
            ];
            foreach (['debit', 'credit'] as $side) {
                $amount = $this->cents($line[$side]);
                $key = $period . '_' . $side;
                $accounts[$account][$key] = $this->add($accounts[$account][$key], $amount);
                $totals[$key] = $this->add($totals[$key], $amount);
            }
        }
        $report = [];
        foreach ($accounts as $account) {
            $opening = $this->add($account['opening_debit'], -$account['opening_credit']);
            $periodNet = $this->add($account['period_debit'], -$account['period_credit']);
            $closing = $this->add($opening, $periodNet);
            $report[] = [
                'account_id' => $account['account_id'],
                'opening_balance' => $this->money($opening),
                'period_debit' => $this->money($account['period_debit']),
                'period_credit' => $this->money($account['period_credit']),
                'closing_balance' => $this->money($closing),
            ];
        }
        usort($report, static fn ($a, $b): int => $a['account_id'] <=> $b['account_id']);
        $openingDiff = $this->add($totals['opening_debit'], -$totals['opening_credit']);
        $periodDiff = $this->add($totals['period_debit'], -$totals['period_credit']);
        if ($openingDiff !== 0 || $periodDiff !== 0) {
            throw new \DomainException('V2_TRIAL_BALANCE_GLOBAL_IMBALANCE');
        }
        return [
            'project_id' => $projectId,
            'from' => $from,
            'through' => $through,
            'scope' => 'typed_v2_postings_only',
            'accounts' => $report,
            'total_period_debit' => $this->money($totals['period_debit']),
            'total_period_credit' => $this->money($totals['period_credit']),
            'balanced' => true,
        ];
    }
}
