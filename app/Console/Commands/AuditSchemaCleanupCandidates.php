<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Read-only conservative table-dependency inspection.
 * NEVER drops an existing table: referenced or populated tables are retained.
 */
final class AuditSchemaCleanupCandidates extends Command
{
    protected $signature='schema:audit-cleanup-candidates {--database= : Exact connected DB} {--json : Machine-readable output}';
    protected $description='Identify truly empty and unreferenced schema candidates before editing baseline migrations';

    public function handle(): int
    {
        $database=(string)DB::connection()->getDatabaseName();
        if($database===''||$this->option('database')!==$database){
            $this->error('Exact --database is required. No database change attempted.');
            return self::INVALID;
        }
        $files=[];
        foreach(['app','routes','resources/views','config'] as $directory){
            $path=base_path($directory);
            if(!is_dir($path))continue;
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $path,\FilesystemIterator::SKIP_DOTS)) as $file){
                if(!$file->isFile()||!preg_match('/\.(php|js)$/i',$file->getFilename()))continue;
                $raw=file_get_contents($file->getPathname());
                if(is_string($raw))$files[$file->getPathname()]=$raw;
            }
        }
        $foreignKeys=DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA',$database)->whereNotNull('REFERENCED_TABLE_NAME')
            ->get(['TABLE_NAME','REFERENCED_TABLE_NAME']);
        $fkCounts=[];
        foreach($foreignKeys as $row){
            foreach([(string)$row->TABLE_NAME,(string)$row->REFERENCED_TABLE_NAME] as $table){
                $fkCounts[$table]=($fkCounts[$table]??0)+1;
            }
        }
        $tables=Schema::getTableListing();
        sort($tables);
        $report=[];
        foreach($tables as $table){
            // Show references as a conservative heuristic, not an absolute
            // proof: dynamic names and SQL expressions may be indirect.
            $used=[];
            foreach($files as $path=>$contents){
                if(str_contains($contents,$table))$used[]=str_replace(base_path().DIRECTORY_SEPARATOR,'',$path);
            }
            // This check is efficient even for huge historical tables.
            $hasRows=DB::table($table)->limit(1)->exists();
            $status=$hasRows?'RETAIN_DATA':($used!==[]?'RETAIN_REFERENCED':
                (($fkCounts[$table]??0)>0?'RETAIN_FOREIGN_KEY':'REVIEW_ONLY'));
            $report[]=['table'=>$table,'has_rows'=>$hasRows,
                'runtime_references'=>count($used),'fk_links'=>$fkCounts[$table]??0,
                'status'=>$status,'sample_references'=>array_slice($used,0,4)];
        }
        $candidates=array_values(array_filter($report,fn($x)=>$x['status']==='REVIEW_ONLY'));
        if($this->option('json')){
            $this->line(json_encode(['database'=>$database,'physical_tables'=>count($tables),
                'review_only_count'=>count($candidates),'candidates'=>$candidates,
                'all_tables'=>$report],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        }else{
            $this->info('READ ONLY '.$database.'; physical tables: '.count($tables).
                '; potential review candidates: '.count($candidates));
            $this->table(['Table','Rows?','Code refs','FK links','Decision'],
                array_map(fn($r)=>[$r['table'],$r['has_rows']?'yes':'no',
                    $r['runtime_references'],$r['fk_links'],$r['status']],$report));
            $this->warn('REVIEW_ONLY is NOT authorization to drop. Check dynamic SQL, third-party access and staging import requirements first.');
        }
        return self::SUCCESS;
    }
}
