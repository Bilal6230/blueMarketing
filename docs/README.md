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
| **03** | FK/relationship integrity, safe Create/Edit/Delete, lifecycle columns | **ACTIVE — PARTIAL**. MySQL FK + Plot lifecycle migrations PASS, major CRUD workflows pending; Plot fixture repair and 28 tests **owner PASS**; archived-source reversal guard added, **new local rerun pending** |
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
