# Read-only Audit Worklog & Decision Register

**Updated:** 2026-10-26. Keep new entries dated, evidence-based and append-only where practical.

## 2026-10-26 — Documentation and scope established
- User requested comprehensive schema/migrations/FK/soft-delete and data-integrity audit, plus user activity tracking and new-device/IP/location-based OTP verification.
- User supplied full staging SQL dump in the chat; not to be uploaded to Git or embedded in this documentation.
- Confirmed local environment reports a large set of migrations Pending though corresponding business tables exist.
- Local checks reported missing landing-page builder fields, and user explicitly deferred running the corresponding migration.
- Cross-checked selected repo files: `app/Models/Ledger.php`, `CustomerLedger.php`, `Booking.php`, `JournalVoucher.php`, `User.php`, `Lead.php`, `Labour.php`, `StockItem.php`; finance/login controllers; several baseline migrations.
- Findings recorded in `DATABASE_ARCHITECTURE_PLAN.md` are **preliminary**; no complete per-table/per-row inspection yet, and no claims of integrity certification.
- Decision: READ-ONLY analysis first; no migration or code refactor without risk assessment and authorization. Preserve finance history and production.

## Backlog — not yet verified / not implemented
- [ ] Parse and validate uploaded SQL structure against each tracked migration.
- [ ] Table-by-table physical columns/indexes/constraints and source-of-truth mapping.
- [ ] Record-level orphan/duplicate/ledger and voucher reconciliations using non-mutating tools.
- [ ] Search all controllers, services, repositories, jobs, commands and raw SQL for write/delete and bypassed model events.
- [ ] Inventory currently missing/extra direct-database columns vs create migrations and patch migrations.
- [ ] Design and review all FKs, unique indexes, soft-delete exceptions and accounting reversals.
- [ ] Build clean schema on isolated DB; rehearse **data-only** transfer with preserved identifiers.
- [ ] Implement activity logs, device trust, OTP and tests after separate approval.
- [ ] Re-run automated tests and staging regression checks.
- [ ] Draft production deployment, backup, restore and rollback plan.

## Change tracking template
`YYYY-MM-DD | Area | Finding/Decision | Evidence (file/table/query) | Code commit | Tests | Remaining risk | Next task`

## Not authorized
- Production deploy/merge to `master_live`.
- Running any migration or modifying a real/staging database.
- Destroying or rewriting finance/accounting data.

## 2026-10-26 — Phase 1B reproducible snapshot scan
- Inspected attached SQL snapshot read-only (57 table definitions, 40 FK constraints, 19 CASCADE actions, 394 INSERT statements). No DB connection or mutation.
- Compared critical finance, booking, user and stock columns to selected migrations and models; verified structural drift and soft-delete inconsistency.
- Detailed observations and remaining work: [PHASE1B_SCHEMA_FINDINGS.md](PHASE1B_SCHEMA_FINDINGS.md).
- Next: complete all-table migration matrix and read-only data integrity checks. No production authorization.

## 2026-10-26 — Phase 1C targeted financial schema review
- Compared dump column inventories for twelve core business tables against nine original create-table migrations and selected model behavior.
- Distinguished original-creation-file drift from a *full cumulative* migration diff (still pending).
- Added [PHASE1C_FINANCIAL_SCHEMA_CHECKS.md](PHASE1C_FINANCIAL_SCHEMA_CHECKS.md), containing schema drift matrix and SELECT-only orphan/imbalance/duplicate diagnostics for isolated DB copy.
- No data-level checks yet executed; no repository application changes or database modifications.

## 2026-10-26 — Phase 1D application code audit
- Inspected write/delete paths in eleven controllers (finance, ledger, JV/CV, booking, stock, labour, users, leads, projects/plots).
- Verified destructive stock item/party cascading application deletes and JV/CV ledger deletion paths, CV update filtering JV-type postings, hard-delete risks in User/Labour controllers, and builder updates bypassing observers.
- Source references, risk levels and test gates: [PHASE1D_APPLICATION_WRITE_AUDIT.md](PHASE1D_APPLICATION_WRITE_AUDIT.md).
- No DB/data/code changes. Next: targeted regression tests and complete controller coverage before implementation.

