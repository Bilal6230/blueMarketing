<?php
/**
 * READ ONLY: classify original source full-row digest parity under explicit
 * +00:00 vs +05:00 MySQL SESSION timezones on the exact isolated local V2 clone.
 * Never UPDATE/DELETE/reimport, never print customer row IDs or contents.
 *
 * --source-evidence=C:/PRIVATE/staging/source_full_row_digests.json
 * --selftest (no database required)
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'V2_TIMESTAMP_ZONE_DIAGNOSTIC_REFUSED: '
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


function zoneDisposition(bool $atUtc, bool $atFive): string
{
    if ($atUtc && $atFive) {
        return 'SOURCE_MATCHES_BOTH_SESSIONS';
    }
    if ($atUtc) {
        return 'SOURCE_MATCHES_UTC_ONLY';
    }
    if ($atFive) {
        return 'SOURCE_MATCHES_PLUS05_ONLY';
    }
    return 'SOURCE_MATCHES_NEITHER_SESSION';
}

$options = getopt('', ['source-evidence:', 'selftest']);
if (array_key_exists('selftest', $options)) {
    $expected = [
        [true, true, 'SOURCE_MATCHES_BOTH_SESSIONS'],
        [true, false, 'SOURCE_MATCHES_UTC_ONLY'],
        [false, true, 'SOURCE_MATCHES_PLUS05_ONLY'],
        [false, false, 'SOURCE_MATCHES_NEITHER_SESSION'],
    ];
    foreach ($expected as [$utc, $five, $label]) {
        if (zoneDisposition($utc, $five) !== $label) {
            throw new DomainException('ZONE_CLASSIFICATION_SELFTEST_FAILED');
        }
    }
    if (canonicalValue('1.2', 'decimal(12,2) NOT NULL') !== '1.20'
        || hashTable([hashRow(['x']), hashRow(['y'])])
            !== hashTable([hashRow(['y']), hashRow(['x'])])) {
        throw new DomainException('SOURCE_ROW_CANONICAL_SELFTEST_FAILED');
    }
    echo "V2_TIMESTAMP_ZONE_DIAGNOSTIC_SELFTEST_PASS\n";
    exit(0);
}

$path = (string) ($options['source-evidence'] ?? '');
$real = is_file($path) ? realpath($path) : false;
$repoRoot = realpath(dirname(__DIR__, 2));
if ($real === false || $repoRoot === false
    || $real === $repoRoot
    || str_starts_with($real, $repoRoot . DIRECTORY_SEPARATOR)) {
    throw new DomainException('SOURCE_DIGEST_OUTSIDE_REPO_REQUIRED');
}
$sourceSha = 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed';
$evidence = json_decode((string) file_get_contents($real), true, 512, JSON_THROW_ON_ERROR);
if (($evidence['source_sha256'] ?? null) !== $sourceSha
    || ($evidence['kind'] ?? null) !== 'v2-full-row-semantic-v1'
    || ($evidence['source_tables'] ?? null) !== 57
    || ($evidence['source_rows'] ?? null) !== 120165
    || count($evidence['tables'] ?? []) !== 57) {
    throw new DomainException('SOURCE_57_TABLE_DIGEST_EVIDENCE_INVALID');
}

$settings = config('database.connections.' . config('database.default'));
if (!is_array($settings)
    || ($settings['driver'] ?? '') !== 'mysql'
    || ($settings['database'] ?? '') !== 'bluemarketing_v2_local_rehearsal'
    || !in_array(($settings['host'] ?? ''), ['127.0.0.1', 'localhost'], true)
    || ($settings['url'] ?? '') !== ''
    || !empty($settings['read']) || !empty($settings['write'])
    || config('app.env') !== 'local') {
    throw new DomainException('LOCAL_ISOLATED_V2_CONNECTION_REQUIRED');
}
$db = DB::connection();
if ($db->getDriverName() !== 'mysql'
    || (string) $db->getDatabaseName() !== 'bluemarketing_v2_local_rehearsal'
    || (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled !== 1) {
    throw new DomainException('V2_CONNECTION_OR_FK_STATE_INVALID');
}
if (!Schema::hasTable('finance_posting_source_claims')
    || (int) $db->table('finance_posting_source_claims')->count() !== 0) {
    throw new DomainException('V2_LEGACY_UNATTRIBUTED_IMPORT_REQUIRED');
}

$tables = [];
for ($part = 1; $part <= 4; $part++) {
    $schema = json_decode((string) file_get_contents(base_path(
        'database/v2_schema/legacy_columns_'
        . str_pad((string) $part, 2, '0', STR_PAD_LEFT) . '.json'
    )), true, 512, JSON_THROW_ON_ERROR);
    if (($schema['source_sha256'] ?? null) !== $sourceSha) {
        throw new DomainException('SOURCE_SCHEMA_FINGERPRINT_MISMATCH');
    }
    foreach ($schema['tables'] as $entry) {
        $table = $entry['table'];
        if (isset($tables[$table])) {
            throw new DomainException('DUPLICATE_LEGACY_TABLE');
        }
        $tables[$table] = $entry['columns'];
    }
}
if (count($tables) !== 57) {
    throw new DomainException('SOURCE_TABLE_SCHEMA_COUNT_MISMATCH');
}

// Both scans see one REPEATABLE READ snapshot; timezone alters *rendering*
// of TIMESTAMP columns on the verifier connection, never source data.
$db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->statement('SET TRANSACTION READ ONLY');
$previousTimeZone = (string) $db->selectOne('SELECT @@SESSION.time_zone AS zone')->zone;
$observed = [];
$counts = [
    'SOURCE_MATCHES_BOTH_SESSIONS' => 0,
    'SOURCE_MATCHES_UTC_ONLY' => 0,
    'SOURCE_MATCHES_PLUS05_ONLY' => 0,
    'SOURCE_MATCHES_NEITHER_SESSION' => 0,
];
$db->beginTransaction();
try {
    foreach (['+00:00', '+05:00'] as $zone) {
        $db->statement('SET SESSION time_zone = ?', [$zone]);
        $actualZone = (string) $db->selectOne('SELECT @@SESSION.time_zone AS zone')->zone;
        if ($actualZone !== $zone) {
            throw new DomainException('V2_SESSION_ZONE_COULD_NOT_BE_SET');
        }
        foreach ($tables as $sourceTable => $columns) {
            $target = $sourceTable === 'migrations' ? 'legacy_migration_history' : $sourceTable;
            $sourceProof = $evidence['tables'][$sourceTable] ?? null;
            $names = array_column($columns, 0);
            if (!Schema::hasTable($target)
                || !is_array($sourceProof)
                || (int) $sourceProof['column_count'] !== count($names)) {
                throw new DomainException('SOURCE_SCHEMA_EVIDENCE_INCOMPLETE');
            }
            $digests = [];
            foreach ($db->table($target)->select($names)->cursor() as $row) {
                $values = [];
                foreach ($columns as [$name, $definition]) {
                    $values[] = canonicalValue($row->{$name}, $definition);
                }
                $digests[] = hashRow($values);
            }
            $observed[$sourceTable][$zone] =
                count($digests) === (int) $sourceProof['rows']
                && hash_equals((string) $sourceProof['digest'], hashTable($digests));
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
} finally {
    // The previous zone is restored even on diagnostic failure.
    $db->statement('SET SESSION time_zone = ?', [$previousTimeZone]);
}
foreach ($tables as $table => $columns) {
    $classification = zoneDisposition(
        $observed[$table]['+00:00'] ?? false,
        $observed[$table]['+05:00'] ?? false
    );
    $counts[$classification]++;
    $timestampColumns = [];
    foreach ($columns as [$column, $definition]) {
        if (preg_match('/^timestamp\\b/i', $definition)) {
            $timestampColumns[] = $column;
        }
    }
    if ($classification !== 'SOURCE_MATCHES_BOTH_SESSIONS') {
        // Only field names and per-table result. No customer values / IDs.
        echo 'SOURCE_ZONE_TABLE: ' . $table . ' status=' . $classification
            . ' original_timestamp_columns=' . count($timestampColumns)
            . PHP_EOL;
    }
}
echo 'V2_TIMESTAMP_ZONE_DIAGNOSTIC_COMPLETE: '
    . json_encode($counts, JSON_THROW_ON_ERROR)
    . ', source_rows_expected=120165, database_unchanged=yes' . PHP_EOL;
if ($counts['SOURCE_MATCHES_NEITHER_SESSION'] > 0) {
    echo "V2_DIAGNOSTIC_CAUTION: some differences are not explained by either timezone; stop.\n";
    exit(3);
}
if ($counts['SOURCE_MATCHES_PLUS05_ONLY'] > 0) {
    echo "V2_DIAGNOSTIC_CAUTION: source UTC digests match +05:00 rendering only; imported UTC instants are not proven. No automatic repair.\n";
    exit(4);
}
echo "V2_TIMESTAMP_SOURCE_UTC_PARITY_PASS: all 57 original source tables match in UTC session\n";
