# BlueMarketing — Current Development Handoff

**Updated:** 2026-11-19  
**Branch:** `upgrade/laravel-framework`  
**Local application:** `C:\laragon\www\blueMarketing`  
**Database:** whichever database is configured in `.env`. Owner currently uses `bluenew_clean_final`. Never hard-code database names, introduce separate V2 finance, or reinstate V2 isolation.
**Documentation policy:** Update **this single document after every code batch**. Do not open new milestone/handoff documents. Historic requirements: `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md`. Earlier superseded handoffs are not authoritative.

## Roadmap — six milestones (Activity Audit last)

| # | Milestone | Verified status |
|---|---|---|
| 01 | Nonblocking loading and voucher-tinted skeletons | **COMPLETE** — owner browser + PHP tests PASS |
| 02 | Action-specific permissions, Roles page and backend enforcement | **PARTIAL ACCEPTANCE** — implemented, tests PASS, owner confirmed CR Edit visibly works; complete cross-module browser permission matrix not yet accepted |
| **03** | FK/relationship integrity, safe Create/Edit/Delete, lifecycle columns | **ACTIVE — PARTIAL**. Plot/JV/CV full differential correction verified by owner; Approved CV browser PASS, local 16 tests PASS / 139 assertions. Bank Deposit/Withdrawal manual same-ID Edit + logical Delete committed **LOCAL/BROWSER VERIFICATION PENDING**. CR/DR, CCR/CDR, BOR/BOP, PPR, EC/ECP, SV, labour/stock still need an explicit CRUD acceptance matrix. |
| 04 | Cheque/Online edit/delete with cleared linked-voucher cascade | **NOT STARTED under new rules** |
| 05 | Reversal settings ON/OFF and full regression | **NOT STARTED under new rules** |
| **06** | **Centralized application-wide activity audit (LAST)**: user/role/project/action/request identifiers, exact changed tables and primary keys, field-level before/after values, create/update/delete/approve/restore/cancel, blocked attempts; include Super Admin/Admin/staff; secure/immutable storage, controlled permissions, no sensitive secrets | **DEFERRED BY OWNER** — design planned, DO NOT implement until Milestones 01–05 and current voucher workflows are complete |

## Milestone 03 — Verified work

**Owner-confirmed on `bluenew_clean_final`:**
- `2026_11_18_150000_enforce_voucher_source_foreign_keys.php` migrated. Read-only `finance:audit-voucher-schema --require-fks` PASS: **8 of 8 FKs present**, zero orphan references, zero cross-project JV/CV detail/account links.
- `2026_11_18_160000_add_plot_voucher_lifecycle_columns.php` migrated. `finance_plot_receipts` and `finance_plot_purchases` now have `is_active` and `deleted_at`. Existing source records preserved.
- `VoucherRelationshipSafetyMilestoneTest`: **4 PASS / 57 assertions**, `LinkedVoucherLedgerRetirementTest`: **3 PASS / 10 assertions** (earlier owner run), `PlotVoucherLifecycleColumnsTest`: **3 PASS / 23 assertions** in latest run. PHP lint `PlotEntryWriter.php` PASS; view cache PASS.
- JV/CV **legacy branch** transactional Edit/Delete: linked old ledger rows deactivated with exact project/source/detail/amount checks; previous details/header soft-deleted where appropriate; no business hard deletion. Draft Delete archives the project-scoped draft.
- Plot History/Details hide inactive/deleted source roots. Old request-key replay now rejects an archived Plot Receipt/Purchase instead of returning it as active.

**Prior owner failure (RESOLVED):**
- `PlotEntryWriterTest` reported **23 FAILED / 0 assertions**, all due to the **same setup exception**: `Class "App\\Services\\Finance\\V2IsolatedConnectionGuard" not found`, triggered by `database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php:11`. This is a leftover historical dependency after the user already merged old V2 architecture into the normal application. The failure happened in test fixture setup, before test assertions; it does **not** establish that all 23 Plot posting operations have functional bugs.

**Previous GitHub fix, now owner verified PASS:**
- Replaced the deleted `V2IsolatedConnectionGuard` with existing **`FinanceConnectionGuard`** in all **four** Plot storage fixture/recovery migrations: `database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php` and three files under `database/local_finance_migrations/2026_11_14_*`. They now use the application's configured DB; no DB-name prefix or environment isolation check. Existing required-table, no-overwrite, partial-schema refusal, driver-specific MySQL FK recovery and RESTRICT FKs remain.
- Replaced three old Plot feature-test assertions that specifically demanded **`V2_ISOLATED_CONNECTION_REQUIRED`** with assertions that verify already-installed storage is preserved regardless of environment and that MySQL-only FK repair still refuses SQLite.
- Added `tests/Unit/Finance/PlotLegacyMigrationCompatibilityTest.php` to prevent reintroduction of the deleted guard dependency.
- **Owner terminal confirmed FIX PASS:** `PlotLegacyMigrationCompatibilityTest` **2 / 24 assertions**, `PlotEntryWriterTest` **23 / 121 assertions**, `PlotVoucherLifecycleColumnsTest` **3 / 23 assertions**. **Total: 28 tests, 168 assertions PASS.** The configured `bluenew_clean_final` FK integrity audit also remains **8/8 FKs, zero orphans, zero cross-project JV/CV account links**. No need to repeat older FK/Plot schema migrations.

## 2026-11-18 — Owner test PASS and subsequent Plot archive/reversal safety batch

The last owner terminal output confirms **all 23 PlotEntryWriterTest methods PASS (121 assertions)**, legacy migration compatibility **2 PASS / 24 assertions**, and Plot lifecycle **3 PASS / 23 assertions**; total **28 PASS / 168 assertions**. The earlier deleted V2 class fixture blocker is closed. DB read-only FK audit still **8/8 and clean**.

**Additional code committed after this owner PASS:** `PlotEntryLifecycleService::reverse` now checks the source root's **is_active=1** and **deleted_at IS NULL** (when columns exist) in the same project-scoped locked transaction. Previously an archived source could still produce a second financial mutation via the separate reversal endpoint even though Details/History/Replay hid it. New isolated SQLite integration tests cover both archived Plot Receipt (no new posting batch or lines) and archived Plot Purchase (no new batch, original inventory still active), in `PlotEntryWriterTest`. This blocks a conflicted action; it is **not** an implementation of ordinary Edit/Delete or a reversal-only substitute. No business rows are deleted or archived by this code batch.

**Latest code is committed, but the two NEW regression tests have not yet been run locally.** Next commands:
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotEntryLifecycleService.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotLegacyMigrationCompatibilityTest
php artisan finance:audit-voucher-schema --require-fks
```

## 2026-11-18 — Owner accepted archived-source regression tests; browser gate policy

**Owner verified on local `bluenew_clean_final`:** `PlotEntryWriterTest` **25 PASS / 127 assertions**, `PlotLegacyMigrationCompatibilityTest` **2 PASS / 24 assertions**; total **27 PASS / 151 assertions**. `PlotEntryLifecycleService.php` PHP lint PASS. `finance:audit-voucher-schema --require-fks` remains PASS (**8/8 FKs**, zero orphans, zero cross-project JV/CV detail links). This validates both recently added **archived Plot Receipt and Plot Purchase reversal refusal** integration tests. No browser pass has been reported for these flows.

**Owner instruction:** proactively flag browser-testing gates when they actually arise; specify URL/page, role/project, exact Create/Edit/Delete/reversal action, expected result, and whether disposable local test data is necessary. **No browser testing is required for this strictly backend archived-source regression batch.** A browser gate becomes mandatory once actual user-facing Plot Edit/Delete and linked posting/inventory workflow is implemented. Do not mark complete on unit tests alone. Maintain this single README after each implementation batch and keep future handoffs up to date.

## 2026-11-18 — Milestone 03: Audited Plot Receipt/Purchase narration Edit (LATEST)

**Owner verified preceding batch:** PlotEntryWriterTest **25 PASS / 127 assertions**, PlotLegacyMigrationCompatibilityTest **2 PASS / 24 assertions**; total **27 PASS, 151 assertions**. FK audit **8/8 present**, zero orphan references, zero cross-project JV/CV detail links. This does not verify the following new code.

**Implemented and committed (NOT yet tested locally):**
- New append-only audit table via `database/migrations/2026_11_18_170000_create_plot_voucher_edit_audits_table.php`, storing project, source family/id, before/after narration, editor and timestamps; no destructive down migration.
- `PlotVoucherNarrationEditor` makes a real **transactional narration-only Edit** for Plot Receipt and Plot Purchase, locks active same-project source, validates exact same-project posting link, disallows previously reversed source, updates source narration, writes an audit record atomically, and preserves original finance posting lines, amounts, allocation records and purchased plot inventory untouched.
- `VoucherController` PATCH handlers and `routes/web.php` PATCH routes enforce **independent granular Edit** permissions and workflow-selected project authorization, with JSON status and 409 conflict for actual business constraints.
- Both Plot workspaces now offer **Edit narration** inside the shared row Actions control, protected by their module's own Edit permission (not by Reverse permission), compact Bootstrap modal and jQuery AJAX PATCH with inline popup error, success notification and history refresh. No page navigation/reload required for this edit.
- Added `PlotNarrationEditContractTest` for route/permission/UI/source contract and extended `PlotEntryWriterTest` with isolated SQLite integration cases for same-project receipt edit/audit/unchanged financial lines and reversed Plot Purchase edit rejection.

**Known scope / still pending:** This is **narration-only** editing, not full amount/date/customer/allocation/inventory Edit. Full transactional Edit/Delete of posted Plot vouchers and remaining voucher modules still need the accounting-balance lifecycle design and implementation; do not mark Milestone 03 COMPLETE. Existing reversal remains a separate action, not a substitute for ordinary Delete. The new audit migration **must be applied to the intended local DB before browser testing**. Do not run `migrate:fresh` or previous one-off FK/Plot migrations again.

**Owner local gate (code tests first, then migration):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/PlotVoucherNarrationEditor.php
php -l tests/Unit/Finance/PlotNarrationEditContractTest.php
php artisan test --filter=PlotNarrationEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan db:show
php artisan migrate --path=database/migrations/2026_11_18_170000_create_plot_voucher_edit_audits_table.php
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED AFTER LOCAL TESTS/MIGRATION PASS:** Open `/admin/finance/voucher/plot-receipt` and `/admin/finance/voucher/plot-purchase` with selected local test project. Login as user granted **Plot Receipt Edit** or **Plot Purchase Edit**, without requiring Reverse; open an active unreversed disposable voucher's floating Actions menu -> **Edit narration** -> change note -> Save; expect modal close, success toast, same-row narration updated via AJAX and no full-page refresh. Reload and confirm note persists. Revoke that specific Edit permission (leave Reverse as desired) and verify Edit action disappears and direct PATCH is forbidden (403). If source was reversed, editing must return 409 with visible popup error. Verify original monetary amount, allocation list, posting balance and plot inventory unchanged. **Browser acceptance unverified until owner explicitly confirms.**

## 2026-11-18 — Simplify schema: consolidate redundant Plot narration audit table (LATEST)

**Owner concern:** do not create a new MySQL table for every voucher action. The previous Plot narration feature introduced `finance_plot_voucher_edit_audits`, and owner applied its `2026_11_18_170000` migration to local `bluenew_clean_final`. It is excessive for a two-field before/after history already handled by the shared voucher edit snapshot structure.

**Owner latest terminal verification:** `PlotNarrationEditContractTest` **2 PASS / 33 assertions**; `PlotEntryWriterTest` **26 PASS, 1 FAILED** (136 assertions) where `assertSame($before, $after)` compared separate `stdClass` object instances even though accounting line data displayed the same; `php artisan view:cache` PASS; `finance:audit-voucher-schema --require-fks` **8/8 FKs PASS, zero orphans, zero JV/CV cross-project links**; `2026_11_18_170000_create_plot_voucher_edit_audits_table.php` **DONE** on local DB. **No application-level Plot browser test reported.**

**This code batch (committed; latest owner tests pending):**
- `PlotVoucherNarrationEditor` now writes before/after JSON into **existing** `daily_voucher_edit_snapshots`, with distinct `status=plot_note`, `approval_id=NULL`, project and source id, and source kind embedded in both snapshots to disambiguate Plot Receipt vs Purchase vs customer_ledger numeric IDs. Requires valid actor ID. Retains same transaction, project-scoped source lock, original finance amounts/allocations/inventory untouched, Edit permission and AJAX UI. Existing pending cash approval queries check `status=pending` and never treat `plot_note` as an approval. **No new table required for Plot Edit going forward.**
- `2026_11_18_180000_consolidate_plot_narration_audit.php` is **a one-time cleanup migration**: confirms shared table exists; checks all legacy Plot edit records for complete attribution; copies existing records to shared snapshots, preserving original before/after notes, actor and timestamps; uses stable per-row reason to avoid duplicate copy on retry; **only after successful copy drops the redundant `finance_plot_voucher_edit_audits` table**. On invalid evidence or copy failure, migration fails without dropping legacy table. Does **not** modify any financial source, postings, inventory or ledger. This is the only new migration to apply locally; previous `170000` already ran.
- Updated Plot integration fixture to use existing snapshot layout and assert JSON before/after values. Fixed false test failure by comparing arrays of posting line **field values**, rather than `stdClass` object identity; ordering fixed by id. Added a SQLite feature case confirming legacy audit rows copy correctly and redundant table is removed without losing history. Updated contract test to prohibit use of the redundant table by runtime service.

**Run on local test database after code pull; confirm selected .env database before migration:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/PlotVoucherNarrationEditor.php
php -l database/migrations/2026_11_18_180000_consolidate_plot_narration_audit.php
php artisan test --filter=PlotNarrationEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan tinker --execute="dump(DB::connection()->getDatabaseName());"
php artisan migrate --path=database/migrations/2026_11_18_180000_consolidate_plot_narration_audit.php
php artisan finance:audit-voucher-schema --require-fks
```
**Expected:** New tests PASS, shared `daily_voucher_edit_snapshots` remains, redundant `finance_plot_voucher_edit_audits` disappears. If migration finds old rows without an editor, it intentionally fails and preserves old table; inspect those rows, do not force-drop. **Browser test still required for both Plot Edit Narration workspaces** after code/migration pass (disposable voucher, user with Edit ON, test independent Reverse OFF and 403 on direct PATCH without Edit).

