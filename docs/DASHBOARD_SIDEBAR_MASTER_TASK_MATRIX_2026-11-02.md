# BlueMarketing — Master Task Inventory (Dashboard vs Sidebar)
Date: 2026-11-02 | Branch: `upgrade/laravel-framework` | Scope: audit & planning, no live-data changes

## Scope rule — FINAL
- **Dashboard:** Client sections **A. Dashboard Redesign** and **B. Dashboard Design & Functional Requirements** entirely belong to dashboard. This includes CRM, finance, approvals, attendance, employee tasks, cheques/online transactions, sales/plots, store, labour, date filters, notifications, click-throughs, permissions, performance and mobile responsiveness.
- **Sidebar:** Section **C. Main Menu Structure** and its 11 ordered menu groups and submenus belong ONLY to sidebar.
- **D. Important Instructions** are cross-cutting acceptance rules: preserve CRM reporting, combine Pending+Overdue follow-ups into one card, keep Today separate, exclude total income/expense, approval section separate, compute live values and preserve existing functions.
- **Owner addition:** Dedicated **Dasticash** dashboard widget with Add/Edit/Delete and balance reconciliation is mandatory, even though client did not list it.
- The user has asked to focus implementation on Dashboard first. Sidebar restructuring stays **ON HOLD**.

## Classification definitions
- **EXISTING:** confirmed route/controller/view or model implementation exists. This does **not** mean requested dashboard widget exists or is tested.
- **PARTIAL:** adjacent feature exists but the requested KPI / workflow / integrated report is not demonstrated.
- **NEW:** a requested standalone module, unified reporting surface, device connector, or workflow does not have verified existing implementation in inspected sources; confirm repository-wide before creating duplicate code.
- **NOT VERIFIED:** source audit insufficient to claim presence or absence. Do not equate with NEW.
- **DASHBOARD NEW:** existing source module exists, but the requested dashboard summary/widget must be built.

