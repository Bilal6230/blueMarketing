# BlueMarketing — Combined Development & Financial Integrity Tracker

Updated: 2026-10-28 (Asia/Karachi)
Branch: `upgrade/laravel-framework`

This tracker combines the earlier project-progress dashboard and the newer database `ledgers` integrity investigation. Progress figures are **planning estimates**, not verified release readiness. Never increment a number merely because a code file was inspected.

## P0 — PRIMARY DELIVERY ROADMAP: DATABASE `ledgers` INTEGRITY

**These five workstreams supersede the Laravel upgrade, demo, Hand Cash investigation, and generic testing as active priorities.** The older project baseline below is *secondary*, not a release gate. Progress figures below are approximate implementation/validation coverage, **not** historical financial correctness, and must not be treated as certified completion.

| Rank | Primary task | Estimated progress | Implemented evidence | Remaining acceptance gate |
| --- | --- | ---: | --- | --- |
| P0.1 | **Ledger protection:** audit and fix every live INSERT/UPDATE/DELETE/deactivate/overwrite, project scope and identity guard | **93%** | Mobile approval immutability and project scope; JV/CV destructive gates; Stock/Mobile Stock duplicate, orphan, amount/date/project guards; CR/CP editor prevents noncash JV/SV/BO/BR/PPR overwrite and rejects invalid linked sources | Every producer/mutator classified and tested, protected ledger rows cannot silently disappear |
| P0.2 | **Voucher integrity:** propagate correct source voucher number/date for JV, CV, Stock, Labour, Booking, CR, CP, BR, PPR | **55%** | Core JV header number, Labour JV number/date, Stock existing-posting date and amount checks | Every source creates ledger rows with verified source number/date, regression tests for all families, preexisting mismatch inventory |
| P0.3 | **Foreign-key / typed source identity:** source-aware, project-consistent relational integrity | **15%** | Existing physical `customer_ledger_id` and `charge_type_id` FKs confirmed on restored clone; typed source attribution design exists | Per-source FK/typed links, uniqueness & concurrency checks, vetted migration rehearsal; do **not** force `type_id` into one FK |
| P0.4 | **Soft-delete / append-only reversal:** protect historical business records and posted financial events | **15%** | JV/CV destructive write gates and CR/CP/PPR deletion tests exist | Cover all applicable business tables and side effects; financial reversals/append-only protection; MySQL schema review |
| P0.5 | **Historical recovery:** reconcile missing/inactive/overwritten JV and other ledger entries, then controlled repair | **10%** | 17,855 restored ledger rows inventory; 155 inactive; source ambiguity evidenced; Project 5 balance reproduced | Independently prove provenance per row, compare older backup/audit logs, approved dry-run, reversible repair and balance validation |

### Testing cadence and evidence-based progress rules (2026-10-28)

- **Default: run only targeted tests affected by each changed application path**, including neighboring regression tests where dependencies overlap. Do not repeatedly request `php artisan test --testsuite=Feature` after each small fix.
- **Run the full Feature suite only** for architectural/shared infrastructure changes, schema migrations, substantive cross-module behavior changes, pre-release gates, or a regression that cannot be isolated.
- If a new regression test is added, execute that test and the closely related existing test file/filter. Preserve the previous full-suite baseline without falsely claiming it covers newly changed code.
- **Latest user-verified baseline:** 436 Feature tests / 1,883 assertions, including JV-overwrite guard; clean Git working tree and PHP lint. This supersedes the older 417/428/430/435 baselines.
- P0.1 coverage **40% → 50%** (planning estimate): Stock/Mobile Stock idempotency and source consistency plus CR/CP cash editor source/type immutability now have locally passing regression tests. The remaining 50% reflects unreviewed write paths, concurrency and database-level enforcement, full delete/deactivation audit and source ownership gaps.
- Increment progress **when an independently checkable implementation milestone passes**, not on code inspection alone. Record what is implemented, what tests passed, and what is still unverified for every increment; never call percentages release readiness.
- Continue delivering source implementation via GitHub, Codex as independent inspector only; one consolidated commands block for the user, not repetitive commands.

