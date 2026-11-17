# BlueMarketing clean database rebuild — isolated first gate

Owner supplied `bluenew (2).sql` exported 2026-11-17 (MySQL 8.4.3).
Existing `bluenew` is protected: do not modify or point migration commands at it.

## Confirmed inventory

- 147 SQL tables, including historical `migrations`; 146 application tables.
- 1,428 columns, 511 source indexes and 250 source FKs (see independent schema audit).
- GitHub main historical path: 110 migration files; source DB: 67 historical migration entries.
- Original `ledgers` and `customer_ledger` migrations do not fully describe current columns. Historical chain must remain unchanged for current deployments.

## Implemented first gate

`tools/schema/generate_clean_baseline.php` generates **schema only** locally from owner export. It validates 147 CREATE TABLE statements and precisely 146 schema CREATEs, 146 index ALTERs, 117 AUTO_INCREMENT ALTERs and 110 FK ALTERs. The result is isolated under `database/migrations/clean_baseline/`. Do not copy it into the ordinary migrations directory. No customer data or credentials are committed.

This is an **interim exact-DDL audit bootstrap**, not the final hand-maintained, domain-organized Schema::create files or an optimized index design. Never claim data migration, independent accounting integrity or MySQL schema acceptance before running gates.

## Exact first local execution (PowerShell)

Use an existing local copy of uploaded SQL (change the file path to match the local file):

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php tools/schema/generate_clean_baseline.php "C:\path\to\bluenew (2).sql"
php -l database/migrations/clean_baseline/2026_11_17_000000_create_bluenew_clean_baseline.php
```

In **phpMyAdmin**, create a NEW EMPTY MySQL database `bluenew_clean`; do **not** rename or replace `bluenew`. Then run in same PowerShell (temporary env overrides, no .env rewrite):

```powershell
$env:APP_ENV = "local"
$env:DB_DATABASE = "bluenew_clean"
php artisan optimize:clear
php artisan migrate --path=database/migrations/clean_baseline --force
php artisan migrate:status --path=database/migrations/clean_baseline
Remove-Item Env:\DB_DATABASE
Remove-Item Env:\APP_ENV
```

The generated migration itself guards local MySQL + local host + exact `bluenew_clean` database name. A failed MySQL DDL run is NOT transactionally rolled back; recreate *only the empty bluenew_clean* after investigating, never run migrate:fresh on bluenew.

## Next gates

1. Compare destination columns/constraints/index signatures to the supplied dump.
2. Prepare a protected data-only import, excluding `migrations`; preserve ID zero (`NO_AUTO_VALUE_ON_ZERO`), insertion order, parent IDs, and autoincrement; validate orphan/unique/enum conflicts before enabling constraints.
3. Compare all table counts, FK orphans, and financial source/ledger totals independently, including Project 3.
4. Replace DDL snapshot with reviewed, grouped Laravel Schema::create migrations and query-plan-driven indexes, then repeat clean installation/data restoration.
5. Do not switch normal app .env or production hosting until every gate passes.


## 2026-11-17 Owner fresh schema gate accepted; DATA COPY ready

Owner screenshot shows `bluenew_clean` has **147 tables, zero application records** in phpMyAdmin, matching the expected table count. Screenshot alone does not establish that all schema signatures/FKs match, but table-count gate is accepted.

Added `tools/schema/copy_clean_data.php` (commit ceade864): guarded same-server, data-only `bluenew` -> `bluenew_clean` copier. It checks local environment, source/target 147-table same names, column order equality, empty 146 destination application tables, one clean destination migrations row, and preserves all source PK IDs including ID zero. No copy of migrations history. Uses SQL INSERT ... SELECT with explicit columns; session FK bypass for parent order then restores FK checks, verifies per-table row counts and declared FK orphan links. Does not modify original `bluenew`. If interrupted, destination may be partially populated; never rerun blindly on a nonempty destination, instead inspect or recreate ONLY `bluenew_clean`. This code is intended for the exact independently verified local snapshot, not general purpose database syncing.

User next actions (PowerShell):
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php tools/schema/copy_clean_data.php
# only if PRE-FLIGHT PASS:
php tools/schema/copy_clean_data.php --apply
```
Do not run `migrate:fresh` or replace application .env. Review any FK orphan failures; counts/FK pass is NOT full accounting integrity.


