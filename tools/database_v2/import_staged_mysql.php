<?php

/**
 * V2 historical import executor. Never call from application routes.
 *
 * DEFAULT: strict read-only manifest preflight.
 * APPLY: --apply + --expected-sha256 + --expected-rows, only isolated MySQL
 * local/testing, no source DB connection, all 57 tables empty, FK checks ON.
 *
 * EXAMPLE (only after independent review and isolated backup):
 * php tools/database_v2/import_staged_mysql.php --dir=/secure/stage \\
 *   --expected-sha256=<SOURCE_SHA256> --expected-rows=120165 --apply
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Laravel's global error renderer can format a thrown QueryException while a
// standalone PHP script still exits zero on some environments. Never signal
// import success after ANY unhandled error, and never log sensitive SQL/values.
set_exception_handler(static function (Throwable $e): void {
    $category = $e instanceof \Illuminate\Database\QueryException
        ? 'SQL_CONSTRAINT_OR_QUERY_REJECTED'
        : 'VALIDATION_OR_APPLICATION_REJECTED';
    // All blocked() messages are authored constant validation policy labels;
    // never emit QueryException text because it can contain private SQL rows.
    $safeReason = ($e instanceof RuntimeException &&
        str_starts_with($e->getMessage(), 'V2_IMPORT_REFUSED: '))
        ? substr($e->getMessage(), strlen('V2_IMPORT_REFUSED: '))
        : '';
    fwrite(STDERR, 'V2_IMPORT_ABORTED: ' . $category
        . ($safeReason !== '' ? ' | ' . $safeReason : '') . PHP_EOL);
    exit(2);
});


function blocked(string $message): never
{
    throw new RuntimeException('V2_IMPORT_REFUSED: ' . $message);
}

/** Split SQL only on unquoted semicolons; never split inside user descriptions. */
function parseInsertStatements(string $sql, string $table): array
{
    $body = preg_replace('/\\A-- PRIVATE legacy source data\\.[^\\r\\n]*\\R/', '', $sql, 1);
    if (!is_string($body)) {
        blocked('Invalid staging header');
    }

    $statements = [];
    $statement = '';
    $quoted = false;
    $escaped = false;
    for ($i = 0, $n = strlen($body); $i < $n; ++$i) {
        $char = $body[$i];
        if ($escaped) {
            $escaped = false;
        } elseif ($quoted && $char === '\\') {
            $escaped = true;
        } elseif ($char === "'") {
            $quoted = !$quoted;
        } elseif (!$quoted && $char === ';') {
            $candidate = trim($statement);
            if ($candidate !== '') {
                if (!preg_match('/\\AINSERT INTO \`' . preg_quote($table, '/') . '\`\\s*\\([^)]*\\)\\s+VALUES\\s*\\(/is', $candidate)) {
                    blocked('Non-INSERT or mismatched SQL table ' . $table);
                }
                $statements[] = $candidate;
            }
            $statement = '';
            continue;
        }
        $statement .= $char;
    }
    if ($quoted || $escaped || trim($statement) !== '') {
        blocked('Unterminated SQL statement or string in ' . $table);
    }
    return $statements;
}

$options = getopt('', [
    'dir:', 'expected-sha256:', 'expected-rows:', 'apply', 'ci-synthetic',
    'source-evidence:', 'preserve-legacy-zero-date',
]);
$dir = isset($options['dir']) ? realpath((string) $options['dir']) : false;
$sha = strtolower((string) ($options['expected-sha256'] ?? ''));
$expectedRows = filter_var($options['expected-rows'] ?? null, FILTER_VALIDATE_INT);
if ($dir === false || !is_dir($dir) || !preg_match('/^[a-f0-9]{64}$/D', $sha) ||
    $expectedRows === false || $expectedRows === null || $expectedRows < 0) {
    blocked('Require --dir outside repository, --expected-sha256 (64 hex) and --expected-rows');
}
$root = realpath(dirname(__DIR__, 2));
if ($dir === $root || str_starts_with($dir, $root . DIRECTORY_SEPARATOR)) {
    blocked('Private staging inside repository is forbidden');
}

