<?php

/**
 * READ-ONLY private V2 imported-data reconciliation. No INSERT/UPDATE/DELETE.
 *
 * php tools/database_v2/verify_imported_mysql_financial.php
 *   --manifest=/secure/staging/manifest.json
 *   --source-evidence=/secure/source_fingerprints.json
 *   --expected-sha256=<original hash>
 *   --expected-rows=120165
 *
 * Checks 57 tables: counts, PK ID bounds, historical timestamp extrema/non-null,
 * and exact DECIMAL cash/credit/debit totals. NOT full-column row-by-row parity.
 */
declare(strict_types=1);

use App\Services\Finance\V2HistoricalImportTargetGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'V2_FINANCIAL_PARITY_ABORTED: ' . get_class($e)
        . ': ' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()) . PHP_EOL);
    exit(2);
});

$args = getopt('', ['manifest:', 'source-evidence:', 'expected-sha256:', 'expected-rows:', 'verify-source-utc', 'ci-synthetic']);
$manifestPath = $args['manifest'] ?? '';
$evidencePath = $args['source-evidence'] ?? '';
$expectedSha = strtolower((string) ($args['expected-sha256'] ?? ''));
$expectedRows = filter_var($args['expected-rows'] ?? null, FILTER_VALIDATE_INT);
if (!is_file($manifestPath) || !is_file($evidencePath) ||
    !preg_match('/^[a-f0-9]{64}$/D', $expectedSha) ||
    $expectedRows === false || $expectedRows === null || $expectedRows < 0) {
    throw new RuntimeException('Manifest/evidence/SHA256/expected rows are required');
}
if (!function_exists('bccomp')) {
    throw new RuntimeException('PHP bcmath required for exact financial totals');
}
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$source = json_decode((string) file_get_contents($evidencePath), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['source_sha256'] ?? '') !== $expectedSha ||
    ($source['source_sha256'] ?? '') !== $expectedSha ||
    ($manifest['source_table_count'] ?? null) !== 57 ||
    ($source['table_count'] ?? null) !== 57 ||
    ($source['source_rows'] ?? null) !== $expectedRows ||
    ($manifest['source_row_count'] ?? null) !== $expectedRows) {
    throw new RuntimeException('Source/manifest fingerprint and 57-table row count mismatch');
}

