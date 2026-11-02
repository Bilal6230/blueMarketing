# BlueMarketing Dashboard & Sidebar Upgrade — Client Requirements and Execution Tracker

**Date:** 2026-11-02  
**Status:** Planning / baseline audit; implementation not yet started  
**Branch:** `upgrade/laravel-framework`  
**Purpose:** Approved-scope working document, not an assertion that features are built.

## 1. Source of truth and precedence
- Client specification supplied on 2026-11-02: “BLUE MARKETING — 01. DASHBOARD & MAIN MENU STRUCTURE — Software Upgradation Requirements – Revised Version”.
- Additional owner instruction: **add Dasti Cash / Dasticash to the dashboard** even though the client specification omitted it.
- Existing source confirmed on branch `upgrade/laravel-framework`: `routes/web.php` dashboard routes, `app/Http/Controllers/DashboardController.php` and `resources/views/admin/dashboard.blade.php`.
- If the client specification conflicts internally, section **D. Important Instructions to Developer** takes precedence: **Pending + Overdue CRM follow-ups share one card** (with distinct drill-down/filter states); Today Follow-ups remains separate. Do not add Total Income or Total Expense financial cards.
- No production data changes, migrations, destructive edits, accounting formula changes, or security bypasses without separate verification and explicit approval.

## 2. Verified existing dashboard baseline
In `DashboardController@index`:
- Project and role dependent user scope; `getSelectedTown()`; user/role permissions.
- Today leads and all leads; follow-up count via `Setting::getTodayLeadWorkCount()` in Blade.
- Ledger derived total balance, cash in/out, bank in/out; **Dasticash amount deducted from hand cash** in current calculation.
- Today's attendance punches, project-scoped Dasticash records and employee list.
In `admin/dashboard.blade.php`:
- Four cards: **New Leads Add Today**, **Today Follow UP**, **Pending Works**, **Total Balance**.
- Lead search by mobile number; employee attendance punch controls.
- Financial Summary: **Hand Cash Balance**, **Current Bank Balance**, **Available Cash Balance**.
- **Dasticash Summary** table: ID, Name, Amount, Description, Actions; Add/Edit/Delete modal/AJAX.
- **Punch-in Records for Today**: Date, User Name, Punch In, Punch Out, Total Hours.
- Existing controller/view functionality is **not** evidence that all new requested summary metrics, approvals, task flows or Hikvision integration already exist.
- Current view's “Pending Works” displays `get_all_lead` (from `total_leads`); investigate business meaning before reusing label.
- Current financial bank display negates `total_bank_account_data`; do not silently normalize or alter sign conventions before finance reconciliation.

## 3. Target dashboard sections and acceptance criteria

### D01 — CRM
- Cards: New Leads; Follow-ups Completed; Today Follow-ups; **Pending & Overdue Follow-ups** combined, with separate pending/overdue drill-down filters and an as-of period end count.
- Unified card drill-down report with Lead ID/Number, customer/contact, project, created/last/next follow-up dates, assigned employee, status and remarks.
- Employee breakdown in **same reporting interface**: assigned/new/completed/today/overdue numbers.
- Period presets Today, Yesterday, This Week, Last Week, This Month, Last Month, Custom; project/employee filters.
- Existing CRM behavior preserved and access controlled.

### D02 — Financial Summary
- Cards: Total Cash Balance, Total Bank Balance, Total Receivables, Total Payables, Today's Cash Receipts, Today's Cash Payments, Today's Bank Receipts, Today's Bank Payments.
- Underlying voucher and ledger aggregation checked; drill-down, account and period filters; no Total Income/Expense cards.
- Date-dependent “Today's” semantics versus selected date range explicitly labeled.

### D03 — **Dasti Cash / Dasticash (mandatory owner addition)**
- Dedicated visible widget/section with **Dasticash Summary**: entry count/total (only after verified query), existing ID, Name, Amount, Description, Edit/Delete, and **Add Dasticash**.
- Keep existing routes, create/update/delete workflows, validation, authorization, recalculation and transactional consistency.
- Internal table scroll, responsive compact actions, confirmation before destructive actions.
- Dasticash vs hand cash reconciled; never double-deduct in the dashboard.
- Avoid inventing “Dasti Cash” independent bank account or unsupported figures.

