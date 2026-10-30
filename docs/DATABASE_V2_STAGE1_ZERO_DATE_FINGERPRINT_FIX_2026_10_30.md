# Database V2 — Stage 1 source timestamp fingerprint correction (2026-10-30)

**Observed owner evidence:** `u429032065_dev.sql` private SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`; 57 tables, 120165 rows, 40+46 FKs, 14 migration-history rows; `STAGING_COMPLETE` succeeded without database writes. However `strict_mysql_zero_date_fields: {}` conflicted with the known historical single `leads.follow_up` zero TIMESTAMP.

## Root cause and safety fix

`tools/database_v2/financial_source_fingerprints.py` used raw regex `r'^(?:date|datetime|timestamp)\\\\b'`. Python treats double-backslash-b as **literal backslash+b**, so **no** legacy SQL timestamp/DATE column declarations matched. Changed to true word-boundary `r'^(?:date|datetime|timestamp)\\b'`. No user/customer data affected.

New synthetic fixture regression `test_sql_zero_timestamp_is_identified_in_real_column_not_lost` inserts a fake `leads.follow_up='0000-00-00 00:00:00'` and requires `{'leads': {'follow_up': 1}}`. `local_private_rehearsal.ps1` now validates the expected one-and-only-one source exception **at the end of Stage 1** and **again before any Stage 2 database connection/migration**. Stale source evidence fails closed.

## Safe owner repair: keep all 57 staging SQL parts

**Do not** rerun full Stage1 on the old directory (it correctly refuses to overwrite) and **do not** delete or modify any staging `*.sql` files. Git pull the fixed code, then execute:

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework

$SourceSql = "C:\Users\anwaa\Downloads\u429032065_dev.sql"
$StageDir = "$env:USERPROFILE\Documents\BMV2Private\staging_20261030"
$oldEvidence = Join-Path $StageDir "source_fingerprints.json"
$backupEvidence = Join-Path $StageDir "source_fingerprints_before_zero_date_fix.json"

if (!(Test-Path -LiteralPath $SourceSql)) { throw "Original private source SQL not found" }
if (!(Test-Path -LiteralPath $oldEvidence)) { throw "Existing private staging evidence missing" }
if (Test-Path -LiteralPath $backupEvidence) { throw "Refusing to overwrite an earlier backup" }

Rename-Item -LiteralPath $oldEvidence -NewName "source_fingerprints_before_zero_date_fix.json"
py -3 .\tools\database_v2\financial_source_fingerprints.py `
  --source "$SourceSql" `
  --expected-sha256 bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed `
  --expected-rows 120165 `
  --out "$oldEvidence"

$e = Get-Content -LiteralPath $oldEvidence -Raw | ConvertFrom-Json
$e.mysql8_zero_date_exceptions | ConvertTo-Json -Compress
```

**Required output:** `{"leads":{"follow_up":1}}`. If absent, stop and share sanitized command/error output only. **No database is touched during repair.** This is a strictly safer alternative to erasing or regenerating the 57 already verified SQL staging files.

Once correct, confirm local MySQL engine/version and owner creates a **NEW EMPTY** `bluemarketing_v2_local_rehearsal` database, never touches existing dev/staging/live database. Stage2 still requires explicit `-Apply -PreserveLegacyZeroDate` and exact double-confirmed target in the [local import runbook](DATABASE_V2_LOCAL_MYSQL_IMPORT_RUNBOOK_2026_10_30.md). No real historical MySQL import was executed through this fix.

**Progress remains 8/24 = 33%.** No post-import financial parity gate accepted merely by correcting a fingerprint.
