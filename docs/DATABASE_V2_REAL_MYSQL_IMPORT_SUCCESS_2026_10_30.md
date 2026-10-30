# BlueMarketing Database V2 — Real 120,165-row imported-data milestone

**Date:** 2026-10-30. **Status:** **ACTUAL LOCAL MYSQL 8.0.30 IMPORT + AGGREGATE PARITY PASS** — full-column parity / application cutover separately pending. All evidence comes from the owner's terminal output. No private SQL, customer names, individual financial records or credentials are included in GitHub.

## Verified owner-operated result

- **Original private snapshot:** `u429032065_dev.sql`, SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`.
- **Target connection:** `127.0.0.1:3306`, **MySQL 8.0.30**, database `bluemarketing_v2_local_rehearsal` (strictly isolated local rehearsal). NOT MySQL 8.4 CI and NOT existing V1 dev/staging/live database.
- **Prepared-state preflight:** `SAFE_LOCAL_V2_PREPARED_TARGET_VERIFIED`: **70** schema tables, **3** V2 Laravel migrations, **27** exact source bank IDs, **57** empty historical target tables, **11** empty new posting tables, **116** physical FK columns. Full SQL staging SHA/count preflight PASS.
- **Owner ran:** `local_private_rehearsal.ps1 -Apply -ResumePrepared -PreserveLegacyZeroDate`. No migrations or bank seeding repeated. Existing restored original source `account_types.id=0` and `id=1` preserved by isolated **SESSION-only** `NO_AUTO_VALUE_ON_ZERO`. The exactly one `leads.follow_up` zero TIMESTAMP was retained under the separately authorized session-only SQL mode adjustment. Original session SQL mode restored at end.
- **Importer terminal:** `V2_IMPORT_APPLIED_TO_ISOLATED_DB_ONLY: 120165 source rows preserved; site verified derived column filled. Financial source claims remain UNATTRIBUTED.`
- **Independent source/target aggregate verifier terminal:** `V2_FINANCIAL_PARITY_PASS: 57/57 historical tables, 120165 records, exact ID/timestamp bounds and 34 source numeric aggregate columns match.` Final script marker: `V2_LOCAL_IMPORT_AND_AGGREGATE_PARITY_PASS`.
- **Original V1 databases:** never selected by the guarded tool; imported SQL source file untouched; no historic customer data committed to GitHub.

## Distinctions: what the PASS means, and what it does NOT

**Proved by output:** all 120,165 source row counts were loaded into the isolated local MySQL clone; all 57 table counts match, source ID bounds/count and historical timestamp extrema/non-null counts match, 34 original numeric-financial column aggregate sums match, and the validated new project-site-account derivation ran. All 57 original table names are represented, with legacy Laravel migration history stored separately.

**Not yet proved:** full original column-by-column *per-row* identity (different records can share counts/date bounds/money totals), valid account-level debit-credit posting totals, source-kind attribution across the 74 historically ambiguous JV voucher headers, permissions/routes/report rendering on V2, owner approval of the one historical zero timestamp, and production migration readiness. The typed `finance_posting_source_claims` links are **intentionally unpopulated** until provenance is evidence-based.

**No data repair, automatic re-posting, or financial source attribution was performed.**

## Next: full all-column row-multiset parity (READ ONLY)

New portable tools:

- `tools/database_v2/full_row_digests.py`: parses the original private source SQL outside the database, hashes **every original column of every row**, counting duplicates; normalizes only the SQL storage type's integer and DECIMAL representation. Writes a new PRIVATE fingerprint JSON file outside Git (digest-only, no rows).
- `tools/database_v2/verify_mysql_full_row_digests.php`: reads all 57 imported source tables using a **read-only** local-DB query; hashes every original column, compares sorted per-row SHA256 multisets against source evidence; reports only PASS or the **table names** whose hashes differ. Leaves the extra V2 derived `sites.project_head_subhead_id` outside the original-column comparison by design. Checks source SHA and local V2 database/host/app environment and FK-on.
- Cross-language canonical SHA test includes zero-ID, decimal scale, backslash text, NULL and duplicate-row differences; CI lint and portable Python tests.

### Owner commands — no migrations, no INSERT, no UPDATE

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework

$StageDir = "$env:USERPROFILE\Documents\BMV2Private\staging_20261030"
$SourceSql = "C:\Users\anwaa\Downloads\u429032065_dev.sql"
$Sha = "bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed"
py -3 .\tools\database_v2\full_row_digests.py `
  --source "$SourceSql" --out "$StageDir\source_full_row_digests.json" `
  --expected-sha256 "$Sha" --expected-rows 120165
if ($LASTEXITCODE -ne 0) { throw "Private source fingerprint failed; STOP" }

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

**Success:** `SOURCE_FULL_ROW_DIGESTS_READY`, then `V2_FULL_ROW_PARITY_PASS`. On `V2_FULL_ROW_PARITY_FAILED`, stop and share only the table-name difference labels; do not post original row data or automatically edit imported records.

## Gate accounting

**Formal acceptance remains 8/24 = 33%** pending full row-level parity and explicit timestamp/exception policy. If full original-column parity passes, evaluate **P4.G3** against its complete original-column/date/amount/audit-history criteria. **P4.G4 / P6.G1 / P6.G2 are NOT complete** until source exception review, independent FK/document/ledger totals, and typed JV ownership verification. Do not turn 57/57 aggregate PASS into 100% V2 readiness.
