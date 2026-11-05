# Definitions & Settings — DS-00 Local Baseline

**Checked:** 2026-11-03  
**Scope:** Read-only inspection of local MySQL database `bluenew` and current account-status code. Counts include archived rows unless stated otherwise. **DS-00 read-only baseline is complete.**

## Database snapshot

| Table | Rows |
|---|---:|
| `account_types` | 6 |
| `head_accountings` | 44 |
| `subhead_accountings` | 448 |
| `project_head_subheads` | 561 |
| `sites` | 7 |
| `labours` | 165 |
| `ledgers` | 18,060 |

The counts are a local snapshot, not production counts or a data-reconciliation result.

## Relationship and migration findings

- The live `project_head_subheads` table has restrictive/no-action foreign keys to projects, heads, sub-heads, plots, and leads. Its older create migration has those FK declarations commented out; later database changes supplied the live constraints.
- The live `sites` table has restrictive/no-action foreign keys to projects, heads, and project-account mappings. It has both `subhead_accounting_id` and `project_head_subhead_id` pointers to project-account mappings; this differs from the checked-in original migration and needs site write/read-path review before schema changes.
- The live `labours.project_id` FK is restrictive/no-action, while the original migration requests cascade delete. The live database is safer than that original migration; do not replay or copy the old cascade behavior.
- `php artisan migrate:status` reports many legacy migrations as pending despite their tables already existing. A full `php artisan migrate` was attempted and stopped at the pre-existing `users` table. Attendance device tables were installed with the targeted migration only. Reconcile migration history before running a full migration set on this database.

## Mapping duplicates requiring business review

Read-only grouping found four active duplicate `(project_id, head_accounting_id, subhead_accounting_id)` groups. Their mapping rows have existing active ledger history:

| Project / Head / Sub-head | Mapping IDs and active ledger row counts |
|---|---|
| 1 / 1 / 2 | 27: 349; 35: 0 |
| 5 / 1 / 1 | 263: 428; 302: 1 |
| 6 / 34 / 339 | 438: 5; 439: 2 |
| 6 / 38 / 347 | 449: 13; 450: 40 |

These are not automatically safe to consolidate: both rows in three groups already own ledger history. No row was changed. The account-mapping write paths must be reviewed before adding a unique constraint or group-merge behavior.

There are 0 live sites without a `project_head_subhead_id`. The local snapshot contains 0 inactive heads and 0 inactive sub-heads, so live data cannot exercise inactive-account cases yet.

## DS-01 account-status baseline

- `head_accountings` and `subhead_accountings` use `is_active`; `account_types` uses `status`.
- `project_head_subheads` has no independent active flag.
- The accounting management index methods deliberately load inactive rows too, which is appropriate for administration/history.
- Head and sub-head create/update currently accept `is_active` directly. No reason/actor status-history record was found in those methods.
- A few selectors explicitly filter active account types, but active-account enforcement remains spread across module queries.
- The accounting AJAX selectors (`AccountingController::get_account`, `get_subaccounts_by_project`) still need a posting-vs-history usage audit before changing their behavior.
- `ProjectHeadSubhead::availableForPosting()` is used by Journal/Commission Voucher selectors and validations, legacy CR/CP create/edit/draft lookups, cash-voucher correction, and V2 journal/cash/commission/approved-source writers. Posting-form AJAX selectors can request active-only results; report/history lookups keep inactive mappings visible. Head/sub-head status writes validate boolean values; head creation validates that the account type exists.
- Legacy Booking system/customer mappings, Labour system accounts and JV legs, Stock accounts, Extra Charge accounts/settlements, the mobile Stock account resolver, bank-clearance account selection, cash-voucher correction, and isolated V2 Journal/Attendance workflows reject inactive definitions for new/corrected postings. The shared stock ledger guard rechecks active status at write time, including mobile-originated postings. Existing reversal legs retain their original mapping so correction preserves history.
- Head/Sub-Head status changes now require a reason when the status actually changes and append actor, timestamp, old status, and new status to `account_definition_status_audits` in the same transaction. The migration is authored but not applied to the local database.
- DS-01 code coverage is near complete, but remains pending until the additive migration is safely applied/rehearsed and regression coverage verifies forged inactive IDs, status audit atomicity, and history visibility. No inactive records exist in the local snapshot, so runtime inactive behavior is not empirically exercised here.

## DS-02 COA hierarchy foundation — implementation in progress

- Added additive, project-scoped `account_groups` and `project_head_account_groups` tables, with project-scoped unique codes, restrictive FKs, and one group assignment per Head per project. Existing Heads remain unassigned; no classification or ledger data is guessed or rewritten.
- Added the `AccountGroup` model and project mapping relations, permission-gated group create/edit, hierarchical parent selection, active unassigned-Head assignment, scoped duplicate-code validation, cycle prevention, and Trial Balance group-path headings/subtotals.
- Browser/print report headings and group subtotals use the same shared Trial Balance table partial; group totals are computed from the report's already-filtered account balances and do not modify ledger rows.
- Added append-only application audit events for group create/edit and Head assignment, written in the same transaction as each change. The Account Groups page now shows a setup notice if its additive schema migrations are absent. Migration rehearsal and regression coverage remain outstanding; do not apply these migrations until rehearsed against the deployment schema.
- Added a project-scoped group-merge preview showing affected Heads, project mappings, active ledger row counts, amount-in/out totals, and duplicate mapping groups. A confirmed merge moves only the selected project's Head-group assignments, compares ledger row count and amount-in/out cents before/after, audits each move, and retires the source group. Ledger rows are never updated or deleted.

## Safe next steps

1. Complete the write-path inventory and reconcile the remaining CR/CP, booking, labour, inventory, and mobile posting edge cases against the centralized status policy.
2. Review inactive-account exceptions for historical edits/reversals and selector endpoints before broadening enforcement.
3. Preserve inactive records in reports and history.
4. Rehearse the Account Group/status audit migrations on a disposable database matching the live schema. Do not add FKs or rewrite live rows based on the old migration files alone.

## DS-04 implementation update — 2026-11-03

- Labour and site reads/writes now enforce the selected project boundary: labour lists, reports, history, rate updates, attendance creation, and site editing/printing cannot act on another project's IDs. Attendance upsert now keys by project, labour, date, and site, so recording work at one site cannot overwrite that worker's record at another site on the same date.
- Site account selection now requires an active account mapping belonging to the selected project. New sites receive a stable project-specific code (`SITE-{project}-{site id}`); existing rows are left unassigned until edited, without guessing their project or changing historical links.
- Site active/inactive state now requires a reasoned audit entry; inactive sites are hidden from new attendance selectors and rejected by attendance/voucher writes. Historical rows/reports remain accessible.
- Supplier Stock Parties now receive a stable `SUP-{id}` code and expose it in the supplier/party listing. Existing party/account strings and transactions are retained unchanged. Stock Party status changes now require a reason, write an audit entry, and inactive parties are rejected by web and mobile stock-entry posting.
- Added additive migrations for site codes/status and supplier codes. They are authored but not applied. Site/Vendor COA foreign-key conversion is still deferred because the existing Stock Party master is shared across projects while account mappings are project-specific; assigning one project mapping to a shared vendor would be ambiguous and unsafe.

## DS-03 implementation update — 2026-11-03

