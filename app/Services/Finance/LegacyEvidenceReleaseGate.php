<?php
namespace App\Services\Finance;

/**
 * Aggregates independently reconciled project coverage with reviewed-case
 * conflict reports. Completeness and source certification remain separate.
 */
final class LegacyEvidenceReleaseGate
{
    public function assess(array $groups): array
    {
        if (!$groups || count($groups)>100) throw new \InvalidArgumentException('Provide 1-100 project groups.');
        $seen=[]; $rows=[]; $blockers=[]; $totals=['unverified'=>0,'reviewed'=>0,'remaining'=>0,'cases_checked'=>0];
        foreach ($groups as $group) {
            if (!is_array($group) || !isset($group['coverage'],$group['conflict']) ||
                !is_array($group['coverage']) || !is_array($group['conflict'])) {
                throw new \InvalidArgumentException('Each group requires coverage and conflict reports.');
            }
            $coverage=$group['coverage']; $conflict=$group['conflict'];
            $id=$coverage['project_id'] ?? null;
            if (!is_int($id) || $id<1 || isset($seen[$id]) ||
                ($conflict['project_id'] ?? null)!==$id) {
                throw new \InvalidArgumentException('Project scope mismatch or duplicate.');
            }
            $seen[$id]=true;
            $total=$coverage['total_unverified'] ?? null;
            $reviewed=$coverage['reviewed_in_scope'] ?? null;
            $remaining=$coverage['remaining_unreviewed'] ?? null;
            $checked=count($conflict['rows'] ?? []);
            if (!is_int($total) || !is_int($reviewed) || !is_int($remaining) ||
                $total<0 || $reviewed<0 || $remaining<0 || $total!==$reviewed+$remaining ||
                !isset($conflict['rows']) || !is_array($conflict['rows'])) {
                throw new \InvalidArgumentException('Malformed coverage or case totals.');
            }
            $ids=[];
            foreach ($conflict['rows'] as $case) {
                $ledgerId=$case['ledger_id'] ?? null;
                if (!is_int($ledgerId) || $ledgerId<1 || isset($ids[$ledgerId])) {
                    throw new \InvalidArgumentException('Duplicate or invalid reconciled ledger.');
                }
                $ids[$ledgerId]=true;
            }
            $issues=[];
            if (($coverage['status'] ?? null)!=='coverage_only_complete') $issues[]='project_coverage_incomplete';
            if (($conflict['status'] ?? null)!=='eligible_for_external_reconciliation') $issues[]='project_case_conflicts';
            if ($checked!==$reviewed) $issues[]='reviewed_case_count_mismatch';
            foreach (array_keys($totals) as $key) {
                $totals[$key]+=match($key) {
                    'unverified'=>$total, 'reviewed'=>$reviewed,
                    'remaining'=>$remaining, default=>$checked
                };
            }
            $rows[]=['project_id'=>$id,'unverified'=>$total,'reviewed'=>$reviewed,
                'remaining'=>$remaining,'cases_checked'=>$checked,'issues'=>$issues];
            foreach ($issues as $issue) $blockers[]=['project_id'=>$id,'code'=>$issue];
        }
        usort($rows,static fn ($a,$b)=>$a['project_id']<=>$b['project_id']);
        usort($blockers,static fn ($a,$b)=>$a['project_id']<=>$b['project_id'] ?: strcmp($a['code'],$b['code']));
        $digest=hash('sha256',json_encode(['rows'=>$rows,'blockers'=>$blockers],JSON_THROW_ON_ERROR));
        return ['status'=>$blockers?'blocked':'review_scope_reconciled_only',
            'source_verified'=>false,'deployment_approved'=>false,'project_count'=>count($rows),
            'totals'=>$totals,'projects'=>$rows,'blockers'=>$blockers,'digest_sha256'=>$digest,
            'limitations'=>'Snapshot coverage and conflict checks are not source proof, production readiness or authorization to backfill.'];
    }
}
