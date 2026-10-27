<?php
namespace App\Services\Finance;

/**
 * Consolidates separately validated project reviews. It is an export checkpoint,
 * never a financial-source attribution or an authenticated approval.
 */
final class LegacyEvidencePortfolioReconciliation
{
    public function compile(array $projects, LegacyEvidenceApprovalCheckpoint $checkpoint, LegacyEvidenceReviewWorksheet $worksheet): array
    {
        if (!$projects || count($projects) > 100) {
            throw new \InvalidArgumentException('Provide 1-100 project review groups.');
        }
        $out = []; $seen = []; $issues = []; $total = 0;
        foreach ($projects as $item) {
            if (!is_array($item) || !isset($item['batch'],$item['review'],$item['approval']) ||
                !is_array($item['batch']) || !is_array($item['review']) || !is_array($item['approval'])) {
                throw new \InvalidArgumentException('Every group requires batch, review and approval arrays.');
            }
            $batch = $item['batch'];
            $id = $batch['project_id'] ?? null;
            if (!is_int($id) || $id < 1 || isset($seen[$id])) {
                throw new \InvalidArgumentException('Project IDs must be positive and distinct.');
            }
            $seen[$id] = true;
            $check = $checkpoint->assess($batch,$item['review'],$item['approval'],$worksheet);
            $count = count($batch['packets'] ?? []);
            $total += $count;
            $row = [
                'project_id'=>$id,'ledger_count'=>$count,
                'checkpoint_sha256'=>$check['checkpoint_sha256'] ?? null,
                'status'=>$check['status'],'issues'=>$check['issues'] ?? [],
                'source_verified'=>false,
            ];
            $out[]=$row;
            if ($row['status'] !== 'ready_for_external_signoff') {
                $issues[]=['project_id'=>$id,'code'=>'project_review_blocked'];
            }
        }
        usort($out,static fn ($a,$b)=>$a['project_id'] <=> $b['project_id']);
        $digest=hash('sha256',json_encode(array_map(static fn ($r)=>[
            'project_id'=>$r['project_id'],'ledger_count'=>$r['ledger_count'],
            'checkpoint_sha256'=>$r['checkpoint_sha256'],'status'=>$r['status'],
            'issues'=>$r['issues']
        ],$out),JSON_THROW_ON_ERROR));
        return [
            'schema_version'=>1,'status'=>$issues ? 'blocked' : 'ready_for_external_signoff',
            'source_verified'=>false,'project_count'=>count($out),'ledger_count'=>$total,
            'portfolio_sha256'=>$digest,'projects'=>$out,'issues'=>$issues,
            'limitations'=>'Read-only aggregate; no source attribution, authenticated approval or accounting change.'
        ];
    }
}