- Project Setup now captures location/details, opening/closing dates, corner/facing charge percentages, and a stable generated project code. Charge changes require an effective date and append to `project_charge_revisions`; duplicate project/date revisions are rejected. Existing projects keep their current data and receive a code only when edited.
- Fixed Project create's optional-logo crash and made project creation atomic with its default mapping/account setup. Replaced a duplicate-key PHP array that silently skipped two required Project Sale sub-heads with an explicit list.
- Project schema migration is authored but not applied. Existing sales/plot charge calculation consumers have not yet been converted to resolve effective-dated rates; that remains in DS-03.
- Plot Definition write-path review found a bulk update that rewrote `bookings.plot_size` whenever a plot was renamed/resized. Removed that historical rewrite; existing booking snapshots now remain unchanged. Plot detail reads/updates are scoped to the selected project, plot edits are locked transactionally, and same-project duplicate plot numbers are rejected.
- Plot creation now rejects a forged cross-project `project_id`, requires a valid static plot type, positive size, active road/facing definitions, and boolean status flags; the saved project always comes from the selected project context.
- New booking creation is now create-only (submitted booking IDs are rejected), validates project/customer/available plot within the selected project, locks and rechecks plot availability/hold state, and copies plot size from the master. This prevents a crafted request or concurrent booking from overwriting a booking or allocating the same plot twice. Effective-dated charge calculation remains separate and is not guessed into existing prices.
- PHP syntax checks passed for the changed controllers, models, and migrations. Database migration rehearsal and requested regression tests remain pending; no migration was run and no records were changed in the database.

### Current delivery estimate

`DS-00 baseline` ██████████ 100%  
`DS-01 active-account controls` ████████░░ 80% (migration/rehearsal and regression evidence remain)  
`DS-02 COA groups and merge` ███████░░░ 70% (rehearsal and regression evidence remain)  
`DS-03 project/plot/employee definitions` ███████░░░ 70% (project charge calculation, migration rehearsal, and acceptance evidence remain)  
`DS-04 vendor/site definitions` ██████░░░░ 60% (vendor-to-COA FK and fuller vendor history remain)  
`DS-05 payments/contractors/payroll/import-export` ░░░░░░░░░░ 0%  

Overall estimate: **about 55%**. Payroll calculation/finalization, project-charge calculation, vendor/contractor account integration, payment configuration, inventory import/export, migration rehearsal, and acceptance evidence remain.

## DS-03 Plot Definition milestone — 2026-11-03

- Added project-scoped Category, Type, Size, and Unit definitions. Type definitions map explicitly to the current Residential/Commercial values; no legacy plot values are guessed or bulk-mapped.
- Added dated size-rate revisions and actor/reason audit rows for rename, status, and rate changes. Rate lookups are project-scoped and select the latest revision effective on the requested booking date.
- Plot create/edit now links selected definitions while retaining the existing legacy type/size/unit fields. Database relationships use project-composite foreign keys so a plot or size cannot point at another project's definition. Existing plot and booking values are not mass-updated.
- New booking plot selection receives an effective rate suggestion from the selected plot's size definition. Existing transfer prices and already-saved booking prices are not auto-rewritten.
- Migration is authored but not run. PHP syntax and whitespace checks passed; application-level browser verification and migration rehearsal remain pending.

## Employee Definition and Employment Policy milestone — 2026-11-03

- Added project-scoped Department, Designation, and Employee Category definitions with unique project codes, active/inactive state, and actor/reason audit history.
- Added an explicit employee profile assignment page linked from User Management. It stores employee code, joining date, and project-scoped definition FKs. Existing user accounts are not auto-assigned or rewritten.
- Added append-only effective-dated employment revisions for salary, allowances/additions, payment method/status, shift timing, break, daily hours, weekly schedule/off days, grace, late-fine method/ranges/caps, absence/leave/half-day/short-leave/early-departure deductions, overtime, and other adjustments. Revisions store actor and reason; same employee/effective-date duplicates are rejected.
- All selection and write queries are scoped to the currently selected project. Composite FKs protect profile-definition and revision-profile project identity; user-to-project matching is rechecked server-side in a locked transaction.
- The new employee migration is authored but not run. Route registration, PHP syntax, and whitespace checks pass. No salary calculation, payroll finalization, or historic payroll change has been introduced; those remain separate milestones.
- Follow-up verification fixed missing mass-assignable fields for leave deduction and early-departure method, exposed both in the form, and added duplicate weekly-off, schedule/off-day, equal-shift-time, and pre-joining effective-date validation. Revision history is expandable from the saved profile list.
- Saved employee setup can now be reopened into the form with the current identity and effective policy prefilled; the proposed next revision date defaults to the day after the latest revision, avoiding an accidental duplicate-date submission.
- Verification: PHP lint passed for the employee controller/model/migration and related plot/project files; Blade cache compilation passed; `git diff --check` passed (line-ending warnings only). No tests were run and no migration was applied.

## Payroll source checkpoint — 2026-11-03

- Employee attendance source `punches` stores punch-in/out and project/user IDs; it has no leave, half-day, holiday, or correction status in the inspected model/write paths. The current manual punch flow can update punch-out on the existing daily row.
- `V2OriginalPayrollWorkflow` is for weekly **labour-worker** payments through guarded isolated pilot posting; it is not employee monthly salary payroll and must not be reused as such.
- Therefore DS-07 needs an explicit employee attendance snapshot/status layer and a payroll preview that records its source events before deductions can be finalized. Employee salary division/proration rules and the chart-of-accounts/payment mapping also require an auditable implementation path. Do not create employee payroll ledger entries from the labour workflow or infer missing leave/holiday data.
- Added a project-scoped monthly attendance snapshot page and additive snapshot/audit tables. The page copies existing punch IDs/times into one unique employee/day row, treats no-punch scheduled days as `unreviewed`, pre-marks configured weekly offs, and lets authorized staff review statuses with a required reason. Re-preparing a month only fills missing days; it does not overwrite prior snapshots or punch data. Batch IDs identify exactly which new rows receive creation audits.
- Attendance snapshot migrations are authored but unapplied. PHP lint, Blade compilation, route registration, and whitespace checks pass. No integration with salary deductions/finalization is claimed until snapshot review and calculation rules are verified.

### Current milestone progress

`DS-00 baseline` ██████████ 100%  
`DS-01 active-account controls` ████████░░ 80% (migration rehearsal and regression evidence remain)  
`DS-02 COA groups and merge` ███████░░░ 70% (migration rehearsal and regression evidence remain)  
`DS-03 project/plot/employee definitions` ████████░░ 80% (effective-rate consumers, DB rehearsal and acceptance remain)  
`DS-04 vendor/site definitions` ██████░░░░ 60% (durable vendor/site account integration remains)  
`DS-05 payroll/payment/contractor/import-export` ░░░░░░░░░░ 0%  

`DS-07 payroll` ██░░░░░░░░ 20% (attendance snapshot/review foundation; payroll preview, approval, finalization and payment remain)  

Overall estimate: **about 55%**. The next implementation milestone is employee payroll preview and immutable monthly attendance/source snapshots, then approval and safe accounting/payment linkage. Salary proration/late-fine semantics must be defined in code and reconciled before any posting.

## DS-03 effective pricing integration checkpoint — 2026-11-03

- New booking creation now resolves the selected plot's unit rate from the latest project-scoped rate revision effective on the booking date and recalculates the total on the server. Submitted totals and configured charge amounts are not trusted; project corner/facing percentages are also applied from their effective-dated revision.
- The booking form fetches date-specific rates/charge percentages and displays the computed charges. Legacy manual rate/charge entry remains available only when no definition/revision exists for that item.
- Existing bookings and file transfers are unchanged. New-booking plot locking, active/project checks, and hold checks remain in place.
- Verification: BookingController PHP lint, Blade cache compilation, and targeted `git diff --check` passed. No tests were run and migrations were not applied. DB migration rehearsal and live browser acceptance are still required.

