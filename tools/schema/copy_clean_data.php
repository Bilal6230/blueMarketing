<?php
declare(strict_types=1);

/**
 * One-time, local-only, data-only restore: bluenew -> bluenew_clean.
 * DRY RUN: php tools/schema/copy_clean_data.php
 * APPLY:   php tools/schema/copy_clean_data.php --apply
 * RESUME:  php tools/schema/copy_clean_data.php --resume (after interrupted prior copy)
 * FINAL: php tools/schema/copy_clean_data.php --target=bluenew_clean_final [--apply|--resume]
 * Never writes to original database or changes schema.
 */
if (PHP_SAPI !== 'cli' || count(array_diff(array_slice($argv,1), ['--apply','--resume','--target=bluenew_clean_final'])) > 0
    || (in_array('--apply', $argv, true) && in_array('--resume', $argv, true))) {
    fwrite(STDERR, "Usage: php tools/schema/copy_clean_data.php [--target=bluenew_clean_final] [--apply|--resume]\\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$source='bluenew';
$target=in_array('--target=bluenew_clean_final', $argv, true)?'bluenew_clean_final':'bluenew_clean';
$apply=in_array('--apply',$argv,true)||in_array('--resume',$argv,true);
$resume=in_array('--resume',$argv,true);
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
$expectedMigrationCount=$target==='bluenew_clean_final'?148:1;
if ($count($target,'migrations')!==$expectedMigrationCount)
    throw new RuntimeException('Expected '.$expectedMigrationCount.' migration records in '.$target);
$planned=[];$total=0;
foreach($a as $table) {
    if ($table==='migrations') continue;
    $cols=$columns($source,$table);
    if ($cols!==$columns($target,$table))
        throw new RuntimeException('Column mismatch: '.$table);
    $sourceCount=$count($source,$table);
    $destCount=$count($target,$table);
    if ($destCount!==0 && (!$resume || $destCount!==$sourceCount))
        throw new RuntimeException('Destination already populated or incomplete: '.$table.' source='.$sourceCount.' dest='.$destCount.'; use --resume only for exactly matched copied tables');
    $planned[]=[$table,$cols,$sourceCount,$destCount];
    $total+=$sourceCount;
}
echo "PREFLIGHT PASS: 146 schema-matched tables in $target; $total source rows; migration history excluded; mode=".($resume?'RESUME':($apply?'APPLY':'DRY RUN')).".\n";
if (!$apply) { echo "DRY RUN only. Use --apply on an empty destination, or --resume after a prior interrupted copy.\n";exit(0); }
$db->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
// The source contains historical '0000-00-00 00:00:00' DATETIME values.
// MySQL 8.4 strict/no-zero-date modes reject them on INSERT ... SELECT.
// Restrict relaxed SQL mode to THIS CONNECTION/SESSION only. Preserve id=0.
$oldMode=(string)$db->selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode;
$db->statement("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
try {
    foreach($planned as [$table,$cols,$n,$alreadyCopied]) {
        if ($alreadyCopied === $n && $alreadyCopied > 0) {
            echo "SKIPPED VERIFIED COUNT $table: $n\n";
            continue;
        }
        if (!$n) continue;
        $colList=implode(',',array_map($q,$cols));
        $db->statement('INSERT INTO '.$q($target).'.'.$q($table).' ('.$colList.') SELECT '.
            $colList.' FROM '.$q($source).'.'.$q($table));
        if ($count($target,$table)!==$n) throw new RuntimeException('Copy mismatch: '.$table);
        echo "COPIED $table: $n\n";
    }
} finally {
    $db->statement('SET SESSION FOREIGN_KEY_CHECKS=1');
    $db->statement("SET SESSION sql_mode = ".$db->getPdo()->quote($oldMode));
}
$problems=[];
foreach($planned as [$table,$cols,$n,$alreadyCopied]) {
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
// Verify every source row against the restored destination for tables with a PK.
// This catches MySQL's relaxed-mode coercions and incorrect copied resume rows.
$primaryKeys=$db->select("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION",[$target]);
$pkMap=[];
foreach($primaryKeys as $pk) $pkMap[$pk->t][]=$pk->c;
$noPrimary=[];
foreach($planned as [$table,$cols,$n,$alreadyCopied]) {
    if (!$n) continue;
    if (empty($pkMap[$table])) { $noPrimary[]=$table;continue; }
    $keys=$pkMap[$table];
    $on=implode(' AND ',array_map(static fn($col)=>'s.'.$q($col).' <=> d.'.$q($col),$keys));
    $same=implode(' AND ',array_map(static fn($col)=>'s.'.$q($col).' <=> d.'.$q($col),$cols));
    $missing=(int)$db->selectOne('SELECT COUNT(*) n FROM '.$q($source).'.'.$q($table).
        ' s LEFT JOIN '.$q($target).'.'.$q($table).' d ON '.$on.
        ' WHERE d.'.$q($keys[0]).' IS NULL OR NOT ('.$same.')')->n;
    if ($missing) $problems[]="Source-vs-destination row data mismatch: $table ($missing)";
}
if ($noPrimary) {
    echo "NOTE: no primary-key row comparison for ".implode(', ',$noPrimary)."; count checked only.\n";
}
echo "CHECKED: ".count($planned)." tables, ".count($groups)." foreign keys.\n";
if($problems) {
    fwrite(STDERR,"RECONCILIATION ERRORS:\n".implode("\n",$problems)."\n");
    exit(1);
}
echo "PASS: $total rows copied; table counts and declared foreign key relationships verified.\n";
echo "Accounting/balance checks and browser acceptance remain pending.\n";