### D04 — Approvals Management
- Admin-only / permission-gated pending count and central queue: voucher approvals, lead transfers, voucher edit/delete, sales modifications, plot changes, attendance corrections, leaves, rework, other admin.
- Request detail: reference, type/module, requester/time, customer/project/account, old/new details, reason, status.
- Authorized view/approve/reject/request-info/remarks; immutable historical trail and enforced approval before finalizing guarded actions.
- Category support is **subject to source module/workflow audit**; do not falsely display a supported approval type.

### D05 — Employee Attendance
- Cards: total, present, absent, late, leave, half day, missing check-in/out.
- Employee-wise drill-down with punch timestamps; integrate Hikvision only after device/network/sync/security validation.
- Preserve existing manual punch behavior and permissions until replacement is verified.

### D06 — Employee Task Management
- Cards: generated today, assigned, completed today, pending, in-progress, overdue, submitted for approval, rejected/rework, upcoming.
- Employee metrics: assigned, completed, on-time, late, pending, overdue, completion percentage; drill-down.

### D07 — Cheque & Online Transactions
- Nine cheque cards: received, pending clearing, cleared, bounced, pending amount, cleared amount, partly adjusted, fully adjusted, pending debit/credit adjustment.
- Stages: received details → passed/cleared details → debit/credit adjustment and remaining amount; click to transaction history.
- Online: receipts, payments, pending, confirmed, pending debit/credit adjustments.

### D08 — Sales & Plot Inventory
- Counts: total, available, hold, booked, sold, cancelled.
- Values: available, hold, sold, sales total, received total, outstanding; project filter.

### D09 — Store & Inventory
- Total items, stock quantity/value, today's inward/outward, pending material requests; drill-down.

### D10 — Labour & Project Work
- Active labour/contractors, today's attendance, pending bills, outstanding payments, project-wise labour cost.

## 4. Sidebar / main menu structure (client order)
1. Dashboard
2. Definitions & Settings
3. Daily Entries
4. Administration
5. Reports
6. CRM
7. Sales & Plot Management
8. Store & Inventory
9. Labour Management
10. Employee Task Management
11. Employee Attendance

Keep existing submenus, route names and permission checks; **reorganize only navigation presentation** after inventorying all current links. Dasticash belongs in dashboard widget and its existing operational navigation should not be removed.

## 5. UI/UX acceptance
- Modern responsive summary cards with suitable icons, **animated 0→actual values** and optional animated donut/pie charts based on valid numeric series; honor reduced-motion settings.
- Clickable cards open the related authorized detailed report, not a dead end.
- On desktop, application dashboard viewport fits screen without **page-level vertical scrolling**: fixed header/sidebar, cards in bounded panels, per-widget/table scrolling, tab/group switcher for many sections. At mobile/narrow heights, preserve accessibility even if page scroll is necessary.
- Clear permission-aware modules, date/employee/project/account filters where relevant.
- Notifications for approvals, overdue tasks and critical activities.
- No manually keyed production figures: server-derived metrics, optimized read-only aggregate/query layer, caching with defined invalidation/staleness indicator.
- Edit/Delete actions only where the underlying workflow actually allows them; server-side authorization, CSRF, validation and audit history must remain effective.
- Client preview uses *clearly labeled mock values*, production must use actual authorized records.
- Avoid new animation/chart features that misstate total vs percentage or misleading finance composition.

## 6. Execution milestones and gates
| ID | Milestone | Dependency / acceptance gate | Status |
| --- | --- | --- | --- |
| M00 | Capture client requirements, add Dasticash, baseline audit and tracker | Client text + repo source | IN PROGRESS |
| M01 | Inventory dashboard routes, roles, sources, nav tree and existing reports | M00 | PENDING |
| M02 | Build approved full-screen clickable client prototype with exact requested modules | M01 + design approval | PENDING |
| M03 | Sidebar reorganize safely, keep route/permission compatibility | M01 + approved navigation | PENDING |
| M04 | Shared date/project/employee filters + metric API + query optimization | M01, existing source validation | PENDING |
| M05 | CRM cards, unified drill-down, employee breakdown | M04, CRM domain audit | PENDING |
| M06 | Finance eight cards + verified ledger drill-down and totals | M04, voucher/ledger reconciliation | PENDING |
| M07 | Dasticash redesigned widget, add/edit/delete preserved, balances verified | M01, finance reconciliation | PENDING |
| M08 | Approvals central queue and governed actions | Approval architecture & relevant workflows | PENDING |
| M09 | Attendance cards and Hikvision integration | Device sync feasibility and HR status definitions | PENDING |
| M10 | Employee task cards/drill-down | Task models and approval workflows | PENDING |
| M11 | Cheque + online transaction cards/stages | Reconciliation & source availability | PENDING |
| M12 | Sales/plot inventory summary | Sales/inventory data and status mapping | PENDING |
| M13 | Store summary | Inventory valuation & models | PENDING |
| M14 | Labour summary | Labour contracts/bills/account mappings | PENDING |
| M15 | Regression, performance, role permissions, device/data reconciliation, client sign-off | M03–M14 | PENDING |

