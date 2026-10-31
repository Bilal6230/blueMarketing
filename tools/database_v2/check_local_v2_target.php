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

$opts = getopt('', ['expected-name:', 'require-empty', 'require-prepared', 'manifest:']);
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
if (array_key_exists('require-prepared', $opts)) {
    if (array_key_exists('require-empty', $opts)) {
        throw new RuntimeException('Prepared and empty checks cannot both be requested');
    }
    $manifest = (string) ($opts['manifest'] ?? '');
    if (!is_file($manifest) || $count !== 72) {
        throw new RuntimeException('V2_PREPARED_REFUSED: expected 72 schema tables and private staging manifest');
    }
    $plan = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
    if (($plan['source_sha256'] ?? '') !==
            'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed' ||
        ($plan['source_table_count'] ?? null) !== 57 ||
        ($plan['source_row_count'] ?? null) !== 120165 ||
        ($plan['old_fk_count'] ?? null) !== 40 ||
        ($plan['new_fk_candidates_applied'] ?? null) !== 46) {
        throw new RuntimeException('V2_PREPARED_REFUSED: staging source fingerprint mismatch');
    }
    if ((int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1 ||
        (int) $db->table('migrations')->count() !== 5 ||
        (int) $db->table('banks')->count() !== 27) {
        throw new RuntimeException('V2_PREPARED_REFUSED: framework migration/bank FK state incorrect');
    }
    $order = $plan['import_order'] ?? [];
    if (count($order) !== 57 || count(array_unique($order)) !== 57 ||
        !in_array('legacy_migration_history', $order, true) ||
        in_array('migrations', $order, true)) {
        throw new RuntimeException('V2_PREPARED_REFUSED: invalid 57-table import order');
    }
    foreach ($order as $table) {
        if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]*$/D', $table) ||
            !\Illuminate\Support\Facades\Schema::hasTable($table) ||
            (int) $db->table($table)->count() !== 0) {
            throw new RuntimeException('V2_PREPARED_REFUSED: missing or nonempty source target');
        }
    }
    foreach ([
        'finance_posting_batches', 'finance_posting_lines',
        'finance_posting_source_claims', 'finance_journal_line_sources',
        'finance_posting_journal_sources', 'finance_posting_commission_sources',
        'finance_posting_stock_entry_sources', 'finance_posting_stock_bill_sources',
        'finance_posting_cash_sources', 'finance_posting_booking_sources',
        'finance_posting_labour_sources',
        'finance_commission_line_sources',
        'finance_stock_entry_project_approvals',
        'finance_stock_bill_project_approvals',
    ] as $table) {
        if (!\Illuminate\Support\Facades\Schema::hasTable($table) ||
            (int) $db->table($table)->count() !== 0) {
            throw new RuntimeException('V2_PREPARED_REFUSED: unexpected posted-finance rows');
        }
    }
    $fk = (int) $db->selectOne(
        "SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
    )->n;
    if ($fk < 124) {
        throw new RuntimeException('V2_PREPARED_REFUSED: physical FK graph incomplete');
    }
    echo "SAFE_LOCAL_V2_PREPARED_TARGET_VERIFIED: 72 tables, 5 migrations, 27 bank IDs, "
        . "57 empty historic target tables, 14 empty finance posting/approval tables, {$fk} FK columns." . PHP_EOL;
}

$version = (string) $db->selectOne('SELECT VERSION() AS v')->v;
echo json_encode([
    'status' => 'SAFE_LOCAL_V2_TARGET_VERIFIED',
    'database' => $name, 'server' => $host,
    'engine_version' => $version, 'tables' => $count,
    'require_empty' => array_key_exists('require-empty', $opts),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