`DS-03 project/plot pricing integration` █████████░ 90% (migration rehearsal and acceptance remain)
Overall estimate: **about 56%**. Next implementation area: DS-04 vendor/site/contractor account linkage; payroll calculation and financial settings still require separate milestones.

## DS-04 project-scoped stock-party account link checkpoint — 2026-11-03

- Added a per-project stock-party account link with a composite foreign key to `(project_head_subhead_id, project_id)`. The migration also ensures that referenced project-account pair has a unique key. Account-link changes are append-audited with actor, previous account, new account, project, and party.
- Stock party create/edit screens now use only active COA mappings belonging to the selected project. The server revalidates both the project and account status. Stock entry/bill ledger posting resolves a configured project-specific mapping first; existing global head/subhead values remain a compatibility fallback for parties not yet assigned in a project.
- No old party/account mapping or ledger row is backfilled or rewritten. Existing project mappings remain separate. Vendor/site/contractor lifecycle integration and stock-record project ownership still need review before DS-04 can be marked complete.
- Verification: PHP lint passed for the touched controller/model/migration, Blade compilation passed, targeted whitespace check passed, and stock party routes are registered. No tests were run and no migrations were applied.

`DS-04 stock-party project account linkage` ███████░░░ 70% (migration rehearsal, site/contractor integration, project-scoped stock history, and acceptance remain)
Overall estimate: **about 57%**. Next implementation area: site and contractor definitions/linkage, followed by stock history scoping and payment/payroll milestones.

## DS-04 site-account constraint checkpoint — 2026-11-03

- Added a nullable project-account reference for sites with a composite `(project_head_subhead_id, project_id)` foreign key. New site saves already resolve the selected active account inside the selected project and now persist that project-account row. Existing site account columns remain intact for compatibility; historical sites are not auto-mapped.
- DS-04 is now estimated at 75%. Contractor master/ledger integration, project ownership for stock history, migration rehearsal, and acceptance remain open.

## DS-04 contractor ledger integration checkpoint — 2026-11-03

- Added an additive `contractors` master scoped by project, with unique project/code, required same-project active COA account, active/inactive state, and audit history. No hard-delete path is exposed.
- Added contractor listing/create/update/status routes and a project-specific Labour Contractors page. Contractor account reassignment is validated against the selected project's active COA and each change is audited.
- Added optional contractor selection to site labour voucher creation. The selected contractor must be active in the selected project; its linked account replaces the legacy Labour Party account on the payable leg, and contractor identity is stored on the JV header. Existing postings retain null contractor identity and are not rewritten.
- Added a read-only contractor ledger view using the linked account's active posted entries, with transaction history and running balance. V2 attendance posting now validates and persists contractor selection only when the matching mapped debit leg is supplied.
- Migration `2026_11_03_000013_create_project_contractors.php` is authored but not applied. Migration rehearsal, labor payment/advance integration, transaction-level reconciliation, and acceptance evidence remain.

## DS-05 contractor payroll linkage checkpoint — 2026-11-03

- Added optional project contractor selection to the weekly Labour Payment flow. Legacy posting uses the contractor's active same-project COA account and matching subhead; the contractor is re-locked and revalidated inside the payment transaction. Without a selection, existing Labour Party behavior remains.
- New weekly payroll rows persist `contractor_id` and project ID. V2 payroll replay includes contractor identity; new V2 payroll requests must post the selected contractor's mapped account for the full payroll amount. Invalid/inactive/cross-project contractor selections fail before posting.
- The contractor ledger now reads only journal/customer-ledger sources explicitly tagged to that contractor. Reassigning its COA later does not hide or reattribute earlier contractor transactions. Legacy rows remain untagged instead of being guessed or rewritten.
- Added migration `2026_11_03_000014_scope_labour_ledgers_and_link_contractors.php`. It backfills existing labour-ledger project IDs from the owning labour record, rejects orphan/mismatched project rows for manual reconciliation, replaces the cascading labour FK with project-aware restrictive FKs, and adds nullable contractor links. It also adds contractor tags to customer-ledger history. The migration is authored only and has not been applied.
- Remaining DS-05 contractor work: a properly specified contractor advance workflow (the existing worker advance field is not treated as contractor advance), plus reconciliation/acceptance. DS-04 still needs stock-history project scoping and migration rehearsal.

## Contractor ledger isolation checkpoint — 2026-11-03

- Contractor ledger now constrains the final legacy-ledger query to the selected project as well as the tagged contractor voucher IDs; this prevents an orphaned/mis-linked legacy ledger row from leaking into another project's contractor history.
- Contractor status toggles now lock and reload the contractor under the selected project inside the transaction before flipping status and writing the audit event, avoiding stale-read status flips under concurrent requests.
- Contractor create/update now lock and recheck the selected project's active COA mapping inside the same transaction that saves the contractor, closing the validation-to-write race if the account is deactivated concurrently.
- Verification: `php -l app/Http/Controllers/ContractorController.php` passed; `git diff --check` passed. Tests and migrations were not run. Contractor advance accounting remains intentionally unimplemented until its source, payment direction, and accounting accounts can be grounded in the existing approved workflow; the worker's personal advance field is not treated as a contractor advance.

Current estimates: `DS-03 project/plot pricing integration` **90%**; `DS-04 vendor/site/contractor definitions` **80%**; `DS-05 payroll/payment/import-export` **15%**. Overall estimate **about 60%**. Next implementation milestone: stock-history project scoping and contractor advance requirements, then payroll/import-export foundations and acceptance.

### Stock-history scope audit — 2026-11-03

- The `stock_entries`, `stock_bills`, `stock_movements`, `stock_item_balances`, and stock master tables do not currently carry authoritative project ownership. The current stock report aggregates `stock_item_balances` and `stock_movements` globally; adding a project filter from the selected party alone would still mix projects and give incorrect balances.
- Existing legacy stock rows cannot be safely attributed to a project from their current schema. Do not auto-backfill them to an arbitrary selected project. The stock module needs an explicit legacy reconciliation path and project-scoped transaction/balance schema before the project-wise acceptance requirement can be claimed.
- No stock schema or behavior was changed in this audit checkpoint. Evidence came from the stock migrations, models and `StockController::report()`.

## DS-04 project-scoped stock history milestone â€” 2026-11-03

- Added migration `2026_11_03_000015_scope_stock_transactions_by_project.php`: nullable project tags on stock entries, bills, movements, and their detail lines; project-specific item balances; project/party and parent/line composite foreign keys with restrictive delete behavior.
- Legacy transactions are attributed only where the existing party-account mapping names exactly one project, and linked bill/entry ownership agrees. Ambiguous or unmapped rows remain intact with `project_id = NULL`. The old global balance table is preserved and never copied into an arbitrary project's balance.
- Web and mobile stock lists, detail lookup, report balances/movements, party selectors, stock writes, and dashboard inventory KPIs now use the selected/authorized project's balance and transaction scope. New entries require a stock-party COA mapping for that project. Mobile stock posting uses the same isolated project balance table.
- V2 stock source snapshots and approval/posting checks now include and validate project ownership. Stock entry/bill line and linked-entry deletes no longer cascade through the replacement project-aware restrictive foreign keys.
- Stock page shows counts of unassigned legacy entries, bills, movements, and preserved global balance rows so history is visible for manual reconciliation. No legacy row is auto-assigned where project identity is ambiguous.
- Verification this turn: PHP syntax checks passed for all touched controllers, models, dashboard service, V2 stock services, and the migration. No tests were run and no migration was applied. The migration still needs rehearsal against a disposable copy of the deployment schema, followed by API/browser acceptance.

