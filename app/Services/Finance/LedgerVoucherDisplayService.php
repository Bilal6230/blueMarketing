<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source-safe ledger voucher number display.
 * Only an independently reviewed attribution can authorize a header lookup.
 */
final class LedgerVoucherDisplayService
{
    public function display(object $ledger, int $projectId): string
    {
        $original = (string) ($ledger->type ?? '') . '-' . (string) ($ledger->voucher_number ?? '');
        if ($projectId < 1 || ($ledger->type ?? null) !== 'JV'
            || !isset($ledger->id) || !Schema::hasTable('ledger_source_attributions')) {
            return $original;
        }

        // Re-check real ledger ownership, not only a potentially stale or
        // forged attribution record or caller-provided ledger object.
        $ledgerOwned = DB::table('ledgers')->where('id', $ledger->id)
            ->where('type', 'JV')->whereIn('project_head_subheads_id', function ($query) use ($projectId) {
                $query->select('id')->from('project_head_subheads')->where('project_id', $projectId);
            })->exists();
        if (!$ledgerOwned) {
            return $original;
        }
        $attribution = DB::table('ledger_source_attributions')
            ->where('ledger_id', $ledger->id)->first();
        if (!$attribution
            || (int) $attribution->project_id !== $projectId
            || !in_array($attribution->source_kind, ['journal_voucher', 'commission_voucher'], true)
            || !in_array($attribution->evidence_kind, [
                'new_write_transaction', 'verified_external_reference', 'manual_review',
            ], true)
            || empty($attribution->source_id)
            || (int) ($ledger->type_id ?? 0) !== (int) $attribution->source_id) {
            return $original;
        }

        $table = $attribution->source_kind === 'journal_voucher'
            ? 'journal_vouchers' : 'commision_vouchers';
        if (!Schema::hasTable($table)) {
            return $original;
        }
        $header = DB::table($table)->where('id', $attribution->source_id)
            ->where('project_id', $projectId)->first(['id', 'project_id', 'voucher_number']);
        if (!$header || (string) $header->voucher_number === '') {
            return $original;
        }

        $prefix = $attribution->source_kind === 'journal_voucher' ? 'JV' : 'CV';
        return $prefix . '-' . $header->voucher_number;
    }
}