## 2026-11-17 — Imported partial data fails on legacy zero datetime; guarded resume fix

Owner executed importer: source 120,835 rows; first 59 nonempty tables copied into `bluenew_clean`, then MySQL 8.4 rejected `leads.follow_up = '0000-00-00 00:00:00'` (SQLSTATE 22007 / 1292). This is not a migration-structure failure, not a missing column and not a reason to modify the original rows.

Resolved in `tools/schema/copy_clean_data.php`: `--resume` allows only already-populated destination tables with exact source row counts, skips these, and copies remaining tables. Copy session sets `sql_mode='NO_AUTO_VALUE_ON_ZERO'` to preserve legacy zero datetime and ID zero (restored in finally), plus restores foreign key checks in finally. After copy, every nonempty primary-key table is compared source vs target using all columns, and declared FK orphan checks run. Remaining tables without PK receive row-count-only comparison and are listed as such. Added static `CleanDatabaseCopyGateTest`. CI/local runtime validation still required.

Commands:
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l tools/schema/copy_clean_data.php
php artisan test --filter=CleanDatabaseCopyGateTest
php tools/schema/copy_clean_data.php --resume
```

**Never** run `--apply` (requires fully empty target) or wipe the partial `bluenew_clean` for this error. If resumed import fails on a different legacy type or FK orphan, inspect and address precise error without changing `bluenew`. Database copy is not accepted until final reconciliation output. Strict SQL mode is relaxed for the importer session ONLY; it does not change server/app SQL mode. Full accounting comparison and final maintainable Laravel migrations remain pending.


## 2026-11-17 — Owner DATA COPY PASS — 120,835 rows

Owner ran `php tools/schema/copy_clean_data.php --resume` on local MySQL 8.4:
- `PREFLIGHT PASS`: 146 schema-matched application tables, 120,835 source rows; `migrations` history excluded.
- `CHECKED: 146 tables, 250 foreign keys.`
- `PASS: 120835 rows copied; table counts and declared foreign key relationships verified.`
- One remaining non-PK table `punches` was count-checked only; now independently audited by full-row multiset.
- This is **accepted Data-Only Recovery Gate**. Keep original `bluenew` safe and do not rerun copy.

Added `tools/schema/audit_clean_reconciliation.php` (read-only) to compare information_schema column/engine/index/FK signatures, all numeric aggregate columns on 19 finance-relevant tables and full `punches` row multiset. No writes, no environment flips. Owner should now run:
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l tools/schema/audit_clean_reconciliation.php
php tools/schema/audit_clean_reconciliation.php
```
Report schema/equality errors for next repair. Results do not establish independent accounting correctness or final consolidated Laravel-native migration design.


## 2026-11-17 — Owner confirms independent restoration audit PASS

Owner executed read-only audit and returned:
- `PASS SCHEMA column definitions (1428 vs 1428)`
- `PASS SCHEMA indexes (761 vs 761)`
- `PASS SCHEMA foreign keys (301 vs 301)`
- `PASS SCHEMA foreign key rules (250 vs 250)`
- `PASS SCHEMA table engines (147 vs 147)`
- All 19 finance-table numeric aggregates PASS; non-PK `punches` full-row multiset PASS.
- Final: `PASS: schema signatures, 19 finance-table numeric aggregates and punches row data match.`

**CLOSE** both local source-to-target data-copy and SQL-equivalent schema-reconciliation gates. Source `bluenew` and validated `bluenew_clean` remain untouched and protected. No need to rerun copy.

NEXT: 6-file domain-grouped Laravel-native Blueprint draft generated from source SQL, stored separately in conversation artifact `bluemarketing_native_migrations_draft.zip` (not committed; still an experimental draft). PHP lint passes all six. Exact snapshot has 146 app tables, 250 FK definitions and existing indexes. It is NOT approved for production: MySQL fresh migration and schema audit may uncover differences (data defaults, named indexes, etc.), and the index workload review is not complete. The draft must be placed only in `database/migrations/clean_native_draft` and tested exclusively in new disposable local `bluenew_clean_native`—never in `bluenew` or validated `bluenew_clean`.

