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

if ($tables !== 70 || $foreign < 116) {
    throw new RuntimeException("Unexpected V2 topology: {$tables} tables / {$foreign} FK columns");
}

// Prove that MySQL 8.4 can change the old case-only ENUM labels to
// the source's original label case WITHOUT changing stored ordinals.
// This is a disposable TEMPORARY table, not a historical data migration.
$db->statement(
    "CREATE TEMPORARY TABLE v2_enum_case_probe (
      flow ENUM('in','out') NOT NULL,
      direction ENUM('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
      party_type ENUM('supplier','consumer','site') NOT NULL)"
);
try {
    $db->table('v2_enum_case_probe')->insert([
        ['flow' => 'in', 'direction' => 'out', 'party_type' => 'supplier'],
        ['flow' => 'out', 'direction' => 'in', 'party_type' => 'site'],
    ]);
    $ordinalBefore = $db->table('v2_enum_case_probe')
        ->selectRaw('flow + 0 AS a, direction + 0 AS b, party_type + 0 AS c')
        ->orderByRaw('flow + 0')->get()->toArray();
    $db->statement("ALTER TABLE v2_enum_case_probe
      MODIFY COLUMN flow ENUM('IN','OUT') NOT NULL,
      MODIFY COLUMN direction ENUM('IN','OUT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
      MODIFY COLUMN party_type ENUM('Supplier','Consumer','Site') NOT NULL");
    $ordinalAfter = $db->table('v2_enum_case_probe')
        ->selectRaw('flow + 0 AS a, direction + 0 AS b, party_type + 0 AS c')
        ->orderByRaw('flow + 0')->get()->toArray();
    if ($ordinalBefore !== $ordinalAfter) {
        throw new RuntimeException('ENUM case restoration altered ordinal identities.');
    }
    $fixed = $db->table('v2_enum_case_probe')->orderByRaw('flow + 0')->get();
    if ($fixed[0]->flow !== 'IN' || $fixed[0]->direction !== 'OUT' ||
        $fixed[0]->party_type !== 'Supplier' || $fixed[1]->flow !== 'OUT' ||
        $fixed[1]->direction !== 'IN' || $fixed[1]->party_type !== 'Site') {
        throw new RuntimeException('ENUM case restoration did not preserve original labels.');
    }
} finally {
    $db->statement('DROP TEMPORARY TABLE IF EXISTS v2_enum_case_probe');
}

// Source ENUM labels are CASE-SENSITIVE business values. MariaDB V1 had
// uppercase IN/OUT and mixed-case Supplier/Consumer/Site; never lowercase
// the quoted enum type arguments when rebuilding the V2 schema.
foreach ([
    'stock_entries' => ['flow' => "enum('IN','OUT')"],
    'stock_movements' => ['direction' => "enum('IN','OUT')"],
    'stock_parties' => ['type' => "enum('Supplier','Consumer','Site')"],
] as $table => $columns) {
    foreach ($columns as $column => $originalEnum) {
        $actual = $db->selectOne(
            "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?",
            [$table, $column]
        );
        if (!$actual || (string) $actual->t !== $originalEnum) {
            throw new RuntimeException("SOURCE_ENUM_CASE_MISMATCH: {$table}.{$column}");
        }
    }
}

$sourceClaims = (int) $db->selectOne(
    "SELECT COUNT(*) AS n FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_posting_source_claims'"
)->n;
$sourceChecks = (int) $db->selectOne(
    "SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK'
       AND TABLE_NAME LIKE 'finance_posting_%_sources'"
)->n;
if ($sourceClaims !== 1 || $sourceChecks < 7) {
    throw new RuntimeException("V2 cross-family canonical source claims not DB-enforced: {$sourceChecks} family CHECKs");
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

// Verify a meaningful composite unique source key exists physically in MySQL.
$journalUnique = (int) $db->selectOne(
    "SELECT COUNT(*) AS n FROM (
       SELECT INDEX_NAME FROM information_schema.STATISTICS
       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_vouchers'
         AND NON_UNIQUE=0
       GROUP BY INDEX_NAME
       HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
              = 'project_id,type,voucher_number'
    ) AS exact_unique"
)->n;
if ($journalUnique !== 1) {
    throw new RuntimeException('Missing project/type/scoped journal voucher uniqueness.');
}

// The seeded source-bank primary key must reject duplicates physically.
DB::beginTransaction();
try {
    DB::table('banks')->insert(['id' => 1, 'display_name' => 'Duplicate bank ID']);
    throw new RuntimeException('Duplicate bank identity unexpectedly accepted.');
} catch (\Illuminate\Database\QueryException $e) {
    // Database UNIQUE/PK constraint correctly blocked the duplicate.
} finally {
    DB::rollBack();
}

// Historical money/date values can be imported verbatim, while FK parents
// cannot be deleted out from underneath financial payment sources.
DB::beginTransaction();
try {
    $id = DB::table('customer_ledger')->insertGetId([
        'date' => '2020-06-05', 'transaction_type' => 'CR',
        'amount_in' => '123.45', 'amount_out' => '0.00',
        'bank_id' => 1,
        'created_at' => '2020-06-05 02:03:04',
        'updated_at' => '2020-06-08 05:06:07',
    ]);
    $stored = DB::table('customer_ledger')->where('id', $id)->first();
    if ((string) $stored->created_at !== '2020-06-05 02:03:04' ||
        (string) $stored->updated_at !== '2020-06-08 05:06:07' ||
        (string) $stored->amount_in !== '123.45') {
        throw new RuntimeException('Historical timestamp or amount was changed by V2 schema.');
    }
    try {
        DB::table('banks')->where('id', 1)->delete();
        throw new RuntimeException('Bank parent deletion unexpectedly permitted.');
    } catch (\Illuminate\Database\QueryException $e) {
        // Strict FK prevents erasing a source lookup used by historic finance.
    }
} finally {
    DB::rollBack();
}
if ((int) DB::table('customer_ledger')->count() !== 0) {
    throw new RuntimeException('Disposable import fixture left persistent rows.');
}

// One 2026-10-30 source follow_up TIMESTAMP is a historic ZERO date.
// Verify exact preservation is possible in a disposable session WITHOUT
// changing the GLOBAL sql_mode. Always restore the session mode.
$beforeMode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS m')->m;
$db->statement('CREATE TEMPORARY TABLE v2_zero_date_session_probe (stamp TIMESTAMP NOT NULL)');
try {
    $withoutZeroModes = implode(',', array_filter(explode(',', $beforeMode),
        static fn ($m) => !in_array($m, ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE'], true)));
    $db->statement('SET SESSION sql_mode = ?', [$withoutZeroModes]);
    $db->table('v2_zero_date_session_probe')->insert(['stamp' => '0000-00-00 00:00:00']);
    $saved = (string) $db->table('v2_zero_date_session_probe')->value('stamp');
    if ($saved !== '0000-00-00 00:00:00') {
        throw new RuntimeException('Legacy zero TIMESTAMP was not preserved exactly in isolated MySQL.');
    }
} finally {
    $db->statement('SET SESSION sql_mode = ?', [$beforeMode]);
    $db->statement('DROP TEMPORARY TABLE IF EXISTS v2_zero_date_session_probe');
}
$restored = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS m')->m;
if ($restored !== $beforeMode) {
    throw new RuntimeException('Session SQL mode not restored after legacy compatibility test.');
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
    .  "source-claim exclusivity CHECKs; correct broker/bank/account identity; 27 seeded banks; "
    . "legacy migration-history isolated; rejected invalid ledger write; rollback intact.\n";
