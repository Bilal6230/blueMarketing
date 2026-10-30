# V2 functional delivery: central posting + project reports + physically aligned source models

Date: 2026-10-31. Branch: upgrade/laravel-framework.
Status: CODE DELIVERED; TEST EXECUTION DEFERRED BY OWNER.

## Completed engineering work

1. Central isolated V2 gateway: `V2PostingGateway` has 6 entrypoints: postJournal, postCash, reversePostedBatch, postingLines, trialBalance, accountStatement. It delegates to existing guarded JV, cash and immutable-reversal services. Not connected to V1 routes.
2. Real project trial balance: `V2ProjectTrialBalanceReader` computes opening, period debits/credits, and account closing in checked integer cents, with typed source checks and date-effective reversals.
3. Real account statement: `V2AccountStatementReader` gives project-scoped account opening, period, closing, running balance, exact typed source IDs, and reversal lineage. No `ledgers.type_id` inference.
4. V2 imported-source models: `ImportedHistoricalModel`, `ImportedJournalVoucher`, `ImportedJournalVoucherDetail`, `ImportedLedger`, `ImportedProjectAccount`. Original source foreign keys, exact decimal casts and dates; Eloquent instance save/delete blocked, Eloquent queries must pass V2 connection guard. Direct DB builders are separately governed.
5. Original V1 JournalVoucher `site_id` and Ledger `journal_voucher_detail_id` columns are absent from pinned source schema. Isolated V2 model adapters avoid inventing those columns; existing V1 classes are unchanged.
6. Regression test definitions authored for source model contracts, gateway, journal replay, reversal-aware trial balance, running statement and cross-project rejects. They have NOT been executed, per owner instruction.

## Implementation progress (NOT formal acceptance)

- V2 central gateway surface: 0/6 -> 6/6 operations.
- V2 report shapes: 1/3 -> 3/3 (posting lines, project trial balance, account statement).
- Known source model missing-column adapter conflicts: 0/2 -> 2/2.

## Formal gate status

Formal roadmap remains 8/24 = 33.3%. P3.G4 requires complete compatibility acceptance; P5.G1 requires concurrency, approval and workflow integration; P5.G3 requires full UI/export/API refactor. P4.G3 is blocked on original UTC instant mismatch in existing local rehearsal (41/57 table digests only match +05:00). Code alone cannot close gates.

## Safety and later verification

No V1 route was changed; no DB, migration, data, production, live, client, or staging state was modified. End-stage tests: PHP lint, SQLite regression, real MySQL concurrency, finance parity, permissions and owner approval. No tests were run this delivery.
