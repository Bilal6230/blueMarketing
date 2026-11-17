<?php
declare(strict_types=1);

/**
 * Local read-only application readiness gate.
 * Usage: php tools/schema/accept_clean_application.php
 * No INSERT, UPDATE, DELETE, migrations or seeding.
 */
if (PHP_SAPI !== 'cli' || $argc !== 1) {
    fwrite(STDERR, "Usage: php tools/schema/accept_clean_application.php\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$db=DB::connection();
$cfg=config('database.connections.'.config('database.default'));
$name=$db->getDatabaseName();
$host=strtolower((string)($cfg['host']??''));
if (!app()->environment('local') || $db->getDriverName()!=='mysql'
    || $name!=='bluenew_clean_final'
    || !in_array($host,['localhost','127.0.0.1','::1'],true)
    || !empty($cfg['url'])) {
    throw new RuntimeException('REFUSED: local MySQL bluenew_clean_final without connection URL required');
}
$pass=0;$fail=0;
$check=static function(bool $ok,string $label) use(&$pass,&$fail): void {
    echo ($ok?'PASS':'FAIL').' '.$label."\n";
    if($ok)++$pass;else ++$fail;
};
$quote=static fn(string $v)=>chr(96).str_replace(chr(96),chr(96).chr(96),$v).chr(96);
$tables=array_map(static fn($r)=>(string)$r->table_name,$db->select(
    "SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'",[$name]
));
$check(count($tables)===147,'147 restored tables');
$check(in_array('migrations',$tables,true),'Migration history table present');
$history=(int)$db->table('migrations')->count();
$check($history===149,"149 migrations; actual=$history");
$counts=[];
foreach($tables as $table) {
    if($table==='migrations')continue;
    $counts[$table]=(int)$db->selectOne("SELECT COUNT(*) n FROM ".$quote($table))->n;
}
$sum=array_sum($counts);
$check(count($counts)===146,'146 application tables scanned');
$check($sum===120835,"120835 restored rows; actual=$sum");
$expected=[
    'users'=>53,'roles'=>9,'permissions'=>101,'model_has_roles'=>55,
    'role_has_permissions'=>238,'projects'=>6,'leads'=>13397,
    'works'=>53405,'bookings'=>198,'booking_details'=>3970,
    'customer_ledger'=>5635,'ledgers'=>18096,'journal_vouchers'=>996,
    'journal_voucher_details'=>5175,'finance_posting_batches'=>13,
    'finance_posting_lines'=>27,'finance_cheque_intakes'=>8,
    'finance_online_intakes'=>3,
];
foreach($expected as $table=>$expectedCount) {
    $value=$counts[$table]??null;
    $check($value===$expectedCount,"$table rows: expected=$expectedCount actual=".($value??'MISSING'));
}
$fk=(int)$db->selectOne(
    "SELECT COUNT(*) n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=?",[$name]
)->n;
$check($fk===250,"250 foreign key constraints; actual=$fk");
$fkRows=$db->select(
    "SELECT k.CONSTRAINT_NAME fk,k.TABLE_NAME child,k.COLUMN_NAME child_col,
       k.REFERENCED_TABLE_NAME parent,k.REFERENCED_COLUMN_NAME parent_col,
       k.ORDINAL_POSITION ordinal
     FROM information_schema.KEY_COLUMN_USAGE k
     WHERE k.TABLE_SCHEMA=? AND k.REFERENCED_TABLE_NAME IS NOT NULL
     ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION",[$name]
);
$groups=[];
foreach($fkRows as $part)$groups[$part->child.'|'.$part->fk][]=$part;
$orphans=0;
foreach($groups as $items) {
    $first=$items[0];
    $join=[];$nonnull=[];
    foreach($items as $part) {
        $c='c.'.$quote($part->child_col);
        $join[]=$c.' = p.'.$quote($part->parent_col);
        $nonnull[]=$c.' IS NOT NULL';
    }
    $sql='SELECT COUNT(*) n FROM '.$quote($first->child).' c WHERE '.
        implode(' AND ',$nonnull).' AND NOT EXISTS (SELECT 1 FROM '.
        $quote($first->parent).' p WHERE '.implode(' AND ',$join).')';
    $missing=(int)$db->selectOne($sql)->n;
    if($missing>0) {
        $orphans+=$missing;
        echo 'FAIL ORPHANS '.$first->child.'.'.$first->fk.': '.$missing."\n";
    }
}
$check(count($groups)===250,"250 FK definitions scanned; actual=".count($groups));
$check($orphans===0,"No FK orphans; actual=$orphans");
echo "\nREAD-ONLY ACCEPTANCE: $pass passed, $fail failed.\n";
echo "NOTE: Browser login, authorization and independent accounting business logic remain separate.\n";
exit($fail===0?0:1);
