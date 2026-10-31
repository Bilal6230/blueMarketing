# BlueMarketing V2 — 24-gate execution checkpoint

Date: 2026-10-31. Branch: `upgrade/laravel-framework`. No production/owner DB changes.

## Ground truth

- Baseline before this batch: 8/24. **Current verified acceptance: 9/24 = 37.5%**. Exactly 15 gates remain OPEN. P5.G1 accepted only for the isolated V2 central posting engine after real MySQL concurrency and complete CI evidence.
- Verified current code milestone: GitHub Actions [run #762](https://github.com/Bilal6230/blueMarketing/actions/runs/41175650122) **both jobs SUCCESS**. finance-safety: **733 tests, 3,127 assertions PASS**. Disposable MySQL 8.4: new 2-process project-lock/idempotency proof, JV/SV exact source FK, immutable reversal and synthetic cleanup PASS.
- Prior operator-verified isolated SQLite suites: 69/69 finance/module tests (274 assertions) plus 9/9 HTTP tests (44 assertions). Full 24-gate project acceptance is not implied by those suites.
- P4.G3 remains hard blocked: 41/57 original source table UTC TIMESTAMP parity discrepant in existing local V2 rehearsal. That DB must remain intact as evidence.
- Original full historical source private SQL (57 tables / 120,165 rows) and its independent digest/evidence are on the owner's machine, not in the repository. Original source owner authorizations/signoffs cannot be performed by CI.

## New implemented milestones in this batch (verified by run #762)

1. **Actual MySQL finance execution:** `tools/database_v2/probe_mysql_journal_posting_disposable.php` now seeds an isolated synthetic project/account/user/JV fixture into the exact disposable MySQL 8.4 `bluemarketing_v2_ci`; executes real V2 posting, exact detail FK proof, identical-key replay, different-key refusal, immutable reversal, checked closing balances, and safe child-first cleanup with foreign keys enabled. It also tests sales `SV` from actual journal header/detail IDs. CI invokes the script after 72-table migration and bank seed, before clean rollback/import stages. This is stronger than the existing bank row-lock probe, but **does not substitute for two simultaneously executing financial writer processes**.
2. **Sales `SV` source typing:** `V2JournalPostingWriter::post(..., $expectedSeries='JV')` now supports exactly JV/SV journal types without changing original JV idempotency hashes. New `V2PostingGateway::postSalesVoucher()`; disabled-by-default pilot HTTP `kind=sales`; regression cases for typed source FK, wrong-kind refusal, replay and preserved text/numeric voucher identity. `booking_vouchers.id` is never guessed as the SV journal identity.
3. **Expanded real MySQL model contract:** `tools/database_v2/verify_mysql_v2_model_schema.php` now validates 3 typed posting models plus 4 source-aligned V2 historical models, 13 relationship contracts, 8 DECIMAL(?,2) casts, schema absence of fabricated `journal_vouchers.site_id` and `ledgers.journal_voucher_detail_id`, source read-model mass-assignment protection and idempotency unique constraints.
4. **Controlled API errors:** Domain-level source/kind/financial reversal conflicts are now HTTP **409** with generic error codes rather than uncontrolled HTTP 500, preserving transaction rollback and no private SQL disclosure. HTTP negative test checks `SV` cannot be posted as `JV`.

All related changes are isolated V2 application code, disposable CI scripts and regression definitions; no existing V1 controller was switched, no migration/import executed against owner's DB, and no external/private data was copied.

## Remaining acceptance gates — complete 15/15 list

| Gate | Missing evidence/implementation required for acceptance |
|---|---|
| P1.G3 | Exhaustive semantic CRUD/ledger writer/reader call graph including dynamic DB builders, Blade, routes, jobs, modules and SQL; prove no unclassified posting path |
| P1.G4 | Owner-approved source ownership/architecture decision register, including ambiguous `type_id` and project mismatches |
| P2.G3 | Table-specific delete/void/immutable reversal and legacy URL/ID compatibility policy signed off |
| P2.G4 | Approved original 57-table V1→V2 column/exception policy and constraint map |
| P3.G4 | Full model/cast/relationship compatibility across all source families, with final clean MySQL acceptance; expanded CI code is only a partial verification |
| P4.G1 | Immutable checksum-verified source backup and approved historical exception handling |
| P4.G3 | Fresh, *separately authorized* isolated MySQL import preserving original source UTC TIMESTAMP instants, 57/57 original row digests and archive history |
| P4.G4 | Source→target full-record, FK, ledger line, account/project cash, duplicate/orphan financial reconciliation |
| P5.G2 | All real voucher controller workflows, including JV/CV/CR/CP/PPR/EC/ECP/SV/BR, mapped and accepted without guessed source IDs |
| P5.G3 | Booking/stock/labour, mobile/web routes, background jobs, Blade reports and exports switched safely to V2 with approvals |
| P5.G4 | Exhaustive V2/V1 coexistence read/write, wrong-project, rollback, duplicate, concurrent posting and workflow tests |
| P6.G1 | Independent full 120,165-record V1/V2 row-level parity across all business tables |
| P6.G2 | Independent signed document/ledger and cash/bank/project financial reconciliation, including ~74 historic JV source exceptions |
| P6.G3 | Complete browser/mobile/API/report/security/performance acceptance on isolated full-copy data |
| P6.G4 | Owner-approved backup, restore, cutover, rollback and separate explicit deployment authorization |

**P5.G1 ACCEPTED (isolated V2 central posting only), leaving 15 OPEN. No claimed 24/24.** P4 source provenance, policy sign-off, financial reconciliations, complete production UI migration and UAT cannot be inferred or auto-approved through GitHub CI. Production deployment remains blocked.

## Next execution order

1. Keep the verified #762 V2 central engine as the baseline; extend typed source ownership and integration to real legacy web/mobile controllers with explicit approvals.
2. Complete remaining P1.G3 semantic call graph and P3.G4 model contracts; implement business-approved V2 posting for remaining source kinds and all web/mobile routes without activating production.
3. Owner supplies separately authorized fresh UTC import target and backup evidence; run read-only comparisons first, then approved migration on ONLY new isolated target.
4. Reconcile document-level finance and every source table; perform real concurrency/UAT and obtain P2/P4/P6 sign-offs. Move formal bars only as proofs are accepted.
