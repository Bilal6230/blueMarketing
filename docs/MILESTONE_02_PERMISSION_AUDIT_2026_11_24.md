# 2026-11-25 — M02 OWNER ACCEPTED / CLOSED

Owner ran `Milestone02BookingLabourPermissionsContractTest` and confirmed **6 PASS / 95 assertions**. All five current browser test groups were previously explicitly confirmed **PASS**: Booking CRUD vs separate Price Update, Leads CRUD vs Assign Lead, Labour Master vs Attendance, Accounting read-only Write denial, and Roles permission revocation/visibility/direct endpoint guards. Previously accepted Stock, Tasks, Users and finance permission gates are preserved. **Final owner-accepted M02 local scope: CLOSED.** No repeat browser tests needed.

The three permission-only migrations previously listed below have no separately pasted migration-status output in this close-out; do not assert live production deployment. M03 Labour CP ON HOLD; M01/M04/M05/M06 CLOSED.

---

# 2026-11-25 — M02 cross-module browser acceptance: OWNER PASS

**Owner confirmed all browser checks PASS** for the final permissions matrix:
- Booking: independent Read/Create/Update/Delete and Price Update.
- Leads / CRM: CRUD permissions separate from Assign Lead.
- Labour Master: independent from Attendance view/mark permissions.
- Accounting: Read-only cannot Create or Update.
- Role Management: toggling permissions correctly affects sidebar, buttons and direct endpoint/API authorization.

These five browser groups are **accepted**. Do not request any of them again unless an actual new regression or affected code change requires it.

**Terminal verification status:** The prior 2026-11-25 Booking/Labour/Attendance/Leads permission migration and focused regression suite were documented as pending. Browser PASS does not itself establish those terminal commands ran. No terminal PASS output has yet been supplied in this thread for the latest `Milestone02BookingLabourPermissionsContractTest` batch. Therefore M02 browser acceptance is **CLOSED**, but overall M02 stays **terminal sign-off pending** until the relevant targeted automated gate is verified. This note does NOT ask for another browser check. The outstanding command set is already in the section below, and should not be broadened to previously accepted older tests.

**Other milestones:** M01, M04, M05, M06 CLOSED/ACCEPTED. M03 Labour Payment CP remains ON HOLD.

---

# 2026-11-25 — M02 Role Permission discovery + module integration batch (NEW OWNER GATE PENDING)

Owner confirmed **3 browser acceptance scenarios PASS**: CR View OFF / DR View ON; Employee Tasks View OFF; Users Read ON / Update OFF. Earlier Stock no-permission and exact Stock action browser test PASS; latest permission suites **Milestone02PermissionEnforcementContractTest 5/150**, **Milestone02StrictModuleAuthorizationContractTest 5/67**, **RoleManagementModernizationTest 8/485** PASS, total **18/702 assertions**. These are accepted results on prior source. Do NOT request repetition except where this new batch changes the tested area.

## Investigation: missing vs already existing rights