### P0.1 — Posted ledger deactivation loophole closed in code (verification pending)

- Discovered active `LedgerController::destroy()` historically rejected **only** `CR/CP`, but other posted types `JV/SV/BO/BR/PPR` could be changed to `is_active=0` and could have related `customer_ledger` records deactivated; nonprivileged users could submit pending delete requests. This makes records disappear from active ledgers without physically deleting them.
- Replaced this action with a **fail-closed guard for every posted `ledgers` row**, retaining selected-project 404 and 422 `voucher_posted_to_ledger` behavior. Removed unreachable deactivation and deletion-approval branches. `draftDestroy()` remains separate and unchanged. Application code commits `31947f8`, `db9ec63`.
- Added five SQLite datasets checking `JV, SV, BO, BR, PPR` cannot be deactivated and no pending deletion is created, plus existing CR/CP coverage. Commit `7b626be`.
- **VERIFIED:** focused `VoucherDeleteProtectionTest` completed **12 passed / 54 assertions** on 2026-10-28; PHP lint passed, Git tree clean. Latest full Feature suite remains **436 passed / 1,883 assertions** before this change (not rerun by design).
- P0.1 implementation estimate: **60%**, verified by user's targeted test run: **12 passed / 54 assertions**, including five new JV/SV/BO/BR/PPR cases. Remaining gaps: other producers' mutation routes, raw SQL, triggers/grants, concurrent writes and application-wide deletion policies. No MySQL writes, migrations or historical repairs.

### 2026-10-28 P0.1/P0.2 combined implementation — Bank clearance

- Identified a live `ReportController::bank_posting_check()` path that previously permitted source types beyond active pending CR/PPR bank/check receipts, accepted a passed clearance without explicit business date, and treated any matching BR ledger as already posted without cross-project/source, activity, date, account or amount validation.
- Implemented transaction-scoped checks for active CR/PPR sources and bank/check payment types, required `bank_post_at` when passing, and validated all matching BR reference legs for consistency before idempotent reuse. Conflicting/duplicate BR ledger rows now trigger rollback rather than silently accepting a mismatched posting.
- Corrected BR amount selection: CR receipts may carry value in `amount_in`; use positive `amount_out` when present, otherwise `amount_in`, and reject zero/negative resolved amounts. This guards against zero-value BR ledger postings.
- Added two targeted regression datasets checking rejected cash and wrong-source bank clearance attempts do not change the source status or create a BR entry. Commits `04c4323`, `fba678d`, `3df5d78`.
- **Pending targeted verification:** `VoucherClearanceFlowTest`. Retain P0.1 **60%** and P0.2 **30%** until passing tests; no full-suite rerun required. No production or staging data change, migration, or historical repair.

### 2026-10-28 Bank clearance hardening — both active endpoints

- **User verified prior ReportController batch**: `VoucherClearanceFlowTest` **27 passed / 188 assertions**, both invalid-source cases passed, PHP lint passed, clean worktree. This closes its earlier pending gate.
- **New implementation**: `Finance/VoucherController::updatePendingBankPaymentStatus()` now blocks inactive receipts, requires an explicit `bank_post_at` on pass, computes a strictly positive BR amount from source `amount_out` or `amount_in` (CR often credits `amount_in`), and detects existing `BR` reference collisions across all sources. Idempotent reuse requires matching linked source, account, amount, activity and date. Any conflict rolls back source status/history and posting.
- **New synthetic regression cases**: missing date, inactive source, positive-value CR-to-BR posting, foreign source BR reference collision, in `VoucherClearanceFlowTest`. Commits `984be43`, `fa3c2ea`.
- **Progress estimation** P0.1 **60% → 65%**, P0.2 **30% → 35%**: prior ReportController tests are verified and the second clearance endpoint has a concrete implementation plus focused regression cases. **New tests are not yet run locally**; 65/35 is implementation coverage estimate, not verified release readiness; adjust if tests fail.
- Remaining: audit other live ledger source writers, direct SQL mutations, concurrency/unique constraints, actual MySQL source/date/FK compatibility, historical provenance. No production/staging writes or schema migrations.
- **Testing cadence**: run only `tests/Feature/VoucherClearanceFlowTest.php` in this iteration; no full-suite repetition.

