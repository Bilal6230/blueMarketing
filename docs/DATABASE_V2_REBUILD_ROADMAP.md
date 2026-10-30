# BlueMarketing Database V2 — Rebuild Roadmap & Progress Tracker

**Started:** 2026-10-30  
**Repository branch for planning:** `upgrade/laravel-framework`  
**Source evidence:** user-uploaded `u429032065_dev.sql` (private; do NOT commit raw SQL or personal/financial rows)  
**Target:** isolated Database V2, new migration baseline, faithful historical import, verified application refactor and reconciliation.  
**Current state (2026-10-30):** 57-table schema dictionary, isolated 70-table V2 schema, 116 physical FK-column references, and typed finance pilot verified. **Owner successfully imported all 120,165 private historical rows into isolated local MySQL 8.0.30**, with 57/57 table counts, original ID/timestamp boundaries and 34 source numeric aggregate totals passing. Full original-column row digests, 74 JV source exception reviews, V2 application refactor, financial parity and production UAT remain PENDING.

**LATEST SAFETY STATUS (2026-10-31): P4.G3 BLOCKED — 41/57 original tables match under +05:00 rendering but fail source UTC instant parity. Retain existing V2 rehearsal unchanged; corrected fresh UTC importer and isolated target guard are implemented.**

## Live progress bar (acceptance-gate based)

**Overall: 8 / 24 gates = 33%**  
`[████████░░░░░░░░░░░░░░░░]` **33%**

The percentage counts **verified completion gates**, not lines of code, elapsed time, engineering quality, or production readiness. Each of six phases has four equally weighted gates. Update this meter and the last verified evidence with every substantive completed milestone. Do not mark a gate done on code creation alone when tests/approval are required.

| Phase | Workstream | Gates passed | Progress | Status |
|---|---|---:|---:|---|
| 1 | Legacy SQL + repository dependency audit | **2 / 4** | **50%** | IN PROGRESS |
| 2 | Database V2 schema / migration design | **2 / 4** | **50%** | IN PROGRESS |
| 3 | Clean schema creation and constraint rehearsal | **3 / 4** | **75%** | IN PROGRESS |
| 4 | Faithful old-to-new data transfer | **1 / 4** | **25%** | IN PROGRESS |
| 5 | Full Laravel application refactor | 0 / 4 | 0% | IMPLEMENTATION PILOT (NO GATES ACCEPTED) |
| 6 | Tests, financial parity and controlled cutover | 0 / 4 | 0% | NOT STARTED |

### 2026-10-30 latest acceptance update — 8/24 = 33%

