<?php

declare(strict_types=1);

/**
 * Read-only DS-10 historical-table reconciliation between a restored source
 * baseline and its migrated disposable clone. Prints table names/counts only.
 *
 * Required process environment:
 *   DS10_BASELINE_DATABASE=codex_ds10_baseline_YYYYMMDD
 *   DS10_POST_DATABASE=codex_ds_rehearsal_YYYYMMDD
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function ds10Fail(string $code): never
{
    fwrite(STDERR, "DS10_RECONCILIATION_BLOCKED: {$code}\n");
    exit(2);
}

$baselineName = (string) getenv('DS10_BASELINE_DATABASE');
$postName = (string) getenv('DS10_POST_DATABASE');
if (!preg_match('/^codex_ds10_baseline_[0-9]{8}$/D', $baselineName) ||
    !preg_match('/^codex_ds_rehearsal_[0-9]{8}$/D', $postName) || $baselineName === $postName) {
    ds10Fail('EXPLICIT_DISPOSABLE_DATABASE_NAMES_REQUIRED');
}

$baseConfig = config('database.connections.mysql');
if (!is_array($baseConfig) || !in_array($baseConfig['host'] ?? null, ['127.0.0.1', 'localhost'], true) ||
    isset($baseConfig['url']) && $baseConfig['url'] !== null && $baseConfig['url'] !== '') {
    ds10Fail('LOCAL_MYSQL_CONNECTION_REQUIRED');
}

foreach ([['ds10_baseline', $baselineName], ['ds10_post', $postName]] as [$connectionName, $databaseName]) {
    $connectionConfig = $baseConfig;
    $connectionConfig['database'] = $databaseName;
    config(["database.connections.{$connectionName}" => $connectionConfig]);
    DB::purge($connectionName);
}

$baseline = DB::connection('ds10_baseline');
$post = DB::connection('ds10_post');
if ($baseline->getDriverName() !== 'mysql' || $post->getDriverName() !== 'mysql' ||
    $baseline->getDatabaseName() !== $baselineName || $post->getDatabaseName() !== $postName) {
    ds10Fail('DATABASE_IDENTITY_MISMATCH');
}

$baselineTables = $baseline->select('SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = ?', [$baselineName, 'BASE TABLE']);
$postTables = collect($post->select('SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = ?', [$postName, 'BASE TABLE']))
    ->mapWithKeys(fn (object $row) => [$row->table_name => true]);
$tableNames = collect($baselineTables)->pluck('table_name')->sort()->values();
if ($tableNames->isEmpty()) {
    ds10Fail('BASELINE_HAS_NO_TABLES');
}

$missingTables = [];
$missingColumns = [];
$countMismatch = [];
$digestMismatch = [];
$compared = 0;
$comparedRows = 0;
$excluded = ['migrations'];
$payrollPermissionNames = ['read payroll', 'create payroll', 'approve payroll'];

foreach ($tableNames as $table) {
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
        ds10Fail('UNSAFE_BASELINE_IDENTIFIER');
    }
    if (in_array($table, $excluded, true)) {
        continue;
    }
    if (!$postTables->has($table)) {
        $missingTables[] = $table;
        continue;
    }

    $baseColumns = collect($baseline->select("SHOW COLUMNS FROM `{$table}`"))->pluck('Field')->values();
    $postColumns = collect($post->select("SHOW COLUMNS FROM `{$table}`"))->pluck('Field')->all();
    $commonColumns = $baseColumns->filter(fn (string $column) => in_array($column, $postColumns, true))->values();
    $absentColumns = $baseColumns->diff($commonColumns)->all();
    if ($absentColumns !== []) {
        $missingColumns[$table] = $absentColumns;
        continue;
    }

    $digestColumns = $commonColumns->all();
    if ($table === 'labours' && in_array('project_id', $digestColumns, true)) {
        // The disposable clone received a reviewed, evidence-based repair for
        // 151 labour masters. Reconcile every other original labour field and
        // separately validate the project_id transitions below.
        $digestColumns = array_values(array_diff($digestColumns, ['project_id']));
    }
    $baseQuery = $baseline->table($table);
    $postQuery = $post->table($table);
    if ($table === 'permissions' && in_array('name', $commonColumns->all(), true)) {
        $baseQuery->whereNotIn('name', $payrollPermissionNames);
        $postQuery->whereNotIn('name', $payrollPermissionNames);
    } elseif ($table === 'role_has_permissions' && in_array('permission_id', $commonColumns->all(), true)) {
        $baseQuery->whereNotIn('permission_id', $baseline->table('permissions')->select('id')->whereIn('name', $payrollPermissionNames));
        $postQuery->whereNotIn('permission_id', $post->table('permissions')->select('id')->whereIn('name', $payrollPermissionNames));
    }
    $baseDigests = [];
    foreach ($baseQuery->select($digestColumns)->cursor() as $row) {
        $baseDigests[] = ds10RowDigest($row, $digestColumns);
    }
    $postDigests = [];
    foreach ($postQuery->select($digestColumns)->cursor() as $row) {
        $postDigests[] = ds10RowDigest($row, $digestColumns);
    }
    $baseCount = count($baseDigests);
    $postCount = count($postDigests);
    $compared++;
    $comparedRows += $baseCount;
    if ($baseCount !== $postCount) {
        $countMismatch[$table] = [$baseCount, $postCount];
    }
    sort($baseDigests, SORT_STRING);
    sort($postDigests, SORT_STRING);
    if (!hash_equals(hash('sha256', implode("\n", $baseDigests)), hash('sha256', implode("\n", $postDigests)))) {
        $digestMismatch[] = $table;
    }
}

$labourRepair = ['baseline_unscoped' => 0, 'post_unscoped' => 0, 'mapped' => 0, 'invalid_project_links' => 0, 'unexpected_transitions' => 0];
if (in_array('labours', $tableNames->all(), true) && $postTables->has('labours')) {
    $labourRepair['baseline_unscoped'] = (int) $baseline->table('labours')->whereNull('project_id')->count();
    $labourRepair['post_unscoped'] = (int) $post->table('labours')->whereNull('project_id')->count();
    $labourRepair['mapped'] = (int) $baseline->table('labours as before')->join($postName . '.labours as after', 'after.id', '=', 'before.id')
        ->whereNull('before.project_id')->whereNotNull('after.project_id')->count();
    $labourRepair['unexpected_transitions'] = (int) $baseline->table('labours as before')->join($postName . '.labours as after', 'after.id', '=', 'before.id')
        ->whereRaw('NOT (`before`.`project_id` <=> `after`.`project_id`)')
        ->where(function ($query): void {
            $query->whereNotNull('before.project_id')->orWhereNull('after.project_id');
        })->count();
    $labourRepair['invalid_project_links'] = (int) $post->table('labours as labour')
        ->leftJoin('projects', 'projects.id', '=', 'labour.project_id')->whereNotNull('labour.project_id')
        ->whereNull('projects.id')->count();
    if ($labourRepair['baseline_unscoped'] !== 165 || $labourRepair['post_unscoped'] !== 14 ||
        $labourRepair['mapped'] !== 151 || $labourRepair['unexpected_transitions'] !== 0 ||
        $labourRepair['invalid_project_links'] !== 0) {
        $digestMismatch[] = 'labours.project_id_repair';
    }
}

$payrollPermissionsPresent = (int) $post->table('permissions')->whereIn('name', $payrollPermissionNames)
    ->where('guard_name', 'web')->distinct()->count('name');
$payrollRoleLinksPresent = (int) $post->table('role_has_permissions as rp')
    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')->join('roles as r', 'r.id', '=', 'rp.role_id')
    ->whereIn('p.name', $payrollPermissionNames)->where('p.guard_name', 'web')
    ->where('r.name', 'superadmin')->where('r.guard_name', 'web')->distinct()->count('p.name');
if ($payrollPermissionsPresent !== 3 || $payrollRoleLinksPresent !== 3) {
    $digestMismatch[] = 'payroll_permissions';
}

echo 'DS10_HISTORICAL_TABLES=' . $compared . "\n";
echo 'DS10_BASELINE_ROWS=' . $comparedRows . "\n";
echo 'DS10_EXCLUDED_BOOKKEEPING=' . implode(',', $excluded) . "\n";
echo 'DS10_MISSING_TABLES=' . implode(',', $missingTables) . "\n";
echo 'DS10_MISSING_COLUMNS=' . implode(',', array_keys($missingColumns)) . "\n";
echo 'DS10_ROW_COUNT_MISMATCH=' . implode(',', array_keys($countMismatch)) . "\n";
echo 'DS10_ROW_CONTENT_MISMATCH=' . implode(',', $digestMismatch) . "\n";
echo 'DS10_REVIEWED_LABOUR_MAPPING=' . $labourRepair['mapped'] . " mapped; " . $labourRepair['post_unscoped'] . " remain unscoped; " . $labourRepair['invalid_project_links'] . " invalid project links\n";
echo 'DS10_EXPECTED_PAYROLL_PERMISSIONS=' . $payrollPermissionsPresent . "/3 permissions; " . $payrollRoleLinksPresent . "/3 superadmin links\n";

if ($missingTables !== [] || $missingColumns !== [] || $countMismatch !== [] || $digestMismatch !== []) {
    exit(1);
}
echo "DS10_HISTORICAL_RECONCILIATION_PASS\n";

function ds10RowDigest(object $row, array $columns): string
{
    $hash = hash_init('sha256');
    foreach ($columns as $column) {
        $value = $row->{$column};
        if ($value === null) {
            hash_update($hash, 'N');
            continue;
        }
        $literal = (string) $value;
        hash_update($hash, 'V' . strlen($literal) . ':' . $literal);
    }
    return hash_final($hash);
}