**Follow-on engineering policy:** Before adding any further business or audit table, explicitly inspect existing tables and prefer reuse where semantics and integrity can be preserved. Schema growth needs a demonstrated functional necessity, not one table per new Edit/Delete action. **Milestone 03 still PARTIAL**: full amount/allocation/inventory edits and ordinary logical deletion remain unimplemented.

## 2026-11-18 — Owner PASS + full-table redundancy audit (LATEST)

**Owner local confirmations:** `PlotNarrationEditContractTest`: **2 PASS / 35 assertions**; `PlotEntryWriterTest`: **28 PASS / 150 assertions**; combined **30 PASS / 185 assertions**. `2026_11_18_180000_consolidate_plot_narration_audit.php` migrated **DONE** on configured `bluenew_clean_final`, copying evidence from the redundant `finance_plot_voucher_edit_audits` table to shared `daily_voucher_edit_snapshots` and removing the old table. FK audit still **8/8 FKs present**, **0 orphans**, **0 cross-project JV/CV detail/account links**. The user wants additional genuinely unused tables cleaned, without arbitrary schema proliferation.

**This batch: read-only whole-database inventory, NOT destructive removal.** New `app/Console/Commands/AuditUnusedTables.php`, command `php artisan schema:audit-unused-tables --json`, inspects **all tables in currently configured schema**, estimated row counts, exact row counts for unreferenced candidates, **incoming and outgoing FK relationships**, MySQL view and trigger references and application source matches under `app`, `routes`, `resources/views`, `resources/js`, `public/js`, and `config`. No other databases are queried or modified. Results include `REVIEW_EMPTY_UNREFERENCED`, `REVIEW_UNREFERENCED_WITH_DATA`, and `REFERENCED_OR_DEPENDENT`. The test `UnusedTableAuditSafetyTest` checks that this tool is read-only and requires evidence. **Current true row counts, current trigger/view dependencies and complete runtime-source matches are not available to GitHub-only review; owner must run it locally. No other table was removed in this batch.**

**Interpretation rules:** Zero records or absence of direct literal references does **NOT** prove a table is useless. Dynamic table names, queued jobs, integrations, reporting, historical migrations, raw SQL, offline consumers and accounting audit evidence require manual trace. Never drop a finance source, posting, reversal, claim, ledger, approval, receipt allocation, purchase inventory, audit/history, permission, migration or system table without confirming all consumers and preserving any relevant data. A candidate with rows is **not delete-ready**. Do not issue broad `DROP TABLE`, `migrate:fresh`, `db:wipe`, `TRUNCATE` or mass rollback. Only after a verified report and confirmed no consumers may a minimal, reversible, data-preserving migration be authored for specific tables. The owner explicitly prefers **reuse/consolidation of shared tables** rather than one new table per Edit/Delete feature.

**Owner next commands (READ ONLY; no browser test for this audit):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Console/Commands/AuditUnusedTables.php
php artisan test --filter=UnusedTableAuditSafetyTest
php artisan tinker --execute="dump(DB::connection()->getDatabaseName());"
php artisan schema:audit-unused-tables
php artisan schema:audit-unused-tables --json | Set-Content .\storage\app\unused-table-audit.json -Encoding UTF8
```

The concise terminal candidate table and the `storage/app/unused-table-audit.json` contain the required evidence for a **specific** cleanup batch. Do not invent a numeric “tables to delete” count before owner provides actual DB results. **Browser acceptance for Plot narration Edit remains pending separately.**

## 2026-11-18 — Owner full DB usage audit PASS; corrected false positives (LATEST)

**Owner ran** `schema:audit-unused-tables` on **`bluenew_clean_final`**: **147 tables / 536 runtime source files**, and `UnusedTableAuditSafetyTest` **1 PASS / 24 assertions**. Initial scan listed only three candidates: `landing_pages` (exact **0** rows, zero literal SQL references), `legacy_migration_history` (**14** rows), `personal_access_tokens` (**18** rows). **NO TABLES WERE DROPPED.**

**Crucial dependency verification:** The original candidate classification was too aggressive because SQL-string-only inspection misses Eloquent model inference and third-party framework consumers:
- **`landing_pages` MUST remain.** `app/Models/LandingPage.php` is an active Eloquent model with inferred table name, `LandingPageAdminController` reads/writes/drafts/publishes page data, and `routes/web.php` defines six admin actions for it. An empty table means public landing content is still on defaults, **not** that the feature is unused.
- **`personal_access_tokens` MUST remain.** `laravel/sanctum` is installed, and `SanctumTokenRegressionTest` creates/verifies/revokes bearer tokens for mobile API. The 18 token records also merit retention subject to explicit expiry policy; removing the table breaks login.
- **`legacy_migration_history` MUST remain.** 14 rows are historical migration evidence; zero first-party runtime matches is insufficient to justify deleting upgrade/reconciliation records. Historical migration files are present.
- Other **144 tables** had source and/or FK/view/trigger references according to initial scanner. This does not certify every table useful, but there is **no verified removable table** among the three flags.

**Implemented scanner accuracy repair (GitHub committed, new local run pending):** `AuditUnusedTables` now infers Eloquent conventional table names and explicit `$table` mappings from `app/Models`. It marks implicit model tables `ELOQUENT_MODEL_DEPENDENT`, and framework/migration infrastructure `FRAMEWORK_OR_HISTORY_PROTECTED`; these **never** appear as removal candidates. Added regression in `UnusedTableAuditSafetyTest` against real LandingPage, LandingPageAdminController and Sanctum test evidence. This is a **code/test fix only**, no migration, no schema changes or table drops. **Never use the initial report to delete `landing_pages`.**

**Verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Console/Commands/AuditUnusedTables.php
php artisan test --filter=UnusedTableAuditSafetyTest
php artisan schema:audit-unused-tables
```

Expected new classification: `landing_pages` **ELOQUENT_MODEL_DEPENDENT**, `legacy_migration_history` and `personal_access_tokens` **FRAMEWORK_OR_HISTORY_PROTECTED**. In this verified set there is **nothing safe to remove**; do not create a cleanup migration. The previously completed Plot-only redundant audit table cleanup remains successful. Browser test for Plot narration Edit is **still pending**, but this scanner fix requires no browser testing.

## 2026-11-18 — Milestone 03: Plot Receipt/Purchase same-ID full-field Edit (LATEST)

**Owner directive:** Stop shipping narration-only edit as if it were full voucher Edit. Speed up actual CRUD completion, reuse the existing Create forms and finance tables, keep one canonical handoff, report any browser test. **No new MySQL table or migration** in this batch.

**Code implemented on GitHub (owner acceptance pending):**
- New `app/Services/Finance/PlotVoucherEditor.php` updates **same source ID, posting batch ID, and posting line IDs** in one DB transaction; validates project ownership, active source, unreversed posting, active project accounts, balanced debit/credit, unique voucher numbers, valid bookings and inventory relationships.
- **Receipt:** updates voucher number/date/customer/amount/narration, cash account posting, each linked plot allocation and receivable credit posting. Requires same count of allocations as prior source. Snapshot of source/allocations/prior posting lines saved to existing `daily_voucher_edit_snapshots` with `status=plot_edit`; no new audit table.
- **Purchase:** updates number/date/seller/narration, area/rate/cost/inventory account/plot metadata and linked purchase-item and posting rows; locks actual existing plot IDs, refuses sold/booked/unavailable inventory, and keeps existing purchased plot count. Prior source/items/plots/posting lines captured in shared snapshot; no new table. No new physical plots or duplicate posting batch inserted.
- `VoucherController` has PATCH `updatePlotReceipt`/`updatePlotPurchase` with **separate Edit permissions**. Existing detail responses expose the source cash account and existing purchased-plot dimensions/definitions for form prefill. `routes/web.php` has source-scoped PATCH routes.
- Both Plot workspace templates use their **full existing Create modal** to load fields for **Edit voucher**, show Save voucher, AJAX PATCH and refresh listing, while keeping independent Create/Reverse authorization. The earlier narration-only endpoint remains for backward compatibility but is no longer the primary Edit button.
- Prior original Create request key no longer replays stale payload after same-source full Edit (`PlotEntryWriter::existingReplay` guards the shared `plot_edit` snapshot status).
- Added `PlotFullVoucherEditContractTest`, three integrated scenarios in `PlotEntryWriterTest` for receipt full-field rewrite/balance, purchase inventory/amount, rejected allocation-cardinality change with rollback, plus stale replay assertion.

**Honest limitations:** Editing **the number of allocated plot lines or purchased inventory rows** is not yet supported: those operations currently fail with a clear 409 and roll back without partial writes. Neither ordinary Plot Delete nor other remaining voucher families' full CRUD is delivered in this commit. Existing exact financial posting lines are modified **in place** under a transaction and before-image snapshot rather than physically deleted; this changes their original amounts, so accounting/provenance acceptance must explicitly confirm it meets owner policy before live deployment. No claim of completed Milestone 03 yet. Browser acceptance required for each full Edit flow.

**Follow-up quality fixes in the same implementation batch:** Full Receipt Details now reads the original cash and individual receivable accounts from the exact posting lines, so editing does not silently replace a historical account with the current default. Purchase Edit Details reads optional plot-definition columns conditionally for database portability. Added two more integration regressions: booked Purchase inventory and wrong-project Receipt edits fail without changing postings. Old original Create idempotency request keys now reject stale replay after full editing. The full form's Save preserves existing source/batch/plot IDs and does not add a table. **These new tests are code-committed only; owner local PASS is still pending.**

**Owner local gate:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Http/Controllers/Finance/VoucherController.php
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED** *after local tests PASS*, only with disposable local vouchers on selected project:
1. `/admin/finance/voucher/plot-receipt` → Actions → **Edit voucher**. Verify full existing fields prefill; change number/date/amount and a plot's allocation amount (do not add/remove allocation rows yet). **Save voucher** should close modal, update history by AJAX without page reload, preserve source ID and balanced account register.
2. `/admin/finance/voucher/plot-purchase` → Actions → **Edit voucher**. Verify full purchased-plot fields prefill; change plot description/area/rate, seller and date, Save; confirm amount/inventory metadata updated and original plot ID retained. Do not test on booked/sold real inventory.
3. Edit permission OFF while Reverse ON must hide Edit button, direct PATCH 403; wrong-project ID must reject. Changing number of allocation/plot rows should show 409 without changing original source or lines.
4. Report screenshots/logs for errors; owner browser PASS not yet received.

**Follow-on:** finish safe add/remove allocation/item line lifecycle, plot ordinary logical Delete, other voucher CRUD, then Milestone 04 linked cheque/online and 05 configurable reversal. Do not add new tables for these unless evidence demonstrates existing schema cannot safely represent source and history.

## 2026-11-18 — Plot Receipt/Purchase ordinary logical Delete (LATEST)

