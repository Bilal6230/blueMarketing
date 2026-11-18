# BlueMarketing — Current Development Handoff (Single Source of Truth)

**Updated:** 2026-11-18  
**Branch:** `upgrade/laravel-framework`  
**Active plan:** FIVE milestones. The former 10-phase and V2-isolation plans are retired.  
**Database:** The owner's local `.env` points to the newly migrated `bluenew_clean_final`. All application code must use the configured DB and must NOT hard-code a database-name gate.

## Milestone status

| # | Milestone | Truth / Gate |
|---|---|---|
| 01 | Global nonblocking loader and voucher-colored skeletons | **COMPLETE: owner browser PASS and 8 local PHPUnit tests PASS** on 2026-11-18 (6 + 2 test methods, 71 assertions total). |
| 02 | Separate permissions for every Daily Entries voucher and action | **GRANULAR BACKEND TESTS PASS (OWNER); ROLE UI REDESIGN COMMITTED, NEW UI QA AND PERMISSION INVENTORY PENDING.** CURRENT active milestone. |
| 03 | Relationship-safe voucher create/edit/logical delete and missing FK/deleted_at/is_active audit | **PENDING** after Milestone 02 owner PASS. |
| 04 | Edit/delete cleared cheque and online sources with correct linked-voucher/ledger cascade | **PENDING** after Milestone 03. |
| 05 | Reversal ON/OFF setting for posted/approved vouchers and full regression | **PENDING** after Milestone 04. |

## 2026-11-18 Role Management UX / permission inventory batch — ACTIVE

**Owner verification:** `FinanceActionPermissionMatrixTest` **6 PASS / 365 assertions**, `VoucherAuthenticatedPermissionIntegrationTest` **2 PASS / 19 assertions**, view cache PASS, initial permissions migration ran on `bluenew_clean_final`. Owner screenshot showed an overly tall legacy Roles modal, duplicated generic voucher permissions in General and finance sections, and unreadable permissions string in roles listing.

**Committed changes:**
- `resources/views/admin/role.blade.php` rewritten into the common compact CR/DR-inspired design: six columns **ID, Name, Guard, Updated, Updated By, Actions**; no concatenated permissions table column or legacy DataTables sorting; first-frame server rows; search, guard, column-filter popovers, sort, page size, shared `$.blueVoucherPagination`, and shared `$.blueActionMenu` for modern actions.
- One centered, scroll-contained, searchable Create/Edit modal; module sidebar, relevant action checkboxes only, selected counter, section select/clear, safe form error messages, and AJAX create/update without page reload. Delete is a separate modern confirmation modal using AJAX.
- `app/Support/Finance/RolePermissionCatalog.php` centralizes module labels and applicable actions. Old generic `read voucher`, `create voucher`, `update voucher`, `delete voucher` and old JV names are **hidden in editor** to avoid duplicate choices, **not deleted/revoked**. Existing role grants are retained automatically during edits while old routes may depend on them. The backend rejects newly granting those retired names from the modern role editor.
- `app/Http/Controllers/RoleController.php` now returns refreshable JSON rows for AJAX, validates distinct permissions/role names, prevents guard changes on an existing role, protects superadmin, archives assigned-user-free roles via SoftDeletes (no hard delete), and preserves hidden historical permissions.
- New idempotent migration `2026_11_18_140000_add_updated_by_to_roles.php` adds nullable `roles.updated_by` and index (historical unknown actors display —). Requires local run once.
- New **read-only** `php artisan permissions:audit --json` command inventories every actual permission, guard, role-grant count, direct-user-grant count, and classification without mutating records; do NOT delete or rename until owner shares the output and usage map is confirmed.
- Added `tests/Unit/RoleManagementModernizationTest.php` for duplicate classification, applicable actions, six-column UI, modal and preservation contracts.

**Unverified:** new migration, role-page Blade compile, new tests, modern role editor create/edit/delete behavior on browser, permission-use inventory. **Keep milestone 02 open.** Do not start milestone 03 until owner verifies.

