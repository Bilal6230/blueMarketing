# Phase 1B — SQL Snapshot Structural Audit (Read-only)

**Date:** 2026-10-26  
**Evidence:** user-provided `u429032065_staging_bluema (1).sql` (private; never commit), checked against selected files on `upgrade/laravel-framework`.  
**Status:** findings verified for uploaded staging snapshot; NOT validated against current live DB. No writes performed.

## Structural inventory
- SQL snapshot has **57 CREATE TABLE definitions**.
- Detected **40 ADD CONSTRAINT foreign-key definitions**, of which **19 specify ON DELETE CASCADE**.
- Detected **394 INSERT INTO statements** (statements, NOT row count).
- These counts refer to this single uploaded dump and must not be extrapolated to `bluenew` or live.
- MySQL dump defines many foreign keys in subsequent `ALTER TABLE` statements, not within `CREATE TABLE`. A parser must inspect both.

## Confirmed high-priority schema findings
| Table | Actual columns in staging SQL dump | Code / migration comparison | Concern |
|---|---|---|---|
| `ledgers` | 19 columns, including `voucher_number`, `customer_ledger_id`, `charge_type_id`; **no `deleted_at`** | `2024_01_14_150839_create_ledgers_table.php` lacks those three; Ledger model includes customer_ledger_id/voucher_number but no SoftDeletes | Baseline schema drift; cannot assume migrate:fresh reproduces loaded schema |
| `customer_ledger` | 24 columns including banking, approval, history; **no `deleted_at`** | Initial 2024 create migration does not define all operational columns; model no SoftDeletes | Lossy fresh schema risk |
| `bookings` | 29 columns including `deleted_at`, cancellation financial snapshot fields | Initial booking create migration omits later operational fields and deleted_at; Booking model uses SoftDeletes | Baseline must encode complete current shape |
| `journal_vouchers` | 15 columns including `project_id` and `deleted_at` | Initial create migration lacks project_id/deleted_at; model uses SoftDeletes | Baseline drift |
| `journal_voucher_details` | 9 columns including `deleted_at` | Verify model operations and parent/child delete semantics | Cascades may compromise history |
| `users` / `leads` | No deleted_at | Models do not use SoftDeletes | Proposed no-hard-delete rule not implemented |
| `stock_items` | deleted_at exists | StockItem model does not use SoftDeletes | Inconsistent deletion behavior |

## Foreign-key & business rules requiring redesign
- Do not replace all CASCADE actions mechanically; enumerate each exact constraint and business owner. Ensure voucher, ledger, customer, booking, audit and stock-history records cannot be lost through parent deletion.
- Existing IDs may contain orphan references; count them before any FK creation. Account IDs, type IDs and creator IDs may mix signed and unsigned column types; validate exact types and semantics.
- Check that `ledgers.customer_ledger_id` linkage (SET NULL in dump) matches the immutable finance history policy.
- Eloquent `belongsTo` is NOT a database constraint. Eloquent soft deletes do not trigger SQL ON DELETE CASCADE.
- Approved financial vouchers should be voided/reversed using immutable postings; soft-deleting a ledger line alone can break trial balance.
- Avoid blindly making old `create` migrations current: keep existing DB upgrade migrations separate from a reproducible clean baseline.

## Next checks (open)
1. Extract reproducible matrix of **all 57 dump tables** and each migration's complete expected columns, field types, defaults, nullability, indices/FKs. Account for add/alter migrations separately.
2. Read-only *data* validation: orphans by every intended FK; duplicate voucher numbers by type/project; ledger vs customer-ledger reconciliation; JV debit/credit per document; inventory quantity/balance reconciliation.
3. Trace **every** relevant controller/service/repository write/delete and query-builder bulk mutation, including audit event bypass paths.
4. Formalize target schema versioning and data-only import mapping; rehearse only on isolated disposable MySQL with backups and explicit approval.
5. Security/activity audit remains a separate design phase.

## Explicit no-change record
No migrations executed; no database connection, import, schema updates, branch merge, or production edits. No customer rows or secrets committed.
