<?php
namespace Tests\Feature\Finance;

use App\Services\Finance\LegacyEvidenceApprovalCheckpoint;
use App\Services\Finance\LegacyEvidencePortfolioReconciliation;
use App\Services\Finance\LegacyEvidenceReviewWorksheet;
use PHPUnit\Framework\TestCase;

final class LegacyEvidencePortfolioReconciliationTest extends TestCase
{
    private function group(int $project): array
    {
        $batch=['project_id'=>$project,'packets'=>[[
            'ledger'=>['id'=>$project*10,'type'=>'JV','legacy_type_id'=>1310,
                'original_voucher_number'=>'249','project_account_id'=>428],
            'candidate_category'=>'neither_header','candidate_jv_header'=>null,'candidate_cv_header'=>null
        ]]];
        $w=new LegacyEvidenceReviewWorksheet();
        $review=$w->template($batch);
        $review['rows'][0]=array_merge($review['rows'][0],[
            'proposed_source_kind'=>'unresolved','evidence_reference'=>'archive',
            'reviewer'=>'Analyst','reviewed_at'=>'2026-10-27','review_note'=>'Unresolved']);
        $a=(new LegacyEvidenceApprovalCheckpoint())->manifest($batch,$review,$w);
        $a['approvals'][0]=['role'=>'finance_reviewer','name'=>'Reviewer','reviewed_at'=>'2026-10-27','evidence_reference'=>'r1'];
        $a['approvals'][1]=['role'=>'independent_approver','name'=>'Controller','reviewed_at'=>'2026-10-27','evidence_reference'=>'a1'];
        return ['batch'=>$batch,'review'=>$review,'approval'=>$a];
    }
    private function compile(array $groups): array {
        return (new LegacyEvidencePortfolioReconciliation())->compile(
            $groups,new LegacyEvidenceApprovalCheckpoint(),new LegacyEvidenceReviewWorksheet());
    }
    public function test_two_projects_aggregate_deterministically_without_certification(): void {
        $a=$this->compile([$this->group(7),$this->group(6)]);
        $b=$this->compile([$this->group(6),$this->group(7)]);
        $this->assertSame($a,$b);
        $this->assertSame('ready_for_external_signoff',$a['status']);
        $this->assertSame(2,$a['ledger_count']);
        $this->assertFalse($a['source_verified']);
    }
    public function test_tampered_review_blocks_whole_portfolio(): void {
        $group=$this->group(6);
        $group['review']['rows'][0]['review_note']='changed';
        $result=$this->compile([$group,$this->group(7)]);
        $this->assertSame('blocked',$result['status']);
        $this->assertSame(6,$result['issues'][0]['project_id']);
    }
    public function test_duplicate_project_is_denied(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->compile([$this->group(6),$this->group(6)]);
    }
    public function test_empty_manifest_is_denied(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->compile([]);
    }
    public function test_modified_approval_changes_report_digest_and_blocks(): void {
        $a=$this->group(6);
        $b=$a; $b['approval']['approvals'][1]['name']='Reviewer';
        $first=$this->compile([$a]); $second=$this->compile([$b]);
        $this->assertNotSame($first['portfolio_sha256'],$second['portfolio_sha256']);
        $this->assertSame('blocked',$second['status']);
    }
}