**Progress accounting:** Scope and requirements documentation can be COMPLETE independently, but **code delivery is 0% until code/tests are committed and verified**. Never imply a prototype equals implemented functionality.

## 7. Non-regression / safety gates
1. Preserve original ledger computations and all voucher/JV/CV/SV/CR/CP/PPR/EC/ECP relationships; avoid coupling this dashboard task to unfinished Database V2 cutover.
2. Check source query definitions, soft delete/is_active, selected project, role scopes and period end semantics before any financial KPI is labeled correct.
3. Database backup or disposable clone for verification; do not modify live/staging finance balances.
4. Confirm menu link permission checks against original sidebar before changes.
5. Preserve Dasticash AJAX add/update/delete and balance synchronization; confirm authorization and accounting invariants.
6. Test desktop viewport and internal scroll, narrow/mobile, keyboard accessibility, reduced motion, large datasets and empty states.

## 8. Immediate next actions
1. Save this document in GitHub and verify commit.
2. Audit current sidebar Blade, routes and source metrics.
3. Prepare interactive client preview covering **all requested** sections, including Dasticash.
4. Request client approval of UI before modifying production dashboard presentation.
5. Implement in small audited changes, with evidence-based progress updates.

## 9. Implementation batch 01 — 2026-11-02
- **GitHub commit:** `eb381703ed057d4f694fb630e6e8ccb827003a8c`
- **Changed:** `resources/views/admin/dashboard.blade.php`.
- Added dashboard-scoped modern presentation (KPI cards, financial circles, Dasticash table, attendance table); existing Blade variables, routes, form actions, validation, permissions and AJAX blocks left in place.
- Added initial numeric count-up animation for top-level cards and finance circles, respecting `prefers-reduced-motion`.
- Added desktop height constraint plus scrollable dashboard content container; Dasticash and attendance tables scroll independently. This is an incremental layout, **not yet** the final client-specified zero-dashboard-scroll/tab-based design.
- **No backend API, sidebar regrouping, new CRM/finance/approvals/task/cheque metrics, or Hikvision logic implemented** in this batch.
- **Verification:** GitHub commit created. Local Blade compilation, browsers, finance arithmetic and role tests **not yet run**; user's `git pull` and local smoke test are next gates.
- **Status:** M00 document COMPLETE; M01 audit IN PROGRESS; M02 screen redesign PARTIAL (existing widgets only, client approval pending); M07 Dasticash visual styling PARTIAL (existing CRUD preserved in source but regression unverified); others PENDING.
- **Next pull:** `git pull --ff-only origin upgrade/laravel-framework`.
- **Local checks after pull:** `php artisan view:cache`, `php artisan view:clear`, inspect `/admin/dashboard` as administrator and a restricted user; verify Dasticash add/edit/delete, hand cash balance and scrolling without modifying real accounting records (use disposable clone for destructive actions).

## 10. Implementation batch 02 — compact dashboard-only composition (2026-11-02)
- **Commits:** `4e26d36d1504030395f1ad6b6c766e96704c8c53` and selector correction `601117e26633a82e4674080d3d10728d76008720`.
- **File updated:** `resources/views/admin/dashboard.blade.php`, **sidebar untouched** by owner's express instruction.
- Arranges the existing four KPI cards in a compact top row, and existing financial summary, lead search, attendance form, Dasticash and attendance-record widgets into a bounded responsive grid; preserves the underlying original Blade/form/AJAX elements by DOM relocation rather than duplicating controls.
- The three financial circles now display modern donut/ring-style visuals with original, live ledger numeric text. The visual arc is decorative only, **not** an accounting percentage (no unsupported financial ratios).
- On desktop the dashboard viewport container and widgets use internal scrolling; on narrower displays the layout falls back to responsive flow. Some heights/role combinations still require browser verification.
- Known limitation: with 5 widgets, third grid row spans both columns. UI may not match screenshot perfectly at every resolution; further tuning is needed after local screenshot.
- Existing hand cash/bank/total cash math, lead routes, punch forms, Dasticash Add/Edit/Delete modals and AJAX kept unchanged.
- **Local gate:** pull, `php artisan view:cache`, `php artisan view:clear`, hard refresh `Ctrl+Shift+R` at `http://localhost:8000/home`. Compare 1366x768 and 1920x1080 and report screenshot; verify no missing widget (Dasticash is mandatory). Do not run live destructive tests.
- **Progress:** M02 modern dashboard layout PARTIAL; M07 Dasticash layout PARTIAL; no production acceptance or browser signoff yet. Sidebar M03 explicitly deferred.

