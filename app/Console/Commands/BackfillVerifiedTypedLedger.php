<?php

namespace App\Console\Commands;

use App\Services\Finance\TypedLedgerBackfill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BackfillVerifiedTypedLedger extends Command
{
    protected $signature='finance:backfill-verified-ledgers
        {--database= : Exact connected database name (required)}
        {--project= : One positive project ID (required)}
        {--apply : Persist verified links; default dry-run}
        {--confirm= : In apply mode, enter the exact database name again}
        {--json : Output each batch as JSON}';

    protected $description='Source-verified, default DRY RUN, ledger backfill for BD/BW/plot PR/PP using existing tables';

    public function handle(TypedLedgerBackfill $backfill): int
    {
        $name=(string)DB::connection()->getDatabaseName();
        $project=(string)$this->option('project');
        $apply=(bool)$this->option('apply');
        if($name===''||$this->option('database')!==$name
            ||!preg_match('/^[1-9][0-9]*$/D',$project)){
            $this->error('Exact --database and positive --project are required.');
            return self::INVALID;
        }
        if($apply&&$this->option('confirm')!==$name){
            $this->error('Apply requires --confirm='.$name.' and a verified local backup.');
            return self::INVALID;
        }
        foreach(['ledger_source_attributions','finance_posting_lines',
            'finance_posting_batches','finance_posting_source_claims',
            'ledgers','projects','project_head_subheads'] as $table){
            if(!Schema::hasTable($table)){
                $this->error('Missing required table: '.$table);
                return self::FAILURE;
            }
        }
        $ids=DB::table('finance_posting_batches')
            ->where('project_id',(int)$project)
            ->whereIn('voucher_series',['BD','BW','PR','PP'])
            ->whereNull('reversal_of_batch_id')->orderBy('id')
            ->pluck('id');
        $results=[];$errors=0;
        foreach($ids as $id){
            try{
                $results[]=$backfill->inspect((int)$project,(int)$id,$apply);
            }catch(\Throwable $e){
                $errors++;
                $results[]=['batch'=>(int)$id,'status'=>'BLOCKED','reason'=>$e->getMessage()];
            }
        }
        $summary=[
            'database'=>$name,'project_id'=>(int)$project,
            'dry_run'=>!$apply,'batch_count'=>$ids->count(),'blocked_batches'=>$errors,
            'ready_count'=>count(array_filter($results,fn($r)=>$r['status']==='ready')),
            'linked_count'=>count(array_filter($results,fn($r)=>$r['status']==='linked')),
            'results'=>$results,
        ];
        if($this->option('json')){
            $this->line(json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        }else{
            $this->info(($apply?'APPLY':'DRY RUN').' — '.$name.', project '.$project);
            $this->table(['Batch','Type','Source ID','Legs','Status / reason'],
                array_map(fn($r)=>[$r['batch']??'', $r['series']??'', $r['source_id']??'',
                    $r['lines']??'',($r['status']??'').(isset($r['reason'])?': '.$r['reason']:'')],$results));
            $this->line('Blocked: '.$errors.'; Ready: '.$summary['ready_count'].
                '; Linked: '.$summary['linked_count']);
            if(!$apply)$this->warn('No data changed. Explicit --apply and --confirm are needed.');
        }
        return $errors===0?self::SUCCESS:self::FAILURE;
    }
}
