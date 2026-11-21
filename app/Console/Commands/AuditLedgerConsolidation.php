<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-migration, strictly read-only evidence for moving typed posting legs
 * into the existing ledgers table. Never guesses a source from type_id alone.
 */
final class AuditLedgerConsolidation extends Command
{
    protected $signature = 'finance:audit-ledger-consolidation
        {--database= : Required exact database name, preventing accidental live-target scans}
        {--project= : Optional positive project ID}
        {--json : Emit machine-readable results}';

    protected $description = 'READ ONLY: inventory ledger coverage, typed posting links and CP/DR identities before consolidation';

    public function handle(): int
    {
        $database=(string)DB::connection()->getDatabaseName();
        if($database==='' || $this->option('database')!==$database){
            $this->error('Specify the exact connected database with --database='.$database);
            return self::INVALID;
        }
        $selected=$this->option('project');
        if($selected!==null && !preg_match('/^[1-9][0-9]*$/D',(string)$selected)){
            $this->error('Project must be a positive integer.');
            return self::INVALID;
        }
        $project=$selected===null?null:(int)$selected;
        $required=['ledgers','project_head_subheads','finance_posting_lines',
            'finance_posting_batches','customer_ledger'];
        foreach($required as $table){
            if(!Schema::hasTable($table)){
                $this->error('Missing table: '.$table);
                return self::FAILURE;
            }
        }
        if(!Schema::hasColumn('finance_posting_lines','legacy_ledger_id')){
            $this->error('Existing typed-to-ledger FK mapping column is missing.');
            return self::FAILURE;
        }
        $legacy=DB::table('ledgers as l')
            ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
            ->when($project!==null,fn($q)=>$q->where('a.project_id',$project))
            ->selectRaw('a.project_id, l.type AS voucher_type, l.is_active, COUNT(*) AS rows_count')
            ->groupBy('a.project_id','l.type','l.is_active')->orderBy('a.project_id')
            ->orderBy('l.type')->get()->map(fn($row)=>(array)$row)->all();

        $typed=DB::table('finance_posting_lines as line')
            ->join('finance_posting_batches as batch','batch.id','=','line.posting_batch_id')
            ->leftJoin('project_head_subheads as account','account.id','=','line.project_head_subhead_id')
            ->leftJoin('ledgers as ledger','ledger.id','=','line.legacy_ledger_id')
            ->leftJoin('project_head_subheads as legacy_account','legacy_account.id','=','ledger.project_head_subheads_id')
            ->when($project!==null,fn($q)=>$q->where('batch.project_id',$project))
            ->selectRaw("batch.project_id, batch.voucher_series, 
                CASE WHEN batch.reversal_of_batch_id IS NULL THEN 'original' ELSE 'reversal' END AS batch_kind,
                COUNT(*) AS total_lines,
                SUM(CASE WHEN line.legacy_ledger_id IS NULL THEN 1 ELSE 0 END) AS without_ledger_link,
                SUM(CASE WHEN line.legacy_ledger_id IS NOT NULL AND ledger.id IS NULL THEN 1 ELSE 0 END) AS missing_link_target,
                SUM(CASE WHEN ledger.id IS NOT NULL AND
                    (legacy_account.project_id IS NULL OR legacy_account.project_id != batch.project_id
                    OR ledger.project_head_subheads_id != line.project_head_subhead_id
                    OR ledger.amount_in != line.debit OR ledger.amount_out != line.credit)
                    THEN 1 ELSE 0 END) AS mismatched_link,
                SUM(CASE WHEN account.project_id IS NULL OR account.project_id != batch.project_id
                    THEN 1 ELSE 0 END) AS foreign_account")
            ->groupBy('batch.project_id','batch.voucher_series','batch.reversal_of_batch_id')
            ->get()->groupBy(fn($row)=>(int)$row->project_id.'|'.$row->voucher_series.'|'.
                ($row->batch_kind==='reversal'?'reversal':'original'))
            ->map(function($group){
                $first=$group->first();
                return [
                    'project_id'=>(int)$first->project_id,
                    'voucher_type'=>(string)$first->voucher_series,
                    'batch_kind'=>(string)$first->batch_kind,
                    'total_lines'=>(int)$group->sum('total_lines'),
                    'without_ledger_link'=>(int)$group->sum('without_ledger_link'),
                    'missing_link_target'=>(int)$group->sum('missing_link_target'),
                    'mismatched_link'=>(int)$group->sum('mismatched_link'),
                    'foreign_account'=>(int)$group->sum('foreign_account'),
                ];
            })->values()->all();

        // CP may mean DR, CDR or BOP. Never execute an indiscriminate CP->DR UPDATE.
        $hasDisplay=Schema::hasTable('project_voucher_display_numbers');
        $displayJoin=$hasDisplay
            ? "LEFT JOIN project_voucher_display_numbers d
                ON d.project_id=a.project_id
                AND d.source_kind='customer_ledger' AND d.source_id=c.id"
            : '';
        $series=$hasDisplay?"COALESCE(d.series,'UNCLASSIFIED_CP')":"'UNCLASSIFIED_CP'";
        $cp=DB::table('ledgers as l')
            ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
            ->leftJoin('customer_ledger as c','c.id','=','l.customer_ledger_id')
            ->when($project!==null,fn($q)=>$q->where('a.project_id',$project))
            ->where('l.type','CP')
            ->selectRaw("a.project_id, $series AS display_series,
                COUNT(*) AS ledger_rows,
                SUM(CASE WHEN c.id IS NULL OR c.project_id != a.project_id THEN 1 ELSE 0 END) AS missing_or_foreign_source")
            ->when($hasDisplay,fn($q)=>$q->leftJoin('project_voucher_display_numbers as d',
                function($join){
                    $join->on('d.source_id','=','c.id')
                        ->on('d.project_id','=','a.project_id')
                        ->where('d.source_kind','=','customer_ledger');
                }))
            ->groupBy('a.project_id')
            ->when($hasDisplay,fn($q)=>$q->groupBy('d.series'))
            ->get()->map(fn($row)=>(array)$row)->all();

        $problemRows=array_sum(array_map(static fn($row)=>
            $row['missing_link_target']+$row['mismatched_link']+$row['foreign_account'],$typed));
        $missingMappings=array_sum(array_column($typed,'without_ledger_link'));
        $ambiguousCp=array_sum(array_map(static fn($row)=>
            $row['display_series']==='UNCLASSIFIED_CP'||$row['missing_or_foreign_source']>0
                ?(int)$row['ledger_rows']:0,$cp));

        $result=[
            'database'=>$database,'project_id'=>$project,'read_only'=>true,
            'authority_target'=>'ledgers',
            'physical_tables'=>count(DB::select('SELECT TABLE_NAME FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=? AND TABLE_TYPE=?',[$database,'BASE TABLE'])),
            'existing_link'=>'finance_posting_lines.legacy_ledger_id',
            'legacy_ledger_counts'=>$legacy,'typed_posting_coverage'=>$typed,
            'cp_display_identity'=>$cp,
            'totals'=>[
                'typed_without_ledger_link'=>$missingMappings,
                'typed_invalid_ledger_links'=>$problemRows,
                'cp_rows_requiring_explicit_identity_review'=>$ambiguousCp,
            ],
            'cleanup_gate'=>'BLOCKED until exact source ownership, archival parity, and balance reconciliation are proven',
        ];
        if($this->option('json')){
            $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        }else{
            $this->info('READ ONLY: '.$database.' / '.($project===null?'all projects':'project '.$project));
            $this->line('Physical tables: '.$result['physical_tables']);
            $this->table(['Project','Type','Batch','Lines','No ledger link','Bad links','Foreign accounts'],
                array_map(static fn($r)=>[$r['project_id'],$r['voucher_type'],$r['batch_kind'],
                    $r['total_lines'],$r['without_ledger_link'],
                    $r['missing_link_target']+$r['mismatched_link'],$r['foreign_account']],$typed));
            $this->table(['Project','CP display identity','Rows','Missing/foreign source'],
                array_map(static fn($r)=>[$r['project_id'],$r['display_series'],
                    $r['ledger_rows'],$r['missing_or_foreign_source']],$cp));
            $this->warn("UNLINKED typed lines: $missingMappings | INVALID links: $problemRows | CP identities needing review: $ambiguousCp");
            $this->warn('No data migrated, no CP renamed, and no tables dropped.');
        }
        // A missing link is an expected consolidation backlog; a corrupt
        // existing FK / foreign-project mapping is a hard safety failure.
        return $problemRows===0 ? self::SUCCESS : self::FAILURE;
    }
}
