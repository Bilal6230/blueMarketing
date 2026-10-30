<?php

/**
 * V2 CI ONLY. Read-only metadata checks + an intentionally rejected FK insert
 * inside a rollback-protected transaction. Never runs against live/staging.
 *
 * Usage: php tools/database_v2/verify_mysql_schema.php
 */
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$db = DB::connection();
if ($db->getDriverName() !== 'mysql' ||
    (string) $db->getDatabaseName() !== 'bluemarketing_v2_ci' ||
    getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('REFUSED: schema validator only accepts disposable bluemarketing_v2_ci MySQL testing.');
}

$tables = (int) $db->selectOne(
    "SELECT COUNT(*) AS n FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'"
)->n;

$foreign = (int) $db->selectOne(
    "SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
)->n;

if ($tables !== 69 || $foreign < 108) {
    throw new RuntimeException("Unexpected V2 topology: {$tables} tables / {$foreign} FK columns");
}

foreach ([
    ['ledgers', 'project_head_subheads_id', 'project_head_subheads'],
    ['journal_voucher_details', 'account_id', 'project_head_subheads'],
    ['sites', 'subhead_accounting_id', 'project_head_subheads'],
    ['sites', 'project_head_subhead_id', 'project_head_subheads'],
    ['bookings', 'broker_id', 'subhead_accountings'],
    ['booking_vouchers', 'bank_id', 'banks'],
    ['customer_ledger', 'bank_id', 'banks'],
] as [$table, $column, $target]) {
    $got = $db->selectOne(
        "SELECT REFERENCED_TABLE_NAME AS parent FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND
               REFERENCED_TABLE_NAME IS NOT NULL",
        [$table, $column]
    );
    if (!$got || $got->parent !== $target) {
        throw new RuntimeException("Invalid V2 FK: {$table}.{$column} -> {$target}");
    }
}

$childType = $db->selectOne(
    "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledgers' AND COLUMN_NAME='project_head_subheads_id'"
)->t;
$parentType = $db->selectOne(
    "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project_head_subheads' AND COLUMN_NAME='id'"
)->t;
if (strtolower($childType) !== strtolower($parentType) ||
    !preg_match('/^bigint(?:\\(\\d+\\))? unsigned$/i', $childType)) {
    throw new RuntimeException("Ledger-account PK/FK width mismatch: {$childType} / {$parentType}");
}

foreach ([
    'banks', 'ledgers', 'journal_voucher_details', 'legacy_migration_history',
    'finance_posting_batches', 'finance_posting_lines',
    'finance_posting_journal_sources', 'finance_posting_stock_entry_sources',
] as $table) {
    if (!Schema::hasTable($table)) {
        throw new RuntimeException('V2 table absent: ' . $table);
    }
}

if ((int) DB::table('migrations')->count() !== 3 ||
    (int) DB::table('legacy_migration_history')->count() !== 0) {
    throw new RuntimeException('V2 and V1 Laravel migration-history separation failed.');
}

if ((int) DB::table('banks')->count() !== 27 ||
    (string) DB::table('banks')->where('id', 27)->value('display_name') !== 'Bank Al-Habib') {
    throw new RuntimeException('Source bank ID mapping not populated correctly.');
}

// Rejected FK insert must cause NO persistent ledger changes.
$original = (int) DB::table('ledgers')->count();
DB::beginTransaction();
try {
    DB::table('ledgers')->insert([
        'project_head_subheads_id' => 999999999,
        'amount_in' => '0.00', 'amount_out' => '1.00', 'date' => '2026-10-30',
    ]);
    throw new RuntimeException('Invalid account FK unexpectedly accepted by MySQL.');
} catch (\Illuminate\Database\QueryException $e) {
    // The engine rejected a non-existent account source.
} finally {
    DB::rollBack();
}
if ((int) DB::table('ledgers')->count() !== $original) {
    throw new RuntimeException('Foreign-key test left persistent ledger changes.');
}

echo "SUCCESS: disposable V2 MySQL 8.4; {$tables} tables; {$foreign} FKs; "
    . "correct broker/bank/account identity; 27 seeded banks; "
    . "legacy migration-history isolated; rejected invalid ledger write; rollback intact.\n";