**GitHub implementation, not owner-accepted yet:**
- `PlotEntryLifecycleService::archive` performs **ordinary Delete** of active Plot Receipt/Purchase inside a DB transaction: locks same-project source, uses the established exact linked-posting compensation writer internally to neutralize debit/credit effects, retires unbooked/unsold purchased plot inventory, and marks the original voucher `is_active=0`, `deleted_at=now()`. **It never hard-deletes financial source, allocation, purchase item or posting rows.** If any step fails, the transaction rolls back.
- `VoucherController::deletePlotReceipt` and `deletePlotPurchase` enforce **independent `finance plot-receipt delete` / `finance plot-purchase delete` permissions**. Their DELETE routes and both directory Actions add jQuery AJAX Delete + confirmation/reason modal. Users do not need Reverse permission merely to perform ordinary Delete. Archive supports future-dated original posting through a chronological compensation date and normalizes user-entered multiline reasons.
- New `PlotEntryWriterTest` SQLite integration tests cover Receipt archive (source inactive, old allocations retained, original and compensating batches offset, second Delete rejected) and Purchase archive (source + unsold plot inactive, financial balancing, retained item evidence); `PlotFullVoucherEditContractTest` additionally covers independent Delete permissions/routes/modal.
- Full-field Edit from the same batch remains: original Create modal prefilled, same source and existing posting IDs, changed amounts/dates/accounts/plots, shared before/after snapshot; source/account lookups project scoped and stale original request key replay forbidden. Additional tests reject booked Plot Purchase updates and cross-project receipt updates.

**Still NOT complete:** The present Edit does not support **changing the number of Receipt allocations or Purchase items**. It returns a conflict instead of hard-deleting line evidence or creating wrong mappings. This requires a safe per-line lifecycle extension. Other voucher modules' full CRUD are not implemented by this batch, so **Milestone 03 remains PARTIAL, pending local tests and browser acceptance**. Historical original posting amounts are updated under same-ID Edit with shared before-image snapshot; confirm reconciliation expectations before using real data. No new database table or migration in this batch.

**Exact owner PowerShell verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Services/Finance/PlotEntryLifecycleService.php
php -l app/Http/Controllers/Finance/VoucherController.php
php artisan view:cache
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED after automated tests PASS:** Use **disposable vouchers in local selected project**.
- `/admin/finance/voucher/plot-receipt`: Actions→Edit voucher; entire form prefilled (number, date, customer, account, amount via allocations, narration), change amount and other fields, Save; expect AJAX update and same voucher ID/balanced posting. Actions→Delete; enter reason; expect confirmation close, original hidden from active history, account balance neutralized and original posting evidence retained.
- `/admin/finance/voucher/plot-purchase`: Edit voucher existing purchased plot metadata/area/rate/seller; Save; expect same original plot ID, purchase cost and source history updated; Delete an unbooked unsold test purchase and expect source/plot inactive plus compensated account balance.
- Independent permissions: turn Edit off while Delete on and Reverse off; Edit hidden, Delete works, direct PATCH 403. Turn Delete off: Delete button hidden, direct DELETE 403. A booked/sold purchase must be rejected without changes. No full-page reload or raw error page; toast/modal error only.
- Record browser PASS/FAIL separately; **NO browser acceptance yet reported for new full Edit/Delete** (previous narration-only browser pass does not cover it).

## 2026-11-18 — Plot full Edit: allocation/item add-remove lifecycle (LATEST)

**Supersedes the earlier same-count Edit limitation.** This subsequent code batch implements adding/removing existing Plot Receipt allocations and Plot Purchase items while retaining source ID and financial integrity. **No new tables**; one idempotent schema migration only adds `is_active` default true to `finance_plot_receipt_allocations`, `finance_plot_purchase_items`, and shared `finance_posting_lines`. Already-stored records remain active. This migration has **NOT** run on owner MySQL yet.

- `PlotVoucherEditor::replaceLines` now updates debit/credit sides independently, keeps old cash/seller posting-line IDs, inserts additional active posting lines for new allocations/inventory, and archives removed posting lines with `is_active=0` and **zero debit/credit** (so historic inactive legs cannot distort balances). Full before-image is kept in existing shared `daily_voucher_edit_snapshots`; no destructive child-row deletion.
- `receipt` can add/reassign/remove multiple plot allocations. No longer requires fixed count. Old removed allocations stay physically present with `is_active=0`; newly selected ones link to same receipt. The original source, posting batch, project and balance remain consistent. `PlotEntryWriter::receipt` filters inactive allocation rows.
- `purchase` now uses `legacy_plot_id` to retain precise existing plot identity, allows new purchased plots via the existing validated `PlotEntryWriter::createAvailablePlot` method, and archives removed purchased plot items and unsold/unbooked plot inventory logically. It refuses duplicates or booked/sold inventory. `purchaseDetails` and purchase list counts filter inactive items. Purchase Edit form includes hidden linked plot IDs and enables Add/Remove controls.
- `PostingReversalWriter::reverse` now ignores inactive (zeroed) historical posting lines, and `PlotEntryLifecycleService::reverse` checks only active purchased plot items for reversal/archive inventory.
- Integration fixture now installs the line lifecycle columns. New tests exercise Receipt allocation addition and later archive (balances, source ID and before-image history), Purchase plot addition and later archive (original plot ID retained for active item, removed plot inactive, account balance maintained), plus existing linked-CRUD and Delete regressions.
- **No local PHP tests or real browser tests have yet been executed by assistant for this newest extension.** Previous verified Owner PASS (30 tests/185 assertions) applies only to the earlier schema cleanup, not the new Plot full Edit/Delete batch.

**Final same-turn UI guard:** While Plot Receipt Edit is open, the optional booking search is disabled so table rerender cannot silently discard or restore changed allocation selections; normal Create mode still supports the search. Purchase Edit uses a hidden `legacy_plot_id` to distinguish an explicitly new row from an existing plot (including empty-string-to-null middleware conversion). No new table was added. All new changes still await the owner's local PHP and browser pass.

**Additional code safety checks in this same batch:** Updated `FinanceActionPermission` route action map for both Plot Edit/Delete/narration endpoints, so granular permission middleware correctly recognizes the new routes. `PlotPurchaseReconciliation` now excludes archived inactive purchase items and posting lines; the dynamic Purchase integration test asserts a clean reconciliation after add/remove. `PlotFullVoucherEditContractTest` verifies the migration adds **columns only**, zeroed archived lines, and that reversal/audit consider only active evidence. Runtime line changes, extra regression tests, and the migration are committed but remain unverified locally. No manual database cleanup or earlier migration rerun is required.

**Final UI feedback pass:** Plot Receipt and Plot Purchase now call the common `$.financeVoucherFeedback.toast` (or toastr/fallback) for visible Save/Delete success; receipt success no longer hides inside its closed modal. Inline validation errors remain visible inside form modal. There are still no new tables. This is code-committed but local/browser acceptance pending.

**Important deploy order:** Because the new tests use the migration in disposable SQLite fixtures, first verify test commands below; then confirm the connected DB is `bluenew_clean_final`; apply the NEW `190000` migration once; then perform browser acceptance against disposable local Plot vouchers, not live financial sources. Do NOT rerun old 150000/160000/170000/180000 migrations, nor migrate:fresh.

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Services/Finance/PlotEntryLifecycleService.php
php -l app/Services/Finance/PlotEntryWriter.php
php -l app/Services/Finance/PostingReversalWriter.php
php -l database/migrations/2026_11_18_190000_add_plot_detail_lifecycle_columns.php
php artisan view:cache
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan tinker --execute="dump(DB::connection()->getDatabaseName());"
php artisan migrate --path=database/migrations/2026_11_18_190000_add_plot_detail_lifecycle_columns.php
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED after tests/migration PASS:**
1. Plot Receipt on `/admin/finance/voucher/plot-receipt`: Edit full voucher; change date/account/amount and **add second booking allocation**, save and verify balanced posting; reopen and remove it, save again; source ID unchanged and inactive historical allocation excluded from details.
2. Plot Purchase on `/admin/finance/voucher/plot-purchase`: Edit; **Add plot**, set unique code, definition/road/facing, area/rate/inventory account; save and verify new linked plot ID. Reopen and remove a **not-booked/not-sold** plot, save; removed inventory inactive, original record/audit preserved and active price updated. Reverse/ordinary Delete should preserve balance and must reject booked/sold plots.
3. Test Edit/Reverse/Delete permissions independently; ensure no full-page reload, server errors shown inside modal and the history refreshed through AJAX.
4. Confirm original JV/CV/CR/etc voucher behavior and reporting are not negatively affected by the shared `finance_posting_lines.is_active` column. Broader Milestone 03 acceptance (other voucher families) is still pending; do not declare whole milestone complete.

## 2026-11-18 — Owner Plot full Edit test gate: UNIQUE allocation correction (LATEST)

**Owner local results at commit `f4262105` (database `bluenew_clean_final`):** `PlotFullVoucherEditContractTest` **4 PASS / 78 assertions**, `PlotEntryWriterTest` **35 PASS, 1 FAIL / 214 assertions**. All requested Plot PHP files passed lint and `view:cache` passed. Failed test: `full receipt edit adds and archives allocation rows without hard delete`. SQLite `UNIQUE(receipt_id, booking_id)` rejected an attempted positional reassignment of row #1 to booking #32 while booking #32 already existed. **This is an application matching bug, not a redundant database constraint.** No schema migration has been reported as executed yet for the latest Plot line lifecycle.

**Code repair after owner output (GitHub committed; owner retest pending):**
- `PlotVoucherEditor::receipt` no longer updates allocation child rows by array index. It locks all receipt allocations, indexes by **booking_id**, deactivates old active bookings that have been removed, updates matching booking records, **reactivates an archived booking row** if re-added, and inserts only genuinely unseen booking IDs. This respects the existing composite unique key and preserves same-child identity.
- A changed set of booking IDs requires the Plot lifecycle `is_active` migration, **even if allocation counts happen to be equal**. Editing existing booking amounts without a membership change remains supported on older schema.
- `PlotEntryWriterTest` integration test now verifies **add booking 32 → archive booking 31 → restore booking 31**, preserving its original allocation row ID, exactly 2 unique booking rows, balanced account credits, same posting batch and 3 edit snapshots. No new table, column or migration created in this repair.
- **Do not report PHP tests PASS until owner terminal confirms.** The earlier 35 PASS/1 FAIL is the only verified feature-test outcome.

**Local verification (first, no migration):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
```

If the tests pass, confirm the configured database and inspect the shared-posting impact before applying the existing `2026_11_18_190000_add_plot_detail_lifecycle_columns.php` migration. **Do not add or drop any new table.** Specifically, `finance_posting_lines` is shared across voucher series: audit readers/reporters that sum all lines must be checked for correct inactive-line treatment. The migration itself adds `is_active` to 3 existing tables only and has not yet been owner-applied. After a safe migration and accounting report checks, **browser test is required** for complete Plot Receipt/Purchase Edit, dynamic row add/remove and Delete; previous narration-only browser PASS does not cover it.

## Remaining Milestone 03 work — do not mark complete yet

1. Rerun `PlotEntryWriterTest` on local; resolve any subsequent actual assertion failures. Verify Plot History/Details/Replay after root logical archive.
2. Implement **real atomic Plot Receipt/Purchase Create/Edit/Delete** across sources, receipt allocations, purchased plot inventory, linked finance posting batches/lines and downstream balances. The current `PlotEntryLifecycleService::reverse` is a **separate reversal operation**, not a substitute for ordinary delete. No new V2 isolation, no hard deletes of business records and no silent reversal-only restriction.
3. Review and implement remaining editable/soft-deletable **Bank Deposit/Withdrawal, Extra Charges, typed JV/CV, SV, CR/DR/CCR/CDR/BOR/BOP** workflows, including project-scoped linked relationships and independent action permissions. Retain immutable audit/history evidence.
4. Classify other tables that lack both lifecycle columns, but add them **only where business operations need logical deletion**; audit/event/metadata tables should not be blanket altered.
5. Run representative browser Create/Edit/Delete and financial balance/relationship checks; get owner PASS. Milestone 02's remaining permission browser acceptance also stays explicit.

## Exact next commands for local Windows PowerShell

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php
php -l database/local_finance_migrations/2026_11_14_000100_create_finance_plot_receipt_storage.php
php -l database/local_finance_migrations/2026_11_14_000101_repair_finance_plot_receipt_claim_fk.php
php -l database/local_finance_migrations/2026_11_14_000200_create_finance_plot_purchase_storage.php
php artisan test --filter=PlotLegacyMigrationCompatibilityTest
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotVoucherLifecycleColumnsTest
php artisan finance:audit-voucher-schema --require-fks
```

