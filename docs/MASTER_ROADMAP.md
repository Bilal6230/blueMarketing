# BlueMarketing — Master Roadmap & Cross-Chat Handoff

**Last updated:** 2026-10-26 (Phase 1T actual DataTables collision verified)  
**Active Git branch:** `upgrade/laravel-framework`  
**Latest confirmed local verification:** `UnsafeVoucherActionGateTest` — **5 passed, 10 assertions** (user's local Laragon environment)  
**Purpose:** This is the **canonical project continuity file**. Read this first in every new chat, before planning or changing code. Maintain this file after every meaningful audit, code, test, data finding or deployment decision.  
**Status:** In-progress accounting integrity hardening; **not production approved**.

## 1. Goal and working style

We are progressively upgrading, auditing and safeguarding BlueMarketing, a Laravel business platform, without disrupting existing functionality or historical financial data. Current emphasis is financial ledger integrity, JV/CV voucher safety, schema audit, future centralized activity logging, and eventual scoped integrations.

User works on **Windows / Laragon**, project path `C:\laragon\www\blueMarketing`, and prefers concise step-by-step **Roman Urdu** instructions with exact PowerShell commands.

**Collaboration workflow:** Assistant edits/commits only to `upgrade/laravel-framework` via the GitHub connector; user pulls using `git pull --ff-only origin upgrade/laravel-framework`, runs specified local tests and shares output. When successful output is posted, **automatically continue with the next planned safe task in that same response**, without re-asking for “go ahead.” Do not merely congratulate. Record new commits and tests here.

## 2. Hard safety and approval boundaries

- **Never touch, merge, deploy or directly push to `master_live` without explicit user approval.**
- **Never run MySQL migrations, production SQL writes, deletions, imports, backfills, data repair or destructive operations without explicit approval.** A test suite must use isolated SQLite `:memory:` or explicitly authorized disposable DB.
- Preserve existing backups, historical accounting rows, uncommitted user files and other modules. Do not create unexpected Git branches.
- No assumption that a passed test means production data was repaired, a workflow is deployable or a business process is correct.
- Before shipping a voucher gate, advise that it **deliberately disables functions**; do not deploy as a silent “fix.”
- Staging SQL snapshots must never be treated as live production evidence; do not commit sensitive SQL dumps or customer details.
- Temporary fail-closed guards must not be removed until safer replacement passes isolated regression and reconciliation gates.

## 3. Quick status

| Area | Current condition |
|---|---|
| Framework | Laravel 13.38.0 / PHP 8.3 in upgrade effort (verify locally if relevant) |
| Git | `upgrade/laravel-framework`; latest verified user pull **`1daaccf`** |
| Full suite baseline | **233 passed, 4 failed, 1070 assertions** on 2026-10-26 at commit `6c782e7`; all 4 failures in `CommissionVoucherUpdateIsolationTest` |
| Latest targeted suite | **5 passed, 10 assertions**, `UnsafeVoucherActionGateTest` after pull of `1daaccf` |
| Previous finance protection suite | **8 passed, 16 assertions** (JV/CV collision and financial-preservation tests; before CV-store gate) |
| JV update and destroy | **HTTP 409 temporary fail-closed**, not functional |
| CV store, update and destroy | **HTTP 409 temporary fail-closed**, not functional |
| JV store | Not changed by safety gates; **must be audited**, not implicitly certified safe |
| Production and `master_live` | **Untouched by this audit work** |
| Reversal / immutable ledger system | **Not implemented** |
| Central activity logging | Planned; not yet implemented |
| PHPUnit XML deprecation notice | Warning; separate from accounting failures |

**Do not label the current suite fully green.** Four source-contract tests are still RED. Latest full suite run predates CV-store gate, so rerun full suite after any relevant code changes.

## 4. Confirmed bugs and evidence

1. **Commission Voucher update collision:** Updating CV header ID 41 deleted an unrelated Journal Voucher ledger with `type='JV',type_id=41`, then created a JV-type ledger linked to a CV detail ID. Confirmed in isolated SQLite `CommissionVoucherCollisionTest` **before temporary gate**. Under gate, protection assertions now pass.
2. **Journal Voucher update collision:** Updating JV header 41 with detail ID 88 deleted an unrelated JV header 88 ledger record and created new ledger under header 41. Confirmed in isolated SQLite `JournalVoucherCollisionTest` **before gate**. Under gate, protection assertions now pass.
3. **JV/CV destroy permanently deletes ledger rows** in original controller paths. Detected by `FinancialHistoryPreservationTest` before gate; current gates block destructive entrypoints.
4. **CV creation uses JV-type ledger postings** (`CommissionVoucherController::store`); source test remains RED. Mitigation added: block CV store with HTTP 409.
5. **CV update legacy code remains unsafe behind gate:** deletes JV-matching ledger rows, creates JV type, links ledger `type_id` to detail ID rather than CV header ID. Four source-contract checks remain RED by design until corrected.
6. **Legacy `JV` ledger references ambiguous:** independent header and detail numeric IDs overlap. From **staging snapshot**, among 2,823 `JV` ledger rows: 2,742 matched both JV header ID and detail ID; 81 detail-only; no evidence sufficient for safe global reclassification. **Not a production loss claim.**
7. Other historical data risks previously noted: stock deletion flows, user/labour hard-delete, booking-related history semantics. These remain broader audit work items.

**Critical distinction:** RED source tests expose dangerous code still present; current HTTP 409 tests verify mitigation, **not true ledger posting repair**.

## 5. Phases completed so far

| Phase | Summary | Evidence |
|---|---|---|
| Earlier audit | Git project status, schema inventory, financial writers, security/activity architecture | `docs/PROJECT_STATUS.md`, `docs/DATABASE_ARCHITECTURE_PLAN.md`, `docs/SECURITY_ACTIVITY_PLAN.md` |
| 1B–1D | Schema baseline and financial write audits | `docs/PHASE1B_SCHEMA_FINDINGS.md`, `docs/PHASE1C_FINANCIAL_SCHEMA_CHECKS.md`, `docs/PHASE1D_APPLICATION_WRITE_AUDIT.md` |
| 1F–1I | CV posting identity, proven collisions, staging JV inventory | `docs/PHASE1F_COMMISSION_LEDGER_SAFETY_DESIGN.md`, `docs/PHASE1G_COLLISION_CONFIRMED.md`, `docs/PHASE1H_LEDGER_REFERENCE_AUDIT.md`, `docs/PHASE1I_STAGING_LEDGER_REFERENCE_INVENTORY.md` |
| 1J | JV cross-header/detail collision reproduced | `tests/Feature/Finance/JournalVoucherCollisionTest.php` and `docs/AUDIT_WORKLOG.md` |
| 1K | Immutable ledger/reversal safety blueprint | `docs/PHASE1K_FINANCIAL_POSTING_SAFETY_BLUEPRINT.md` |
| 1L | Temporary JV/CV update and destroy HTTP 409 guards | `UnsafeVoucherActionGateTest`: initially 4/4 |
| 1M | Collision/preservation tests transitioned to guarded behavior | 8/8, 16 assertions |
| 1N | Full suite compatibility audit and CV store guard | 233 passed/4 source RED; CV-store guard 5/5 test pass |
| **1O** | **Master roadmap and cross-chat handoff introduced** | This file |
| **1P** | JV creation risk and actual route surface read-only inspection | `docs/PHASE1P_VOUCHER_CREATION_ROUTE_AUDIT.md` |
| **1Q** | Real registered URL safety and permission-declaration tests | **2 passed, 17 assertions** locally |
| **1R** | Limited financial report reader mapping | `docs/PHASE1R_LEDGER_REPORT_READER_MAP.md` |
| **1S** | Isolated voucher display ID collision diagnostic (deliberately RED) | `tests/Feature/Finance/LedgerVoucherDisplayCollisionTest.php` |
| **1T** | Actual LedgerController DataTables endpoint regression (unrun) | `tests/Feature/Finance/LedgerVoucherDisplayEndpointCollisionTest.php` |

**Committed worklog:** `docs/AUDIT_WORKLOG.md`. Continue updating both the worklog (chronological facts) and this roadmap (current state and future plan).

## 6. Next actionable engineering tasks — ordered

### Next immediately: Phase 1P — complete safety verification, then design repair

1. User pulls this roadmap commit; run full suite `php artisan test` **after CV-store gate** and record exact pass/fail counts. Expect four known RED source-contract failures; inspect anything else separately.
2. Audit `JournalVoucherController::store` and any additional call paths which create `JV` ledger rows; check voucher numbering, authorization, project scoping, atomic transactions, idempotency, balancing; use read-only source inspection first.
3. Review all places reading `ledgers.type/type_id` and voucher report generation before selecting a correction identity. Write a source-to-report map in `docs/`.
4. Ensure temporary guards are enforced on **all real routes** (HTTP feature tests including auth/permissions), not only direct controller calls.
5. **Keep four intentional source RED checks visible**; don't skip/delete/change expected outcomes to manufacture full green.
6. After code-review of design, implement narrow, testable fail-closed/append-only safe flows; preserve posted history, do not rewrite existing ledger identities blindly.

### Medium term

- Add additive typed voucher reference, posting batch, reversal relationships and actor audit (subject to explicit migration approval).
- Implement immutable balanced postings, linked reversals, idempotence, transaction locks, report reconciliation, permission checks.
- Trial on disposable restored staging snapshot with separately authorized setup; compare account/project balances and reports; classify ambiguous legacy `JV` rows without bulk conversion.
- Roll out controlled safe workflows only after sign-off, backup/restore verification and test gates.
- Continue broader destructive write audit and centralized user activity log (who/when/IP/device/authorization), security controls, then peripheral modules.

## 7. Key file map

- `app/Http/Controllers/CommissionVoucherController.php`
- `app/Http/Controllers/JournalVoucherController.php`
- `tests/Unit/Finance/CommissionVoucherUpdateIsolationTest.php` — **four known source-level RED**.
- `tests/Feature/Finance/UnsafeVoucherActionGateTest.php` — **five known GREEN**.
- `tests/Feature/Finance/CommissionVoucherCollisionTest.php`
- `tests/Feature/Finance/JournalVoucherCollisionTest.php`
- `tests/Feature/Finance/FinancialHistoryPreservationTest.php`
- `docs/AUDIT_WORKLOG.md` — chronological events, exact commits/verification.
- `docs/PHASE1K_FINANCIAL_POSTING_SAFETY_BLUEPRINT.md` — approved-for-planning design, not deployment clearance.

## 8. Cross-chat continuation protocol

**In any NEW ChatGPT conversation in BlueMarketing project**, send:

> `BlueMarketing ko docs/MASTER_ROADMAP.md aur docs/AUDIT_WORKLOG.md ke mutabiq continue karo. Active branch upgrade/laravel-framework hai. Latest roadmap aur worklog GitHub se read kar lo, phir next pending safe step automatically implement karo. master_live / production DB ko touch nahi karna.`

Assistant must:
1. Retrieve `docs/MASTER_ROADMAP.md`, then `docs/AUDIT_WORKLOG.md` and relevant code from GitHub **before making changes**; follow the most recent authoritative state, not recollection.
2. Check current Git commit/branch and user's latest test output; clearly distinguish *locally run* from *not yet executed*.
3. Continue next safe read-only/code/test/docs task; commit only onto `upgrade/laravel-framework`, state commit hash, give exact PowerShell verification commands.
4. After **every meaningful committed change**, update **both** `docs/MASTER_ROADMAP.md` and `docs/AUDIT_WORKLOG.md` in the **same task**. Update date, completed phase, changed paths, verified/unverified results, new risks, next step and explicit deployment restrictions.
5. Never alter `master_live`, production DB or financial history without specific approval. **Do not run destructive SQL** without consent. Preserve uncommitted developer work.
6. If prior roadmap is stale or conflicts with source/test output, reconcile evidence and record corrections before proceeding.

## Phase 1P — latest read-only audit

- Added `docs/PHASE1P_VOUCHER_CREATION_ROUTE_AUDIT.md`. JV `store` is active and has source-level gaps involving array validation, subaccount project scoping, monetary precision, voucher-number concurrency, idempotency and explicit transaction exception rollback. These are **potential risks requiring isolated proof**, not yet reproduced.
- CV `store` and both JV/CV update/delete entrypoints remain HTTP 409 guarded. Actual HTTP-route and middleware integration is still untested by this phase; direct tests alone are insufficient.
- Next: add isolated HTTP-route access/409 tests and inspect ledger report readers, keeping database untouched and four historic source RED tests intact.
- Phase 1P was documentation/source inspection only. No controller, migrations, deployed environment or database changed.

## Phase 1Q — actual HTTP route gate tests added

- User pulled Phase 1P commit `6632e9e` successfully.
- Added `tests/Feature/Finance/VoucherSafetyHttpRouteTest.php`: tests registered URLs for CV store/update/delete and JV update/delete return HTTP 409 **with middleware disabled** to isolate controller actions; separately checks expected route-level permission middleware declarations for these routes and JV store.
- **Not yet run by user**; next local command: `php artisan test --filter=VoucherSafetyHttpRouteTest`.
- Limitation: this does **not** verify actual authentication/permission enforcement end-to-end because the mutation test bypasses middleware. A future isolated authenticated fixture is needed for that. Do not claim security integration fully tested.
- Full suite still last verified at 233 passed / 4 intentional source-level failures, prior to this new test.
- Next after local verification: read-only inventory of ledger report readers and voucher source identity; then authentication/authorization fixture and JV-create integrity tests.

## Phase 1R — ledger report source mapping and next gate

- Phase 1Q user verification **2 passed, 17 assertions** after pull of `d048614`. These route tests bypass middleware for action checks; authenticated role/permission behavior still requires testing.
- Added `docs/PHASE1R_LEDGER_REPORT_READER_MAP.md`. In `LedgerController` the DataTables `display_voucher_number` reader resolves **every `JV` ledger `type_id` as a Journal Voucher header** and may overwrite its displayed voucher number. Since historic CV creation also posted `JV` ledger rows, colliding IDs create a **potential display misattribution** (not yet proven with isolated test or production data).
- No ledger type conversion, changes to reports, migrations or production data.
- **Next action:** isolated read-only display-number collision regression, then broader helper/Blade/report reader inventory and end-to-end auth fixture. Keep original source RED evidence.
- Full suite last 233 passed / 4 RED and was run before the latest CV-store guard; latest Phase 1Q route suite verified separately.

## Phase 1S — isolated display-collision diagnostic (execution pending)

- User pulled Phase 1R commit `f9dab903` successfully.
- Added `tests/Feature/Finance/LedgerVoucherDisplayCollisionTest.php`, a **deliberately RED** SQLite `:memory:` illustration of the exact `LedgerController::fetch_data_url` voucher-number lookup with colliding IDs. Example: ledger's original voucher number 7001 is overridden by unrelated JV header number 9001 because `type='JV',type_id=41`.
- **Scope limitation:** the test mirrors the reader logic using isolated SQL; it **does not call the actual DataTables endpoint**, prove actual CV provenance, or establish a production mismatch. Preserve as diagnostic until provenance-based policy and real endpoint integration tests are designed.
- Next verification: `php artisan test --filter=LedgerVoucherDisplayCollisionTest` (expected 1 intentional failure at voucher display assertion). Do not confuse this with a fresh application runtime error.
- Historical source-contract RED tests still present; do not make full suite green by skipping genuine risk tests. No controller/DB migrations changed in this phase.

## Phase 1S verification and Phase 1T next plan

- User successfully pulled `5771196`; `php -l tests/Feature/Finance/LedgerVoucherDisplayCollisionTest.php` passed.
- Locally ran `LedgerVoucherDisplayCollisionTest`: **1 failed / 1 assertion, as intentionally designed**. Expected `JV-7001`, actual `JV-9001`. This validates the **mirrored report lookup** under the test's constructed colliding IDs, not live data or actual DataTables endpoint behavior.
- Do **not** simply use stored number instead of looked-up number throughout: authentic JV ledger numbers and historical reports may depend on the override. Ledger source provenance must be resolved first.
- **Phase 1T next:** add isolated actual `LedgerController::fetch_data_url` DataTables endpoint test for overlapping numeric identifiers and trustworthy header source; protect existing historical display behavior. Review authorization/project scoping and keep fixtures in `sqlite :memory:`. Then expand reader inventory (Blade exports/other reports).
- Full suite is expected to remain RED on 4 old CV source checks plus this new isolated report diagnostic until safe design/reconciliation; do not hide tests to claim green.

## Phase 1T — real DataTables endpoint diagnostic pending

- User pulled Phase 1S documentation commit `b0d5d35` successfully.
- Added `tests/Feature/Finance/LedgerVoucherDisplayEndpointCollisionTest.php`. The SQLite-only fixture invokes **actual** `LedgerController::fetch_data_url` and Yajra DataTables (unlike Phase 1S mirrored lookup) with an overlapping `JV type_id=41` row. It expects original voucher number `JV-7001` rather than unrelated JV header `JV-9001`.
- **NOT EXECUTED YET.** Run `php -l tests/Feature/Finance/LedgerVoucherDisplayEndpointCollisionTest.php` then `php artisan test --filter=LedgerVoucherDisplayEndpointCollisionTest`. If fixture/eager-load/DataTables errors occur, resolve harness errors first; only a failing display assertion after valid response supports the finding.
- Source identity for the ambiguous row is illustrative, not proof that any actual production row belongs to CV. Do not modify reports or ledger records until provenance and financial reconciliation design is approved.
- Next after verification: expand Blade/export/readers audit and add authenticated middleware integration tests. Four source-level CV RED tests remain.

## 9. Most recent user feedback

2026-10-26 user explicitly requested this durable roadmap because chat limits can force migration to a new conversation; **the roadmap must be refreshed after every change**, enabling continuous work with no loss of context.


## Phase 1T latest local feedback
- User pulled `34aee731` and PHP lint passed. Real DataTables test failed **before voucher display assertion**: expected `recordsFiltered=1`, got `0` (1 failed/1 assertion). **Not yet verified as report-number mismatch.**
- Added read-only diagnostic assertions for selected-project cookie and fixture row plus full DataTables payload on mismatch (`1634104`). Rerun required to determine whether cookie resolution or DataTables filtering is responsible. Avoid assuming report bug until output reaches display assertion.

## Phase 1T — SoftDeletes fixture repair
- User reran actual DataTables test after `b40d1a9`: got **1 failed / 3 assertions**, because Yajra response included SQLite error `no such column: journal_vouchers.deleted_at`; this is a test fixture schema omission, not proof of report display behavior.
- Added `$table->softDeletes()` to isolated `journal_vouchers` fixture in test (commit `3290c709`). **Test rerun pending**. Do not interpret any result as live production ledger issue before the display assertion executes.

## Phase 1T — locally verified real DataTables lookup collision
- User pulled `a2f6b6e`, PHP lint passed; actual `LedgerController::fetch_data_url` SQLite regression reached voucher display and was **RED: 1 failed, 5 assertions**: expected `JV-7001`, received `JV-9001`.
- This establishes the report reader's behavior for a constructed ambiguous numeric-ID fixture. **It does NOT prove any production ledger row belongs to CV or that actual production financial reports are wrong.**
- Do not unconditionally switch report display to stored voucher numbers or mass-convert `JV` to `CV`; valid JV report display can depend on header identity. No report code changed.
- Next Phase 1U: read-only provenance and report/export impact inventory, derive safe typed identity/read fallback policy, add authenticated permission coverage. Review possible live data inspection only with explicit approval, SELECT-only.
