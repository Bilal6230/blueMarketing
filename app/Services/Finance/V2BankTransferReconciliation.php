<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Read-only audit of isolated V2 Bank Deposit / Withdrawal batches.
 * Never reconciles by mutating a voucher, source or posting line.
 */
final class V2BankTransferReconciliation
{
    /** @return array<int,array<string,mixed>> */
    public function audit(int $projectId): array
    {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Positive project id is required.');
        }
        V2IsolatedConnectionGuard::assertAllowed();

        $sources = DB::table('finance_bank_transfer_sources as transfer')
            ->leftJoin('finance_posting_batches as batch', 'batch.id', '=', 'transfer.posting_batch_id')
            ->leftJoin('project_bank_accounts as bank', 'bank.id', '=', 'transfer.bank_account_id')
            ->where('transfer.project_id', $projectId)
            ->orderBy('transfer.posting_batch_id')
            ->get([
                'transfer.posting_batch_id', 'transfer.project_id', 'transfer.bank_account_id',
                'transfer.transfer_kind', 'transfer.amount', 'transfer.linked_customer_ledger_id',
                'batch.project_id as batch_project_id', 'batch.voucher_series',
                'bank.project_id as bank_project_id', 'bank.account_mapping_id as bank_mapping_id',
            ]);

        $result = [];
        foreach ($sources as $source) {
            $batchId = (int) $source->posting_batch_id;
            $issues = [];
            $expectedDebit = $source->transfer_kind === 'deposit';
            if (!in_array($source->transfer_kind, ['deposit', 'withdrawal'], true)) {
                $issues[] = 'INVALID_TRANSFER_KIND';
            }
            if ((int) $source->batch_project_id !== $projectId) {
                $issues[] = 'BATCH_PROJECT_MISMATCH';
            }
            if ((int) $source->bank_project_id !== $projectId || !$source->bank_mapping_id) {
                $issues[] = 'BANK_PROJECT_OR_MAPPING_MISMATCH';
            }
            if ($source->voucher_series !== ($expectedDebit ? 'BD' : 'BW')) {
                $issues[] = 'VOUCHER_SERIES_MISMATCH';
            }

            $lines = DB::table('finance_posting_lines as line')
                ->leftJoin('project_head_subheads as account', 'account.id', '=', 'line.project_head_subhead_id')
                ->where('line.posting_batch_id', $batchId)
                ->orderBy('line.id')
                ->get(['line.project_head_subhead_id', 'line.debit', 'line.credit', 'account.project_id as account_project_id']);

            $debit = 0;
            $credit = 0;
            $bankCount = 0;
            $bankAmount = 0;
            $oppositeAmount = 0;
            foreach ($lines as $line) {
                $dr = $this->cents($line->debit);
                $cr = $this->cents($line->credit);
                if ($dr < 0 || $cr < 0 || ($dr > 0 && $cr > 0) || ($dr === 0 && $cr === 0)) {
                    $issues[] = 'INVALID_LINE_SIDES';
                }
                if ((int) $line->account_project_id !== $projectId) {
                    $issues[] = 'LINE_PROJECT_MISMATCH';
                }
                $debit += $dr;
                $credit += $cr;
                if ($source->bank_mapping_id && (int) $line->project_head_subhead_id === (int) $source->bank_mapping_id) {
                    $bankCount++;
                    $bankAmount += $expectedDebit ? $dr : $cr;
                    if ($expectedDebit ? $cr > 0 : $dr > 0) {
                        $issues[] = 'BANK_DIRECTION_MISMATCH';
                    }
                } else {
                    $oppositeAmount += $expectedDebit ? $cr : $dr;
                    if ($expectedDebit ? $dr > 0 : $cr > 0) {
                        $issues[] = 'CASH_DIRECTION_MISMATCH';
                    }
                }
            }
            if ($bankCount !== 1 || $lines->count() < 2) {
                $issues[] = 'BANK_OR_LINE_COUNT_MISMATCH';
            }
            if ($debit !== $credit) {
                $issues[] = 'UNBALANCED_POSTING';
            }
            $amount = $this->cents($source->amount);
            if ($amount < 1 || $bankAmount !== $amount || $oppositeAmount !== $amount) {
                $issues[] = 'SOURCE_AMOUNT_MISMATCH';
            }
            $claimKinds = DB::table('finance_posting_source_claims')
                ->where('posting_batch_id', $batchId)->pluck('source_kind')->all();
            if ($claimKinds !== ['bank_transfer']) {
                $issues[] = 'SOURCE_CLAIM_MISMATCH';
            }
            $result[] = [
                'posting_batch_id' => $batchId,
                'voucher' => ($source->voucher_series ?: '?').'-'.$batchId,
                'kind' => $source->transfer_kind,
                'amount' => $this->money($amount),
                'debit' => $this->money($debit),
                'credit' => $this->money($credit),
                'line_count' => $lines->count(),
                'issues' => array_values(array_unique($issues)),
                'ok' => $issues === [],
            ];
        }
        return $result;
    }

    private function cents(mixed $amount): int
    {
        $value = (string) ($amount ?? '0');
        if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,2}))?$/D', $value, $match)) {
            throw new \DomainException('V2_BANK_TRANSFER_AUDIT_INVALID_MONEY');
        }
        $cents = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