**New verification / safe audit:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan migrate --path=database/migrations/2026_11_18_140000_add_updated_by_to_roles.php
php artisan view:cache
php artisan test --filter=RoleManagementModernizationTest
php artisan test --filter=FinanceActionPermissionMatrixTest
php artisan test --filter=VoucherAuthenticatedPermissionIntegrationTest
php artisan permissions:audit --json | Set-Content -Path .\storage\app\permission-audit.json -Encoding UTF8
```
Upload `storage/app/permission-audit.json` for the next targeted duplicate/unused permission review. This JSON reports permission names and **counts** but not user personal details. Browser: open `/admin/role`, confirm six columns and filter/action UX; create a disposable role with CR View only; edit to add CR Create and ensure old grants persist on existing roles; test guards and archive on unassigned disposable role. No production data changes.

## 2026-11-18 Local regression repair after owner's first terminal run

- Owner confirmed `bluenew_clean_final` in `php artisan db:show`; the 2026-11-18 granular finance permission migration **ran successfully**. DO NOT rerun or roll it back just to test these code repairs.
- The first local permission test attempt found **2 failures** in `FinanceActionPermissionMatrixTest` and **1 failure** in `VoucherAuthenticatedPermissionIntegrationTest`.
- Fixed the unit test's unbound synthetic Laravel route using `Route::bind($request)`, so the daily directory type parameter check can execute.
- Fixed the actual Plot Purchase create button Blade defect: the stray `@endcan` had no opening `@can`. The button is now enclosed by `@can('finance plot-purchase create')`.
- Updated the authenticated feature regression to differentiate the actual form validation response from the former blanket `409` assertion: invalid AJAX form payloads should receive validation `422`, and delete without a selected project should receive `403`. This does not weaken unauthorized action denial tests.
- GitHub source reinspection verified the Plot Purchase guard exists and `@can`/`@endcan` counts are balanced; **local tests and browser QA have not yet been rerun**. Do not mark Milestone 02 accepted on source review alone.

### Rerun after repairs (local only)
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=FinanceActionPermissionMatrixTest
php artisan test --filter=VoucherAuthenticatedPermissionIntegrationTest
```
After PASS, verify Role Create/Edit and granular finance menus using the same project with a separate test staff account. Start with the CR series: View-only role shows list but cannot Create/Edit/Delete/Reverse; add Create and verify only Create becomes available; grant Edit independently; grant Delete independently; test Approve, Reject and Print separately. Repeat for CCR/CDR/DR/BOR/BOP/CP, CV/JV/SV, Cheque/Online, Plot Receipt/Purchase, Bank Deposit/Withdrawal, Draft and Extra Charge. Direct disallowed endpoints must respond 403. No edits to real production data. Record any failures against Milestone 02.

## Milestone 02 — What is committed

