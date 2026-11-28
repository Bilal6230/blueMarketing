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
        // Resolve the voucher's retained source FK before consulting legacy
        // type/type_id attributions. This works for all selected projects and
        // cannot mislabel a similarly-numbered voucher in another family.
        if ($projectId > 0 && isset($ledger->id) && Schema::hasTable('ledgers')
            && Schema::hasTable('project_head_subheads')
            && Schema::hasColumn('ledgers','customer_ledger_id')
            && Schema::hasColumn('ledgers','journal_voucher_detail_id')
            && Schema::hasColumn('ledgers','commision_voucher_detail_id')) {
            $owned = DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('l.id',(int)$ledger->id)->where('a.project_id',$projectId)
                ->first(['l.type','l.type_id','l.voucher_number','l.customer_ledger_id',
                    'l.journal_voucher_detail_id','l.commision_voucher_detail_id']);
            if ($owned) {
                $type = (string)$owned->type;
                if ((int)$owned->customer_ledger_id > 0 && Schema::hasTable('customer_ledger')) {
                    $source = DB::table('customer_ledger')
                        ->where('id',(int)$owned->customer_ledger_id)
                        ->where('project_id',$projectId)
                        ->first(['transaction_type','type_id']);
                    if ($source && (int)$source->type_id > 0
                        && $type === (string)$source->transaction_type) {
                        // Immutable source mapping overrides the old canonical
                        // ledger label, without altering accounting rows.
                        $display=app(ProjectVoucherDisplayIdentity::class)
                            ->lookup($projectId,'customer_ledger',(int)$owned->customer_ledger_id);
                        if ($display !== null) return $display;
                        if ($type === 'PPR' && Schema::hasTable('booking_vouchers')) {
                            $booking = DB::table('booking_vouchers')
                                ->where('project_id',$projectId)
                                ->where('customer_ledger_id',(int)$owned->customer_ledger_id)
                                ->where('voucher_series','PPR')->value('voucher_number');
                            if ((int)$booking > 0) return 'PPR-'.(int)$booking;
                        }
                        if ($type === 'CP'
                            && Schema::hasTable('project_voucher_display_numbers')
                            && DB::table('project_voucher_display_numbers')
                                ->where('project_id',$projectId)
                                ->where('source_kind','customer_ledger')
                                ->where('source_id',(int)$owned->customer_ledger_id)
                                ->where('series','DR')->exists()) {
                            return 'DR-'.(int)$source->type_id;
                        }
                        return $type.'-'.(int)$source->type_id;
                    }
                    // Preserve old CP ledger/source identities in their
                    // historical debit-voucher display without rewriting rows.
                    if ($source && $type === 'CP'
                        && (string)$source->transaction_type === 'CP'
                        && (int)$source->type_id > 0) {
                        $display=app(ProjectVoucherDisplayIdentity::class)
                            ->lookup($projectId,'customer_ledger',(int)$owned->customer_ledger_id);
                        return $display ?: 'DR-'.(int)$source->type_id;
                    }
                }
                if (in_array($type,['JV','SV'],true) && (int)$owned->journal_voucher_detail_id > 0
                    && Schema::hasTable('journal_voucher_details')
                    && Schema::hasTable('journal_vouchers')) {
                    $header = DB::table('journal_voucher_details as d')
                        ->join('journal_vouchers as h','h.id','=','d.journal_voucher_id')
                        ->where('d.id',(int)$owned->journal_voucher_detail_id)
                        ->where('h.project_id',$projectId)->where('h.type',$type)
                        ->where('h.id',(int)$owned->type_id)->first(['h.id','h.voucher_number']);
                    if ($header && (int)$header->voucher_number > 0) {
                        $display=app(ProjectVoucherDisplayIdentity::class)
                            ->lookup($projectId,'journal_voucher',(int)$header->id);
                        if ($display !== null) return $display;
                        return $type === 'JV'
                            ? 'JV-'.str_pad((string)(int)$header->voucher_number,7,'0',STR_PAD_LEFT)
                            : 'SV-'.(int)$header->voucher_number;
                    }
                }
                if ($type === 'CV' && (int)$owned->commision_voucher_detail_id > 0
                    && Schema::hasTable('commision_voucher_details')
                    && Schema::hasTable('commision_vouchers')) {
                    $header = DB::table('commision_voucher_details as d')
                        ->join('commision_vouchers as h','h.id','=','d.commision_voucher_id')
                        ->where('d.id',(int)$owned->commision_voucher_detail_id)
                        ->where('h.project_id',$projectId)
                        ->where('h.id',(int)$owned->type_id)->first(['h.id','h.voucher_number']);
                    if ($header && (string)$header->voucher_number !== '') {
                        $display=app(ProjectVoucherDisplayIdentity::class)
                            ->lookup($projectId,'commission_voucher',(int)$header->id);
                        return $display ?: CommissionVoucherNumber::display($header->voucher_number);
                    }
                }
            }
        }
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

        $display=app(ProjectVoucherDisplayIdentity::class)->lookup(
            $projectId,$attribution->source_kind,(int)$attribution->source_id);
        if ($display !== null) return $display;
        $prefix = $attribution->source_kind === 'journal_voucher' ? 'JV' : 'CV';
        return $prefix . '-' . $header->voucher_number;
    }
}
