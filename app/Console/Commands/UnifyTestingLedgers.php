<?php

namespace App\Console\Commands;

use App\Services\Finance\CashSourceLedgerLinker;
use App\Services\Finance\ReversalLedgerLinker;
use App\Services\Finance\TypedLedgerBackfill;
use App\Services\Finance\VoucherDetailLedgerLinker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source-aware ledger conversion for the current local testing database.
 * Uses the existing ledgers table, existing linked source FKs and batch lines.
 */
final class UnifyTestingLedgers extends Command
{
    protected $signature='finance:unify-testing-ledgers
        {--database= : Exact current database name}
        {--project= : Specific project ID, required}
        {--apply : Write reconciled ledger records; omitted by default}
        {--confirm= : Repeat exact database name in apply mode}';

    protected $description='Convert all typed voucher families to source-linked general ledger rows without creating tables';

    public function handle(
        TypedLedgerBackfill $plotBank,
        CashSourceLedgerLinker $cash,
        VoucherDetailLedgerLinker $detail,
        ReversalLedgerLinker $reversal
    ): int {
        $database=(string)DB::connection()->getDatabaseName();
        $project=(string)$this->option('project');
        $apply=(bool)$this->option('apply');
        if($database===''||$this->option('database')!==$database
            ||!preg_match('/^[1-9][0-9]*$/D',$project)){
            $this->error('Specify exact --database and --project.');
            return self::INVALID;
        }
        if($apply && $this->option('confirm')!==$database){
            $this->error('Apply requires --confirm='.$database);
            return self::INVALID;
        }
        foreach(['ledgers','ledger_source_attributions','finance_posting_lines',
            'finance_posting_batches','finance_posting_source_claims'] as $table){
            if(!Schema::hasTable($table)){
                $this->error('Required existing table is missing: '.$table);
                return self::FAILURE;
            }
        }
        $batches=DB::table('finance_posting_batches')
            ->where('project_id',(int)$project)
            ->orderByRaw('CASE WHEN reversal_of_batch_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')->get(['id','voucher_series','reversal_of_batch_id']);
        if(!$apply){
            $rows=$batches->map(function($batch){
                $unlinked=DB::table('finance_posting_lines')
                    ->where('posting_batch_id',$batch->id)
                    ->whereNull('legacy_ledger_id')->count();
                return [(int)$batch->id,$batch->voucher_series,$unlinked];
            })->all();
            $this->info('DRY RUN: '.$database.', project '.$project.'. No data changed.');
            $this->table(['Batch','Series','Unlinked typed legs'],$rows);
            $this->warn('After local backup, run with --apply and --confirm='.$database);
            return self::SUCCESS;
        }
        $linked=0;$reused=0;$blocked=[];
        // Every batch independently preserves transactional integrity.
        // Failing batches remain available for diagnosis and rerun.
        foreach($batches as $batch){
            try{
                $series=(string)$batch->voucher_series;
                if(in_array($series,['BD','BW','PR','PP'],true)){
                    $result=$plotBank->inspect((int)$project,(int)$batch->id,true);
                    if($result['status']==='linked')$linked+=(int)$result['lines'];
                    else $reused++;
                }elseif(in_array($series,['JV','SV','CV'],true)){
                    $linked+=$detail->sync((int)$project,(int)$batch->id);
                }elseif(in_array($series,['CR','CP','DR','PPR','EC','ECP'],true)){
                    $linked+=$cash->sync((int)$project,(int)$batch->id);
                }elseif($series==='REV' && $batch->reversal_of_batch_id!==null){
                    $linked+=$reversal->sync((int)$project,(int)$batch->id);
                }else{
                    $blocked[]='Batch '.$batch->id.': unsupported series '.$series;
                }
            }catch(\Throwable $error){
                $blocked[]='Batch '.$batch->id.' ('.$batch->voucher_series.'): '.$error->getMessage();
            }
        }
        $remaining=DB::table('finance_posting_lines as line')
            ->join('finance_posting_batches as batch','batch.id','=','line.posting_batch_id')
            ->where('batch.project_id',(int)$project)->whereNull('line.legacy_ledger_id')->count();
        $this->info('DB '.$database.' / project '.$project.'; new general-ledger entries: '.$linked.
            '; prelinked batches: '.$reused.'; remaining unlinked: '.$remaining);
        foreach($blocked as $message)$this->error($message);
        if($blocked!==[]||$remaining>0){
            $this->warn('Incomplete. No tables dropped. Correct blocked source relationships before cleanup.');
            return self::FAILURE;
        }
        $this->info('PASS: every typed line has a linked authoritative ledger row.');
        return self::SUCCESS;
    }
}