- Central action matrix: `app/Support/Finance/FinanceActionPermission.php`. Voucher/module names: CR, DR, CP, CCR, CDR, BOR, BOP, JV, SV, CV, Bank Deposit/Withdrawal, Plot Receipt/Purchase, Draft Voucher, Extra Charges, Cheque Management, Online Management and applicable directory/source types.
- Each module has **independent** `finance <module> <action>` permissions: View, Create, Edit, Delete, Approve, Reject, Reverse, Print, History, Details, Confirm, Clear, Return, Withdraw, Release and Resubmit. Only actions that exist on a module need to be granted; a permission definition does **not** implement a missing CRUD feature.
- `app/Http/Middleware/AuthorizeFinanceAction.php`, registered in `app/Http/Kernel.php`, enforces the matching ability on the authenticated admin routes. Existing voucher CRUD, printing, approvals, bank transfer, plot receipt/purchase, JV/CV, cheque and online routes were mapped to separate actions. Persisted voucher/source ID and selected project determine authorization for edits, deletes, approvals and prints; client-supplied voucher type alone cannot choose an unrelated source's permission. Unknown persisted voucher types now fail closed instead of inheriting a generic directory permission.
- Extra Charge create uses `app/Http/Middleware/AuthorizeBookingDeposit.php` so regular booking deposits preserve their original `create plot` right, while Extra Charges use their own grant.
- `database/migrations/2026_11_18_130000_register_finance_action_permissions.php` registers permissions idempotently and copies **existing** active role/direct grants into the new per-module permissions (including cheque processing/online confirming). The migration does **not** remove old permissions or business rows. Roles can independently grant/revoke each new permission after migration.
- Role Create/Edit `resources/views/admin/role.blade.php` now shows a separate collapsible action group for each voucher/module, retaining generic permissions and existing role save endpoints.
- Finance voucher, cheque and online action buttons/menus were updated to reflect their respective permissions; combined Daily Voucher Directory filters unauthorized types, row actions, and related print links.
- New `tests/Unit/Finance/FinanceActionPermissionMatrixTest.php`; existing `tests/Feature/Finance/VoucherAuthenticatedPermissionIntegrationTest.php` updated.

**Important verification limitation:** No local migration, PHP/Blade compilation, PHPUnit or browser authorization tests were executed by the assistant for this new batch. GitHub source-level review is not runtime PASS. Some unrelated legacy surfaces still have their old permission checks; do not claim every combination or complete historical route inventory PASS without the owner test gate. **Milestone 02 is NOT yet marked accepted.** No posted finance records or customer balances were altered by these code commits.

## Exact local verification before calling Milestone 02 complete

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan db:show
# Confirm this is the intended local database before applying migrations
php artisan migrate:status
# Apply only the new action-permission migration; do not run unrelated pending migrations
php artisan migrate --path=database/migrations/2026_11_18_130000_register_finance_action_permissions.php
php artisan permission:cache-reset
php artisan view:cache
php artisan test --filter=FinanceActionPermissionMatrixTest
php artisan test --filter=VoucherAuthenticatedPermissionIntegrationTest
php artisan route:list --path=admin/finance
```

Then **browser acceptance on local database only**: In Roles, verify each voucher's View/Create/Edit/Delete/Approve/Reject/Reverse/Print permissions separately, including Cheque Clear/Return/Withdraw and Online Confirm/History. With a role allowed only View, other actions must not be shown or callable (403); with only Create, Delete must remain 403; with only Reverse, Delete must remain 403. Verify CR/DR/CCR/CDR/BOR/BOP, JV/SV/CV, Bank Deposit/Withdrawal, Plot Receive/Purchase, Draft, Extra Charges, Cheque and Online. Check superadmin and ordinary-role login, project isolation, print history, modal links, and existing voucher read/write functionality **without posting arbitrary production data**. If any gate fails, fix it within Milestone 02 before moving to 03.

## Mandatory rule for every future batch / new chat

1. Read `docs/README.md` (this file), `AGENTS.md`, and inspect the current branch code. The original client source `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md` is a historical reference; the owner's latest five milestones supersede its old phase planning.
2. Work on the current milestone, code and targeted tests, then **update this same README in the same batch**. Never add a competing phase/handoff document.
3. First-party frontend DOM and AJAX use jQuery only. Reuse CR/DR-approved global skeleton, modals, toast, tables, colors, filters and pagination. No full-page blocking loaders.
4. Use the configured DB, not a database-specific V2 isolation. No hard-deleting financial/business rows; for future Milestone 03, preserve source-to-detail-to-ledger relationships with atomic writes, project scoping and logical deletion.
5. Report exact commits and **separately** what passed source inspection, local PHP tests, migrations and owner browser. Do not claim PASS for steps not executed. Next after Milestone 02 owner PASS is Milestone 03.

**Documentation housekeeping:** Only two active docs remain: this README and the original client requirements reference. Superseded 10-phase, V2 and older database worklogs are recoverable in Git history, not current instructions.
