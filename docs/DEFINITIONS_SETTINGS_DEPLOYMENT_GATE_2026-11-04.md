# Definitions & Settings deployment gate

This file records the engineering checks for the 2026-11-03/04 changes. Browser QA belongs to the client. It does not indicate that the application database has been migrated.

## Current gate

| Check | Result |
|---|---|
| Stock import project isolation, replay, unauthorized user, oversized file | Passed on SQLite `:memory:` |
| Stock web/mobile creation, approval, rollback and account scope | 46 V2 workflow tests passed, 352 assertions |
| Payroll link: approved run, posted same-project CP, idempotency and allocation limits | Passed on SQLite `:memory:` |
| Active/foreign accounting account rejection and audit rollback | Passed on SQLite `:memory:` |
| Blade compilation and route registration | Passed |
| Isolated MySQL clone migration rehearsal | All 31 repository DS migrations are recorded as applied on disposable clone `codex_ds_rehearsal_20261104`; no missing or unexpected DS migration-history entries. The clone required the previously documented evidence-based mapping for 151 labour masters; 14 remain unscoped. |
| Historical-table reconciliation | Passed against a restored pre-migration source snapshot: 69 historical tables / 120,192 compared row records; zero missing tables, missing columns, row-count mismatches, or unexpected content mismatches. `migrations` is bookkeeping and excluded. Expected changes were isolated and verified: 151 reviewed `labours.project_id` assignments (14 remain null, 0 invalid project links) and the three DS payroll permissions plus their three `superadmin` links. |
| Financial totals | `ledgers` 18,003 rows (in 1,476,647,756.25; out 1,276,102,791.25) and `customer_ledger` 5,580 rows (in 2,663,018,587.25; out 1,460,129,450.00) remain unchanged. |
| Client browser acceptance | Excluded from engineering scope |

## Safe rehearsal order

1. Restore a fresh, disposable clone of the target database to a separately named MySQL schema. Record the source backup timestamp and target schema name. Never point the application `.env` at the production schema for this rehearsal.
2. Compare `migrations` history and table structure against the target. This repository has legacy migrations reported pending even though their tables already exist. Do not run unrestricted `php artisan migrate` on that database. Resolve history separately using the existing structural preflight and reconciliation manifest.
3. On the clone, apply the Definitions & Settings migrations in filename order **only after** all earlier dependencies and data checks pass. This includes the project stock definitions migration `2026_11_03_000024` and payroll link migration `2026_11_04_000001`.
4. Before and after each migration, capture row counts for `ledgers`, `customer_ledger`, `journal_vouchers`, `stock_items`, `stock_entries`, `stock_bills`, `stock_movements`, and approved payroll runs. Capture exact decimal sums of ledger `amount_in`/`amount_out` and customer-ledger `amount_in`/`amount_out` by project and type; compare unchanged sums and IDs. New definition/link tables may gain rows, but historical financial rows must not change.
5. Verify FK/index presence, project ownership of all stock definitions, unchanged global stock item IDs, and no duplicate `(project_id,item_id)` definitions. Confirm that no `employee_payroll_payment_links` row exists until an authorized CP link is made.
6. Re-run the focused automated gates against the code and perform the client browser QA against the clone. Promote only after the reconciliation is exact. If a gate fails, restore the disposable clone and investigate; do not improvise rollback against the live financial tables.

## Deployment limitation

The isolated clone `codex_ds_rehearsal_20261104` was restored from `bluemarketing_v2_local_rehearsal (1).sql` (backup dated 2026-10-31). Four migration portability issues were found and fixed in code: two self-referencing composite FKs were created before their unique indexes, the attendance record lacked its composite referenced key, and the contractor migration assumed `journal_vouchers.site_id` existed. DS-14 now gives a clear preflight error for unscoped labour masters. Initially, 165 `labours` had NULL `project_id`; 627 `labour_ledgers` referenced 151 of those masters. On the disposable clone only, 151 masters were mapped to their unique project evidence from existing ledger rows. Fourteen unlinked masters remain unscoped. This clone repair allowed the rest of the migration chain to pass, but it is not an automatic production data repair. Source-of-truth review and a reviewed repair script are required before live migration. The application database was not touched.

## 2026-11-04 complete historical reconciliation

- Restored `D:\D Dwonloads\bluemarketing_v2_local_rehearsal (1).sql` (18,486,327 bytes; SHA-256 `2C371F38120604B27D65C36C6484BBAD75E77FB25503370237FC687630C0F99B`) into a new local-only pre-migration baseline schema. The source file's dump header dates it 2026-10-31.
- Compared every original non-bookkeeping table to the migrated clone by row count and sorted SHA-256 row digests over the original columns. Result: 69 historical tables, 120,192 row records covered, no missing tables/columns, row-count mismatches, or unexplained content mismatches. The `migrations` table was excluded as migration bookkeeping.
- Explicitly isolated DS-19's expected additions: all three payroll permissions and all three `superadmin` permission links are present; remaining permission rows and role mappings match the source baseline.
- Explicitly isolated the already reviewed disposable-clone data repair: exactly 151 NULL `labours.project_id` values became valid project references; 14 remain NULL and zero project references are invalid. All other original labour columns and row counts match.
- Verified all 31 DS migrations present in this checkout have matching applied-history rows in the post-migration clone (31 applied, 0 missing, 0 unexpected). Prior ledger/customer-ledger financial row-count and sum reconciliation remains exact.
- Reusable read-only comparator: `tools/reconcile_ds10_mysql_clone.php`. It requires explicit local disposable schema names and emits only aggregate counts/table names, never business rows. The temporary imported baseline schema was removed after reconciliation; the existing migrated rehearsal clone was left untouched.
- This closes the historical-table reconciliation gate for the recorded 2026-10-31 snapshot and current clone. It does not certify production data or substitute for deployment backup/reconciliation and client browser acceptance.

The new payroll link table and project stock definition table are additive. Their `down()` methods retain historical evidence. Restoration from a verified backup is the rollback path if deployment fails. The application database has not been modified in this engineering turn.
