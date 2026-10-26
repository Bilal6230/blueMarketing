# Phase 1G — CV update deletes unrelated JV ledger: reproduced

Date: 2026-10-26 | Branch: `upgrade/laravel-framework`
Status: **confirmed in isolated SQLite; no production data reviewed or altered**

## Execution evidence

User ran `php artisan test --filter=CommissionVoucherCollisionTest` on local Laragon after pulling `bd4bbf0`.

- Result: **1 failed, 3 assertions**, duration 2.27s.
- Test fixtures gave independently stored Commission Voucher and Journal Voucher the same header ID **41**.
- Original JV ledger record: `id=1, type='JV', type_id=41`.
- CV update returned redirect to commission index, then ledger assertion failed.
- Remaining ledger shown by PHPUnit: `id=2, type='JV', type_id=1`. Original JV ledger was **deleted**, and the new entry incorrectly referenced commission detail ID 1.
- This is an **executed collision regression** rather than a static source-pattern check.

## Risk and scope

Confirmed: updating a Commission Voucher can permanently delete an unrelated Journal Voucher ledger line if identifiers collide. This can distort account history and balances. The test **does not prove that production records were lost**.

Related previously confirmed issues:
- CV store and update write `type='JV'`.
- CV store uses header ID as `type_id`; CV update uses detail ID.
- CV and JV deletion controllers hard-delete general ledger history.

## Remediation gates (do not hotfix by string replacement)

1. Preserve original ledgers and prevent cross-voucher deletes; add blocked-update/void controls for posted financial entries.
2. Audit every reader and writer of `ledgers.type` / `type_id`, including voucher generation and financial reports; measure legacy ambiguous rows using a **disposable** restored database.
3. Define unambiguous posting source identity and an additive migration, dual-read compatibility plan, and controlled backfill with reconciliation.
4. Add tests for authorization, project boundaries, duplicate/overlapping IDs, atomicity, idempotence, and balanced reversal.
5. Run financial report reconciliation before/after isolated implementation; only then consider a reviewed production release.

## Data safety

No application controller logic changed. No migrations, MySQL imports, production writes or deployment performed. Keep `master_live` untouched. Avoid bulk UPDATE of legacy `JV` rows.