**No database migration required for this latest fixture/test repair.** Do not run `migrate:fresh`, `db:wipe`, destructive rollbacks, or prior one-off Plot/FK migrations again. The feature test fixture uses in-memory SQLite; it should not mutate local MySQL.

## Requirements every next turn must respect

- Implement **code and tests first**, then update this exact handoff in the same response; do not return with only progress text or another status document.
- Server-side granular voucher permissions for View/Create/Edit/Delete/Approve/Reject/Reverse/Print etc. No old generic grant silently overriding an independently denied action.
- First-party DOM/AJAX uses jQuery only; shared CR/DR design system, colored skeleton loading, compact table cards/filters, Tom Select, modal/toast errors, AJAX CRUD, no whole-page loader/reload.
- Any configured database should work via `.env` without special database-name isolation, custom V2 modules, or renamed copies of finance.
- Financial mutations must be transactional, source-linked, project-scoped, and never hard-delete business rows. Maintain account/ledger integrity and preserve immutable evidence.
- Separate code-committed, user terminal PASS, browser PASS, and pending acceptance in the progress report.


## 2026-11-18 — Owner confirmation: Plot full Edit/Delete browser gate PASS

**Owner local confirmation:** `bluenew_clean_final` selected; `2026_11_18_190000_add_plot_detail_lifecycle_columns.php` migration **DONE**. `finance:audit-voucher-schema --require-fks` reported **8/8 foreign keys present, 0 orphan references, 0 cross-project JV/CV account links**. The lifecycle migration adds `is_active` to three existing tables (`finance_plot_receipt_allocations`, `finance_plot_purchase_items`, `finance_posting_lines`), no new tables.

**Automated local gate:** `PlotFullVoucherEditContractTest` **4 PASS / 78 assertions**, `PlotEntryWriterTest` **36 PASS / 226 assertions**, combined **40 PASS / 304 assertions**; editor PHP lint PASS.

**Owner reports Plot Receipt and Plot Purchase browser tests PASS.** Treat their full Edit/Delete browser acceptance as owner-confirmed. Do not request the same browser gate again without a new change that affects it. This does not independently establish that all shared financial reports were regression-tested or that remaining voucher families have complete CRUD acceptance.

**Next engineering priorities:** (1) inspect readers and reporters of shared `finance_posting_lines` and validate inactive-row semantics and balances across unrelated voucher families; (2) proceed with Milestone 03 remaining voucher Create/Edit/Delete workflows and project-scoped account integrity; (3) keep full application AJAX, common modal/filter/dropdown/skeleton/toast patterns consistent; (4) update this single README after each actual implementation batch. **Milestone 03 remains PARTIAL overall.**


## 2026-11-18 — Milestone 03 continuation: Plot Receipt duplicate booking guard

**Owner acceptance carried forward:** Plot Receipt/Purchase full Edit/Delete browser tests PASS; configured `bluenew_clean_final` lifecycle migration `2026_11_18_190000` DONE; 8/8 FK integrity, no orphans or cross-project JV/CV links; 40/40 automated Plot tests (304 assertions) passed **before this new code batch**.

**Code change committed:** `PlotVoucherEditor::receipt` now detects duplicate `booking_id` values in an Edit allocation payload even if the repeated booking appears with a different plot. Previously the code checked duplicated `plot_id` only; a repeated booking could reach the `(receipt_id, booking_id)` unique constraint, leading to a database exception rather than a clear business validation error. The editor now throws a `DomainException` before writing the updated lines, and the existing transaction protects financial state. `PlotEntryWriterTest` includes a new integration regression that asserts a duplicate-booking edit refuses the write and leaves voucher number, allocation rows, posting lines and edit snapshots unchanged. **No DB schema migration, new table, or UI change.**

**Verification required (not yet locally executed for this new regression):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Services/Finance/PlotVoucherEditor.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

**Milestone 03 is still PARTIAL**: shared posting-line inactive handling and cross-family reports need verification, and other voucher families require lifecycle CRUD completion. Do not repeat prior Plot browser acceptance without relevant UI change. Do not claim new tests have passed before the owner runs them.


## 2026-11-18 — Plot full Edit related-record identity hardening (new local gate)

**Owner verified preceding batch:** PlotEntryWriterTest 37 PASS / 231 assertions, PlotFullVoucherEditContractTest 4 PASS / 78 assertions, total 41 PASS / 309 assertions, plus previous Plot browser acceptance. This predates the following code changes.

**Implemented on GitHub:**
- Posting legs are matched by original source identity: booking_id for Plot Receipt credit legs, legacy_plot_id for Plot Purchase debit legs. Row reordering no longer overwrites another source's active posting line. Unchanged sources retain same posting line ID; truly new related sources append within the original batch; removed legs are deactivated, not hard-deleted.
- Existing Plot Purchase items require their explicit legacy_plot_id on Edit. Missing ID signifies a genuinely new item and no longer silently reuses an existing plot by array index. This avoids writing changes to an unrelated plot when the form is reordered.
- Plot Receipt Details prefill now excludes inactive posting lines so archived credit legs are not treated as active accounts.
- Added SQLite integration regression tests for repeated reordered Plot Receipt/Purchase edits, unchanged source IDs, allocation/item/plot IDs and posting line IDs, balanced amounts and no duplicate posting batch. Existing replacement test now expects archived old and appended new credit leg instead of recycling its ID, and existing purchase edit test supplies explicit related plot ID.

**Scope/limitations:** no new table or migration. An identical Save may still create an additional audit snapshot (not an additional business voucher or posting batch). Previously archived posting lines are not reactivated when a removed booking is reintroduced; an additional active leg may be created while inactive history is retained. A persistent allocation-to-posting-line association would be needed to safely reactivate the exact historical line across arbitrary account changes. Broader financial reports and other voucher CRUD flows remain pending.

**New code requires owner local tests (NOT verified locally by this assistant):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Http/Controllers/Finance/VoucherController.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

No additional migration and no repeated browser gate unless relevant UI regression appears.


## 2026-11-18 — Owner regression follow-up: booked Purchase test identity corrected

**Owner latest local gate on preceding commit:** PlotEntryWriterTest 38 PASS / 1 FAIL (244 assertions), PlotFullVoucherEditContractTest 4 PASS / 78 assertions; PHP lint passed. The only failure was the booked-plot edit test's expected DomainException text. After positional fallback removal, this test submitted an existing purchased plot without its `legacy_plot_id`, so the service correctly interpreted it as new inventory and refused to remove the booked original, producing a different business error. This is a test payload mismatch, not evidence that booked inventory was mutated.

**Targeted correction committed:** Updated booked-plot regression input to pass the actual linked `legacy_plot_id`. This exercises the intended existing-plot edit branch and verifies the explicit booked-plot guard, rollback of source and posting balance, and no audit snapshot. Application service and database schema were not changed in this batch. Owner retest is pending.

**Local gate:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

Do not report 43 PASS until terminal output confirms. No migration or browser re-test needed for this test-only correction.


## 2026-11-18 — Milestone 03 relationship integrity continuation (code batch; owner gate pending)

**Previous owner pass:** PlotEntryWriterTest 39 PASS / 248 assertions and PlotFullVoucherEditContractTest 4 PASS / 78 assertions, combined 43 PASS / 326 assertions. This pass preceded the changes below.

**Implemented and committed:**
- Plot Receipt Details AJAX prefill no longer derives receivable accounts from posting-line array offsets. It selects active posting legs scoped to the same source batch and project, matches credit account by actual customer and plot relationship, and resolves cash leg separately. A missing/ambiguous leg returns a JSON 409 rather than silently pre-filling another plot's account. This fixes a potential wrong-account Edit after source rows are removed and new posting legs are appended.
- Plot Receipt/Purchase full Edit now verifies old active posting amounts and related account/plot identity against active allocation and inventory item rows before changing anything. A corrupted historical linkage throws a business conflict inside the existing transaction, preserving the source and audit history. This specifically guards against overwriting an unrelated record in a corrupted or reordered source.
- Added two isolated SQLite integration regression tests: corrupt Receipt credit account/plot relationship and corrupt Purchase inventory debit amount both refuse Edit, preserve original source, and create no edit snapshot or second batch. Added a contract regression ensuring Receipt AJAX prefill never uses posting array indexing.
- Reused existing source, financial tables and lifecycle columns; no new migration or table, no destructive edits. Previous full Plot browser acceptance remains recorded, but this batch affects prefill so a focused browser check may be warranted after local tests.

**New owner verification (NOT YET EXECUTED):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Http/Controllers/Finance/VoucherController.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

**Remaining scope:** overall Milestone 03 cross-family voucher CRUD and shared financial reporting regression are still not certified complete. Do not imply this focused integrity batch completes those unrelated modules. No new DB migration needed.


## 2026-11-18 — Follow-on: historical posting line identity after archive/restore

Further source inspection found a crucial edge case in the new integrity guard: after an allocation was archived then restored, the allocation row ID may be older than the new active posting line ID. An index-based old-line check would reject a valid subsequent Edit. This was corrected before owner testing.

**Committed code fix:** Existing Receipt credit legs are now matched against active allocation rows by project/customer/plot account relation and exact amount, not line sequence; original posting-line ID maps to the matching booking_id for the replacement operation. Existing Purchase inventory debit legs are matched by linked source item's unique plot-code description, inventory account and amount, and mapped to the matching legacy_plot_id regardless of row order. Ambiguous/mismatched original evidence fails safely before writes; no extra posting batch. Historic lines remain deactivated, not hard-deleted.

**Regression extensions:** The existing add/archive/restore Receipt test now performs one more valid Edit after restoring the older booking and asserts balance, original allocation row count and single batch. The existing Purchase dynamic add/archive test likewise performs an additional Edit after removing a prior plot and verifies no duplicate purchase items or batch.

**Owner verification pending:** Run the PlotEntryWriterTest and PlotFullVoucherEditContractTest commands in the preceding section after pulling latest branch. This is a code/test change, not a new migration. No new SQL tables or columns.


## 2026-11-18 — Owner accepted Plot relationship browser tests + cross-series posting audit batch

**Owner acceptance:** Local `bluenew_clean_final` browser scenarios 1) Plot Receipt add/remove/restore + correct receivable account prefill, 2) Plot Purchase existing record Update/no duplicate inventory, and 3) AJAX validation error without full-page reload were all explicitly confirmed PASS. The immediately preceding owner terminal results were `PlotEntryWriterTest` **41 PASS / 263 assertions**, `PlotFullVoucherEditContractTest` **5 PASS / 88 assertions** (46 total / 351 assertions); `view:cache` and PHP lint passed. Do not request these browser tests again without relevant UX changes.

**This GitHub implementation batch (new local verification PENDING):**
- `PlotPurchaseReconciliation::audit` excludes logically deleted (`is_active=0` or `deleted_at` set) Plot Purchase roots from *active* inventory reconciliation. The original source, posting, reversal and item evidence remain stored; no financial mutation occurs. Extended Plot Purchase Delete integration test to assert no false-positive active inventory findings after archive.
- Added reusable read-only `PostingBatchIntegrityAudit`, which scans every posted series (including reversal batches) for a requested project, considers only active posting legs, checks debit/credit positivity and equal amounts, verifies account project ownership, and reports invalid or missing line legs. It does not change rows or introduce a table.
- Added Artisan command `finance:audit-posting-balances --project=N` (`--json` optional). Explicit project scoping is required to avoid accidental cross-project reporting. It exits nonzero if findings exist. This is an integrity check; it does not certify every voucher module's business lifecycle or replace full reports.
- Added isolated integration regression for a valid Receipt and Purchase pair, tampered financial credit, restored balanced posting, an inactive credit leg, and another project's empty result. It checks that no extra posting batch is created.