Current estimates: `DS-03 project/plot pricing integration` **90%**; `DS-04 vendor/site/contractor/stock definitions` **88%**; `DS-05 payroll/payment/import-export` **25%**. Overall estimate: **about 63%**. Next milestone: stock legacy reconciliation controls and contractor advances, then finish payroll/payment and import/export acceptance.

## DS-09 inventory CSV staging checkpoint â€” 2026-11-03

- Added all-item and selected-item CSV export. Selection uses stable stock item IDs, never item names.
- Added staged CSV upload with required headers, file/row limits, duplicate-ID and duplicate-new-definition checks, active-item ID validation, row-level errors, and downloadable error CSV.
- Added preview counts and explicit apply confirmation. Apply is transactional and idempotent, requires stock permissions, and only changes item master name/unit/default rate; it does not write balances, stock movements, purchase rates, or ledger rows.
- Added import batch/row audit tables and a project-filtered recent upload history view with actor, status, counts, and preview replay. Export streams rows to avoid loading the full item master into memory. The stock Items screen supports export all/selected, staged preview, errors download, and apply. CSV is Excel-compatible; native XLSX is not available in the installed package set.
- Verification: PHP lint passed for StockController, both new stock migrations, and routes; `git diff --check` passed (only existing LF/CRLF notices). No tests were run and no migrations were applied. Browser/API acceptance and migration rehearsal remain.

Current estimate: `DS-03 project/plot pricing integration` **90%**; `DS-04 vendor/site/contractor/stock definitions` **88%**; `DS-09 inventory import/export` **78%**; `DS-05 payroll/payment` **25%**. Overall estimate: **about 67%**. Next: contractor advance/payment linkage and legacy stock reconciliation controls, then effective-dated payroll/payment definitions and remaining acceptance.

## DS-04 contractor account history guard â€” 2026-11-03

- Contractor account changes now lock the selected-project contractor row and are rejected once that contractor has linked journal-voucher or customer-ledger transactions. This keeps the durable account link from silently diverging from tagged voucher history; the existing ledger records are not changed.
- Legacy transactions without a contractor ID remain unassigned because account ownership cannot be inferred safely. Contractor advance/payment accounting still needs its exact workflow and posting accounts validated before it can be wired into the general ledger.
- Verification: controller PHP syntax check passed. No tests or migrations were run.

## DS-06 employee-definition write integrity checkpoint â€” 2026-11-03

- Employee profile saves now recheck and row-lock the selected project's active Department, Designation, and optional Category inside the write transaction. This closes the gap between request validation and saving if another admin archives a definition concurrently.
- Existing employee profile and employment-revision audit records remain transactional; project-scoped validation and the database uniqueness constraints remain in place.
- Verification: PHP syntax checks passed for `EmployeeDefinitionController` and `ContractorController`; targeted `git diff --check` passed. No tests or migrations were run. Payroll calculations/finalization and actual migration rehearsal remain incomplete.

## Settings navigation and payment-method audit checkpoint â€” 2026-11-03

- Fixed the Settings tab state so selecting `Attendance Machine` activates only that tab and its panel; previously the first general settings category stayed active at the same time. The selected settings category is also restored from a valid `tab` query value.
- Payment methods are persisted as numeric `payment_type` values (`1` Cash, `2` Online, `3` Check/Cheque), while labels and instrument behavior are duplicated in helpers, Ledger/Booking controllers, voucher validators, and clearance/report workflows. These values cannot safely be made custom by changing labels alone: custom methods need a stable method identity plus explicit instrument/bank behavior, and each read/write path must be migrated together. No payment semantics or historical values were changed in this checkpoint.
- Verification: `php -l app/Http/Controllers/SettingController.php` passed and `php artisan view:cache` compiled Blade views successfully. No tests or migrations were run.

## DS-06 employee audit visibility checkpoint â€” 2026-11-03

- Employee Setup now displays definition and employee-profile audit history, including actor, time, before/after values, action, and reason. Both audit queries join on project ownership and are capped at the latest 200 entries per history list.
- Employment-term revisions were already visible in the profile list; the new view exposes the separate profile/definition audit events that previously existed only in database tables.
- Verification: EmployeeDefinitionController PHP syntax passed; Blade view compilation passed; targeted `git diff --check` passed. No tests or migrations were run.

## DS-01 broker account enforcement and DS-09 CSV safety checkpoint â€” 2026-11-03

- Booking creation and file-transfer broker selectors now return active broker accounts for the selected project only. The booking edit selector also retains its existing broker mapping for historical display, including an inactive one, while the amendment write path rejects assigning any changed broker unless its mapping is active and belongs to the same project.
- File transfer now loads the booking through the selected project scope, closing a direct cross-project record lookup in that screen.
- Stock item export and import-error CSV downloads now prefix formula-leading untrusted text (`=`, `+`, `-`, `@`, including leading control/space characters) so spreadsheet applications treat it as text. IDs and rate values remain numeric cells.
- Verification: PHP syntax checks passed for the touched controllers/service and `git diff --check` passed. No tests, migrations, database writes, or browser checks were run.
- Remaining: validate the broker selector/edit flow in-browser and extend the same active/project audit to other remaining account selectors. DS-09 still needs broader import acceptance and migration rehearsal.

Updated local estimate: `DS-01 account-status enforcement` **84%**; `DS-09 inventory import/export` **80%**; overall Definitions & Settings estimate **about 68%**. Next milestone: continue payroll/payment definitions and integration, then close remaining DS-01/DS-09 acceptance and migration rehearsal.

## DS-04 legacy stock reconciliation workflow checkpoint â€” 2026-11-03

- Added an administrator-only, selected-project reconciliation panel for unassigned stock entries, bills, and standalone movements. Candidate lists require a party-to-account mapping in the selected project; standalone movement assignment additionally requires that the party map to exactly one project and that no source entry/bill still exists.
- Reconciliation locks the project and selected source rows, rejects linked records/lines/movements already assigned to a different project, and assigns linked bill/entry lines and movements together. Existing archived state is retained. Movement quantities, rates, amounts, item IDs, and source identities are not edited.
- A required reason is stored in a new append-only audit table with actor, timestamp, record type/ID, and project. The page shows recent audit events. Only affected project/item balance rows are recalculated from that project's movement history.
- Added migration `2026_11_03_000017_create_stock_legacy_reconciliation_audits.php`; it requires the preceding project-stock migration. Neither migration has been applied. No legacy rows were assigned during implementation.
- Verification: PHP syntax, Blade compilation, route registration, and targeted `git diff --check` passed. No tests or database changes were run. Live candidate SQL, migration rehearsal, and admin browser acceptance remain required.

Updated local estimate: `DS-04 stock project scoping/reconciliation` **92%**; overall Definitions & Settings estimate **about 69%**. Next milestone: payroll calculation preview from reviewed attendance/effective employment revisions, while payment-method identity and numbering remain a separate integration milestone.

## DS-07 employee payroll preview checkpoint — 2026-11-03

