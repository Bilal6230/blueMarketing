<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * A committed idempotency reservation cannot be treated as a successful JV
 * replay when typed ledger evidence has subsequently become inconsistent.
 * This inspector is strictly read-only.
 */
final class JournalVoucherReplayIntegrity
{
    public function assertIntact(int $projectId, int $voucherId): void
    {
        $voucher = DB::table('journal_vouchers')->where('id', $voucherId)
            ->where('project_id', $projectId)->whereNull('deleted_at')
            ->first(['id', 'total_debit', 'total_credit']);
        if (!$voucher) {
            throw new \RuntimeException('Committed JV replay references a missing voucher.');
        }

        $details = DB::table('journal_voucher_details')
            ->where('journal_voucher_id', $voucherId)->whereNull('deleted_at')->get(['account_id','debit','credit']);
        if ($details->count() < 2) {
            throw new \RuntimeException('Committed JV replay is missing posting details.');
        }

        $ledgerRows = DB::table('ledgers as l')
            ->join('project_head_subheads as p', 'p.id', '=', 'l.project_head_subheads_id')
            ->leftJoin('ledger_source_attributions as a', 'a.ledger_id', '=', 'l.id')
            ->where('l.type', 'JV')->where('l.type_id', $voucherId)
            ->where('p.project_id', $projectId)
            ->get(['l.id', 'l.project_head_subheads_id', 'l.amount_in', 'l.amount_out',
                'a.project_id as attributed_project', 'a.source_kind', 'a.source_id']);
        // Scope every persisted row to its original project. A foreign-project
        // ledger pointing at the same numeric header is a blocker, not invisible.
        $allLedgerCount = DB::table('ledgers')->where('type', 'JV')
            ->where('type_id', $voucherId)->count();
        if ($allLedgerCount !== $ledgerRows->count()) {
            throw new \RuntimeException('Committed JV replay includes cross-project ledger links.');
        }

        if ($details->count() !== $ledgerRows->count()) {
            throw new \RuntimeException('Committed JV replay has a ledger/detail count mismatch.');
        }

        // Do not approve a replay whose source header was edited after commit.
        $detailDebit = 0;
        $detailCredit = 0;
        foreach ($details as $detail) {
            $detailDebit += $this->cents($detail->debit);
            $detailCredit += $this->cents($detail->credit);
        }
        if ($detailDebit <= 0 || $detailDebit !== $detailCredit ||
            $detailDebit !== $this->cents($voucher->total_debit) ||
            $detailCredit !== $this->cents($voucher->total_credit)) {
            throw new \RuntimeException('Committed JV replay has mismatched voucher totals.');
        }

        $expected = [];
        foreach ($details as $line) {
            $key = (int) $line->account_id.'|'.$this->cents($line->debit).'|'.$this->cents($line->credit);
            $expected[$key] = ($expected[$key] ?? 0) + 1;
        }
        foreach ($ledgerRows as $line) {
            if ((int) $line->attributed_project !== $projectId ||
                $line->source_kind !== 'journal_voucher' || (int) $line->source_id !== $voucherId) {
                throw new \RuntimeException('Committed JV replay has missing or conflicting source attribution.');
            }
            $key = (int) $line->project_head_subheads_id.'|'.$this->cents($line->amount_in).'|'.$this->cents($line->amount_out);
            if (($expected[$key] ?? 0) < 1) {
                throw new \RuntimeException('Committed JV replay has mismatched posting lines.');
            }
            $expected[$key]--;
        }
        if (array_sum($expected) !== 0) {
            throw new \RuntimeException('Committed JV replay has incomplete posting lines.');
        }
    }

    private function cents($amount): int
    {
        $value = (string) $amount;
        if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $value)) {
            throw new \RuntimeException('Committed JV replay contains an invalid amount.');
        }
        $parts = explode('.', $value);
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
