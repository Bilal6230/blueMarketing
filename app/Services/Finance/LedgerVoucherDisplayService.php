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

        $attribution = DB::table('ledger_source_attributions')
            ->where('ledger_id', $ledger->id)->first();
        if (!$attribution
            || (int) $attribution->project_id !== $projectId
            || !in_array($attribution->source_kind, ['journal_voucher', 'commission_voucher'], true)
            || !in_array($attribution->evidence_kind, [
                'new_write_transaction', 'verified_external_reference', 'manual_review',
            ], true)
            || empty($attribution->source_id)) {
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
