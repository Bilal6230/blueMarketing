# Milestone 02 — Final permissions enforcement code batch, 2026-11-24

**Code implementation delivered, owner terminal/browser acceptance PENDING.** The previously owner-verified Roles UI grouping test remains **7 PASS / 478 assertions**, Finance Matrix **6 PASS / 365 assertions**, and Blade view cache PASS. The 441-permission live audit is closed and its data is retained below. No role-grant removal, no new business tables, no 21×16 permission explosion and no Labour CP voucher lifecycle edits.

## Implemented: compact Stock roles and guards

- Added **16 precise permissions only**: `stock items|parties|entries|billing view|create|edit|delete`. One additive `2026_11_24_180000_register_compact_stock_permissions.php` migration inserts missing Spatie `permissions` names only. No new table; no direct-user grant changes; down migration does not revoke role assignments.
- One central `StockAbility` and `AuthorizeStockAbility` enforce new module/action permissions on Stock index, dropdown meta, list/report endpoints, counters, item import/export, all item/party CRUD, entry/bill create and legacy reconciliation. Kind-dependent Entry/Bill document detail/update/delete guard now uses same ability checks. Pre-existing `stock.finance.approve` approval guard was not replaced.
- To avoid locking out existing live roles (the full JSON has **no old Stock CRUD permission names**), compatibility fallback continues to respect **`read slip` -> view**, **`add slip` -> create/edit**, **`delete slip` -> delete** and existing admin/superadmin grants; this is a deliberate **transition compatibility layer**. New roles can use independent actions for each workspace. Existing broad slip grants may authorize more than one Stock workspace until a separately reviewed migration of live role assignments; this is explicitly documented, not claimed as perfect isolation for legacy roles.
- Role editor shows four distinct **Stock · Items**, **Stock · Parties**, **Stock · Entries**, **Stock · Billing** tabs alongside Finance/Daily Entries and application modules; uses same existing jQuery checkbox/search and role persistence behavior.
- Stock Blade now passes per-user allowed rights; disallowed workspace tabs, New buttons and row Edit/Delete/Status actions are hidden. Auto-loaders for unauthorized list/counter requests are skipped, avoiding routine AJAX 403 toasts. Backend guard remains authoritative for direct requests.
- **Caveat:** The main stock page remains a shared multi-tab view, so data embedded in page metadata should not be considered a full per-tab data-isolation boundary; server list and write endpoints are independently guarded. This rollout aims at safe CRUD permissions, not multi-tenant/data-field confidentiality beyond existing selected-project scoping.

## Implemented: Labour/Attendance route parity

- Replaced unguarded `Route::resource('labours')` with explicit existing Labour read/create/update/delete middleware per real action; contractor routes already had these guards.
- Attendance week/report routes use existing `read attendance|read labour`; attendance write uses `create attendance|create labour` for legacy staff compatibility. Labour site, rates, history and print routes use existing `read/create/update labour` appropriately. **No new Labour or Attendance permission names**.
- `labours.payment`, `labours.payment.details`, `labours.payment.update`, `labours.payment.delete` left unchanged: this is the M03 Labour CP browser gate **ON HOLD**. Existing controller-level finance CP payment protections remain. Do not claim M03 Labour CP accepted from this M02 route rollout.

## Owner gate (new code ONLY)

