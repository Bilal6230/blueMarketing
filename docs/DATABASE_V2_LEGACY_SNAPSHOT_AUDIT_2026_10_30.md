# BlueMarketing Database V2 — First Legacy SQL Snapshot Audit

**Snapshot date:** 2026-10-30, `u429032065_dev.sql` (read-only uploaded phpMyAdmin SQL export).  
**Dump metadata:** database `u429032065_dev`, MariaDB `11.8.9-MariaDB-log`.  
**Target for V2 compatibility rehearsal:** MySQL 8.4.3 (separate environment).  
**Branch:** `upgrade/laravel-framework`.  
**Status:** INITIAL STRUCTURAL BASELINE, **NOT** exhaustive relationship/code/data reconciliation.

The SQL contents and individual financial/customer records are private and **must not be committed**. This document contains only structural and aggregate findings derived offline without importing or executing SQL.

## Aggregate snapshot findings

| Metric | Verified count | Scope |
|---|---:|---|
| Tables (`CREATE TABLE`) | **57** | This SQL export |
| Tables with exported rows | **44** | 13 have no INSERT rows |
| Exported row tuples | **120,165** | Across 44 tables |
| Declared FK constraints (`ADD CONSTRAINT ... FOREIGN KEY`) | **40** | SQL DDL |
| Declared unique keys (`ADD UNIQUE KEY`) | **16** | SQL DDL; excludes PKs |
| Tables with `deleted_at` column | **10** | 47 without; not 47 automatic soft-delete defects |
| Rows in legacy `migrations` | **14** | Specific *dev* dump, not previous staging history |
| Ledger rows | **18,003** | All types, active+inactive; not a financial reconciliation |
| General JV headers | **991** | All exported headers |
| General JV details | **5,147** | Includes soft-deleted detail history |
| `customer_ledger` rows | **5,580** | Multiple source families |
| Ledger `type='JV'` rows | **2,970** | Includes non-general-JV source family risk |
| Ledger rows with `customer_ledger_id` | **4,001** | Direct source FK present |
| Distinct candidate columns ending `_id` without declared FK | **56** | **Heuristic leads, NOT 56 proven missing FKs** |

### Ledger type distribution (all 18,003 rows)

| `ledgers.type` | Row count |
|---|---:|
| CP | 11,072 |
| CR | 3,700 |
| JV | 2,970 |
| BO | 101 |
| BR | 77 |
| PPR | 77 |
| SV | 6 |

**Critical:** `type='JV'` is overloaded across current stock and genuine JV and past CV flows. Matching `type_id` to journal header IDs is *not* evidence of a real header relation.

## Verified relationship and key-type blockers

The following checks compare non-NULL stored numeric keys with the corresponding parent ID set in the uploaded dump. Zero unmatched IDs **does not** certify valid source identity or enforceability.

| Candidate relationship | Checked child rows | Missing parent IDs | FK-compatible SQL integer type? | Current direct FK? |
|---|---:|---:|---|---|
| `ledgers.project_head_subheads_id → project_head_subheads.id` | 18,003 | 0 | **NO** — BIGINT UNSIGNED vs INT UNSIGNED | No |
| `journal_voucher_details.account_id → project_head_subheads.id` | 5,147 | 0 | **NO** — BIGINT UNSIGNED vs INT UNSIGNED | No |
| `commision_voucher_details.account_id → project_head_subheads.id` | 0 | 0 | **NO** — BIGINT UNSIGNED vs INT UNSIGNED | No |
| `journal_vouchers.project_id → projects.id` | 991 | 0 | **NO** — signed INT vs unsigned BIGINT | No |
| `customer_ledger.project_id → projects.id` | 5,580 | 0 | **NO** — signed vs unsigned BIGINT | No |
| `bookings.project_id → projects.id` | 198 | 0 | **NO** — signed INT vs unsigned BIGINT | No |
| `bookings.customer_id → leads.id` | 198 | 0 | **NO** — signed INT vs unsigned BIGINT | No |
| `bookings.plot_id → plots.id` | 198 | 0 | **NO** — signed INT vs unsigned BIGINT | No |
| `booking_vouchers.booking_id → bookings.id` | 81 | 0 | YES | No |
| `booking_vouchers.ledger_id → ledgers.id` | 77 | 0 | YES | No |
| `booking_vouchers.customer_ledger_id → customer_ledger.id` | 81 | 0 | YES | No |
| `ledgers.customer_ledger_id → customer_ledger.id` | 4,001 | 0 | YES | **Yes**, ON DELETE SET NULL |
| `ledgers.charge_type_id → charge_types.id` | 0 | 0 | YES | **Yes**, ON DELETE SET NULL |

For the **30 FK relationships with exported child data**, the non-NULL referenced ID checks found **zero missing parent IDs**. There are 40 declared FKs overall, but the remaining ten cannot be claimed data-tested from rows absent in this dump.

**Additional project ownership check:** 4,001 ledger rows carrying `customer_ledger_id` had no mismatch between the linked source project and the ledger account's project. This is only an account/source project identity check, not voucher posting parity.

### JV evidence — unsafe to auto-reclassify

- 2,970 rows use `ledgers.type='JV'`.
- 2,889 of those have a numeric `type_id` matching some `journal_vouchers.id`; **81 do not**. Neither group is automatically attributable to a real JV because of polymorphic source collisions.
- **Three** JV-typed ledger rows match an existing JV header's numeric ID but their account project's ID differs from that header's project. Treat as cross-domain numeric collision candidates for forensic ownership, not as proven invalid rows.
- **Two** JV-typed rows have a populated `customer_ledger_id`, reinforcing non-JV-header source possibilities.
- Uploaded `ledgers` structure has **NO** `journal_voucher_detail_id` column and snapshot contains **NO** `ledger_source_attributions` table. Source code support for optional provenance must not be confused with deployed schema.

