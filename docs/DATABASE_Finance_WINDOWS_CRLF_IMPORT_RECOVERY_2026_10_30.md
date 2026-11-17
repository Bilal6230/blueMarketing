# Database V2 — Windows CRLF staging recovery after migration and bank seed (2026-10-30)

**Evidence from local operator:** On local **MySQL 8.0.30**, the standalone `bluemarketing_v2_local_rehearsal` database started with **0 tables** and passed exact local DB/hostname validation. The three V2 migrations **all completed** and `V2LegacyBanksSeeder` inserted/verified **27** fixed-source bank IDs. The importer then exited `V2_IMPORT_ABORTED: VALIDATION_OR_APPLICATION_REJECTED` **BEFORE** printing `V2_IMPORT_PREFLIGHT_OK`; no source INSERTs were begun by the importer. Never drop this database or re-run initial Stage2 migration/seeding sequence.

## Reproduced host-specific root cause

The Python V2 staging generator calculated the SHA256 checksum from canonical original SQL separated by **LF**. Its previous `Path.write_text()` used host text mode: Python on **Windows** transparently translated `\n` into **CRLF** in the staged `*.sql` bytes. The PHP importer compared the raw staged file body against the original LF manifest hash and rejected the **first staging file before the database import transaction**. This did NOT demonstrate the source customer data was modified or corrupted.

Independent reproduction from the source SQL in a disposable in-memory check: staged Windows raw-byte body SHA **does not match** original manifest; replacing only Windows CRLF with LF **exactly matches** the unchanged original manifest SHA. The existing 57 staged files remain **untouched and safe** in the user's private directory.

## Code fixes already committed

1. `tools/database_v2/import_staged_mysql.php` accepts either the exact raw staged SHA, **or** CRLF→LF restoration **ONLY IF** it reproduces the verified original manifest SHA. Rejects every other discrepancy. Parsing and executing use that checksum-proved canonical SQL string. Expected source data and manifest **are not changed**. Sanitized, hardcoded `V2_IMPORT_REFUSED` error reasons are now printed, but private SQL/values from database exceptions are **never printed**.
2. `tools/database_v2/v2_import_plan.py` now uses `open(newline="\\n")` so future Windows staging writes deterministic LF, independent of host.
3. `tools/database_v2/check_local_v2_target.php --require-prepared --manifest=...` verifies the exact `bluemarketing_v2_local_*` DB, `127.0.0.1` local host, `APP_ENV=local`, no DB_URL, exactly 70 expected schema tables, 3 framework V2 migrations, 27 seed bank IDs, FK checks enabled, 116+ physical FK columns, **all 57 historical target tables empty** and **all 11 new posting tables empty**. Read-only: no schema/data modification.
4. `tools/database_v2/local_private_rehearsal.ps1 -ResumePrepared` explicitly **skips V2 migrations and banks seeding**, performs the no-DB SQL staging preflight and prepared target checks before executing the existing guarded import and independent aggregate parity steps.
5. GitHub CI has a real MySQL 8.4 disposable job with a synthetic **Windows-CRLF staging** fixture; valid source hash must pass, then a deliberately tampered file must fail closed. Overall finance-safety workflow can stay RED due to unrelated old integration test failures.

## Safe owner commands — READ ONLY FIRST

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
git status --short

$StageDir = "$env:USERPROFILE\Documents\BMV2Private\staging_20261030"
$Sha = "bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed"

php tools/database_v2/import_staged_mysql.php `
  "--dir=$StageDir" `
  "--expected-sha256=$Sha" `
  "--expected-rows=120165" `
  "--source-evidence=$StageDir\source_fingerprints.json" `
  --preserve-legacy-zero-date

if ($LASTEXITCODE -ne 0) { throw "READ-ONLY staging SHA preflight failed; STOP" }
```

Expected `V2_IMPORT_PREFLIGHT_OK: 57 tables, 120165 rows` and `READ_ONLY_ONLY`. No database changes. Check the prepared DB **read-only** before any resumed import:

```powershell
powershell -NoProfile -Command {
  $env:APP_ENV = "local"
  $env:DB_CONNECTION = "mysql"
  $env:DB_HOST = "127.0.0.1"
  $env:DB_PORT = "3306"
  $env:DB_DATABASE = "bluemarketing_v2_local_rehearsal"
  $env:DB_URL = ""
  $env:DATABASE_URL = ""
  php tools/database_v2/check_local_v2_target.php `
    --expected-name=bluemarketing_v2_local_rehearsal `
    --require-prepared `
    "--manifest=$env:USERPROFILE\Documents\BMV2Private\staging_20261030\manifest.json"
  exit $LASTEXITCODE
}
```

Expected `SAFE_LOCAL_V2_PREPARED_TARGET_VERIFIED`. If anything fails, **STOP**; never use `migrate:fresh`, `DROP`, `TRUNCATE` or repeat seeding.

## After both checks pass: controlled recovery to same prepared DB

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\database_v2\local_private_rehearsal.ps1 `
  -SourceSql "C:\Users\anwaa\Downloads\u429032065_dev.sql" `
  -StageDir "$env:USERPROFILE\Documents\BMV2Private\staging_20261030" `
  -Apply -ResumePrepared -PreserveLegacyZeroDate `
  -Database "bluemarketing_v2_local_rehearsal" `
  -ConfirmEmptyDatabase "bluemarketing_v2_local_rehearsal"
```

**This is a real database write and must NOT be issued before BOTH read-only checks have passed.** The script rechecks source SQL SHA256 and source zero-date exception, physical target empty historical tables and all finance posting tables before its single-transaction import. A data constraint/query error must STOP; importer rolls back and never auto-retries. Keep the private 57 files and no real source/PII in GitHub/chat.

Formal acceptance remains 8/24 (33%) until the full 120,165-row MySQL historical import and independent financial parity genuinely pass.
