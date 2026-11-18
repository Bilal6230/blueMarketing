<?php

namespace App\Console\Commands;

use App\Services\Finance\PostingBatchIntegrityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditPostingBatchIntegrity extends Command
{
    protected $signature = 'finance:audit-posting-balances {--project= : Optional project ID; omit to audit all projects in the configured database} {--json : Output machine-readable findings}';
    protected $description = 'Read-only audit of active finance postings for every project or a specified project';

    public function handle(PostingBatchIntegrityAudit $audit): int
    {
        $selected=$this->option('project');
        if ($selected !== null && !preg_match('/^[1-9][0-9]*$/D',(string)$selected)) {
            $this->error('Invalid project ID: use a positive integer or omit --project for all projects.');
            return self::FAILURE;
        }
        // No project ID is hard-coded or inferred from a specific deployment.
        // Include projects with zero batches, plus any orphan posting project IDs
        // so the audit does not silently skip their financial evidence.
        $projectIds=$selected !== null
            ? [(int)$selected]
            : DB::table('projects')->orderBy('id')->pluck('id')->map(fn($id)=>(int)$id)
                ->merge(DB::table('finance_posting_batches')->distinct()->pluck('project_id')
                    ->map(fn($id)=>(int)$id))
                ->filter(fn($id)=>$id>0)->unique()->sort()->values()->all();
        $findings=[];
        foreach ($projectIds as $projectId) {
            foreach ($audit->audit($projectId) as $finding) {
                $findings[]=['project_id'=>$projectId]+$finding;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'database'=>DB::connection()->getDatabaseName(),
                'project_id'=>$selected===null ? null : (int)$selected,
                'project_count'=>count($projectIds),
                'finding_count'=>count($findings),
                'findings'=>$findings,
            ],JSON_THROW_ON_ERROR));
        } else {
            $this->info('READ-ONLY active posting integrity audit for '.
                ($selected===null ? 'ALL projects' : 'project '.$selected).
                ' ('.count($projectIds).' project(s) checked)');
            if ($findings===[]) {
                $this->info('No imbalanced / invalid active posting batches found.');
            } else {
                $this->table(['Project','Batch','Series','Voucher','Active lines','Issues'],
                    array_map(static fn($f)=>[$f['project_id'],$f['batch_id'],$f['series'],
                        $f['voucher_number'],$f['active_line_count'],
                        implode(', ',$f['issues'])],$findings));
            }
        }
        return $findings===[] ? self::SUCCESS : self::FAILURE;
    }
}