After pulling:
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan migrate --path=database/migrations/2026_11_24_180000_register_compact_stock_permissions.php
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=Milestone02PermissionEnforcementContractTest
php artisan test --filter=RoleManagementModernizationTest
php artisan route:list --path=admin/stocks
```

**Focused browser acceptance on disposable roles:**
1. Roles → Create test role **Stock Items Viewer** with only `stock items view`. Sign in as testing user with that role: Stock page opens, Items tab visible, Billing/Entry/Parties and write buttons hidden; Items API GET works, Stock Items Create/Update direct action returns 403. Super Admin has full access.
2. Role with `stock billing view` and `stock billing create`: Billing tab appears, New Bill available, Entries/Items/Parties tabs hidden; other write endpoints deny 403. Use only disposable local data if actually saving.
3. An existing `read slip` / `add slip` / `delete slip` legacy role retains compatible Stock access; new Stock permission names do not silently revoke old grants.
4. Labour test role with `read labour` and separately `create attendance`: corresponding list and attendance routes work; unauthorized Labour create/update/deletes return 403. No Labour CP payments/edits/deletes are part of the test.
5. Roles editor: Finance CR/DR and general Stock/Attendance/Labour tabs remain independent; save/reopen role retains selected permissions.

Do **not** rerun owner-accepted M04/M05 voucher tests or Labour CP. If new local suite and browser gate PASS, mark M02 **CLOSED / OWNER ACCEPTED**; otherwise fix any reported exceptions before broadening permissions.

# 2026-11-24 — Latest owner verification and Stock route compatibility discovery

**Owner latest terminal PASS:** pulled `7126bc40`; `RoleManagementModernizationTest` **7 PASS / 478 assertions**, `php artisan view:cache` PASS. Earlier `FinanceActionPermissionMatrixTest` **6 PASS / 365 assertions**. The categorization of real old permissions is verified; no need to rerun that gate.

**Stock security audit finding requiring compatibility planning before enforcement:** the 441-permission local audit contains **no** `read stock`, `create stock`, `edit stock`, `update stock`, or `delete stock` records. It does contain `read slip` (4 role grants), `add slip` (2), `delete slip` (2), and `stock module` (0), all already granted/non-granted as reported by the actual database. Meanwhile, `routes/web.php` stock items, parties, entries, bills, document edits/deletes and exports/imports do not consistently have named Spatie permission middleware; `StockController` import logic and some document flows reference `create stock`/`edit stock`/other compatibility fallbacks even though those names are absent from this DB. **Do not abruptly add `permission:create stock` middleware**: it would block all existing non-superadmin users when these rights have not been seeded/granted. Likewise, an unreviewed broad stock permission cross-product would add unnecessary options.

**Safe proposed next change:** use the real functional stock surfaces—Items, Parties, Entries, Bills—to design a small explicit View/Create/Edit/Delete permission set only when those actions actually exist; implement a controlled grant migration or deliberate compatibility bridge for existing `read/add/delete slip` roles; enforce AJAX + page + export endpoints together; audit and test actual roles before switching over. Keep stock approval on its already separate approval policy and preserve existing user grants. Avoid changing Labour CP while its browser gate is ON HOLD.

**M02 remains IN PROGRESS.** This security-related compatibility discovery prevents claiming full Stock permission enforcement from the passed role-editor tests.

# OWNER LOCAL DATABASE AUDIT — 2026-11-24, authoritative (supersedes older pending-audit notes below)

Owner provided full file `permissions-audit.json` (database `bluenew_clean_final`, generated `2026-11-24T17:18:51+05:00`; read-only report). The complete JSON was parsed (441 `permissions` entries). Results:

| Metric | Actual database count |
|---|---:|
| All permissions | 441 |
| Finance granular | 340 |
| General / non-finance | 90 |
| Hidden legacy voucher names | 11 |
| Finance permissions not selectable in current role editor | 171 |
| Hidden entries with active grants | 181 |
| Unassigned permissions | 3 |
| Direct user grants | 0 |

The **three unassigned** are `direct-update` (id 87), `stock module` (id 89), `voucher approver` (id 101). **They are review candidates only, NOT automatically safe to delete**, since route references, direct `can()` calls and seeders may still depend on names or framework Super Admin override. `stock module` is now categorized under Stock in editor even though currently unassigned. The **171 finance-unexposed** are legacy cross-product action names outside supported UI choices; many of these have role grants. The **181 assigned hidden** must remain intact until route-level and role-level migration is proven.

The uploaded JSON contains **340 finance** permissions: 21 modules × 16 actions (=336), plus four additional verified Cheque/Online correction rights (`finance cheque correct`, `finance cheque correction approve`, `finance online correct`, `finance online correction approve`). They are security-sensitive, currently assigned to Super Admin, and not redundant just because they are excluded from generic role toggles. All 11 legacy voucher names are present. No direct-user permission assignments were recorded.

Found existing general permission strings that the previous generic module parser wrongly dumped into `Other Access`: `add slip`, `bank posting`, `pass cheque`, `party_reprot` (original spelling), `report cashbook`, `report party_ledger`, `report party_report`, `plot voucher`, `plot voucher form`, `plot voucher report`, `print voucher`, `view inventory`; all now mapped into existing matching resource tabs **without renaming permission names or altering grants**. Only `direct-update` remains in the miscellaneous bucket in this complete local audit. New role grouping regression assertions added; **this follow-up patch has not been run in the owner's environment yet**.

**Owner terminal results immediately before this categorization patch:** `RoleManagementModernizationTest` **7 PASS / 466 assertions**; `FinanceActionPermissionMatrixTest` **6 PASS / 365 assertions**; `php artisan view:cache` PASS. These validate the previous tested commit, not the new follow-up. Current M02 status remains **IN PROGRESS**. Later work is required to implement Stock Entries/Bills/Items/Parties and Labour/Attendance route permission parity; Labour CP browser workflow expressly ON HOLD, no new permission tables/migrations.

**Focused verification for the current follow-up:**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan test --filter=RoleManagementModernizationTest
php artisan view:cache
```
Browser: Roles → Create/Edit test role, verify historical report/cheque/plot/stock permission names are categorized into relevant tabs, and previously selected permissions persist across tabs and after Save. No need to re-export the audit JSON or rerun accepted FinanceActionPermissionMatrixTest.

