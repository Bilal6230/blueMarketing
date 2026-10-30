# BlueMarketing Database V2 — Private local import and reconciliation runbook (Windows/Laragon)

**Status:** Ready for **owner-operated isolated rehearsal**, not live/staging deployment. Source is the original private `u429032065_dev.sql`, SHA-256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`, **57 tables / 120,165 source rows**. **Never upload this SQL dump, generated staging SQL, credentials, customer data or local `.env` to GitHub.**

## Prerequisites

- Current Git branch `upgrade/laravel-framework`; clean git status; Composer dependencies installed; PHP 8.2+ with `pdo_mysql` and `bcmath`; Python 3 (`py -3` or `python`); Laragon MySQL/MariaDB available on localhost.
- The original, exact-SHA SQL file is on your Windows computer **outside** `C:\laragon\www\blueMarketing` (e.g. Downloads or a secure private folder). Merely having uploaded the file to ChatGPT does NOT put a copy on your local PC.
- **Source issue:** private read-only compatibility audit found exactly **one** historic zero TIMESTAMP in `leads.follow_up` (`0000-00-00 00:00:00`). Checked source unsigned negative IDs, legacy varchar length, decimal precision overflow: **zero** in those categories. The zero date is **not silently mapped to NULL**. If owner explicitly requests exact source retention, the isolated connection temporarily omits `NO_ZERO_DATE/NO_ZERO_IN_DATE` from **SESSION ONLY** `sql_mode`, restores the prior mode on success/failure, and verifies exactly one source zero date remained. This is NOT approval of zero dates for V2 production business logic. No global mode changes.
- Your old local `bluenew`, cloned `u429032065_dev`, `u429032065_staging_bluema`, live and production DBs MUST NOT be selected or modified.

## Stage 1 — READ-ONLY source staging (do this FIRST)

Run in Windows **PowerShell** from your Laravel project:

```powershell
cd C:\laragon\www\blueMarketing
git switch upgrade/laravel-framework
git pull --ff-only origin upgrade/laravel-framework
git status --short
py -3 --version
php -v

powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\database_v2\local_private_rehearsal.ps1 `
  -SourceSql "C:\Users\YOUR_WINDOWS_USER\Downloads\u429032065_dev.sql" `
  -StageDir "C:\BMV2Private\staging_20261030"
```

Replace `YOUR_WINDOWS_USER` with your actual Windows username and adjust the source path. The stage folder **must not exist yet** and must live outside Git. This command verifies the exact source SHA256 and creates 57 **private data-only SQL** staging parts plus a manifest and `source_fingerprints.json` in the separate private folder. It scans original source counts/IDs/timestamps/Decimal sums and prints a zero-date warning. **It does not connect to any database** and does not modify your source file.

**Expected evidence:** `STAGING_COMPLETE`, `57` source tables, `120165` rows, and exactly one `leads.follow_up` zero TIMESTAMP compatibility exception. Do not paste the staging SQL or financial fingerprint JSON back into a chat; only share sanitized command output / status.

## Stage 2 — Manually create a NEW empty local database

Open **local** phpMyAdmin (connected to your Laragon machine, NOT remote hosting), then execute only:

```sql
CREATE DATABASE bluemarketing_v2_local_rehearsal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

If this database name already exists, **STOP**. Do not `DROP`, truncate, overwrite, use `IF NOT EXISTS`, or run `migrate:fresh`. Tell us so we can choose another new name. This database must be **empty**, local and separate from every V1 DB.

## Stage 3 — Explicit isolated migration, import and parity check

Only after the Stage 1 checksum passes, the source issue is acknowledged and the empty new local DB exists:

```powershell
cd C:\laragon\www\blueMarketing

powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\database_v2\local_private_rehearsal.ps1 `
  -SourceSql "C:\Users\YOUR_WINDOWS_USER\Downloads\u429032065_dev.sql" `
  -StageDir "C:\BMV2Private\staging_20261030" `
  -Apply `
  -PreserveLegacyZeroDate `
  -Database "bluemarketing_v2_local_rehearsal" `
  -ConfirmEmptyDatabase "bluemarketing_v2_local_rehearsal"
```

The script **first checks** the actual Laravel DB connection is local `127.0.0.1` / `localhost`, has no `DB_URL`, has the exact chosen `bluemarketing_v2_local_*` name, `APP_ENV=local`, and contains **zero tables**. Only then it executes the three **V2-only** `database/v2_migrations/` migrations (NOT `database/migrations`), seeds the 27 original bank lookup IDs, runs the 57-part SHA256-enforced **transactional** import with FKs ON, and runs independent **read-only** per-table count, ID range, historical `created_at/updated_at/deleted_at` extrema and financial numeric total comparison.

**Expected terminal success:** `SAFE_LOCAL_V2_TARGET_VERIFIED`, `V2_IMPORT_APPLIED_TO_ISOLATED_DB_ONLY`, `V2_FINANCIAL_PARITY_PASS`, and `V2_LOCAL_IMPORT_AND_AGGREGATE_PARITY_PASS`.

**Engine note:** GitHub Actions certified V2 migration and **synthetic** data import on disposable **MySQL 8.4**. Laragon may run a different MySQL/MariaDB version. Report the version printed by the local target preflight. A successful local rehearsal does NOT by itself certify MySQL 8.4 with your real 120,165 rows or unresolved JV financial source ownership.

## What to send back

Share only the **sanitized PowerShell terminal summary**: SHA check status, staging pass/fail, server/version, V2-only migrations result, 57 table/120165 count, any count/ID/timestamp/amount mismatch labels, and zero-date compatibility outcome. **Never** paste passwords, the raw dump, staging SQL files, private fingerprint JSON, individual ledger entries or personal customer data.

If any step fails: **STOP. Do not rerun on a partially populated target**. Keep old databases untouched, keep the staged private evidence securely stored, and share only the sanitized error title. The script never auto-drops databases or retries.

## Separate remaining finance acceptance

The import preserves all source IDs/timestamps/money, but 74 general JV headers have unresolved *candidate* detail-vs-ledger source matches; 8 headers disagree with active detail totals. Typed source claims are intentionally NOT backfilled or forged. Follow `DATABASE_V2_HISTORICAL_JV_RECONCILIATION_2026_10_30.md` for owner-reviewed exception resolution before app-wide writer cutover.