## 2026-10-26 — Phase 1E first red finance safety tests
- Added `tests/Feature/Finance/FinancialHistoryPreservationTest.php` with two isolated SQLite regression tests protecting general-ledger rows on CV/JV voucher deletion.
- **Intentional RED phase:** current controller source hard-deletes `Ledger` rows; these expectations are designed to fail until a reviewed reversal/void design replaces destructive deletes. No PHP test runner is connected to the user's local machine; **not executed/verified yet**.
- Safety guard refuses database schema setup unless connection is in-memory SQLite, as also forced in `phpunit.xml`.
- To execute ONLY the new red tests on a verified local development copy: `php artisan test --filter=FinancialHistoryPreservationTest`. Expect failing assertions; do not use `--filter` to silently claim entire suite green.
- Next: capture test output, then plan CV update/JV type-isolation tests and Stock history regressions. No production changes authorized.

## 2026-10-26 — Phase 1E genuine RED results (user local execution)
- User ran `php artisan test --filter=FinancialHistoryPreservationTest` after fixing PDO SQLite and assertDatabaseHas test argument.
- Result: **2 failed / 2 assertions**, both because `ledgers` table became empty after `CommissionVoucherController::destroy` and `JournalVoucherController::destroy` in isolated SQLite.
- This verifies controller-level permanent ledger removal in these exercised scenarios; **does not prove any real production records were lost**. No production database involved.
- Corrected test assertion commit `88302244`. Tests are intentionally red until an approved financial void/reversal design is implemented.
- **Blocking decisions before code change:** whether historic JV/CV may be voided, how approved entries are reversed, ledger balances and report filtering, linked voucher detail retention, authorization/reason requirements, and old-data compatibility.
- Next: add CV update cross-type isolation tests, document void/reversal transaction invariants, implement in isolation only after review. Do not change `master_live` or run migrations.

## 2026-10-26 — Phase 1F CV update isolation guards
- Inspected `CommissionVoucherController::update`: deletes ledger rows filtered as `JV`, then creates new ledger entries with `type='JV'` even though this controller edits Commission Vouchers. Type/type_id may refer to detail identities: actual database-backed collision scenarios remain to be tested.
- Added `tests/Unit/Finance/CommissionVoucherUpdateIsolationTest.php` (two intentional RED source-contract guards: no JV-type deletion or creation by CV update).
- These are **static guards**, not integration tests and not yet executed. Run with `php artisan test --filter=CommissionVoucherUpdateIsolationTest` on local machine; failure is expected until reviewed implementation.
- No production DB, migrations or controller code changed. Next: use failure result to design isolated fixture-backed update regression and safe fix.

## 2026-10-26 — Phase 1F RED results and additional posting-identity checks
- User ran `php artisan test --filter=CommissionVoucherUpdateIsolationTest`: **2 failures, 8 assertions** (as intended) because CV update targets JV-type ledgers and writes JV-type entries. PHPunit XML deprecation warning is unrelated.
- Further inspected `CommissionVoucherController::store` versus `::update`: store creates ledger rows labeled `JV` with `type_id = CommisionVoucher->id`; update creates `JV` rows with `type_id = CommisionVoucherDetail->id`. This is an additional posting-identity inconsistency beyond the source-level tests.
- Added two more deliberately RED source guards for CV-store type labeling and CV-update type_id consistency. Four source guards total in `CommissionVoucherUpdateIsolationTest.php`. **New tests not run yet.**
- Do NOT blindly rename all old JV ledger rows to CV: historical IDs may collide and reports may rely on legacy encoding. First audit actual staged data and queries, design typed source identity with migration/reconciliation, and implement only in isolated environment.
- No production/mySQL changes, no controller fixes or migrations.

## 2026-10-26 — Phase 1F four RED tests verified; safety-design gate
- User confirmed fast-forward pull to `648f9d7`, then ran `php artisan test --filter=CommissionVoucherUpdateIsolationTest`.
- Results: **4 failed, 16 assertions**, matching intentional source guards. These are not integration proof of production data loss.
- Added [PHASE1F_COMMISSION_LEDGER_SAFETY_DESIGN.md](PHASE1F_COMMISSION_LEDGER_SAFETY_DESIGN.md): explicitly distinguishes store/update/destroy legacy type/type_id behavior; prescribes typed source identity, void/reversal, isolated collision fixture tests and legacy-data reconciliation before any controller changes.
- No app business logic, databases, migrations or `master_live` changes.
