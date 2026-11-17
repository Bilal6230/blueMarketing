<?php
/**
 * One-shot local Phase-5 bootstrap; never use against production.
 * Run with APP_ENV=local and explicit DB_DATABASE=bluemarketing_v2_phase5_test.
 * Refuses all nonempty targets, remote hosts, URL/replica/socket overrides.
 * Does NOT import historical records or modify .env.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connectionName = (string) config('database.default');
$config = config('database.connections.' . $connectionName);
$target = 'bluemarketing_v2_phase5_test';
if (!is_array($config)
    || $connectionName !== 'mysql'
    || ($config['driver'] ?? null) !== 'mysql'
    || config('app.env') !== 'local'
    || ($config['database'] ?? null) !== $target
    || !in_array($config['host'] ?? null, ['127.0.0.1', 'localhost'], true)
    || !empty($config['url'])
    || !empty($config['read'])
    || !empty($config['write'])
    || !empty($config['unix_socket'])
    || !in_array((int) ($config['port'] ?? 3306), [3306], true)) {
    throw new RuntimeException('PHASE5_SETUP_REFUSED_UNSAFE_CONFIG');
}
$db = DB::connection();
if ($db->getDriverName() !== 'mysql'
    || $db->getDatabaseName() !== $target
    || (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled !== 1) {
    throw new RuntimeException('PHASE5_SETUP_REFUSED_TARGET_IDENTITY');
}
$verifyOnly = in_array('--verify-existing', $argv, true);
$before = (int) $db->selectOne(
    'SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
)->n;
if (!$verifyOnly && $before !== 0) {
    throw new RuntimeException('PHASE5_SETUP_REFUSED_NONEMPTY_DATABASE: ' . $before);
}
if (!$verifyOnly) {
echo 'PHASE5_TARGET_EMPTY_VERIFIED: ' . $target . PHP_EOL;
$code = Artisan::call('migrate', [
    '--path' => 'database/v2_migrations',
    '--force' => true,
    '--no-interaction' => true,
]);
echo Artisan::output();
if ($code !== 0) {
    throw new RuntimeException('PHASE5_V2_MIGRATIONS_FAILED: ' . $code);
}
}
$after = (int) $db->selectOne(
    "SELECT COUNT(*) AS n FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'"
)->n;
$history = (int) $db->table('migrations')->count();
// The canonical V2 baseline is 72 domain/finance tables PLUS Laravel's
// migrations history table = 73 physical tables in a freshly migrated DB.
if ($after !== 73 || $history !== 5 || ($verifyOnly && $before !== 73)) {
    throw new RuntimeException("PHASE5_SCHEMA_CHECK_FAILED: {$after} tables / {$history} migrations");
}
echo "PHASE5_V2_SCHEMA_PASS: {$after} physical tables (72 V2 + migrations) / {$history} migrations; no historical source import performed by this script." . PHP_EOL;

// Read-only acceptance of physical source and posting integrity on this
// exact disposable local target. Never claim business workflow parity from
// an empty schema.
$required = [
    'journal_vouchers', 'journal_voucher_details', 'commision_vouchers',
    'commision_voucher_details', 'customer_ledger', 'booking_vouchers',
    'stock_entries', 'stock_bills', 'labour_ledgers',
    'finance_posting_batches', 'finance_posting_lines',
    'finance_posting_source_claims', 'finance_posting_commission_sources',
    'finance_commission_line_sources', 'finance_stock_entry_project_approvals',
    'finance_stock_bill_project_approvals',
];
$missing = [];
foreach ($required as $table) {
    if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
        $missing[] = $table;
    }
}
if ($missing !== []) {
    throw new RuntimeException('PHASE5_MISSING_WORKFLOW_TABLES: ' . implode(',', $missing));
}
$fk = (int) $db->selectOne(
    'SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE '
    . 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
)->n;
if ($fk < 124) {
    throw new RuntimeException('PHASE5_FOREIGN_KEY_COVERAGE_FAILED: ' . $fk);
}
echo 'PHASE5_WORKFLOW_SCHEMA_CONTRACT_PASS: ' . count($required)
    . ' core source/posting tables and ' . $fk
    . ' physical FK columns; business workflow tests NOT yet accepted.' . PHP_EOL;

