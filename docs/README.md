# BlueMarketing — Current Five-Milestone Development Handoff

**Last updated:** 2026-11-18  
**Branch:** `upgrade/laravel-framework`  
**Database:** Use the active `.env` connection; the owner has verified `bluenew_clean_final`. Never hard-code DB names, use V2 isolation, or delete business records physically.  
**Documentation rule:** This README is the **only live progress/handoff document**. Update it after EVERY batch. Do not create separate phase or handoff documents. Original client requirements remain in `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md`; retired phases live in Git history.

## Current five milestones

| # | Milestone | Status |
|---|---|---|
| **01** | Full-page loader removal; global voucher-tinted skeletons | **COMPLETED — owner terminal/browser PASS**. 8 methods, 71 assertions (2026-11-18). |
| **02** | Per-voucher, per-action permissions and modern Roles management | **ACTIVE — backend tests PASS; Roles UI redesign committed; local migration/UI QA and permissions inventory pending.** |
| **03** | Atomic voucher create/edit/delete, FK and soft-delete / is_active audit | **PENDING, start after 02 accepted.** |
| **04** | Cheque/Online cleared-record edit/delete and linked-voucher cascade | **PENDING, after 03.** |
| **05** | Reversal ON/OFF setting, approved/posted reversal and end-to-end tests | **PENDING, after 04.** |

## Milestone 02 — Verified and committed

- Per-module/action permissions and routing: `app/Support/Finance/FinanceActionPermission.php`, `app/Http/Middleware/AuthorizeFinanceAction.php`, `app/Http/Middleware/AuthorizeBookingDeposit.php`; applied across Daily Entries, JV, CV, bank/plot, cheque, online, and related voucher endpoints. Actions include View/Create/Edit/Delete/Approve/Reject/Reverse/Print and applicable specialized actions. Backend is authoritative; user-facing actions should follow the same grants.
- User's **original permission migration** `2026_11_18_130000_register_finance_action_permissions.php` **ran successfully** on local `bluenew_clean_final`. Do NOT rerun or rollback just for UI work. Original legacy permissions were preserved and new module permissions copied from historical grants. They can now be configured independently.
- Owner's latest terminal PASS: `FinanceActionPermissionMatrixTest` **6 passed / 365 assertions**, `VoucherAuthenticatedPermissionIntegrationTest` **2 passed / 19 assertions**. Blade cache PASS. Browser permission combinations have NOT all been accepted.
- New Roles UX implemented on `/admin/role`: **ID, Name, Guard, Updated, Updated By, Actions** only. Removed unreadable concatenated permissions column and old DataTables. CR/DR-inspired compact cards, search, guard and per-column filters/sort, shared pagination and global speed-dial row actions. Server-rendered first rows avoid first-paint loading flashes.
- Create/Edit uses a single, centered, search-enabled, category/module sidebar **AJAX modal** with relevant action checkboxes only, selected counter, section select/clear, modern error feedback. Delete uses a dedicated confirmation modal. No full-page reload after success. User may create a role with zero permissions; changing an existing role's guard is prohibited (create a new role).
- Updated `app/Http/Controllers/RoleController.php` supports AJAX list/create/edit/archive, preserved old grants, protected superadmin, name+guard uniqueness (including archived names), and soft deletion only; a role still assigned to users cannot be archived.
- `app/Support/Finance/RolePermissionCatalog.php` classifies permissions: legacy generic voucher/JV actions are **hidden from selectable UI** (no duplicate permissions), not deleted/revoked; existing old grants are preserved through role edit until an accurate code-and-DB usage audit. New roles cannot re-grant the legacy duplicates in the editor.
- **NEW migration (not yet verified locally):** `database/migrations/2026_11_18_140000_add_updated_by_to_roles.php` adds nullable `roles.updated_by` for future audit display; historical unknown editors show “—”.
- **NEW safe audit command:** `php artisan permissions:audit --json` lists every permission, guard, legacy/granular classification, assigned role names/count and direct-user grant count. It is read-only. No permission deletion/cleanup has been performed yet.
- New tests: `tests/Unit/RoleManagementModernizationTest.php` (modern layout + safe role-grant contract). **Local test/browser outcome of this new batch still PENDING.**

## Next actions — Milestone 02 acceptance

Owner run in Windows PowerShell, on the intended local DB only:

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

Then upload `storage/app/permission-audit.json` so each legacy/unused permission can be verified **against both the current repository's references and actual DB grants before any removal**. Do not run blanket `permission:clear`, `migrate:fresh`, `db:wipe`, or blind SQL deletes.

Browser verify: roles page six columns; first-frame rows; searching/filter/pagination; Create and Edit modal compact/search/category/selected counts; AJAX saves without reload; new role CR View only then add Create and confirm independent behavior; old role hidden generic grants preserved; archive unused test role only (not assigned/protected roles); Updated By shows the actor on new edits; verify unauthorized actions return 403 and no unexpected 500. **Only after owner PASS** close Milestone 02 and start 03.

## Rules for every future assistant batch

Inspect code and `AGENTS.md`; implement actual changes on the same branch; run/extend targeted tests where possible; update **this README** and provide exact commit/pull/test commands. Do not equate committed/static checks with local/browser PASS. Reuse globally approved jQuery, CR/DR theme, Tom Select, card/table/filter/action/skeleton/modal patterns. No ordinary first-party vanilla JS. Do not reintroduce old V2 names, separate DB writers, approval restrictions, or hard-coded DB isolation.
