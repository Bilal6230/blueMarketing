<?php
namespace Tests\Feature\Finance;

use App\Services\Finance\LegacyEvidenceReleaseGate;
use PHPUnit\Framework\TestCase;

final class LegacyEvidenceReleaseGateTest extends TestCase
{
    private function group(int $id, int $remaining=0): array
    {
        $coverage=['project_id'=>$id,'status'=>$remaining?'blocked':'coverage_only_complete',
            'total_unverified'=>2,'reviewed_in_scope'=>2-$remaining,'remaining_unreviewed'=>$remaining];
        $rows=[];
        for ($n=1;$n<=2-$remaining;$n++) $rows[]=['ledger_id'=>$id*100+$n];
        return ['coverage'=>$coverage,'conflict'=>['project_id'=>$id,
            'status'=>'eligible_for_external_reconciliation','rows'=>$rows]];
    }
    public function test_complete_multiple_projects_still_not_approved_for_deployment(): void
    {
        $gate=new LegacyEvidenceReleaseGate();
        $a=$gate->assess([$this->group(7),$this->group(6)]);
        $b=$gate->assess([$this->group(6),$this->group(7)]);
        $this->assertSame($a,$b);
        $this->assertSame('review_scope_reconciled_only',$a['status']);
        $this->assertFalse($a['source_verified']);
        $this->assertFalse($a['deployment_approved']);
        $this->assertSame(4,$a['totals']['reviewed']);
    }
    public function test_incomplete_project_blocks_entire_portfolio(): void
    {
        $r=(new LegacyEvidenceReleaseGate())->assess([$this->group(6,1),$this->group(7)]);
        $this->assertSame('blocked',$r['status']);
        $this->assertSame('project_coverage_incomplete',$r['blockers'][0]['code']);
    }
    public function test_existing_conflict_blocks_project(): void
    {
        $g=$this->group(6); $g['conflict']['status']='blocked';
        $r=(new LegacyEvidenceReleaseGate())->assess([$g]);
        $this->assertSame('project_case_conflicts',$r['blockers'][0]['code']);
    }
    public function test_missing_case_audit_fails_count_validation(): void
    {
        $g=$this->group(6); array_pop($g['conflict']['rows']);
        $r=(new LegacyEvidenceReleaseGate())->assess([$g]);
        $this->assertSame('reviewed_case_count_mismatch',$r['blockers'][0]['code']);
    }
    public function test_duplicate_projects_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new LegacyEvidenceReleaseGate())->assess([$this->group(6),$this->group(6)]);
    }
    public function test_mismatched_project_scope_is_refused(): void
    {
        $g=$this->group(6);$g['conflict']['project_id']=7;
        $this->expectException(\InvalidArgumentException::class);
        (new LegacyEvidenceReleaseGate())->assess([$g]);
    }
    public function test_duplicate_reconciled_ledger_ids_are_refused(): void
    {
        $g=$this->group(6);$g['conflict']['rows'][1]['ledger_id']=$g['conflict']['rows'][0]['ledger_id'];
        $this->expectException(\InvalidArgumentException::class);
        (new LegacyEvidenceReleaseGate())->assess([$g]);
    }
    public function test_inconsistent_coverage_totals_are_refused(): void
    {
        $g=$this->group(6);$g['coverage']['total_unverified']=10;
        $this->expectException(\InvalidArgumentException::class);
        (new LegacyEvidenceReleaseGate())->assess([$g]);
    }
}
