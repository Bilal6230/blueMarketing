# BlueMarketing — Current Five-Milestone Development Handoff

**Last updated:** 2026-11-18  
**Branch:** `upgrade/laravel-framework`  
**Database:** Use the active `.env` connection; the owner has verified `bluenew_clean_final`. Never hard-code DB names, use V2 isolation, or delete business records physically.  
**Documentation rule:** This README is the **only live progress/handoff document**. Update it after EVERY batch. Do not create separate phase or handoff documents. Original client requirements remain in `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md`; retired phases live in Git history.

## Current five milestones

| # | Milestone | Status |
|---|---|---|
| **01** | Full-page loader removal; global voucher-tinted skeletons | **COMPLETED — owner terminal/browser PASS**. 8 methods, 71 assertions (2026-11-18). |
| **02** | Per-voucher, per-action permissions and modern Roles management | **ACTIVE — backend tests PASS; Roles UI migration, tests and visual layout owner PASS; CR Edit browser behavior still unresolved, permission inventory upload pending.** |
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
- **Role migration (owner local PASS):** `database/migrations/2026_11_18_140000_add_updated_by_to_roles.php` adds nullable `roles.updated_by` for future audit display; historical unknown editors show “—”.
- **NEW safe audit command:** `php artisan permissions:audit --json` lists every permission, guard, legacy/granular classification, assigned role names/count and direct-user grant count. It is read-only. No permission deletion/cleanup has been performed yet.
- New tests: `tests/Unit/RoleManagementModernizationTest.php` (modern layout + safe role-grant contract). **RoleManagementModernizationTest 4 PASS / 424 assertions verified by owner. New CR Edit diagnostic test/browser result is pending.**

## 2026-11-18 — Data Operator CR Edit authorization repair (CURRENT batch)

**User evidence:** Role editor screenshot shows Data Operator's CR Edit granted, but CR voucher actions do not offer Edit. Local tests already PASS: RoleManagementModernizationTest 4/424, FinanceActionPermissionMatrixTest 6/365, VoucherAuthenticatedPermissionIntegrationTest 2/19; these tests did **not** cover the actual customer-ledger source-to-Edit action flow. User generated `storage/app/permission-audit.json` locally, but **has not uploaded the file**; do not claim actual DB role/user grant contents were inspected.

**Root cause verified in current code:** `DailyVoucherDirectoryController` still checked obsolete `update voucher` before supplying `edit_url`. `RejectedCashVoucherController` also checked `update voucher` internally, even when role had `finance cr edit`. The route mapping erroneously required `resubmit` for `finance.daily-vouchers.revise.edit`; final row permission filter mapped `revise_url` to `resubmit`. Legacy `delete voucher` also gated the reverse event handler. In addition, existing source list Edit eligibility required original submitter and excluded approved/posted vouchers of eligible cash type. These competing gates meant the checkbox could be saved yet have no effect.

**GitHub code changes committed:**
- Route action mapping: `finance.daily-vouchers.revise.edit` and its same-source POST store use **Edit**; reverse and release keep separate permissions.
- Daily Directory customer-ledger rows use `finance <module> edit/delete/reverse/release`, plus independently scoped JV/CV rights; no obsolete generic update/delete/read JV checks for these actions. Rows with approved or posted **active cash CR/CP/CCR/CDR** are offered Edit when eligible; rejected records use Revise under Edit. Voucher source/project/accounting validations remain.
- `RejectedCashVoucherController` GET and POST both check persisted source's `finance <module> edit`, independent of which user originally submitted it; selected project scope and financial transaction/lifecycle checks remain.
- Shared Daily Directory Javascript no longer requires `@can('delete voucher')` merely to attach Reverse click handlers. Backend remains authorized independently.
- New `tests/Unit/Finance/FinanceVoucherEditPermissionParityTest.php` checks the actual directory/controller/route permission contract, including no legacy generic permissions in that chain.
- **Local PHPUnit and browser QA of this fix have NOT been run by assistant.** Do not close Milestone 02 until owner PASS. No DB/role grants were altered in this fix.

**Owner verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=FinanceVoucherEditPermissionParityTest
php artisan test --filter=FinanceActionPermissionMatrixTest
php artisan test --filter=RoleManagementModernizationTest
```
With an ordinary Data Operator user assigned to the edited role and correct project, refresh `/admin/finance/daily-vouchers?type=CR`. An active posted/approved **cash CR** with an eligible linked ledger should expose Edit in its shared floating action menu; clicking Edit should open the same-source form, not a 403. Revocation of CR Edit must remove that link and direct endpoint must return 403. Pending or bank/cheque entries may still have unrelated lifecycle restrictions; do not pretend a missing Edit button on an ineligible lifecycle state is a permission bug. Upload `storage/app/permission-audit.json` to investigate remaining legacy names and distinguish actual role grants from UI or lifecycle gating.

## 2026-11-18 Follow-up: CR Edit still missing in owner's screenshot

Owner reports CR Edit still absent after prior GitHub fix. The new screenshot shows **Pending**, **Approved** and **Posted** CR entries. The owner has NOT supplied a fresh terminal pull/test log since commit `743484ad`, so local code version and actual database role grants cannot be independently verified. Do not claim browser PASS.

**Cause split:** (1) old `update voucher` checks were corrected in the directory/Edit GET+POST in prior commits; (2) the listing still only renders an Edit link for eligible **active cash posted/approved** sources with a linked ledger; **Pending** records and online/cheque sources have distinct lifecycle rules and cannot be made editable merely by adding permission; (3) a checkbox in role configuration does not prove the logged-in user has that role or a refreshed effective grant. There is also a possible source/display series mismatch. These facts need database checks, not more guessed grants.

**This batch:** Added read-only `finance:diagnose-edit` command with role name, series, optional project id. It reports the role's `finance cr edit` grant, number of assigned users and effective grants, plus up to 12 voucher source statuses/payment methods and linked ledger counts when `--project` is supplied. It modifies **no** records, roles or permissions. Added `FinanceVoucherEditDiagnosticTest`. The previous fixes remain committed. The original five-milestone roadmap is unchanged; **Milestone 02 remains OPEN until direct browser Edit test succeeds**.

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=FinanceVoucherEditDiagnosticTest
php artisan test --filter=FinanceVoucherEditPermissionParityTest
php artisan finance:diagnose-edit --role="Data Operator" --series=CR
# Optional after identifying selected project ID:
# php artisan finance:diagnose-edit --role="Data Operator" --series=CR --project=3
```

If the role grant is YES and the browser still has no Edit for an eligible cash voucher, inspect the authenticated user's actual role/effective grant, relevant source identity, and listing JSON (Network -> daily-vouchers/data -> `edit_url`). If the role's grant is NO, use role editor to save that role's CR Edit. **Do not enable unsafe edit on Pending, cleared online/cheque or broken ledger-linked rows without implementing and testing the corresponding atomic lifecycle edit workflow.**

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
