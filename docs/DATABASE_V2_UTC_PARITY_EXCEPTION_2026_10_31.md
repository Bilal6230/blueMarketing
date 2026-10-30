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