---

## 2026-11-24 — First owner terminal gate / pasted audit excerpt (fix committed, re-run pending)

Owner ran first M02 gate: `view:cache` PASS; `FinanceActionPermissionMatrixTest` 6 PASS / 365 assertions; `RoleManagementModernizationTest` 6 PASS, 1 FAIL (442 assertions) specifically `update booking price`: expected module `booking` but got `other`. Cause was the existing per-resource grouping regex accepting only two-token names. Fixed `RolePermissionCatalog::generalModule` for real multiword resource permissions (`update booking price`, `view booking report`, `read landing page`, `update plot number`, `stock module`, `labour module`) and expanded regression. No grants/DB actions changed; awaiting owner re-run before marking PASS.

Owner's pasted `permissions:audit --json` excerpt was truncated by terminal scrollback (starts partway into finance draft; no opening report totals) and cannot be used to calculate complete database counts. It **does** demonstrate many unselectable finance cross-product permissions still granted to legacy roles (e.g. `finance draft clear` and `finance jv reverse`), so **do not delete these assignments blindly**. Export entire read-only JSON directly to a disk file rather than printing 3000+ lines to PowerShell, then share the saved file:
```powershell
php artisan permissions:audit --json | Set-Content -Path .\permissions-audit.json -Encoding UTF8
Get-Item .\permissions-audit.json | Select-Object FullName,Length
```
Full output is required for authoritative category totals and further safe cleanup recommendations. Do not paste terminal scrollback; share the file as an attachment. Existing finance permissions preserved.

# Milestone 02 — Roles & Permissions / Daily Entries audit (2026-11-24)

**Branch:** `upgrade/laravel-framework`. This is a **source-code audit and first implementation batch**. The owner's current local `permissions` and role-grant tables have **not** yet been read: do not invent counts of actual database grants, call entries unused, or delete them. Use `php artisan permissions:audit --json` against the owner's local DB to complete the live grant inventory.

## What the source actually contains

- One **Spatie permission/role** database model and pivots (`permissions`, `roles`, `role_has_permissions`, `model_has_permissions`). No new table is required.
- `FinanceActionPermission` declares **21 granular finance modules** and **16 possible action names**. Migration `2026_11_18_130000_register_finance_action_permissions.php` registers their **21×16 = 336** cross-product entries regardless of whether any particular module has an action. **Registration is not proof that all 336 actions are used.** Existing `RolePermissionCatalog::moduleActions` already narrows the UI to relevant actions.
- Separate Daily Entry permission groups: CR, DR, CP, CCR, CDR, BOR, BOP, JV, SV, CV; linked Bank Deposit, Bank Withdrawal, Plot Receipt and Plot Purchase; plus Directory/Draft/Extra Charge/Cheque/Online/PPR/ECP.
- Granular route authorization is centralized in `FinanceActionPermission::forRequest` and `AuthorizeFinanceAction`; browser workspaces and actual write routes also contain additional source/custody/approval checks.
- `RoleController` already guards create/edit/archive, rejects legacy voucher permission additions, protects Super Admin and preserves historical grants on edits. `RolePermissionCatalog::LEGACY_VOUCHER_NAMES` lists older generic permissions that still may be needed by legacy code. **Never delete or revoke these solely because their UI entries look duplicated.**
- Before this batch, non-finance permissions such as Users, Roles, Permissions, Settings, Projects, Plots, Leads, Payroll, Attendance and Labour were all lumped into a single "Other Modules" tab in the Roles editor.
- Separate `PermissionController` offers low-level permission CRUD; this is *not* the recommended everyday module-role assignment experience and must not be used for bulk manual deletion of system permission names.

