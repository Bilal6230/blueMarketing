# JV forensic recovery assessment — 2026-10-29

Source: user-supplied `bluenew.sql` dump. Analysis was offline, read-only. **Not a live database reconciliation or a repair.**

## Evidence from snapshot

- 991 `journal_vouchers` headers, 988 of type `JV`, 980 active (not soft deleted) type-`JV` headers; 5,147 detail rows; 17,855 ledger rows.
- **84 active `JV` headers have no rows in `ledgers` where `type='JV'` and `type_id=journal_vouchers.id`.** The 84 headers carry **272 nondeleted detail rows** that lack a matching same-account, same-amount debit/credit ledger leg.
- Unposted detail debit and credit sums are each **PKR 4,941,032.00**. Globally balanced does not mean project/account balances are correct. Distribution of unposted JV headers: Project 3: 11; Project 5: 10; Project 6: 63.
- Sample JV IDs: 9, 14, 15, 25, 59, 113, 150, 158, 169, 301. The full snapshot remains the only authoritative source for row details.
- JV detail-level debit and credit sums matched nondeleted JV header sums in this check (zero header mismatches); no detail-account project discrepancy was found in this check.
- 80 distinct `ledgers.type='JV'` nonnull `type_id` values do not name any `journal_vouchers.id`. **Do not call these orphaned without disambiguating stock and labour polymorphic posting.**
- Earlier all-project financial audit found 101 positive `customer_ledger.type_id` collisions in CR; posted ledger voucher identities may differ from those source type IDs. No global source-number UNIQUE is safe on this history.

## Recovery acceptance gates

1. Keep all current source and ledger rows immutable until accounting owners approve an evidence packet, snapshot checksum, verified full DB backup and tested restore on isolated disposable database.
2. Classify missing JV ledgers by posting pathway and authorization, not merely by `type_id`. Reconcile each source detail, related account, ledger history, voucher reference, event date, deleted status and existing reversal/clearance. Reject ambiguous or already-posted cases.
3. Design **explicit provenance** for new journal postings, e.g. a nullable `ledgers.journal_voucher_detail_id` with a real FK to details, after proving legacy schema and one-to-one/split cardinality. Keep old rows NULL unless reconciled. `ledgers.type_id` cannot be FKed to `journal_vouchers` as a polymorphic owner.
4. In code, ensure atomic project-locked posting, balanced detail checks, unique idempotency identity and ledger/source provenance before commit. Keep destructive JV update/delete fail-closed.
5. Only after approval: create a **separate** transactional, idempotent, per-JV repair command with dry-run default, explicit allowlist, exact before/after account deltas, anti-double-posting checks and rollback/reconciliation tests. No automatic run against user database.
6. For future hard constraints, prefer explicit nullable provenance FKs and an idempotency key for newly posted legs over a blanket historical `customer_ledger(project_id,transaction_type,type_id)` or reference unique key. Backfill historic source keys only with proof.

## Not yet proven

External cash/bank actual balances; full JV stock/labour attribution; 84 JV eligibility for reinstatement; all raw SQL writer protections; end-to-end production migration safety. P0.1 remains **98% estimated** rather than certified 100%.
