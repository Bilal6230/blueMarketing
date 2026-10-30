<?php

namespace App\Services\Finance;

/**
 * Date-filtered source-family summary for isolated V2 postings, including
 * immutable reversals separately. Never reads ambiguous legacy type_id.
 */
final class V2PostingSummaryReader
{
    private function cents(mixed $raw): int
    {
        $value = (string) $raw;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new \DomainException('V2_SUMMARY_MONEY_INVALID');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function add(int $a, int $b): int
    {
        if ($a > PHP_INT_MAX - $b) {
            throw new \DomainException('V2_SUMMARY_OVERFLOW');
        }
        return $a + $b;
    }

    public function summarize(
        int $projectId, ?string $from = null, ?string $through = null
    ): array {
        $lines = (new V2PostingReportReader())->lines($projectId, $from, $through);
        $groups = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lines as $line) {
            $reversed = (bool) $line['is_reversal'];
            $family = (string) $line['source_kind'];
            $series = (string) $line['voucher_series'];
            $groupKey = $family . '|' . $series . '|' . ($reversed ? 'reverse' : 'original');
            $groups[$groupKey] ??= [
                'source_kind' => $family,
                'voucher_series' => $series,
                'is_reversal' => $reversed,
                'batches' => [],
                'debit' => 0,
                'credit' => 0,
            ];
            $debit = $this->cents($line['debit']);
            $credit = $this->cents($line['credit']);
            $groups[$groupKey]['debit'] = $this->add($groups[$groupKey]['debit'], $debit);
            $groups[$groupKey]['credit'] = $this->add($groups[$groupKey]['credit'], $credit);
            $groups[$groupKey]['batches'][(int) $line['posting_batch_id']] = true;
            $totalDebit = $this->add($totalDebit, $debit);
            $totalCredit = $this->add($totalCredit, $credit);
        }
        if ($totalDebit !== $totalCredit) {
            throw new \DomainException('V2_SUMMARY_UNBALANCED');
        }
        ksort($groups);
        $result = [];
        $allBatches = [];
        foreach ($groups as $group) {
            foreach ($group['batches'] as $id => $_) {
                $allBatches[$id] = true;
            }
            $result[] = [
                'source_kind' => $group['source_kind'],
                'voucher_series' => $group['voucher_series'],
                'is_reversal' => $group['is_reversal'],
                'document_count' => count($group['batches']),
                'total_debit' => $this->money($group['debit']),
                'total_credit' => $this->money($group['credit']),
            ];
        }
        return [
            'project_id' => $projectId,
            'from' => $from, 'through' => $through,
            'scope' => 'typed_v2_postings_only',
            'posting_batch_count' => count($allBatches),
            'total_debit' => $this->money($totalDebit),
            'total_credit' => $this->money($totalCredit),
            'groups' => $result,
        ];
    }
}
