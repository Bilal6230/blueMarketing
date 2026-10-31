<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Snapshot of exact original stock source + source lines for a separate
 * project-ownership approval. Stock tables have no project_id.
 * Never guess project from party, item, site, or polymorphic source_type.
 */
final class V2StockSourceSnapshot
{
    public function read(string $kind, int $sourceId): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!in_array($kind, ['stock_entry', 'stock_bill'], true) || $sourceId < 1) {
            throw new \DomainException('V2_STOCK_SOURCE_ID_INVALID');
        }
        $isEntry = $kind === 'stock_entry';
        $table = $isEntry ? 'stock_entries' : 'stock_bills';
        $lineTable = $isEntry ? 'stock_entry_lines' : 'stock_bill_lines';
        $lineFk = $isEntry ? 'entry_id' : 'bill_id';
        $source = DB::table($table)->where('id', $sourceId)->lockForUpdate()->first();
        if (!$source || $source->deleted_at !== null) {
            throw new \DomainException('V2_STOCK_SOURCE_MISSING_OR_DELETED');
        }
        if ($isEntry && (
            $source->billed_at !== null
            || DB::table('stock_bills')->where('entry_id', $sourceId)
                ->whereNull('deleted_at')->exists()
        )) {
            // Posting entry and its bill can produce a double financial claim.
            throw new \DomainException('V2_STOCK_ENTRY_ALREADY_BILLED');
        }
        if (!$isEntry && $source->entry_id !== null
            && DB::table('finance_posting_stock_entry_sources')
                ->where('stock_entry_id', $source->entry_id)->exists()) {
            throw new \DomainException('V2_STOCK_BILL_ENTRY_ALREADY_POSTED');
        }
        $lines = DB::table($lineTable)->where($lineFk, $sourceId)
            ->orderBy('id')->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            throw new \DomainException('V2_STOCK_SOURCE_LINES_MISSING');
        }
        $total = 0;
        $items = [];
        foreach ($lines as $line) {
            $value = (string) $line->amount_minor;
            if (!preg_match('/^(0|[1-9][0-9]{0,13})$/D', $value)) {
                throw new \DomainException('V2_STOCK_SOURCE_MINOR_INVALID');
            }
            $amount = (int) $value;
            if ($total > PHP_INT_MAX - $amount) {
                throw new \DomainException('V2_STOCK_SOURCE_OVERFLOW');
            }
            $total += $amount;
            $items[] = [
                'id' => (int) $line->id,
                'item_id' => (int) $line->item_id,
                'qty' => (string) $line->qty,
                'rate_minor' => (string) $line->rate_minor,
                'amount_minor' => $value,
            ];
        }
        if ($total < 1) {
            throw new \DomainException('V2_STOCK_SOURCE_TOTAL_ZERO');
        }
        if (!$isEntry && (string) $total !== (string) $source->total_minor) {
            throw new \DomainException('V2_STOCK_BILL_LINES_TOTAL_MISMATCH');
        }
        $date = $isEntry ? (string) $source->entry_date : (string) $source->bill_date;
        // Eloquent's DATE cast stores midnight text in SQLite; MySQL DATE
        // returns Y-m-d. Both represent the same business calendar date.
        if (preg_match('/^\d{4}-\d{2}-\d{2} 00:00:00$/D', $date)) {
            $date = substr($date, 0, 10);
        }
        $series = $isEntry
            ? (($source->flow === 'IN') ? 'ST-IN' : (($source->flow === 'OUT') ? 'ST-OUT' : null))
            : (($source->type === 'purchase') ? 'SB-PUR' : (($source->type === 'sale') ? 'SB-SALE' : null));
        if ($series === null || !$date) {
            throw new \DomainException('V2_STOCK_SOURCE_TYPE_INVALID');
        }
        $fingerprint = hash('sha256', json_encode([
            'kind' => $kind, 'source_id' => $sourceId,
            'date' => $date, 'series' => $series,
            'source_number' => (string) ($isEntry ? $source->slip_no : $source->bill_no),
            'party_id' => (int) $source->party_id,
            'linked_entry_id' => $isEntry ? null : $source->entry_id,
            'amount_minor' => $total, 'source_lines' => $items,
        ], JSON_THROW_ON_ERROR));

        return [
            'source_kind' => $kind, 'source_id' => $sourceId,
            'date' => $date, 'series' => $series,
            'amount_cents' => $total, 'fingerprint' => $fingerprint,
        ];
    }
}
