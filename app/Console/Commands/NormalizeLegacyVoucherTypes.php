<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time test-data normalization: CP was renamed Debit Voucher (DR).
 * Explicit CCR/CDR/BOR/BOP display identities remain their own voucher types.
 */
final class NormalizeLegacyVoucherTypes extends Command
{
    protected $signature='finance:normalize-legacy-voucher-types
        {--database= : Exact database name, required}
        {--apply : Apply canonical series to existing customer and ledger records}
        {--project= : Optional project ID, omit for all projects}';

    protected $description='Convert legacy CP financial rows into DR (and preserve separately identified CCR/CDR/BOR/BOP)';

    public function handle(): int
    {
        $name=(string)DB::connection()->getDatabaseName();
        $p=$this->option('project');
        if($name===''||$this->option('database')!==$name
            ||($p!==null&&!preg_match('/^[1-9][0-9]*$/D',(string)$p))){
            $this->error('Exact --database and valid --project are required.');
            return self::INVALID;
        }
        if(!Schema::hasTable('project_voucher_display_numbers')){
            $this->error('Existing voucher display identity table missing.');
            return self::FAILURE;
        }
        $types=['CR','DR','CCR','CDR','BOR','BOP'];
        $query=DB::table('customer_ledger')->whereIn('transaction_type',
            ['CR','CP','DR','CCR','CDR','BOR','BOP'])
            ->when($p!==null,fn($q)=>$q->where('project_id',(int)$p))
            ->orderBy('id');
        $sourceChanges=[];$ledgerChanges=0;$unlinkedCp=0;
        // Materialize IDs instead of changing the iteration predicate while
        // iterating, which could skip records on some database engines.
        $rows=$query->get(['id','project_id','transaction_type']);
        $ids=$rows->pluck('id')->all();
        $display=DB::table('project_voucher_display_numbers')
            ->where('source_kind','customer_ledger')
            ->when($p!==null,fn($q)=>$q->where('project_id',(int)$p))
            ->whereIn('source_id',$ids?:[0])
            ->get(['project_id','source_id','series'])
            ->keyBy(fn($x)=>$x->project_id.'|'.$x->source_id);
        $updates=[];
        foreach($rows as $row){
            $actual=$display[$row->project_id.'|'.$row->id]->series??null;
            if(!in_array($actual,$types,true)){
                $actual=$row->transaction_type==='CP'?'DR':(string)$row->transaction_type;
            }
            if(!in_array($actual,$types,true))
                throw new \DomainException('VOUCHER_TYPE_CLASSIFICATION_INVALID');
            if($actual!==$row->transaction_type){
                $updates[]=['id'=>(int)$row->id,'project'=>(int)$row->project_id,'from'=>$row->transaction_type,'to'=>$actual];
            }
        }
        $sourceChanges=array_count_values(array_map(fn($x)=>$x['from'].'->'.$x['to'],$updates));
        $this->info(($this->option('apply')?'APPLY':'DRY RUN').': '.$name.' — '.
            count($updates).' customer source identities to normalize.');
        foreach($sourceChanges as $change=>$count)$this->line($change.': '.$count);
        if(!$this->option('apply')){
            $this->warn('No data modified. Pass --apply on the local test database.');
            return self::SUCCESS;
        }
        DB::transaction(function()use($updates,$p,&$ledgerChanges,&$unlinkedCp):void{
            $projects=$p!==null?[(int)$p]:DB::table('projects')->pluck('id')->all();
            DB::table('projects')->whereIn('id',$projects)->orderBy('id')->lockForUpdate()->get();
            foreach($updates as $update){
                DB::table('customer_ledger')
                    ->where('id',$update['id'])->where('project_id',$update['project'])
                    ->where('transaction_type',$update['from'])
                    ->update(['transaction_type'=>$update['to'],'updated_at'=>now()]);
                $ledgerChanges+=DB::table('ledgers')
                    ->where('customer_ledger_id',$update['id'])
                    ->whereIn('project_head_subheads_id',DB::table('project_head_subheads')
                        ->select('id')->where('project_id',$update['project']))
                    ->where('type',$update['from'])
                    ->update(['type'=>$update['to'],'updated_at'=>now()]);
            }
            // Legacy CP ledger rows without an attributable customer source
            // still need the renamed DR label, but their ownership is left
            // unresolved for a separate evidence-based source repair.
            $unknown=DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('l.type','CP')
                ->when($p!==null,fn($q)=>$q->where('a.project_id',(int)$p))
                ->pluck('l.id');
            foreach($unknown->chunk(1000) as $chunk){
                $unlinkedCp+=DB::table('ledgers')->whereIn('id',$chunk->all())
                    ->where('type','CP')->update(['type'=>'DR','updated_at'=>now()]);
            }
        },3);
        $this->info('Converted source rows: '.count($updates).
            '; linked ledger rows: '.$ledgerChanges.
            '; residual legacy CP ledger labels: '.$unlinkedCp);
        $this->warn('Historical source attribution gaps remain subject to separate reconciliation. No tables were dropped.');
        return self::SUCCESS;
    }
}
