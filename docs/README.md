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
| **03** | Atomic voucher create/edit/delete, FK and soft-delete / is_active audit | **ACTIVE — FK/orphan audit and preflight migration committed; legacy JV/CV linked ledger CRUD fixes committed; full schema/local integration and other voucher workflows pending.** |
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

## 2026-11-18 Owner local diagnostic PASS — CR Edit grant and project 5 voucher eligibility

Owner executed commit `2b2325ed` on local DB `bluenew_clean_final`. **Local confirmed:** `FinanceVoucherEditDiagnosticTest` 2 passed / 13 assertions; `FinanceVoucherEditPermissionParityTest` 4 passed / 21 assertions. The role `Data Operator` (ID 5) holds `finance cr edit`; 9 users assigned and **all 9 can the ability**; legacy `update voucher` is NO and must NOT be required. **With `--project=5 --limit=25`: every returned CR source (IDs 5635 through 5550 in the inspected sample) is active=1, payment_type=1 (cash), posted and has one linked ledger — status is `LISTING EDIT ELIGIBLE`.** These facts rule out role grant and basic voucher eligibility as explanations for those records. However the owner has not reported a new browser reload/inspection after this local pull; the UI Edit flow is NOT yet accepted.

**Important correction:** The previous suggestion `DB::table('projects')->get(['id','name'])` failed because this schema's `projects` table does not have `name`; do not repeat. Owner supplied project 5 directly, so no project discovery is needed.

**Next decisive verification:** With the same logged-in Data Operator and project 5, hard-refresh `/admin/finance/daily-vouchers?type=CR`, open an eligible **Posted cash CR** row in the Actions + menu; verify Edit exists and opens its embedded form. If missing, inspect Chrome DevTools Network request to `finance/daily-vouchers/data`, locate the matching row's `number`, `type`, `status`, `edit_url`, `revise_url`, and the request's HTTP status. If `edit_url` is non-null but menu omits Edit, fix JS/blue action menu. If `edit_url` is null despite an eligible source and correct role, inspect **authenticated user / selected project / source series / source approval state** on that exact request. Do not guess additional database gates or alter accounting safety rules. User's permission-audit JSON is still not uploaded.

## 2026-11-18 — Owner browser verification: CR Edit visible

Owner screenshot confirms **CR-834 Edit Voucher** is now present in the shared Actions floating menu for project 5, after previous granular permission fixes. Role grant and listing eligibility had already passed local diagnostics. **CR Edit action visibility: BROWSER PASS.** Editing form opening, successful safe save, and negative authorization/revocation test are still unverified. Do not mark all Milestone 02 complete; continue remaining action-permission/browser acceptance. No code changes required for this confirmation.

## 2026-11-18 — Milestone 03 implementation batch (CURRENT)

**Owner instruction:** proceed to Milestone 03. Milestone 02 has locally passing granular permission tests and owner browser **CR Edit action visibility PASS**, but the complete multi-role authorization acceptance for all modules is not recorded; do not invent it.