$synthetic = array_key_exists('ci-synthetic', $options);
$preserveZeroDate = array_key_exists('preserve-legacy-zero-date', $options);
if ($preserveZeroDate) {
    if ($synthetic) {
        blocked('Legacy zero-date override cannot be used with synthetic CI fixtures');
    }
    $evidenceFile = (string) ($options['source-evidence'] ?? '');
    if (!is_file($evidenceFile)) {
        blocked('Exact source financial evidence required for legacy zero-date preservation');
    }
    $evidence = json_decode((string) file_get_contents($evidenceFile), true, 512, JSON_THROW_ON_ERROR);
    if (($evidence['source_sha256'] ?? '') !== $sha ||
        ($evidence['mysql8_zero_date_exceptions'] ?? null) !== [
            'leads' => ['follow_up' => 1],
        ]) {
        blocked('Unexpected historic zero dates; only one source leads.follow_up exception is approved for rehearsal');
    }
}

if (!$synthetic && ($sha !== 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed' ||
    $expectedRows !== 120165)) {
    blocked('Historical source fingerprint/row count differs from approved 2026-10-30 snapshot');
}

$manifestFile = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
if (!is_file($manifestFile)) {
    blocked('Missing staging manifest');
}
$manifest = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['source_sha256'] ?? null) !== $sha ||
    ($manifest['source_table_count'] ?? null) !== 57 ||
    ($manifest['source_row_count'] ?? null) !== $expectedRows ||
    ($manifest['old_fk_count'] ?? null) !== 40 ||
    ($manifest['new_fk_candidates_applied'] ?? null) !== 46) {
    blocked('Manifest SHA, 57-table count, 40+46 FKs or source row count mismatch');
}
$meta = $manifest['tables'] ?? [];
$order = $manifest['import_order'] ?? [];
if (count($meta) !== 57 || count($order) !== 57 || count(array_unique($order)) !== 57 ||
    in_array('migrations', $order, true) || !in_array('legacy_migration_history', $order, true)) {
    blocked('Invalid import order or old framework migration-history collision');
}
$byTable = [];
foreach ($meta as $entry) {
    if (!isset($entry['target_table'], $entry['rows'], $entry['content_sha256'], $entry['insert_statements']) ||
        isset($byTable[$entry['target_table']])) {
        blocked('Duplicate/missing staging table metadata');
    }
    $byTable[$entry['target_table']] = $entry;
}
$plannedRows = 0;
$files = [];
foreach ($order as $offset => $name) {
    if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name) || !isset($byTable[$name])) {
        blocked('Unrecognized target table: ' . (string) $name);
    }
    $f = sprintf('%02d_%s.sql', $offset + 1, $name);
    $path = $dir . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path) || is_link($path)) {
        blocked('Missing or linked private staging part ' . $f);
    }
    $raw = (string) file_get_contents($path);
    $withoutComment = preg_replace('/\\A-- PRIVATE legacy source data\\.[^\\r\\n]*\\R/', '', $raw, 1);
    $expectedContentHash = (string) $byTable[$name]['content_sha256'];
    if (!hash_equals($expectedContentHash, hash('sha256', (string) $withoutComment))) {
        // Python's text-mode write_text() expands LF to CRLF on Windows.
        // The manifest hashes the ORIGINAL unmodified SQL statement text.
        // Accept Windows newline expansion ONLY when undoing it reproduces
        // the exact source fingerprint. Never silently accept arbitrary edits.
        $canonicalRaw = str_replace("\r\n", "\n", $raw);
        $canonicalBody = preg_replace(
            '/\\A-- PRIVATE legacy source data\\.[^\\r\\n]*\\R/',
            '', $canonicalRaw, 1
        );
        if (!is_string($canonicalBody) ||
            !hash_equals($expectedContentHash, hash('sha256', $canonicalBody))) {
            blocked('Staging SQL content checksum differs for ' . $name);
        }
        $raw = $canonicalRaw;
    }
    $chunks = parseInsertStatements($raw, $name);
    if (count($chunks) !== (int) $byTable[$name]['insert_statements']) {
        blocked('INSERT statement count mismatch for ' . $name);
    }
    $files[$name] = $chunks;
    $plannedRows += (int) $byTable[$name]['rows'];
}
if ($plannedRows !== $expectedRows) {
    blocked('Staging row-count sum mismatch');
}
echo 'V2_IMPORT_PREFLIGHT_OK: ' . count($order) . ' tables, ' . $plannedRows
    . ' rows, fingerprint ' . $sha . PHP_EOL;
if (!array_key_exists('apply', $options)) {
    echo "READ_ONLY_ONLY: --apply was NOT supplied; no database write performed.\n";
    exit(0);
}

if (!$synthetic && !$preserveZeroDate) {
    blocked('Source contains one zero TIMESTAMP: require explicit --preserve-legacy-zero-date and private --source-evidence; no silent repair');
}

