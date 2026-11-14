<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/** Read-only isolated-project PP posting integrity audit; never fixes entries. */
final class V2PlotPurchaseReconciliation
{
    /** @return list<array<string,mixed>> */
    public function audit(int $projectId): array
    {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Positive project id required.');
        }
        V2IsolatedConnectionGuard::assertAllowed();

        $purchases = DB::table('finance_plot_purchases as p')
            ->leftJoin('finance_plot_purchase_postings as link', 'link.source_id', '=', 'p.id')
            ->leftJoin('finance_posting_batches as batch', 'batch.id', '=', 'link.posting_batch_id')
            ->where('p.project_id', $projectId)
            ->orderBy('p.id')
            ->get([
                'p.id', 'p.purchase_number', 'p.amount', 'p.seller_account_id',
                'link.posting_batch_id', 'link.source_kind as linked_kind',
                'batch.project_id as batch_project_id', 'batch.voucher_series',
            ]);
        $results = [];
        foreach ($purchases as $purchase) {
            $batchId = (int) $purchase->posting_batch_id;
            $issues = [];
            if (!$batchId || (int) $purchase->batch_project_id !== $projectId) {
                $issues[] = 'MISSING_OR_FOREIGN_BATCH';
            }
            if ($purchase->voucher_series !== 'PP' || $purchase->linked_kind !== 'plot_purchase') {
                $issues[] = 'INVALID_POSTING_KIND';
            }
            $seller = (int) $purchase->seller_account_id;
            if (!DB::table('project_head_subheads')->where('id', $seller)->where('project_id', $projectId)->exists()) {
                $issues[] = 'SELLER_ACCOUNT_PROJECT_MISMATCH';
            }
            $items = DB::table('finance_plot_purchase_items as item')
                ->leftJoin('project_head_subheads as account', 'account.id', '=', 'item.inventory_account_id')
                ->leftJoin('plots as plot', 'plot.id', '=', 'item.legacy_plot_id')
                ->where('item.purchase_id', $purchase->id)->orderBy('item.id')
                ->get(['item.id','item.amount','item.inventory_account_id','item.legacy_plot_id',
                    'account.project_id as inventory_project_id','plot.project_id as plot_project_id']);
            $lines = $batchId ? DB::table('finance_posting_lines as line')
                ->leftJoin('project_head_subheads as account', 'account.id', '=', 'line.project_head_subhead_id')
                ->where('line.posting_batch_id', $batchId)->orderBy('line.id')
                ->get(['line.project_head_subhead_id','line.debit','line.credit',
                    'account.project_id as account_project_id']) : collect();
            $debit = 0; $credit = 0; $sellerCredits = 0;
            $inventory = []; $itemTotal = 0;
            foreach ($items as $item) {
                $itemTotal += $this->cents($item->amount);
                if ((int) $item->inventory_project_id !== $projectId ||
                    (int) $item->plot_project_id !== $projectId || !$item->legacy_plot_id) {
                    $issues[] = 'ITEM_ACCOUNT_OR_PLOT_PROJECT_MISMATCH';
                }
                $account = (int) $item->inventory_account_id;
                $inventory[$account] = ($inventory[$account] ?? 0) + $this->cents($item->amount);
            }
            $actualInventory = [];
            foreach ($lines as $line) {
                $dr = $this->cents($line->debit);
                $cr = $this->cents($line->credit);
                $debit += $dr; $credit += $cr;
                if ($dr < 0 || $cr < 0 || ($dr > 0 && $cr > 0) || ($dr === 0 && $cr === 0)) {
                    $issues[] = 'INVALID_POSTING_LINE';
                }
                if ((int) $line->account_project_id !== $projectId) {
                    $issues[] = 'LINE_PROJECT_MISMATCH';
                }
                $account = (int) $line->project_head_subhead_id;
                if ($account === $seller && $cr > 0) {
                    $sellerCredits += $cr;
                } elseif ($dr > 0) {
                    $actualInventory[$account] = ($actualInventory[$account] ?? 0) + $dr;
                } else {
                    $issues[] = 'INCORRECT_DEBIT_CREDIT_DIRECTION';
                }
            }
            ksort($inventory); ksort($actualInventory);
            if ($inventory !== $actualInventory || $items->isEmpty()) {
                $issues[] = 'INVENTORY_POSTING_MISMATCH';
            }
            $amount = $this->cents($purchase->amount);
            if ($amount < 1 || $amount !== $itemTotal || $amount !== $sellerCredits) {
                $issues[] = 'PURCHASE_OR_SELLER_AMOUNT_MISMATCH';
            }
            if ($debit !== $credit || $debit !== $amount || $lines->count() !== $items->count()+1) {
                $issues[] = 'POSTING_UNBALANCED_OR_LINE_COUNT';
            }
            $claims = $batchId ? DB::table('finance_posting_source_claims')
                ->where('posting_batch_id', $batchId)->pluck('source_kind')->all() : [];
            if ($claims !== ['plot_purchase']) {
                $issues[] = 'SOURCE_CLAIM_MISMATCH';
            }
            $issues = array_values(array_unique($issues));
            $results[] = [
                'id' => (int) $purchase->id, 'voucher' => $purchase->purchase_number,
                'batch' => $batchId, 'amount' => $this->money($amount),
                'debit' => $this->money($debit), 'credit' => $this->money($credit),
                'plots' => $items->count(), 'issues' => $issues, 'ok' => $issues === [],
            ];
        }
        return $results;
    }

    private function cents(mixed $value): int
    {
        $value = (string) ($value ?? '0');
        if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,2}))?$/D', $value, $match)) {
            throw new \DomainException('V2_PLOT_PURCHASE_AUDIT_INVALID_MONEY');
        }
        $result = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$result : $result;
    }

    private function money(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.
            str_pad((string)(abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