### 2026-10-28 Bank clearance fallback-account project isolation

- User verified the previous bank-clearance batch: **31 targeted Feature tests / 206 assertions PASS**, PHP lint clean and working tree clean. No need to repeat the full Feature suite.
- **P0.1 security fix**: `Finance/VoucherController::updatePendingBankPaymentStatus` formerly trusted the existing receipt-linked ledger's account ID as a fallback if the request omitted valid accounts. A corrupted/cross-project source ledger could therefore select another project's account for a fresh `BR` posting.
- The fallback now resolves only a `project_head_subheads` row belonging to the selected project. If the existing account is foreign, the clearance transaction fails and preserves source status and ledger state.
- Two targeted tests added: foreign-project fallback rejection and valid same-project fallback acceptance. Commits `22c85c6` and `35679a2`.
- Progress: P0.1 **65% → 68% implementation estimate**, based on one additionally protected source-ownership path. New tests **not yet locally verified**; mark as pending until focused test execution. P0.2 stays **35%**.
- Remaining gates include full write-path inventory, concurrency/races, MySQL unique constraints and historical source provenance. No staging/production finance writes.

### 2026-10-28 multi-path ledger protection batch

- User-verified prior checkpoint: **2 focused fallback-account tests / 6 assertions PASS**, PHP lint passed, clean Git tree; earlier broader clearance tests **31 / 206 PASS**.
- **Three code paths hardened together**: (1) report BR clearance refuses existing ledger rows with nonzero `amount_in`; (2) finance clearance refuses explicitly wrong or incomplete bank account selections, rather than silently falling back; (3) cash `LedgerController::update` refuses inactive or already-mismatched source/ledger pairs for business date and debit/credit amounts, preventing legacy inconsistency from being silently overwritten.
- Two regression tests were added for finance clearance explicit-invalid and partial-account requests (both must preserve source status and not create BR). Commits `32e57b9`, `547435b`, `2c62efb`, `f419fcf`.
- Progress implementation coverage P0.1 **68% → 76%** and P0.2 **35% → 38%** for the multi-path protections. **New changes remain unverified locally** until focused `VoucherClearanceFlowTest` is run; progress may be revised if tests fail. No claim of total P0.1 completion or database constraints.
- Continue targeted testing only. No production/staging finance writes, migration, historical repair or deployment.

### 2026-10-28 Stock posting completeness (Web entry / bill + Mobile entry)

- User-verified last checkpoint: `VoucherClearanceFlowTest` **35 passed / 219 assertions**, and clean working tree; no full-suite rerun requested.
- **Web Stock entry and bill plus Mobile Stock entry** previously checked `StockLedgerPostingGuard` but still independently skipped each debit/credit leg based on a loose `exists()`. Removed that partial posting skip from all three writers: when preflight permits a fresh posting, both legs are inserted as a unit by the caller's transaction. Existing incomplete/duplicate reference remains rejected by the guard. This does not prove concurrency uniqueness for competing first-time requests.
- **Guard expanded:** both expected project-head-subhead IDs must exist in the intended project even when there are zero ledger rows; invalid amounts are also rejected before posting.
- Added 2 new focused SQLite tests for foreign and missing expected accounts. Commits `54860c8`, `86061a3`, `8bf73dd`, `254f7c0`.
- **Coverage estimate:** P0.1 **76% → 82%**, P0.2 stays **38%**. This is code implementation coverage, *pending fresh focused tests*. It does not certify MySQL concurrent uniqueness or historic record integrity.
- Only run `StockLedgerPostingGuardTest.php` and syntax checks for modified sources. No migration, production mutation, historical backfill or release.

