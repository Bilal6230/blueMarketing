<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A verified Online transaction can be corrected without replacing the
 * original immutable confirmation event. Latest audited bank-source-fix
 * snapshot is an authorized amendment to its current allocatable amount.
 */
final class BankSourceCorrectionEvidence
{
    public static function matchesOnlineAmount(int $projectId,int $sourceId,
        mixed $confirmedAmount,mixed $currentAmount): bool
    {
        if (round((float)$confirmedAmount,2)===round((float)$currentAmount,2)) {
            return true;
        }
        if (!Schema::hasTable('daily_voucher_edit_snapshots')) return false;
        $history=DB::table('daily_voucher_edit_snapshots')
            ->where('project_id',$projectId)->where('source_id',$sourceId)
            ->where('status','bank_source_fix')->orderByDesc('id')
            ->limit(50)->get(['before_snapshot','proposed_snapshot']);
        foreach($history as $entry) {
            $before=json_decode((string)$entry->before_snapshot,true);
            $after=json_decode((string)$entry->proposed_snapshot,true);
            if (($before['source_kind']??null)!=='finance_online_intakes'
                || ($after['source_kind']??null)!=='finance_online_intakes'
                || ($after['method']??null)!=='online') continue;
            $amended=$after['fields']['amount']??null;
            if($amended===null) continue;
            // Latest eligible amendment governs. Do not accept old archived
            // amendments after a subsequent correction or stale data.
            return round((float)$amended,2)===round((float)$currentAmount,2);
        }
        return false;
    }
}
