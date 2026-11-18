# BlueMarketing — Current Development Handoff (Single Source of Truth)

**Updated:** 2026-11-18  
**Branch:** `upgrade/laravel-framework`  
**Active plan:** FIVE milestones. The former 10-phase and V2-isolation plans are retired.  
**Database:** The owner's local `.env` points to the newly migrated `bluenew_clean_final`. All application code must use the configured DB and must NOT hard-code a database-name gate.

## Milestone status

| # | Milestone | Truth / Gate |
|---|---|---|
| 01 | Global nonblocking loader and voucher-colored skeletons | **COMPLETE: owner browser PASS and 8 local PHPUnit tests PASS** on 2026-11-18 (6 + 2 test methods, 71 assertions total). |
| 02 | Separate permissions for every Daily Entries voucher and action | **IMPLEMENTED IN CODE; PENDING LOCAL MIGRATION, TESTS AND BROWSER PERMISSION ACCEPTANCE.** CURRENT active milestone. |
| 03 | Relationship-safe voucher create/edit/logical delete and missing FK/deleted_at/is_active audit | **PENDING** after Milestone 02 owner PASS. |
| 04 | Edit/delete cleared cheque and online sources with correct linked-voucher/ledger cascade | **PENDING** after Milestone 03. |
| 05 | Reversal ON/OFF setting for posted/approved vouchers and full regression | **PENDING** after Milestone 04. |

## Milestone 02 — What is committed

- Central action matrix: `app/Support/Finance/FinanceActionPermission.php`. Voucher/module names: CR, DR, CP, CCR, CDR, BOR, BOP, JV, SV, CV, Bank Deposit/Withdrawal, Plot Receipt/Purchase, Draft Voucher, Extra Charges, Cheque Management, Online Management and applicable directory/source types.
- Each module has **independent** `finance <module> <action>` permissions: View, Create, Edit, Delete, Approve, Reject, Reverse, Print, History, Details, Confirm, Clear, Return, Withdraw, Release and Resubmit. Only actions that exist on a module need to be granted; a permission definition does **not** implement a missing CRUD feature.
- `app/Http/Middleware/AuthorizeFinanceAction.php`, registered in `app/Http/Kernel.php`, enforces the matching ability on the authenticated admin routes. Existing voucher CRUD, printing, approvals, bank transfer, plot receipt/purchase, JV/CV, cheque and online routes were mapped to separate actions. Persisted voucher/source ID and selected project determine authorization for edits, deletes, approvals and prints; client-supplied voucher type alone cannot choose an unrelated source's permission.
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
php artisan migrate
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
