<?php
namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only reconciliation gate for previously reviewed historical ledgers.
 * Never writes attributions or treats a header number as ownership evidence.
 */
final class LegacyEvidenceConflictGate
{
    public function inspect(array $batch, array $review, array $approval,
        LegacyEvidenceReviewWorksheet $worksheet, LegacyEvidenceApprovalCheckpoint $checkpoint): array
    {
        $result = $checkpoint->assess($batch, $review, $approval, $worksheet);
        $issues = [];
        if ($result['status'] !== 'ready_for_external_signoff') {
            $issues[] = ['code'=>'review_approval_blocked'];
        }
        $projectId = $batch['project_id'];
        if (!Schema::hasTable('ledger_source_attributions')) {
            $issues[]=['code'=>'attribution_schema_missing'];
        } else {
            $ids = array_map(static fn ($p)=>(int)$p['ledger']['id'],$batch['packets']);
            $existing = DB::table('ledger_source_attributions')->where('project_id',$projectId)
                ->whereIn('ledger_id',$ids)->get(['ledger_id','source_kind','source_id']);
            foreach ($existing as $row) {
                $issues[]=['ledger_id'=>(int)$row->ledger_id,'code'=>'ledger_already_attributed'];
            }
        }
        $rows = [];
        foreach ($batch['packets'] as $packet) {
            $id = (int)$packet['ledger']['id'];
            $reviewRow = null;
            foreach ($review['rows'] ?? [] as $row) {
                if (is_array($row) && ($row['ledger_id'] ?? null) === $id) {
                    $reviewRow=$row;
                    break;
                }
            }
            if ($reviewRow === null) {
                $issues[]=['ledger_id'=>$id,'code'=>'review_row_missing'];
                continue;
            }
            $kind=$reviewRow['proposed_source_kind'] ?? null;
            $header=$reviewRow['proposed_header_id'] ?? null;
            if ($kind === 'unresolved') {
                $issues[]=['ledger_id'=>$id,'code'=>'ownership_unresolved'];
            } elseif (in_array($kind,['journal_voucher','commision_voucher'],true)) {
                $candidate = $kind === 'journal_voucher' ? $packet['candidate_jv_header'] : $packet['candidate_cv_header'];
                if (!is_array($candidate) || $candidate['id'] !== $header) {
                    $issues[]=['ledger_id'=>$id,'code'=>'review_header_not_current'];
                }
            } else {
                $issues[]=['ledger_id'=>$id,'code'=>'source_claim_missing'];
            }
            $rows[]=['ledger_id'=>$id,'candidate_category'=>$packet['candidate_category'],
                'proposed_source_kind'=>$kind,'source_verified'=>false];
        }
        usort($rows,static fn ($a,$b)=>$a['ledger_id'] <=> $b['ledger_id']);
        usort($issues,static fn ($a,$b)=>($a['ledger_id'] ?? 0)<=>($b['ledger_id'] ?? 0)
            ?: strcmp($a['code'],$b['code']));
        return ['project_id'=>$projectId,'status'=>$issues ? 'blocked' : 'eligible_for_external_reconciliation',
            'source_verified'=>false,'issues'=>$issues,'rows'=>$rows,
            'limitations'=>'This is a non-mutating discrepancy gate, not signed evidence, source certification or permission to backfill.'];
    }
}
