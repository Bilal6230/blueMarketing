# BlueMarketing Database V2 — Rebuild Roadmap & Progress Tracker

**Started:** 2026-10-30  
**Repository branch for planning:** `upgrade/laravel-framework`  
**Source evidence:** user-uploaded `u429032065_dev.sql` (private; do NOT commit raw SQL or personal/financial rows)  
**Target:** isolated Database V2, new migration baseline, faithful historical import, verified application refactor and reconciliation.  
**Current state (2026-10-30):** 57-table schema dictionary + 46 proposed true-source FKs implemented, full isolated V2 Laravel migration baseline and typed finance pilot, successful disposable MySQL 8.4 migration/rollback, checksum-guarded historical import staging tool; historical data import, complete application refactor and production acceptance remain PENDING.

## Live progress bar (acceptance-gate based)

**Overall: 6 / 24 gates = 25%**  
`[█████░░░░░░░░░░░░░░░]` **25%**

The percentage counts **verified completion gates**, not lines of code, elapsed time, engineering quality, or production readiness. Each of six phases has four equally weighted gates. Update this meter and the last verified evidence with every substantive completed milestone. Do not mark a gate done on code creation alone when tests/approval are required.

| Phase | Workstream | Gates passed | Progress | Status |
|---|---|---:|---:|---|
| 1 | Legacy SQL + repository dependency audit | **2 / 4** | **50%** | IN PROGRESS |
| 2 | Database V2 schema / migration design | **1 / 4** | **25%** | IN PROGRESS |
| 3 | Clean schema creation and constraint rehearsal | **3 / 4** | **75%** | IN PROGRESS |
| 4 | Faithful old-to-new data transfer | 0 / 4 | 0% | NOT STARTED |
| 5 | Full Laravel application refactor | 0 / 4 | 0% | NOT STARTED |
| 6 | Tests, financial parity and controlled cutover | 0 / 4 | 0% | NOT STARTED |

### 2026-10-30 accepted-gate evidence (newest)

- **P1.G2 accepted:** complete source DDL/PK/unique/FK metadata, 54 candidate disposition (46 real FK links + 8 business/polymorphic), 3 source-project mismatch families recorded without guess-based deletions.
- **P2.G1 accepted:** full 56-source-entity normalized Laravel schema (+ banks + legacy migration archive), verified in MySQL 8.4; tenant ownership exceptions deliberately documented, not silently coerced.
- **P3.G1/P3.G2/P3.G3 accepted:** V2-only explicit migration directory under existing upgrade branch; fresh disposable MySQL `bluemarketing_v2_ci` migration, 27-bank seeding, physical FK/unique/width/restrict checks, complete rollback and migration re-run. GitHub Actions: <https://github.com/Bilal6230/blueMarketing/actions/runs/41092458939> (V2 MySQL job SUCCESS; unrelated old finance regression FAILED).
- **Engineering sub-milestones M1–M5 delivered:** [five-milestone execution document](DATABASE_V2_FIVE_MILESTONE_EXECUTION_2026_10_30.md). No production or V1 database touched, historical 120,165-row import not executed. Overall acceptance **25%**, not 100% because code + schema tests do not prove full reconciliation or entire app refactor.

## Phase 1 — Audit the legacy database AND actual code

- [x] **P1.G1** Identify uploaded SQL snapshot; enumerate schema, counts, actual FKs, soft-delete fields, key-type risks and initial orphan checks. Evidence: [snapshot assessment](DATABASE_V2_LEGACY_SNAPSHOT_AUDIT_2026_10_30.md). **Completed 2026-10-30 (read-only).**
- [x] **P1.G2** Exhaustive 57-table physical relationship dictionary: 40 original FKs, 46 verified parent-source candidates, 8 deliberately unlinked identifiers, account width, cardinality/nullability/index data and project-exception evidence. **Completed 2026-10-30;** [relationship and tenant acceptance evidence](DATABASE_V2_57_TABLE_RELATIONSHIP_AND_TENANT_ACCEPTANCE_2026_10_30.md). Application authorization/business exceptions require separate P1.G3–G4.
- [ ] **P1.G3** Exhaustive repository audit of ALL CRUD/ledger writers and readers: models, controllers, services, commands, jobs, observers, APIs, views, helpers, SQL, imports, bookings, stock, labour, CR/CP/PPR/EC/ECP/JV/CV. Check explicitly against the named branch, not default-branch search.
- [ ] **P1.G4** Evidence-based owner-reviewed architecture issue register and signed-off mapping from each legacy column and source family to V2. Unknown historical ownership stays UNKNOWN.