| Area | Existing actual rights / mechanisms | New / adjusted implementation | Admin navigation |
|---|---|---|---|
| Booking / Plot Sales | Existing `delete booking`, `update booking price`, `view booking report`; business form incorrectly relied on `read/create/update plot` | Add **`read booking`, `create booking`, `update booking`** (and ensure `delete booking`) via one migration, change Booking listing/create/metadata/edit/transfer/schedule/cancel/recovery routes, metadata edit UI and sidebar to exact Booking rights. **Pricing remains independently controlled by `update booking price`**. Use one-time mapping of existing Plot role grants to new Booking rights, then independent revocation works. Existing Plot Master and Inventory View remain separate. | **Bookings / Plot Sales** tab |
| Leads / CRM | `read/create/update/delete lead`, `lead search`, `lead details`, `lead report`, `lead pendings`, approval `assign lead` (not reliably seeded / misclassified as Other) | Ensure **`assign lead`** exists via idempotent migration; only if it was missing, preserve legacy approval authority by granting it to admin/superadmin roles that already have `update lead`. Role catalog maps `assign lead` into the Leads tab. Existing Lead approval methods enforce this role ability once registered. | **Leads / CRM** tab |
| Labour Master | `read/create/update/delete labour`; Sites currently use `create labour` | No unnecessary duplicate Labour CRUD permissions. Existing route guards already cover master, Sites and rates; hide Add/Edit Labour/Site form/actions from read-only users. Do not modify Labour CP voucher/payment lifecycle (M03 hold). | **Labour Master** tab |
| Labour Attendance | `read attendance`, `create attendance` already exist; former attendance routes incorrectly accepted **`read labour` or `create labour`** as bypass | Remove OR fallback; Attendance GET/report requires **`read attendance`**, POST mark requires **`create attendance`**. One-time migration maps old Labour Read/Create role grants to Attendance counterparts before strict revocation. Attendance tab and board hide/disable without correct right; controller avoids loading attendance worker records for unauthorized viewers. | **Attendance** tab |
| Accounting categories | Existing `read/create/update/delete accounting` | Fix category Create/Update routes that incorrectly used **Read** permission. Now Create requires `create accounting`, Update requires `update accounting`. | Accounting tab |
| Stock | 16 exact Stock abilities across Items/Parties/Entries/Billing | Previous browser PASS; unchanged in this batch. | 4 distinct Stock tabs |
| Employee Tasks | Read/Create/Update/Delete implemented and previous browser View OFF PASS | No new task permissions. | Employee Tasks tab |
| Other core modules | User/Role/Permission/Settings/Project/Plot/Sector/Area already have CRUD; Finance 21 granular families | No indiscriminate permission multiplication. Source-level route gaps corrected in previous M02 batch. Broader per-record and AJAX parity still needs acceptance; not every route is claimed exhaustively verified. | Individual tabs |

**Clean Role editor:** Re-labeled tabs to **Bookings / Plot Sales**, **Leads / CRM**, **Labour Master**, **Attendance**. Obsolete generic `stock module` and `labour module` toggles are classified as historical and hidden (actual Spatie names/role grants retained). Role updates explicitly retain preexisting hidden grants, preventing accidental revocation. This is UI cleanup, not destructive DB cleanup.

**Financial correctness guard:** The legacy `booking.customer.destroy`, `booking.voucher.show` and `booking.customer.approve` are *customer-ledger voucher routes*, NOT Booking CRUD routes. Existing global `finance.action` resolves their persisted source type and exact `delete/details/approve/reject` rights dynamically; do NOT replace it with static Booking or PPR authorization. Booking price amendment still uses the separate `update booking price` scope; metadata changes now use `update booking`, not broad Plot Update. PPR and Labour CP changes do not occur in this batch.

## New code / regression

- Additive idempotent **permission-only** migrations (NO schema tables):
  - `database/migrations/2026_11_25_110000_register_booking_permissions.php`
  - `database/migrations/2026_11_25_111000_align_labour_attendance_role_grants.php`
  - `database/migrations/2026_11_25_112000_register_lead_assignment_permission.php`