Updated read-only audit CLI to support a safe `--target=bluenew_clean_native --schema-only` command so native test results can be compared with source signatures. This does not imply the draft has passed MySQL.

Expected native test commands once draft ZIP has been extracted:
```powershell
$env:APP_ENV = 'local'
$env:DB_DATABASE = 'bluenew_clean_native'
php artisan optimize:clear
php artisan migrate --path=database/migrations/clean_native_draft --force
php tools/schema/audit_clean_reconciliation.php --target=bluenew_clean_native --schema-only
Remove-Item Env:\DB_DATABASE
Remove-Item Env:\APP_ENV
```


## 2026-11-17 — Owner rejects 6-group draft: per-table native baseline requested

Owner specifically requires each table's own migration, old `database/migrations` archived as `database/old_migration`, and new empty database install via plain `php artisan migrate`. All legacy business rows/timestamps preserved during separate data-only copy. The previous 6-group draft is superseded.

Prepared downloadable `bluemarketing_one_table_migrations.zip` containing:
- 146 separate Laravel Blueprint `create_<table>_table.php` application migrations (one per application table);
- a first, guard-only migration to reject existing populated/protected MySQL DBs;
- a final relationship-only migration adding the 250 existing source foreign keys AFTER all parent tables exist;
- an installer that archives original migrations folder into `database/old_migration` and installs new files into normal `database/migrations`.
- Historical source timestamps retained in filenames where attributable (81 source table mappings); 65 tables without confirmed individual original migration filenames carry the 2026-11-17 baseline timestamp. This does NOT change business record `created_at`, `updated_at` or IDs.

All PHP migration files pass `php -l` but **MySQL native migration execution is not yet tested**. Do not declare production ready or change real database. Use NEW empty local `bluenew_clean_final`; never rerun on `bluenew` or verified `bluenew_clean`.

Added `tools/schema/install_one_table_migrations.ps1`, guarded `--target=bluenew_clean_final` to existing read-only-source data copier, and `--target=bluenew_clean_final --schema-only` to audit. Script supports --resume on validated partial data restore. Do not copy historical migration records into final DB.

Owner next sequence: unzip bundle, run installer from bundle or tracked `tools/schema/install_one_table_migrations.ps1 -BundleFolder <unzipped path>`; create `bluenew_clean_final`; set local DB_DATABASE via process environment, run `php artisan optimize:clear`, plain `php artisan migrate`, then schema-only comparison. Only after schema comparison PASS run copied data preflight/import and reconciliation. If any step fails, repair native Blueprint definitions without touching old restored sources.

**Repo limitation:** The 148 generated Laravel migration files are currently in the downloadable ZIP and require a local install; they have **not** yet been individually committed to GitHub. The tracked installer and importer/audit support are committed; after MySQL acceptance, commit the generated native migration files and legacy archive as a reviewed batch.


## 2026-11-17 — GitHub per-table migrations fully committed (no ZIP required)

Commit `25d11d4f3cd9c98033f9cc5ed506edc69e999d20` atomically:
- Moved **all 110 original migration PHP files** from `database/migrations` to `database/old_migration` in GitHub.
- Added **146 individual one-table migrations** to standard `database/migrations` (based on verified 2026-11-17 `bluenew` MySQL 8.4 source CREATE/INDEX/AUTO_INCREMENT statements; one PHP file for each source application table).
- Added first-run protected empty-database migration and final FK migration (110 ALTER TABLE statements, 250 constraints).
- Removed 146 staging files. Post-commit GitHub tree confirmed `database/migrations` = 148 PHP, `database/old_migration` = 110 PHP, staging = 0.
- The files use source-exact MySQL DDL through Laravel migration classes (`DB::unprepared`), **not yet native `Schema::create` Blueprint definitions**. This avoids silent loss of original collation/keys, but the maintainability/native conversion request remains a separate improvement.
- Original table creation filename timestamps preserved where a same-named historical migration was identifiable. The other newly catalogued tables use the 2026-11-17 baseline timestamp. Business row timestamps are unchanged by schema migration; the separate data-only copy preserves old IDs and timestamps.

