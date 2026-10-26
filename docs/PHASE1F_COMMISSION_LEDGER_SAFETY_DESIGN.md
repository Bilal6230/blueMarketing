# Phase 1F — Commission Voucher Ledger Identity & Safety Design

Date: 2026-10-26  
Branch: `upgrade/laravel-framework`  
Status: **read-only design and verified red tests; no production changes**

## Verified test results

User executed:
```
php artisan test --filter=CommissionVoucherUpdateIsolationTest
Tests: 4 failed (16 assertions)
```
All four failures are expected source-contract RED findings, not successful behavioral regression tests:
1. Commission voucher **store** currently creates Ledger rows with type `JV` instead of a distinguishable commission source.
2. Commission voucher **update** creates Ledger rows with `type='JV'`.
3. Commission voucher **update** deletes `Ledger(type='JV', type_id=$id)` (not safe in the presence of same-ID journal vouchers).
4. Commission voucher **update** populates `type_id` from `CommisionVoucherDetail->id`, while `store` uses `CommisionVoucher->id`.

Earlier SQLite controller tests also confirmed JV and CV delete paths remove general-ledger rows (2 failures / 2 assertions). None of this proves historical production data loss.

## A critical nuance before fixes

The current live conventions cannot be assumed from the names alone:
- `store`: `type='JV'`, `type_id=commission voucher header ID`.
- `update`: `type='JV'`, `type_id=commission voucher detail ID`.
- `destroy`: attempts deleting `type='CV'`, `type_id=commission voucher header ID`.
- A journal voucher also uses `JV` entries. IDs of two tables are independent and can overlap.
- Therefore a naive update to `where type='CV'` or a bulk conversion of legacy `JV` records can silently orphan existing entries, misattribute reports, or change balances.

## Safe target architecture (proposal, not yet implemented)

- Ledger posting source must be unambiguous: e.g. immutable `source_type` and `source_id` typed against *header* entity, with optional `source_line_id`; preserve legacy `type` / `type_id` until all readers are upgraded.
- A posted voucher may only be **voided with balanced compensating entries**. Do not delete original ledger rows/details.
- Void operations must require authorization, explicit reason, user, timestamp, voucher reference, idempotency and row locks.
- Prevent cross-project mutation, avoid double reversal, and preserve original figures for reports and audit.
- Draft-only editing should be constrained by status, with atomic replacement/history policy. Posted vouchers must use adjustment/reversal, not replacement.
- Any new column/migration requires approved additive migration and a separate fresh-install baseline. No automated data backfill without per-row reconciliation.

## Next isolated regression gates

1. Use in-memory SQLite fixture with same numeric ID assigned to independent JV and CV headers; create different journal and commission ledger lines.
2. Assert a CV update does not delete, relabel or affect JV-origin ledger rows even when IDs collide.
3. Assert source identity for new CV postings is consistent across store and update.
4. Assert partial failure rolls back full transaction and no duplicate postings occur on retry.
5. Test non-zero balanced reversal with mixed debit and credit, void-once idempotence, and report totals before/after.
6. Analyze all queries that interpret `ledgers.type/type_id` before changing convention; use isolated restored dump to quantify ambiguous records.

## Safety constraints

- No real MySQL database access, migrations, imports, backfills, or code/controller behavior changes authorized by this design.
- The four current tests are deliberately RED. Full integration behavior remains unverified.
- Do **not** deploy either test-driven correction directly to `master_live` without comprehensive test and reconciliation sign-off.
