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

## 2026-10-26 — Phase 1G isolated CV/JV colliding-ID regression fixture
- User synced `87c81ab` successfully; Phase 1G proceeded automatically per workflow instruction.
- Added `tests/Feature/Finance/CommissionVoucherCollisionTest.php`: creates independent CV/JV headers sharing ID `41` and an existing JV ledger row, then calls CV update and asserts JV ledger row survives.
- Safety guard enforces `sqlite` + `:memory:` before dropping test tables. Fixture includes schema needed by `getVocuherNumber`. Test additionally checks redirect target to flag controller caught exceptions/rollback rather than falsely passing history check.
- **Unexecuted** by assistant: local PHP test result needed to verify fixture completeness; expected RED if legacy CV update commits destructive JV deletion. Run: `php artisan test --filter=CommissionVoucherCollisionTest`.
- No production DB, migrations, application controller changes, or `master_live` changes.

## 2026-10-26 — Phase 1G fixture syntax correction
- User pull to `466ed0c` succeeded; PHPUnit failed loading new `CommissionVoucherCollisionTest.php` at line 58 with PHP parse error before any test executed.
- Root cause: literal backslash-n sequences inserted into the PHP test fixture schema source, including lines 58 and 62.
- Fixed those escaped newline sequences in test file (commit `53d18a6f`). No application/database changes. Test behavior is **not yet verified**; rerun isolated collision test after pulling.

## 2026-10-26 — Phase 1G unsupported Request setup fixed
- User confirmed PHP syntax lint passes, but isolated collision test failed before controller execution: `Illuminate\\Http\\Request::setContainer` does not exist (0 assertions).
- Removed unsupported `setContainer()` and `setRedirector()` calls from `CommissionVoucherCollisionTest.php`; existing Laravel app request binding remains.
- Collision test **not yet re-run** after fix. Production / MySQL / application controllers untouched.

## 2026-10-26 — Phase 1G collision test reproduced (critical)
- User ran isolated `CommissionVoucherCollisionTest`: **1 failed, 3 assertions**, confirming CV update deletes unrelated JV ledger record with colliding header IDs (41).
- Original JV ledger `id=1,type=JV,type_id=41` disappeared. After CV update only `id=2,type=JV,type_id=1` remained, also demonstrating wrong commission detail-ID linkage.
- Evidence and safe remediation gates: [PHASE1G_COLLISION_CONFIRMED.md](PHASE1G_COLLISION_CONFIRMED.md).
- No app logic changes or production/DB writes. Next: finish tracing readers/writers and prepare safe isolated repair tests; no blind type conversion.

## 2026-10-26 — Phase 1H targeted ledger reference audit
- User successfully pulled Phase 1G report commit `29839d3`.
- Traced ledgers `type`/`type_id` usages in JV, CV, Ledger, Accounting and Booking flows plus helper numbering. Critical: LedgerController resolves JV-type entries to JournalVoucher by type_id; JournalVoucher update matches both header/detail IDs.
- See [PHASE1H_LEDGER_REFERENCE_AUDIT.md](PHASE1H_LEDGER_REFERENCE_AUDIT.md). Read-only partial coverage; no database/controller changes.

## 2026-10-26 — Phase 1I staging SQL ledger reference inventory
- User pulled Phase 1H docs (`480e544`) successfully.
- Parsed uploaded staging SQL export **read-only**: 17,850 ledger rows; 2,823 JV-type; 991 JV headers; 5,080 JV detail rows. CV header and detail tables exist, but **no INSERT records** found.
- Of JV-ledger references, 2,742 IDs exist in both JV header and detail tables; 81 exist only in details. Numeric overlap is ambiguous and NOT automatically corruption.
- Aggregate-only findings and caveats: [PHASE1I_STAGING_LEDGER_REFERENCE_INVENTORY.md](PHASE1I_STAGING_LEDGER_REFERENCE_INVENTORY.md). No personal row values committed. Next: isolated JV update collision test and reader audit.

## 2026-10-26 — Phase 1J JV header/detail ID collision regression added
- User successfully pulled Phase 1I docs (`751a6bc`).
- Added `tests/Feature/Finance/JournalVoucherCollisionTest.php`: independent JV headers `41` and `88`; detail ID `88` belongs to header `41`; ledger `type=JV,type_id=88` belongs to independent header `88`. The test invokes JV header `41` update and expects unrelated ledger row to survive.
- Fixture enforces SQLite `:memory:` before schema setup; **not executed** yet. A failing ledger assertion would demonstrate cross-voucher header/detail ID collision, while any earlier setup error must be fixed first.
- Run `php -l tests/Feature/Finance/JournalVoucherCollisionTest.php` and `php artisan test --filter=JournalVoucherCollisionTest` locally; no MySQL migrations, controller changes or production deployment.

## 2026-10-26 — Phase 1J redirect diagnostic gate
- User pulled `f245065` and PHP lint passed; test stopped on expected success redirect check (`http://localhost` rather than journal route): 1 failed, 2 assertions.
- **No verified cross-JV ledger loss yet**: test was blocked before ledger assertion. Updated test to check unrelated ledger preservation first, then diagnose redirect; rerun needed. An error redirect may indicate controller caught an exception and rolled back.
- No application controller / financial database changes.

## 2026-10-26 — Phase 1J second syntax correction
- User pull to `0c3d985` succeeded, but PHPUnit stopped before running a test: parse error line 112 (literal `\\n` before diagnostic string). This was an assistant-generated fixture error, **not an application or ledger result**.
- Replaced literal escaped newline with actual source newline in `JournalVoucherCollisionTest.php` (commit `87ef56fc`). Local PHP lint/retest required; no controller or database modifications.

