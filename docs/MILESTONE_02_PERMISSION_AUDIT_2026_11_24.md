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
