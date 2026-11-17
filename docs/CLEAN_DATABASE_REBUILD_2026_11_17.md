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
