<?php
/**
 * READ ONLY historical timestamp and audit-history inventory. No source
 * records or timestamps printed. Pair with the 57/57 source full-row digest
 * verifier; this inventory alone cannot certify original export timezone.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, 'V2_TEMPORAL_HISTORY_AUDIT_REFUSED: ' . get_class($error) . PHP_EOL);
    exit(2);
});

$db = DB::connection();
if ($db->getDriverName() !== 'mysql'
    || $db->getDatabaseName() !== 'bluemarketing_v2_local_rehearsal'
    || !in_array((string) $db->getConfig('host'), ['127.0.0.1', 'localhost'], true)
    || (string) ($db->getConfig('url') ?? '') !== ''
    || config('app.env') !== 'local'
    || (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1) {
    throw new RuntimeException('Exact isolated local MySQL V2 with FK checks required');
}
if ((int) $db->table('finance_posting_source_claims')->count() !== 0) {
    throw new RuntimeException('Historical import audit requires no new typed source claims');
}

$expectedSha = 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed';
$meta = [];
for ($part = 1; $part <= 4; $part++) {
    $path = base_path('database/v2_schema/legacy_columns_'
        . str_pad((string) $part, 2, '0', STR_PAD_LEFT) . '.json');
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($data['source_sha256'] ?? '') !== $expectedSha) {
        throw new RuntimeException('Source schema fingerprint mismatch');
    }
    foreach ($data['tables'] as $entry) {
        if (isset($meta[$entry['table']])) {
            throw new RuntimeException('Duplicate legacy source table');
        }
        $meta[$entry['table']] = $entry['columns'];
    }
}
if (count($meta) !== 57) {
    throw new RuntimeException('Historical source schema not 57 tables');
}

$zones = $db->selectOne('SELECT @@session.time_zone AS session_zone,'
    . ' @@global.time_zone AS global_zone, @@system_time_zone AS system_zone');

$db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->beginTransaction();
try {
    $results = [
        'source_tables' => 0, 'original_temporal_columns' => 0,
        'timestamp_columns' => 0, 'datetime_columns' => 0, 'date_columns' => 0,
        'temporal_non_null_values' => 0, 'soft_deleted_rows' => 0,
        'legacy_migration_history_rows' => 0, 'typed_source_claims' => 0,
        'mysql_session_zone' => (string) $zones->session_zone,
        'mysql_global_zone' => (string) $zones->global_zone,
        'mysql_system_zone' => (string) $zones->system_zone,
    ];
    foreach ($meta as $table => $columns) {
        $target = $table === 'migrations' ? 'legacy_migration_history' : $table;
        if (!Schema::hasTable($target)) {
            throw new RuntimeException('Missing legacy source table');
        }
        $results['source_tables']++;
        foreach ($columns as [$column, $type]) {
            if (!preg_match('/^(timestamp|datetime|date)\b/i', $type, $match)) {
                continue;
            }
            if (!Schema::hasColumn($target, $column)) {
                throw new RuntimeException('Missing original temporal column');
            }
            $family = strtolower($match[1]);
            $results[$family . '_columns']++;
            $results['original_temporal_columns']++;
            $results['temporal_non_null_values'] += (int) $db->table($target)
                ->whereNotNull($column)->count();
            if ($column === 'deleted_at') {
                $results['soft_deleted_rows'] += (int) $db->table($target)
                    ->whereNotNull($column)->count();
            }
        }
    }
    $results['legacy_migration_history_rows'] = (int) $db->table('legacy_migration_history')->count();
    if ($results['source_tables'] !== 57 ||
        $results['legacy_migration_history_rows'] !== 14 ||
        $results['original_temporal_columns'] === 0) {
        throw new RuntimeException('Historical import history/temporal inventory invariant failed');
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
echo 'V2_TEMPORAL_HISTORY_READ_ONLY_PASS: ' . json_encode($results, JSON_THROW_ON_ERROR) . PHP_EOL;
echo "LIMITATION: original export TZ remains unverified without source-session evidence; full row-parity must be verified separately.\n";
