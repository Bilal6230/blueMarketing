<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An explicit, append-only source attribution writer for NEW transaction-owned rows.
 * Never reclassifies historical ledger rows, and never trusts ledgers.type_id.
 */
final class LedgerSourceAttributionWriter
{
    public function recordNewPosting(
        int $ledgerId,
        int $projectId,
        string $sourceKind,
        int $sourceId
    ): void {
        if ($ledgerId < 1 || $projectId < 1 || $sourceId < 1
            || !in_array($sourceKind, ['journal_voucher', 'commission_voucher'], true)) {
            throw new \InvalidArgumentException('Valid typed finance posting identity required.');
        }

        if (!Schema::hasTable('ledger_source_attributions')) {
            throw new \RuntimeException('Finance source attribution schema is not installed.');
        }

        $headerTable = $sourceKind === 'journal_voucher' ? 'journal_vouchers' : 'commision_vouchers';
        $headerQuery = DB::table($headerTable)->where('id', $sourceId)
            ->where('project_id', $projectId);
        if ($sourceKind === 'journal_voucher') {
            $headerQuery->whereNull('deleted_at');
        }
        $headerExists = $headerQuery->exists();

        $ledgerExists = DB::table('ledgers')->where('id', $ledgerId)
            ->where('type', 'JV')->where('type_id', $sourceId)
            ->whereIn('project_head_subheads_id', function ($query) use ($projectId) {
                $query->select('id')->from('project_head_subheads')->where('project_id', $projectId);
            })->exists();

        if (!$headerExists || !$ledgerExists) {
            throw new \DomainException('Typed header and ledger must both belong to the same project.');
        }

        // Unique ledger_id rejects duplicates and preserves immutable source provenance.
        // Caller must wrap posting, ledger creation and attribution in ONE DB transaction.
        DB::table('ledger_source_attributions')->insert([
            'ledger_id' => $ledgerId,
            'project_id' => $projectId,
            'source_kind' => $sourceKind,
            'source_id' => $sourceId,
            'evidence_kind' => 'new_write_transaction',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
