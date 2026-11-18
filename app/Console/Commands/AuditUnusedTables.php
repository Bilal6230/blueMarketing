<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only database / runtime-source inventory.
 * "Unreferenced" is a review candidate, never proof that a table is unused.
 */
final class AuditUnusedTables extends Command
{
    protected $signature = 'schema:audit-unused-tables
        {--json : Output machine-readable JSON}
        {--exact-empty : Verify unreferenced row counts with SELECT COUNT(*) instead of relying on MySQL estimates}';

    protected $description = 'Read-only scan of database table usage, foreign-key references, and application source code';

    public function handle(): int
    {
        $database=(string)DB::connection()->getDatabaseName();
        if ($database==='') {
            $this->error('Database connection has no selected schema.');
            return self::FAILURE;
        }

        $tables=DB::select('SELECT TABLE_NAME AS table_name, TABLE_ROWS AS estimated_rows
            FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=?
            ORDER BY TABLE_NAME',[$database,'BASE TABLE']);
        $fkRows=DB::select('SELECT TABLE_NAME AS child, REFERENCED_TABLE_NAME AS parent
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$database]);
        $viewRows=DB::select('SELECT TABLE_NAME AS view_name, VIEW_DEFINITION AS definition
            FROM information_schema.VIEWS WHERE TABLE_SCHEMA=?',[$database]);
        $triggerRows=DB::select('SELECT TRIGGER_NAME AS trigger_name,
                EVENT_OBJECT_TABLE AS source_table, ACTION_STATEMENT AS statement
            FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=?',[$database]);

        $sources=$this->runtimeSources();
        $report=[];
        foreach ($tables as $table) {
            $name=(string)$table->table_name;
            $references=[];
            $pattern='/(?<![a-zA-Z0-9_])'.preg_quote($name,'/').'(?![a-zA-Z0-9_])/i';
            foreach ($sources as $file=>$body) {
                if (preg_match($pattern,$body)) $references[]=$file;
            }
            $incoming=collect($fkRows)->filter(fn($r)=>$r->parent===$name)
                ->pluck('child')->unique()->values()->all();
            $outgoing=collect($fkRows)->filter(fn($r)=>$r->child===$name)
                ->pluck('parent')->unique()->values()->all();
            $viewReferences=collect($viewRows)->filter(fn($r)=>
                preg_match($pattern,(string)$r->definition))->pluck('view_name')->values()->all();
            $triggerReferences=collect($triggerRows)->filter(fn($r)=>
                $r->source_table===$name || preg_match($pattern,(string)$r->statement))
                ->pluck('trigger_name')->values()->all();
            $estimated=$table->estimated_rows===null?null:(int)$table->estimated_rows;
            // COUNT(*) is exact only for candidates or when explicitly asked,
            // and does not write any business data.
            $count=null;
            $noRuntimeRef=count($references)===0 && !$incoming && !$outgoing
                && !$viewReferences && !$triggerReferences;
            if ($this->option('exact-empty') || $noRuntimeRef) {
                // Name is from the database's own information_schema (not user input).
                $quoted='`'.str_replace('`','``',$name).'`';
                $count=(int)DB::table(DB::raw($quoted))->count();
            }
            $status= $noRuntimeRef && $count===0 ? 'REVIEW_EMPTY_UNREFERENCED'
                : ($noRuntimeRef ? 'REVIEW_UNREFERENCED_WITH_DATA' : 'REFERENCED_OR_DEPENDENT');
            $report[]=[
                'table'=>$name,'status'=>$status,
                'estimated_rows'=>$estimated,'exact_rows'=>$count,
                'application_reference_count'=>count($references),
                'application_reference_examples'=>array_slice($references,0,12),
                'incoming_fk_tables'=>$incoming,'outgoing_fk_tables'=>$outgoing,
                'views'=>$viewReferences,'triggers'=>$triggerReferences,
            ];
        }
        $result=[
            'database'=>$database,'read_only'=>true,'tables_scanned'=>count($tables),
            'runtime_source_files_scanned'=>count($sources),
            'warning'=>'Static scan cannot prove a table is unused: dynamic query names, external apps, jobs, exports, and historical migrations may still depend on it. Never drop based only on this report.',
            'candidates'=>array_values(array_filter($report,fn($r)=>str_starts_with($r['status'],'REVIEW_'))),
            'tables'=>$report,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        } else {
            $this->info('READ-ONLY schema usage audit: '.$database);
            $this->line('Tables: '.count($tables).' | Runtime source files: '.count($sources));
            $this->table(['Candidate table','Status','Estimated rows','Exact rows','Source matches'],
                array_map(fn($r)=>[
                    $r['table'],$r['status'],$r['estimated_rows']??'?',
                    $r['exact_rows']??'?',$r['application_reference_count'],
                ],$result['candidates']));
            $this->warn('No tables have been dropped. REVIEW candidates are NOT automatically safe to delete.');
        }
        return self::SUCCESS;
    }

    /** @return array<string,string> Only live application references; migrations/tests excluded on purpose. */
    private function runtimeSources(): array
    {
        $root=base_path();
        $folders=['app','routes','resources/views','resources/js','public/js','config'];
        $output=[];
        foreach ($folders as $folder) {
            $path=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$folder);
            if (!is_dir($path)) continue;
            $iterator=new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array(strtolower($file->getExtension()),['php','js'],true)) continue;
                $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
                if (str_contains($relative,'/vendor/') || str_contains($relative,'/node_modules/')) continue;
                // Keep a bounded scan; minified third-party files do not
                // describe authoritative application table usage.
                if ($file->getSize()>500000 || preg_match('/(?:\.min\.js|\.bundle\.js)$/',$relative)) continue;
                $content=file_get_contents($file->getPathname());
                if ($content!==false) $output[$relative]=$content;
            }
        }
        return $output;
    }
}