- Added a project-scoped, month/employee-filtered read-only employee payroll preview at `admin/employee-payroll/preview`, linked from attendance review. It uses effective-dated salary revisions and attendance snapshots; explicitly reviewed rows qualify, and a weekly off matching the effective schedule is accepted from that schedule.
- Added effective-dated salary-proration, half-day, and short-leave rules to employee setup. Proration supports calendar-day or scheduled-workday basis; the safe default is manual review. Half-day deduction is an explicit per-day amount; short leave requires an explicit no-deduction or fixed-per-event rule.
- Preview prorates basic salary, allowances/additions and other fixed adjustments per effective revision. It includes configured attendance deductions, fixed/range/percentage late fines (percentage base is daily basic salary), early-departure fixed/per-minute deductions, and overtime fixed/day, hourly, or multiplier additions. Missing punch data and missing multiplier inputs block readiness. CSV export is spreadsheet-formula-safe; browser print supports Save PDF. It never creates a payroll record, voucher, or payment.
- Salary/attendance revisions remain append-only; missing revisions, unreviewed attendance, missing snapshots and manual-review proration block readiness.
- Added forward-only migration `2026_11_03_000018_add_explicit_payroll_preview_policies.php`. It has not been applied. PHP syntax, Blade cache compilation, named route registration, and `git diff --check` passed. No feature tests or database writes were run.

Updated local estimates: `DS-04 stock project scoping/reconciliation` **92%**; `DS-07 payroll` **75%**; overall Definitions & Settings estimate **about 78%**. Next: DS-08 configurable payment methods, bank accounts, voucher prefixes and concurrency-safe sequence design; payroll accounting/payment linkage remains separate.

## DS-07 immutable payroll run checkpoint — 2026-11-03

- Added additive, project-scoped payroll run, run-item and append-only audit tables. Composite FKs bind every run item to its project and employee profile; all references restrict deletion.
- A consolidated, fully-ready monthly preview can be captured as an immutable pending-approval snapshot. Each employee row stores the calculation, effective employment revision IDs and source attendance record IDs. Per-item and whole-run SHA-256 hashes support integrity checking.
- Snapshot creation is limited to completed months; an in-progress current month remains a preview and cannot be finalized with future workdays unseen.
- Payroll snapshot creation serializes on the project row and allocates a per-project/per-month run number. Approval locks the same project and run, verifies item count, source hashes, aggregate totals and full snapshot hash, and blocks a second approved run for the same project/month.
- Added separate `read payroll`, `create payroll` and `approve payroll` permissions. Migration installs them and grants the three capabilities to the existing `superadmin` role only; others require explicit role assignment.
- Approval locks the snapshot but deliberately does not create a voucher, ledger entry or payment. Salary accounting linkage, slips, adjustment runs, migration rehearsal and acceptance remain open.
- Static PHP syntax checks and `git diff --check` passed. No tests, migrations, database writes or browser runtime checks were run.

## DS-07 saved payroll slips checkpoint — 2026-11-03

- Added a project-scoped saved-run view that reads only the immutable item snapshots and validates each item's source hash before rendering.
- Added employee, department and designation filters plus print/PDF salary slips. Later employee profile, salary or attendance changes do not alter a saved run.
- Pending and approved snapshots remain visibly distinguished; viewing/printing has no posting side effects.
- Verification: PHP syntax, route registration and Blade compilation passed. No tests or migrations were run.

## DS-08 legacy voucher-number reuse guard — 2026-11-03

- Legacy `getVocuherNumber()` selected the smallest missing number for the selected project/type. A gap could therefore recycle an old voucher number after a row was removed or archived.
- Changed the fallback to allocate `max(voucher_number) + 1` across all retained rows for that project and type. Existing voucher numbers and prefixes are unchanged.
- Added a retained per-project/per-voucher-type counter table. During transactional posting, the helper locks that counter row, reconciles counter state against the legacy ledger maximum, increments once, and returns the reserved number; first-time initialization serializes on the project row, and transaction rollback releases the reservation. Outside a transaction the helper remains a non-reserving preview.
- The reservation keeps legacy prefix/type and global-per-type counting behavior. Configurable financial-year segmentation and user-editable prefixes remain pending; they cannot be safely enabled until display and validation paths are migrated together.
- Verification: PHP syntax and static diff checks only; no tests or migrations were run.

## DS-08 project bank account settings checkpoint — 2026-11-03

- Added Settings → Bank Accounts for project-scoped bank name, account title, branch, encrypted account number, active/inactive status, and selected project COA mapping.
- Composite project/account foreign keys prevent cross-project COA links. New active bank accounts require active COA mappings; inactive historical mappings may remain only when the bank account is deactivated. Accounts are edited/deactivated, never hard-deleted.
- Every save appends a same-transaction audit record with actor and before/after configuration; audit values include only the account number's last four digits. The full account number is encrypted using the application's configured encryption key.
- Settings shows the latest 100 scoped account changes with actor, action, masked before/after details and timestamp.
- Added project-scoped labels for the three existing payment codes. Labels flow through the shared payment-label helpers used by transaction/report screens; codes and cash/transfer/cheque semantics are fixed, and each changed label is audited.
- This configuration screen is not yet connected to legacy voucher payment selectors. Existing numeric `bank_id` fields and static bank catalog remain unchanged until all readers/writers can persist and validate the project bank account identity.
- Verification: PHP syntax, route registration, Blade compilation and static diff checks. No tests or migrations were run.

## DS-08/DS-09 continuation checkpoint — 2026-11-03

- DS-09 already had CSV export and staged import endpoints/UI, stable `item_id` matching, row validation, preview, error download, apply-once batch status, and project-isolated import history. Import changes only stock-item master fields and does not write balances or movement rows.
- Hardened staged updates against stale previews: staging records a hash of the original item ID/name/unit/default rate; apply locks and compares that snapshot, refusing the batch if the item changed after review. Older staged update batches without a snapshot must be restaged. New-item rows are unaffected.
- `ProjectVoucherAllocator` now reserves numbers through the same per-project/type retained counter as the legacy helper. Its JV-header, SV-header, and booking-receipt maxima are included before reserving, so those source records continue to reserve identities even if the corresponding ledger row is missing. Booking Sales Voucher creation now uses this shared allocator instead of its own `MAX + 1` method. This covers migrated callers only after the counter migration is applied; remaining independent allocators are still unconverted.
- Continued allocator audit: `get_new_typeID()` now reads/allocates within the selected project using a separate retained `:ID` counter namespace; legacy cash, booking, stock and mobile flows therefore do not share source-ID counters with printed voucher numbers. Ledger `type_id` row markers in stock, mobile stock, labour, booking PPR, and bank-clearance flows now use a selected-project `:ROW` counter namespace rather than global latest-row lookups. `ReportController` clearance no longer uses `global last ID + 2`.
- `getLastLedgerIdByType()` now also scopes to the selected project's account mappings, protecting its remaining legacy helper consumers from another project's row IDs.
- The legacy printed-number helper now takes the project-row lock before computing its fallback maximum, including when the counter migration is not yet installed. Allocator lock ordering is project row then sequence row.
- Verification: PHP lint for StockController passed; import routes are registered; Blade cache compilation and `git diff --check` passed (only existing CRLF warnings). No tests were run and no migrations/database writes were performed.
- DS-08 remains partial: configured bank records are not connected to legacy transaction `bank_id` selectors/writers; custom payment-method identities and behavior are not supported; configurable prefixes/financial-year sequences are not wired across all voucher allocators. Do not treat label editing as custom payment-method support.
- Remaining: browser acceptance and migration rehearsal for import/export and Settings; inventory import large-file/permitted-role checks; safe bank-account identity bridge; then sequence settings and consolidation with concurrency/regression evidence.