- Updated `BookingEditPageTest` expected metadata permission scope, `Milestone02PermissionEnforcementContractTest` independent attendance routes, and new `Milestone02BookingLabourPermissionsContractTest` for actual mappings and UI/backend alignment.
- Tests **not yet run on owner's local application**; treat this batch as code-delivered, NOT accepted.
- Owner-run targeted gate:
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan migrate --path=database/migrations/2026_11_25_110000_register_booking_permissions.php
php artisan migrate --path=database/migrations/2026_11_25_111000_align_labour_attendance_role_grants.php
php artisan migrate --path=database/migrations/2026_11_25_112000_register_lead_assignment_permission.php
php artisan optimize:clear
php artisan permission:cache-reset
php artisan view:cache
php artisan test --filter=Milestone02BookingLabourPermissionsContractTest
php artisan test --filter=Milestone02PermissionEnforcementContractTest
php artisan test --filter=RoleManagementModernizationTest
php artisan test --filter=BookingEditPageTest
```

**Focused browser after terminal PASS** using a disposable non-superadmin user with **one** target role:
1. Roles → Search `booking`, `lead`, `labour`, `attendance`; separate module tabs and appropriate actions available. Save/Reopen and refresh the user session.
2. **Booking Read ON, Create/Update/Delete OFF**: Plot Sales listing visible, New Booking/Edit/Delete absent; direct Create/Update/Delete requests deny. Toggle Booking Read OFF: Plot Sales sidebar hides even if Plot Master Read is ON; direct Booking listing 403. Grant `update booking price` separately only when needed.
3. **CRM Lead Read ON, Update OFF**: listing accessible, Edit controls hidden, direct Update blocked. `assign lead` is a separate permission for approvals.
4. **Labour Read ON, Create/Update OFF**: Labour listing visible but Add/Edit Labour/Site actions hidden. **Attendance Read OFF** (even while Labour Read ON): Attendance tab hidden and attendance week/report URL 403; Attendance Read ON: read-only board available; Attendance Create OFF: marking disabled and direct POST 403.
5. **Accounting Read ON, Create/Update OFF**: category listing accessible, direct category Store/Update denies.
6. Do NOT browser-test Labour CP; remains expressly ON HOLD.

**M02 status:** substantial source integration delivered; permission role UX, selected route matrix and browser acceptance pending. M01/M04/M05 CLOSED, M03 Labour CP ON HOLD, M06 Activity Audit LAST. Do not claim all application permission combinations fully proven until role-browser acceptance.

---

# 2026-11-24 — Strict permission integration audit / owner Stock bypass reproduction (CURRENT)

Owner screenshot: role **Data Operator** has **0/4** permissions in Stock Items/Parties/Entries/Billing tabs yet a logged-in user can open Stock, see every tab and execute actions. **Confirmed root cause from source**: `StockAbility::allows()` treated generic `read slip`, `add slip`, `delete slip` as implicit grants to every Stock workspace, and treated plain `admin` as universal Stock rights. Separately `StockController::authorizeStockDocumentAction` accepted `direct-update` and `<action> stock`, while item import update accepted `edit stock`. These were direct bypass paths, and previous compatibility tests incorrectly expected the unsafe fallback. **This is a real authorization defect, not merely a sidebar display issue.**

**Fixes committed in the same implementation batch:**
1. `StockAbility` now grants each action **only** on the exact `stock <items|parties|entries|billing> <view|create|edit|delete>` permission, except protected system `superadmin` / `super-admin`. Plain `admin` and legacy slip rights are **not** implicit Stock grants. Stock index, sidebar, AJAX endpoints, row actions and server document Edit/Delete already reuse this central ability and therefore become strict together.
2. Removed old `direct-update`, `edit stock` and generic `<action> stock` alternatives in document and item import update; retained proper finance approvals and selected-project/source checks. Stock metadata endpoint now returns only Items / Parties datasets needed by authorized View or Entry/Billing editing rights, not both indiscriminately.
3. Expanded previous Stock contract test to assert **legacy slip/admin users get denied**, independent narrow permissions remain isolated and `superadmin` remains protected. Added `Milestone02StrictModuleAuthorizationContractTest` covering exact Stock permissions, no document/import bypass, metadata and sidebar consistency.
4. **Employee Task Management** was a second verified View-ungated module: left menu appeared for everybody and routes only checked manager role internally. Added four true Employee Tasks permissions: `read employee task`, `create employee task`, `update employee task`, `delete employee task`; real route middleware, controller action checks, task UI controls and independent Roles tab. Additive migration `2026_11_24_190000_register_employee_task_permissions.php` uses the existing Spatie tables, **no new tables**. To preserve preexisting task access when deployed, assigns `read employee task` to existing web roles and manager CRUD to existing admin/superadmin role names; owner may then revoke View on specific roles, which will hide Task sidebar and deny direct GET. Status edits remain available for assigned employees with Task View, while managerial editing requires Update. No task records edited.
5. Audited named routes for other resource modules and closed specific missing action gates using **existing** grants: `user.status.toggle` → `update user`; `update_plot_number.store` → `update plot number`; `project.plot.history` → `read plot`; `lead.work` → `read lead`; `lead.search` → `lead search`; `admin.reports.check_history` → `cheque report`. No broad shortcut grants or new names. Stock Items import is now visibly hidden unless Items Create, and embedded posting-account options are omitted from narrow Items/Parties read-only pages. 
6. Verified source for other primary sidebar groups: granular Daily Entries/CR/DR/BOR/BOP/etc link View checks align to the `AuthorizeFinanceAction` route boundary; Stock and Labour sidebars now match their module guard; Users/Settings/Projects/Plots/Reports/Attendance use their existing Spatie Read permissions. Do not claim every underlying custom AJAX endpoint is exhaustively verified: remaining unusual/legacy routes must be evaluated case-by-case before global close.
7. **Effective permissions are cumulative across all assigned roles and direct-user grants.** The role-edit screenshot alone does not prove the signed-in user holds only that role. Owner should verify user roles when testing. Even if a role has 0 Stock permissions, another assigned role with explicit Stock rights will authorize that user. No role grants deleted automatically; no destructive migration.

**Behavior change / important:** Because owner specifically requires revoked modules to disappear, former staff relying *only* on `read slip` to reach Stock will now lose Stock access and see a correct 403. Give them only necessary new `stock ... view/create/edit/delete` grants through Roles. **Do not re-enable broad fallback** merely to eliminate 403; previous backwards-compatibility note is superseded by this strict owner requirement. `superadmin` remains privileged by design.

**New tests to run (previous M02 tests need not be repeated except the changed Stock contract):**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan migrate --path=database/migrations/2026_11_24_190000_register_employee_task_permissions.php
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=Milestone02PermissionEnforcementContractTest
php artisan test --filter=Milestone02StrictModuleAuthorizationContractTest
php artisan test --filter=Milestone02SidebarViewPermissionContractTest
php artisan test --filter=RoleManagementModernizationTest
```

