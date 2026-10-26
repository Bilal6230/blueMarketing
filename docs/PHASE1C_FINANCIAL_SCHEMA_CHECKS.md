# Phase 1C — Financial Schema/Migration Drift and Integrity Checks

**Date:** 2026-10-26 | **Mode:** READ ONLY | **Source:** user-provided staging SQL snapshot and files from `upgrade/laravel-framework`. This is a targeted financial-table comparison, **not yet a full 57-table audit**. No database modification performed.

## Reproducible financial table column inventory (from staging dump)
| Table | Dump columns | Core create migration discrepancies requiring design |
| --- | ---: | --- |
| `ledgers` | 19 | Dump contains `voucher_number`, `customer_ledger_id`, `charge_type_id` not in original create migration; no `deleted_at` |
| `customer_ledger` | 24 | Original create migration omits bank/approval/passing/project-linked operational fields including `reference`, `plot_id`, `bank_id`, `passing_status`, `is_approve`; no `deleted_at` |
| `bookings` | 29 | Dump adds `deleted_at`, cancellation amounts/status timestamps, `profile_image`, `dicount_value` and other fields not in original creation definition |
| `journal_vouchers` | 15 | Dump has `type`, `project_id`, `deleted_at`; absent from original create migration; type of `voucher_number` must be compared exactly before changing any constraint |
| `journal_voucher_details` | 9 | Dump has `deleted_at`; original create migration must be reviewed with FK cascade behavior |
| `booking_details` | 7 | Current schema has no deleted_at, despite parent Booking soft deletion; inspect whether detail edits/deletes should preserve historical installment schedule |
| `labour_attendances` | 18 | No deleted_at in dump; currently lacks uniform no-hard-delete semantics |
| `stock_items` | 10 | deleted_at exists in dump, but StockItem Eloquent model lacks SoftDeletes trait |
| `booking_vouchers` | 24 | Dump has `delete_reason`; original creation migration omits it |
| `users` | 16 | No deleted_at; User model does not implement SoftDeletes |
| `leads` | 26 | No deleted_at; Lead model does not implement SoftDeletes |
| `project_head_subheads` | 8 | Referenced by general ledger; inspect account mapping, plot/customer validity and project boundaries before adding constraints |

**Interpretation:** This is drift *against individual original create migrations*. Some added columns may be intentionally represented by later tracked migrations; the **combined chronological migration result** has NOT been fully checked yet. Do not label every field above as 'missing migration'.

## High-risk technical observations
1. `customer_ledger` creation migration uses `ON DELETE CASCADE` for `customer_id → leads.id`: conflicts with retention policy if anyone hard-deletes a lead.
2. `booking_details`, `journal_voucher_details`, `labour_attendances` create migrations contain cascade definitions; list all affected real constraints before proposing replacements.
3. `Ledger` and `CustomerLedger` models currently lack SoftDeletes; `Booking` and `JournalVoucher` use it. Record removal behavior is inconsistent.
4. Financial approved postings should be immutable and corrected by balanced void/reversal rather than deleting a single row.
5. The uploaded snapshot and local `bluenew` schema differ (e.g. landing page). **Never import the uploaded full dump over a fresh migrated database.**

## Next-step read-only checks on isolated sanitized database copy (NOT commands to run against production)
Before enabling any new FKs, use MySQL information_schema to inspect types and constraints. Examples:

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME,
       REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

SELECT rc.TABLE_NAME, rc.CONSTRAINT_NAME, rc.UPDATE_RULE, rc.DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS rc
WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
ORDER BY rc.TABLE_NAME, rc.CONSTRAINT_NAME;

SELECT l.id, l.project_head_subheads_id
FROM ledgers l
LEFT JOIN project_head_subheads a ON a.id = l.project_head_subheads_id
WHERE l.project_head_subheads_id IS NOT NULL AND a.id IS NULL
LIMIT 100;

SELECT cl.id, cl.customer_id
FROM customer_ledger cl
LEFT JOIN leads lead ON lead.id = cl.customer_id
WHERE cl.customer_id IS NOT NULL AND lead.id IS NULL
LIMIT 100;

SELECT j.id, j.voucher_number, j.total_debit, j.total_credit
FROM journal_vouchers j
WHERE j.total_debit <> j.total_credit
LIMIT 100;

SELECT voucher_number, COUNT(*) AS cnt
FROM journal_vouchers
GROUP BY voucher_number HAVING COUNT(*) > 1
LIMIT 100;
```

These SELECT queries are candidates for controlled read-only execution; don't share personal data or financial amounts in outputs. First verify actual column types and business rules before interpreting any result.

## Deployment/design gate
- Complete full migrations comparison incl. *all* alter migrations (a standalone create-file comparison is not sufficient).
- Validate real records (orphan, duplicates, exact financial reconciliations) in a copy.
- Formalize FK and soft-delete/reversal design by business entity.
- Build fresh baseline on disposable MySQL, import **data-only with mapping and reconciliation**, never into `bluenew`/production until approved.

**No SQL executed against user database; no migration or application code changes; `master_live` untouched.**
