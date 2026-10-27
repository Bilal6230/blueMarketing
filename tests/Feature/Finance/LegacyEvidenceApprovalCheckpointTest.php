<?php
namespace Tests\Feature\Finance;

use App\Services\Finance\LegacyEvidenceApprovalCheckpoint;
use App\Services\Finance\LegacyEvidenceReviewWorksheet;
use PHPUnit\Framework\TestCase;

final class LegacyEvidenceApprovalCheckpointTest extends TestCase
{
    private function fixture(): array {
        $batch=['project_id'=>6,'packets'=>[[
            'ledger'=>['id'=>12179,'type'=>'JV','legacy_type_id'=>1310,'original_voucher_number'=>'249','project_account_id'=>428],
            'candidate_category'=>'neither_header','candidate_jv_header'=>null,'candidate_cv_header'=>null
        ]]];
        $worksheet=new LegacyEvidenceReviewWorksheet();
        $review=$worksheet->template($batch);
        $review['rows'][0]=array_merge($review['rows'][0],[
            'proposed_source_kind'=>'unresolved','evidence_reference'=>'box:7',
            'reviewer'=>'Analyst','reviewed_at'=>'2026-10-27','review_note'=>'Not established'
        ]);
        $checkpoint=new LegacyEvidenceApprovalCheckpoint();
        $approval=$checkpoint->manifest($batch,$review,$worksheet);
        $approval['approvals'][0]=['role'=>'finance_reviewer','name'=>'Finance Lead','reviewed_at'=>'2026-10-27','evidence_reference'=>'ticket:1'];
        $approval['approvals'][1]=['role'=>'independent_approver','name'=>'Controller','reviewed_at'=>'2026-10-27','evidence_reference'=>'ticket:2'];
        return [$batch,$worksheet,$review,$checkpoint,$approval];
    }
    public function test_valid_declarations_never_certify_provenance(): void {
        [$b,$w,$r,$c,$a]=$this->fixture();
        $result=$c->assess($b,$r,$a,$w);
        $this->assertSame('ready_for_external_signoff',$result['status']);
        $this->assertFalse($result['source_verified']);
    }
    public function test_stale_review_blocks_checkpoint(): void {
        [$b,$w,$r,$c,$a]=$this->fixture();
        $r['rows'][0]['review_note']='changed';
        $this->assertContains('stale_or_foreign_approval_checkpoint',$c->assess($b,$r,$a,$w)['issues']);
    }
    public function test_same_approver_is_rejected(): void {
        [$b,$w,$r,$c,$a]=$this->fixture();
        $a['approvals'][1]['name']='Finance Lead';
        $this->assertContains('approvers_not_independent',$c->assess($b,$r,$a,$w)['issues']);
    }
    public function test_case_reviewer_cannot_independently_approve(): void {
        [$b,$w,$r,$c,$a]=$this->fixture();
        $a['approvals'][1]['name']='Analyst';
        $this->assertContains('approver_is_case_reviewer',$c->assess($b,$r,$a,$w)['issues']);
    }
    public function test_empty_evidence_does_not_generate_checkpoint(): void {
        [$b,$w,$r,$c,$a]=$this->fixture();
        $r['rows'][0]['evidence_reference']='';
        $this->assertSame('blocked',$c->manifest($b,$r,$w)['status']);
    }
    public function test_duplicate_role_and_missing_reference_are_rejected(): void {
        [$b,$w,$r,$c,$a]=$this->fixture();
        $a['approvals'][1]['role']='finance_reviewer';
        $a['approvals'][0]['evidence_reference']='';
        $issues=$c->assess($b,$r,$a,$w)['issues'];
        $this->assertContains('invalid_or_duplicate_approval_role',$issues);
        $this->assertContains('invalid_approval_evidence_reference',$issues);
    }
}
