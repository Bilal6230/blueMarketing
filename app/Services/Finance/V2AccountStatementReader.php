<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Isolated V2 account statement: exact typed source and immutable reversal
 * lineage, with opening/closing and running account balance in integer cents.
 * Never reads legacy ledgers.type/type_id/reference as a relation.
 */
final class V2AccountStatementReader
{
    private function cents(mixed $value): int
    {
        $s = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $s, $m)) {
            throw new \DomainException('V2_ACCOUNT_STATEMENT_INVALID_AMOUNT');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function add(int $first, int $second): int
    {
        if (($second > 0 && $first > PHP_INT_MAX - $second)
            || ($second < 0 && $first < PHP_INT_MIN - $second)) {
            throw new \DomainException('V2_ACCOUNT_STATEMENT_OVERFLOW');
        }
        return $first + $second;
    }

    private function amount(int $value): string
    {
        if ($value === PHP_INT_MIN) {
            throw new \DomainException('V2_ACCOUNT_STATEMENT_OVERFLOW');
        }
        $positive = abs($value);
        return ($value < 0 ? '-' : '')
            . intdiv($positive, 100) . '.'
            . str_pad((string) ($positive % 100), 2, '0', STR_PAD_LEFT);
    }

    private function checkedDate(?string $date): void
    {
        if ($date === null) {
            return;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('V2_ACCOUNT_STATEMENT_INVALID_DATE');
        }
    }

    public function lines(
        int $projectId,
        int $accountId,
        ?string $from = null,
        ?string $through = null
    ): array {
        V2IsolatedConnectionGuard::assertAllowed();
        if ($projectId < 1 || $accountId < 1) {
            throw new \InvalidArgumentException('V2_ACCOUNT_STATEMENT_ID_INVALID');
        }
        $this->checkedDate($from);
        $this->checkedDate($through);
        if ($from !== null && $through !== null && $from > $through) {
            throw new \InvalidArgumentException('V2_ACCOUNT_STATEMENT_DATE_ORDER_INVALID');
        }
        if (!DB::table('project_head_subheads')
            ->where('id', $accountId)->where('project_id', $projectId)->exists()) {
            throw new \DomainException('V2_ACCOUNT_STATEMENT_PROJECT_OWNERSHIP_MISMATCH');
        }

        // The shared V2 reader verifies every typed claim, project identity,
        // balanced batch and reversal exact inverse before returning lines.
        $all = (new V2PostingReportReader())->lines($projectId, null, $through);
        $balance = 0;
        $periodDebit = 0;
        $periodCredit = 0;
        $records = [];
        foreach ($all as $line) {
            if ((int) $line['project_head_subhead_id'] !== $accountId) {
                continue;
            }
            $debit = $this->cents($line['debit']);
            $credit = $this->cents($line['credit']);
            $net = $this->add($debit, -$credit);
            if ($from !== null && (string) $line['effective_date'] < $from) {
                $balance = $this->add($balance, $net);
                continue;
            }
            $periodDebit = $this->add($periodDebit, $debit);
            $periodCredit = $this->add($periodCredit, $credit);
            $balance = $this->add($balance, $net);
            $records[] = [
                'posting_line_id' => (int) $line['posting_line_id'],
                'posting_batch_id' => (int) $line['posting_batch_id'],
                'source_kind' => (string) $line['source_kind'],
                'source_id' => (int) $line['source_id'],
                'effective_date' => (string) $line['effective_date'],
                'is_reversal' => (bool) $line['is_reversal'],
                'reversal_of_batch_id' => $line['reversal_of_batch_id'] === null
                    ? null : (int) $line['reversal_of_batch_id'],
                'debit' => $this->amount($debit),
                'credit' => $this->amount($credit),
                'running_balance' => $this->amount($balance),
            ];
        }
        $periodNet = $this->add($periodDebit, -$periodCredit);
        $opening = $this->add($balance, -$periodNet);
        return [
            'project_id' => $projectId,
            'account_id' => $accountId,
            'from' => $from,
            'through' => $through,
            'scope' => 'typed_v2_postings_only',
            'opening_balance' => $this->amount($opening),
            'period_debit' => $this->amount($periodDebit),
            'period_credit' => $this->amount($periodCredit),
            'closing_balance' => $this->amount($balance),
            'lines' => $records,
        ];
    }
}
