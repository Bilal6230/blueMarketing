# Definitions & Settings — Requirements Audit and Delivery Plan

**Date:** 2026-11-02  
**Project:** BlueMarketing / Waqas ERP  
**Status:** DEFERRED — implementation must not start until the current dashboard/sidebar work, known regressions, and pending bug fixes are closed.  
**Source:** Client requirements for Definitions & Settings, Labour Contractor Accounts, and Store Inventory Excel Import/Export.

## 1. Purpose and scope

This document separates the client requirements into:

1. Existing functionality that mainly needs navigation/configuration alignment.
2. Partial functionality that should be extended without replacing current data or workflows.
3. New functionality that requires schema, backend, permissions, UI, audit, and tests.

The core acceptance rule is preservation: no existing customer, project, plot, vendor, contractor, inventory, employee, ledger, voucher, attendance, or financial record may be lost, duplicated, silently reassigned, or rewritten.

## 2. Current repository evidence

| Area | Current evidence |
|---|---|
| Account heads | `HeadAccounting`, accounting head CRUD routes and UI |
| Account sub-heads | `SubheadAccounting`, party/child account CRUD routes and UI |
| Classification | `AccountType`; heads contain `acct_type` |
| Account mapping | `project_head_subheads` maps project, head, sub-head, customer and plot |
| Ledger history protection | `AccountMappingHistoryGuard`, preserved-record concerns, finance regression tests |
| Projects | `Project`, project CRUD, address and active status |
| Plots | `Plot`, existing Add Plot form, type, size, unit, amount, corner, road and facing |
| Employees | `User`, status, contact/CNIC/department fields, roles and permissions |
| Attendance | Punch-in/out and dashboard attendance summaries |
| Sites | `sites`, site/head/sub-head fields, labour attendance site linkage, JV site field |
| Labour | Labour master, status, daily wage, attendance, advances, payments and ledgers |
| Inventory | Stock items, parties, balances, entries, bills, movements, units and rates |
| Payment workflows | Cash, online and cheque handling; bank-payment clearance states |
| Voucher numbering | Existing automatic allocators in operational voucher flows |

Repository presence is not proof of complete client acceptance. Partial items below still require end-to-end enforcement and tests.

## 3. Existing functionality — configuration/navigation work

These features already have usable foundations and should be retained:

- Account Heads and Account Sub-Heads management.
- Account classification through Account Types.
- Project-specific Head/Sub-Head mappings.
- Basic Type → Head → Sub-Head/Account reporting hierarchy.
- Project create, edit, view, management and active status.
- Existing Add Plot form and its fields.
- Plot type, size, unit, amount/rate, road, facing, corner and project association.
- Basic user/employee identity, contact, CNIC, department text and status.
- Manual employee punch-in/punch-out.
- Cash, online-transfer and cheque transaction handling.
- Existing bank selection and cheque/online clearance workflow.
- Existing automatic operational voucher numbering.
- Site name/address and basic account linkage.
- Labour master, attendance, wage, advance/payment and ledger flows.
- Inventory items, parties, supplier/consumer/site types, unit, default rate, balances and historical movements.
- Existing authorization framework using roles and permissions.
- Existing financial-history and used-record deletion guards where already implemented.

### Required alignment work

- Rename the navigation label “Project Definitions” to “Project Setup” where applicable.
- Place each operational definition in its owning module.
- Preserve existing route names, permission checks, fields and stored records.
- Do not move Inventory Items/Categories out of Store & Inventory.
- Do not move Labour Categories/Rates out of Labour Management.
- Keep Roles, Permissions and Access Management under Administration/Employee management.

## 4. Partial functionality — extend safely

### 4.1 Accounts

- Current Head/Sub-Head structure is not an arbitrary multi-level Chart of Accounts.
- `account_types` supplies basic classification, but not a complete configurable classification lifecycle.
- Vendor-like records exist as Stock Parties with `Supplier` type; account fields are strings rather than durable account foreign keys.
- Sites exist but lack a complete code, project, status and centrally enforced site-ledger model.
- Head/Sub-Head active status exists, but selection and posting restrictions are not centrally enforced across every module.

### 4.2 Project Setup

Currently available: name, address, status and CRUD. Required extensions:

- Project code.
- Location/details beyond the current address.
- Opening and closing dates.
- Project-wise configuration.
- Corner charge percentage.
- Facing charge percentage.
- Effective-date/revision history for charge changes.
- Sales/Plot calculation integration.

### 4.3 Plot definitions

The existing Add Plot form must remain intact. Extensions required:

- Dedicated Plot Category master.
- Dedicated Plot Type master if current type values are static.
- Plot Size definitions and classification.
- Reusable Plot Unit definitions.
- Unit-wise rate configuration and effective history.
- Foreign-key association of plots with the relevant definitions.

### 4.4 Employee management

Current users provide basic identity and department text. Extensions required:

- Designation master.
- Department master.
- Employee Category master.
- Employee code, joining date and structured employment record.
- Effective-dated employment configuration.

### 4.5 Bank/Cash and numbering

- Bank/Cash accounts currently operate through accounting mappings and transaction fields.
- A dedicated bank definition with branch, account number and status is incomplete.
- Voucher numbers are generated operationally, but configurable prefixes, financial-year sequences and administrative sequence controls are missing.

### 4.6 Contractors

- Labour workers, roles, wages, attendance, advances and payments exist.
- A Contractor is not yet a dedicated master with a durable Chart-of-Accounts relationship and contractor-wise outstanding ledger.

## 5. New development required

### 5.1 Complete Chart of Accounts

- Dedicated Account Group master.
- Parent-child hierarchy supporting the required depth.
- Duplicate mapping prevention and appropriate unique constraints.
- Classification and reporting integration.
- Historical reports must resolve the current classification without changing transactions.

### 5.2 Mandatory Account Group Merge

The merge must change classification/mapping only; it must never rewrite or delete ledger entries.

Required workflow:

1. Select source and target groups.
2. Reject same-group, inactive-target and invalid cross-scope selections.
3. Lock source/target and affected mappings in one database transaction.
4. Produce a confirmation preview showing affected accounts and balances.
5. Detect duplicate mappings before applying.
6. Move account mappings to the target group.
7. Recalculate and compare opening, movement and closing totals before/after.
8. Roll back on any imbalance or mapping conflict.
9. Retire the source group from new account creation.
10. Record actor, timestamp, source, target, affected accounts, before/after data and verification totals in an immutable audit record.

Required tests include replay/idempotency, concurrent merge, duplicate mapping, project isolation, unchanged ledger count/amounts, Trial Balance classification and authorization.

### 5.3 Universal Account Active/Inactive control

Central policy must cover General Ledger, Heads/Sub-Accounts where applicable, vendors, sites, contractors, bank/cash and all other transaction accounts.

- Inactive accounts must be removed from every new-entry selector.
- Server-side validation must reject inactive account IDs even if a request is forged.
- Posting services must fail closed before any financial write.
- Historical ledgers, balances, reports and transaction detail must remain visible.
- Reactivation must preserve identity and history.
- Every status change must record user, date/time, old/new status and reason.
- No module may bypass the central policy.

### 5.4 Vendor accounts

- Vendor master with unique code, name, contact and address.
- Foreign-key link to the correct Chart-of-Accounts account.
- Add/edit/view/search/status management.
- Availability in Purchase, Store/Inventory and relevant finance workflows.
- Vendor ledger, balance and transaction history.

### 5.5 Site accounts

- Unique site code, name, project, location/details and status.
- Foreign-key Chart-of-Accounts link.
- Site selector in material issue/transfer.
- Site-wise material consumption, expense, ledger and balance reports.
- Inactive-site enforcement for new transactions while retaining history.

### 5.6 Employee salary and attendance rules

Effective-dated, employee-specific configuration is required for:

- Basic salary, allowances, additions, payment method and salary status.
- Duty start/end, breaks, daily hours, working days and weekly offs.
- Shift or individual timing.
- Grace period and fine-free limits.
- One selected late-fine method, ranges and daily/monthly caps.
- Absence, half-day, short leave, early departure and leave deductions.
- Overtime rate/method and other optional adjustments.
- Revision history that does not alter finalized payroll.

### 5.7 Payroll

- Monthly attendance snapshot and deterministic salary calculation.
- Prevention of double deductions for one attendance event.
- Employee-wise preview and consolidated preview.
- Authorized approval/finalization.
- Immutable finalized payroll with controlled adjustments.
- Employee salary slips and consolidated salary sheet.
- Employee/designation/department/month filters.
- PDF, Excel and print output.
- Historical payroll records.
- Accounting/payment linkage when salary is paid.

### 5.8 Payment and voucher configuration

- Configurable Payment Method master, including custom methods.
- Dedicated Bank/Cash account definitions and status.
- Voucher Type definitions.
- Prefix settings.
- Financial-year-wise numbering.
- Sequence controls with concurrency-safe allocation.

### 5.9 Contractor accounts