## 11. Incident: Batch 02 layout regression — rollback (2026-11-02)
- User's local screenshot after successful `git pull` showed the dashboard KPI row but oversized Punch-in Records occupying the rest of viewport, with Financial Summary, Dasticash, Lead Search and Punch form not visible.
- Cause: unsafe DOM relocation added in Batch 02; moved Blade-generated widgets into a newly assembled CSS grid and left incompatible row structures/height constraints. The attempted selector correction did not solve it.
- **Fix committed:** `6a821f087adf1686dca8fd7abb4d2869f6b1805b`; dashboard Blade restored **exactly to the prior Batch 01 revision** (`eb381703ed057d4f694fb630e6e8ccb827003a8c`), retaining compact card styling and count-up animation but removing faulty Batch 02 grid relocation and donut ring CSS.
- **Verification:** GitHub commit successful; local runtime verification pending user's pull and hard refresh. Do not mark Batch 02 complete. This supersedes the optimistic progress in sections above.
- **Status correction:** M02 layout **regressed then rolled back**; M07 Dasticash visibility expected to recover after local verification. Sidebar untouched.
- **Next:** Implement fixed-grid layout using explicit Blade wrappers rather than runtime DOM movement; run local Blade/browser verification before claiming complete.

## 12. Implementation batch 03 — direct Blade dashboard grid (2026-11-02)
- **Code commit:** `f2ce810b86669701aad891b3299a3c18972b5172`.
- Replaced the former legacy dashboard content section with **direct Blade** KPI row and 2x2 widget grid; removed reliance on prior faulty runtime DOM relocation.
- Four existing KPI values retained; cards link to their original report routes.
- Finance panel: hand cash, bank account, total cash values from existing controller with three contemporary donut-style *decorative* progress rings (not ratios); preserved `handCash` and `totalCash` identifiers used by original Dasticash AJAX updates.
- Quick action panel: Lead Search and Employee Attendance controls, with original `number`, `search_number`, `punch_button`, `date_time` selectors and POST `/admin/punch`; role-gated with original permissions.
- Dasticash panel: existing table name/amount/description columns, `dasticashTable`, `row-ID` and Add/Edit/Delete buttons/modal data attributes retained; original add/edit/delete AJAX script and modal blocks were not rewritten.
- Attendance records panel: original record fields, role permission, scrollable table. Updated visual display of worked time to HH:mm:ss; consider original long-duration semantics during regression.
- New dashboard-only CSS supports compact desktop viewport, internal table scroll, and responsive fallback on small screens. Sidebar unchanged.
- **Verification status: code committed, NOT browser-verified, NOT tested on local Laravel instance.** User must pull, compile views and screenshot. A real screenshot may reveal further CSS adjustment required.
- **Milestones:** M02 layout development **in progress** (rough estimate 75%); M07 Dasticash appearance **in progress** (rough estimate 65%); tests remain pending. No claims of completed end-to-end production features.
- **Commands:** `git pull --ff-only origin upgrade/laravel-framework`; `php artisan optimize:clear`; `php artisan view:cache`; test `/home` with hard refresh. Avoid destructive Dasticash CRUD in live finance database.

## 13. Implementation batch 04 — compact KPI header and inline actions (2026-11-02)
- **Commit:** `5b174fcaa2f6922b45c096662624393f120572d6`.
- Based on user's verified working Batch 03 screenshot. Updated only `resources/views/admin/dashboard.blade.php`, no sidebar/backend modifications.
- KPI card: icon left/value right in top row; title left/View report right in bottom row; larger icon/value/title/action typography and reduced widget height.
- Finance ring size reduced; three financial amount values and cash IDs remain unchanged.
- Lead Search: separate colored Search text button removed; small search-icon-only trigger overlaid **inside the right side of the input**, preserving `#number` and `#search_number` for the original AJAX search.
- Attendance: employee selector, optional admin date/time input, and Punch In submit button in one horizontal row on desktop; the original POST form and selectors preserved. Restricted roles without datetime field still show selector + button.
- Responsive fallback for small widths; owner screenshot review pending.
- **Status:** code committed, browser/UI behavior unverified until local pull. Approx M02 layout 85%, M07 Dasticash UI 65% (unchanged this batch). Backend permission/finance regression not re-tested.
- **Pull:** `git pull --ff-only origin upgrade/laravel-framework`, `php artisan optimize:clear`, `php artisan view:cache`.