### P0.1 stock first-posting concurrency control — 2026-10-28

- **Verified previous stock milestone:** user ran `StockLedgerPostingGuardTest`: **20 passed / 21 assertions**, syntax checks passed for Web/Mobile controllers and guard, Git working tree clean.
- **Implemented shared lock across all 3 stock writers**: `StockLedgerPostingGuard::alreadyPosted` now acquires a `projects` row `FOR UPDATE` for the selected project *before* checking ledger rows. Because Web Stock Entry, Web Stock Bill and Mobile Stock Entry invoke this guard inside the same transaction that inserts both legs, competing stock postings in that project serialize even when no `ledgers` record exists yet. This closes the first-posting gap not covered by `lockForUpdate` on empty `ledgers` results.
- Added fail-closed project existence check and a new SQLite regression test; fixture includes a synthetic `projects` row. Commits `6b900ae`, `2a0b9aa`. SQLite tests verify fail-closed behavior, **not** real MySQL concurrent scheduling; staging concurrency rehearsal and database constraints remain to be verified.
- **P0.1 coverage estimate 82% → 87%**, contingent on targeted test passing; P0.2 remains **38%**. Not a release readiness claim.
- **Future check**: independent MySQL staging concurrent requests, other raw SQL writers, typed source linkage and per-project voucher uniqueness. No historical repair, production writes or migration.

### P0.1 Stock financial-source consistency batch (2026-10-29)

- **Last verified local checkpoint:** Stock posting guard **21 passed / 22 assertions**, clean PHP syntax and Git worktree. No need to rerun unrelated feature tests.
- **New fail-closed protections** in `StockLedgerPostingGuard` shared by Web Stock Entry, Web Stock Bill and Mobile Stock Entry:
  1. A posting must contain exactly two positive, individually one-sided debit/credit legs, with equal value and **distinct project-owned accounts**. Zero amounts, negative amounts, same-account cancellation and mixed debit/credit legs are blocked.
  2. Already-posted stock references now require **exactly one matching customer source row** globally; an extra duplicate source or missing linked source invalidates replay rather than silently classifying it as safely posted.
  3. Fresh and already-posted stock sources must carry exactly one positive debit/credit-side amount equal to the expected ledger journal total. This prevents source amount drift or mismatched stranded source reuse. The Stock IN and OUT direction conventions are both accepted.
- **11 added targeted test cases**, including six debit/credit invalid expectation datasets, two hidden/missing source cases, and three source-amount scenarios. Commits `6851534`, `a50d130`, `c2cd148`, `2236c32`.
- **P0.1 estimated implementation coverage 87% → 93%** for tested-in-principle protective invariants; *new tests still need local execution*. This is not database-level durability assurance. Remaining: global writer inventory, MySQL concurrent test, database constraints and source-type migration design. P0.2 stays **38%**.
- Historical rows, production MySQL and staging were **not** modified. Any legacy source inconsistencies will be reported and blocked, not silently repaired.

### 2026-10-29 Stock identity and numeric safety checkpoint

- **User-verified:** Stock guard baseline **32 tests / 34 assertions PASS**, PHP syntax clear and Git tree clean. Previous Stock-source integrity batch is verified.
- Implemented explicit Stock reference namespace validation (`STOCK-ENTRY:<id>` and `STOCK-BILL:<id>` with positive integer IDs) and ISO business date shape validation *before touching source postings*. Prevents arbitrary `JV` reference aliases from being classified as Stock.
- Hardened minor-unit money parsing to fail on values above PHP integer capacity instead of risking silent saturation/overflow. New regressions include four malformed references, malformed date and overflowing amount.
- Commits `2bf124c`, `5a27e20`. **New targeted tests pending local verification**; full suite not needed.
- **P0.1 stays 93%** until remaining writer inventory/DB concurrency guarantees are delivered; P0.2 stays 38%. Do not increase milestones merely for narrowly scoped checks. Production MySQL and historical financial rows remain untouched.