Updated local estimates: `DS-08 payment/voucher settings` **60%**; `DS-09 inventory CSV import/export` **85%**; Definitions & Settings overall **about 81%**. Remaining: inspect any unconverted writers and run concurrency/identity regression coverage after migrations are rehearsed; then safely bridge project bank accounts into transaction forms.

## DS-05 legacy project charge baseline checkpoint — 2026-11-03

- Added forward-only migration `2026_11_03_000022_backfill_project_charge_revision_baselines.php` for existing projects that have configured corner/facing rates but no revision history.
- The migration inserts only when a project has no revisions, uses the project opening date (or creation date when unset) as the first known effective date, and preserves current configured rates. Existing revision rows are never updated or removed; rollback intentionally retains the history.
- This ensures booking calculations can use date-effective rates for legacy projects after migration instead of falling back to manual charges. It cannot reconstruct rate changes made before revision tracking existed; those dates inherit the current stored rate as the first known baseline.
- Migration has not been run. No live data or database was touched.

Updated local estimates: `DS-05 project/plot configuration` **82%**; overall Definitions & Settings estimate **about 82%**. Next: close remaining DS-01 account selector/posting enforcement gaps, then continue DS-08 bank and voucher settings integration without reinterpreting legacy bank IDs.

## DS-01/DS-04 labour site account integrity checkpoint — 2026-11-03

- The Labour dashboard account selector included inactive head/sub-head mappings. It now uses the shared `availableForPosting()` scope.
- Labour JV posting was writing `sites.subhead_accounting_id` (the sub-head definition ID) into ledger and JV-detail account fields, which require the selected project's `project_head_subheads.id`. Posting now resolves and locks the active mapping in the selected project and refuses missing/ambiguous mappings before creating the JV.
- Existing sites are backfilled only when project/head/sub-head identifies exactly one mapping. Ambiguous/unmapped sites remain untouched for administrator correction; existing vouchers and ledger rows are not rewritten. New unambiguous mappings discovered at posting are attached to the site under lock.
- Site voucher history matching checks both the new mapping ID and the legacy definition ID so old voucher details remain findable after the correction.
- Added forward-only migration `2026_11_03_000023_backfill_unambiguous_site_account_mappings.php`; it has not been run. No tests or live database changes were performed.

Updated local estimates: `DS-01 account-status enforcement` **86%**; `DS-04 vendor/site/contractor accounts` **83%**; overall Definitions & Settings estimate **about 83%**. Next: continue auditing the remaining inactive-account write paths, then address voucher settings and bank account identity in a compatibility-safe way.

## DS-01 booking API project isolation checkpoint — 2026-11-03

- `/project/booking/list` previously allowed an omitted project filter and returned broker mappings across projects. It now requires an existing `project_id`, applies that scope to booking rows, and returns only active broker mappings from that same project.
- Repository call-site search found no in-project callers that omit the project ID; external integrations must include it. No record data was changed.
- Verification: targeted PHP syntax lint and `git diff --check` passed. No tests or database writes were run.

Updated local estimates: `DS-01 account-status enforcement` **87%**; overall Definitions & Settings estimate **about 84%**. Next: continue the remaining inactive-account posting audit and integrate the project bank account identity into voucher writes without changing historical `bank_id` meanings.

## DS-01 cash voucher account selector checkpoint � 2026-11-03

- Cash In, Cash Out, and Draft Voucher account selectors now use `ProjectHeadSubhead::availableForPosting()` for both heads and child accounts. Inactive definitions remain available to historical reports/edit display where required, but are not offered for new voucher selection.
- Reviewed the cash voucher correction endpoint: it already validates the selected project/head/sub-head through `availableForPosting()` before changing linked ledger and customer-ledger entries; invalid mappings are rejected without touching records.
- Verification: `php -l app/Http/Controllers/Finance/VoucherController.php` and `git diff --check` passed. No tests run and no database changes made.
- Remaining DS-01 work: extend endpoint-level audits to the other legacy transaction types and confirm account activation/deactivation safeguards against every writer.

## DS-01 project setup system-account validation checkpoint � 2026-11-03

- Project creation now refuses an inactive/missing default sales head or sub-head before creating its project mapping. The transaction rolls back the new project if any required system mapping is inactive or unavailable.
- The system head/sub-head mapping helper now fails closed for inactive definitions, verifies the resulting mapping is available for posting, and returns the actual project mapping ID (matching its declared return type).
- Verification: PHP syntax checks and `git diff --check` passed. No tests run and no database changes made.

## DS-08 bank-account identity bridge checkpoint � 2026-11-03

- Project bank profiles are linked by a composite project/account-mapping foreign key. Legacy voucher `bank_id` remains the external bank catalog ID for cheque/transfer instrument history and is not repurposed.
- Added the optional project-bank-profile relationship to project account mappings. Cash In, Cash Out, and Draft Voucher selectors now show the configured bank name/title beside the existing chart sub-head, while posting still stores the existing project mapping ID. Thus transaction identity continues through the account mapping already referenced by ledger records; no historic row is rewritten and no duplicate bank-account ID is inferred.
- Bank-profile eager loading is conditional on the settings migration being installed, so finance pages continue to load on databases that have not applied it.
- Verification: relevant PHP files lint and `git diff --check` passed. No tests run, migrations applied, or database records changed.
- Remaining DS-08: configurable custom payment methods and voucher prefix/financial-year sequence controls. Number display migration must preserve existing voucher-number columns and historical values.

## DS-01 accounting selector project-scope checkpoint � 2026-11-03

- Accounting AJAX head/child selectors now always apply the active-for-posting scope server-side; clients cannot disable inactive filtering by omitting or changing a request flag.
- The project-account selector endpoints now bind responses to the selected project and reject a supplied project ID that does not match. The account list endpoint also no longer returns inactive mappings.
- This closes read-selector bypasses while posting paths continue to validate independently. Historical report queries were not changed.
- Verification: AccountingController PHP syntax and `git diff --check` passed. Tests not run; no database changes made.

## DS-01 account-mapping creation safeguard checkpoint � 2026-11-03

- Account Mapping creation now validates active head/sub-head definitions, locks the selected project and definitions, checks duplicates only within that project and only for the general mapping identity, then inserts through `ProjectHeadSubhead` in a transaction.
- The mapping form now offers active definitions and the selected project only. Existing customer/plot mappings are not mistaken for duplicate general mappings and are not modified.
- Verification: AccountingController PHP syntax and `git diff --check` passed. No tests run and no data changed.

## DS-01 account mapping edit safeguard checkpoint � 2026-11-03

- Account Mapping edit now scopes reads to the selected project, only offers active heads for new assignments (while retaining the currently linked inactive head for visibility), and rejects inactive head/sub-head definitions when creating new mappings.
- Submitted head IDs are validated as distinct existing definitions; mappings with financial history remain protected by `AccountMappingHistoryGuard`. All new mapping writes stay in the selected project.
- Verification: AccountingController PHP syntax and `git diff --check` passed. Tests not run and no data changed.

## DS-01 account mapping reference protection checkpoint - 2026-11-03

