<?php
declare(strict_types=1);

/**
 * Read-only and local-only index inventory + EXPLAIN audit.
 *
 * php tools/schema/audit_query_performance.php
 * php tools/schema/audit_query_performance.php --json
 *
 * No migrations or mutations, and no EXPLAIN ANALYZE (which executes SQL).
 */
$flags=array_slice($argv,1);
if (PHP_SAPI!=='cli' || array_diff($flags,['--json']) || count($flags)!==count(array_unique($flags))) {
    fwrite(STDERR,"Usage: php tools/schema/audit_query_performance.php [--json]\n");
    exit(2);
}
$json=in_array('--json',$flags,true);

require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$db=DB::connection();
$cfg=config('database.connections.'.config('database.default'));
$host=strtolower((string)($cfg['host']??''));
if (!app()->environment('local') || $db->getDriverName()!=='mysql'
    || $db->getDatabaseName()!=='bluenew_clean_final'
    || !in_array($host,['localhost','127.0.0.1','::1'],true)
    || !empty($cfg['url'])) {
    throw new RuntimeException('REFUSED: performance audit requires local bluenew_clean_final without connection URL.');
}
$database='bluenew_clean_final';
$details=['database'=>$database,'mode'=>'READ_ONLY_EXPLAIN','queries'=>[],'indexes'=>[],
    'prefix_candidates'=>[],'notes'=>[],'errors'=>[]];

$info=$db->select(
    "SELECT TABLE_NAME table_name,INDEX_NAME index_name,NON_UNIQUE non_unique,
       SEQ_IN_INDEX seq_in_index,COLUMN_NAME column_name,SUB_PART sub_part,
       INDEX_TYPE index_type,EXPRESSION expression_value
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=?
     ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",[$database]
);
$groups=[];
foreach($info as $index) {
    $key=$index->table_name."\0".$index->index_name;
    if(!isset($groups[$key]))$groups[$key]=[
        'table'=>$index->table_name,'name'=>$index->index_name,
        'unique'=>((int)$index->non_unique)===0,'parts'=>[],
        'type'=>$index->index_type,
    ];
    $groups[$key]['parts'][]=[
        'column'=>$index->column_name,
        'expression'=>$index->expression_value,
        'prefix'=>$index->sub_part===null?null:(int)$index->sub_part,
    ];
}
$byTable=[];
foreach($groups as $g) {
    $byTable[$g['table']][]=$g;
    $details['indexes'][]=$g;
}
// Candidate means exact left prefix of another BTREE index, NOT safe to delete.
// Uniqueness, FK constraint name and optimizer behavior must be reviewed first.
foreach($byTable as $table=>$indexes) {
    foreach($indexes as $a) {
        if($a['name']==='PRIMARY' || $a['unique'] || $a['type']!=='BTREE')continue;
        foreach($indexes as $b) {
            if($a['name']===$b['name'] || $b['type']!=='BTREE'
                || count($b['parts'])<count($a['parts']))continue;
            $prefix=array_slice($b['parts'],0,count($a['parts']));
            if($a['parts']!==$prefix)continue;
            $details['prefix_candidates'][]=[
                'table'=>$table,'candidate'=>$a['name'],
                'covered_by'=>$b['name'],'candidate_columns'=>$a['parts'],
                'warning'=>'DO NOT DROP automatically; verify foreign keys, optimizer and write patterns',
            ];
            break;
        }
    }
}

$tableNames=array_keys($byTable);
$must=['projects','journal_vouchers','journal_voucher_details','ledgers',
    'customer_ledger','project_head_subheads','bookings','booking_details',
    'leads','works','finance_posting_batches','finance_posting_lines'];
$missing=array_values(array_diff($must,$tableNames));
if($missing)throw new RuntimeException('Missing expected tables: '.implode(',',$missing));
$cols=[];
foreach($must as $table)$cols[$table]=array_map(
    static fn($r)=>(string)$r->name,
    $db->select("SELECT COLUMN_NAME name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?",[$database,$table])
);
$has=static fn(string $table,array $columns):bool=>count(array_diff($columns,$cols[$table]??[]))===0;
$quote=static fn(string $id):string=>chr(96).str_replace(chr(96),chr(96).chr(96),$id).chr(96);
$sampleProject=(int)($db->selectOne(
    'SELECT id FROM projects ORDER BY id LIMIT 1'
)->id??0);
if($sampleProject===0)throw new RuntimeException('No project rows found for representative explain filters.');

