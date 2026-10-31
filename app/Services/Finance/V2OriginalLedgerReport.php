<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2OriginalLedgerReport
{
    public function money(mixed $value): string
    {
        if (!preg_match('/^([0-9]{1,15})(?:\.([0-9]{1,2}))?$/D', (string) $value, $m)) {
            throw new \DomainException('V2_REPORT_AMOUNT_INVALID');
        }
        return (string) (int) $m[1].'.'.str_pad($m[2] ?? '', 2, '0');
    }

    public function rows(int $projectId, ?string $from, ?string $through): array
    {
        $lines = (new V2PostingGateway())->postingLines($projectId, $from, $through);
        $accounts = \App\Models\ProjectHeadSubhead::withTrashed()->with(['headAccounting', 'subheadAccounting', 'project'])
            ->where('project_id', $projectId)->whereIn('id', array_column($lines, 'project_head_subhead_id'))->get()->keyBy('id');
        $batches = DB::table('finance_posting_batches')->where('project_id', $projectId)
            ->whereIn('id', array_column($lines, 'posting_batch_id'))->get()->keyBy('id');
        $actors = \App\Models\User::withTrashed()->whereIn('id', $batches->pluck('posted_by')->filter())->get()->keyBy('id');
        $documents = [];
        foreach (['journal' => 'journal_vouchers', 'commission' => 'commision_vouchers', 'cash' => 'customer_ledger',
            'booking' => 'booking_vouchers', 'labour' => 'labour_ledgers', 'stock_entry' => 'stock_entries', 'stock_bill' => 'stock_bills'] as $kind => $table) {
            $ids = array_column(array_filter($lines, fn ($line) => $line['source_kind'] === $kind), 'source_id');
            if ($ids) { $documents[$kind] = DB::table($table)->whereIn('id', $ids)->get()->keyBy('id'); }
        }
        return array_map(function ($line) use ($accounts, $batches, $actors, $documents): array {
            $account = $accounts->get($line['project_head_subhead_id']);
            $batch = $batches->get($line['posting_batch_id']);
            $series = (string) $line['voucher_series'];
            $number = $line['voucher_number'] ?? '#'.$line['source_id'];
            $source = ($documents[$line['source_kind']] ?? collect())->get($line['source_id']);
            $display = $line['is_reversal'] ? 'REV-'.$line['posting_batch_id']
                : ($series === 'CV' ? CommissionVoucherNumber::display($number) : $series.'-'.$number);
            return [
                'id' => 'V2-'.$line['posting_line_id'], 'posting_line_id' => (int) $line['posting_line_id'],
                'display_voucher_number' => $display, 'voucher_number' => $display,
                'date' => $line['effective_date'], 'type' => $series,
                'amount_in' => $this->money($line['debit']),
                'amount_out' => $this->money($line['credit']),
                'detail' => $line['description'] ?? ($source->description ?? ''), 'reference' => $source->reference ?? '',
                'project_name' => $account?->project?->project ?? '',
                'accountent_name' => $actors->get($batch->posted_by)?->name ?? '',
                'head_account_name' => $account?->headAccounting?->name ?? '',
                'subhead_account_name' => $account?->subheadAccounting?->name ?? '',
                'project_head_subheads_id' => (int) $line['project_head_subhead_id'],
                'head_accounting_id' => $account?->head_accounting_id,
                'subhead_accounting_id' => $account?->subhead_accounting_id,
                'posting_batch_id' => (int) $line['posting_batch_id'],
                'acct_type' => $account?->headAccounting?->acct_type,
                'source_kind' => $line['source_kind'], 'source_id' => $line['source_id'],
                'is_reversal' => $line['is_reversal'], 'action' => '', 'scope' => 'typed_v2_postings_only',
            ];
        }, $lines);
    }

    public function totals(array $rows, string $group): array
    {
        $types = $group === 'acct_type' ? \App\Models\AccountType::query()->pluck('name', 'id') : collect();
        $result = [];
        foreach ($rows as $row) {
            $key = (string) ($row[$group] ?? 'unknown');
            $result[$key] ??= $row + ['debit_cents' => 0, 'credit_cents' => 0];
            foreach (['amount_in' => 'debit_cents', 'amount_out' => 'credit_cents'] as $field => $total) {
                $cents = (int) str_replace('.', '', $row[$field]);
                if ($result[$key][$total] > PHP_INT_MAX - $cents) { throw new \DomainException('V2_REPORT_AMOUNT_OVERFLOW'); }
                $result[$key][$total] += $cents;
            }
        }
        $money = static fn (int $c): string => ($c < 0 ? '-' : '').intdiv(abs($c), 100).'.'.str_pad((string) (abs($c) % 100), 2, '0', STR_PAD_LEFT);
        foreach ($result as &$row) {
            if ($group === 'acct_type') { $row['head_account_name'] = $types->get($row['acct_type'], 'Unclassified'); }
            $row['total_amount_in'] = $money($row['debit_cents']);
            $row['total_amount_out'] = $money($row['credit_cents']);
            $row['balance_amount'] = $money($row['debit_cents'] - $row['credit_cents']);
            unset($row['debit_cents'], $row['credit_cents']);
        }
        return array_values($result);
    }
}