**Owner local gate (no migration or browser test for backend/read-only change):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l app/Console/Commands/AuditPostingBatchIntegrity.php
php -l app/Services/Finance/PlotPurchaseReconciliation.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances --project=3
```

The example project ID 3 is only valid if project 3 exists in the user's current DB; use the selected local project ID. Audit is read-only. Do not misreport failures if actual historical data is inconsistent; investigate each batch first.

**Roadmap:** Milestone 03 remains PARTIAL. This batch advances shared posting integrity and Plot reconciliation, but remaining JV/CV/cash/stock/etc full CRUD and permissions browser acceptance are not certified, and Milestones 04/05 are pending. The preceding browser gate is closed and does not require repetition.


## 2026-11-18 — Owner cross-series audit PASS; reversal source-state integrity extension

**Owner verified on configured local database `bluenew_clean_final`:** latest `PlotEntryWriterTest` **42 PASS / 275 assertions**; `PlotFullVoucherEditContractTest` **5 PASS / 88 assertions**, combined **47 PASS / 363 assertions**. PHP lint for PostingBatchIntegrityAudit, AuditPostingBatchIntegrity and PlotPurchaseReconciliation PASS. Read-only `php artisan finance:audit-posting-balances --project=3` returned **no imbalanced or invalid active posting batches**. This certifies the checked active batches in project 3, not every project or every voucher CRUD workflow. Previously owner confirmed all three Plot relationship browser scenarios PASS.

**Further implementation this turn (GitHub committed, new local gate pending):** Extended existing `PostingBatchIntegrityAudit` without schema changes to validate accounting reversal source integrity in addition to active posting totals: any batch with `reversal_of_batch_id` must refer to an existing, posted, non-reversal original in the same project, and carry voucher series `REV`; a `REV` batch with no original reference is flagged. This helps prevent cross-project or orphan reversal anomalies from appearing clean merely because debit and credit totals happen to balance. All checks are read-only; business rows unchanged.

**Regression:** Added a test to `PlotEntryWriterTest` that temporarily labels a valid balanced receipt batch `REV` without original linkage, checks that audit flags `REVERSAL_ORIGINAL_MISSING`, restores series, and verifies clean audit. Earlier Project 3 audit success predates this change and must not be represented as a retest of it.

**Owner verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances --project=3
```

**Milestone 03 remains PARTIAL:** pending comprehensive remaining voucher family Create/Edit/Delete and shared reporting checks; the new audit extends safety but does not implement those families. No new DB migration and no browser gate for this read-only change.


## 2026-11-18 — All-project posting audit default (owner correction)

**Owner local test gate PASS:** PlotEntryWriterTest 42 PASS / 278 assertions and PlotFullVoucherEditContractTest 5 PASS / 88 assertions, total **47 PASS / 366 assertions**. PHP lint PASS. `finance:audit-posting-balances --project=3` on the configured local database reported no active posting anomalies in project 3; that is a project-specific observation only. The owner then ran `finance:audit-posting-balances` and rightly received `Supply an explicit positive project ID`, contradicting the required default all-project behavior.

**Code fix committed:** `app/Console/Commands/AuditPostingBatchIntegrity.php` now treats `--project` as optional. Without it, audits every project ID from the **currently configured** database `projects` table plus any project IDs present in posted batches; with it, still audits only the requested ID. Results include `project_id` for each finding, the number of projects scanned, and the selected project (null if all) in JSON output. No project/database name hard-coded and no schema or data mutation. Existing balance/relationship audit service still enforces exact project scope per iteration.

**New isolated integration regression:** Covers the default all-project command for two configured test projects, detects a deliberately tampered voucher in the proper project, confirms an explicit other-project filter stays clean, and validates rejection of a malformed project ID.

**New owner local gate (NOT yet verified for this code change):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Console/Commands/AuditPostingBatchIntegrity.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances
```

`finance:audit-posting-balances --project=N` remains optional and read-only. Do not demand project 3 or a specific database name. Browser test and migration are not required for this command-only change. Milestone 03 cross-family CRUD acceptance remains pending.


## 2026-11-18 — Owner verified 48/48, six-project posting audit and shared Daily Voucher Directory correction

**Verified by owner:** `PlotEntryWriterTest` **43 PASS / 291 assertions**, `PlotFullVoucherEditContractTest` **5 PASS / 88 assertions**: **48 PASS / 379 assertions**, zero failures. PHP lint PASS. `php artisan finance:audit-posting-balances` checked **all 6 projects on current configured database**, zero imbalanced/invalid active posting batches. All-project default behavior is now verified and no per-project special casing required. Earlier Plot browser acceptance is still PASS.

**Next actual application code batch, GitHub committed (new local verification pending):**
- Fixed shared `DailyVoucherDirectoryController` Plot Receipt/Purchase listing to exclude logically archived source rows (`is_active=0` / `deleted_at` present), avoiding deleted vouchers showing as active records in the unified Daily Voucher Directory. Existing source and accounting evidence remains in storage.
- Fixed account filters in typed bank-transfer and Plot Receipt/Purchase directory sections to consider **only active finance posting lines**; previously an archived line could still match an account filter and produce misleading results after full voucher Edit.
- Fixed `postingLines()` used by the shared directory's voucher detail output to exclude inactive financial legs so retired credit/debit rows do not display as active. Project/account join constraints remain.
- Added `DailyVoucherDirectoryPostingLifecycleContractTest` to prevent regression of archived-source and inactive-line filters in both typed source listing and directory detail output. No new database tables, migrations, hard delete, or DB-name/project-specific behavior.

**New verification commands (not run locally by GitHub connector):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Http/Controllers/Finance/DailyVoucherDirectoryController.php
php -l tests/Unit/Finance/DailyVoucherDirectoryPostingLifecycleContractTest.php
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances
```

**Focused browser check required** because the unified Daily Voucher Directory listing/filter UI is affected: open that page in a disposable local project with an archived Plot Receipt/Purchase and an active edited voucher containing retired finance legs; confirm logically archived voucher is absent in active register, account filters ignore retired legs and voucher detail lines only show active posting legs. Previous Plot create/edit/delete browser tests need not be repeated.

**Reality check:** Milestone 03 is still partial across other voucher CRUD families (JV/CV, cash, stock, cheque/bank, extra charges), and Milestones 04 and 05 are not accepted. A clean balance audit does not prove those Create/Edit/Delete paths. Do not claim the entire remaining batch has been completed.


## 2026-11-18 — Owner verified Daily Voucher directory contract; JV/CV related-update hardening batch

**Owner verification carried forward:** new `DailyVoucherDirectoryPostingLifecycleContractTest` **1 PASS / 12 assertions**, Daily Voucher Directory PHP lint PASS. `php artisan finance:audit-posting-balances` checked **all 6 projects, zero imbalance/invalid active posted batches**. Prior Plot tests **48 PASS / 379 assertions** and all three Plot browser cases PASS. The new contract PASS does not certify the changed directory browser UI; its focused browser gate remains open.

**New code committed this batch:**
- Legacy JV and CV Update now call `VoucherUnchangedUpdateDetector` after locking current voucher/its details, before retiring related ledger rows. Identical repeated Save preserves same existing header, detail and active ledger IDs instead of creating another generation of financial/history rows. A changed date, reference, narration, account, side, amount or line order still invokes the existing audited transactional correction path. Validator also checks stored header debit and credit totals if available.
- Normal Commission draft `updateDraft` now applies the same no-op check before archiving active draft details and re-inserting replacements, preventing unnecessary duplicate history on identical repeated Edit. No-op keeps existing detail identities; material edits still archive/replace as before.
- Added nullable `ledgers.commision_voucher_detail_id` with a MySQL RESTRICT FK to `commision_voucher_details` via additive migration `2026_11_18_220000_link_commission_details_to_ledger.php`. Existing ledger rows and tables remain intact; old CV ledger rows with a NULL detail link are supported through existing exact account/amount matching. No V2 database isolation or database hard-coding. The down migration intentionally preserves the accounting link rather than dropping history.
- Legacy Commission Update now persists each new ledger leg's specific newly created commission detail ID (when column exists), matching JV detail linkage. `LinkedVoucherLedgerRetirement` validates the attached CV detail belongs to that voucher, is unique across active legs, and has the same account and debit/credit values, before any retirement. Legacy NULL details continue to validate through exact account/amount multiset.
- Added `VoucherUnchangedUpdateDetectorTest`, `CommissionVoucherDetailRelationContractTest`, and extended `LinkedVoucherLedgerRetirementTest` to cover exact CV detail IDs, swapped/duplicate detail links and legacy NULL fallback. New tests and migration have NOT been executed locally by GitHub connector. Do not claim this batch PASS before the owner runs it.

**New migration needed once on local DB before browser verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/VoucherUnchangedUpdateDetector.php
php -l app/Services/Finance/LinkedVoucherLedgerRetirement.php
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l database/migrations/2026_11_18_220000_link_commission_details_to_ledger.php
php artisan test --filter=VoucherUnchangedUpdateDetectorTest
php artisan test --filter=CommissionVoucherDetailRelationContractTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan migrate --path=database/migrations/2026_11_18_220000_link_commission_details_to_ledger.php
php artisan finance:audit-posting-balances
```

**Acceptance:** after local tests and migration, test normal JV/CV and Commission draft Edit with identical Save twice, then actual value change, confirming no extra details or active ledgers on no-op; original IDs preserved. Milestone 03 overall remains PARTIAL: additional cash/bank/stock/extra-charge lifecycle and cross-module permission/browser acceptance are not yet complete. Milestones 04 and 05 pending. Do not re-run old Plot browser tests.


### Follow-up in same CV/JV edit safety batch

- Commission draft no-op guard now uses `array_key_exists` when resolving optional `reference` / `description`, so deliberately clearing a field to `null` is recognized as a real change rather than skipped.
- The Commission relation contract test was corrected to use literal PHP variable tokens in its static string assertions and now checks that nullable draft metadata is handled explicitly.
- No extra migration was added after the single additive `2026_11_18_220000_link_commission_details_to_ledger.php` migration listed above. The new code has not yet passed owner-local lint/test/migration gates; do not claim full Milestone 03 complete.


## 2026-11-18 — Commission Voucher draft/approval Ledger parity with JV (code batch; owner validation pending)

**User evidence:** Owner's local phpMyAdmin screenshot shows `commision_vouchers.id=1` in status `draft`, two active `commision_voucher_details` rows (1000 debit, 1000 credit), and zero `ledgers` rows for `type='CV'` / `type_id=1`. Code review confirms `CommissionDraftWorkflow::createDraft()` previously persisted only header + details; `CommissionPostingWriter::post()` posted to `finance_posting_lines` without creating legacy `ledgers` legs. This was a genuine functional gap relative to the requested JV-like three-table design.

**Implementation committed:**
- Added `CommissionLedgerSynchronizer` to mirror CV detail rows into `ledgers` with a **real enforced database FK** `commision_voucher_detail_id` (column added by the already owner-applied migration `2026_11_18_220000_link_commission_details_to_ledger.php`). MySQL `information_schema` and SQLite FK metadata checks refuse work when FK is not actually enforced. It validates the source project, exact voucher-detail ownership, account, amount and unique detail identity, never matching simply by row order.
- Draft Create now stores header + detail rows + one inactive (`is_active=0`) legacy ledger row per active detail in the same transaction. Draft Update keeps the same ledger and detail IDs on a no-op; a real detail edit archives old detail rows, retains their inactive ledger evidence, writes new details, and inserts new inactive FK-linked ledgers. Draft Cancel checks relationships and logically deletes source/details; no hard deletion of linked ledgers.
- Approve/Post uses existing immutable `finance_posting_batches` / `finance_posting_lines` writer, then validates/activates the exact current CV legacy ledger legs (`is_active=1`) in **the same transaction**. Retry does not duplicate the legacy ledger IDs. A failed posting or failed FK/amount integrity check rolls back approval and ledger transition together; a reversed CV cannot be reactivated through this path.
- Posted CV Reversal also deactivates that CV's FK-linked active legacy ledger legs in the same reversal transaction, retaining both posting and ledger history. This is required so legacy ledger balance readers don't keep counting a reversed CV.
- Added `finance:sync-commission-draft-ledgers` (default read-only preview, optional `--apply` to create only missing inactive rows for existing unposted drafts, across all projects in configured DB; `--project=N` optional). The already-existing CV ID 1 in screenshot can be repaired this way without any SQL inserts or hardcoded database names. The command acquires project/voucher locks, is idempotent per detail FK, and reports any integrity failure without cross-source mutation.
- Added isolated `CommissionLedgerSynchronizerTest` covering Draft Create, exact FK, no-op Edit, changed Draft Edit, activation after posted batch, duplicate prevention, reversal deactivation, existing draft backfill and command dry-run/idempotency, plus `CommissionLedgerActivationContractTest` for workflow integration. These new tests are **not yet locally verified**; do not mark them PASS until owner output is supplied.

**Owner commands (no new migration; previous Commission ledger FK migration already DONE):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/CommissionLedgerSynchronizer.php
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Services/Finance/VoucherLifecycleService.php
php -l app/Console/Commands/SyncCommissionDraftLedgers.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=CommissionVoucherDetailRelationContractTest
php artisan finance:sync-commission-draft-ledgers
php artisan finance:sync-commission-draft-ledgers --apply
php artisan finance:audit-posting-balances
```