- Generic Account Mapping update now serializes edits with the selected project lock and locks affected generic mapping rows before checking duplicates. The final update remains scoped to the selected project and generic mappings only.
- Generic mapping deletion now rejects customer/plot mappings by scope and serializes against project mapping changes.
- AccountMappingHistoryGuard now protects mapping identities referenced by sites, project bank accounts, contractors, project-specific stock-party assignments and their audit history, in addition to ledger/voucher rows. Optional tables/columns are checked before querying for compatibility with older schema.
- Verification: PHP syntax checks and git diff --check passed. No feature tests, migrations, or data changes were run.

## DS-03 plot definition creation concurrency checkpoint - 2026-11-03

- Plot definition creation now locks the selected project and repeats code, legacy type, and active same-project unit checks inside the transaction. Two simultaneous requests cannot pass stale preflight checks and create conflicting legacy type definitions.
- Rate updates already lock the size definition and enforce a unique effective date before appending an immutable revision.
- Verification: PHP lint and git diff --check passed for touched controllers and account mapping guard. No tests, migrations, or database writes were run.

## DS-06 employee definition concurrency checkpoint - 2026-11-03

- Employee department/designation/category creation now locks the selected project and rechecks duplicate codes inside the transaction, preventing concurrent duplicate definitions after request validation.
- Employee profile writes already lock the employee and selected active definitions in the project; the new-definition path now uses the same project-level serialization rule as plot definitions.
- Verification: PHP lint and git diff --check passed. No tests, migrations, or database writes were run.

## DS-08 bank profile history safeguard checkpoint - 2026-11-03

- A project bank profile can no longer be moved to another COA mapping after the original mapping has posted ledger, draft-ledger, JV-detail, commission-detail, or V2 posting-line history. The settings form returns a validation error and directs admins to configure a separate bank profile.
- Bank name/title/branch/status edits remain available and audited; only the identity-changing mapping switch is protected. No existing transaction or bank catalog ID is rewritten.
- Verification: PHP lint and git diff --check passed. Tests, migrations, and database writes were not run.

## 2026-11-03 verified account and project isolation checkpoint

- AccountMappingHistoryGuardTest: 3 passed; ledger history, bank references, and missing optional tables covered.
- AccountMappingHistoryIntegrityTest: 7 passed after its SQLite fixture was updated to include the current project, head/subhead and generic mapping schema.
- AccountingProjectIsolationTest: 3 passed; cross-project accounting creation, accountant-ledger query and details filter return 403.
- AccountingController now scopes the accounting list and details list to the selected project, and saves accounting entries against the selected project under a transaction. The client project ID must match; failed account validation does not write a record.
- Tests use SQLite :memory:. No production or local application database migration/write was performed.
- Remaining: full posting-path status audit, DS-08 method and voucher configuration, payroll accounting linkage, inventory acceptance, and DS-10 migration/reconciliation/browser verification.

## 2026-11-03 stock import staging checkpoint

- Fixed staged CSV row inserts to set batch_id on the actual chunk rows, preserving the import-row foreign key.
- StockItemImportStagingTest: 1 passed, 5 assertions on SQLite :memory:; staged row links to the selected-project batch and stock master is unchanged until apply.
- StockController PHP lint and focused git diff check passed. No application database write or migration was run.
- Remaining DS-09: verify project ownership of stock item definitions and real Excel format support. Remaining DS-10: migration rehearsal, reconciliation, browser QA and production runbook.


## 2026-11-03 stock regression checkpoint

- Updated the StockLedgerPostingGuardTest SQLite fixture with active head/subhead definitions required by the current guard. No production code weakened.
- Focused stock suite: 47 passed, 96 assertions across posting guard, deletion integrity and staged import tests.
- Remaining DS-09 risk: stock_items is a global master in both web and mobile controllers. Project-scoped import batches can update that shared definition; a tenant ownership policy and migration are needed before claiming project-isolated item imports.


## 2026-11-03 voucher allocation checkpoint

- ProjectVoucherAllocatorTest: 1 passed, 4 assertions. Selected project CR sequence advances from its own retained source and ledger numbers (13, then 14); another project's higher sequence is ignored and no counter is written for it.
- No production counter or voucher row was changed; test uses SQLite :memory:. DS-08 still lacks configurable prefixes and fiscal-year reset policy, so milestone is incomplete.


## 2026-11-03 import tenant preview checkpoint

- StockItemImportStagingTest now also switches to another selected project and verifies the staged batch preview returns 404. Result: 1 passed, 6 assertions.


## 2026-11-03 contractor definition checkpoint

- Contractor create/update now lock the selected project and repeat duplicate-code checks inside the transaction, preventing concurrent duplicate codes from passing stale preflight checks.
- ContractorDefinitionIsolationTest: 2 passed, 7 assertions; duplicate code and foreign-project accounting mapping are rejected without modifying the existing contractor.
- No application database migration/write was run. Next: DS-09 stock master ownership policy, DS-08 numbering configuration, DS-10 rehearsal and acceptance.


## 2026-11-03 DS-09 Excel checkpoint

- Added native XLSX stock-item export (all or selected) and XLSX staging through the existing preview/confirmation pipeline; CSV remains supported. XLSX text cells are literal inline strings, preventing formula execution. Excel parsing rejects DTD/entity declarations, oversized worksheet/shared-string XML, and over 10000 rows.
- Found and fixed a real staging FK bug: an unknown item ID is now kept in the row payload/error but the nullable stock_item_id FK remains null, so the failed preview can be shown instead of crashing.
- StockItemExcelTest and StockItemImportStagingTest: 3 passed, 19 assertions including XLSX roundtrip and failed-ID staging. No application database migration/write was run.
- Remaining DS-09: test import apply/replay and policy for globally shared stock master changes; DS-10 rehearsal and browser QA remain.


## 2026-11-03 DS-09 apply and replay checkpoint

- Confirmed import apply rejects a batch containing failed rows, rejects another project's batch, creates one item for a valid batch, and returns already_applied on replay without a duplicate item.
- Focused suite across XLSX, import, contractor, and voucher tests: 6 passed, 37 assertions. No application database writes/migrations.


## 2026-11-03 DS-03 merge verification checkpoint

- AccountGroupMergePreviewTest: 1 passed, 12 assertions. Preview excludes foreign-project ledger totals and foreign target. Actual merge moves the selected project Head link, retires source, writes merge audit, and leaves both selected and other-project ledger rows/amounts intact.
- Fixture was aligned with the real project_head_account_groups timestamps. No application database write/migration.
- Remaining DS-03 gates: duplicate-conflict, replay, authorization and concurrency tests plus browser review.


## 2026-11-03 DS-03 replay and duplicate checkpoint

- Expanded merge test: replay returns 409 with ledger count unchanged; duplicate mapping preview cannot apply. Result: 1 passed, 17 assertions. Merge route is protected by update accounting permission middleware.
- Remaining DS-03: multi-request concurrency and browser review; no application database write/migration.


## 2026-11-03 contractor ledger correction

- Fixed contractor ledger report to derive project scope through project_head_subheads, because ledgers has no project_id column. Qualified selected ledger fields and order to avoid ambiguous joins.
- ContractorDefinitionIsolationTest: 3 passed, 10 assertions; confirms same voucher ID with another project's account mapping is excluded from this contractor balance.
- No application database migration/write. Next: payroll accounting linkage and DS-08 settings remain.


## 2026-11-03 combined regression checkpoint

- Stock, contractor, account-group merge and voucher allocator suite: 54 passed, 148 assertions.
- Account mapping guard, history integrity and accounting tenant isolation suite: 13 passed, 45 assertions.
- Combined focused gate: 67 passed, 193 assertions. All use SQLite :memory:; no application database migration/write.


