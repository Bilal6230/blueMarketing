# Database V2 — 3 stock-table full-row parity exceptions (2026-10-31)

**Scope:** Private source SQL `u429032065_dev.sql` SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`; target local `bluemarketing_v2_local_rehearsal`, MySQL **8.0.30**. No private historical row values or per-customer IDs are committed.

## Confirmed from owner's local terminal

- **PASS:** Exact source SHA256, complete imported historical row total **120,165 / 120,165**, all **57** original table row counts, original ID bounds and audit timestamp extrema/non-null counts, **34/34** source financial aggregate numeric columns. No production or V1 data changed.
- **PASS:** First complete all-original-column SHA256 multiset comparison found **54/57 tables equal**.
- **HOLD:** Exactly **three source tables differed at least once in all-column digest:** `stock_entries`, `stock_movements`, `stock_parties`. Source snapshot contained respectively **1**, **2** and **3** rows in these tables; this means the remaining forensic scope is **6 source records spread across 3 tables**, **NOT** six definitively changed or corrupted rows. The scanner checks every original column and preserves duplicate identical rows.
- **Not yet known:** Whether difference is storage normalization, an SQL text escaping/parser discrepancy, timezone/ENUM/character-set interpretation, or a genuinely changed value. **Do not** alter/source-reimport/normalize values until the differing exact column(s) have been identified.
- **Formal 8/24 = 33% gate progress remains unchanged.** P4.G3 and P6.G1 stay open until differences are explained, source evidence preserved, and complete parity certified.

## New isolated, read-only, zero-PII diagnostic tools

- `tools/database_v2/stock_column_diagnostics.py` creates a **PRIVATE** source SHA256 for every original column separately across the 3 stock tables; verifies the same immutable uploaded SQL and the 57/120165 source-table baseline. It stores **hashes and schema column names only** outside the Git tree.
- `tools/database_v2/verify_mysql_stock_columns.php` checks only the three tables on exact local V2 MySQL, with FKs enabled, and computes the identical original-column multiset SHA from imported rows; prints only **table name, row count, matching-column count, and names of mismatched columns**. Never prints private values, amounts, notes, primary IDs or database query payloads. Does not call any mutation methods.
- CI syntax/selftest and Python synthetic tests cover duplicates, NULL vs empty string and DECIMAL representation. This is **diagnostic evidence**, not a blind data fix.

## Safe owner instructions (READ-ONLY: no new migration, import, DELETE or UPDATE)

**1. Pull the new source-only and read-only MySQL diagnostic tools:**

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
git status --short
```

**2. Create a NEW private per-column evidence file (no database connection):**

```powershell
$StageDir = "$env:USERPROFILE\Documents\BMV2Private\staging_20261030"
py -3 .\tools\database_v2\stock_column_diagnostics.py `
  --source "C:\Users\anwaa\Downloads\u429032065_dev.sql" `
  --out "$StageDir\stock_column_evidence.json"
if ($LASTEXITCODE -ne 0) { throw "Source stock diagnostic failed. STOP." }
```

Expected: `STOCK_COLUMN_SOURCE_EVIDENCE_READY: 3 tables, source SHA verified`. Tool refuses to overwrite an existing evidence file.

**3. Compare MySQL stock columns against source (read-only):**

```powershell
powershell -NoProfile -Command {
    $env:APP_ENV = "local"
    $env:DB_CONNECTION = "mysql"
    $env:DB_HOST = "127.0.0.1"
    $env:DB_PORT = "3306"
    $env:DB_DATABASE = "bluemarketing_v2_local_rehearsal"
    $env:DB_URL = ""
    $env:DATABASE_URL = ""

    php .\tools\database_v2\verify_mysql_stock_columns.php `
      "--source-evidence=$env:USERPROFILE\Documents\BMV2Private\staging_20261030\stock_column_evidence.json"
    exit $LASTEXITCODE
}
```

Expected output starts with `STOCK_COLUMN_TABLE: stock_entries` / `stock_movements` / `stock_parties`; differences appear as `STOCK_COLUMN_DIFF: stock_entries.<column>` etc. When any differences exist, **exit code 3 is deliberate diagnostic status, not a SQL import failure**.

**4. Send only these safe markers and names:** lines beginning `STOCK_COLUMN_TABLE:`, `STOCK_COLUMN_DIFF:`, and `V2_STOCK_COLUMN_DIAGNOSTIC_COMPLETE:`. **Never upload private `stock_column_evidence.json`, the raw SQL dump, stage SQL files or financial/customer values.**

### Next code milestone after diagnostic

Classify each mismatched column as a storage/encoding interpretation or an actual retained-value change. Fix a verifier's canonicalization **only with proof** that source and MySQL stored values are semantically identical; otherwise document exact historical exception and require owner-reviewed correction on a separate isolated clone. Once all 57 table row digests pass, reconsider the original data preservation gate. Typed JV/stock financial posting source associations remain intentionally unresolved and require independent audit.