## A+B — DASHBOARD task matrix (all requirements)
| ID | Feature / requested data | Source audit | Build classification | Evidence / next action |
|---|---|---|---|---|
| D01 | CRM: New Leads count | EXISTING | PARTIAL dashboard | `DashboardController@index`, `LeadRepository`, dashboard card; add date filters |
| D02 | Follow-ups Completed | PARTIAL | DASHBOARD NEW | CRM work/log data exists; verify complete status logic |
| D03 | Today Follow-ups | EXISTING/PARTIAL | DASHBOARD NEW semantic correction | Existing Today Follow UP card calls `Setting::getTodayLeadWorkCount()`; verify whether *due today* |
| D04 | Pending + Overdue Follow-ups combined, as-of end date | PARTIAL | DASHBOARD NEW | Lead work/report exists; need due/pending logic, selected-period snapshot |
| D05 | CRM unified card drill-down: lead ID, customer, mobile, project, created, assigned, last/next follow-up, status, remarks | PARTIAL | NEW integrated report | Existing leads report & today work report routes, not demonstrated as one report |
| D06 | Employee CRM counts within same drill-down | PARTIAL | NEW integrated report | `printLeadsByUsersReport`; merge with unified detailed reporting |
| D07 | Today/Yesterday/This Week/Last Week/This Month/Last Month/Custom & employee/project filters | PARTIAL | DASHBOARD NEW | Some reports accept date ranges; not yet unified |
| D08 | Financial eight cards: Cash/Bank balances, Receivables/Payables, today's Cash/Bank Receipts/Payments | PARTIAL | DASHBOARD NEW | Ledger aggregation and finance voucher routes exist; separately verify each exact accounting definition |
| D09 | Finance ledger drill-down, date/account filters | EXISTING/PARTIAL | DASHBOARD NEW integration | `finance.reports.show_ledger`, ledger reports; account and period filter QA |
| D10 | Dasticash widget with ID/Name/Amount/Description/Add/Edit/Delete | EXISTING | PRESENT, needs responsive & CRUD testing | `DashboardController`, dashboard Blade, `DastiCashController` routes 266-270 |
| D11 | Dasticash/hand cash reconciliation | EXISTING/PARTIAL | AUDIT REQUIRED | Dasticash sum subtracted from `total_blance` in controller; verify on clone |
| D12 | Admin approvals queue & pending count | PARTIAL | NEW unified queue | Lead and finance approvals routes exist; central cross-module queue does not appear in inspected dashboard |
| D13 | 10 approval categories (voucher pending, lead shift, voucher edit/delete, sales edits, plot changes, attendance corrections, leave, task rework, admin other) | PARTIAL | NEW categories/workflows where missing | Finance, lead and some booking approvals evidenced; attendance corrections, leave and task approval remain NOT VERIFIED |
| D14 | Approval details: request number/type/module/employee/time/record, before/after, reason, status | PARTIAL | NEW unified detail interface | Audit workflow schemas/permissions |
| D15 | Approve/reject/request info/remarks and complete history, role gates | PARTIAL | NEW workflow integration | Existing lead/finance approve/reject routes; request-info and cross-module history NOT VERIFIED |
| D16 | Employee attendance: total/present/absent/late/leave/half-day/missing punch | PARTIAL | DASHBOARD NEW + NEW HR statuses if absent | Existing `Punch`, attendance form and today punch table; leave/schedule policies NOT VERIFIED |
| D17 | Employee attendance drill-down | PARTIAL | DASHBOARD NEW | Today punch records exist, not full status breakdown |
| D18 | Hikvision biometric automatic sync | NOT VERIFIED | **NEW integration** pending device-connectivity evidence | No connector verified from inspected dashboard/routes; do not modify live attendance prematurely |
| D19 | Employee task 9 cards + 7 employee performance metrics, drill-down | NOT VERIFIED | **NEW module (provisional)** | Dashboard `Work` model is CRM lead work, not proof of independent employee Task Management; audit entire repo first |
| D20 | Cheques 9 cards, received→cleared→adjusted workflow, history | PARTIAL | DASHBOARD NEW + possible workflow extensions | Existing `report.check`, `checkHistory`, pending-bank-payments and clearance routes |
| D21 | Online transaction 5 counts + adjustment tracking | NOT VERIFIED | **NEW module/workflow (provisional)** | Bank voucher routes exist, dedicated online transaction workflow NOT VERIFIED |
| D22 | Sales & plot status counts (total/available/hold/booked/sold/cancelled) | EXISTING/PARTIAL | DASHBOARD NEW | `PlotController`, `booking.plot.inventory`, `BookingController`; exact statuses audit |
| D23 | Sales & plot financial inventory values, project-wise | PARTIAL | DASHBOARD NEW | Booking/payment schedule exists; ensure accurate valuation/recovery logic |
| D24 | Store 6 metrics: items, qty, value, inward, outward, pending requests | PARTIAL | DASHBOARD NEW + **NEW material-request workflow if absent** | `StockController` items, entries, bills, report exist; material requests NOT VERIFIED |
| D25 | Labour: active workers, today attendance, pending bills, payable, project costs | PARTIAL | DASHBOARD NEW | `LabourController`, `LabourAttendance`, `LabourLedger`; pending bill status audit |
| D26 | Design: responsive cards/charts/icons, animation, viewport fit/internal scroll | PARTIAL | DASHBOARD ENHANCEMENT | Dashboard Blade V3 + layout commits; screenshot and responsive QA pending |
| D27 | Clickable details, no dead links, account/project/employee filters | PARTIAL | DASHBOARD ENHANCEMENT | Only existing four KPI routes wired; new cards require authorized drill-down |
| D28 | Optimized live actual values, role-based access, cache invalidation | PARTIAL | DASHBOARD NEW service/testing | Never static production amounts; verify role scope and financial soft deletes |
| D29 | Pending approval/overdue task/activity notifications | NOT VERIFIED | DASHBOARD NEW | source and notification mechanism audit |
| D30 | All UI & backend regression, mobile, tablet, permissions, accessibility and speed | PENDING | TESTING | do not claim done before tests |

**Existing assets are not proof that full client functionality is delivered.** NEW labels mark new delivery scope, provisional when repository inventory incomplete.

