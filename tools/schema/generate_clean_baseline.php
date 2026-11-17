<?php
declare(strict_types=1);
/**
 * Local-only schema bootstrap generator for 2026-11-17 bluenew phpMyAdmin dump.
 * CLI: php tools/schema/generate_clean_baseline.php "C:\path\bluenew (2).sql"
 * Never uploads source SQL and never modifies any database.
 */
if (PHP_SAPI !== 'cli' || $argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php tools/schema/generate_clean_baseline.php <export.sql>\n");
    exit(2);
}
$source = file_get_contents($argv[1]);
if ($source === false) {
    throw new RuntimeException('Cannot read SQL export');
}
$source = preg_replace('/^\xEF\xBB\xBF/', '', $source);
preg_match_all('/CREATE TABLE \x60([^\x60]+)\x60 \(.*?\) ENGINE=.*?;/s', $source, $createMatches, PREG_SET_ORDER);
preg_match_all('/ALTER TABLE \x60([^\x60]+)\x60\s+(.*?);/s', $source, $alterMatches, PREG_SET_ORDER);

$creates = [];
foreach ($createMatches as $m) {
    if (isset($creates[$m[1]])) {
        throw new RuntimeException('Duplicate table: '.$m[1]);
    }
    $creates[$m[1]] = $m[0];
}
if (count($creates) !== 147 || !isset($creates['migrations'], $creates['ledgers'], $creates['customer_ledger'])) {
    throw new RuntimeException('SQL snapshot differs from 147-table audited source');
}
$stages = [
    'Create tables' => [],
    'Add keys and indexes' => [],
    'Apply auto increment' => [],
    'Add foreign keys' => [],
];
foreach ($creates as $table => $sql) {
    if ($table !== 'migrations') $stages['Create tables'][] = $sql;
}
foreach ($alterMatches as $m) {
    if ($m[1] === 'migrations') continue;
    if (!isset($creates[$m[1]])) throw new RuntimeException('ALTER target not found');
    $body = ltrim($m[2]);
    if (str_starts_with($body, 'MODIFY')) $group = 'Apply auto increment';
    elseif (str_contains($body, 'ADD CONSTRAINT')) $group = 'Add foreign keys';
    elseif (str_starts_with($body, 'ADD')) $group = 'Add keys and indexes';
    else throw new RuntimeException('Unexpected ALTER syntax');
    $stages[$group][] = $m[0];
}
if (array_map('count', array_values($stages)) !== [146,146,117,110]) {
    throw new RuntimeException('Index or foreign key counts differ; manual review required');
}
$lines = [
'<?php',
'',
'use Illuminate\Database\Migrations\Migration;',
'use Illuminate\Support\Facades\DB;',
'use Illuminate\Support\Facades\Schema;',
'',
'/** Strictly disposable LOCAL bootstrap. Historical migrations are excluded. */',
'return new class extends Migration {',
'    private function safe(): void {',
'        $db = (string) DB::getDatabaseName();',
'        $host = strtolower((string) config("database.connections.".config("database.default").".host"));',
'        if (!app()->environment("local") || DB::connection()->getDriverName() !== "mysql"',
'            || !preg_match("/^bluenew_clean(?:_[a-z0-9_]+)?$/D", $db)',
'            || !in_array($host, ["localhost","127.0.0.1","::1"], true))',
'            throw new RuntimeException("Refusing nonlocal or non-bluenew_clean database");',
'    }',
'    public function up(): void {',
'        $this->safe();',
];
$n=0;
foreach ($stages as $label=>$queries) {
    $lines[] = '        // '.$label.' ('.count($queries).')';
    foreach ($queries as $sql) {
        $tag='BM_SCHEMA_'.(++$n);
        if (str_contains($sql,$tag)) throw new RuntimeException('Invalid heredoc marker');
        $lines[]="        DB::unprepared(<<<'".$tag."'";
        $lines[]=$sql;
        $lines[]=$tag;
        $lines[]=');';
    }
}
$lines[]='    }';
$lines[]='    public function down(): void {';
$lines[]='        $this->safe();';
$lines[]='        DB::statement("SET FOREIGN_KEY_CHECKS=0");';
$lines[]='        try {';
foreach (array_reverse(array_keys($creates)) as $table) {
    if ($table !== 'migrations') $lines[]='            Schema::dropIfExists('.var_export($table,true).');';
}
$lines[]='        } finally { DB::statement("SET FOREIGN_KEY_CHECKS=1"); }';
$lines[]='    }';
$lines[]='};';
$dir=dirname(__DIR__,2).'/database/migrations/clean_baseline';
if (!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('mkdir failed');
$target=$dir.'/2026_11_17_000000_create_bluenew_clean_baseline.php';
if (file_exists($target)) throw new RuntimeException('Existing baseline will not be overwritten: '.$target);
if (file_put_contents($target,implode("\n",$lines)."\n")===false) throw new RuntimeException('Write failed');
echo "Generated $target\n146 application tables; 146 key ALTERs; 117 auto-increments; 110 foreign-key ALTERs.\n";