## 2026-11-03 DS-01 inactive account checkpoint

- ActiveAccountPostingScopeTest: 1 passed, 4 assertions. An inactive Head or Sub-head removes its account mapping from new-posting scope while ordinary historical queries still see it; reactivation restores the same identity.
- No application database writes/migrations. Remaining DS-01: write-path forged-ID regression and status-audit atomicity gates.


## 2026-11-03 checkpoint: project stock item master
- User policy: January calendar financial year; stock item master is project-wise.
- Added stock_project_item_definitions with project/item key and restrictive foreign keys. Migration copies existing item metadata per project without changing item IDs or historical movements.
- Web item list/export/create/update/archive, CSV/XLSX import stage/apply, and web/mobile stock posting now use the selected project's active definition. Archiving affects only that project; global identity and historical ledgers remain.
- Verification: stock deletion/import/Excel/posting guard suite 49 passed, 126 assertions; targeted project isolation update/archive suite 9 passed, 79 assertions. PHP lint passed. Isolated SQLite memory only; local/live migration has not run.
- Next: calendar-year voucher display/sequence policy, remaining Definitions & Settings integration, isolated migration rehearsal.
- Broader --filter=Stock suite exposes an older OriginalVoucherV2WorkflowHttpTest fixture gap: it omits stock_project_item_balances and stock_party_project_accounts required by current posting code. The fixture was not changed; this broader test is not counted as passed. The focused project-stock suite passed. Add a complete fixture before using this workflow test as an acceptance gate.

## 2026-11-04 stock import cross-project update checkpoint
- Extended import acceptance: a staged update to an existing item changes only the selected project definition. Another project definition and global historical identity remain unchanged. The fixture also verifies a forged edit of an unowned project item is rejected.
- StockItemImportStagingTest: 1 passed, 25 assertions on SQLite :memory:. No application database migration or business-row write.
- Engineering milestone DS-09 remains pending mobile HTTP fixture, permission/large-file gates, and migration rehearsal.

## 2026-11-04 DS-01 write-path gate
- Added direct write-path regression: forged inactive or foreign-project accounting mappings cannot create an AccountingExpense. Status change rolls back when audit persistence fails.
- InactiveAccountingWriteTest: 2 passed, 7 assertions on SQLite :memory:. No application database migration or business-row write.
- Remaining DS-01: broaden these gates to remaining posting families and rehearse the additive status-audit migration.

## 2026-11-04 DS-07 payroll payment evidence gate

- Approved immutable payroll slips can be linked to existing approved, active, posted CP sources in the same project. Links store amount and audit evidence without editing CP, payroll snapshots, or ledgers. Replays are idempotent; changed replays and overpayment of either slip or CP are rejected.
- The run screen shows paid and remaining amount per employee and permits authorized payment links. Route requires `approve payroll` and selected-project access.
- EmployeePayrollPaymentLinkerTest: 1 passed, 12 assertions on SQLite :memory:; link route registered. No application database migration or business-row write. The additive payment-link migration is required before deployment use.
- DS-07 engineering exit criteria met locally. Next: DS-08 voucher settings and identity controls.

## 2026-11-04 DS-09 inventory acceptance gate

- The import fixture now proves an unauthorized user and a file above 5 MB cannot stage a batch. Existing project-isolated import/update/apply/replay checks still pass: StockItemImportStagingTest 1 passed, 29 assertions.
- Updated the older V2 workflow fixture to include current account-status and project stock definitions/balances/mapping. Web and mobile stock creation, finance approval, replay and rollback now pass: 6 tests, 45 assertions.
- DS-09 focused engineering gate is complete locally. Production migration/application still belongs to DS-10 and deployment. Browser QA is assigned to the client.
- Repaired the remaining V2 fixture drift (active account dimensions, project-scoped stock rows and payroll contractor column). Full OriginalVoucherV2WorkflowHttpTest now passes: 46 tests, 352 assertions on SQLite :memory:. This is the broad pilot workflow regression gate, not production database migration evidence.

## 2026-11-04 DS-08 allocator safety checkpoint

- Legacy getVocuherNumber fallback now counts retained customer_ledger source IDs as reserved as well as ledger voucher numbers, including when the counter migration is absent. This prevents reuse after an old ledger leg is missing. Existing numeric identifiers and displayed prefixes are unchanged.
- ProjectVoucherAllocatorTest passes with a source-only CP number: 1 test, 5 assertions. DS-08 is still open for configurable custom methods, prefixes, and calendar-year display/sequence migration across all voucher writers.
- Combined account/stock/payroll/allocator focused gate: 13 passed, 87 assertions. Full isolated V2 pilot workflow suite: 46 passed, 352 assertions.

## 2026-11-04 DS-08 configuration checkpoint

- User decisions: January calendar year, custom methods map to Cash/Online/Cheque, voucher display pattern TYPE-YEAR-0001.
- Added project-scoped voucher prefix settings with audit trail, custom payment method metadata with fixed base behavior, and project/series/year sequence allocation service. Legacy numeric voucher identities and ledger rows are not modified by these settings.
- ProjectVoucherDisplayTest and ProjectVoucherFiscalSequenceTest: 2 passed, 10 assertions. Settings routes registered and Blade compiled. The UI explicitly says custom methods are not offered in voucher forms yet.
- DS-08 remains open: persist each allocated display number against its actual voucher source, make supported writers select custom method identity and resolve its base behavior, verify bank-account identity and migrations. New settings tables require isolated migration rehearsal before deployment.
- Local MySQL client exists, but localhost:3306 refused the connection, so the isolated MySQL clone rehearsal is not yet possible. No application database migration/write was run.

## 2026-11-04 isolated MySQL rehearsal checkpoint

- Restored 2026-10-31 supplied local rehearsal SQL into disposable codex_ds_rehearsal_20261104; app database and .env were untouched.
- DS migrations 000001-000013 reached completion after fixing MySQL DDL order for account_groups and plot_definitions self-referencing FKs, adding the missing attendance (id,project_id) key, and removing a journal_vouchers.site_id placement assumption.
- DS-14 stopped on historical data: 165 labours have NULL project_id; 627 labour_ledgers reference those labours. Migration now reports this explicitly before attempting the FK. No inferred project backfill was performed.
- Independent DS-08 additive migrations 2026_11_04_000002 through 000004 passed on the clone. Existing ledgers and customer_ledger row counts and exact in/out totals were unchanged. MySQL full migration chain, dependent payroll/stock migration rehearsal, and all-table reconciliation remain open.

## 2026-11-04 conditional full-clone rehearsal result

- In disposable clone only, 151 labour masters were assigned the one project appearing in all their linked labour-ledger rows. The remaining 14 unlinked masters were left unchanged. This was test data repair, not an application database write or production repair policy.
- With that evidence-based clone mapping, DS-14 through DS-24 and payroll payment-link migration passed. Together with earlier runs, all 29 DS migrations completed on the clone. Financial ledger/customer-ledger row counts and exact in/out sums matched the pre-migration baseline.
- Stock project definitions: 12 rows, no duplicate project/item pair or missing project/item owner. Payroll payment links: 0. New settings tables have expected project FKs. Historical stock and other table totals were not captured before import, so the full reconciliation gate remains incomplete.
- Focused regression after migration fixes: 7 tests, 79 assertions passed. DS-08 still needs voucher-writer integration for immutable fiscal display identities and custom payment method selection. Client browser QA remains excluded by request.
