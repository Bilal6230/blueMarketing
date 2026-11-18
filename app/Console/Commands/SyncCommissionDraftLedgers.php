<?php

namespace App\Console\Commands;

use App\Services\Finance\CommissionLedgerSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operator-invoked repair for draft Commission vouchers created before
 * inactive FK-linked ledger projection was enabled.
 */
final class SyncCommissionDraftLedgers extends Command
{
    protected $signature='finance:sync-commission-draft-ledgers
        {--project= : Optional project ID; omit to scan all projects}
        {--apply : Create only missing inactive FK-linked draft ledger legs}';

    protected $description='Review or idempotently backfill missing inactive Commission draft ledger records';

    public function handle(CommissionLedgerSynchronizer $sync): int
    {
        $project=$this->option('project');
        if ($project!==null && (!preg_match('/^[1-9][0-9]*$/D',(string)$project)
            || (int)$project<1)) {
            $this->error('Project must be a positive integer, or omit --project to scan all projects.');
            return self::FAILURE;
        }
        $query=DB::table('commision_vouchers')->where('status','draft')->whereNull('deleted_at');
        if ($project!==null) $query->where('project_id',(int)$project);
        $drafts=$query->orderBy('id')->get(['id','project_id','voucher_number']);
        $apply=(bool)$this->option('apply');
        $created=0;$failed=0;
        foreach ($drafts as $draft) {
            if (!$apply) continue;
            try {
                DB::transaction(function()use($sync,$draft):void {
                    DB::table('projects')->where('id',$draft->project_id)->lockForUpdate()->firstOrFail();
                    $sync->ensureDraft((int)$draft->project_id,(int)$draft->id);
                },3);
                $created++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
                $this->error('Cannot synchronize CV ID '.$draft->id.
                    ' (project '.$draft->project_id.'); inspect source/detail/FK integrity.');
            }
        }
        $this->info($apply
            ? 'Commission draft sync completed: '.$created.' processed, '.$failed.' failed.'
            : 'READ-ONLY: '.count($drafts).' Commission drafts found; no records were changed. Use --apply to synchronize missing inactive FK-linked rows.');
        return $failed===0 ? self::SUCCESS : self::FAILURE;
    }
}