## Implemented in this batch

1. Roles editor now has one **searchable navigation tab per existing module**, keeping finance/daily voucher tabs separate and breaking non-finance items into resource-specific tabs (Leads, Stock, Labour, Attendance, Payroll, Users, Roles, Permissions, Settings, Reports, and other existing resources) when relevant names exist in the DB. Two compact navigation headings: **FINANCE & DAILY ENTRIES** and **OTHER MODULES**. Permissions and checkbox state still come from live `permissions` records; nothing is hardcoded as an automatic new grant.
2. Unexposed finance actions with no general browser Reverse UI (JV/SV/CV, CCR/CDR, BOR/BOP) were removed from *selectable choices*; the database permission names themselves remain intact. Any pre-existing hidden finance or legacy grants are automatically preserved on role edit, so navigating/clearing visible categories cannot accidentally revoke them.
3. Reuses read-only `php artisan permissions:audit --json`, now also showing source-derived `selectable_in_role_editor`, per-resource `module`, counts of **finance_unexposed** and **hidden_with_active_grants**. Also reports actual grant counts and unassigned records; **unassigned does not imply safe to delete**.
4. Regression coverage expanded in `RoleManagementModernizationTest` to check distinct module resolution, supported vs unsupported Reverse choices, two navigation groups, preservation of hidden grants, and enhanced audit. No new migration or destructive SQL.

## Confirmed permission gaps requiring a separate guarded rollout

- `StockController`: legacy permissions **read/create/edit/update/delete stock** already appear in specific imports/stock document branches, but `routes/web.php` stock index/items/parties/entry/bill handlers do **not** consistently have per-action middleware. A safe subsequent batch should apply *real* Stock Items, Parties, Entry and Billing abilities with route + AJAX UI parity and migration/grant compatibility, not create broad unchecked cross-products.
- `LabourController`: payment Edit/Delete reuse **finance cp edit/delete** guards, but attendance/site/rate/payment routes show inconsistent granular route middleware. Labour CP browser acceptance remains expressly **ON HOLD**, and this milestone must not silently reopen or change its voucher payment lifecycle. Design future labour/attendance scopes around actual existing operations, with explicit backwards-compatible grants and targeted tests.
- `FinanceActionPermission` registration creates many finance action names that no browser action uses. **Do not purge them** until inspecting the active DB roles/direct user grants and confirming real route/controller uses; cleanup should focus on removing misleading selections first, not destroying permissions.
- `Approval Required`, `Reverse`, `Delete`, `Edit` and `Release` are distinct actions: do not collapse them into a single CRUD checkbox. No action may be inferred from another's permission.

## Owner verification for first UI/audit batch

```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=RoleManagementModernizationTest
php artisan test --filter=FinanceActionPermissionMatrixTest
php artisan permissions:audit --json
```

Browser: Roles → create/edit a **disposable** non-Super Admin test role; verify individual CR/DR/etc and Users/Stock/Payroll/Attendance/Settings tabs appear *only where corresponding permissions exist*, search filters names, selecting one tab preserves checkboxes selected in others, save/reopen retains assignments, Super Admin remains locked. No need to rerun accepted M05 reversal tests.

**Status:** Milestone 02 **IN PROGRESS**, improved role navigation source committed, owner terminal/browser tests pending, broader Stock/Labour permission enforcement and complete cross-module matrix **still open**. Milestones 01/04/05 CLOSED; M03 Labour CP ON HOLD; M06 activity audit LAST.