## 2026-10-26 — Phase 1J ledger assertion passed, fixture incomplete
- User pulled `b9ee71d`; PHP lint passed. Test result 1 failed / 3 assertions: unrelated JV ledger row **remained present**; failure was only redirect destination `http://localhost` instead of journal index.
- Inspecting `JournalVoucherController::update` showed it writes `journal_vouchers.type='JV'`, but Phase 1J fixture omitted `type` column. SQLite would reject that write and roll back, explaining why ledger preservation cannot yet be interpreted as success. This is a likely cause pending rerun, not proven runtime trace.
- Added `type` column to isolated test schema in `JournalVoucherCollisionTest.php` (commit `edc28e7`). Rerun required before concluding cross-JV deletion. No app/controller or production DB changes.

## 2026-10-26 — Phase 1J genuine JV header/detail collision RED confirmed
- User pulled `43fe20e`, PHP lint passed, and ran `php artisan test --filter=JournalVoucherCollisionTest`.
- Result: **1 failed, 2 assertions**; unrelated JV ledger `id=1,type=JV,type_id=88` was deleted when JV header `41` updated and one of its detail IDs was `88`. Remaining ledger `id=2,type=JV,type_id=41` proves update proceeded rather than rolling back.
- This is a second independent, confirmed isolated SQLite cross-voucher ledger deletion bug (alongside CV update collision). **No claim of actual production loss.**
- Previous syntax and redirect failures were test fixture issues and are now distinct from this final real regression failure.
- Next: stop adding diagnostic red tests in a loop; consolidate a safe immutable-ledger repair plan, and add isolated fix only following design review. Never alter `master_live` or MySQL data without explicit approval.

## 2026-10-26 — Phase 1K consolidated financial safety blueprint
- User authorized continuing planned work after isolated JV/CV collision failures; no permission to modify production data or deploy.
- Added [PHASE1K_FINANCIAL_POSTING_SAFETY_BLUEPRINT.md](PHASE1K_FINANCIAL_POSTING_SAFETY_BLUEPRINT.md): tested failure evidence, fail-closed interim safeguards, typed source identity, append-only reversals, accounting invariants, required regression gates and explicit migration/deployment boundaries.
- Documentation only. Next step: controlled isolated fail-closed implementation with tests, preserving legacy rows and accounting reports.

## 2026-10-26 — Phase 1L temporary financial safety gate (code change on upgrade branch)
- Added unconditional HTTP 409 safety guard at the beginning of `JournalVoucherController::update`, `JournalVoucherController::destroy`, `CommissionVoucherController::update`, and `CommissionVoucherController::destroy`.
- This intentionally **disables all JV/CV edits/deletions, including drafts**, until source identity / append-only reversal architecture is ready. It does NOT add reversal features, fix historical rows, or alter store/create flows.
- Added 4 controller-entry regression tests in `tests/Feature/Finance/UnsafeVoucherActionGateTest.php`; **not yet run by local developer**. Run `php artisan test --filter=UnsafeVoucherActionGateTest` after pull.
- Existing intentionally RED collision/source tests still represent unsafe legacy code and may continue failing; the prior controller-execution collision tests may now fail because guarded actions throw 409. Do not claim entire suite green until regression expectations are updated.
- No migrations, MySQL imports/writes, deployment or `master_live` changes. Code only on `upgrade/laravel-framework`. Requires workflow/UI review before any deployment; the block impacts users.

## 2026-10-26 — Phase 1M safety gate verification and collision regression transition
- User executed `UnsafeVoucherActionGateTest` locally: **4 passed, 8 assertions**, proving direct JV/CV update/delete controller entrypoints currently reject with HTTP 409.
- Updated isolated `CommissionVoucherCollisionTest`, `JournalVoucherCollisionTest`, and two `FinancialHistoryPreservationTest` cases to assert HTTP 409 **and original ledger row remains present**. Previously confirmed destructive behavior remains documented in Phase 1G/1J reports and Git history; green protection tests DO NOT mean a permanent reversal fix exists.
- Historical `CommissionVoucherUpdateIsolationTest` source-contract guards remain intentionally RED because original unsafe statements are still present behind the temporary 409 gate. Full suite may still report these failures; do not advertise full suite passing.
- New transitioned tests **not yet executed**; recommended targeted run `php artisan test --filter='UnsafeVoucherActionGateTest|CommissionVoucherCollisionTest|JournalVoucherCollisionTest|FinancialHistoryPreservationTest'` in isolated testing environment.
- No migrations, production data changes or `master_live` changes.

## 2026-10-26 — Phase 1M isolated safety suite GREEN
- User pulled commit `9eff1c7` and ran isolated targeted suite: **8 passed, 16 assertions**, covering JV/CV four blocked update/delete entrypoints and four ledger-preservation scenarios.
- Passing tests demonstrate temporary fail-closed HTTP 409 protection, **not** restoration of normal voucher edit/delete, historical data correction or implementation of reversal posting.
- Four `CommissionVoucherUpdateIsolationTest` source tests still target unsafe statements located behind unconditional guards and are intentionally RED. Next: preserve this historical diagnostic evidence, introduce explicit behavioral guards, and run full suite to identify wider compatibility breaks. Do not claim full suite green.
- No production deployments, migrations or MySQL data changes.
