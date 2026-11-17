<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\{DB, Schema};

final class StockApprovalWorkflow
{
    public function approveAndPost(int $projectId, string $kind, int $sourceId, string $key,
        string $evidence, array $lines, ?int $actorId): int
    {
        FinanceConnectionGuard::assertAllowed();
        if (!in_array($kind, ['stock_entry', 'stock_bill'], true)) {
            throw new \DomainException('V2_STOCK_KIND_INVALID');
        }
        return DB::transaction(function () use ($projectId, $kind, $sourceId, $key, $evidence, $lines, $actorId): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $reference = ($kind === 'stock_entry' ? 'STOCK-ENTRY:' : 'STOCK-BILL:').$sourceId;
            foreach (['ledgers', 'customer_ledger'] as $table) {
                if (Schema::hasColumn($table, 'reference') && DB::table($table)->where('reference', $reference)->exists()) {
                    throw new \DomainException('V2_STOCK_LEGACY_POSTING_EXISTS');
                }
            }
            (new StockProjectApprovalWriter())->approve($kind, $sourceId, $projectId, $evidence);
            $gateway = new PostingGateway();
            return $kind === 'stock_entry'
                ? $gateway->postStockEntry($projectId, $sourceId, $key, $lines, $actorId)
                : $gateway->postStockBill($projectId, $sourceId, $key, $lines, $actorId);
        }, 3);
    }
}