**Do not recover missing legs using just the current 991/5,147/2,970 counts.** Require active-detail and per-project/account/money-source multiset parity, typed provenance, evidence review and reversible recovery approval.

## `deleted_at` presence — factual, not blanket recommendation

Present in: `bookings`, `commision_vouchers`, `commision_voucher_details`, `journal_vouchers`, `journal_voucher_details`, `personal_access_tokens`, `stock_bills`, `stock_entries`, `stock_items`, `stock_parties`.

Absent from `ledgers`, `customer_ledger`, `labours`, `labour_attendances`, `users` and many other tables. Determine retention policy per entity: master soft-delete, finance void/reversal, append-only audit history, or ephemeral hard-delete. **Never blindly add SoftDeletes to every table or cascade financial deletions.**

## Exported table row-count inventory (57 tables)

| Table | Rows | Table | Rows |
|---|---:|---|---:|
| accounting_expenses | 1 | account_types | 6 |
| areas | 20 | bookings | 198 |
| booking_details | 3,970 | booking_edit_audits | 0 |
| booking_vouchers | 81 | charge_types | 0 |
| commision_vouchers | 0 | commision_voucher_details | 0 |
| customer_ledger | 5,580 | dasticash | 4 |
| draft_ledgers | 1 | facings | 5 |
| failed_jobs | 0 | head_accountings | 44 |
| hold_plots | 0 | installments | 0 |
| journal_vouchers | 991 | journal_voucher_details | 5,147 |
| labours | 165 | labour_attendances | 2,695 |
| labour_ledgers | 627 | leads | 13,396 |
| lead_user | 13,380 | ledgers | 18,003 |
| migrations | 14 | model_has_permissions | 0 |
| model_has_roles | 52 | password_resets | 0 |
| pending_updates | 159 | permissions | 93 |
| personal_access_tokens | 18 | plots | 726 |
| projects | 6 | project_head_subheads | 561 |
| project_zones | 0 | punches | 30 |
| roads | 11 | roles | 9 |
| role_has_permissions | 227 | settings | 13 |
| sites | 7 | stocks | 0 |
| stock_bills | 0 | stock_bill_lines | 0 |
| stock_counters | 2 | stock_entries | 1 |
| stock_entry_lines | 2 | stock_items | 2 |
| stock_item_balances | 2 | stock_movements | 2 |
| stock_parties | 3 | subhead_accountings | 448 |
| users | 52 | works | 53,405 |
| zones | 6 | — | — |

**Interpretation:** the dump's 14 migration rows must be separately reconciled with the **current branch's** repository migration inventory. Other snapshots have different counts. The dump's server is MariaDB; MySQL 8.4 acceptance is not implied.

## Required next actions and risk gates

1. Expand every `_id` candidate to actual model/controller joins, relationship multiplicity, referential action, nullability and confirmed parent; classify 56 heuristics individually, not automatically.
2. Finish exact code-path map of CRUD, ledger direct/bulk writes and readers, raw SQL and all voucher families (see `LEDGER_SOURCE_IDENTITY_REPOSITORY_AUDIT_2026_10_29.md`).
3. Inspect actual legacy PK/unique/signedness and source timestamps before proposing V2 schema. Preserve original values and IDs where feasible; use deterministic old→new mapping if widths force a change.
4. Design FK-safe import order and explicit failure/quarantine reporting; keep original source data immutable.
5. Prepare isolated MySQL 8.4 schema/migration tests and independent financial reconciliation before any V2 import.

**Phase tracker:** [DATABASE_V2_REBUILD_ROADMAP.md](DATABASE_V2_REBUILD_ROADMAP.md) — P1.G1 completed; overall 1/24 = 4%.

No database was connected to, restored, modified, or migrated. No finance code changed. No deployment performed.

## Next pass — 56 unconstrained `*_id` candidate columns

This is an **automated naming-based inventory, not confirmed 56 missing FKs**. Some are genuine join candidates, but others are polymorphic business IDs (`type_id`, `model_id`, `tokenable_id`, `record_id`) or statuses and must **not** be assigned a false FK. Confirm each with application code and data before altering schema.

| Table | Unconstrained candidate fields to classify |
|---|---|
| accounting_expenses | project_head_subheads_id |
| areas | project_id |
| bookings | project_id, customer_id, plot_id, broker_id |
| booking_edit_audits | booking_id, project_id, user_id, journal_voucher_id |
| booking_vouchers | booking_id, customer_ledger_id, ledger_id, project_id, customer_id, plot_id, bank_id |
| commision_voucher_details | account_id |
| customer_ledger | type_id, project_id, plot_id, bank_id |
| draft_ledgers | type_id, customer_id, project_id, plot_id, bank_id, project_head_subheads_id |
| journal_vouchers | project_id |
| journal_voucher_details | account_id |
| leads | area_id, zone_id, follow_id, project_id |
| ledgers | type_id, project_head_subheads_id |
| model_has_permissions | model_id |
| model_has_roles | model_id |
| pending_updates | record_id |
| personal_access_tokens | tokenable_id |
| plots | project_id, road_id, facing_id |
| project_head_subheads | plot_id, customer_id |
| punches | user_id, project_id |
| sites | head_accounting_id, subhead_accounting_id |
| stock_bills | party_id, entry_id |
| stock_movements | source_id, source_line_id |
| users | status_id, project_id |
| works | user_id |

**Immediate priority:** real ledger account FK, JV detail account FK, explicit journal/commission/stock identity, booking source references, and project tenancy. Keep Laravel/Spatie/Sanctum polymorphic fields polymorphic unless changing their model is independently justified. Do not automatically add FKs to business sequence numbers or workflow polymorphic keys.

