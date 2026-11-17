<?php
/**
 * Disposable MySQL 8.4 CI-only TIMESTAMP timezone-semantic proof.
 * Creates only a connection-scoped TEMPORARY table on bluemarketing_v2_ci.
 * Not intended for the owner's historical local rehearsal database.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Fail closed BEFORE connecting: disposable CI schema, loopback MySQL,
// no replication hosts, DB URL, unix socket, staging or production.
$settings = config('database.connections.' . config('database.default'));
if (!is_array($settings)) {
    throw new RuntimeException('V2_MYSQL_CI_CONFIG_REQUIRED');
}
\App\Services\Finance\V2HistoricalImportTargetGuard::assertAllowed(
    $settings, (string) config('app.env'), true
);

$db = DB::connection();
if ($db->getDriverName() !== 'mysql'
    || (string) $db->getDatabaseName() !== 'bluemarketing_v2_ci'
    || config('app.env') !== 'testing'
    || !in_array((string) $db->getConfig('host'), ['127.0.0.1', 'localhost'], true)
    || (string) ($db->getConfig('url') ?? '') !== '') {
    throw new RuntimeException('DISPOSABLE_V2_CI_MYSQL_ONLY');
}

try {
    $db->statement("SET SESSION time_zone = '+00:00'");
    $db->statement('CREATE TEMPORARY TABLE v2_timestamp_semantics_probe (id INT PRIMARY KEY, event_at TIMESTAMP NOT NULL)');
    $db->table('v2_timestamp_semantics_probe')->insert([
        'id' => 1, 'event_at' => '2020-02-03 04:05:06',
    ]);
    $utc = (string) $db->table('v2_timestamp_semantics_probe')->value('event_at');
    $db->statement("SET SESSION time_zone = '+05:00'");
    $pak = (string) $db->table('v2_timestamp_semantics_probe')->value('event_at');
    $db->statement("SET SESSION time_zone = '+00:00'");
    $again = (string) $db->table('v2_timestamp_semantics_probe')->value('event_at');
    if ($utc !== '2020-02-03 04:05:06'
        || $pak !== '2020-02-03 09:05:06'
        || $again !== $utc) {
        throw new RuntimeException('V2_TIMEZONE_SEMANTIC_REGRESSION');
    }
    echo "V2_MYSQL_TIMESTAMP_SESSION_SEMANTICS_PASS: exact UTC / +05:00 roundtrip; temporary only\n";
} finally {
    $db->statement('DROP TEMPORARY TABLE IF EXISTS v2_timestamp_semantics_probe');
}