**Browser acceptance to follow after green local tests:** On a disposable CV draft, Create should generate exactly two inactive FK-linked ledger legs; repeated unchanged Edit should not add rows; changed Edit should archive old details and create two new inactive ledger legs; after approval exactly current two become `is_active=1`, and repeating approval with the original request key must not add new rows. Existing Drafts can be synchronized via the opt-in command. A posted CV's *full amendment Edit* is still protected by the separate approved-posting reversal workflow; this batch aligns Draft Edit + Approve/Post + Reverse, not arbitrary mutation of an immutable posted financial batch.

**Milestone 03:** This targeted CV three-table lifecycle and FK requirement is code-implemented but owner-local tests/browser acceptance are still pending. Other voucher-family lifecycle completion and permission acceptance should not be marked complete based on these tests. Do not re-run the already accepted Plot browser gates.


## 2026-11-19 — JV/CV differential detail editing + Commission AJAX modal lifecycle (implementation; owner tests pending)

**Owner observation:** CV `type_id=1` accumulated 8 inactive `ledgers` rows across repeated Edit, with historical details 13/14,17/18,19/20 and current details 21/22. Existing changed Draft Update archived all old detail IDs and created replacements on every edit. The Commission edit iframe used a native `form.submit()` which left the modal open until manual page refresh. Owner supplied previous JV row-differential `detail_ids[]` Update/Destroy source as a design reference; its `saveQuietly()` existing-posting overwrite was intentionally NOT copied.

**Code implemented and committed:**
- Both JV and CV Edit forms now submit one hidden `detail_ids[]` per existing row, and a blank ID for each newly added row (inside the correct table cell). Both Update controllers validate ID array key alignment, nullable distinct positive IDs and normalize the empty debit/credit side to zero before validation. Non-owned/duplicate detail IDs are refused instead of guessed by account or row position.
- `CommissionDraftWorkflow::updateDraft()` now performs a **true row-identity differential** for unposted drafts only: unchanged existing rows keep exact detail + ledger IDs, changed existing rows update their still-inactive detail and FK-linked ledger in place, removed rows are soft-archived and old FK-linked ledger evidence remains inactive, new rows create new details and new inactive ledgers. All changes remain inside the project-locked DB transaction; `CommissionLedgerSynchronizer` validates the enforced FK and relationships before and after. Existing idempotent Draft Create/Approve/Reversal behavior unchanged.
- Legacy JV Update now validates strict same-voucher detail FK ownership, correct debit/credit and account for **only current active** ledger rows, skips identical rows and preserves their original detail/ledger IDs, archives changed/deleted source-detail+active-ledger rows, and creates fresh FK-linked detail+ledger rows for truly new or changed posting lines. Old accounting amounts are NEVER overwritten with `saveQuietly()`. Archived historical legs do not falsely block repeated edits. A whole voucher-header date/reference change replaces affected ledger evidence, preserving history.
- Commission Edit confirmation now submits via guarded jQuery AJAX, uses the existing `finance:commission-saved` parent event, automatically closes the parent modal, reloads available DataTables via AJAX, and falls back to automatic parent refresh if no AJAX table is present. Errors are shown via existing finance toast helper; no native form navigation on Save.
- New isolated `CommissionLedgerSynchronizerTest` cases cover existing detail-ID stability despite amount changes, adding new allocations, soft-archiving removed rows, correct inactive ledger FK, and rejection of foreign voucher detail IDs without mutation. New `VoucherDifferentialEditContractTest` verifies both forms/controllers and CV AJAX response; existing JV no-op contract was preserved.

**Previous verified owner gate:** `CommissionLedgerSynchronizerTest` 4 PASS / 22 assertions, `CommissionLedgerActivationContractTest` 1 PASS / 14 assertions, `CommissionVoucherDetailRelationContractTest` 2 PASS / 20 assertions, 3/3 local drafts synced, all six projects clean on read-only posting balance audit. THESE RESULTS PRECEDE THE CODE IN THIS SECTION.

**New owner verification (NOT executed for changes above):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php -l tests/Unit/Finance/VoucherDifferentialEditContractTest.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan test --filter=VoucherUnchangedUpdateDetectorTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan finance:audit-posting-balances
```

**Browser acceptance:** On a disposable JV and CV, Edit existing row amount with balanced other-side correction (existing IDs unaffected for unchanged rows), add one row, remove another, Save. Ensure only intended detail IDs remain active, new ledger FK identifies the actual created detail, old rows preserved where archival applies, and no cross-voucher mutation. Commission Edit modal must close automatically after success and the listing must refresh without manual reload. No new migration required: previous `commision_voucher_detail_id` FK migration already applied and validated. Posted CV remains immutable under the existing approval/reversal workflow.

**Milestone 03 overall remains partial until owner browser gates and remaining cash/bank/etc lifecycle acceptance.** Do not claim all modules complete based on this focused JV/CV batch.


## 2026-11-19 — Commission differential no-op regression corrected

Owner verification after JV/CV differential change: `CommissionLedgerSynchronizerTest` **1 FAILED / 5 passed** because the original no-op integration test called `updateDraft()` without persisted `detail_id` values. With the newer row-identity contract, a missing ID explicitly means a newly inserted row; it cannot be silently inferred from array position, account or amount. The application form already submits existing hidden `detail_ids[]` and a blank ID only for new rows. **Committed fix:** update the regression to submit the two actual persisted detail IDs, exactly as the browser does, and assert their detail and linked ledger IDs remain unchanged. Remaining owner tests were green: `VoucherDifferentialEditContractTest` 3 PASS / 28 assertions; `VoucherUnchangedUpdateDetectorTest` 3 PASS / 11; `CommissionLedgerActivationContractTest` 1 PASS / 14; `LinkedVoucherLedgerRetirementTest` 6 PASS / 17. All-project posting balance audit scanned 6 projects with zero findings. Corrected test requires new local run and has not yet been verified. No migration, business data mutation or browser retest required for this test-only correction.


## 2026-11-19 — CV Approve/Post 409 iframe error and missing Party Ledger activation

**Owner browser evidence:** CV Edit iframe's native Approve form produced HTTP 409 Laravel debug page `Commission approval rejected to protect accounting history`, with no toast/modal. Previously CV Draft creation and differential edits were accepted, ledgers existed with is_active=0. Approval had not completed; therefore draft CV postings do not yet appear in active-party ledger reporting.

**Implemented code fix (owner verification pending):**
- Removed `CommissionDraftWorkflow::approveAndPost` blanket conflict check that treated any JV `ledgers.type_id` matching the CV header ID or any CV detail row ID as an ambiguous posting. Different source tables legitimately overlap numeric IDs. Approval must validate the existing enforced `commision_voucher_detail_id` FK, source voucher/project and exact accounting evidence instead. `CommissionLedgerSynchronizer::assertDraftCanReplace` is used only when source status is draft; approved idempotent retry continues through posted batch replay and ledger activation checks.
- `CommissionVoucherController::approve` now returns structured success JSON on AJAX; handles domain integrity conflicts as HTTP 409 with safe, specific user-friendly messages, logs diagnostic error, and returns a safe JSON 500 response for unexpected failures rather than HTML exception inside iframe. Ordinary non-AJAX requests still redirect with errors.
- Commission Edit Approve form now has a dedicated jQuery AJAX handler with Accept: application/json, busy/disabled-state protection, existing skeleton, toaster and error popup. On success it emits `finance:commission-saved`, closes parent modal and reloads parent listing. On failure it displays the JSON message without navigating iframe into exception page. Fixed longstanding `projectID: 3` literal in CV Edit subaccount Ajax, replacing with the selected configured project ID.
- Added integration regression `CommissionLedgerSynchronizerTest::test_approve_succeeds_despite_unrelated_jv_numeric_identity_collision` using a real separate JV ledger with overlapping numeric CV identity. It executes Draft -> Approve/Post -> replay, verifies exactly two active FK-linked CV ledgers, two posting lines, one batch and no duplicated records. Added `CommissionLedgerActivationContractTest` assertions for collision removal, AJAX feedback, absence of HTML abort and selected-project dropdown.

**New local verification (tests not yet run on this code):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan finance:audit-posting-balances
```

**Browser acceptance after tests:** In local testing project, open existing Draft CV, click `Approve saved voucher and post` once. Expect success toast, automatic modal close/list refresh; `commision_vouchers.status=approved`, exactly current two detail-linked ledger legs `is_active=1`, old archived legs remain inactive, finance posting batch balanced, Party Ledger can include account only if its account type is part of that report. Failed approval must show toast + popup, leave Draft status and inactive ledgers unchanged. Approval replay with same key must not create duplicates. Neither schema migration nor hardcoded project-specific DB operation is needed.

**Note:** Other JV/CV differential Edit tests were previously confirmed PASS (CommissionLedgerSynchronizerTest 6 PASS/31 assertions; VoucherDifferentialEditContractTest 3 PASS/28), and all-project posting audit found no issues in 6 projects. The approval fix and new test are additional code changes and have not been owner-verified yet.


## 2026-11-19 — Approved CV reopened after posting: read-only modal instead of HTTP 409

**Owner confirmed:** Commission approval is now successful; linked legacy `ledgers` active entries and Party Ledger visibility verified via browser. The next issue was opening Approved CV from the Commission Voucher register: `CommissionVoucherController::edit` explicitly aborted HTTP 409 and rendered Laravel's debug exception inside the modal iframe. This was a *read-route UX defect*, not a failure of the approved accounting posting.

**Code fix committed:**
- `CommissionVoucherController::edit` no longer throws 409 simply because an approved CV is selected; it loads the existing voucher and passes `readOnly=true` into the shared Edit/View Blade template for a posted typed CV. Other project scoping remains unchanged.
- Approved CV displays an explicit 'View only' explanation. All voucher form fields, row add/remove buttons and enhanced TomSelect dropdowns are disabled, and the Update and Approve buttons are absent. The server-side Draft-only mutation restriction is unchanged: a posted CV cannot be amended by forged PUT requests. Voucher header/detail/ledger records are never modified by merely opening the modal.
- Added `CommissionApprovedReadOnlyModalContractTest` to prevent reintroducing HTTP 409 in the approved read route and ensure non-editable controls are present in the Blade view. No database migrations or background repair required.