### 2026-10-29 verified Stock checkpoint and JV dead-code removal

- User-verified `StockLedgerPostingGuardTest`: **38 tests / 40 assertions PASS**; syntax checks clean; Git working tree clean. This confirms the preceding source-identity/overflow patch.
- Reviewed `JournalVoucherController::destroy` and `update`: both already started with unconditional HTTP 409 but contained extensive **unreachable code** that would physically delete or overwrite historical `ledgers` by ambiguous polymorphic `type_id` if the gate were accidentally bypassed. Removed the unreachable destructive code, retained explicit 409 fail-closed behavior and left the JV edit view/read unchanged.
- Added focused regression checking both mutation endpoints still return 409 and ledger row count stays constant. Commits `4ea88b6`, `3854ea5`. This test remains **pending local execution**.
- **P0.1 remains at 93%**: removing latent dangerous code does not close DB-level cross-writer guarantees, and the user asked for evidence-based progress rather than cosmetic percentage changes. P0.2 remains 38%. Production/staging DB unchanged.
- Next acceptance gates: broader customer/booking/labour writer review, MySQL concurrency checks and source-typed DB constraints.

### 2026-10-29 Booking PPR + Labour JV active writer batch

- **Verified last checkpoint**: legacy JV blocked-mutator regression **1 passed / 3 assertions**, PHP lints clean, Git worktree clean.
- **Booking PPR live writer** (`BookingController`): previously selected just the first `PPR` ledger linked to a customer source, then updated ledger narrative/date even when the amount, activity, account or source owner was inconsistent. Now gathers *all* `PPR` ledger rows sharing a reference with row locks; rejects duplicates, foreign-linked sources, inactive ledger, wrong account, wrong date, amount drift or nonzero credit; rejects reuse of inactive PPR customer sources. This prevents silent repair/hijack of existing posted entries, preserving them for independent reconciliation.
- **Labour JV live writer** (`LabourController::createVoucher`): inside existing transaction, before posting two journal legs, now requires two distinct project-owned account IDs. Rejects cross-project and self-cancelling JV account mappings, rolling back voucher header/details if invalid.
- **3 new focused PPR replay regressions** in `VoucherClearanceFlowTest`: duplicate reference ledger, inactive source, and financial amount drift; all assert no repair/overwrite. Commits `921f00e`, `9bbdd00`, `d746dbb`.
- **Implementation coverage**: P0.1 remains **93%** (final DB/other-writer inventory gates still open); P0.2 **38% → 48% estimated** for PPR source-ledger identity and Labour JV account mapping checks, **not yet verified locally**. No real staging/production data changed; no migration or historical repair.
- Run only focused `VoucherClearanceFlowTest` with PPR filter first and lint both touched controllers. Do not rerun the full suite.

### 2026-10-29 Booking Extra Charge + Labour JV source-number alignment

- User-verified PPR checkpoint: **12 targeted tests / 93 assertions PASS**, PHP lint passed, clean Git tree.
- **Booking extra-charge writer fail-closed:** identified live legacy branch creating `customer_ledger.transaction_type = 'null'` and mixed CR/CP general-ledger rows, referencing `voucher` (not canonical `voucher_number`) and independently allocating wrong voucher types. Removed the unsafe insert sequence and replaced with explicit exception before any financial source or ledger write. **This intentionally disables legacy extra-charge posting** until a properly typed, balanced, tested workflow is designed; other Booking deposit/PPR flows are not disabled.
- **Labour JV voucher consistency:** stopped independently using `getLastJvVNumber()+1` for JV header while ledger legs used `getVocuherNumber('JV')`; the header now gets the same resolved `voucherNumber` as the ledger legs. Existing transaction links header and both ledger rows.
- Added targeted Booking regression checking extra-charge attempt redirects with error and preserves both financial table counts. Commits `999ed70`, `2a4520c`, `1ea2e02`.
- **P0.2 coverage estimated 48% → 55%** as a major broken voucher numbering/typing writer was blocked and Labour JV number alignment corrected. This is an implementation estimate, **new code/tests not yet verified locally**. P0.1 stays 93%; no production/staging data edits. Remaining: typed extra charge redesign, booking/Labour integration cases, overall voucher uniqueness and source typing.
- Only run focused extra-charge regression and relevant Labour tests; full suite not needed.

