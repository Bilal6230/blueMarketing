<?php
/**
 * CI-only MySQL 8.4 two-connection row-lock contention proof.
 *
 * Uses the seeded bank ID 1 as an existing safe lock target, never creates or
 * edits any business row. Two transactions roll back and release all locks.
 * Does NOT claim full V2 posting/idempotency concurrency acceptance.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$settings = config('database.connections.' . config('database.default'));
if (!is_array($settings)) {
    throw new RuntimeException('V2_DISPOSABLE_LOCK_PROBE_CONFIG_MISSING');
}
\App\Services\Finance\V2HistoricalImportTargetGuard::assertAllowed(
    $settings, (string) config('app.env'), true
);

$db = DB::connection();
if ($db->getDriverName() !== 'mysql'
    || (string) $db->getDatabaseName() !== 'bluemarketing_v2_ci'
    || (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1
    || (int) $db->table('banks')->count() !== 27
    || ! $db->table('banks')->where('id', 1)->exists()) {
    throw new RuntimeException('V2_DISPOSABLE_LOCK_PROBE_BASELINE_REFUSED');
}

$dsn = sprintf(
    'mysql:host=127.0.0.1;port=%d;dbname=bluemarketing_v2_ci;charset=utf8mb4',
    (int) ($settings['port'] ?? 3306)
);
$second = new PDO(
    $dsn,
    (string) ($settings['username'] ?? ''),
    (string) ($settings['password'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$second->exec('SET SESSION innodb_lock_wait_timeout = 1');

$db->beginTransaction();
try {
    $first = $db->selectOne('SELECT id FROM banks WHERE id = 1 FOR UPDATE');
    if (!$first || (int) $first->id !== 1) {
        throw new RuntimeException('V2_DISPOSABLE_LOCK_PROBE_PARENT_NOT_FOUND');
    }

    $second->beginTransaction();
    try {
        try {
            $second->query('SELECT id FROM banks WHERE id = 1 FOR UPDATE');
            throw new RuntimeException('V2_DISPOSABLE_LOCK_PROBE_UNEXPECTED_CONCURRENT_ACQUISITION');
        } catch (PDOException $error) {
            // MySQL error 1205 means the second connection genuinely waited
            // on the first connection's held InnoDB row lock.
            if ((int) ($error->errorInfo[1] ?? 0) !== 1205) {
                throw new RuntimeException('V2_DISPOSABLE_LOCK_PROBE_WRONG_SQL_ERROR', 0, $error);
            }
        }
    } finally {
        if ($second->inTransaction()) {
            $second->rollBack();
        }
    }
} finally {
    if ($db->transactionLevel() > 0) {
        $db->rollBack();
    }
}

if ((int) $db->table('banks')->count() !== 27
    || ! $db->table('banks')->where('id', 1)->exists()) {
    throw new RuntimeException('V2_DISPOSABLE_LOCK_PROBE_CHANGED_SOURCE_DATA');
}
echo "V2_DISPOSABLE_MYSQL_TWO_CONNECTION_ROW_LOCK_PASS: MySQL 1205 confirmed; 27 source banks unchanged; all transactions rolled back.\n";
