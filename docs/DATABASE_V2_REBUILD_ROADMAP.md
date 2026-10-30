# BlueMarketing Database V2 — Rebuild Roadmap & Progress Tracker

**Started:** 2026-10-30  
**Repository branch for planning:** `upgrade/laravel-framework`  
**Source evidence:** user-uploaded `u429032065_dev.sql` (private; do NOT commit raw SQL or personal/financial rows)  
**Target:** isolated Database V2, new migration baseline, faithful historical import, verified application refactor and reconciliation.  
**Current state (2026-10-30):** 57-table schema dictionary, isolated 70-table V2 schema, 116 physical FK-column references, and typed finance pilot verified. **Owner successfully imported all 120,165 private historical rows into isolated local MySQL 8.0.30**, with 57/57 table counts, original ID/timestamp boundaries and 34 source numeric aggregate totals passing. Full original-column row digests, 74 JV source exception reviews, V2 application refactor, financial parity and production UAT remain PENDING.

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
| 5 | Full Laravel application refactor | 0 / 4 | 0% | NOT STARTED |
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