### Primary delivery sequencing

1. **Prevent new data loss** first: finish source-specific mutation guards, database authorization, concurrency and comprehensive regression tests.
2. **Fix new voucher/date propagation** for every ledger-producing module. Compare source and ledger fields; never rewrite historical dates silently.
3. **Introduce source-aware FKs/constraints** through tested, backup-first migrations on an approved disposable environment; maintain compatibility with existing overloaded `JV` rows.
4. **Implement safe deletion/reversal** across modules without blanket soft-deletes on posted journal rows or destructive migration rollbacks.
5. **Repair historical rows last**, after ownership evidence and explicit authorization; no balance plug or guessed `type_id`.

### 2026-10-28 P0.1 / P0.2 new implementation checkpoint

- Implemented a **fail-closed linked customer-ledger date/activity check** in `StockLedgerPostingGuard`. Existing stock `ledgers` legs may only be considered already posted if the related `customer_ledger` entry shares the original business date and is active. Previously the guard checked its project, reference and transaction type, but not its date/activity.
- Added two SQLite cases for wrong `customer_ledger.date` and inactive `customer_ledger.is_active`; expanded synthetic fixture columns. GitHub commits `d935b2a`, `9386532`.
- **Not yet locally verified:** rerun the focused Stock regression and full Feature suite; latest previously verified baseline remains **428 passed / 1,871 assertions**.
- At this checkpoint P0.1 was estimated **40%**. The newer 2026-10-28 test-confirmed checkpoint above supersedes it with **50%**; P0.2 remains **30%**.
- No MySQL database, source attribution, migration, recovery or production deployment operation.

### P0.1 one-turn implementation — stranded Stock source safeguard

- Implementation: `StockLedgerPostingGuard::alreadyPosted` now checks `customer_ledger` even when **zero** stock ledger legs exist, catching duplicate source rows and cross-project, inactive or business-date-mismatched stranded source records before allowing new postings.
- Scope: Web Stock entries/bills and Mobile Stock share this guard; it does not repair any older incomplete records.
- GitHub implementation and five regression cases: commits `5814329`, `3110572`.
- Baseline before these changes: user verified **430 Feature tests / 1,873 assertions**, working tree clean.
- Post-change focused and full test execution **pending local verification**. Do not mark full P0.1 complete on the basis of this limited guard.
- Strictly no MySQL writes, migrations, production deployment, or historical backfill.

### 2026-10-28 P0.1 cash-ledger editor safety batch

- **Critical bug addressed in source:** the generic `LedgerController::update()` previously computed both debit/credit amounts as **zero** for `JV`, `SV`, `BO`, `BR`, `PPR` etc., while changing the account/date/detail. This could silently destroy posting meaning if a non-cash ledger row reached the cash editor.
- **Implementation:** hard gate inside the locked update transaction restricts the editor to `CR` or `CP` ledger rows that have a linked active `CustomerLedger` with matching project, transaction type and reference. Any missing/foreign/inactive/wrong-type source fails closed. Existing source synchronization remains transactional. No historical rows are modified.
- **Regression:** isolated test added in `VoucherClearanceFlowTest` verifying a synthetic `JV` ledger cannot be zeroed via this editor.
- **Latest previously verified run:** **435 Feature tests / 1,879 assertions**, clean Git status, before this batch. **Post-batch tests NOT YET VERIFIED locally**, so no progress percentage increase or release readiness claim.
- **Follow-on:** Audit CR/CP reference evolution and authenticated route responses; fail-closed may intentionally reject legacy inconsistent cash rows pending separate reconciliation. Preserve immutability and do not auto-align historical references.

