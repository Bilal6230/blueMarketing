# BlueMarketing Database V2 — Rebuild Roadmap & Progress Tracker

**Started:** 2026-10-30  
**Repository branch for planning:** `upgrade/laravel-framework`  
**Source evidence:** user-uploaded `u429032065_dev.sql` (private; do NOT commit raw SQL or personal/financial rows)  
**Target:** isolated Database V2, new migration baseline, faithful historical import, verified application refactor and reconciliation.  
**Current state:** PLAN + FIRST READ-ONLY SNAPSHOT CHECK; no V2 schema or application migration created.

## Live progress bar (acceptance-gate based)

**Overall: 1 / 24 gates = 4%**  
`[█░░░░░░░░░░░░░░░░░░░]` **4%**

The percentage counts **verified completion gates**, not lines of code, elapsed time, engineering quality, or production readiness. Each of six phases has four equally weighted gates. Update this meter and the last verified evidence with every substantive completed milestone. Do not mark a gate done on code creation alone when tests/approval are required.

| Phase | Workstream | Gates passed | Progress | Status |
|---|---|---:|---:|---|
| 1 | Legacy SQL + repository dependency audit | **1 / 4** | **25%** | IN PROGRESS |
| 2 | Database V2 schema / migration design | 0 / 4 | 0% | NOT STARTED |
| 3 | Clean schema creation and constraint rehearsal | 0 / 4 | 0% | NOT STARTED |
| 4 | Faithful old-to-new data transfer | 0 / 4 | 0% | NOT STARTED |
| 5 | Full Laravel application refactor | 0 / 4 | 0% | NOT STARTED |
| 6 | Tests, financial parity and controlled cutover | 0 / 4 | 0% | NOT STARTED |

## Phase 1 — Audit the legacy database AND actual code

- [x] **P1.G1** Identify uploaded SQL snapshot; enumerate schema, counts, actual FKs, soft-delete fields, key-type risks and initial orphan checks. Evidence: [snapshot assessment](DATABASE_V2_LEGACY_SNAPSHOT_AUDIT_2026_10_30.md). **Completed 2026-10-30 (read-only).**
- [ ] **P1.G2** Exhaustive 57-table relationship dictionary: actual FK, inferred application join, cardinality, nullable policy, key type, project ownership, delete semantics, expected parent, and orphan/duplicate evidence.
- [ ] **P1.G3** Exhaustive repository audit of ALL CRUD/ledger writers and readers: models, controllers, services, commands, jobs, observers, APIs, views, helpers, SQL, imports, bookings, stock, labour, CR/CP/PPR/EC/ECP/JV/CV. Check explicitly against the named branch, not default-branch search.
- [ ] **P1.G4** Evidence-based owner-reviewed architecture issue register and signed-off mapping from each legacy column and source family to V2. Unknown historical ownership stays UNKNOWN.

**Acceptance:** never claim source identity from numeric `ledgers.type_id` alone; treat legacy business `reference` as nonunique unless proven; identify ambiguous/orphan historical records without rewriting them.

## Phase 2 — Design schema and import mappings

- [ ] **P2.G1** Define entities, PK/FK cardinalities, project tenancy, required and optional links, indexes, decimal precision, status enums and normalized timestamp rules.
- [ ] **P2.G2** Design ledger posting identity: immutable balanced posting batches, per-line ledger FKs, independently verified typed source relationships (actual DB-enforced source FK where possible), reversal and idempotency policy; distinguish journal, commission, stock, labour, booking, cash source families.
- [ ] **P2.G3** Decide delete/void policy *per table* (SoftDeletes on appropriate masters, restrictive FK deletes for audited finance, dedicated reversal/correction entries); devise backward compatibility for old IDs/URLs/reports.
- [ ] **P2.G4** Review/approve the 57-table V1→V2 column map, database migration ordering, rejected-source quarantine, test plan, and exact constraint policy. Treat MariaDB 11.8.9 export vs MySQL 8.4.3 target compatibility explicitly.

**Acceptance:** migration spec, source-to-target mapping and FK dependency graph independently reviewed; no silent default/repair for missing historical values.

## Phase 3 — Build clean migrations in an isolated environment

- [ ] **P3.G1** Create V2 migration set on a dedicated feature branch, leaving existing deployed migrations/history untouched.
- [ ] **P3.G2** Rehearse fresh `migrate:fresh` only on a disposable V2 test database (NEVER on legacy/production).
- [ ] **P3.G3** Run MySQL 8.4 constraint/unique/key-width verification and rollback tests, including parent-child and history-preserving delete cases.
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

**Last update:** 2026-10-30, P1.G1 verified from uploaded SQL only. All remaining gates pending.
