# BlueMarketing — Current Development Handoff

**Updated:** 2026-11-18  
**Branch:** `upgrade/laravel-framework`  
**Local application:** `C:\laragon\www\blueMarketing`  
**Database:** whichever database is configured in `.env`. Owner currently uses `bluenew_clean_final`. Never hard-code database names, introduce separate V2 finance, or reinstate V2 isolation.
**Documentation policy:** Update **this single document after every code batch**. Do not open new milestone/handoff documents. Historic requirements: `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md`. Earlier superseded handoffs are not authoritative.

## Roadmap — only five milestones

| # | Milestone | Verified status |
|---|---|---|
| 01 | Nonblocking loading and voucher-tinted skeletons | **COMPLETE** — owner browser + PHP tests PASS |
| 02 | Action-specific permissions, Roles page and backend enforcement | **PARTIAL ACCEPTANCE** — implemented, tests PASS, owner confirmed CR Edit visibly works; complete cross-module browser permission matrix not yet accepted |
| **03** | FK/relationship integrity, safe Create/Edit/Delete, lifecycle columns | **ACTIVE — PARTIAL**. MySQL FK + Plot lifecycle migrations PASS, major CRUD workflows pending; Archived-source guard owner PASS; audited narration-only Edit committed; local tests, new migration and browser acceptance pending |
| 04 | Cheque/Online edit/delete with cleared linked-voucher cascade | **NOT STARTED under new rules** |
| 05 | Reversal settings ON/OFF and full regression | **NOT STARTED under new rules** |

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
php artisan db:show
php artisan migrate --path=database/migrations/2026_11_18_180000_consolidate_plot_narration_audit.php
php artisan finance:audit-voucher-schema --require-fks
```
**Expected:** New tests PASS, shared `daily_voucher_edit_snapshots` remains, redundant `finance_plot_voucher_edit_audits` disappears. If migration finds old rows without an editor, it intentionally fails and preserves old table; inspect those rows, do not force-drop. **Browser test still required for both Plot Edit Narration workspaces** after code/migration pass (disposable voucher, user with Edit ON, test independent Reverse OFF and 403 on direct PATCH without Edit).

**Follow-on engineering policy:** Before adding any further business or audit table, explicitly inspect existing tables and prefer reuse where semantics and integrity can be preserved. Schema growth needs a demonstrated functional necessity, not one table per new Edit/Delete action. **Milestone 03 still PARTIAL**: full amount/allocation/inventory edits and ordinary logical deletion remain unimplemented.

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