$verifyUtc = array_key_exists('verify-source-utc', $args);
$syntheticUtc = array_key_exists('ci-synthetic', $args);
if ($syntheticUtc && !$verifyUtc) {
    throw new DomainException('V2_SYNTHETIC_UTC_MODE_REQUIRED');
}
if ($verifyUtc) {
    // Validate a separately named local candidate before connecting to MySQL.
    $config = config('database.connections.' . config('database.default'));
    if (!is_array($config)) {
        throw new DomainException('V2_UTC_FINANCIAL_DATABASE_CONFIG_MISSING');
    }
    V2HistoricalImportTargetGuard::assertAllowed(
        $config, (string) config('app.env'), $syntheticUtc
    );
}
$db = DB::connection();
$name = (string) $db->getDatabaseName();
$environment = (string) config('app.env');
if ($db->getDriverName() !== 'mysql' ||
    !str_starts_with($name, 'bluemarketing_v2_') ||
    !in_array($environment, ['local', 'testing'], true) ||
    preg_match('/(?:prod|stag|live|client|dev)/i', $name)) {
    throw new RuntimeException('Only isolated local/testing bluemarketing_v2_* MySQL target permitted');
}
if ((int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1) {
    throw new RuntimeException('Target FK checks disabled');
}

if ($verifyUtc) {
    // TIMESTAMP extrema must be interpreted using the same session zone as
    // the pinned original SQL export and the full-row UTC digest verifier.
    // Changes THIS PHP process connection only, never GLOBAL or stored rows.
    $db->statement("SET SESSION time_zone = '+00:00'");
    $session = $db->selectOne('SELECT @@SESSION.time_zone AS tz');
    if (!$session || (string) $session->tz !== '+00:00') {
        throw new DomainException('V2_UTC_FINANCIAL_SESSION_NOT_APPLIED');
    }
    echo "V2_FINANCIAL_SOURCE_UTC_SESSION_VERIFIED: +00:00; session_only=yes\n";
}

$tableMeta = $source['tables'] ?? [];
$order = $manifest['import_order'] ?? [];
if (count($order) !== 57 || count($tableMeta) !== 57) {
    throw new RuntimeException('Expected exactly 57 V2 historical source tables');
}
$problems = [];
$passed = 0;
$numericColumns = 0;
foreach ($order as $table) {
    if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]*$/D', $table) || !Schema::hasTable($table)) {
        throw new RuntimeException('Missing/unrecognized V2 table');
    }
    $original = $table === 'legacy_migration_history' ? 'migrations' : $table;
    $evidence = $tableMeta[$original] ?? null;
    if (!is_array($evidence)) {
        throw new RuntimeException('Original source evidence absent for ' . $original);
    }
    $rowCount = (int) $db->table($table)->count();
    if ($rowCount !== (int) $evidence['rows']) {
        $problems[] = $original . ': row count mismatch';
        continue;
    }
    if (($evidence['id_non_null'] ?? null) !== null) {
        $bounds = $db->table($table)
            ->selectRaw('COUNT(`id`) AS count_id, MIN(`id`) AS minimum_id, MAX(`id`) AS maximum_id')
            ->first();
        $idNonNull = (int) $evidence['id_non_null'];
        if ((int) $bounds->count_id !== $idNonNull ||
            (string) $bounds->minimum_id !== (string) ($evidence['id_min'] ?? '') ||
            (string) $bounds->maximum_id !== (string) ($evidence['id_max'] ?? '')) {
            $problems[] = $original . ': historical ID bounds differ';
        }
    }
    foreach (($evidence['timestamps'] ?? []) as $column => $dates) {
        if (!preg_match('/^(created_at|updated_at|deleted_at)$/D', $column) ||
            !Schema::hasColumn($table, $column)) {
            throw new RuntimeException('Unrecognized historical date column ' . $table . '.' . $column);
        }
        $quote = '`' . $column . '`';
        $bounds = $db->table($table)->selectRaw(
            'COUNT(' . $quote . ') AS total_non_null, MIN(' . $quote
            . ') AS earliest, MAX(' . $quote . ') AS latest'
        )->first();
        if ((int) $bounds->total_non_null !== (int) $dates['non_null'] ||
            (string) $bounds->earliest !== (string) ($dates['min'] ?? '') ||
            (string) $bounds->latest !== (string) ($dates['max'] ?? '')) {
            $problems[] = $original . '.' . $column . ': timestamp parity mismatch';
        }
    }
    foreach (($evidence['numeric_totals'] ?? []) as $column => $totals) {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', (string) $column) ||
            !Schema::hasColumn($table, $column)) {
            throw new RuntimeException('Unrecognized amount column ' . $table . '.' . $column);
        }
        $quote = '`' . $column . '`';
        $sum = $db->table($table)
            ->selectRaw('COUNT(' . $quote . ') AS n, COALESCE(SUM(' . $quote . '), 0) AS value_sum')
            ->first();
        $numericColumns++;
        if ((int) $sum->n !== (int) $totals['non_null'] ||
            bccomp((string) $sum->value_sum, (string) $totals['sum'], 6) !== 0) {
            $problems[] = $original . '.' . $column . ': financial aggregate mismatch';
        }
    }
    $passed++;
}

if ($problems !== []) {
    fwrite(STDERR, "V2_FINANCIAL_PARITY_FAILED: " . count($problems) . " discrepancies\n");
    foreach (array_slice($problems, 0, 40) as $problem) {
        fwrite(STDERR, ' - ' . $problem . PHP_EOL);
    }
    exit(3);
}
echo ($verifyUtc ? 'V2_FINANCIAL_UTC_PARITY_PASS: ' : 'V2_FINANCIAL_PARITY_PASS: ') . $passed . '/57 historical tables, '
    . $expectedRows . ' records, exact ID/timestamp bounds and '
    . $numericColumns . " source numeric aggregate columns match.\n";
echo "LIMITATION: full-column per-row hashing and JV source attribution are separate controls.\n";
