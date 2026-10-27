<?php
namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only project-wide coverage gate. A submitted review set is never proof
 * of historical source ownership, even when the set covers every ledger.
 */
final class LegacyEvidenceCoverageInspector
{
    public function inspect(int $projectId, array $reviewedLedgerIds, int $sampleLimit = 50): array
    {
        if ($projectId < 1 || $sampleLimit < 1 || $sampleLimit > 100 ||
            count($reviewedLedgerIds) > 10000) {
            throw new \InvalidArgumentException('Valid project, review set and sample limit required.');
        }
        $ids = [];
        foreach ($reviewedLedgerIds as $id) {
            if (!is_int($id) || $id < 1 || isset($ids[$id])) {
                throw new \InvalidArgumentException('Ledger IDs must be distinct positive integers.');
            }
            $ids[$id] = true;
        }
        $query = app(LegacyVoucherCollisionInventory::class)->unverifiedQuery($projectId);
        $total = (clone $query)->count();
        $reviewed = 0;
        // The entire provided ID set is checked, rather than trusting IDs from
        // a worksheet or a stale portfolio export.
        foreach (array_chunk(array_keys($ids),500) as $chunk) {
            $reviewed += (clone $query)->whereIn('ledgers.id',$chunk)->count();
        }
        $unknown = count($ids)-$reviewed;
        $missing = $total-$reviewed;
        $sampleQuery=clone $query;
        if ($ids) {
            $sampleQuery->whereNotIn('ledgers.id',array_keys($ids));
        }
        $sample=$sampleQuery->orderBy('ledgers.id')->limit($sampleLimit)
            ->pluck('ledgers.id')->map(static fn ($id)=>(int)$id)->all();
        $issues=[];
        if ($unknown) $issues[]='review_ids_outside_current_unverified_scope';
        if ($missing) $issues[]='unreviewed_legacy_ledger_rows';
        if (!Schema::hasTable('ledger_source_attributions')) $issues[]='attribution_schema_missing';
        // Historical certification is NEVER granted by coverage accounting.
        return [
            'project_id'=>$projectId,'status'=>$issues ? 'blocked' : 'coverage_only_complete',
            'source_verified'=>false,'total_unverified'=>$total,
            'reviewed_in_scope'=>$reviewed,'submitted_ids'=>count($ids),
            'outside_scope_ids'=>$unknown,'remaining_unreviewed'=>$missing,
            'missing_sample_ledger_ids'=>$sample,
            'sample_incomplete'=>$missing>count($sample),'issues'=>$issues,
            'limitations'=>'Coverage is not source proof, signoff or permission to write attributions.'
        ];
    }
}
