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