- **P2.G2 PASS (schema/design only):** canonical finance source claim + 7 typed source tables enforce exclusive voucher family under real MySQL 8.4 CHECK and composite FK; JV writer and typed report regression pass. Production service refactor/reversal remains open. [Source design and CI evidence](DATABASE_V2_REAL_DUMP_REHEARSAL_AND_TYPED_POSTING_2026_10_30.md).
- **P4.G2 PASS (import graph and fidelity staging only):** 57 original source tables and 86 FK edges form an acyclic dependency order; 120,165 real source rows were loaded into private ephemeral SQLite mirror and **all 120,165 rows matched source full-column multisets** (0/86 FK problems, 7 site source account mappings, 14 old migration history archived). Original SQL not uploaded or modified; this is not a full real MySQL imported snapshot.
- **Disposable MySQL 8.4 importer test:** deliberately wrong source FK rejected with full rollback; synthetic 57-table / two-record good import preserves date/ID/metadata; 70-table/116-FK V2 schema acceptance passed. [CI example](https://github.com/Bilal6230/blueMarketing/actions/runs/41098426140). Existing unrelated legacy finance regression suite still RED.
- **Writer risk index:** 360 app/route/database/Blade source files, 291 static access matches across 71 files, **39 dynamic references classified** in [risk register](DATABASE_V2_INDIRECT_LEDGER_ACCESS_CLASSIFICATION_2026_10_30.md). This is NOT complete P1.G3 semantic certification.
- **V2 reader pilot:** [V2PostingReportReader](../app/Services/Finance/V2PostingReportReader.php) returns exact typed header IDs and blocks cross-project / unclaimed / missing-typed-source reports; no existing production controller or report switched.
- **Still open:** source-specific old financial attribution decisions, P1.G3/G4, per-table delete/reversal policy sign-off P2.G3/G4, complete V2 app refactor, actual **120,165-row MySQL 8.4 import** and financial/project parity, UAT and cutover. NO user DB modified.

### 2026-10-30 accepted-gate evidence (newest)

- **P1.G2 accepted:** complete source DDL/PK/unique/FK metadata, 54 candidate disposition (46 real FK links + 8 business/polymorphic), 3 source-project mismatch families recorded without guess-based deletions.
- **P2.G1 accepted:** full 56-source-entity normalized Laravel schema (+ banks + legacy migration archive), verified in MySQL 8.4; tenant ownership exceptions deliberately documented, not silently coerced.
- **P3.G1/P3.G2/P3.G3 accepted:** V2-only explicit migration directory under existing upgrade branch; fresh disposable MySQL `bluemarketing_v2_ci` migration, 27-bank seeding, physical FK/unique/width/restrict checks, complete rollback and migration re-run. GitHub Actions: <https://github.com/Bilal6230/blueMarketing/actions/runs/41092458939> (V2 MySQL job SUCCESS; unrelated old finance regression FAILED).
- **Engineering sub-milestones M1–M5 delivered:** [five-milestone execution document](DATABASE_V2_FIVE_MILESTONE_EXECUTION_2026_10_30.md). No production or V1 database touched, historical 120,165-row import not executed. Overall acceptance **25%**, not 100% because code + schema tests do not prove full reconciliation or entire app refactor.

### 2026-10-30 — Actual owner-operated MySQL historical import: PASS (aggregate stage)

- **Confirmed from operator terminal:** `V2_IMPORT_APPLIED_TO_ISOLATED_DB_ONLY: 120165 source rows preserved` and `V2_FINANCIAL_PARITY_PASS: 57/57 historical tables, 120165 records, exact ID/timestamp bounds and 34 source numeric aggregate columns match`; final `V2_LOCAL_IMPORT_AND_AGGREGATE_PARITY_PASS`. Target was **local MySQL 8.0.30**, NOT GitHub MySQL 8.4 and not old V1/staging/live. Original SHA: `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`.
- Source `account_types` zero/one IDs and one `leads.follow_up` zero TIMESTAMP preserved under **connection-only** session policy; all 14 old Laravel migration history rows remain archived, originals not applied as new migrations. New typed finance source claims deliberately **unattributed**.
- **New next gate script:** `tools/database_v2/full_row_digests.py` + `verify_mysql_full_row_digests.php` check every original column of each of the 120,165 rows using canonical per-row SHA and duplicate-preserving multisets, without printing data. See [actual MySQL import evidence and next read-only commands](DATABASE_V2_REAL_MYSQL_IMPORT_SUCCESS_2026_10_30.md).
- **Formal gate count remains 8/24 = 33%**: P4.G3 remains open until the full original-column row and audit-history values pass; P4.G4/P6 explicitly require additional FK/account-level/financial leg reconciliation and source-ownership approval. Aggregate parity alone cannot establish every transaction's value survived unmodified.

### 2026-10-31 — full original-column row comparison: 54/57 PASS; 3 stock tables need diagnosis

Owner executed the new **read-only** MySQL full original-column SHA multiset verification after successful 120,165/120,165 import and 34 numeric aggregate parity. **54 source tables match exactly**, but the digests for `stock_entries` (1 source row), `stock_movements` (2 source rows) and `stock_parties` (3 source rows) differ. **Six total source rows are in the affected tables, not necessarily six changed rows.** Imported SQL remains untouched and historic aggregate checks still PASS. No automatic fixes or source re-import.

New private source column digest tool and read-only isolated MySQL comparator localize the discrepancy to `table.column` labels without any source values/PKs in logs. [Stock mismatch diagnostic and exact Windows commands](DATABASE_V2_STOCK_FULL_ROW_PARITY_DIAGNOSTIC_2026_10_31.md).

**P4.G3/P6.G1 remain OPEN and formal acceptance stays 8/24 = 33%** until proof of full source parity or explicitly reviewed, lossless exceptions. All 74 JV candidate-source discrepancies are independently unresolved.

### 2026-10-31 — STOCK ENUM source-label case regression identified and permanently fixed

- **Exact root cause:** V2 baseline previously lowercased the **whole SQL type token**, including quoted uppercase/mixed-case ENUM member names. The MySQL imported clone therefore returned `in/out` and `supplier/consumer/site` instead of original `IN/OUT` and `Supplier/Consumer/Site`. Owner's read-only column SHA comparison isolated precisely `stock_entries.flow`, `stock_movements.direction`, and `stock_parties.type`; all 39 other stock fields and remaining 54 table SHA comparisons MATCHED.
- **Future greenfield fix:** `database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php` now only lowercases the SQL **type identifier**, preserving quoted enum labels. Disposable MySQL8.4 CI asserts three exact `information_schema.COLUMNS.COLUMN_TYPE` values and rehearses case-only ENUM ALTER on a temporary table, verifying member ordinals unchanged.
- **Already-imported local V2 clone:** separate **read-only default** `tools/database_v2/restore_stock_enum_case.php` verifies exact local DB, approved source row hashes, 3 V2 migrations, 27 banks, zero posting rows, expected stock row counts and ENUM ordinals. `--apply` is explicit **DDL, not transactional**, and must be preceded by a private local backup and owner consent. No source row UPDATE or general data reimport. [Repair runbook](DATABASE_V2_STOCK_ENUM_CASE_REPAIR_2026_10_31.md).
- **No repair has yet been executed against the owner's MySQL 8.0.30 clone**. All-original-column SHA still 54/57, P4.G3 still OPEN, project formal accepted gates **8/24 = 33%**.

## Phase 1 — Audit the legacy database AND actual code

- [x] **P1.G1** Identify uploaded SQL snapshot; enumerate schema, counts, actual FKs, soft-delete fields, key-type risks and initial orphan checks. Evidence: [snapshot assessment](DATABASE_V2_LEGACY_SNAPSHOT_AUDIT_2026_10_30.md). **Completed 2026-10-30 (read-only).**
- [x] **P1.G2** Exhaustive 57-table physical relationship dictionary: 40 original FKs, 46 verified parent-source candidates, 8 deliberately unlinked identifiers, account width, cardinality/nullability/index data and project-exception evidence. **Completed 2026-10-30;** [relationship and tenant acceptance evidence](DATABASE_V2_57_TABLE_RELATIONSHIP_AND_TENANT_ACCEPTANCE_2026_10_30.md). Application authorization/business exceptions require separate P1.G3–G4.
- [ ] **P1.G3** Exhaustive repository audit of ALL CRUD/ledger writers and readers: models, controllers, services, commands, jobs, observers, APIs, views, helpers, SQL, imports, bookings, stock, labour, CR/CP/PPR/EC/ECP/JV/CV. Check explicitly against the named branch, not default-branch search.
- [ ] **P1.G4** Evidence-based owner-reviewed architecture issue register and signed-off mapping from each legacy column and source family to V2. Unknown historical ownership stays UNKNOWN.

**Acceptance:** never claim source identity from numeric `ledgers.type_id` alone; treat legacy business `reference` as nonunique unless proven; identify ambiguous/orphan historical records without rewriting them.

## Phase 2 — Design schema and import mappings

- [x] **P2.G1** Define full V2 physical schema entities, PK/FK widths/cardinality, nullable/project-link exceptions, original indexes/uniques, DECIMAL/status enums, original timestamp fields, and typed source pilot. **Completed 2026-10-30, verified on disposable MySQL 8.4.** Owner policy choices and full application mapping remain P2.G2–G4.
- [x] **P2.G2** Design and physically verify canonical source claims, exclusive typed source FKs, debit/credit lines, idempotency, and immutable reversal policy across seven voucher/source families. **2026-10-30: DESIGN/FK INVARIANT ACCEPTED**, disposable MySQL 8.4 70 tables / 116 FK-column references / 7 source-kind CHECK constraints; JV posting service atomic. Other family services and reversal implementation remain P5 gates. [Evidence](DATABASE_V2_REAL_DUMP_REHEARSAL_AND_TYPED_POSTING_2026_10_30.md).
- [ ] **P2.G3** Decide delete/void policy *per table* (SoftDeletes on appropriate masters, restrictive FK deletes for audited finance, dedicated reversal/correction entries); devise backward compatibility for old IDs/URLs/reports.
- [ ] **P2.G4** Review/approve the 57-table V1→V2 column map, database migration ordering, rejected-source quarantine, test plan, and exact constraint policy. Treat MariaDB 11.8.9 export vs MySQL 8.4.3 target compatibility explicitly.

**Acceptance:** migration spec, source-to-target mapping and FK dependency graph independently reviewed; no silent default/repair for missing historical values.

## Phase 3 — Build clean migrations in an isolated environment

- [x] **P3.G1** Create isolated opt-in V2-only migration set in `database/v2_migrations` on the existing `upgrade/laravel-framework` development branch, leaving default deployed `database/migrations` and all V1 migration history untouched. **Completed 2026-10-30.** (No separate feature branch was created.)
- [x] **P3.G2** Rehearse full fresh V2 migration set with `migrate --path=database/v2_migrations` against an **empty, disposable MySQL 8.4** database `bluemarketing_v2_ci` (NOT `migrate:fresh` against any existing database). **Completed 2026-10-30, GitHub Actions job passed.**
- [x] **P3.G3** Verify 70 tables / at least 116 physical FK-column references, correct broker/bank/ledger account link, BIGINT width, journal unique index, bank PK duplicate rejection, rejected invalid ledger account, restrictive parent delete and historical timestamp/money literals; perform complete V2 rollback + clean schema re-application in disposable MySQL 8.4. **Completed 2026-10-30, isolated CI MySQL job passed.**
- [ ] **P3.G4** Verify Laravel models, relationships, casts, unique constraints and migrations against clean schema and test database.

**Acceptance:** independent fresh database migration passes without disabling FK checks to conceal inconsistent relationships.

## Phase 4 — Preserve historical records and import safely

- [ ] **P4.G1** Immutable source snapshot checksum/backup, extract mapping, classify nulls/duplicates/orphans and decide exception policy.
- [x] **P4.G2** Verified deterministic 57-table parent-first V2 import order from 40 original + 46 added FK edges; graph is **acyclic**, so two-pass cycle resolution is **not applicable**. Original IDs preserved 1:1, old Laravel 14 migration rows isolated in legacy history. **2026-10-30: ACCEPTED** with 120,165 real rows in disposable SQLite mirror at 0 row/FK differences and MySQL 8.4 transactional synthetic good/bad import tests. This is NOT acceptance of real MySQL financial import. [Evidence](DATABASE_V2_REAL_DUMP_REHEARSAL_AND_TYPED_POSTING_2026_10_30.md).
- [ ] **P4.G3** Preserve existing `created_at`, `updated_at`, any `deleted_at`, amounts, references, voucher numbers, statuses, original timezone interpretation, and original audit history; do not use Eloquent auto-timestamps during import.
- [ ] **P4.G4** Reconcile counts, IDs, full column checksums/values, FKs, orphan exception register, financial leg totals and balances before accepting import.

**Acceptance:** no skipped/overwritten financial records; ambiguously owned historic rows are retained in review/quarantine with full source evidence, **not silently rewritten**.

## Phase 5 — Refactor the whole Laravel project to V2

- [ ] **P5.G1** Central financial posting API: transactions, row/project locking, idempotency, exact header/detail source, balanced multi-leg posting, reversal instead of destructive update.
- [ ] **P5.G2** Update JV/CV/CR/CP/PPR/EC/ECP and commission/source ownership, keeping safety 409 guards until replacement workflows are validated.
- [ ] **P5.G3** Update booking/sales, stock/purchases, labour/attendance, all APIs, exports/reports, permissions and background/raw SQL paths; avoid relying on model events for DB builder writes.
- [ ] **P5.G4** Confirm every read/write path uses V2 identity; run isolation, project ownership, concurrency, rollback, and duplicate posting regression suite.

**Acceptance:** no unreviewed writes by polymorphic numeric ID; destructive legacy paths disabled or replaced by auditable reversals.

## Phase 6 — Verify, reconcile and plan cutover

- [ ] **P6.G1** Automated V1/V2 row-level parity: original IDs, timestamps, status, balances, ledgers, vouchers, bookings, stock and labour.
- [ ] **P6.G2** Independent financial acceptance: document-level details↔ledger parity, debit/credit totals, account/project cash, missing/duplicate/ambiguous history, regression MySQL suite.
- [ ] **P6.G3** Full browser/API/report/UAT smoke testing and permission/performance verification on isolated V2 copy.
- [ ] **P6.G4** Approved backup+restore, repeatable import delta/cutover rehearsal, rollback procedure and explicit separate deployment authorization.

**Acceptance:** cutover does NOT happen solely because tests pass; require reviewed historical exceptions and explicit production approval.

## Safety / approval boundaries

1. **Never modify:** `u429032065_dev` live database, existing `bluenew` database, staging, production, existing migration history or `master_live` as part of planning/audit.
2. SQL upload is read-only; NEVER commit dump, raw customer information, credentials, transaction descriptions or unredacted evidence.
3. Do not auto-delete, deactivate, renumber, reclassify, regenerate, or merge historical financial rows. A missing JV ledger is **not** reconstructed solely from a numeric type_id or voucher number.
4. Actual FK must be backed by a real matching parent table/PK and matched type/signedness; adding `deleted_at` is *not* appropriate for every transactional/audit table.
5. Historical `migrations` rows describe that particular export, not proof that repository migrations match the database. A V2 greenfield migration baseline will be kept separate from existing production lineage.
6. All data imports/DDL only to isolated V2 targets after schema/mapping approval. Production/live changes require separate explicit approval.

## Key findings inherited from existing ledger audits

- Current `ledgers.type='JV'` can be genuine JV, legacy CV, or stock generated sequence. `type_id` is not a universal FK.
- `StockController` writes JV type with generated type_id and `STOCK-ENTRY:/STOCK-BILL:` references; `LabourController` creates JV details/ledger legs but direct labour JV code lacks optional per-detail provenance.
- Previous 20 approved local JV header repairs / 148 inserted rows do not establish full reconciliation. Historical JV #1012 / row #11822 original provenance remains unresolved; live/staging JV #113 history is unresolved.
- `LedgerSourceAttributionWriter` exists but currently only accepts genuine JV/commission typed source; inspect before extending it to stock.
- Existing `STAGING_MIGRATION_STRUCTURAL_EXPECTATIONS.md` documents previous migration drift; avoid repeating unsafe legacy migration replay.

## Update protocol

With each verified milestone: (a) update checkbox, (b) recalculate `completed / 24` and phase bars, (c) add dated evidence and the exact branch/commit, (d) update `BLUEMARKETING_PROGRESS_TRACKER.md`, (e) record open blockers and manual decisions. If verification fails, **do not** increment progress.

**Last update:** 2026-10-30. **8/24 = 33% accepted gates**; five linked engineering delivery milestones summarized in [execution report](DATABASE_V2_FIVE_MILESTONE_EXECUTION_2026_10_30.md). Historical row import not run; remaining 18/24 gates pending.

## 2026-10-30 implementation tranche: 4 substantial sub-milestones delivered (acceptance gates still open)

1. **App-wide source scan:** 189/189 `app/**/*.php` exact-branch files checked with 0 read errors and 96 direct ledger/raw-statement token hits; 12 indexed reports in `docs/database_v2_scan/APP_LEDGER_SCAN_01.md`–`12.md`. Canonical family matrix `docs/DATABASE_V2_LEDGER_WRITER_MATRIX_2026_10_30.md` now distinguishes true writers vs readers, JV vs CV/Stock/SV/EC/ECP source domains, mobile/finance dynamic builder updates and older recovery SQL. **P1.G3 not passed**: indirect aliases, routes, Blade, raw scripts, observers and full call graph not certified.
2. **V2 pilot migration implementation:** `database/v2_migrations/2026_10_30_000010_create_v2_bank_lookup.php`, `...000100_create_v2_finance_posting_core.php` and `database/seeders/V2LegacyBanksSeeder.php`. Real FK-typed Journal, Commission, Stock Entry, Stock Bill, Cash, Booking, Labour source mappings, posting lines/batches and exact JV detail links. All migration ops fail closed outside isolated `bluemarketing_v2_*` / SQLite in-memory. Finance core requires **11 pre-existing V2 baseline tables with BIGINT UNSIGNED IDs**, so it cannot silently run against V1 account INT PK. **P2/P3 gates not passed**: full 57-table greenfield baseline, exact-one typed source enforcement and MySQL 8.4 execution pending.
3. **Historical import planner code:** `tools/database_v2/legacy_import_plan.py` inspects source fingerprint and exported row counts, sorts 57 tables by declared FK parent dependencies, produces DATA-ONLY staging statements per table only with explicit checksum and out-of-repo location; no DB access. `tools/database_v2/tests/test_legacy_import_plan.py` adds synthetic fidelity, checksum failure, cyclic dependency and parent-first regression. **P4 gate not passed**: no final V2 mapping or actual database import; raw data-only export must never be run against application target without review.
4. **Isolated schema/unit tests + CI:** `tests/Feature/DatabaseV2IsolatedFinanceSchemaTest.php` covers FK reject, missing prerequisite, source linkage, and 27 bank IDs; existing `.github/workflows/laravel13-finance-safety.yml` now lints V2 PHP and runs synthetic Python/import + isolated Laravel tests before the older finance suite. **No MySQL 8.4 integration acceptance yet**. Historical existing finance CI failure must not be mislabeled V2 regression.

**Progress policy unchanged:** 1/24 (4%) accepted gates until full gate acceptance is independently evidenced; implementation sub-milestones above are valuable work but not deployment certification. No database connection or SQL writes were performed by ChatGPT and no finance posting/controller path was changed.

**Next priorities:** inspect CI V2 step results and fix, complete 54-column semantic dictionary and query-builder/route scan, build 57-table clean V2 baseline, enforce exact one source per posting, prove balanced/project tenant-safe batch service, rehearse MariaDB→MySQL 8.4 staging and lossless import on isolated database only. Current migration files are intentionally explicit-path only; never run `php artisan migrate:fresh` on an existing DB.


### CI-verified follow-on: isolated V2 journal writer

- New `app/Services/Finance/V2JournalPostingWriter.php`: atomic, strict same-project and account checks, persisted active-detail-to-posting-line mapping, balanced cents/header, request-key idempotency, and replay ledger integrity. Its V2 database-name gate and absence from production routes prevent this pilot from posting to V1. Tests include mutation rollback, duplicate-source uniqueness and tampered replay.
- New feature tests `tests/Feature/Finance/V2JournalPostingWriterTest.php`. **Verified isolated CI step SUCCESS** in GitHub Actions run [41088160648](https://github.com/Bilal6230/blueMarketing/actions/runs/41088160648), alongside V2 migration/seed tests and Python synthetic planner tests. **Overall CI FAIL** at unrelated existing finance suite. Do not claim entire application or MySQL 8.4 certified.
- Four implemented sub-milestones recorded above; **formal gate bar unchanged at 1/24 = 4%** because complete 57-table clean V2 baseline, full semantic raw/dynamic source inventory and historical import have not yet passed acceptance.


### 2026-10-30 local-import multi-milestone tranche — significant implementation, gates still 8/24 (33%)

- **Web/mobile mutation loopholes narrowed:** `FinancialPendingChangeGuard` and `VoucherController` / `MobileApprovalController` now reject posted ledger/customer monetary, source, account, tenant and date edits via approval; generic web finance approvals delegate to properly authorized/locked finance approval. Narrative-only changes remain. Added regression and unit tests.
- **V2 cash CR/CP pilot:** `V2CashPostingWriter` joins actual `customer_ledger.id` via typed `cash` source claim, checks exact source amount, project account ownership, two balanced legs, idempotency, replay and unique source. Five isolated tests. This is **not** an all-family posting refactor or deployment.
- **Private per-table imported-data evidence:** `financial_source_fingerprints.py` creates aggregate-only financial source evidence OUTSIDE Git; `verify_imported_mysql_financial.php` compares 57 tables' row counts, original ID bounds, audit timestamp extrema and Decimal numeric totals in read-only isolated MySQL. It is not full-column row/financial source-ownership certification.
- **Actual 120,165-source-row import requires owner-operated local environment:** `local_private_rehearsal.ps1` Stage1 (no DB) and Stage2 (manual `-Apply`, exact new empty local V2 DB/host confirmation, V2-only migrations, transactional 57-table MySQL import and aggregate reconciliation) with [Windows instructions](DATABASE_V2_LOCAL_MYSQL_IMPORT_RUNBOOK_2026_10_30.md). No direct MySQL/local DB access through GitHub connection.
- **Zero-TIMESTAMP source exception:** Original private source has exactly **one** `leads.follow_up` `0000-00-00 00:00:00`; other scanned unsigned/length/decimal violations zero. MySQL 8.4 strict may reject. The real import now requires owner-explicit `-PreserveLegacyZeroDate` and matching private SHA evidence, preserves exact raw value by relaxing NO_ZERO_DATE flags for the isolated **session only**, then restores session SQL_MODE. Zero-date semantic cleanup for production requires separate owner-approved mapping.
- [Five engineering milestone report](DATABASE_V2_MULTI_MILESTONE_EXECUTION_2026_10_30_LOCAL_IMPORT.md). **Remain honest: 8/24 = 33% formal accepted gates**, P1.G3, P4.G1/G3/G4, P5/P6 remain open until audited call graphs, actual private MySQL 120165-row import, full row and finance parity, approval and cutover. Latest CI status must be checked; old unrelated finance end-to-end job remains red.


### 2026-10-31 — Owner-verified five-sub-milestone local V2 parity checkpoint

- Source: owner terminal on `upgrade/laravel-framework` at `fe16de2d`, exact target `bluemarketing_v2_local_rehearsal` (local MySQL 8.0.30). `git status --short` was clean before documentation changes.
- Three stock ENUM definitions restored with `V2_ENUM_CASE_REPAIR_PASS` and preserved ordinals; original-column multiset verifier now `V2_FULL_ROW_PARITY_PASS: 57/57 tables, 120165/120165 rows`, including identical duplicate rows. Previous 54/57 remains *historical/pre-repair*, not current status.
- Independent `V2_FINANCIAL_PARITY_PASS`: 57/57 tables, 120165 records, ID/timestamp bounds and 34 numeric aggregate columns match; initial missing-arguments abort was an invocation error, not failed data parity.
- Five sub-milestones documented: ENUM restoration, original-column parity, aggregate parity, prior JV exception register classification, safe gate/next-work handoff. [Evidence and limits](DATABASE_V2_FIVE_MILESTONE_PARITY_CHECKPOINT_2026_10_31.md). **No claim that five formal phase gates passed.**
- **Acceptance remains 8/24 = 33%** until independent P4.G3 timezone/audit-history review and P4.G4/P6 JV source, project/account/financial-leg reconciliation. 74 candidate-mismatching JV headers and eight total mismatches are unresolved, not automatically remediated. No V1/staging/live edits or deployment; assistant did not run local SQL or CI.


### 2026-10-31 — Actual code tranche (not documentation-only)

- Added `tests/Feature/DatabaseV2SourceEnumFidelityTest.php`: four fixture cases preserve ENUM member case/order and escaped quotes, plus schema metadata contract for three original stock enums. Uses isolated Laravel test harness; never touches historical database.
- CI now includes new test file in PHP lint and isolated PHPUnit invocation; new `tools/database_v2/audit_local_jv_candidate_parity.php` is included in CI syntax lint. **CI and user-local tests not yet reported PASS; changes are implemented, not accepted gates.**
- Added guarded **read-only** local V2 aggregate JV candidate audit. It requires exact `bluemarketing_v2_local_rehearsal`, localhost, local APP_ENV, FK checks on and zero typed source claims; compares active JV details vs same-project JV-typed ledger candidate multisets and checks header/detail totals with integer cents, outputs only aggregate categories. Numeric `type_id` is **NOT** declared ownership; do not auto-post, modify or reassign any record.
- New development changes: `cb9109a0` (ENUM regression), `66eb6c5c` (CI test integration), `e4279141` (read-only JV candidate auditor), `19fbf2d7` (auditor CI lint). **Acceptance stays 8/24 (33%)** pending actual CI and local run plus per-gate review. V1/staging/live and existing isolated V2 data were not connected or changed by the assistant.


### 2026-10-31 — User local regression PASS; JV audit typed-bucket defect corrected

- Operator pulled `b2f9f40a`, confirmed clean tree and both new PHP syntax checks PASS. Isolated `DatabaseV2SourceEnumFidelityTest`, `DatabaseV2FullLegacyBaselineTest`, `DatabaseV2IsolatedFinanceSchemaTest`: **10 tests / 51 assertions PASS** (SQLite memory only).
- First operator run of isolated local MySQL JV candidate audit stopped with `V2_JV_CANDIDATE_AUDIT_REFUSED: TypeError`; **NO audit counts accepted**. Root cause in script: passing uninitialized nested PHP multiset bucket by typed `array &` to `addLeg`, where the missing offset resolves to null. Commit `c9e22c74` initializes both detail and ledger candidate buckets before reference writes. No DB mutation, financial correction or reclassification.
- Corrected audit must be pulled and rerun by operator, and its aggregate output reviewed before claiming P4.G4 or P6.G2 advancement. Formal phase gate remains **8/24 = 33%** until accepted evidence. Deployment blocked.


### 2026-10-31 — Local JV candidate audit executed; population discrepancy under investigation

- Operator pulled `cab73ead`, PHP lint PASS, clean tree, and ran exact local V2 READ-ONLY aggregate audit: `headers=980`, `candidate_exact=914`, `candidate_mismatch=66`, `no_active_details=0`, `header_detail_amount_mismatch=0`, `active_details_without_project_candidate=64`. This is **candidate matching only**, not verified typed source ownership.
- Prior offline 2026-10-30 source audit had 988 JV headers, 914 candidate matches, 74 mismatches, eight zero-active-detail headers and eight header/detail amount discrepancies. The new audit excludes `journal_vouchers.deleted_at IS NOT NULL`, likely a different population; the cause of the exact 8-header difference **is not yet proven**. Do not describe discrepancies as resolved or mutate historical data.
- Commit `15d25837` adds aggregate-only `all_jv_headers_including_deleted`, `soft_deleted_jv_headers_excluded` and partition invariants to the local read-only auditor. Requires operator rerun to confirm exact population boundary and examine the old eight separately if needed.
- Formal gates stay 8/24=33%, P4.G4 and P6.G2 blocked, production blocked.


### 2026-10-31 — JV population boundary independently confirmed by owner

- Owner pulled `faff8d2f`, PHP lint PASS, clean Git tree; exact local MySQL read-only auditor returned `headers=980`, `candidate_exact=914`, `candidate_mismatch=66`, `no_active_details=0`, `header_detail_amount_mismatch=0`, `active_details_without_project_candidate=64`, `all_jv_headers_including_deleted=988`, `soft_deleted_jv_headers_excluded=8`.
- **988 = 980 active + 8 soft-deleted**; the header-population difference is now proven. Prior offline `74` candidate mismatches vs `66` current active mismatches differs numerically by 8; whether each excluded header contributed to the previous mismatch set still requires matched-definition verification. Earlier eight header/detail-total discrepancies are not declared resolved: earlier and current audit populations differ.
- Numeric `ledgers.type_id` plus account/amount/project can establish a *candidate multiset*, never authoritative typed source ownership. Do not restore, relink, insert, reclassify or delete original financial rows. P4.G4/P6.G2 remain OPEN; accepted gates still **8/24 = 33%**. Deployment BLOCKED.


### 2026-10-31 — Actual implementation: JV exception partitions + deleted-header reconciliation

- New implementation commit `6f585b1d` extends isolated read-only `tools/database_v2/audit_local_jv_candidate_parity.php`: mutually exclusive active mismatch categories (missing candidate, partial/extra candidate legs, no active detail, null project) and an independent pass over all eight deleted headers (candidate match, no active details and header/detail total discrepancies).
- Existing 988 = 980 active + 8 deleted, 914 exact active candidate matches and 66 active candidate mismatches were previously operator-verified at `faff8d2f`. **New category counts are NOT YET verified** until operator executes updated script; no expected values fabricated. Source details for deleted headers now included in internal read-only comparison but only aggregate counts are printed.
- Explicit invariants require active mismatch categories sum to 66 (or the current measured mismatch total) and deleted candidate partitions sum to deleted header count. Source attribution from numeric `type_id` remains unverified; do not post/reassign historical finance rows.
- Gate disposition unchanged: 8/24 = 33%; P4.G4/P6.G2 pending detailed finance evidence, ownership and independently approved exception decisions. No production/staging/V1 database touched; CI/local tests for new code pending.



### 2026-10-31 — Owner-verified JV exception classifications PASS

- Owner pulled `a522e03c`; PHP lint PASS, clean Git tree. Exact local read-only auditor returned `headers=980`, `candidate_exact=914`, `candidate_mismatch=66`, `mismatch_no_candidate=64`, `mismatch_partial_or_extra_candidate=2`, `mismatch_no_active_details=0`, `mismatch_project_null=0`.
- The same read-only snapshot returned `all_jv_headers_including_deleted=988`, `soft_deleted_jv_headers_excluded=8`, `deleted_headers_exact_candidates=8`, `deleted_headers_mismatched_candidates=0`, `deleted_headers_without_active_details=8`, `deleted_headers_header_detail_amount_mismatch=8`. The earlier eight total discrepancies are accounted for by deleted-header population under the active-detail definition; not authorization to rewrite headers.
- Partition invariants passed. **64 absent same-project candidates are NOT 64 confirmed missing ledgers; 2 partial candidates are not proven source-owned.** Existing 914 matches are candidates only. Financial recovery/reclassification and typed claims remain blocked pending source proof.
- P4.G4/P6.G2 OPEN, acceptance 8/24 (33%), production deployment BLOCKED. Read-only V2 audit; original V1/staging/live untouched.


### 2026-10-31 — Actual V2 model↔schema compatibility implementation (P3.G4 partial)

- Delivered `app/Services/Finance/V2ModelSchemaContract.php`: an isolated V2/SQLite-memory-only, read-only schema inspector comparing Eloquent fillable fields against the physical table columns. It blocks V1/live/staging database names and refuses file-backed SQLite. It has **no runtime integration into existing V1 posting paths** and performs no historical DML.
- Added `tests/Feature/DatabaseV2ModelSchemaContractTest.php`: full 56-business-table V2 baseline on isolated SQLite memory, assertion that historical `journal_vouchers.site_id` and `ledgers.journal_voucher_detail_id` are legacy-model fillable fields not in imported V2 baseline, plus checks that detail/account model fillables have no schema drift and refusal against non-memory SQLite targets. These discrepancies are **discovered design blockers**, not authority to silently add V2 columns or rewrite historical data.
- GitHub Actions isolated finance-safety workflow now lints both new PHP files and executes the targeted test class with pre-existing V2 schema suites. Commits `d8d5026b`, `4e81b166`, `b9dc4a25`.
- **New tests have not yet been executed by the user or CI-confirmed**; P3.G4 remains open pending real MySQL model/relationship/cast integration checks and explicit policy on the two legacy fillable-only fields. Accepted gates still 8/24=33%. Production blocked.


### 2026-10-31 — P3.G4 substantive relationship contract implementation

- Owner verified previous isolated tests **12 PASS / 57 assertions** at `31400658`, PHP lint clean, Git tree clean.
- New implementation `66deb764`: `V2ModelSchemaContract::inspectRelationships()` inspects six real financial Eloquent relationships (JV→details, detail→JV/account, Ledger→account/customer source, account→ledgers) against physically generated V2 columns in isolated SQLite memory or named isolated V2 MySQL only. Does not invoke posting or query customer rows. Previously detected legacy fillable drift (`journal_vouchers.site_id`, `ledgers.journal_voucher_detail_id`) is still a policy blocker, not silently patched.
- New integration regression `5e3f1fa1`: constructs clean V2 bank+legacy baseline and asserts all six relationship FK/PK contracts are present. Existing CI already runs this test file. **New test execution is pending operator/CI; do not mark P3.G4 PASS yet.**
- Acceptance remains **8/24 (33%)**. P3.G4 still needs full model/cast coverage and real MySQL validation. No historical source rows edited; V1/staging/production and deployment untouched.


### 2026-10-31 — V2 cash writer security/integrity correction (actual functional code)

- Real business service changed: `V2CashPostingWriter::post()` now refuses malformed, decimal, signed, or lossy-coercion account identifiers instead of casting arbitrary input with `(int)` before the same-project lookup. This prevents e.g. `101junk` being silently treated as account 101. Invalid input is rejected before any posting batch is persisted.
- Replay now checks the canonical `finance_posting_source_claims.source_kind='cash'` and persisted voucher series matches the source's CR/CP type, in addition to prior source identity, SHA and leg multiset checks. This makes corrupted batch metadata fail closed, not silently replay.
- Regression added for 5 malformed account ID variations and mutated persisted batch series in `tests/Feature/Finance/V2CashPostingWriterTest.php`. Code commits `ca5cb80c`, `3f1da895`, `4fec605a`. **New tests not yet operator-run/CI-confirmed PASS**; do not increase formal gates on implementation alone.
- Baseline operator result remains 13 tests / 58 assertions PASS at `f5c27a16`, with clean working tree. P5.G1 still open: reversal, full concurrency and all-family services not implemented/accepted. Overall 8/24 (33%), deployment blocked. Production/V1/staging not touched.


### 2026-10-31 — V2 pilot financial hardening code pack (pending operator test)

- Strict V2-only connection guard, JV replay metadata/detail/narrative protection, cash replay persisted identity protection, read-only report balance/empty-batch checks, and dedicated regressions/CI wiring authored as a guarded local patch.
- Source files are only written by owner explicitly applying the package on clean `upgrade/laravel-framework` at `abe55741`. GitHub direct write was unavailable, so no remote commit or CI success is claimed.
- Operator must run isolated PHPUnit and check diff; formal phase gates remain **8/24 (33%)**. Outstanding JV typed attribution, derived column policy, reversal, concurrency, UAT, approval and deployment blockers unchanged.

### 2026-10-31 — V2 finance hardening operator regression PASS
Evidence ID: V2_HARDENING_32_87_LOCAL_PASS

- 32 focused Laravel tests PASS / 87 assertions.
- 6 isolated connection guard tests PASS.
- JV posting, idempotent replay, extra-leg detection, narrative verification and report integrity PASS.
- Cash CR/CP posting and replay integrity tests PASS.
- Model/schema compatibility tests PASS.
- PHP lint and git diff --check PASS.
- Executed against SQLite :memory: under APP_ENV=testing.
- Formal gates remain 8/24 until full acceptance requirements are satisfied.
- No V1, staging or live database writes authorized.

### 2026-10-31 — Three substantive V2 acceptance-target implementations (operator tests pending)

- **Git whitespace error fixed:** removed blank lines at EOF in four previously committed Markdown files; prior a0bc6c97 Git push succeeded. The LF→CRLF warnings were not build failures.
- **P5.G1 implementation:** transactional immutable `V2PostingReversalWriter`, exact `reversal_of_batch_id` FK, original typed-source proof, balanced opposite posting legs, source/metadata fingerprinted idempotency and tamper checks. V2 report reads reversal source *through original batch*, with is_reversal; six behavioural regression cases written. No V1 controller integration or history updates.
- **P3.G4 implementation:** three schema-aligned V2-only Eloquent posting models, seven relationship contracts and DECIMAL/date casts; isolated full-schema PHPUnit test; actual disposable MySQL `verify_mysql_v2_model_schema.php` checks model FK, money scale and unique indexes. Legacy fillable-only source columns require explicit compatibility decision, not automated schema changes.
- **P4.G3 implementation:** isolated MySQL READ-ONLY `audit_local_temporal_history.php` inventories original dated columns and archived 14 legacy migration history rows, reports aggregate and MySQL TZ metadata. The original snapshot export-session timezone is **not yet authenticated**. Existing source full-row digest PASS remains separate from historical timezone interpretation.
- CI lints new PHP tools/models/reversal, runs new SQLite regression, and checks model contracts against disposable MySQL 8.4. **New suite and MySQL audit have NOT yet been executed/confirmed by operator.** Roadmap acceptance remains **8/24 = 33%**; P3.G4, P4.G3, P5.G1 are still open. See [detailed checkpoint](DATABASE_V2_REVERSAL_TYPED_MODELS_AND_TIME_HISTORY_2026_10_31.md). No V1/staging/live DB change or deployment.

### 2026-10-31 — Reversal amount identity added

- `V2PostingReportReader` now verifies reversal legs are exactly account-wise inverted original posting legs, even for reversal-only date windows. It refuses balanced but financially incorrect reversal postings (`V2_REPORT_REVERSAL_NOT_EXACT_INVERSE`). `V2JournalPostingWriterTest` has the corresponding tamper test.
- New code and regression remain **operator/CI execution pending**, P5.G1 and formal 8/24 gates unchanged.

### 2026-10-31 — Owner verification PASS; source export timezone proof and cash reversal regression delivered

- Owner verified commit `587114d7` with **41 PASS / 139 assertions**, all new PHP lint PASS and clean working tree. Real local MySQL audit returned `V2_MYSQL_MODEL_CONTRACT_PASS`: 3 V2 typed models, 7 FK relationships, 2 exact DECIMAL casts, 2 posting uniqueness constraints. Independent read-only temporal history returned `V2_TEMPORAL_HISTORY_READ_ONLY_PASS`: 57 source tables, 142 original temporal columns (127 TIMESTAMP, 15 DATE), 320043 non-NULL values, 2158 soft-deleted rows, 14 migration archive rows, 0 new typed source claims. Local server TZ reported `Pakistan Standard Time`, which **does not establish original export-session TZ**.
- `git diff --check a0bc6c97 HEAD` failed **only** on two Markdown trailing whitespace lines. Commit `a0a630e9` fixed them; actual 41 tests and MySQL audits were green before that edit.
- Actual new code: private snapshot SHA-gated `prove_source_export_timezone.py` inspects explicit SQL export timezone directives (read-only, no source row output), plus cash CR/CP immutable reversal source-lineage, idempotency and cross-project negative tests in `V2CashPostingWriterTest.php`. CI executes independent synthetic timezone scanner selftest. This is **not** new 41-test approval: these additions remain unexecuted by operator/CI.
- **Gate status 8/24=33% unchanged**. P3.G4 still needs full legacy model compatibility, P4.G3 needs original export-session provenance + parity/acceptance, P5.G1 needs concurrency and controlled API integration. No V1, staging, live or original source database mutation.

### 2026-10-31 — Owner 44/44 PASS; original UTC export verified; new historical instant safety check implemented

- Operator: clean HEAD `55cec100`, PHP lint and `git diff --check` PASS, **44 focused tests / 156 assertions PASS**; SHA256 of original private `u429032065_dev.sql` matches `bbc45fe7...`; snapshot SQL explicit export session `+00:00` proved by `SOURCE_EXPORT_TIMEZONE_EXPLICIT_PASS`.
- Important semantic risk: the earlier local read of V2 MySQL used `SYSTEM` (`Pakistan Standard Time`). Since MySQL TIMESTAMPs store UTC instants but render through session timezone, a 57/57 full-row digest under `SYSTEM` may hide an import-time +05:00 shift. **No re-import or data repair authorized**.
- Implemented `verify_mysql_full_row_digests.php --verify-source-utc`: after original source fingerprint verification and isolated local DB guard, switches **only the verifier's session** to `+00:00`, then compares all 57 source-table original-column digests (120165 historical rows) and fails with table-name-only diagnostics on mismatch. Explicit PASS marker `V2_FULL_ROW_UTC_SEMANTIC_PARITY_PASS`.
- Added guarded one-command `check_source_utc_semantics.ps1`, disposable MySQL 8.4 TEMPORARY-TIMESTAMP conversion regression (CI), and backdated-reversal rejection + PHPUnit regression. [Exact evidence instructions](DATABASE_V2_SOURCE_UTC_INSTANT_AUDIT_2026_10_31.md). **New UTC comparison and reversal regression not yet owner/CI executed.**
- P4.G3 is now narrowly blocked on actual original-UTC row digest verification and full history exception acceptance; **formal gates unchanged: 8/24=33.3%.** P3.G4, P4.G4, P5.G1 and all production/UAT gates remain pending. V1/staging/live data untouched.

### 2026-10-31 — UTC import bug prevention and semantic reconciliation (actual implementation)

- Owner's source SQL SHA validated and `SOURCE_EXPORT_TIMEZONE_EXPLICIT_PASS: +00:00`; prior **44/44 tests, 156 assertions PASS**, source/timezone parser PASS and Git clean at `55cec100`.
- **Identified concrete import risk:** `import_staged_mysql.php` preserved source zero-ID SQL mode but did not set source export TIME_ZONE. MySQL TIMESTAMPs can therefore have correct textual values under Pakistan session with incorrect actual UTC instants. Existing imported `bluemarketing_v2_local_rehearsal` rows are **not changed**.
- Implemented opt-in read-only full-row UTC semantic digest validation `--verify-source-utc`; one-command guarded PowerShell orchestration; temporary MySQL 8.4 timezone conversion check. These will show affected source **table names only** if historical instants differ. [Runbook](DATABASE_V2_SOURCE_UTC_INSTANT_AUDIT_2026_10_31.md).
- **Future isolated importer corrected:** pinned source `SET SESSION time_zone='+00:00'` with restored session in `finally`; synthetic CI now imports with simulated +05:00 session and verifies source TIMESTAMP values at UTC. This applies to **new isolated imports only**; it does not retroactively repair existing records.
- V2 reversal chronology guard now rejects an effective date earlier than original batch, covered by new regression.
- **New code not yet independently executed by operator/CI; P4.G3 remains open.** No staging/live/V1 writes, guessed JV source claims or destructive history modifications. Formal accepted gates **8/24 = 33.3%**.

### 2026-10-31 — Critical imported UTC instant parity failure; diagnostic shipped (P4.G3 BLOCKED)

- Owner at `d2282d7b`: **45 tests / 159 assertions PASS**, original source SHA and `SET TIME_ZONE='+00:00'` confirmed, 120165 historic rows counted. **UTC full-row digest FAILED 41/57 original tables**; previously local `SYSTEM` display-time digest passed 57/57. Import target read only, clean git. **These are 41 differing table digests, not 41 changed rows**.
- Original pinned 57-table schema metadata cross-check: all 41 differing tables have a source `TIMESTAMP` column (102 TIMESTAMP columns across these tables); zero timestamp-free tables in mismatch list. Other 16 tables match at UTC; further column-level proof needed.
- Code fix: `verify_mysql_full_row_digests.php` EOF whitespace removed; added `diagnose_mysql_source_timezone_parity.php` with exact local target and source SHA guards, two-session (`+00:00`/`+05:00`) 57-table digest classification, read-only consistent transaction, no row contents/IDs; CI lints and runs selftest.
- Source/target historical repair NOT performed or authorized. P4.G3/P4.G4/P6 remain blocked; P5 regression pilot still 45 PASS. See [UTC incident and safe diagnostic](DATABASE_V2_UTC_PARITY_EXCEPTION_2026_10_31.md). **Formal acceptance remains 8/24 (33.3%)**; Phase 4 1/4 (25%).

### 2026-10-31 — UTC source mismatch mechanism confirmed; future isolated import guards + candidate acceptance runner

**Owner-verified at `6efaace6`:** pinned source digest, local V2 rehearsal unchanged and clean; `diagnose_mysql_source_timezone_parity.php --selftest` PASS, **41/57 `SOURCE_MATCHES_PLUS05_ONLY`**, **16/57 `SOURCE_MATCHES_BOTH_SESSIONS`**, **0 UTC-only**, **0 neither**. This is table-level parity evidence that original UTC-imported TIMESTAMP instants in the old V2 rehearsal differ from the source; it does **not** count changed rows. Earlier finance pilot **45 tests / 159 assertions PASS**; no new tests claimed yet.

**New real code:** `V2HistoricalImportTargetGuard` integrated into `import_staged_mysql.php` before MySQL access; rejects remote hosts, DB URLs, replica configs, unix sockets, prod/staging/dev/client names, environment mismatch and synthetic-real target crossover. PHPUnit regression covers **18 forbidden configurations**. The earlier importer pins source `+00:00` session on *future new imports*; existing clone is **not mutated**. New `verify_new_utc_rehearsal.ps1` is read-only and accepts only a **separately named** local V2 candidate, verifies original SHA and source UTC header, 57/57 full-row UTC digests and 57-table financial aggregates with no migrations/import; CI lints PHP, runs guard regressions and parses PowerShell.

**Formal gates:** 8/24 = 33.3% unchanged. Phase 4 P4.G3 is **BLOCKED** until new independently authorized, isolated import demonstrates source-UTC 57/57 and historical exceptions are signed off. P4.G4 and Phase 6 need source ownership/account parity and UAT. Operator must *not* write current rehearsal, V1, dev, staging or live; no database creation or import was performed this turn. New code needs independent operator/CI execution.

### 2026-10-31 — 48/48 finance safety PASS; UTC candidate acceptance and importer safeguards repaired

- Operator checked out clean `47251559`: **48 PASS / 179 assertions**, no PHP/PowerShell syntax failures or `git diff --check` errors, working tree clean. 3 import guard PHPUnit tests PASS, including 18 forbidden remote/production/replica cases. No new MySQL import has been authorized or executed.
- Code review exposed a real future-rehearsal acceptance defect: the new candidate runner compared all full-row hashes under source `+00:00` but verified financial timestamp bounds under local default timezone, risking false FAIL on a correctly imported database. **Fixed** `verify_imported_mysql_financial.php --verify-source-utc` with guarded local-only UTC session; for disposable CI fake data only, requires `--ci-synthetic` and an exact `bluemarketing_v2_ci` test target. CI good synthetic import now exercises this UTC financial-extrema path.
- `verify_new_utc_rehearsal.ps1` now read-only preflights target identity and runs BOTH full-row and financial checks in source UTC, not inconsistent session timezones.
- `local_private_rehearsal.ps1` no longer defaults to the **affected original local rehearsal database** or suggests reusing it. Explicit new target and matching name required; original shifted `bluemarketing_v2_local_rehearsal` is rejected by the importer service guard and PowerShell. Stage 1 now generates immutable private 57-table full-row digests; new candidate Stage 2 validates financial aggregates and full-row evidence under UTC. Stage 1 is file-only; Stage 2 still requires explicit `-Apply`, new empty isolated target, owner authorization and zero-date exception consent. **No automatic database creation or history repair.**
- Import guard negative regression expanded to **19 forbidden scenarios** (including old shifted rehearsal). New changes and CI synthetic checks are **implementation delivered, operator/CI execution pending**. P4.G3/P4.G4 remain OPEN/BLOCKED; **formal gate count 8/24 = 33.3%**. Existing V1, staging/live and original affected V2 clone untouched.

### 2026-10-31 — P3/P5 substantial V2 functionality delivered (no test execution per owner)

- **Central V2 posting gateway:** `V2PostingGateway` provides six typed operations (post JV, post Cash CR/CP, immutable reverse, posting lines, project trial balance, account statement). Uses existing isolated, locked/idempotent writers; not connected to legacy routes.
- **New real finance read functionality:** `V2ProjectTrialBalanceReader` implements exact-cent opening/period/closing balances; `V2AccountStatementReader` provides source-linked running account history and reversal lineage with project ownership check. No `ledgers.type_id` guessing; report scope is posted V2 lines only.
- **V2 physical-source model layer:** `ImportedHistoricalModel`, `ImportedJournalVoucher`, `ImportedJournalVoucherDetail`, `ImportedLedger`, `ImportedProjectAccount`. Eloquent queries guarded to isolated V2 and model instance writes forbidden; no V1 mutation. Explicitly address missing `journal_vouchers.site_id` and `ledgers.journal_voucher_detail_id` via physically aligned V2 models, not silent historical schema changes.
- Test cases **authored but NOT EXECUTED** per owner instruction. Implementation submeter moves: gateway `0/6 -> 6/6`; report shapes `1/3 -> 3/3`; known missing-source-column V2 adapters `0/2 -> 2/2`. See [delivery detail](DATABASE_V2_P3_P5_FUNCTIONAL_IMPLEMENTATION_2026_10_31.md).
- Formal acceptance **8/24 = 33.3% unchanged** pending testing, owner policy, real concurrency, production route refactor, finance reconciliation and P4.G3 UTC instant remedy. Original V1, staging/live and existing affected rehearsal untouched.

### 2026-10-31 — Multi-family V2 posting + guarded API integration IMPLEMENTED; tests deferred

- **7/7 physical source families now have V2 pilot writer code:** journal (existing), cash/CR/CP extended with strict expected PPR/EC/ECP, commission CV with exact source-detail FKs, booking, labour, stock entry and stock bill. All share real source FKs, project account checks, balanced debit/credit, transactions and idempotency. No historic `type_id` ownership guesses for new types.
- **Commission CV:** additive V2 migration creates `finance_commission_line_sources` with physical commission detail FK; its writer checks approved, balanced source-header totals, immutable source amounts, replay and narrative.
- **Booking/labour/stock:** `V2ApprovedSourcePostingWriter` checks original project/source evidence; stock lacks project_id and requires the new separate typed `finance_stock_*_project_approvals` FK tables. `V2StockSourceSnapshot` verifies stock line totals, rejects linked entry/bill duplicate posting, requires source SHA match. Approval operation requires both explicitly registered Gate ability and authenticated actor; none granted automatically.
- **Real integration code:** `V2PostingGateway` expanded 6 → 13 methods. `V2FinancePilotController`, `routes/api.php`, `config/finance.php` implement authenticated, project scoped, throttle-limited pilot endpoints for posting, reversal, source summary, trial balance, account statement and CSV exports. Both API and write switches are FALSE by default and route registration restricted to local/testing. Gate definitions must be explicitly registered; no V1 endpoint changed.
- **Reporting:** `V2PostingSummaryReader` groups typed voucher families and reversal postings; `V2FinanceCsvExporter` writes fixed-column CSV safely. Pilot V2 report formats move 3/4 → 4/4; CSV 0/2 → 2/2; gateway 6/13 → 13/13. New deferred regression class `V2MultiFamilyPostingTest` covers physical sources, replay, project and approval guards.
- **No tests or migrations manually executed per owner request.** Code completion percentages are pilot-only, NOT full production functional acceptance. P5.G1, P5.G2, P5.G3, P5.G4 all remain pending formal acceptance; complete legacy UI, mobile/other APIs, stock/booking/labour workflow refactor and concurrency testing are still open. P4.G3 UTC instant mismatch remains blocking. **8/24 accepted (33.3%) unchanged**.
- See [source-grounded delivery checkpoint](DATABASE_V2_MULTI_FAMILY_POSTING_DELIVERY_2026_10_31.md). V1, live, staging and existing local V2 rehearsal unchanged; no backfill or automated stock ownership assignment.
