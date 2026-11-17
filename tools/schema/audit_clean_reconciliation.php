<?php
declare(strict_types=1);

/**
 * Read-only local audit of schema fingerprints and finance totals.
 * php tools/schema/audit_clean_reconciliation.php
 * php tools/schema/audit_clean_reconciliation.php --target=bluenew_clean_native --schema-only
 */
if (PHP_SAPI !== 'cli') exit(2);
$options=array_slice($argv,1);
if (array_diff($options,['--target=bluenew_clean_native','--target=bluenew_clean_final','--schema-only'])) {
    fwrite(STDERR,"Usage: php tools/schema/audit_clean_reconciliation.php [--target=bluenew_clean_native|--target=bluenew_clean_final] [--schema-only]\n");
    exit(2);
}
$schemaOnly=in_array('--schema-only',$options,true);
$targetName=in_array('--target=bluenew_clean_final',$options,true)?'bluenew_clean_final':(in_array('--target=bluenew_clean_native',$options,true)?'bluenew_clean_native':'bluenew_clean');
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn=DB::connection();
$cfg=config('database.connections.'.config('database.default'));
if (!app()->environment('local') || $conn->getDriverName()!=='mysql'
    || !in_array(strtolower((string)($cfg['host']??'')),['localhost','127.0.0.1','::1'],true)
    || !in_array($conn->getDatabaseName(),['bluenew','bluenew_clean','bluenew_clean_native','bluenew_clean_final'],true)) {
    throw new RuntimeException('This audit is restricted to local bluenew/bluenew_clean.');
}
$quote=static function(string $id): string {
    if(!preg_match('/^[a-zA-Z0-9_]+$/D',$id)) throw new RuntimeException('Unsafe identifier');
    return chr(96).$id.chr(96);
};
$old='bluenew';$new=$targetName;$errors=[];
$rows=static fn(string $sql,array $params=[])=>$conn->select($sql,$params);
$signature=static function(string $db,string $sql) use($rows): array {
    return array_map(static function($r):array{return array_values((array)$r);},$rows($sql,[$db]));
};
$hash=static fn(array $data):string=>hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

$queries=[
    'column definitions'=>"SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,
      COLUMN_DEFAULT,EXTRA,COLLATION_NAME,GENERATION_EXPRESSION
      FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,ORDINAL_POSITION",
    'indexes'=>"SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,
      INDEX_TYPE,COLLATION,EXPRESSION,IS_VISIBLE
      FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=?
      ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",
    'foreign keys'=>"SELECT TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,
      REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,POSITION_IN_UNIQUE_CONSTRAINT
      FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL
      ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION",
    'foreign key rules'=>"SELECT CONSTRAINT_NAME,TABLE_NAME,REFERENCED_TABLE_NAME,
      UPDATE_RULE,DELETE_RULE,MATCH_OPTION
      FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA=? ORDER BY TABLE_NAME,CONSTRAINT_NAME",
    'table engines'=>"SELECT TABLE_NAME,ENGINE,TABLE_COLLATION,ROW_FORMAT
      FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'
      ORDER BY TABLE_NAME",
];
foreach($queries as $title=>$sql) {
    $a=$signature($old,$sql);$b=$signature($new,$sql);
    $ok=$a===$b;
    echo ($ok?'PASS':'FAIL')." SCHEMA $title (".count($a)." vs ".count($b).")\n";
    if(!$ok) {
        $errors[]="Schema $title differs";
        for($i=0;$i<max(count($a),count($b));$i++) {
            if(($a[$i]??null)!==($b[$i]??null)) {
                echo ' First difference at item '.($i+1).': '.
                    json_encode(['source'=>$a[$i]??null,'target'=>$b[$i]??null],JSON_UNESCAPED_SLASHES)."\n";
                break;
            }
        }
    }
}

if ($schemaOnly) {
    if ($errors) {
        fwrite(STDERR,"SCHEMA AUDIT FAILED:\n - ".implode("\n - ",$errors)."\n");
        exit(1);
    }
    echo "PASS: schema-only signatures match for $new. Data not checked.\n";
    exit(0);
}

// Read-only equality checks on money columns, not a claim of ledger correctness.
// MySQL decimal SUM gives exact arithmetic. Integer money-like values are not
// silently cast to floating point.
$financeTables=['ledgers','customer_ledger','journal_vouchers','journal_voucher_details',
    'commision_vouchers','commision_voucher_details','booking_vouchers',
    'finance_posting_batches','finance_posting_lines','finance_plot_purchases',
    'finance_plot_purchase_items','finance_plot_receipts','finance_plot_receipt_allocations',
    'finance_posting_cash_sources','finance_bank_transfer_sources','labour_ledgers',
    'draft_ledgers','dasticash','accounting_expenses'];
$checked=0;
foreach($financeTables as $table) {
    $cols=$rows("SELECT COLUMN_NAME c,DATA_TYPE t FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND DATA_TYPE IN ('decimal','numeric','int','bigint','smallint','mediumint','tinyint')
      ORDER BY ORDINAL_POSITION",[$old,$table]);
    if(!$cols)continue;
    $expr=[];
    foreach($cols as $col) {
        $name=$quote($col->c);
        $expr[]="COALESCE(CAST(SUM($name) AS CHAR),'NULL') AS ".$name;
    }
    $sql='SELECT '.implode(',',$expr).' FROM %s.'.$quote($table);
    $a=(array)$conn->selectOne(sprintf($sql,$quote($old)));
    $b=(array)$conn->selectOne(sprintf($sql,$quote($new)));
    if($a!==$b) {
        $errors[]='Numeric aggregate mismatch: '.$table;
        echo 'FAIL FINANCE '.$table.' source='.json_encode($a).' target='.json_encode($b)."\n";
    } else {
        echo 'PASS FINANCE '.$table.' ('.count($cols)." numeric aggregates)\n";
    }
    ++$checked;
}
if(!$checked)$errors[]='No financial tables compared';

// 'punches' has no PK: compare every row with multiset semantics, preserving
// duplicate rows and NULLs. This is only 30 records in source fixture.
$columns=$rows("SELECT COLUMN_NAME c FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=? AND TABLE_NAME='punches' ORDER BY ORDINAL_POSITION",[$old]);
if($columns) {
    $names=array_map(static fn($x)=>$x->c,$columns);
    $readPunches=static function(string $db) use($conn,$quote,$names): array {
        $values=$conn->select('SELECT '.implode(',',array_map($quote,$names)).
          ' FROM '.$quote($db).'.'.$quote('punches'));
        $counts=[];
        foreach($values as $v) {
            $record=array_values((array)$v);
            $key=hash('sha256',serialize($record));
            $counts[$key]=($counts[$key]??0)+1;
        }
        ksort($counts);
        return $counts;
    };
    $a=$readPunches($old);$b=$readPunches($new);
    echo ($a===$b?'PASS':'FAIL')." PUNCHES full-row multiset comparison\n";
    if($a!==$b)$errors[]='punches multiset differs';
}
if($errors) {
    fwrite(STDERR,"AUDIT FAILED (read-only):\n - ".implode("\n - ",$errors)."\n");
    exit(1);
}
echo "PASS: schema signatures, $checked finance-table numeric aggregates and punches row data match.\n";
echo "NOTE: equality to old database does not prove independent accounting correctness, app acceptance, or index speed.\n";