$connection = DB::connection();
$database = (string) $connection->getDatabaseName();
$env = (string) config('app.env');
if ($connection->getDriverName() !== 'mysql' || !str_starts_with($database, 'bluemarketing_v2_') ||
    !in_array($env, ['local', 'testing'], true) ||
    preg_match('/(?:live|prod|stag|dev|client)/i', $database)) {
    blocked('Apply requires an isolated V2 MySQL target in local/testing, never old/dev/staging/live');
}
if ($synthetic && !($env === 'testing' && $database === 'bluemarketing_v2_ci')) {
    blocked('Synthetic fixture flag is ONLY available in disposable CI MySQL');
}
if ((int) $connection->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1) {
    blocked('MySQL FK checks must be enabled');
}
if ((int) $connection->table('migrations')->count() !== 3 ||
    (int) $connection->table('banks')->count() !== 27) {
    blocked('V2 migrations/27 banks not seeded to expected clean baseline');
}
foreach ($order as $name) {
    if (!Schema::hasTable($name) || (int) $connection->table($name)->count() !== 0) {
        blocked('All 57 V2 import target tables must exist AND be empty: ' . $name);
    }
}
// The source contains one zero TIMESTAMP. On a NEW isolated database only,
// permit MySQL to retain that ORIGINAL value by changing this connection's
// session SQL_MODE temporarily. Never alter GLOBAL or persistent server mode.
// The exceptional row is later isolated for owner review before cutover.
$previousSqlMode = null;
if ($preserveZeroDate) {
    $previousSqlMode = (string) $connection->selectOne('SELECT @@SESSION.sql_mode AS m')->m;
    $modes = array_filter(explode(',', $previousSqlMode), static fn ($m) =>
        !in_array($m, ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE'], true));
    $connection->statement('SET SESSION sql_mode = ?', [implode(',', $modes)]);
}
$connection->beginTransaction();
try {
    foreach ($order as $name) {
        foreach ($files[$name] as $offset => $query) {
            try {
                $connection->unprepared($query);
            } catch (\Illuminate\Database\QueryException $e) {
                // QueryException->getMessage() can contain full private SQL
                // with individual customer rows. NEVER log or expose it.
                // SQLSTATE and numeric driver error are safe diagnostics.
                $info = $e->errorInfo ?? [];
                $state = (string) ($info[0] ?? $e->getCode());
                $errno = (int) ($info[1] ?? 0);
                if (!preg_match('/^[A-Z0-9]{5}$/D', $state)) {
                    $state = 'UNKNOWN';
                }
                throw new RuntimeException(
                    'V2_IMPORT_REFUSED: SQL_INSERT_FAILED table=' . $name
                    . ' statement=' . ($offset + 1)
                    . ' sqlstate=' . $state
                    . ' mysql_errno=' . $errno, 0, $e
                );
            }
        }
        try {
            $got = (int) $connection->table($name)->count();
        } catch (\Illuminate\Database\QueryException $e) {
            throw new RuntimeException(
                'V2_IMPORT_REFUSED: SQL_COUNT_FAILED table=' . $name,
                0, $e
            );
        }
        if ($got !== (int) $byTable[$name]['rows']) {
            blocked('Imported row count mismatch in ' . $name . ': ' . $got);
        }
    }

    // Preserve original site account value while filling only the NEW field.
    $sites = $connection->table('sites')->whereNotNull('subhead_accounting_id')->get();
    foreach ($sites as $site) {
        $account = $connection->table('project_head_subheads')
            ->where('id', $site->subhead_accounting_id)
            ->where('project_id', $site->project_id)->first();
        if (!$account) {
            blocked('Site account/project ownership unresolved; roll back entire import');
        }
        $connection->table('sites')->where('id', $site->id)->update([
            'project_head_subhead_id' => $account->id,
        ]);
    }
    if ($preserveZeroDate) {
        $zeroCount = (int) $connection->table('leads')
            ->where('follow_up', '0000-00-00 00:00:00')->count();
        if ($zeroCount !== 1) {
            blocked('Original zero TIMESTAMP not preserved exactly; rollback source import');
        }
    }
    $connection->commit();
} catch (Throwable $e) {
    $connection->rollBack();
    throw $e;
} finally {
    if ($previousSqlMode !== null) {
        $connection->statement('SET SESSION sql_mode = ?', [$previousSqlMode]);
    }
}

echo 'V2_IMPORT_APPLIED_TO_ISOLATED_DB_ONLY: ' . $plannedRows . ' source rows preserved; '
    . 'site verified derived column filled. Financial source claims remain UNATTRIBUTED.\n';