### Latest verified testing checkpoint

- User's focused `StockLedgerPostingGuardTest`: **11 passed / 11 assertions**, six conflict datasets executed.
- User's full isolated Feature suite: **428 passed / 1,871 assertions**; working tree clean after pull at `c524251`.
- The earlier `@dataProvider` failure is **resolved and verified**, not an open blocker.
- These synthetic SQLite tests **do not** certify MySQL concurrency, original voucher provenance or production safety.

## Historical project-progress baseline

The earlier dashboard shown in the screenshot reported the following estimates:

| Track | Prior reported estimate | Current evidence/status |
| --- | ---: | --- |
| Overall application migration | 88% | Historical estimate only; deployment is blocked |
| Laravel 13 application | 98% | Full isolated Feature suite 428 passed / 1,871 assertions on 2026-10-28 |
| Migration compatibility | 82% | Schema/history and DDL provenance unresolved |
| Financial reconciliation | 60% | Historical estimate; unverified JV/CV ownership and missing ledger rows remain |
| Database restore & connectivity | 100% | Local restricted clone was restored and read successfully; **not** production reconciliation |
| Client demo readiness | 55% | Financial correctness has not been certified |

**Dashboard note:** The local SQL clone reproduced Project 5 Hand Cash Balance of **-76,910** by subtracting Dasti Cash **83,910** from active ledger net **7,000**. This verifies the formula for that snapshot, **not** why an earlier displayed balance of 11 changed; no prior-image audit or independently proven historical delta is available.

## Current five-track implementation board (not a percentage of release readiness)

| Workstream | State | Concrete evidence and next acceptance gate |
| --- | --- | --- |
| Laravel 13 and isolated tests | Active / previously green | 428 Feature tests passed / 1,871 assertions after Stock provider fix; additional integration and MySQL rehearsal pending |
| Ledger write-path integrity | Partially implemented | JV and Labour number/date fixes, Mobile approval scope/field guards, web and mobile Stock fail-closed guard; source-specific integrations/concurrency unfinished |
| Source attribution, FKs and numbering | Design / blocked | Existing ledger FKs: charge_type_id and customer_ledger_id. `type_id` is shared; typed source FK design and schema compatibility await approval |
| Historical ledger reconciliation/recovery | Not authorized | Missing/inactive/duplicate/source mismatch inventory and independent audit evidence needed before any backfill or reversal |
| Deletion protection and deployment | Blocked | JV/CV destructive endpoints currently gated; append-only reversal and schema-aware SoftDeletes are future work; no production deployment |

## 2026-10-28 test-failure tracking

- User's earlier local Feature suite: **417 passed / 1,860 assertions**; PHP syntax clean and git clean after Labour fix.
- After Stock guard: **422 passed, 1 failed / 1,865 assertions**; failing test: `StockLedgerPostingGuardTest::test_conflicting_stock_posting_is_rejected`.
- Root cause: PHPUnit ran method with no arguments because its docblock data provider was not registered.
- Code fix pushed: explicit PHPUnit attribute `#[\\PHPUnit\\Framework\\Attributes\\DataProvider('conflicts')]` at commit `794fc543`.
- **RESOLVED:** post-fix 11 focused tests / 11 assertions and 428 Feature tests / 1,871 assertions passed; all six datasets were executed.
- No database operations, production deployment, migration or historical repair were performed.

## Working conventions

1. **ChatGPT implements via GitHub**; Codex provides independent review/inspection only, not unreviewed direct implementation.
2. User receives **one consolidated PowerShell command block per turn** for pull, isolated tests, syntax checks and git status.
3. Keep the restricted local clone SELECT-only; never run PHPUnit against it. No migration, seed, financial UPDATE/DELETE/INSERT on production/staging/clone.
4. Preserve `vendor.before-laravel13-incomplete/` locally, excluded through `.git/info/exclude`; never stage it.
5. Require passing local tests, database FK and source matching evidence, financial reconciliation and approved reversible recovery plan before deployment.