Owner now needs no ZIP/download/install. GitHub pull, **new empty `bluenew_clean_final` database** and `php artisan migrate` using local process env. Do not run plain migrate against historical `bluenew` or validated `bluenew_clean`, which the guard rejects. The migration execution itself and destination schema signature comparison remain **unverified** until owner runs them locally.

Commands:
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
$env:APP_ENV = 'local'
$env:DB_DATABASE = 'bluenew_clean_final'
php artisan optimize:clear
php artisan migrate
php tools/schema/audit_clean_reconciliation.php --target=bluenew_clean_final --schema-only
Remove-Item Env:\DB_DATABASE
Remove-Item Env:\APP_ENV
```
Only after native/new migration schema signature PASS run `php tools/schema/copy_clean_data.php --target=bluenew_clean_final --apply`, followed by read-only reconciliation (target option without schema-only). Expected migration history count 148. Do not copy old `migrations` data.


## 2026-11-17 — First standard migrate parse failure and bulk fix

Owner ran plain `php artisan migrate` on new `bluenew_clean_final`, which created only the Laravel `migrations` table, then failed before running table migrations:
`ParseError syntax error, unexpected token "<<" at database/migrations/2019_08_19_000000_create_failed_jobs_table.php:10`.
Root cause: the first batch of 50 per-table migrations had a trailing space after the PHP nowdoc opener (`<<<'BMDDL0' `), illegal under PHP 8.4. A second formatting difference in that batch was `BMDDL0 );`. Fixed all 50 offending PHP migration files directly on GitHub in seven atomic batches (commits d960feb9 through ad2c1b59). Original 110 old migrations and 146 new one-table migrations remain archived/active, respectively. The later 96 generated migrations and final FK file were authored without trailing nowdoc whitespace and need no changes.

Next owner command on the *existing still-empty* `bluenew_clean_final` (which has just migrations table; NOT dropping database):
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
$lintErrors = @(Get-ChildItem database/migrations -Filter *.php | ForEach-Object { $out = php -l $_.FullName; if ($LASTEXITCODE -ne 0) { $out } })
if ($lintErrors.Count -gt 0) { $lintErrors; throw 'Migration PHP syntax check failed' }
$env:APP_ENV = 'local'
$env:DB_DATABASE = 'bluenew_clean_final'
php artisan optimize:clear
php artisan migrate
php tools/schema/audit_clean_reconciliation.php --target=bluenew_clean_final --schema-only
Remove-Item Env:\DB_DATABASE
Remove-Item Env:\APP_ENV
```
Only upon MySQL migration and schema-audit PASS, copy data with original timestamps. Never `migrate:fresh` on `bluenew` or `bluenew_clean`.


## 2026-11-17 — Final FK migration PHP class terminator fixed

Owner re-ran all PHP -l checks: **147/148 passed**, only `2026_11_17_999999_add_final_foreign_keys.php` reported `Parse error: Unclosed '{' on line 7`. Root cause directly verified: the final anonymous migration class lacked its closing `};`. Committed exact fix as `23700362`. Added automatic regression test `tests/Unit/AllMigrationFilesSyntaxTest.php` that PHP-lints all 148 active migrations and verifies 110 archived files (commit `53b0330f`). This is not a MySQL-schema validation; local migration still must run.

