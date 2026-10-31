<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2OriginalCashReport
{
    public function documents(int $project, bool $incoming, ?string $from, ?string $through): array
    {
        $reader = new V2OriginalLedgerReport();
        $lines = collect($reader->rows($project, $from, $through))->where('source_kind', 'cash');
        $sources = DB::table('customer_ledger')->where('project_id', $project)->whereIn('id', $lines->pluck('source_id'))
            ->whereIn('transaction_type', $incoming ? ['CR', 'PPR', 'ECP'] : ['CP'])->get()->keyBy('id');
        $result = [];
        foreach ($lines->groupBy('source_id') as $id => $group) {
            $source = $sources->get($id); if (!$source) { continue; }
            $first = $group->first(); $sign = 0;
            foreach ($group->unique('posting_batch_id') as $batch) { $sign += $batch['is_reversal'] ? -1 : 1; }
            if (abs($sign) > 1) { throw new \DomainException('V2_CASH_REPORT_DUPLICATE_SOURCE'); }
            $amount = $reader->money(max($source->amount_in, $source->amount_out));
            $minor = (int) str_replace('.', '', $amount) * $sign;
            $net = ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
            $result[] = [
                'id' => 'SRC-'.$id, 'source_id' => (int) $id, 'type' => $source->transaction_type,
                'date' => $group->max('date'), 'voucher_number' => $first['display_voucher_number'],
                'project' => $first['project_name'], 'head' => $group->pluck('head_account_name')->unique()->implode(' / '),
                'subhead' => $group->pluck('subhead_account_name')->unique()->implode(' / '),
                'head_ids' => $group->pluck('head_accounting_id')->unique()->all(),
                'subhead_ids' => $group->pluck('subhead_accounting_id')->unique()->all(),
                'detail' => $source->description ?? '', 'amount' => $net, 'original_amount' => $amount,
                'status' => $group->contains('is_reversal', true) ? 'Reversed' : 'Posted',
                'submitted_by' => $first['accountent_name'], 'old_values' => [], 'new_values' => [],
                'print_url' => route('finance.voucher.source.print', ['customerLedger' => $id]), 'scope' => 'typed_v2_postings_only',
            ];
        }
        return $result;
    }
}
