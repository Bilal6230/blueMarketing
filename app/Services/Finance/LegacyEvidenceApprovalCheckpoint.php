<?php
namespace App\Services\Finance;

/** Read-only, declarative segregation-of-duties checkpoint. Never posts or certifies provenance. */
final class LegacyEvidenceApprovalCheckpoint
{
    public function manifest(array $batch, array $review, LegacyEvidenceReviewWorksheet $worksheet): array
    {
        $assessment = $worksheet->assess($batch, $review);
        if ($assessment['status'] !== 'ready_for_independent_approval') {
            return ['status'=>'blocked','source_verified'=>false,'issues'=>$assessment['issues']];
        }
        $rows = [];
        foreach ($review['rows'] as $row) {
            $rows[] = array_intersect_key($row, array_flip([
                'ledger_id','snapshot_sha256','proposed_source_kind','proposed_header_id',
                'evidence_reference','reviewer','reviewed_at','review_note'
            ]));
        }
        usort($rows, static fn ($a,$b) => $a['ledger_id'] <=> $b['ledger_id']);
        return [
            'schema_version'=>1,'project_id'=>$batch['project_id'],
            'checkpoint_sha256'=>hash('sha256',json_encode(['project_id'=>$batch['project_id'],'rows'=>$rows],JSON_THROW_ON_ERROR)),
            'approvals'=>[
                ['role'=>'finance_reviewer','name'=>null,'reviewed_at'=>null,'evidence_reference'=>null],
                ['role'=>'independent_approver','name'=>null,'reviewed_at'=>null,'evidence_reference'=>null]
            ],
            'status'=>'awaiting_independent_approval','source_verified'=>false,
            'limitations'=>'Declarations are not signatures, authentication, or financial approval.'
        ];
    }

    public function assess(array $batch, array $review, array $approval, LegacyEvidenceReviewWorksheet $worksheet): array
    {
        $manifest = $this->manifest($batch,$review,$worksheet);
        if ($manifest['status'] === 'blocked') return $manifest;
        $issues = [];
        if (($approval['schema_version'] ?? null) !== 1 ||
            ($approval['project_id'] ?? null) !== $batch['project_id'] ||
            !is_string($approval['checkpoint_sha256'] ?? null) ||
            !hash_equals($manifest['checkpoint_sha256'],$approval['checkpoint_sha256'])) {
            $issues[] = 'stale_or_foreign_approval_checkpoint';
        }
        $entries = $approval['approvals'] ?? null;
        if (!is_array($entries) || count($entries) !== 2) {
            $issues[] = 'two_approvals_required';
        } else {
            $roles = []; $names = [];
            $caseReviewers = array_map(static fn ($r) => strtolower(trim($r['reviewer'])), $review['rows']);
            foreach ($entries as $entry) {
                if (!is_array($entry) ||
                    !in_array($entry['role'] ?? null,['finance_reviewer','independent_approver'],true) ||
                    isset($roles[$entry['role']])) {
                    $issues[] = 'invalid_or_duplicate_approval_role';
                    continue;
                }
                $roles[$entry['role']] = true;
                foreach (['name','reviewed_at','evidence_reference'] as $field) {
                    $value = $entry[$field] ?? null;
                    if (!is_string($value) || trim($value) === '' || strlen($value) > 2000) {
                        $issues[] = 'invalid_approval_'.$field;
                    }
                }
                if (is_string($entry['name'] ?? null) && trim($entry['name']) !== '') {
                    $name = strtolower(trim($entry['name']));
                    $names[] = $name;
                    if ($entry['role'] === 'independent_approver' && in_array($name,$caseReviewers,true)) {
                        $issues[] = 'approver_is_case_reviewer';
                    }
                }
            }
            if (count($roles) !== 2 || count(array_unique($names)) !== 2) {
                $issues[] = 'approvers_not_independent';
            }
        }
        return [
            'status'=>$issues ? 'blocked' : 'ready_for_external_signoff',
            'source_verified'=>false,'project_id'=>$batch['project_id'],
            'checkpoint_sha256'=>$manifest['checkpoint_sha256'],'issues'=>$issues,
            'limitations'=>'Read-only declared review, not authenticated signoff or certified provenance.'
        ];
    }
}