**Browser acceptance, use a disposable non-superadmin test user with only the target role**:
- No Stock permissions: Stock sidebar hidden and `/admin/stocks` returns 403. This holds **even when read/add/delete slip are granted**. If a user holds multiple roles, check their effective union.
- Only `stock items view`: Stock link and Items tab visible, not Entry/Billing/Parties; no New/Edit/Delete; direct writes 403.
- Add `stock items create`: New Item available; unrelated Stock workspaces remain hidden. Revoke View: sidebar gone and Stock URL 403 (even if Create remains granted).
- Task View present: Employee Tasks sidebar and GET allowed, permitted assigned tasks only; revoke `read employee task`: sidebar hidden and GET 403. Create/Update/Delete each affect only corresponding UI and server action.
- Finance CR OFF/DR ON behavior from previous sidebar fix remains independent; do not repeat deep Finance voucher functionality tests. Labour Payment CP workflow stays ON HOLD.

**Source audit limitation:** Admin routes are numerous and may use route middleware, `finance.action` dynamic guards or controller-level scope. The obvious missing user/plot/CRM/report route checks above were corrected, but do not claim all per-record authorizations across the entire application are formally proven until owner browser roles accept. Employee attendance and labour share some historical permissions; Labour Payment CP remains explicitly untouched.

**Latest owner result preceding this fix:** `Milestone02SidebarViewPermissionContractTest` **3 PASS / 51 assertions** and `php artisan view:cache` PASS; these don't validate this new strict Stock change. **Local new tests and browser checks PENDING**. M02 must not be marked closed yet; M01/M04/M05 remain CLOSED, M03 Labour CP ON HOLD, M06 Activity Audit LAST.

---

# 2026-11-24 — M02 Sidebar View permission parity (code committed, owner acceptance pending)

Owner's browser screenshot: logged-in limited role sees **Credit Voucher (CR)** in Daily Entries sidebar, but clicking `/admin/finance/daily-vouchers?type=CR` yields **403: You do not have permission for this finance action**. The backend already correctly checks granular `finance cr view`; the left menu incorrectly used old broad `read voucher` to show *all* daily voucher links. This was a **sidebar authorization presentation bug**, not a reason to weaken the backend permission guard.

