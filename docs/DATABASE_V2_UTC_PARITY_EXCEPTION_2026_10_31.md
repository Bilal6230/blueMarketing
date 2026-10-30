# V2 local rehearsal — UTC historical instant mismatch exception

**Date:** 2026-10-31
**Environment:** owner-operated, isolated local MySQL `bluemarketing_v2_local_rehearsal`
**Status:** BLOCKING; no remediation approved; 8/24 formal gates accepted.

## Verified operator output

- Commit `d2282d7b` pulled successfully; working tree remained clean.
- 45 focused Laravel tests / 159 assertions PASS; all five changed PHP files linted clean.
- Original SQL source SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed` matches and source export timezone `+00:00` is explicit.
- SQL full-row source digests under `+00:00` session: **41 of 57 original tables differ**, total source-row count **120165**. This is a **table count**, not a count of shifted rows or corrupted source records.
- Previously, original-column parity under the default local `SYSTEM` timezone passed 57/57. The local system timezone is reported as `Pakistan Standard Time`.
- `git diff --check 55cec100 HEAD` reported one blank EOF line in `verify_mysql_full_row_digests.php`. Corrected in separate commit; unrelated to the accounting mismatch.

## Root-cause hypothesis and precise next proof

MySQL `TIMESTAMP` converts between session-local values and stored UTC instants. The original source exported UTC; the previous importer applied legacy `sql_mode` but did **not** set `SET SESSION time_zone='+00:00'`. Rows can therefore display the original source strings in Pakistan time while storing shifted UTC instants.

This is a **high-confidence mechanism, not yet conclusive per-column evidence**. The 41-table failures can include unrelated differences. Execute the read-only dual-session classifier:

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l tools/database_v2/diagnose_mysql_source_timezone_parity.php
php tools/database_v2/diagnose_mysql_source_timezone_parity.php --selftest

powershell -NoProfile -Command {
    $env:APP_ENV = "local"
    $env:DB_CONNECTION = "mysql"
    $env:DB_DATABASE = "bluemarketing_v2_local_rehearsal"
    $env:DB_HOST = "127.0.0.1"
    $env:DB_PORT = "3306"
    $env:DB_URL = ""
    $env:DATABASE_URL = ""
    php .\tools\database_v2\diagnose_mysql_source_timezone_parity.php `
      "--source-evidence=$env:USERPROFILE\Documents\BMV2Private\staging_20261030\source_full_row_digests.json"
    exit $LASTEXITCODE
}
```

Tool classifies 57 original tables as `SOURCE_MATCHES_BOTH_SESSIONS`, `SOURCE_MATCHES_UTC_ONLY`, `SOURCE_MATCHES_PLUS05_ONLY`, or `SOURCE_MATCHES_NEITHER_SESSION`. It reports **table names and count of original TIMESTAMP columns**, never individual IDs, row values, customers or full digests. If +05-only appears, exit code 4 is intentional: the current imported clone fails original UTC instant acceptance. If either timezone fails and the other matches, it isolates timezone dependence. If neither matches, investigate additional causes.

## Isolation and remediation policy

- Do not run UPDATE, DELETE, ALTER, TRUNCATE, `migrate:fresh`, or `SET GLOBAL` on original, V1, staging, live or existing V2 rehearsal data.
- Do not rewrite imported `TIMESTAMP` fields in place or declare them repaired from aggregate evidence. Source rows may contain historical zero timestamps and soft-deletion/audit history.
- A **separate disposable, empty, named V2 database** is the appropriate target for a future strictly controlled *fresh* import with explicit source `+00:00` session. Require independent backup, exact source fingerprint, owner approval and full 57/57 UTC, FK, finance and project reconciliation before considering any replacement. Existing rehearsal must be retained for comparison.
- Never infer `ledgers.type_id` source ownership, backfill typed source claims or repair finance ledger records automatically.

## Formal acceptance

P4.G3 and P4.G4 remain BLOCKED; P6 financial/cutover gates remain BLOCKED. Phase 4 1/4=25%, entire Database V2 8/24=33.3%. No production cutover.

## Independent schema cross-check of operator's mismatch list

The 41 reported `TABLE_DIFF` table names were cross-checked against all four pinned `database/v2_schema/legacy_columns_*.json` files. **41/41 mismatching tables have at least one original TIMESTAMP field; zero UTC-mismatching tables are timestamp-free.** Those 41 tables collectively define **102 original TIMESTAMP columns**. The 16 original source tables that matched under UTC include some timestamp columns; presence alone does not prove a changed stored value. This strongly supports timezone dependence but does not establish precise affected-row count or exclude additional differences. A two-session source digest classification is required.

## 2026-10-31 — Owner two-session diagnostic PASS, mismatch confirmed

At clean local commit `6efaace6`, owner executed the read-only `diagnose_mysql_source_timezone_parity.php` with verified private source digest. PHP lint and `--selftest` PASS. The exact output classification was:

- `SOURCE_MATCHES_PLUS05_ONLY` = **41** original tables.
- `SOURCE_MATCHES_BOTH_SESSIONS` = **16** original tables.
- `SOURCE_MATCHES_UTC_ONLY` = **0**.
- `SOURCE_MATCHES_NEITHER_SESSION` = **0**.
- Expected historical row count = **120165**; database unchanged; CLI exit **4** intentionally blocks original UTC acceptance.

The 41 table digests match original SQL source column values only if stored MySQL TIMESTAMPs are *rendered at +05:00*; the original SQL dump explicitly exported those values in *+00:00*. This strongly establishes a **five-hour UTC instant offset for at least some original TIMESTAMP-bearing rows** in the existing isolated rehearsal clone. There is no evidence here that all 120165 rows or every TIMESTAMP field are affected. Zero other unexplained **table-level** mismatches were observed at the two tested sessions.

**Decision:** Retain the affected rehearsal as read-only diagnostic evidence; do not attempt in-place timestamp shifting. A new, separately named and owner-authorized empty V2 target plus the corrected +00 importer and independent source-UTC full-digest parity will be required for P4.G3. No approved production cutover.

### 2026-10-31 — UTC source mismatch mechanism confirmed; future isolated import guards + candidate acceptance runner

**Owner-verified at `6efaace6`:** pinned source digest, local V2 rehearsal unchanged and clean; `diagnose_mysql_source_timezone_parity.php --selftest` PASS, **41/57 `SOURCE_MATCHES_PLUS05_ONLY`**, **16/57 `SOURCE_MATCHES_BOTH_SESSIONS`**, **0 UTC-only**, **0 neither**. This is table-level parity evidence that original UTC-imported TIMESTAMP instants in the old V2 rehearsal differ from the source; it does **not** count changed rows. Earlier finance pilot **45 tests / 159 assertions PASS**; no new tests claimed yet.

**New real code:** `V2HistoricalImportTargetGuard` integrated into `import_staged_mysql.php` before MySQL access; rejects remote hosts, DB URLs, replica configs, unix sockets, prod/staging/dev/client names, environment mismatch and synthetic-real target crossover. PHPUnit regression covers **18 forbidden configurations**. The earlier importer pins source `+00:00` session on *future new imports*; existing clone is **not mutated**. New `verify_new_utc_rehearsal.ps1` is read-only and accepts only a **separately named** local V2 candidate, verifies original SHA and source UTC header, 57/57 full-row UTC digests and 57-table financial aggregates with no migrations/import; CI lints PHP, runs guard regressions and parses PowerShell.

**Formal gates:** 8/24 = 33.3% unchanged. Phase 4 P4.G3 is **BLOCKED** until new independently authorized, isolated import demonstrates source-UTC 57/57 and historical exceptions are signed off. P4.G4 and Phase 6 need source ownership/account parity and UAT. Operator must *not* write current rehearsal, V1, dev, staging or live; no database creation or import was performed this turn. New code needs independent operator/CI execution.
