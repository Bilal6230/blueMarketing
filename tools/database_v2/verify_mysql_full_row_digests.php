<?php

/**
 * Read-only full-value MySQL V2 source parity. No data writes, no row output.
 *
 * php tools/database_v2/verify_mysql_full_row_digests.php
 *   --source-evidence=C:/PRIVATE/staging/source_full_row_digests.json
 *
 * Can also run --selftest without a database to check cross-language SHA.\n * --verify-source-utc: source export must first pass its SHA+UTC-header proof;\n * compare historical TIMESTAMP instants under a session-only +00:00 zone.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $e): void {
    // SQL query exceptions may embed customer data; never print raw messages.
    fwrite(STDERR, 'V2_FULL_ROW_PARITY_BLOCKED: '
        . ($e instanceof DomainException ? $e->getMessage() : get_class($e))
        . PHP_EOL);
    exit(2);
});

function canonicalValue(mixed $value, string $definition): ?string
{
    if ($value === null) {
        return null;
    }
    $literal = (string) $value;
    if (preg_match('/^(bigint|int|tinyint|smallint)\b/i', $definition)) {
        if (!preg_match('/^[+-]?\d+$/D', $literal)) {
            throw new DomainException('INVALID_INTEGER_STORAGE_VALUE');
        }
        $negative = str_starts_with($literal, '-');
        $digits = ltrim(ltrim($literal, '+-'), '0');
        $digits = $digits === '' ? '0' : $digits;
        return ($negative && $digits !== '0' ? '-' : '') . $digits;
    }
    if (preg_match('/^decimal\((\d+),\s*(\d+)\)/i', $definition, $m)) {
        $scale = (int) $m[2];
        if ($scale > 30 || !preg_match('/^[+-]?\d+(?:\.\d+)?$/D', $literal)) {
            throw new DomainException('INVALID_DECIMAL_STORAGE_VALUE');
        }
        $canonical = bcadd($literal, '0', $scale);
        if (bccomp($literal, $canonical, 30) !== 0) {
            throw new DomainException('DECIMAL_STORAGE_PRECISION_LOST');
        }
        return $canonical;
    }
    if (!preg_match('/^(?:varchar|char|text|mediumtext|longtext|date|datetime|timestamp|enum)\b/i', $definition)) {
        throw new DomainException('UNSUPPORTED_SOURCE_COLUMN_TYPE');
    }
    return $literal;
}

function hashRow(array $values): string
{
    $context = hash_init('sha256');
    foreach ($values as $value) {
        if ($value === null) {
            hash_update($context, 'N');
        } else {
            hash_update($context, 'V' . strlen($value) . ':' . $value);
        }
    }
    return hash_final($context);
}

function hashTable(array $rowDigests): string
{
    sort($rowDigests, SORT_STRING);
    return hash('sha256', implode("\n", $rowDigests));
}

$options = getopt('', ['source-evidence:', 'selftest', 'verify-source-utc']);
if (array_key_exists('selftest', $options)) {
    $one = hashRow(['0', 'CI_ZERO_ACCOUNT', '1']);
    $two = hashRow(['1.20', '2020-02-03 04:05:06', "a\\b", null]);
    if ($one !== '355244ae16345f19baedafd737e7d8f22f008948638756ccb7ebb538b83045a4' ||
        $two !== 'b1b806ea3b5ac7d8d0ee9248bee8e623805673e93144df16a755f02543e6530e' ||
        hashTable([]) !== hash('sha256', '') ||
        canonicalValue('0001', 'int(11) NOT NULL') !== '1' ||
        canonicalValue('1.2', 'decimal(10,2) NOT NULL') !== '1.20') {
        throw new DomainException('SOURCE_MYSQL_CANONICAL_HASH_SELFTEST_FAILED');
    }
    echo "V2_FULL_ROW_CANONICAL_SELFTEST_PASS\n";
    exit(0);
}

$path = (string) ($options['source-evidence'] ?? '');
$root = realpath(dirname(__DIR__, 2));
$real = is_file($path) ? realpath($path) : false;
if ($real === false || $real === $root ||
    str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
    throw new DomainException('PRIVATE_SOURCE_EVIDENCE_MISSING_OR_INSIDE_REPOSITORY');
}

$evidence = json_decode((string) file_get_contents($real), true, 512, JSON_THROW_ON_ERROR);
$expectedSha = 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed';
if (($evidence['kind'] ?? null) !== 'v2-full-row-semantic-v1' ||
    ($evidence['source_sha256'] ?? null) !== $expectedSha ||
    ($evidence['source_rows'] ?? null) !== 120165 ||
    ($evidence['source_tables'] ?? null) !== 57 ||
    count($evidence['tables'] ?? []) !== 57) {
    throw new DomainException('SOURCE_57_TABLE_FINGERPRINT_INVALID');
}

$connection = DB::connection();
$name = (string) $connection->getDatabaseName();
$host = (string) ($connection->getConfig('host') ?? '');
if ($connection->getDriverName() !== 'mysql' ||
    !preg_match('/^bluemarketing_v2_local_[a-z0-9_]+$/D', $name) ||
    !in_array($host, ['127.0.0.1', 'localhost'], true) ||
    (string) ($connection->getConfig('url') ?? '') !== '' ||
    (string) config('app.env') !== 'local') {
    throw new DomainException('REQUIRES_ISOLATED_LOCAL_V2_MYSQL');
}
if ((int) $connection->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled !== 1) {
    throw new DomainException('MYSQL_FOREIGN_KEY_CHECKS_DISABLED');
}

$verifyUtc = array_key_exists('verify-source-utc', $options);
if ($verifyUtc) {
    // Only this DB connection's timezone changes; stored timestamps are
    // never updated. This exposes import-time UTC-to-local misinterpretation.
    $connection->statement("SET SESSION time_zone = '+00:00'");
    $session = $connection->selectOne('SELECT @@session.time_zone AS tz');
    if (!$session || (string) $session->tz !== '+00:00') {
        throw new DomainException('V2_UTC_SESSION_PRECONDITION_FAILED');
    }
    echo "V2_SOURCE_UTC_SEMANTIC_CHECK: session=+00:00, all_57_tables; session_only=yes\n";
}

$tables = [];
for ($part = 1; $part <= 4; $part++) {
    $file = base_path('database/v2_schema/legacy_columns_'
        . str_pad((string) $part, 2, '0', STR_PAD_LEFT) . '.json');
    $meta = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (($meta['source_sha256'] ?? null) !== $expectedSha) {
        throw new DomainException('SOURCE_SCHEMA_METADATA_FINGERPRINT_MISMATCH');
    }
    foreach ($meta['tables'] as $row) {
        if (isset($tables[$row['table']])) {
            throw new DomainException('DUPLICATE_SOURCE_TABLE_SCHEMA');
        }
        $tables[$row['table']] = $row['columns'];
    }
}
if (count($tables) !== 57) {
    throw new DomainException('SOURCE_SCHEMA_TABLE_COUNT_INVALID');
}

$failures = [];
$total = 0;
foreach ($tables as $sourceTable => $columns) {
    $table = $sourceTable === 'migrations' ? 'legacy_migration_history' : $sourceTable;
    if (!Schema::hasTable($table) || !isset($evidence['tables'][$sourceTable])) {
        throw new DomainException('MISSING_SOURCE_TABLE_OR_DIGEST_EVIDENCE');
    }
    $expected = $evidence['tables'][$sourceTable];
    $names = array_column($columns, 0);
    if (count($names) !== (int) $expected['column_count']) {
        throw new DomainException('SOURCE_COLUMN_COUNT_CHANGED');
    }

    $rows = [];
    $count = 0;
    foreach ($connection->table($table)->select($names)->cursor() as $row) {
        $values = [];
        foreach ($columns as [$column, $definition]) {
            $values[] = canonicalValue($row->{$column}, $definition);
        }
        $rows[] = hashRow($values);
        $count++;
    }
    $total += $count;
    if ($count !== (int) $expected['rows'] ||
        !hash_equals((string) $expected['digest'], hashTable($rows))) {
        $failures[] = $sourceTable;
    }
}
if ($total !== 120165 || $failures !== []) {
    fwrite(STDERR,
        ($verifyUtc ? 'V2_FULL_ROW_UTC_SEMANTIC_PARITY_FAILED: '
            : 'V2_FULL_ROW_PARITY_FAILED: ')
        . count($failures) . '/57 differing tables, total rows=' . $total . PHP_EOL);
    foreach ($failures as $name) {
        fwrite(STDERR, 'TABLE_DIFF: ' . $name . PHP_EOL);
    }
    exit(3);
}

echo ($verifyUtc ? "V2_FULL_ROW_UTC_SEMANTIC_PARITY_PASS" : "V2_FULL_ROW_PARITY_PASS") . ": 57/57 tables, 120165/120165 rows, "
    . "all original columns compared, including duplicate identical rows.\n";
echo "LIMITATION: does not prove JV typed source attribution or new V2 derived-column policy.\n";
if ($verifyUtc) {
    echo "V2_UTC_NOTE: full-column parity was evaluated using source's explicit +00:00 export timezone; "
        . "no DB rows or server settings were modified.\n";
}