## C — SIDEBAR task matrix (ON HOLD)
Existing sidebar: `resources/views/admin/layouts/sidebar.blade.php`; dynamic `$modulemenus` + explicit permission-gated menus. Preserve existing links, named routes, `@can` checks and active states.
| Order | Client top-level menu | Current evidence | Target task |
|---|---|---|---|
| S01 | Dashboard | EXISTING | retain |
| S02 | Definitions & Settings | PARTIAL (settings/accounting/project setup) | NEW navigation grouping, reuse routes |
| S03 | Daily Entries | PARTIAL (voucher/entries) | NEW navigation grouping, reuse routes |
| S04 | Administration | PARTIAL (users/roles/approvals) | NEW navigation grouping |
| S05 | Reports | EXISTING (CRM, finance and cheque) | consolidate links |
| S06 | CRM | EXISTING (lead routes) | consolidate links |
| S07 | Sales & Plot Management | EXISTING (project plots, booking/sales) | consolidate links |
| S08 | Store & Inventory | EXISTING (stocks routes) | group links |
| S09 | Labour Management | EXISTING (labours routes) | group links |
| S10 | Employee Task Management | NOT VERIFIED | **NEW menu/module provisional**; depends on D19 |
| S11 | Employee Attendance | PARTIAL (manual punches) | **NEW menu surface** / Hikvision D18 dependency |

## Specific currently verified repository evidence
- `routes/web.php`: `/home`, `admin/dashboard`, lead report, work report, financial/lead approval endpoints, cheque report/history, Dasticash CRUD, stock, labour, plot inventory, bookings/sales.
- `app/Http/Controllers/DashboardController.php`: today's leads, all leads, ledger balances, Dasticash deduction and rows, `Punch` today, attendance form data.
- `resources/views/admin/dashboard.blade.php`: 4 cards + finance donuts + lead search + punch + Dasticash CRUD table + attendance records. It does NOT yet show all client sections.
- `resources/views/admin/layouts/sidebar.blade.php`: permission-gated explicit menu and `$modulemenus` dynamic entries.
- `app/Http/Controllers/StockController.php`: stock items, entry/bill/report flows.
- `app/Http/Controllers/LabourController.php`: labour attendance/payment/ledger flows.

## Execution order and progress accounting
| Batch | Scope | Status |
|---|---|---|
| 00 | Requirements split, task matrix, repository evidence | COMPLETE for initial route/controller audit; exhaustive cross-repo discovery PENDING |
| 01 | Existing dashboard visual layout / 4 KPIs / finance rings / Dasticash | PARTIAL (code exists; responsiveness and workflows need QA) |
| 02 | CRM summary, unified detail and date/employee/project filters | PENDING |
| 03 | Financial eight KPIs, ledger drill-down and Dasticash reconciliation | PENDING |
| 04 | Cross-module approvals audit, central queue, permissions, history | PENDING |
| 05 | Employee attendance summary + Hikvision connection | PENDING / NEW integration |
| 06 | Employee task module & summary | PENDING / NEW provisional |
| 07 | Cheque + online transactions | PENDING; online NEW provisional |
| 08 | Sales/plots + store + labour cards | PENDING |
| 09 | Responsive/roles/performance/data integrity test & approval | PENDING |
| 10 | Sidebar regrouping (11 menus) | ON HOLD by user |

No implementation claim is made for this documentation-only revision. Existing dashboard design commits remain separate. Avoid new database migrations or production mutations until metric definitions/source reconciliation.

## Batch 08 — CRM source audit + first live scoped date filter (2026-11-02)
**Commits:** `9b51281d`, `73a47abd`, `111782eb`.  
**Code changes:** `app/Http/Controllers/DashboardController.php`, `resources/views/admin/dashboard.blade.php`.
- Existing `LeadRepository::getLeadsToday(..., 'today')` counts active leads by **created_at**, project, and creator for non-superadmin. New Leads KPI now accepts an allowlisted `lead_period`: today, yesterday, this_week, last_week, this_month, last_month. Date bounds are shown on the dashboard; scope remains selected project and role-restricted creator. It is not yet Custom Date Range.
- Existing `Setting::getTodayLeadWorkCount()` is a separate work count, **not verified** as a scheduled-due today count. Existing `getAllLeads()` aggregates `follow_up <= today` with assigned-user scoping (not necessarily true pending Tasks). Neither metric was relabeled or altered.
- Source evidence: `app/Repository/Lead/LeadRepository.php`, `app/Models/Lead.php`, `app/Models/Work.php`, `resources/views/dashboard/leads_by_users_report.blade.php`, `resources/views/dashboard/today_lead_work_report.blade.php`.
- Unified CRM report with true completed/pending/overdue semantics and employee breakdown still **PENDING**. Need confirm lead status workflow rules, last/next follow-up mapping and business definitions before implementing.
- **QA:** GitHub commits successful. Local PHP syntax, Blade compilation, actual counts, role/date boundary tests are not yet verified. Preserve original no-mutation policy and test with read-only local account. Sidebar untouched.

