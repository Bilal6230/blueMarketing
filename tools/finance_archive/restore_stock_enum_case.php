<?php
/**
 * Local V2-only, opt-in restoration of original ENUM labels.
 * Default is READ-ONLY; --apply performs ALTER TABLE (not transactional).
 * A private local backup is mandatory before an operator permits --apply.
 * No individual row updates, no source/cust data printed.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $e): void {
    $error = $e instanceof DomainException ? $e->getMessage() : get_class($e);
    fwrite(STDERR, 'V2_ENUM_CASE_REFUSED: ' . $error . PHP_EOL);
    exit(2);
});

// Hard-coded original V1 SQL enum labels, source SHA validated separately.
// The ordinal positions MUST remain the same during ALTER.
$spec = [
    'stock_entries' => [
        'column' => 'flow', 'rows' => 1, 'max' => 2,
        'old' => "enum('in','out')", 'new' => "enum('IN','OUT')",
        'alter' => "ALTER TABLE `stock_entries` MODIFY COLUMN `flow` ENUM('IN','OUT') NOT NULL",
    ],
    'stock_movements' => [
        'column' => 'direction', 'rows' => 2, 'max' => 2,
        'old' => "enum('in','out')", 'new' => "enum('IN','OUT')",
        'alter' => "ALTER TABLE `stock_movements` MODIFY COLUMN `direction` "
            . "ENUM('IN','OUT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL",
    ],
    'stock_parties' => [
        'column' => 'type', 'rows' => 3, 'max' => 3,
        'old' => "enum('supplier','consumer','site')",
        'new' => "enum('Supplier','Consumer','Site')",
        'alter' => "ALTER TABLE `stock_parties` MODIFY COLUMN `type` "
            . "ENUM('Supplier','Consumer','Site') NOT NULL",
    ],
];

function ordinalCounts(string $table, string $column): array
{
    // Identifiers are trusted literal fields from the private $spec above.
    $expression = chr(96) . $column . chr(96) . ' + 0';
    $rows = DB::table($table)->selectRaw($expression . ' AS enum_number, COUNT(*) AS n')
        ->groupByRaw($expression)->get();
    $result = [];
    foreach ($rows as $row) {
        $result[(int) $row->enum_number] = (int) $row->n;
    }
    ksort($result, SORT_NUMERIC);
    return $result;
}

$args = getopt('', ['source-evidence:', 'apply', 'confirm-database:']);
$evidencePath = (string) ($args['source-evidence'] ?? '');
$evidenceFile = is_file($evidencePath) ? realpath($evidencePath) : false;
$root = realpath(dirname(__DIR__, 2));
if (!$evidenceFile || $evidenceFile === $root ||
    str_starts_with($evidenceFile, $root . DIRECTORY_SEPARATOR)) {
    throw new DomainException('PRIVATE_SOURCE_EVIDENCE_OUTSIDE_REPOSITORY_REQUIRED');
}
$source = json_decode((string) file_get_contents($evidenceFile), true, 512, JSON_THROW_ON_ERROR);
$expectedSha = 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed';
if (($source['kind'] ?? '') !== 'v2-full-row-semantic-v1' ||
    ($source['source_sha256'] ?? '') !== $expectedSha ||
    ($source['source_tables'] ?? null) !== 57 ||
    ($source['source_rows'] ?? null) !== 120165 ||
    count($source['tables'] ?? []) !== 57) {
    throw new DomainException('SOURCE_57_TABLE_DIGEST_EVIDENCE_INVALID');
}

$db = DB::connection();
$name = (string) $db->getDatabaseName();
if ($db->getDriverName() !== 'mysql' ||
    $name !== 'bluemarketing_v2_local_rehearsal' ||
    !in_array((string) ($db->getConfig('host') ?? ''), ['127.0.0.1', 'localhost'], true) ||
    (string) ($db->getConfig('url') ?? '') !== '' ||
    (string) config('app.env') !== 'local' ||
    (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1) {
    throw new DomainException('REQUIRES_EXACT_LOCAL_REHEARSAL_MYSQL_WITH_FKS');
}
$apply = array_key_exists('apply', $args);
if ($apply && (string) ($args['confirm-database'] ?? '') !== $name) {
    throw new DomainException('APPLY_REQUIRES_EXACT_CONFIRMED_DATABASE');
}
if ((int) $db->table('migrations')->count() !== 3 ||
    (int) $db->table('banks')->count() !== 27 ||
    (int) $db->table('finance_posting_batches')->count() !== 0 ||
    (int) $db->table('finance_posting_lines')->count() !== 0) {
    throw new DomainException('UNEXPECTED_V2_MIGRATIONS_BANKS_OR_POSTING_ROWS');
}

$before = [];
$pending = [];
foreach ($spec as $table => $rule) {
    if (!Schema::hasTable($table) ||
        (int) $db->table($table)->count() !== $rule['rows'] ||
        (int) ($source['tables'][$table]['rows'] ?? -1) !== $rule['rows']) {
        throw new DomainException('STOCK_TABLE_ROW_COUNT_OR_SCHEMA_MISMATCH_' . $table);
    }
    $actual = $db->selectOne(
        "SELECT COLUMN_TYPE AS type_name, IS_NULLABLE AS nullable
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?",
        [$table, $rule['column']]
    );
    if (!$actual || $actual->nullable !== 'NO' ||
        !in_array((string) $actual->type_name, [$rule['old'], $rule['new']], true)) {
        throw new DomainException('UNRECOGNIZED_STOCK_ENUM_DEFINITION_' . $table);
    }
    $counts = ordinalCounts($table, $rule['column']);
    if (array_sum($counts) !== $rule['rows']) {
        throw new DomainException('STOCK_ENUM_ROW_COUNT_MISMATCH_' . $table);
    }
    foreach (array_keys($counts) as $index) {
        if ($index < 1 || $index > $rule['max']) {
            throw new DomainException('INVALID_ENUM_INDEX_IN_' . $table);
        }
    }
    $before[$table] = $counts;
    if ((string) $actual->type_name === $rule['old']) {
        $pending[$table] = $rule;
        echo 'V2_ENUM_CASE_REPAIR_NEEDED: ' . $table . '.' . $rule['column'] . PHP_EOL;
    } else {
        echo 'V2_ENUM_CASE_ALREADY_CORRECT: ' . $table . '.' . $rule['column'] . PHP_EOL;
    }
}

if (!$apply) {
    echo 'V2_ENUM_READ_ONLY_PREFLIGHT_PASS: ' . count($pending)
        . "/3 case-only definitions require restoration. No database writes.\n";
    exit(0);
}

// MySQL DDL commits implicitly; if a statement fails, STOP, do not retry.
// Each allowed ALTER changes only the three source ENUM label spellings.
foreach ($pending as $table => $rule) {
    $db->statement($rule['alter']);
    $column = $db->selectOne(
        "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?",
        [$table, $rule['column']]
    );
    if (!$column || (string) $column->t !== $rule['new'] ||
        ordinalCounts($table, $rule['column']) !== $before[$table] ||
        (int) $db->table($table)->count() !== $rule['rows']) {
        throw new DomainException('POST_REPAIR_ENUM_ORDINAL_OR_SCHEMA_MISMATCH_' . $table);
    }
    echo 'V2_ENUM_CASE_SCHEMA_RESTORED: ' . $table . '.' . $rule['column'] . PHP_EOL;
}
echo "V2_ENUM_CASE_REPAIR_PASS: 3 source label definitions / enum ordinals preserved.\n";
echo "NEXT: independent read-only full-column 57-table SHA verifier.\n";
