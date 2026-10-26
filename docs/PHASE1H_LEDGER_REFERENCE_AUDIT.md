# Phase 1H — Ledger Source-Identity Readers/Writers Audit

Date: 2026-10-26 | Branch: `upgrade/laravel-framework` | Read-only targeted audit

## Verified code-level dependencies

| File | Approx lines | Observation / compatibility implication |
|---|---:|---|
| `JournalVoucherController.php` | 379–383, 441, 512–537 | JV store writes `type=JV,type_id=journalVoucher.id`. JV destroy hard-deletes matching ledger; JV update looks up `type=JV` against **header ID OR detail IDs**; dangerous when type IDs are not unique across identity domains. |
| `CommissionVoucherController.php` | 176–181, 240, 308, 320–323 | CV store writes `type=JV,type_id=CV header ID`; CV destroy searches `type=CV`; CV update deletes `type=JV,type_id=CV header ID` and writes `type=JV,type_id=CV detail ID`. |
| `LedgerController.php` | 1136–1137 | Resolves `type=JV` ledger records by `JournalVoucher::where('id', ledger.type_id)`. Misclassified CV ledger can resolve to unrelated JV voucher when IDs overlap. |
| `AccountingController.php` | 151–170, 739–745 | Displays/aggregates ledgers by account/project. Posting changes require before/after reconciliation on report amounts. |
| `AppHelper.php` | 313–345 | Ledger ID/number helpers filter by `type` and `voucher_number`; changing `type` may change number sequencing; review project scope and race handling. |
| `BookingSalesAccountingResolver.php` | 92–104 | Treats ledger `type_id` as voucher header reference in booking flows. Changing meaning globally is unsafe. |
| `LedgerController.php` | 1266–1274 | Financial totals sum active ledger rows; reversal strategy and active-status semantics must be report compatible. |

## Verified RED regression evidence
- `CommissionVoucherCollisionTest`: CV and JV header IDs both 41, old JV ledger `id=1, type=JV, type_id=41` removed on CV update; new `id=2, type=JV, type_id=1` remains. 1 failed / 3 assertions (intentionally red).
- Four static CV source guards red. Earlier JV/CV deletion safety tests red in SQLite.

## Proposed next gate
1. Trace every ledger `type/type_id` reader/writer, including views, exports, queries, controller routes, API reporting and scripts; current table above is **targeted, not exhaustive**.
2. Inventory staged SQL records by ledger `type`, overlapping `type_id`, voucher header/detail ID mapping; classify ambiguity without assuming every overlap is wrong. Treat SQL dump as sensitive and never commit rows.
3. Design backward-compatible canonical source mapping; prohibit destructive financial voucher edit/delete until history-safe posting strategy tested.
4. Test voucher detail and header collisions for JV update as well as CV update, plus report totals before/after voiding.
5. Do not do bulk type conversions or migrate production data without approved isolated rehearsal and reconciliation.

No controller/business-logic change, database write, migration or `master_live` change.
