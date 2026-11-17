# Database V2 — three stock ENUM case mismatch RCA and guarded local repair

**Date:** 2026-10-31. **Snapshot:** original private `u429032065_dev.sql`, SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`. **Target:** only the imported **local** MySQL 8.0.30 `bluemarketing_v2_local_rehearsal` clone. NEVER apply to old/staging/live.

## 1. Root cause confirmed in source code

The old V2 baseline migration `2026_10_30_000020_create_v2_legacy_domain_baseline.php` contained:

```php
$sqlType = strtolower((string) strtok($definition, ' '));
```

This lowercased *both* SQL type identifier AND quoted ENUM members. MySQL stores the ENUM member text specified in DDL rather than the case originally provided during source data insert. The original MariaDB columns and V2-lowered equivalents were:

| Table.column | Original source type | Previously imported MySQL ENUM type |
|---|---|---|
| stock_entries.flow | `enum('IN','OUT')` | `enum('in','out')` |
| stock_movements.direction | `enum('IN','OUT')` | `enum('in','out')` |
| stock_parties.type | `enum('Supplier','Consumer','Site')` | `enum('supplier','consumer','site')` |

These are exactly the **three** columns the owner's read-only per-column SHA comparison detected. All other **39** stock columns matched. The source snapshot contains **1**, **2**, and **3** source rows in the respective tables. Source aggregate checks (120,165 rows, 34 financial columns) remained PASS; 54 other tables also passed full all-original-column SHA.

The 2026-10-31 source baseline fix preserves quoted SQL type arguments:

```php
$sqlType = (string) strtok($definition, ' ');
$base = strtolower($m[1]);
```

Therefore a **fresh, empty V2 schema** will retain original ENUM case. This source migration correction **does not retroactively change a database where the earlier migration already ran**.

## 2. Safe restoration for existing populated local V2 clone (separate opt-in)

New `tools/database_v2/restore_stock_enum_case.php` is an **isolated**, explicit, fail-closed DDL correction. Unlike modifying individual stock source rows, it applies only the exact original ENUM definitions with the same ENUM **member order**. It checks the source full-row fingerprint from private SQL, target DB name/host/environment, FK enabled, 3 original Laravel migrations, 27 banks, 0 finance posting rows, original stock table row counts, and exact recognized old/new ENUM types. It computes enum numeric ordinal histograms before ALTER and independently verifies those same ordinals and exact `COLUMN_TYPE` afterward.

**Safety warning:** MySQL `ALTER TABLE` DDL causes an implicit commit and **cannot be rolled back like the previous source INSERT transaction**. The ALTER may rebuild the table. Take a **private LOCAL export/backup of `bluemarketing_v2_local_rehearsal` before invoking `--apply`**. The original SQL dump must also be retained. No automatic updates to individual source IDs, amounts, references or audit timestamps are authorized. No `migrate:fresh`, `DROP`, `TRUNCATE` or reimport.

### Step A — pull code and run **READ-ONLY** preview (do first)

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
git status --short

powershell -NoProfile -Command {
    $env:APP_ENV = "local"
    $env:DB_CONNECTION = "mysql"
    $env:DB_HOST = "127.0.0.1"
    $env:DB_PORT = "3306"
    $env:DB_DATABASE = "bluemarketing_v2_local_rehearsal"
    $env:DB_URL = ""
    $env:DATABASE_URL = ""

    php .\tools\database_v2\restore_stock_enum_case.php `
      "--source-evidence=$env:USERPROFILE\Documents\BMV2Private\staging_20261030\source_full_row_digests.json"

    exit $LASTEXITCODE
}
```

**Expected** `V2_ENUM_CASE_REPAIR_NEEDED` for all three table.column values, followed by `V2_ENUM_READ_ONLY_PREFLIGHT_PASS: 3/3 case-only definitions require restoration. No database writes.` If a different enum schema or row count is found, stop. Send **only safe label/status output**, never private source digest JSON or data.

### Step B — backup and only then opt in to DDL

This step is **not included in the default preview**. After Step A PASS and owner confirmation, export the *local V2 rehearsal database* to a private `.sql` file **outside the Git tree** using local phpMyAdmin Export, without exposing it to GitHub/chat. Do not export or alter other databases.

To apply only to this exact local target, run the same PowerShell environment block above but with **both** `--apply` and `--confirm-database=bluemarketing_v2_local_rehearsal` on the PHP line. The tool skips already-correct fields and refuses unknown schemas. **Do not run this until the local backup is verified.**

### Step C — after case-only DDL, rerun independent read-only SHA comparison

No source SQL or fingerprint regeneration required; the source evidence has not changed:

```powershell
powershell -NoProfile -Command {
    $env:APP_ENV = "local"
    $env:DB_CONNECTION = "mysql"
    $env:DB_HOST = "127.0.0.1"
    $env:DB_PORT = "3306"
    $env:DB_DATABASE = "bluemarketing_v2_local_rehearsal"
    $env:DB_URL = ""
    $env:DATABASE_URL = ""

    php .\tools\database_v2\verify_mysql_full_row_digests.php `
      "--source-evidence=$env:USERPROFILE\Documents\BMV2Private\staging_20261030\source_full_row_digests.json"

    exit $LASTEXITCODE
}
```

**Acceptance:** `V2_FULL_ROW_PARITY_PASS: 57/57 tables, 120165/120165 rows`. If not, stop and retain the private backup for investigation. Never change the verifier to ignore case on business ENUMs; the legacy source distinguishes these exact text labels.

## 3. Independent regression evidence and remaining work

- Future baseline migration now preserves quoted ENUM case; disposable MySQL 8.4 CI `verify_mysql_schema.php` checks all three exact source `COLUMN_TYPE`s.
- MySQL 8.4 CI also creates a temporary fixture with old lowercase ENUMs, ALTERs to original-case labels, and verifies **same numeric ordinals** with restored original display case. No private SQL or customer data is used in CI.
- Full project gate remains **8/24 = 33%** pending independent local 57-table full-row parity and approval of historical source/financial exceptions. This fix is a stock-data fidelity correction, **not** typed JV/stock source ownership or financial UAT certification.
