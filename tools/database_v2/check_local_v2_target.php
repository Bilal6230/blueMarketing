<?php

/**
 * READ-ONLY local target identity preflight. Prevent accidentally running
 * isolated historical import against a live/old database or non-local host.
 *
 * php tools/database_v2/check_local_v2_target.php
 *     --expected-name=bluemarketing_v2_local_rehearsal --require-empty
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$opts = getopt('', ['expected-name:', 'require-empty']);
$expected = (string) ($opts['expected-name'] ?? '');
$db = DB::connection();
$name = (string) $db->getDatabaseName();
$host = (string) ($db->getConfig('host') ?? '');
$url = (string) ($db->getConfig('url') ?? '');
$environment = (string) config('app.env');

if (!preg_match('/^bluemarketing_v2_local_[a-z0-9_]+$/D', $expected) ||
    $db->getDriverName() !== 'mysql' || $name !== $expected ||
    !in_array($host, ['127.0.0.1', 'localhost'], true) ||
    $url !== '' || $environment !== 'local') {
    fwrite(STDERR, "V2_TARGET_REFUSED: require exact local-only V2 DB/host/environment and no DB_URL\n");
    exit(2);
}

$count = (int) $db->selectOne(
    'SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()'
)->n;
if (array_key_exists('require-empty', $opts) && $count !== 0) {
    fwrite(STDERR, 'V2_TARGET_REFUSED: existing tables found (' . $count . '); never overwrite' . PHP_EOL);
    exit(2);
}

$version = (string) $db->selectOne('SELECT VERSION() AS v')->v;
echo json_encode([
    'status' => 'SAFE_LOCAL_V2_TARGET_VERIFIED',
    'database' => $name, 'server' => $host,
    'engine_version' => $version, 'tables' => $count,
    'require_empty' => array_key_exists('require-empty', $opts),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