**Fixed implementation:**
- Active sidebar source is `resources/views/admin/layouts/sidebar-menu.blade.php` (included by `sidebar.blade.php`; legacy `@if(false)` block is inactive). Every live Daily Entries finance link is now gated by the same exact `FinanceActionPermission::name(module,'view')` ability as its route. Voucher type map: CR→cr, DR→dr, CCR→ccr, CDR→cdr, BOR→bor, BOP→bop. Cheque/Online, Directory, Bank Deposit/Withdrawal, Plot Receipt/Purchase, Draft, JV/SV/CV and Extra Charge use their own module View ability. A role with `read voucher` but no `finance cr view` no longer sees CR; direct URL **continues to 403**, as intended. Another series remains visible only when its own View right is granted.
- Daily Entries parent hides if none of its permitted child links is visible. Existing `read slip` continues to control its distinct Receive Payment route; Finance Change/Approval navigation still respects existing policy checks and Directory View where required.
- Stock sidebar now calls its real `StockAbility::allows(user,'workspace','view')`, matching protected Stock index, including compatibility `read slip`; Labour menu uses `read labour` matching the Labour index. No longer dependent on unassigned old `stock module`/`labour module` legacy flags. Definitions uses `read accounting` for account links instead of `read user`; empty Definitions headings for role-only accounts prevented, and Reports parent recognizes `read plot` for Project Recovery.
- New source regression: `Milestone02SidebarViewPermissionContractTest` verifies exact CR→Finance View correspondence, independent voucher/menu mappings, Stock/Labour matching, and removal of the old broad Daily Entries condition.
- **No permissions or grants edited, no migrations, no finance ledger changes;** do not automatically grant View to fix a legitimately forbidden direct URL. Existing user can be granted the appropriate module View via Roles if required.

**Local confirmation requested for NEW FIX only:**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=Milestone02SidebarViewPermissionContractTest
```

**Browser:** use limited test role and refresh the Dashboard sidebar: without `finance cr view`, **CR hidden** and direct CR URL **403**; give only `finance cr view`, reload and **only CR** appears among voucher series; revoke it again and CR disappears. Check DR remains independent, Stock menu appears with Stock View or compatible `read slip`, Labour menu only with `read labour`. This is a role-permission acceptance gate, not a test of held Labour CP payment functionality.

**Remaining scope note:** Employee Task Management currently has no separate dedicated `read employee task` permission nor route middleware; its always-available navigation reflects that original unprotected design. A future task permission policy must be agreed and rolled out compatibly rather than pretending a View permission already exists. M02 remains open until owner browser confirms the permission/navigation matrix.

---

# 2026-11-24 — Owner terminal gates PASS; urgent Dashboard and Roles sort browser hotfix

Owner pulled M02 Stock/Labour batch `8b2d2b16`, ran additive Stock permission migration **DONE**, `php artisan view:cache` **PASS**, `Milestone02PermissionEnforcementContractTest` **5 PASS / 95 assertions**, and `RoleManagementModernizationTest` **7 PASS / 478 assertions**. The code-level Stock and Labour/Attendance permission contract gate passes. **Browser role matrix acceptance is still pending**; owner reported two blockers first:

1. **Dashboard crash after global Labour master migration:** Screenshot shows MySQL `SQLSTATE[42S22]` missing column on dashboard, `app/Services/Dashboard/ModuleMetrics.php:94`: old `labours.project_id` reference is invalid because `2026_11_24_120000_make_labours_global_master.php` removed the column. Implemented schema-aware Labour KPI: only filter by `labours.project_id` when present (older DB), otherwise count active, not-deleted global labour workers with an attendance row for the selected project using project-scoped `EXISTS`. Attendance present/pending/payable/cost metrics remain scoped to `labour_attendances.project_id`. No database change or Labour CP flow change. New `DashboardModuleMetricsGlobalLabourContractTest` ensures both branches and real migration contract. **Owner must verify browser dashboard and focused test after pull.**
2. **Roles listing unclear order:** screenshot lists IDs 9→4, 1, 2, 3 because default sorting was `updated_at DESC`. Changed backend `RoleController::rows` and client table default/Reset to **ID ASC (1,2,3…9)**. Column sorting remains interactive, with stable ID tiebreak. Regression added to `RoleManagementModernizationTest`. **Owner must verify browser Roles order and focused test after pull.**

**Only new hotfix acceptance gate:**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=DashboardModuleMetricsGlobalLabourContractTest
php artisan test --filter=RoleManagementModernizationTest
```
Browser: visit `/admin/dashboard` (no SQL exception; selected project's Labour KPI), visit `/admin/role` (IDs ascending 1..9; manual column sorting and Reset still work). Do not rerun previously owner-accepted Stock/Finance tests. After these 2 blockers PASS, finish the previously planned disposable-role Stock/Attendance permission browser matrix; **do not mark M02 closed until owner verifies it**. M03 Labour CP remains ON HOLD; M04/M05 CLOSED; M06 LAST.

---

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