## 14. Implementation batch 05 — upper card height and financial typography (2026-11-02)
- **Code commit:** `0a768d9cbfcc804f57c052941ddb7a7d802e6ed2`.
- Updated dashboard CSS only: desktop grid allocates a shorter first row to Financial Summary and Lead Search & Attendance; the lower Dasticash/Attendance record row gets more height. Financial rings capped smaller.
- Increased Financial Summary ring title, amount and caption font sizes and weights for readability.
- No dashboard metrics, Dasticash AJAX, backend, permissions or sidebar modified.
- **Verification:** GitHub commit confirmed; local pull, view compile and browser screenshot pending. Smaller viewport and overflow remain browser QA gates.

## 15. Implementation batch 06 — responsive rings and desktop viewport (2026-11-02)
- **Code commit:** `1ab0d61419bd8ab446e75e5d9ab7c0a0fc519100`.
- **Source:** owner's current browser screenshot shows smaller-than-desired Financial Summary rings and an outer page scrollbar despite scrollable Dasticash and Punch Records cards.
- CSS-only dashboard change: enlarge Financial Summary rings on typical desktop screens (clamped responsive width, also restricted by viewport height).
- Correct the desktop viewport calculation: the earlier `content height: 100dvh - 127px` neglected the dashboard breadcrumb/content-header area and caused overflow. Now outer dashboard wrapper is flex-bounded below the navbar; content fills available space and two bottom panels are flex/grid bounded with independently scrollable records.
- On short desktop viewports the donut size is reduced to protect form and table space; tablet layout remains a two-column flexible grid; mobile stacks panels and allows natural document scroll for readability and accessibility.
- No changes to finance amounts, data sources, Dasticash CRUD, permission checks or sidebar.
- **QA gate:** GitHub commit created, local responsive visual verification still pending; measure on user's ~1640x900 screen and narrow widths. Check navbar/site footer/debug toolbar behavior separately if browser still shows a scrollbar.

## 16. Implementation batch 07 — AdminLTE document scrollbar (2026-11-02)
- **Code commit:** `33f7a1c414760ca1a03aaf6a66f9e5ce04966082`.
- **User screenshot:** vertical scrollbar remained on the right of the entire application, with the footer below dashboard content; scroll had shifted the top navbar out of view. Individual widget sizing was not the sole cause.
- **Root-cause hypothesis from inspected layout:** AdminLTE's `.content-wrapper` minimum-height management and separate `.main-footer` contribute more document height than the viewport. Prior fixes constrained only the inner `.content`, not the complete wrapper/navbar/footer column.
- CSS **only in dashboard view**, targeted via `body:has(.bm-dashboard-modern)` at desktop breakpoints. Set wrapper as 100dvh flex column; navbar/footer non-shrinking; content wrapper flex-fill with `min-height:0!important` to override AdminLTE computed min-height; internal dashboard grid/table panes remain scrollable.
- On tablet/mobile use normal document scroll instead of clipping content. `:has()` is supported by user's Chrome-based browser, but browser matrix QA remains pending.
- No changes to master layout Blade, sidebar, ledger, data queries or Dasticash AJAX.
- **Status:** code committed; local browser verification pending user pull. Do not claim scrollbar eliminated until confirmed in browser.

## 17. Master requirements split and source inventory (2026-11-02)
- **New authoritative task matrix:** `docs/DASHBOARD_SIDEBAR_MASTER_TASK_MATRIX_2026-11-02.md` (commit `e08fa58b5ed9b587402891c8053c07efc9705c05`).
- Client **Sections A + B = DASHBOARD**. Section **C = SIDEBAR**. Section D applies to both as appropriate. Dasticash is mandatory owner-added dashboard functionality.
- Source audit separates EXISTING infrastructure, PARTIAL dashboard features, NEW required work and NOT VERIFIED areas. NEW standalone modules are marked provisional until repository-wide audit; sidebar implementation remains ON HOLD.
- Use the master matrix for subsequent scope reporting; historical milestones/percentages in this log reflect past status only.
