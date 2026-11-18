<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ ONLY. Inventories real configured database, never assumes a database
 * name, does not mutate DDL, and does not expose voucher financial values.
 */
final class AuditVoucherSchema extends Command
{
    protected $signature = 'finance:audit-voucher-schema {--json : Print a JSON report including tables with neither lifecycle column}';
    protected $description = 'Read-only audit of foreign keys, linked voucher integrity and missing deleted_at/is_active';

    private const RELATIONS = [
        ['journal_voucher_details','journal_voucher_id','journal_vouchers','id'],
        ['commision_voucher_details','commision_voucher_id','commision_vouchers','id'],
        ['ledgers','journal_voucher_detail_id','journal_voucher_details','id'],
        ['ledgers','customer_ledger_id','customer_ledger','id'],
        ['ledgers','project_head_subheads_id','project_head_subheads','id'],
        ['journal_voucher_details','account_id','project_head_subheads','id'],
        ['commision_voucher_details','account_id','project_head_subheads','id'],
        ['ledger_source_attributions','ledger_id','ledgers','id'],
    ];

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();
        $tables = collect(DB::select('SELECT TABLE_NAME AS table_name FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME', [$database,'BASE TABLE']))
            ->pluck('table_name')->all();
        $columns=collect(DB::select('SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?',[$database]))
            ->groupBy('table_name')->map(fn($rows)=>$rows->pluck('column_name')->all());
        $keys=collect(DB::select('SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name,
                    REFERENCED_TABLE_NAME AS referenced_table_name,
                    REFERENCED_COLUMN_NAME AS referenced_column_name,
                    CONSTRAINT_NAME AS constraint_name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',[$database]));
        $rows=[];
        foreach ($tables as $table) {
            $cols=$columns[$table]??[];
            $hasDeleted=in_array('deleted_at',$cols,true);
            $hasActive=in_array('is_active',$cols,true);
            $rows[]=['table'=>$table,'deleted_at'=>$hasDeleted,'is_active'=>$hasActive,
                'neither'=>!$hasDeleted&&!$hasActive];
        }
        $relations=[];
        foreach (self::RELATIONS as [$child,$key,$parent,$pk]) {
            $present=in_array($child,$tables,true)&&in_array($parent,$tables,true)
                && in_array($key,$columns[$child]??[],true)
                && in_array($pk,$columns[$parent]??[],true);
            $fk=$keys->first(fn($f)=>$f->table_name===$child&&$f->column_name===$key
                && $f->referenced_table_name===$parent&&$f->referenced_column_name===$pk);
            $orphans=null;
            if ($present) {
                $orphans=(int)DB::table($child.' as c')
                    ->leftJoin($parent.' as p','p.'.$pk,'=','c.'.$key)
                    ->whereNotNull('c.'.$key)->whereNull('p.'.$pk)->count();
            }
            $relations[]=['child'=>$child,'column'=>$key,'parent'=>$parent,
                'present'=>$present,'constraint'=>$fk?->constraint_name,
                'orphan_count'=>$orphans];
        }
        $crossProject=[];
        foreach ([
            ['journal_voucher_details','journal_voucher_id','journal_vouchers'],
            ['commision_voucher_details','commision_voucher_id','commision_vouchers'],
        ] as [$details,$sourceFk,$source]) {
            $count=null;
            if (in_array($details,$tables,true)&&in_array($source,$tables,true)
                && in_array('project_head_subheads',$tables,true)) {
                $count=(int)DB::table($details.' as detail')
                    ->join($source.' as header','header.id','=','detail.'.$sourceFk)
                    ->join('project_head_subheads as account','account.id','=','detail.account_id')
                    ->whereColumn('account.project_id','!=','header.project_id')->count();
            }
            $crossProject[]=['details'=>$details,'cross_project_rows'=>$count];
        }
        $report=['database'=>$database,'read_only'=>true,'tables_total'=>count($tables),
            'tables_with_neither'=>array_values(array_filter($rows,fn($r)=>$r['neither'])),
            'all_tables'=>$rows,'relationships'=>$relations,'cross_project_detail_links'=>$crossProject,
            'note'=>'Not every technical/log/history table should receive a deleted_at or is_active column. Review business tables before migration. ledgers.type_id is polymorphic and must never get a single-target FK.'];
        if ($this->option('json')) {
            $this->line(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('READ-ONLY voucher schema audit: '.$database);
            $this->table(['Table missing both deleted_at and is_active'],
                array_map(fn($r)=>[$r['table']],$report['tables_with_neither']));
            $this->table(['Child','Reference','Parent','FK','Orphans'],
                array_map(fn($r)=>[$r['child'],$r['column'],$r['parent'],
                    $r['constraint']??($r['present']?'MISSING':'TABLE/COLUMN MISSING'),
                    $r['orphan_count']===null?'N/A':$r['orphan_count']],$relations));
            $this->table(['Voucher details','Cross-project account links'],
                array_map(fn($r)=>[$r['details'],$r['cross_project_rows']??'N/A'],$crossProject));
        }
        return self::SUCCESS;
    }
}