Owner's `bluenew_clean_final` retains only Laravel migration tracking table; no application table migrations executed. No DB recreation necessary. Do not run schema audit following failed migrations; run only after explicit success.

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan test --filter=AllMigrationFilesSyntaxTest
if ($LASTEXITCODE -ne 0) { throw 'Migration lint failed; stopping' }
$env:APP_ENV='local'
$env:DB_DATABASE='bluenew_clean_final'
php artisan optimize:clear
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Migration failed; STOP, do not audit' }
php tools/schema/audit_clean_reconciliation.php --target=bluenew_clean_final --schema-only
Remove-Item Env:\DB_DATABASE
Remove-Item Env:\APP_ENV
```

For failures in the above sequence, remember temporary environment variables stay set until manually cleared; never run further migrate commands targeting original `bluenew`/`bluenew_clean` inadvertently.


## 2026-11-17 — FINAL LOCAL REBUILD AND HISTORICAL COPY PASSED

Owner executed on local MySQL 8.4:
- All 148 files in standard `database/migrations` finished successfully using **plain `php artisan migrate`** in new empty `bluenew_clean_final`.
- 110 older migrations are preserved in `database/old_migration`.
- Read-only source-to-target schema audit: **PASS** 1,428/1,428 column definitions, 761/761 index records, 301/301 FK column records, 250/250 FK rules, 147/147 table-engine definitions.
- Safe data-only import: **PASS**, exactly **120,835 source rows** into 146 application tables, historical migration tracking table excluded. All declared 250 FK relationships validated.
- Second full read-only comparison: **PASS** schema signatures, numeric aggregates in all 19 selected finance/accounting tables, and complete `punches` multiset (table has no PK).
- Import uses explicit original source columns/values, preserving IDs and source `created_at`/`updated_at`/legacy datetimes rather than generating new timestamps.
- Original `bluenew` and earlier validated `bluenew_clean` remain unchanged.

**CLOSE**: fresh clean DB creation through normal Artisan migrations, exact-schema match, historical data migration, source-vs-restored-data reconciliation.

**Do not confuse the acceptance gate with**: application/browser testing, independent source bookkeeping correctness, optimized index query-plan verification, or conversion of migration internals to Laravel `Schema::create`. The 146 files are one-per-table but deliberately contain exact source MySQL DDL through Laravel `DB::unprepared`. These remaining quality improvements are separate.

**No more data-copy or migrations reruns needed** on `bluenew_clean_final`. Do not run `migrate:fresh` on any historical or validated databases.


## 2026-11-17 — Next milestone: local app readiness, read-only first

Final source-to-target database equality already passed; no import rerun is needed. Added `tools/schema/accept_clean_application.php` read-only local-only app-data smoke gate with:
- Explicit guard for `APP_ENV=local`, local MySQL host, no DATABASE_URL, and `bluenew_clean_final`.
- 147 tables, 146 app tables, 148 migration-history rows, all 120,835 total app data rows.
- Key business/authorization table expected counts (users, roles, permissions, projects, leads, bookings, ledgers, journals, cheque/online intake).
- 250 FK definitions, plus full read-only FK orphan queries using composite null-safe MATCH SIMPLE handling.
- No writes, schema changes, seeders, or migrations. Includes static regression tests in `tests/Unit/CleanDatabaseApplicationAcceptanceTest.php`.

Owner commands to run read-only gate:
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
$env:APP_ENV='local'
$env:DB_DATABASE='bluenew_clean_final'
php artisan optimize:clear
php artisan test --filter=CleanDatabaseApplicationAcceptanceTest
php -l tools/schema/accept_clean_application.php
php tools/schema/accept_clean_application.php
```
After PASS, launch a separate local terminal WITH same environment variables for browser testing:
```powershell
cd C:\laragon\www\blueMarketing
$env:APP_ENV='local'
$env:DB_DATABASE='bluenew_clean_final'
php artisan serve --host=127.0.0.1 --port=8001
```
Open `http://127.0.0.1:8001/login`, use existing local credentials, inspect home/dashboard, user roles, project navigation, ledger, JV listing, cheque/online intake listing, and report pages **READ-ONLY**. Do not save vouchers/approval actions during first pass. Since copied token/session records may exist, perform fresh browser login rather than assuming sessions carry over. Avoid putting the `bluenew_clean_final` database in permanent `.env` until deliberate switch-over is approved.

Business acceptance remains PENDING until owner reports actual read-only gate and browser results. Independent bookkeeping correctness and index/query-plan optimization still future milestones.