**Acceptance:** never claim source identity from numeric `ledgers.type_id` alone; treat legacy business `reference` as nonunique unless proven; identify ambiguous/orphan historical records without rewriting them.

## Phase 2 — Design schema and import mappings

- [x] **P2.G1** Define full V2 physical schema entities, PK/FK widths/cardinality, nullable/project-link exceptions, original indexes/uniques, DECIMAL/status enums, original timestamp fields, and typed source pilot. **Completed 2026-10-30, verified on disposable MySQL 8.4.** Owner policy choices and full application mapping remain P2.G2–G4.
- [ ] **P2.G2** Design ledger posting identity: immutable balanced posting batches, per-line ledger FKs, independently verified typed source relationships (actual DB-enforced source FK where possible), reversal and idempotency policy; distinguish journal, commission, stock, labour, booking, cash source families.
- [ ] **P2.G3** Decide delete/void policy *per table* (SoftDeletes on appropriate masters, restrictive FK deletes for audited finance, dedicated reversal/correction entries); devise backward compatibility for old IDs/URLs/reports.
- [ ] **P2.G4** Review/approve the 57-table V1→V2 column map, database migration ordering, rejected-source quarantine, test plan, and exact constraint policy. Treat MariaDB 11.8.9 export vs MySQL 8.4.3 target compatibility explicitly.

**Acceptance:** migration spec, source-to-target mapping and FK dependency graph independently reviewed; no silent default/repair for missing historical values.

## Phase 3 — Build clean migrations in an isolated environment

- [x] **P3.G1** Create isolated opt-in V2-only migration set in `database/v2_migrations` on the existing `upgrade/laravel-framework` development branch, leaving default deployed `database/migrations` and all V1 migration history untouched. **Completed 2026-10-30.** (No separate feature branch was created.)
- [x] **P3.G2** Rehearse full fresh V2 migration set with `migrate --path=database/v2_migrations` against an **empty, disposable MySQL 8.4** database `bluemarketing_v2_ci` (NOT `migrate:fresh` against any existing database). **Completed 2026-10-30, GitHub Actions job passed.**
- [x] **P3.G3** Verify 69 tables / at least 108 physical FK columns, correct broker/bank/ledger account link, BIGINT width, journal unique index, bank PK duplicate rejection, rejected invalid ledger account, restrictive parent delete and historical timestamp/money literals; perform complete V2 rollback + clean schema re-application in disposable MySQL 8.4. **Completed 2026-10-30, isolated CI MySQL job passed.**
- [ ] **P3.G4** Verify Laravel models, relationships, casts, unique constraints and migrations against clean schema and test database.

**Acceptance:** independent fresh database migration passes without disabling FK checks to conceal inconsistent relationships.

## Phase 4 — Preserve historical records and import safely

- [ ] **P4.G1** Immutable source snapshot checksum/backup, extract mapping, classify nulls/duplicates/orphans and decide exception policy.
- [ ] **P4.G2** Deterministic parent-first import order; two-pass deferred links for cycles, preserving original IDs where possible and maintaining explicit old→new IDs otherwise.
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

**Last update:** 2026-10-30. **6/24 = 25% accepted gates**; five linked engineering delivery milestones summarized in [execution report](DATABASE_V2_FIVE_MILESTONE_EXECUTION_2026_10_30.md). Historical row import not run; remaining 18/24 gates pending.

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