**Owner verification (new local gate pending):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Http/Controllers/CommissionVoucherController.php
php artisan test --filter=CommissionApprovedReadOnlyModalContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan finance:audit-posting-balances
```

**Browser check:** Open already Approved `CV-2026-0001` from Commission register: modal must show header and posted detail rows as View only, without Laravel error page or Save/Approve actions. Open a Draft CV: existing editable Add/Remove/Save/Approve flow must still work. Party Ledger approved CV entries already accepted by owner; no repeat posting needed. This batch does not permit posted vouchers to be directly re-edited; authorized correction/reversal remains separate.


## 2026-11-19 — Owner priority and handoff decision (authoritative)

**Roadmap revision:** six milestones, with Activity Audit as **Milestone 06 LAST**. Owner explicitly wants normal Create/Edit/Update/Delete for staff now, including already-approved CV corrections, not indefinite edit locks; preserve valid account relationships and no duplicate financial effect, but avoid customer-facing unnecessary reversal rows. Detailed user actions and affected table/PK before/after tracking will be implemented in Milestone 06, with proposed three new audit tables; those tables are NOT created now. Handoff/new chats must follow this order, avoid adding V2 isolation or hardcoded project/db names, use jQuery for custom frontend behavior, and keep milestone completion evidence truthful.

**Owner browser evidence (accepted):** CV Draft Create/Edit/Add/Remove with actual FK-linked ledgers works; CV Approve/Post now works; approved CV appears in Party Ledger, and approved CV opens in view-only mode without the earlier iframe 409. JV flow independently browser-accepted. Existing CV approved source is read-only by current implementation; **this does not satisfy the owner’s latest requirement**. Implement full approved CV differential edit/correction including original same CV ID/number, accounts, amounts, add/remove existing details, FK links to ledgers, posting-line source identity and consistent customer-facing balance **without duplicating financial impact**. Do not mark approved correction complete until code/test/browser evidence.

**Milestone 03 STATUS: PARTIAL (do not close prematurely).** Existing completed subdomains and owner validations are documented above. Other modules’ combined accepted CRUD/permission matrix is not established by current evidence; Milestones 04/05 remain pending.


## 2026-11-19 — Approved CV Full Edit/Update implementation (CURRENT CODE BATCH — owner local tests pending)

**Owner decision:** Activity Audit is the new sixth and LAST milestone, no activity tables/migrations now. Within current Milestone 03, the owner requires normal Create/Edit/Update/Delete even for Approved vouchers, avoiding needless customer-visible reversal documents for staff corrections. The owner confirmed CV approval, FK-linked active ledgers and Party Ledger display work, as did earlier JV/Plot owner browser checks.

**Actual code changes on upgrade/laravel-framework branch:**
- New `CommissionApprovedVoucherEditor` applies **same CV ID, same voucher number, same financial posting batch ID** corrections after project/source/batch locks, verified `commision_voucher_detail_id` actual enforced FK, exact source detail to active ledger and typed posting-line links, per-leg account/amount checks, positive balanced totals, and reversal-source conflict check. Existing unchanged rows preserve IDs; changed/removed rows are archived/inactivated and replacement detail+ledger+typed posting lines are inserted with exact FK identity. No new reversal batch and no duplicate active accounting amounts. This is a **controlled mutation of the current posted batch** (fingerprint and date updated), preserving old retired line evidence; it is not an immutable-posting reversal workflow.
- Original `CommissionVoucherController::edit` now allows Approved CV to open editable; already-reversed documents remain view-only. `update()` dispatches Approved CV to the new editor; Draft CV still uses `CommissionDraftWorkflow`. Existing jQuery AJAX modal Save, toast, auto close/refresh remain in place.
- `CommissionPostingWriter` idempotent replay now verifies **active** posting lines only and the corrected source fingerprint. Fingerprint is recalculated using persisted, PK-ordered active details (including nullable description values), matching replay format.
- `PostingReportReader` integrity validation, account statement/trial balance line query, original reversed-leg verification and empty-batch scan now correctly ignore archived posting lines, so customer-facing financial balances see only current active effects. Existing `PostingBatchIntegrityAudit` and Daily Voucher Directory already filter active legs.
- New SQLite integration coverage in `CommissionLedgerSynchronizerTest`: approved Edit changes amount/adds a row and retains unchanged IDs, preserves one posting batch and balanced current legs; no-change Save and approve idempotent replay add nothing; foreign detail ID refusal leaves records unchanged. `CommissionApprovedCorrectionContractTest` and revised previous `CommissionApprovedReadOnlyModalContractTest` guard controller dispatch, FK evidence, report read semantics and allowed Approved editing. **No owner green test results yet for this new batch**.

**Correction semantics deliberately chosen:** Draft rows can be edited in place while inactive. Approved posted changes archive the old leg evidence and create corrected FK-linked active legs; users see current corrected amount without a separate reversal transaction. This is a current-business correction, not global activity auditing, which is Milestone 06. Follow existing permission checks and keep the operation restricted to the selected project and original voucher; never use hardcoded project ID/database, hard deletes, or blanket foreign-ID guesses.

**Owner verification commands (NEXT REQUIRED GATE):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/CommissionApprovedVoucherEditor.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Services/Finance/CommissionLedgerSynchronizer.php
php -l app/Services/Finance/CommissionPostingWriter.php
php -l app/Services/Finance/PostingReportReader.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=CommissionApprovedCorrectionContractTest
php artisan test --filter=CommissionApprovedReadOnlyModalContractTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan finance:audit-posting-balances
```

**Owner focused browser acceptance after green tests:** Choose a disposable Approved CV, open Edit, update a debit/credit pair with balanced total, add a new leg, remove an old leg, Save. Verify modal closes and list/Party Ledger reflect new net amount, exactly one source voucher ID and one posting batch remain, all current ledger legs active with exact detail FK, old legs inactive, unrelated vouchers untouched. Reopen and no-op Save must not increase records. No additional migration required on the already-migrated local CV FK + posting line lifecycle schema. DO NOT run this against real customer financial records without a controlled backup/acceptance window.

**Remaining Milestone 03 not yet accepted:** Cash CR/DR, CCR/CDR, BOR/BOP, SV, PPR, EC/ECP, labour/stock and other source families must get explicit project-scoped Add/Edit/Delete/approval/ledger accuracy reviews and owner browser acceptance; the all-project balance audit checks posted batches only, not every business workflow. Milestone 02 browser permission matrix is also not fully signed off. Milestones 04/05 are not started under latest rules. Do NOT declare entire Milestone 03 100% yet; close only after its actual remaining modules pass acceptance.

### New-chat handoff prompt

Continue BlueMarketing GitHub `Bilal6230/blueMarketing` branch `upgrade/laravel-framework`, configured local project `C:\laragon\www\blueMarketing`. Follow `docs/README.md` as sole authoritative handoff, and this last section as the latest code/verification batch. User wants **actual committed/pushed code, not docs-only work**; deliver complete batches and exact PowerShell pull/tests. Custom frontend JS must use jQuery, global consistent AJAX/skeleton/toasts/modals, no V2 names/isolation or hardcoded project/database. Roadmap is SIX milestones: M01 complete; M02 partial permission acceptance; M03 currently active remaining voucher lifecycle, approved CV correction newly implemented **not yet locally verified**; M04 bank/cheque linked cascade pending; M05 reversal settings/regression pending; M06 centralized activity audit LAST and NOT started (proposed 3 audit tables). Owner verified CV approval/Party Ledger, existing CV draft and JV tests, 6-project posting audit. Next action: request/interpret local test and browser gate above, fix any failures, then finish remaining M03 modules with genuine code and coverage; do not misstate completion.


### Final integrity hardening for approved CV correction (same implementation batch)

- Approved CV editor now rejects any existing active financial batch with a mismatched original SHA-256 source fingerprint, date or voucher series **before** mutating source/detail/ledger/posting rows; this prevents silently overwriting pre-existing tampered history.
- It also rejects any unmapped active posting lines in the source batch, even if the mapped detail count otherwise looks correct. It requires the real database-enforced `ledgers.commision_voucher_detail_id` FK and project scoping. The old posting rows are deactivated, never hard-deleted.
- Local PHP lint, feature tests, owner browser acceptance and full remaining milestone 03 multi-voucher completion are STILL outstanding as of this commit. Code-review-only and contract tests are not proof of browser acceptance.


## 2026-11-19 — Latest owner PASS and remaining Milestone 03 bank voucher CRUD implementation (THIS IS THE CURRENT HANDOFF)

**Owner acceptance already confirmed for approved CV full differential Edit:** local `CommissionLedgerSynchronizerTest` **9 PASS / 51 assertions**, `CommissionApprovedCorrectionContractTest` **1 PASS / 17**, `CommissionApprovedReadOnlyModalContractTest` **1 PASS / 16**, `CommissionLedgerActivationContractTest` **2 PASS / 27**, `VoucherDifferentialEditContractTest` **3 PASS / 28**. Total **16 PASS / 139 assertions**, all PHP lint and Blade view caching successful. Owner also explicitly confirmed approved CV full Edit **browser PASS**. All-project posting audit checked 6 projects and returned zero findings. Plot/JV earlier browser checks accepted; **do not require them again without code changes**.

**Actual new code committed for Milestone 03 this turn (LOCAL TESTS AND BROWSER ACCEPTANCE STILL PENDING):**

1. **BD/BW manual bank voucher same-ID Edit:** new `BankTransferEditor` updates the selected project's existing `finance_bank_transfer_sources` and `finance_posting_batches` without a second batch. It locks the source, batch and exact two active bank/cash posting legs, checks account/project mapping, bank direction, balances, original source SHA256 fingerprint, and absence of a posted reversal. Changed active legs are archived with `is_active=0` and two corrected active legs are inserted under the same batch; no-op Edit preserves all IDs. Multi-allocation historical transfers are refused until they have stable per-line source FKs; no index guessing or unrelated mutation.
2. **BD/BW independent ordinary Delete:** New non-destructive migration `database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php` adds `is_active` and `deleted_at` to the **existing** `finance_bank_transfer_sources` table; no new table or hard deletion. `BankTransferEditor::delete()` locks and validates the exact source, amounts and fingerprint; logically archives it and retires its two active legs without a customer-visible REV batch. Original batch, source, FK links and retired posting legs remain in the database. Idempotent Create replay and separate Reverse reject archived sources.
3. **Project-scoped UI/API:** new GET Details, PUT Update and DELETE endpoints for `finance/voucher/bank-transfer/{kind}/{postingBatch}`; registered with `FinanceActionPermission` as Edit/Delete. `bank_transfer_workspace.blade.php` now offers Edit and Delete for eligible manual bank transactions using the existing modal, jQuery AJAX, toast/error display, confirmation, no full-page refresh, and live history reload. Action-specific bank Edit/Delete permissions are checked server-side; linking cleared cheque/source remains a separate Milestone 04 custody process.
4. **Correct reports and lifecycle:** Bank history, printed vouchers, typed/unified Daily Voucher Directory, `BankTransferReconciliation`, `PostingReportReader`, and `PostingBatchIntegrityAudit` now use only active posting legs and exclude logically deleted bank source roots. All-project audit separately reports `ARCHIVED_BANK_WITH_ACTIVE_POSTINGS` when archived bank evidence is corrupt (unexpected active legs); customer report fails closed instead of displaying wrong amount. Plot Receipt shared-directory print options now ignore archived allocations.
5. **Regression tests:** `BankTransferWriterTest` now has same-batch Edit/re-edit/no-op, project isolation, reversed-source refusal, ordinary Delete preserving source/posting history and hiding customer statement, archive replay refusal, and archived source tamper detection. `BankTransferCorrectionContractTest` guards routes, granular permissions, modal jQuery AJAX and active-only bank reporting. Older tests are retained. **Never claim these passed without local owner output.**

**Owner verification:**

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache

php -l app/Services/Finance/BankTransferEditor.php
php -l app/Services/Finance/BankTransferReconciliation.php
php -l app/Services/Finance/PostingReportReader.php
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l app/Http/Controllers/Finance/VoucherController.php
php -l database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php
php -l tests/Feature/Finance/BankTransferWriterTest.php
php -l tests/Unit/Finance/BankTransferCorrectionContractTest.php

php artisan test --filter=BankTransferWriterTest
php artisan test --filter=BankTransferCorrectionContractTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=PlotEntryWriterTest