## Batch 09 — dashboard AJAX KPI update + Custom Date Range (2026-11-02)
- **Code commits:** `e815f68b`, `78d3ed2c`.
- User requirement: on-dashboard actions must not cause full-page reload; use AJAX to patch only affected widgets, as with Dasticash.
- Updated `DashboardController@index`: when same-origin authenticated dashboard request uses AJAX + JSON Accept, returns `new_leads`, selected `period`, `from`, `to` instead of rendering HTML. Existing `/home` GET route and selected-project / non-superadmin creator restrictions retained.
- Added validated `custom` period via `lead_from`/`lead_to` (`Y-m-d`, in chronological order, max 366 days). Presets remain Today, Yesterday, This Week/Last Week/This Month/Last Month.
- Dashboard Blade now submits period changes with `fetch` and request cancellation, updates **only New Leads** KPI/count and period display; error message stays local. Custom date UI appears only for Custom and needs Apply; full page does not reload on selection/submission.
- **Not yet implemented:** client-specified common CRM date selection across *all* CRM KPIs, completed/due/overdue semantics, unified report or employee/project filters.
- **Verification:** Code committed; syntax, Blade compilation, visual behavior, date boundaries/permissions not tested by assistant on user's local DB. On local pull run `php -l app/Http/Controllers/DashboardController.php`, `php artisan view:cache`; then check browser Network tab: period request XHR/Fetch returns JSON, no Document navigation. Do not alter financial records.
- **Next gate:** review and test AJAX counts, then implement CRM combined detail reporting once status rules are verified; sidebar still ON HOLD.

## Batch 10 — dual-ring financial donut + first CRM AJAX drill-down (2026-11-02)
**Commits:** `d7a2f515`, `501f8479`, `8eb3959c`, `f9f9f8ad`, `a9712c7b`, `89b1cd5e`.
- **Financial Summary:** removed the three separate circles. One CSS donut has fully colored Total Cash outer ring, inner ring split by positive Hand Cash and Bank Account proportions. Live original values remain `$total_blance` and `-$total_bank_account_data`; Total Cash is their sum. Right-side amount/% legend; hover/focus tooltips; responsive layout. Negative values are shown numerically and ring percentages use non-negative components, with visible warning.
- **Preserved legacy Dasticash balance patch IDs:** `#handCash`, `#totalCash`. A MutationObserver keeps doughnut proportions/legend in sync when existing Dasticash AJAX updates hand cash; no extra page reload.
- **CRM next phase:** added authenticated `admin/dashboard/crm-snapshot` GET endpoint returning live, project/role-scoped New Leads, logged Work activities, date-due and date-overdue counts, plus up to 100 lead rows. New AJAX **CRM Details** modal loads the selected date period without full page navigation.
- **Interpretation boundary:** Logged activities ARE NOT claimed as confirmed completed follow-ups; due/overdue currently use recorded Lead `follow_up` dates, not a history-reconstructed pending snapshot. Full user/client CRM milestone remains PARTIAL pending sign-off of status rules and employee breakdown.
- **Data integrity:** Read-only queries; no migrations/accounting logic/sidebar mutations. Desktop/mobile UI validation still pending.
- **Local QA:** `php -l app/Http/Controllers/DashboardController.php`, `php artisan route:list --name=dashboard.crm_snapshot`, `php artisan view:cache`, test financial chart tooltips, Dasticash local non-production CRUD, CRM Details AJAX JSON, role restrictions, narrow screen wrapping.
