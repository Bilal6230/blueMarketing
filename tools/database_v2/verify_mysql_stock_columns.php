<?php

/**
 * READ-ONLY stock column diagnostics; emits ONLY column names and counts.
 * Does not alter MySQL or expose individual source/customer values or IDs.
 *
 * --source-evidence must be produced by stock_column_diagnostics.py outside Git.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'V2_STOCK_COLUMN_DIAGNOSTIC_ABORTED: '
        . ($e instanceof DomainException ? $e->getMessage() : get_class($e))
        . PHP_EOL);
    exit(2);
});

function stockColumnValue(mixed $raw, string $definition): ?string
{
    if ($raw === null) {
        return null;
    }
    $text = (string) $raw;
    if (preg_match('/^(?:bigint|int|tinyint|smallint)\b/i', $definition)) {
        if (!preg_match('/^[+-]?\d+$/D', $text)) {
            throw new DomainException('UNEXPECTED_INTEGER_STORAGE_FORMAT');
        }
        $negative = str_starts_with($text, '-');
        $digits = ltrim(ltrim($text, '+-'), '0');
        $digits = $digits === '' ? '0' : $digits;
        return ($negative && $digits !== '0' ? '-' : '') . $digits;
    }
    if (preg_match('/^decimal\((\d+),\s*(\d+)\)/i', $definition, $m)) {
        $scale = (int) $m[2];
        if ($scale > 30 || !preg_match('/^[+-]?\d+(?:\.\d+)?$/D', $text)) {
            throw new DomainException('UNEXPECTED_DECIMAL_STORAGE_FORMAT');
        }
        $canonical = bcadd($text, '0', $scale);
        if (bccomp($text, $canonical, 30) !== 0) {
            throw new DomainException('DECIMAL_PRECISION_MISMATCH');
        }
        return $canonical;
    }
    if (!preg_match('/^(?:varchar|char|text|mediumtext|longtext|date|datetime|timestamp|enum)\b/i', $definition)) {
        throw new DomainException('UNEXPECTED_STOCK_COLUMN_TYPE');
    }
    return $text;
}

function stockValueHash(?string $value): string
{
    return $value === null
        ? hash('sha256', 'N')
        : hash('sha256', 'V' . strlen($value) . ':' . $value);
}

function stockColumnHash(array $hashes): string
{
    sort($hashes, SORT_STRING);
    return hash('sha256', implode("\n", $hashes));
}

$opt = getopt('', ['source-evidence:', 'selftest']);
if (array_key_exists('selftest', $opt)) {
    if (stockValueHash(null) !== hash('sha256', 'N') ||
        stockValueHash('') !== hash('sha256', 'V0:') ||
        stockColumnHash([stockValueHash('IN'), stockValueHash('OUT')]) !==
          stockColumnHash([stockValueHash('OUT'), stockValueHash('IN')]) ||
        stockColumnValue('1.2', 'decimal(18,3) NOT NULL') !== '1.200' ||
        stockColumnValue('0000', 'bigint(20) UNSIGNED NOT NULL') !== '0') {
        throw new DomainException('STOCK_CANONICAL_HASH_SELFTEST_FAILED');
    }
    echo "V2_STOCK_COLUMN_HASH_SELFTEST_PASS\n";
    exit(0);
}

$path = (string) ($opt['source-evidence'] ?? '');
$actual = is_file($path) ? realpath($path) : false;
$root = realpath(dirname(__DIR__, 2));
if (!$actual || $actual === $root ||
    str_starts_with($actual, $root . DIRECTORY_SEPARATOR)) {
    throw new DomainException('STOCK_PRIVATE_EVIDENCE_OUTSIDE_GIT_REQUIRED');
}
$evidence = json_decode((string) file_get_contents($actual), true, 512, JSON_THROW_ON_ERROR);
$expectedSourceSha = 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed';
$expectedTables = ['stock_entries', 'stock_movements', 'stock_parties'];
if (($evidence['kind'] ?? '') !== 'v2-private-stock-column-parity-v1' ||
    ($evidence['source_sha256'] ?? '') !== $expectedSourceSha ||
    ($evidence['scope'] ?? null) !== $expectedTables ||
    count($evidence['tables'] ?? []) !== 3) {
    throw new DomainException('STOCK_SOURCE_EVIDENCE_INVALID');
}

$db = DB::connection();
$dbName = (string) $db->getDatabaseName();
if ($db->getDriverName() !== 'mysql' ||
    !preg_match('/^bluemarketing_v2_local_[a-z0-9_]+$/D', $dbName) ||
    !in_array((string) ($db->getConfig('host') ?? ''), ['127.0.0.1', 'localhost'], true) ||
    (string) ($db->getConfig('url') ?? '') !== '' ||
    (string) config('app.env') !== 'local' ||
    (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1) {
    throw new DomainException('STOCK_REQUIRES_LOCAL_ISOLATED_V2_WITH_FKS');
}

$definitions = [];
for ($part = 1; $part <= 4; $part++) {
    $file = base_path('database/v2_schema/legacy_columns_'
        . str_pad((string) $part, 2, '0', STR_PAD_LEFT) . '.json');
    $meta = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (($meta['source_sha256'] ?? '') !== $expectedSourceSha) {
        throw new DomainException('STOCK_SCHEMA_CHECKSUM_MISMATCH');
    }
    foreach ($meta['tables'] as $tableMeta) {
        if (in_array($tableMeta['table'], $expectedTables, true)) {
            $definitions[$tableMeta['table']] = $tableMeta['columns'];
        }
    }
}
if (count($definitions) !== 3) {
    throw new DomainException('STOCK_SOURCE_SCHEMA_INCOMPLETE');
}
$diffs = [];
foreach ($expectedTables as $table) {
    if (!Schema::hasTable($table)) {
        throw new DomainException('STOCK_MYSQL_TABLE_MISSING');
    }
    $cols = $definitions[$table];
    $names = array_column($cols, 0);
    $proof = $evidence['tables'][$table] ?? null;
    if (!is_array($proof) || array_keys($proof['columns'] ?? []) !== $names) {
        throw new DomainException('STOCK_SOURCE_COLUMNS_CHANGED');
    }
    $columnDigests = array_fill_keys($names, []);
    $n = 0;
    foreach ($db->table($table)->select($names)->cursor() as $row) {
        $n++;
        foreach ($cols as [$column, $definition]) {
            $columnDigests[$column][] =
                stockValueHash(stockColumnValue($row->{$column}, $definition));
        }
    }
    if ($n !== (int) $proof['rows']) {
        throw new DomainException('STOCK_IMPORTED_ROW_COUNT_MISMATCH');
    }
    $bad = [];
    foreach ($names as $column) {
        $got = stockColumnHash($columnDigests[$column]);
        if (!hash_equals((string) $proof['columns'][$column], $got)) {
            $bad[] = $column;
            $diffs[] = $table . '.' . $column;
        }
    }
    echo 'STOCK_COLUMN_TABLE: ' . $table . ' rows=' . $n . ' matched='
        . (count($names) - count($bad)) . '/' . count($names) . PHP_EOL;
    foreach ($bad as $column) {
        echo 'STOCK_COLUMN_DIFF: ' . $table . '.' . $column . PHP_EOL;
    }
}
echo 'V2_STOCK_COLUMN_DIAGNOSTIC_COMPLETE: differing_columns='
    . count($diffs) . ', database_unchanged=yes' . PHP_EOL;
if ($diffs !== []) {
    exit(3);
}
echo "V2_STOCK_COLUMN_PARITY_PASS: all original stock column multisets identical\n";