php artisan migrate --path=database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php
php artisan finance:audit-posting-balances
```

Run migration only against the intended configured local TEST database, after tests pass; do not use `migrate:fresh`, and don't hardcode a database or project ID.

**Browser acceptance still required for NEW BANK changes:** On disposable manual Bank Deposit and manual Bank Withdrawal vouchers, Edit existing amount/date/cash/bank/narration, confirm modal closes, refreshed history/print and account balance reflect corrected amount. Reopen and Save unchanged (no extra posting). Ordinary Delete -> disappears from active lists and balances, no REV voucher, original rows remain archived. Test linked-cheque withdrawal Edit/Delete refusal without touching custody; that linked-cascade workflow is intentionally Milestone 04. Do not re-test approved CV/Plot/JV if nothing relevant changed there.

**Milestone 03 honest status:** **NOT YET CLOSED.** All-project posting integrity checks alone do not prove end-to-end CRUD. Remaining work includes comprehensive original CR/DR/CCR/CDR/BOR/BOP, PPR, EC/ECP, stock entry/bill/SV and labour payment CRUD/corrections, relationship-safe customer ledger effects and permission/browser acceptance. Some of these have existing partial routes; inspect the exact active endpoints before changes. User wants this work completed as rapidly as safely possible; deliver real code, not docs-only updates. Milestone 04 = cheque/online linked cascade; Milestone 05 = optional reversal settings; **Milestone 06 = centralized app Activity Audit LAST, deferred** (no audit tables yet).

### Updated fresh-chat handoff (highest priority)

Continue the same BlueMarketing development on GitHub `Bilal6230/blueMarketing`, branch `upgrade/laravel-framework` and local `C:\laragon\www\blueMarketing`. Configured DB comes exclusively from `.env`; no project 3 hardcode, no V2 isolation/names, no custom vanilla JS (use jQuery), consistent AJAX + toast/modals/skeleton/filters. **Owner verified CV approved Edit and Party Ledger, JV and Plot browser checks, and all-six-project audit.** Latest bank transfer same-ID Edit/Delete is code-committed but has unverified local tests and new additive lifecycle migration; read the last section and run its exact commands, fix failures, get focused browser PASS. Then finish remaining Milestone 03 voucher families (CR/DR/CCR/CDR/BOR/BOP/PPR/EC/ECP/stock/labour), not just Plot/JV/CV, and only mark COMPLETE after verified matrix. Roadmap: M01 complete, M02 permission browser partial, M03 active partial, M04 linked bank cascade pending, M05 reversal setting pending, M06 Activity Audit LAST, explicitly deferred. Keep a single authoritative `docs/README.md` and commit/push real changes; never promise future/background work. 


## 2026-11-19 — Milestone 03 continuation after owner BD/BW green gate: posted JV/SV correction, ordinary JV/CV Delete, active source filters (LATEST HANDOFF)

**Owner-confirmed local PASS before this new batch:**
- `BankTransferWriterTest`: **11 PASS / 72 assertions**, including BD/BW same batch Edit, ordinary archive/Delete, remnant integrity, reconciliation and linked-cheque refusal.
- `BankTransferCorrectionContractTest`: **2 PASS / 30 assertions**.
- Finance posting read-only audit: **all 6 projects clean**.
- Previously confirmed: Approved CV differential full Edit 16 PASS / 139 assertions and browser PASS, along with JV legacy and Plot full Edit browser acceptance.
- `database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php`: owner local migration successfully applied.
- Old `database/finance_legacy_migrations/2026_11_05_000200_create_finance_bank_transfer_sources.php` fixture was corrected to use `FinanceConnectionGuard`; **all 11 test failures resolved and rerun PASS**. No further V2 class reference in that fixture.

**Additional real application code committed THIS BATCH — LOCAL TESTS/BROWSER PENDING:**

1. **Global Cash active ledger account/detail correctness:** `DailyVoucherDirectoryController` now matches active `customer_ledger` records only to active `ledgers` legs when reading current details and filtering by account. Archived historical legs from previous edits cannot cause an old account to appear as the voucher's current account. Inactive rejected/pending source history is still readable. Added tests to `DailyVoucherDirectoryPostingLifecycleContractTest` to cover this, plus deleted BD/BW visibility.
2. **Posted JV/SV full differential Edit:** New `JournalApprovedVoucherEditor` verifies source/project/posting-batch uniqueness, enforced posted-line-to-detail IDs, original posting SHA256, exact account and debit/credit values, optional legacy JV ledger FK ownership, absence of a reversal and pending approval, and prevents foreign detail IDs. Unchanged rows preserve detail and posting line IDs; changed/removed rows archive old evidence, new current lines are inserted with source FK within the SAME batch and voucher ID/number. Same-ID no-op adds nothing. The typed `JournalPostingWriter` idempotent replay now checks only active posted lines, so old retired legs do not cause duplicates. `JournalVoucherController::edit/update` routes already-posted JV/SV through this editor instead of an iframe 409/immutable-source rejection. Already-reversed sources still reject corrections. Added integration regressions to `JournalPostingWriterTest` and new `JournalApprovedCorrectionContractTest`.
3. **Approved JV/SV/CV ordinary Delete (separate from Reverse action):** New `PostedVoucherArchiver` validates the original source's SHA256 fingerprint, owned typed batch, active financial lines, per-detail FK ownership, account amounts and optional/current ledger FKs. On normal Delete it sets active posting/legacy ledger lines inactive and soft-archives original header and details, all inside one transaction. It creates NO separate REV batch; exact historical rows and original batch remain stored. `JournalVoucherController::destroy` and `CommissionVoucherController::destroy` default to ordinary logical Delete for posted vouchers; explicit `reverse=1` retains the older reversal path when legitimately required. Pending approval decisions and unrelated/cross-project IDs refuse this transition. Added `PostedVoucherArchiveContractTest` and JV/CV integration tests.
4. **Customer-report integrity:** `PostingReportReader` and `PostingBatchIntegrityAudit` exclude intentionally archived source batches from live balances/empty-batch errors, but detect and reject any active posting leg left attached to an archived JV/SV/CV source. For BD/BW the previously verified active-only/archived lifecycle remains unchanged. All report endpoints sourced through `PostingReportReader` inherit this behavior.
5. **No additional database migration added in this newest batch**. The existing finance posting and JV/CV detail linkage migrations are prerequisites.

**Owner next verification (new code NOT YET RUN on their local environment):**

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/JournalApprovedVoucherEditor.php
php -l app/Services/Finance/PostedVoucherArchiver.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Services/Finance/JournalPostingWriter.php
php -l app/Services/Finance/PostingReportReader.php
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l tests/Feature/Finance/JournalPostingWriterTest.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=PostedVoucherArchiveContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan test --filter=BankTransferWriterTest
php artisan finance:audit-posting-balances
```

**Focused new browser acceptance after GREEN tests:** On a disposable/local posted JV (and any typed SV), Edit an amount/line/account with balanced totals, Add and Remove a detail row and Save, confirm same voucher number and corrected account statements and unchanged row IDs; re-open/no-op Save should add no posting legs. Delete a disposable Approved CV or posted JV and confirm active register/Party Ledger/Trial Balance no longer includes its amount, but historical source/ledger/posting rows remain archived and no REV voucher appears. Confirm no cross-project writes and proper permission-denied JSON/toast. The previously accepted CV full Edit and BD/BW unit tests do not require re-testing unless regression is observed.

**Honest Milestone 03 status: ACTIVE — NOT CLOSED.** New typed JV/SV and JV/CV Delete batch has not yet passed owner local tests or browser acceptance. Stock Entry/Stock Bill/SV inventory-linked corrections, labour payment source, PPR/EC/ECP and some approved bank/cheque instrument corrections still require full source-to-ledger/account dependency mapping, user-facing Edit/Delete path and tests. Stock CRUD is not safely addressable by deleting source alone: there are project balances, linked stock movements, inventory lines, stock financial JV/customer ledger source and billed-entry dependencies. Do not mark these complete without coordinated writes and validation. Some cheque/BOR/BOP linked custody corrections are assigned to Milestone 04, not to bypass/disable. Milestone 06 Activity Audit is explicitly last; no new activity audit tables now.

**New-chat handoff:** Continue `Bilal6230/blueMarketing` branch `upgrade/laravel-framework`, configured `C:\laragon\www\blueMarketing`, no V2 database isolation, no hardcoded DB or project ID. Use jQuery for authored browser script, unified AJAX/toast/skeleton/modal conventions. Owner insists *complete actual code* not documentation-only and expects pull/tests per batch. Treat latest owner Bank tests and original CV/Plot/JV acceptance as already passed. First action: run/repair new typed JV & archive regression gates above, then deliver remaining Milestone 03 modules with explicit CRUD matrix and relationship-safe edits, followed by Milestones 04/05. Six-milestone roadmap: 01 complete, 02 partial acceptance, 03 active partial, 04 linked bank custody pending, 05 reversal settings pending, 06 Activity Audit deferred last.


## 2026-11-19 — Labour payment exact source FK + remaining legacy migration-name cleanup (LATEST)

**Implemented, owner local verification pending:**
- `database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php` adds nullable enforced `labour_ledgers.customer_ledger_id` FK to `customer_ledger.id` (restrict physical delete). Historic labour ledger rows stay NULL unless their exact source evidence can be established; no amount/date/number-based backfill guessing, no data deletion.
- Existing `LabourController::labourPayment` now records newly created labour detail row IDs in the original transaction, creates the customer-payment voucher, then binds exactly those IDs to its real `customer_ledger_id` while enforcing the selected project and confirming all rows were linked. If any insert/update fails, the transaction rolls back original labour rows, source and ledger as one. New payment submissions now require this additive migration before writing. `LabourLedger::customerLedger()` relationship provided. Removed exposed PHP `file`/`line` exception metadata from its browser-facing 500 JSON, keeping detailed server log.
- New `LabourPaymentSourceRelationContractTest` validates the migration, FK link and error JSON no-stack-trace contract. **The complete Labour Payment Edit/Delete lifecycle is STILL NOT DONE**; separate attendance-to-payment/member settlement identities must be reconciled before ordinary correction.
- Legacy finance fixture migrations using the removed `V2IsolatedConnectionGuard` class (`2026_10_31_000110` commission detail links, `2026_10_31_000120` stock project approvals) now use the configured `FinanceConnectionGuard`; the old finance posting core and bank lookup fixture migrations no longer impose `bluemarketing_v2_*` database-name allowlists. Existing schema requirements and refuse-overwrite checks remain. These fixtures are **NOT** normal production migrations, should never be manually replayed onto an existing populated ERP.
- The separate historical `2026_10_30_000020_create_finance_legacy_domain_baseline.php` 56-table bootstrap remains **retired/quarantined**, not safe to apply to existing DB, regardless of its isolation/name guard. It must not be part of a normal `php artisan migrate` deployment. Do NOT remove its overwrite protection and run it on live tables.
- On explicit `reverse=1`, both JV and CV posted Delete endpoints now require independent `finance jv reverse` / `finance cv reverse` permission, not only the route's ordinary Delete grant. New contract assertions added.
- Updated `JournalPostingWriterTest` local in-memory fixture with real active head/subhead project account relationships so typed JV integration tests exercise the actual account availability gate.

**Do NOT mark Milestone 03 complete:** Bank BD/BW 13 PASS/102 and CV full Edit 16 PASS/139 plus earlier browser acceptance are real owner results, but all new posted JV/SV Edit, JV/CV logical Delete, Labour FK migration and unified migration fixture changes require owner local tests. Remaining Stock Entry/Bill actual full inventory/ledger CRUD, Labour Payment full correction, PPR/EC/ECP charge/payment/receipt semantics and bank-instrument linked custody remain unresolved. Dependencies must be handled by exact FKs, not arbitrary blocking by name or blindly deleting old rows. M04 cheque/online linked cascades and M05 reversal configuration are later; M06 complete Activity Audit LAST and NOT YET STARTED.

**Next local verification (only after pulling latest):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/JournalApprovedVoucherEditor.php
php -l app/Services/Finance/PostedVoucherArchiver.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Http/Controllers/LabourController.php
php -l app/Models/LabourLedger.php
php -l database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
php -l tests/Feature/Finance/JournalPostingWriterTest.php
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=PostedVoucherArchiveContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=LabourPaymentSourceRelationContractTest
php artisan test --filter=FinanceCoreMigrationUnifiedConnectionContractTest
php artisan test --filter=OriginalVoucherWorkflowHttpTest
php artisan finance:audit-posting-balances
# ONLY on the intended local TEST DB once tests are clean:
php artisan migrate --path=database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
```
**New local/browser acceptance pending:** test posted JV/SV Add/Remove/Edit, same-ID no-op and no duplicate batch; JV/Approved CV ordinary Delete preserves inactive FK-linked historical evidence without REV and removes current balances; labour source rows link to the payment voucher after migration. Reuse previously accepted CV/Plot/BD/BW tests; don't request unnecessary re-tests.

**Fresh chat priority:** Start from this section in `docs/README.md`, connected repo `Bilal6230/blueMarketing` branch `upgrade/laravel-framework`. Never claim the whole milestone closed. After fixing any new local regression failures, implement remaining Stock Entry/Bill, Labour Payment, PPR/EC/ECP full CRUD with stock balances/movements, source-to-ledger and custody exact relationships. Commit actual code, no docs-only round; preserve six-milestone roadmap and defer Activity Audit to final M06.


### Final exact-FK hardening — posted JV/SV and JV/CV logical Delete

- The posted `JournalApprovedVoucherEditor` now discovers any historical active `ledgers` by their **real `journal_voucher_detail_id` FK**, not merely matching `ledgers.type_id` numeric values. A different JV/SV sharing an integer `type_id` is not proof of ownership; if a row actually has this detail FK but the wrong voucher type/header, the correction fails without writes.
- `PostedVoucherArchiver` uses the same exact-FK approach for JV/SV and enforced `commision_voucher_detail_id` for CV. Unrelated numeric collisions are not archived; incorrectly attributed real FKs fail closed.
- Typed Posted JV/SV / ordinary Posted JV/CV Delete / migration normalization / new Labour source FK tests in the above commands remain **locally unverified**, so no milestone closure claim yet.

**Verification gate for this latest set (run tests before applying the new labour FK migration to the configured local TEST DB):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/JournalApprovedVoucherEditor.php
php -l app/Services/Finance/PostedVoucherArchiver.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Http/Controllers/LabourController.php
php -l database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=PostedVoucherArchiveContractTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=LabourPaymentSourceRelationContractTest
php artisan test --filter=FinanceCoreMigrationUnifiedConnectionContractTest
php artisan test --filter=OriginalVoucherWorkflowHttpTest
php artisan finance:audit-posting-balances
php artisan migrate --path=database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
```
Do not run the migration before validating that you're on the intended local test database. Owner does not need to rerun already-accepted browser CV and BD/BW unless there is an actual regression. Explicit posted JV/SV and posted JV/CV Delete browser gate is still pending.

**Legacy fixture rollback safety (2026-11-19):** Historical bank lookup fixture `database/finance_legacy_migrations/2026_10_30_000010_create_finance_bank_lookup.php` now refuses destructive `down()`. Removing its old DB-name allowlist must never make an accidental rollback drop real ERP `banks` master data. The file is not part of normal automatic production migrations. Do not run historical fixture migrations against a populated ERP database.
