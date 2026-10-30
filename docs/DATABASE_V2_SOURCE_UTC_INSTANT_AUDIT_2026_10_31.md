# Database V2 — source UTC TIMESTAMP instant acceptance checkpoint

**Date:** 2026-10-31
**Target:** `bluemarketing_v2_local_rehearsal` only, read-only source digests.
**Status:** Source timezone proof PASS, new UTC instant verification pending.

## Evidence already confirmed by owner

- Exact original private SQL source SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed` matched.
- `SOURCE_EXPORT_TIMEZONE_EXPLICIT_PASS` showed `+00:00` in the original snapshot header.
- Focused Laravel regression: **44 PASS / 156 assertions**, clean git and source-parser selftest PASS on `55cec100`.
- Isolated MySQL 8.0.30 read-only history inventory: 57 original tables, 127 source TIMESTAMP columns, 15 DATE columns, 320043 populated temporal values, 2158 original soft-deleted rows, 14 archived migration records.
- Local DB `@@session.time_zone=SYSTEM`, `@@system_time_zone=Pakistan Standard Time`. **This is not proof that the imported TIMESTAMP instants are identical to the UTC-authored source export.**

## Why previous 57/57 full row checksum may not suffice

MySQL `TIMESTAMP` is converted between UTC storage and session timezone. A source dump serialized with `SET TIME_ZONE='+00:00'` represents UTC wall-clock values. If it was inserted under Pakistan timezone, a later read under the same timezone can reproduce identical text *while representing an offset physical UTC instant*. `DATE` and `DATETIME` are not affected the same way.

The all-columns digest validator now has opt-in `--verify-source-utc` which applies **`SET SESSION time_zone='+00:00'` only to its verifier connection**. It then compares all 57 original source table row-multisets against the independently source-SHA-authorized digest evidence. No rows are modified, and no global SQL setting is changed. Disposable MySQL 8.4 CI gains a separate TEMPORARY TABLE regression proving the UTC/+05:00 translation.

## Operator's one-command fail-closed check

Provide the *actual private SQL path* and its previously generated *private full-row digest evidence*:

```powershell
cd C:\laragon\www\blueMarketing
powershell -NoProfile -File .\tools\database_v2\check_source_utc_semantics.ps1 `
  -SourceSql "$env:USERPROFILE\Downloads\u429032065_dev.sql" `
  -SourceEvidence "$env:USERPROFILE\Documents\BMV2Private\staging_20261030\source_full_row_digests.json"
```

Expected complete success **only if all 57 source tables agree when read at UTC**:

`SOURCE_EXPORT_TIMEZONE_EXPLICIT_PASS` followed by
`V2_FULL_ROW_UTC_SEMANTIC_PARITY_PASS: 57/57 tables, 120165/120165 rows` and
`V2_SOURCE_UTC_HISTORICAL_SEMANTICS_PASS`.

**On FAIL:** report only `TABLE_DIFF` table names; do not upload personal rows/SQL, set any server-wide timezone, alter existing data or reimport automatically. A FAIL demonstrates that the earlier default-session digest was insufficient to establish UTC original-instant preservation. Any remediation needs a new isolated disposable target, source checksum + explicit UTC import session, complete parity re-check, and separate owner authorization.

## Formal gate accounting

P4.G3 **remains OPEN** until UTC instant digest PASS, source audit-history preservation and any zero-timestamp exception review are explicitly accepted. P4.G4 and P6.G2 additionally need authoritative source ownership and financial-leg reconciliation. P3.G4 still requires legacy model exceptions; P5.G1 still requires concurrency and API workflow approval.

Formal project acceptance remains **8/24 (33.3%)**. No original, staging, live or client database modified.

## Future isolated import bug prevention (code implemented, CI pending)

Inspection of `tools/database_v2/import_staged_mysql.php` showed that its historic APPLY path set session `sql_mode` but **did not explicitly set the source dump's UTC timezone**. For TIMESTAMP columns, that can import the correct-looking wall-clock strings as incorrect UTC instants if the current MySQL session is in Pakistan time.

The importer now pins `SET SESSION time_zone='+00:00'` **before** its data-insert transaction, verifies the session variable, restores the previous session zone in `finally`, and still preserves the original source IDs, source zero TIMESTAMP and existing rollback semantics. The explicit source SHA is already required for private historical APPLY.

The isolated CI `--ci-simulated-timezone` test runs **only with synthetic source and `bluemarketing_v2_ci` on APP_ENV=testing**; it first simulates a local +05:00 session, executes importer with source UTC, checks restoration, then verifies actual `projects.created_at/updated_at` TIMESTAMP values while rendering at UTC. This is a new *future-import protection*, not a retroactive repair of the existing local rehearsal.

**No live/staging/V1 or already-imported local V2 rows were modified by this code change.** An existing historical import cannot be silently fixed by changing importer code; rerun the read-only UTC digest comparison first and seek explicit approval if it shows a mismatch.