- Contractor master with unique code, name, contact, address and status.
- Durable Chart-of-Accounts link.
- Contractor selection in labour bills, advances and payments.
- Contractor ledger, transaction history and outstanding balance.
- Inactive contractor rejection for new activity with history retained.

### 5.10 Inventory Excel export/import

- Export all or selected items with clear stable columns.
- Stable Item ID/code matching; never match by mutable item name alone.
- Upload staging and preview before applying.
- Added, updated, skipped and failed counts.
- Row-level validation and downloadable error file.
- Permission-controlled creation of new items.
- Confirmation step and immutable upload history.
- Price/master-field updates must never alter balances, movements, purchase rates or posted history.
- Bulk application must be transactional and duplicate-safe.

## 6. General definition standards

Every applicable definition should provide:

- Add, Edit, View and Manage.
- Active/Inactive status.
- Search and filters.
- Unique code and description.
- Duplicate prevention at validation and database levels.
- Created/updated actor and timestamps.
- Change history for sensitive configuration.
- Restrict/archive behavior when referenced; no destructive deletion of used records.
- Project/tenant isolation where required.
- Permission-gated access.

## 7. Data-integrity rules

- Never cascade-delete financial or operational history.
- Do not update posted ledger amount, identity, date, project or source to implement a classification change.
- Use foreign keys with `RESTRICT` for protected masters and nullable `SET NULL` only where loss of the optional relationship is explicitly acceptable.
- Use soft delete/archive for eligible definitions, while used records remain reportable.
- Use transactions and row locks for merges, payroll finalization, imports and numbering.
- Add unique indexes for codes and relationship identities.
- Backfill new keys through audited, repeatable migrations; do not guess ambiguous mappings.
- Reconcile totals before and after every migration or bulk operation.

## 8. Proposed milestones

| Milestone | Scope | Exit criteria |
|---|---|---|
| DS-00 | Baseline/schema/data audit | Current schema, duplicates, orphan mappings and live workflows documented read-only |
| DS-01 | Universal account status | All selectors and posting paths centrally enforce status; history remains visible |
| DS-02 | COA hierarchy and groups | Group masters, hierarchy, constraints and reports verified |
| DS-03 | Safe group merge | Preview, transactional merge, reconciliation, audit and tests complete |
| DS-04 | Vendor/Site/Contractor accounts | Masters, account FKs, status and owning-module transaction integration complete |
| DS-05 | Project/Plot configuration | Codes/dates, charge history and unit-rate definitions integrated without changing existing form fields |
| DS-06 | Employee definitions/policies | Masters and effective-dated salary/attendance policies complete |
| DS-07 | Payroll | Calculation, preview, approval, history, slips and accounting linkage complete |
| DS-08 | Payment/voucher settings | Configurable methods, banks, prefixes, FY sequences and concurrency tests complete |
| DS-09 | Inventory Excel | Export, staged import, validation, preview, audit and history protection complete |
| DS-10 | Acceptance/migration | Rehearsal migration, reconciliation, permissions, regression and production runbook approved |

## 9. Testing requirements

- Unit tests for calculations, status policies, mapping and numbering.
- Feature tests for permissions, forged requests and inactive records.
- Database tests for foreign keys, uniqueness, soft-delete/archive and project isolation.
- Financial reconciliation tests proving unchanged ledger row counts and debit/credit totals.
- Merge tests proving reports move classification without transaction mutation.
- Payroll tests for grace, late slabs, absence, half-day, leave, overtime and no double deduction.
- Import tests for duplicates, invalid rows, partial failure, replay and unchanged history.
- Browser tests for desktop/tablet/mobile forms, previews, confirmations and exports.
- Performance tests for large account trees, payroll months and inventory files.

## 10. Delivery gate and current decision

Implementation is intentionally paused. Before DS-00 begins:

1. Resolve the current application bugs and dashboard/sidebar acceptance issues.
2. Close or explicitly baseline the remaining regression-test failures.
3. Commit and push the current stable work separately.
4. Take a verified database backup and rehearse against a disposable/local clone.
5. Confirm client terminology for Account Group vs Head/Sub-Head, Vendor vs Supplier, Employee vs User and Contractor vs Labour.
6. Obtain sign-off on payroll policies and merge behavior.

No Definitions & Settings migration or production data mutation is authorized by this document alone.

## 11. Current high-level estimate

- Existing/configuration-oriented scope: approximately **30%**.
- Existing foundations requiring extension: approximately **25%**.
- New development: approximately **45%**.

The highest-risk work is Universal Account Status enforcement and Account Group Merge. These must be implemented before expanding dependent vendor/site/contractor workflows, and only with complete reconciliation and audit coverage.
