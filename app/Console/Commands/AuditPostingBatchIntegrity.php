<?php

namespace App\Console\Commands;

use App\Services\Finance\PostingBatchIntegrityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditPostingBatchIntegrity extends Command
{
    protected $signature = 'finance:audit-posting-balances {--project= : Project ID to audit} {--json : Output machine-readable findings}';
    protected $description = 'Read-only audit of active debit/credit postings across all voucher series in a selected project';

    public function handle(PostingBatchIntegrityAudit $audit): int
    {
        $project=(string)$this->option('project');
        if (!preg_match('/^[1-9][0-9]*$/D',$project)) {
            $this->error('Supply an explicit positive project ID with --project=N.');
            return self::FAILURE;
        }
        $projectId=(int)$project;
        if ($projectId < 1) return self::FAILURE;
        $findings=$audit->audit($projectId);
        if ($this->option('json')) {
            $this->line(json_encode([
                'database'=>DB::connection()->getDatabaseName(),
                'project_id'=>$projectId,
                'finding_count'=>count($findings),
                'findings'=>$findings,
            ],JSON_THROW_ON_ERROR));
        } else {
            $this->info('READ-ONLY active posting integrity audit for project '.$projectId);
            if ($findings === []) {
                $this->info('No imbalanced / invalid active posting batches found.');
            } else {
                $this->table(['Batch','Series','Voucher','Active lines','Issues'],
                    array_map(static fn($f)=>[$f['batch_id'],$f['series'],
                        $f['voucher_number'],$f['active_line_count'],
                        implode(', ',$f['issues'])],$findings));
            }
        }
        return $findings===[] ? self::SUCCESS : self::FAILURE;
    }
}