// Explain uses constant fixture values only and never materializes full result data.
$cases=[
    ['journal_vouchers project/type recent','journal_vouchers',
     ['project_id','type','id'],
     'SELECT id, voucher_number, total_debit FROM journal_vouchers WHERE project_id=? AND type=? ORDER BY id DESC LIMIT 25',
     [$sampleProject,'JV']],
    ['journal details by voucher','journal_voucher_details',
     ['journal_voucher_id'],
     'SELECT * FROM journal_voucher_details WHERE journal_voucher_id=? LIMIT 100',[1]],
    ['ledger by voucher source','ledgers',
     ['type','type_id'],
     'SELECT id FROM ledgers WHERE type=? AND type_id=? AND is_active=? ORDER BY id DESC LIMIT 50',
     ['JV',1,1]],
    ['account-scoped active ledgers','ledgers',
     ['project_head_subheads_id','is_active','amount_in','amount_out'],
     'SELECT id,amount_in,amount_out FROM ledgers WHERE project_head_subheads_id=? AND is_active=? ORDER BY id DESC LIMIT 50',
     [1,1]],
    ['customer ledger project/type','customer_ledger',
     ['project_id','transaction_type'],
     'SELECT id FROM customer_ledger WHERE project_id=? AND transaction_type=? ORDER BY id DESC LIMIT 25',
     [$sampleProject,'CR']],
    ['booking project/cancellation','bookings',
     ['project_id','cancel_status'],
     'SELECT id FROM bookings WHERE project_id=? AND cancel_status=? ORDER BY id DESC LIMIT 25',
     [$sampleProject,0]],
    ['booking details by booking','booking_details',
     ['booking_id'],
     'SELECT * FROM booking_details WHERE booking_id=? LIMIT 50',[1]],
    ['lead project recent','leads',
     ['project_id','id'],
     'SELECT id FROM leads WHERE project_id=? ORDER BY id DESC LIMIT 25',[$sampleProject]],
    ['work by lead','works',
     ['lead_id'],
     'SELECT id FROM works WHERE lead_id=? ORDER BY id DESC LIMIT 25',[1]],
    ['project account lookup','project_head_subheads',
     ['project_id','head_accounting_id','subhead_accounting_id'],
     'SELECT id FROM project_head_subheads WHERE project_id=? AND head_accounting_id=? AND subhead_accounting_id=? LIMIT 20',
     [$sampleProject,6,1]],
    ['finance posting project recent','finance_posting_batches',
     ['project_id'],
     'SELECT id FROM finance_posting_batches WHERE project_id=? ORDER BY id DESC LIMIT 25',[$sampleProject]],
    ['posting lines by batch','finance_posting_lines',
     ['posting_batch_id'],
     'SELECT id FROM finance_posting_lines WHERE posting_batch_id=? ORDER BY id DESC LIMIT 50',[1]],
];
foreach($cases as [$label,$table,$required,$sql,$params]) {
    if(!$has($table,$required)) {
        $details['queries'][]=['label'=>$label,'status'=>'SKIPPED','reason'=>'Column naming not present: '.implode(',',$required)];
        continue;
    }
    try {
        $plan=$db->select('EXPLAIN '.$sql,$params);
        $plans=array_map(static fn($p)=>(array)$p,$plan);
        $flags=[];
        foreach($plans as $p) {
            if(($p['type']??'')==='ALL')$flags[]='FULL_SCAN';
            if(str_contains((string)($p['Extra']??''),'Using filesort'))$flags[]='FILESORT';
            if(str_contains((string)($p['Extra']??''),'Using temporary'))$flags[]='TEMP_TABLE';
        }
        $details['queries'][]=[
            'label'=>$label,'status'=>'EXPLAINED','table'=>$table,
            'sql'=>$sql,'sample_parameters'=>$params,
            'plan'=>$plans,'flags'=>array_values(array_unique($flags))
        ];
    } catch(Throwable $e) {
        $details['errors'][]=['label'=>$label,'error'=>$e->getMessage()];
    }
}
$details['summary']=[
    'index_definitions'=>count($groups),
    'index_column_entries'=>count($info),
    'prefix_candidates'=>count($details['prefix_candidates']),
    'explained'=>count(array_filter($details['queries'],static fn($x)=>$x['status']==='EXPLAINED')),
    'skipped'=>count(array_filter($details['queries'],static fn($x)=>$x['status']==='SKIPPED')),
    'errors'=>count($details['errors']),
];
$details['notes']=[
    'Only SELECT metadata + EXPLAIN; no EXPLAIN ANALYZE and no schema/data writes.',
    'A FULL_SCAN on small tables is sometimes faster than using an index.',
    'An index left-prefix candidate is not a removal instruction. Foreign keys and workload matter.',
    'Historical source equivalence was already proven; any future index changes require separate tested migration.',
    'Project/sample IDs are representative for plan shape but real cardinality can affect optimizer choices.'
];

if($json) {
    echo json_encode($details,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR)."\n";
} else {
    printf("READ-ONLY PERFORMANCE AUDIT: %s; %d index definitions, %d index column entries\n",
        $database,count($groups),count($info));
    printf("Left-prefix review candidates (NOT delete approvals): %d\n",count($details['prefix_candidates']));
    foreach($details['prefix_candidates'] as $c)
        printf("CANDIDATE %s: %s covered by %s\n",$c['table'],$c['candidate'],$c['covered_by']);
    foreach($details['queries'] as $q) {
        if($q['status']==='SKIPPED') {
            echo "SKIPPED {$q['label']}: {$q['reason']}\n";
            continue;
        }
        echo "EXPLAIN {$q['label']}\n";
        foreach($q['plan'] as $p)printf("  table=%s type=%s key=%s rows=%s extra=%s\n",
            $p['table']??'?', $p['type']??'?', $p['key']??'NULL',
            $p['rows']??'?', $p['Extra']??'');
        if($q['flags'])echo '  REVIEW: '.implode(', ',$q['flags'])."\n";
    }
    foreach($details['errors'] as $e)echo "ERROR {$e['label']}: {$e['error']}\n";
    echo 'SUMMARY '.json_encode($details['summary'])."\n";
    echo "No indexes changed. Review candidates before any DDL.\n";
}
exit(count($details['errors'])?1:0);
