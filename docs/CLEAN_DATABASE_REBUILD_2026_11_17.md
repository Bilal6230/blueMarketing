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
