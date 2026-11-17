<?php
declare(strict_types=1);

/**
 * One-time, local-only, data-only restore: bluenew -> bluenew_clean.
 * DRY RUN: php tools/schema/copy_clean_data.php
 * APPLY:   php tools/schema/copy_clean_data.php --apply
 * Never writes to original database or changes schema.
 */
if (PHP_SAPI !== 'cli' || $argc > 2 || ($argc === 2 && $argv[1] !== '--apply')) {
    fwrite(STDERR, "Usage: php tools/schema/copy_clean_data.php [--apply]\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$source='bluenew';
$target='bluenew_clean';
$apply=($argv[1]??'')==='--apply';
$db=DB::connection();
$cfg=config('database.connections.'.config('database.default'));
if (!app()->environment('local') || $db->getDriverName()!=='mysql'
    || !in_array(strtolower((string)($cfg['host']??'')),['localhost','127.0.0.1','::1'],true)
    || !in_array($db->getDatabaseName(),[$source,$target],true)) {
    throw new RuntimeException('Refusing unsafe database or environment');
}
$q=static function(string $name): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$name)) throw new RuntimeException('Unsafe identifier');
    return chr(96).$name.chr(96);
};
$tables=static function(string $name) use($db): array {
    return array_map(static fn($r)=>$r->t,$db->select(
        "SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME",[$name]));
};
$columns=static function(string $name,string $table) use($db): array {
    return array_map(static fn($r)=>$r->c,$db->select(
        "SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION",[$name,$table]));
};
$count=static function(string $name,string $table) use($db,$q): int {
    return (int)$db->selectOne('SELECT COUNT(*) n FROM '.$q($name).'.'.$q($table))->n;
};
$a=$tables($source);
$b=$tables($target);
if (count($a)!==147 || $a!==$b || !in_array('migrations',$a,true))
    throw new RuntimeException('Source/destination schema table names mismatch');
if ($count($target,'migrations')!==1)
    throw new RuntimeException('Expected one baseline migration in clean target');
$planned=[];$total=0;
foreach($a as $table) {
    if ($table==='migrations') continue;
    $cols=$columns($source,$table);
    if ($cols!==$columns($target,$table))
        throw new RuntimeException('Column mismatch: '.$table);
    $sourceCount=$count($source,$table);
    if ($count($target,$table)!==0)
        throw new RuntimeException('Destination must be empty: '.$table);
    $planned[]=[$table,$cols,$sourceCount];
    $total+=$sourceCount;
}
echo "PREFLIGHT PASS: 146 matched empty tables; $total source rows; migration history excluded.\n";
if (!$apply) { echo "DRY RUN only. Run again with --apply.\n";exit(0); }
$db->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
$db->statement("SET SESSION sql_mode=CONCAT_WS(',',NULLIF(@@SESSION.sql_mode,''),'NO_AUTO_VALUE_ON_ZERO')");
try {
    foreach($planned as [$table,$cols,$n]) {
        if (!$n) continue;
        $colList=implode(',',array_map($q,$cols));
        $db->statement('INSERT INTO '.$q($target).'.'.$q($table).' ('.$colList.') SELECT '.
            $colList.' FROM '.$q($source).'.'.$q($table));
        if ($count($target,$table)!==$n) throw new RuntimeException('Copy mismatch: '.$table);
        echo "COPIED $table: $n\n";
    }
} finally {
    $db->statement('SET SESSION FOREIGN_KEY_CHECKS=1');
}
$problems=[];
foreach($planned as [$table,$cols,$n]) {
    if ($count($target,$table)!==$n) $problems[]="Count mismatch: $table";
}
$fkRows=$db->select("SELECT k.CONSTRAINT_NAME fk,k.TABLE_NAME child_table,k.COLUMN_NAME child_col,
 k.REFERENCED_TABLE_NAME parent_table,k.REFERENCED_COLUMN_NAME parent_col
 FROM information_schema.KEY_COLUMN_USAGE k
 JOIN information_schema.TABLE_CONSTRAINTS t
 ON t.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND t.TABLE_NAME=k.TABLE_NAME AND t.CONSTRAINT_NAME=k.CONSTRAINT_NAME
 WHERE k.CONSTRAINT_SCHEMA=? AND t.CONSTRAINT_TYPE='FOREIGN KEY'
 ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION",[$target]);
$groups=[];
foreach ($fkRows as $fk) $groups[$fk->child_table.'|'.$fk->fk][]=$fk;
foreach($groups as $refs) {
    $x=$refs[0];$joins=[];$nonnull=[];
    foreach($refs as $fk) {
        $joins[]='c.'.$q($fk->child_col).'=p.'.$q($fk->parent_col);
        $nonnull[]='c.'.$q($fk->child_col).' IS NOT NULL';
    }
    $sql='SELECT COUNT(*) n FROM '.$q($target).'.'.$q($x->child_table).' c LEFT JOIN '.
        $q($target).'.'.$q($x->parent_table).' p ON '.implode(' AND ',$joins).
        ' WHERE '.implode(' AND ',$nonnull).' AND p.'.$q($x->parent_col).' IS NULL';
    $orphans=(int)$db->selectOne($sql)->n;
    if($orphans) $problems[]="FK orphan {$x->child_table}.{$x->fk}: $orphans";
}
echo "CHECKED: ".count($planned)." tables, ".count($groups)." foreign keys.\n";
if($problems) {
    fwrite(STDERR,"RECONCILIATION ERRORS:\n".implode("\n",$problems)."\n");
    exit(1);
}
echo "PASS: $total rows copied; table counts and declared foreign key relationships verified.\n";
echo "Accounting/balance checks and browser acceptance remain pending.\n";