**Delivered on branch `upgrade/laravel-framework`:**
1. **Actual schema inspection:** The baseline migrations show `journal_vouchers`, `journal_voucher_details`, `commision_vouchers`, `commision_voucher_details` already have `deleted_at`; `ledgers` uses `is_active` and `journal_voucher_detail_id` + `customer_ledger_id` are real single-target columns. Detail parent columns have indexes in baseline DDL but not explicit FK constraints there. `ledgers.type/type_id` remains polymorphic and must NOT receive a single FK.
2. **Read-only complete database inventory:** `app/Console/Commands/AuditVoucherSchema.php` command `finance:audit-voucher-schema` queries the configured database's INFORMATION_SCHEMA to enumerate **all tables with neither `deleted_at` nor `is_active`**, actual FK constraints, and orphan counts for 9 core relations, plus cross-project detail/account link counts. It changes nothing. **The precise missing-column list remains UNKNOWN until owner runs this against `bluenew_clean_final`**. Technical audit/event/history tables do not necessarily need business soft-delete columns; decide case by case.
3. **Idempotent FK migration (NOT YET APPLIED):** `2026_11_18_150000_enforce_voucher_source_foreign_keys.php` adds/reuses exact FK relationships `journal_voucher_details.journal_voucher_id -> journal_vouchers.id`, `commision_voucher_details.commision_voucher_id -> commision_vouchers.id`, `ledgers.journal_voucher_detail_id -> journal_voucher_details.id`, `ledgers.customer_ledger_id -> customer_ledger.id`, plus exact account FKs for JV/CV details and ledgers and `ledger_source_attributions.ledger_id -> ledgers.id`. It checks all relevant historical orphan and cross-project account mismatches first and fails clearly on any orphan/mismatched existing constraint; FK delete/update policy is **RESTRICT**, never CASCADE, to prevent hard deletes. **Do not run it until schema audit shows zero orphans, zero cross-project detail links and expected column types.** No unrelated databases are touched.
4. **Atomic legacy JV/CV linked-lifecycle repairs:** `app/Services/Finance/LinkedVoucherLedgerRetirement.php` locks the source header, active details and explicitly source-linked ledger legs; verifies selected project and exact debit/credit/account multiset, including duplicate-value legs, then sets **only exact linked active ledger rows** `is_active=0` and keeps original ledger rows. Throws/rolls back on orphaned/missing/foreign-project or mismatched rows. The original legacy JV and CV **Delete** controller transactions now retire these linked ledgers, soft-delete matching detail rows and voucher header, no new reversal ledger rows for those legacy branches. Legacy **Edit** transactions retire old matching linked legs and soft-delete old details, then create corrected details/active ledgers under **the same source voucher ID**. This avoids the prior legacy pattern of accumulating active compensating reversal legs. This explicitly does **NOT** silently rewrite typed-posting source claims or unrelated journal/commission records.
5. **Tests committed:** `tests/Unit/Finance/VoucherRelationshipSafetyMilestoneTest.php` checks audit/migration/legacy code contracts. `tests/Feature/Finance/LinkedVoucherLedgerRetirementTest.php` exercises real transactional linked ledger retirement, foreign-project refusal and mismatch rollback in isolated in-memory SQLite (not MySQL database). **No local PHP/unit/browser tests have been executed by the assistant.**

**Remaining before Milestone 03 can be honestly called COMPLETE:**
- Owner run the read-only audit, review its **actual** missing lifecycle columns and orphan counts (including potential existing incompatible FKs); only then apply FK migration or repair orphan evidence. Add `is_active`/soft-delete migrations to any **operational business tables** identified as missing both, not to every immutable history/metadata table blindly.
- Verify all original routes (CR/DR/CCR/CDR/BOR/BOP/JV/SV/CV/Bank Deposit/Withdrawal/Plot Receipt/Plot Purchase/Extra Charges/Draft) against create/edit/logical delete, project-scoped related rows, permissions, printed reports and balances. LedgerController's customer-ledger deletion currently deactivates linked source and ledgers in a DB transaction; SameSourceCashVoucherEdit updates source and ledger via transactional snapshots; those existing flows need **runtime regression** to be accepted.
- Typed / approval/posting branches for JV/CV can still demand an audited reversal or draft-only edits; those rules have **not been altered** in this batch pending actual source/approval mapping. The owner requested independent ordinary edit/delete across modes; **therefore Milestone 03 remains INCOMPLETE** until those branches are safely supported, rather than falsely claiming completed.
- Browser verification of linked accounting totals and local PostgreSQL/MySQL migrations has not occurred. Do not run this new FK migration on production or staging without a separate approval.

**Owner local commands** (safe order):
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=VoucherRelationshipSafetyMilestoneTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan test --filter=FinanceVoucherEditPermissionParityTest
php artisan finance:audit-voucher-schema
php artisan finance:audit-voucher-schema --json | Set-Content -Path .\storage\app\voucher-schema-audit.json -Encoding UTF8
```
**Do not automatically run the new FK migration yet.** Upload the JSON report / relevant orphan output first. If all relationships have `orphan_count=0` and no incompatible FK, the named migration can be evaluated for local-only execution. Test CRUD against disposable records before any real voucher changes.

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
