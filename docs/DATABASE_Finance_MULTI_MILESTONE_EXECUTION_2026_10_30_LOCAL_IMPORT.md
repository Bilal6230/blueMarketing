# BlueMarketing Database V2 — Multi-milestone implementation & local handoff (2026-10-30)

**Branch:** `upgrade/laravel-framework`. Source: private MariaDB dump `u429032065_dev.sql` with SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`. This change set does NOT connect to or modify the user's local, staging or production databases.

## Delivered milestone 1: Web + mobile financial approval bypass protections

Added `app/Services/Finance/FinancialPendingChangeGuard.php`, used by both:
- `app/Http/Controllers/Finance/VoucherController.php::approveFinancePending()` (financial pending approval).
- Legacy generic `VoucherController::approve()` now **delegates financial table updates** through the permission/lock/central safe approval flow; no raw `DB::table(...)->update($pendingNewChanges)` for `ledgers`, `draft_ledgers`, `customer_ledger` from that legacy route.
- `app/Http/Controllers/Api/V1/Mobile/MobileApprovalController.php::applyPendingUpdate()`: before its generic Query Builder update it calls the same guard for finance tables.

Posted `ledgers` allow only `detail` narrative change; posted `customer_ledger` allows only `description`/`note`. Financial amount, date, project, account, `type_id`, source and activation status changes require an audited reversal path. `draft_ledgers` continues to allow draft business edits except identity/project/creator/source/timestamp changes. Empty/malformed payloads fail closed. Existing approval authorization/locking still applies.

New web approval regression tests (financial mutations reject with 409, safe descriptive edit retains 302) and unit tests for the shared policy are included in `FinanceApprovalWorkflowTest` and `FinancialPendingChangeGuardTest`. **Production finance controller changes were scoped narrowly to these approval safeguards.** No automatic posted ledger mutation.

## Delivered milestone 2: Exact-source V2 CR/CP posting service (pilot, not yet wired)

`app/Services/Finance/V2CashPostingWriter.php` posts only into isolated V2 DB and requires explicit account allocations. Its actual source FK is `finance_posting_cash_sources.customer_ledger_id -> customer_ledger.id` and canonical source kind `cash`, not `type_id` or `reference`. It checks active CR/CP source, same-project accounts, strict cents-based debit/credit balance equal to source amount, transaction/locks, request-key idempotency, unique-source protection and posted replay integrity. Five SQLite tests in `V2CashPostingWriterTest` cover CR/CP, cross-project, tampering and double-post rejection.

**Safety:** no legacy `LedgerController`/booking/labour/stock route calls this new service. Full migration of those families and audited reversal implementation remain OPEN. These tests must be validated on CI before claiming application acceptance.

## Delivered milestone 3: Independent original-data financial fingerprints & MySQL read-only reconciliation

- `tools/database_v2/financial_source_fingerprints.py` reads the original private dump, validates exact 57-table/120,165-row source SHA, and writes **aggregate-only private evidence** (per-table row counts, primary-ID bounds, original created/updated/deleted non-null and date extrema, Decimal sums of numeric amount/credit/debit/balance/price fields) **outside Git**.
- `tools/database_v2/verify_imported_mysql_financial.php` verifies real imported isolated MySQL data against that original-source evidence: all 57 table row counts, PK bounds, historical timestamp extrema and non-null counts and numeric sum/non-null parity. Verification is read-only and fails closed. It does NOT claim to replace full-column row-by-row comparison or JV source attribution.
- Local offline scan against actual private source independently found **34 numeric financial-like schema columns** and **zero non-numeric money values**, zero unsigned-negative source values, zero varchar-width overflow and zero decimal precision overflow. No individual customer/financial source values committed.

**Pending:** user's original 120,165 rows need to be imported to isolated local MySQL to execute the verifier against real target data. Previous CI verified the import runner with 57-table synthetic data and rollback, not this private file.

## Delivered milestone 4: Windows/Laragon two-stage local-only operator workflow

`tools/database_v2/local_private_rehearsal.ps1` requires:
1. source SQL path and verified exact SHA, staging directory outside repo; Stage 1 generates 57 sensitive SQL segments, manifest and private fingerprint evidence with **no DB connection**;
2. explicit user `-Apply` plus exact `-Database`/`-ConfirmEmptyDatabase`, local-only PHP target preflight (`tools/database_v2/check_local_v2_target.php`), **new empty `bluemarketing_v2_local_*` DB**, existing `.env` unchanged, three opt-in V2 migrations only, 27 bank IDs seeded, FK-enabled transactional real import, and read-only financial evidence parity check.

The script **does not create/drop a database**, truncate, or run `migrate:fresh`. No retries/cleanup on error, no production connections. The user must manually create a NEW empty local DB using phpMyAdmin. See [exact commands and safe expectations](DATABASE_V2_LOCAL_MYSQL_IMPORT_RUNBOOK_2026_10_30.md).

## Delivered milestone 5: Source zero TIMESTAMP exception discovered and preserved explicitly

Real private SQL compatibility analysis found **one** `leads.follow_up = 0000-00-00 00:00:00` TIMESTAMP. Default strict MySQL 8.4 may reject that value. The isolated importer now **refuses** real-data import unless the user explicitly sets `-PreserveLegacyZeroDate`; it checks private fingerprint evidence proves exactly this exception, removes `NO_ZERO_DATE` and `NO_ZERO_IN_DATE` from **its own session SQL mode only**, preserves that exact historical value, validates the single value survived and restores the original session SQL mode on success/failure. Global/server SQL mode is NEVER changed. CI includes a disposable MySQL zero-date session compatibility probe.

**Important:** Retaining a zero TIMESTAMP does not establish that future Laravel/Carbon workflows support it. Must be resolved through owner-approved mapping (possibly raw-value evidence + nullable normalized date) **before production V2 cutover**. Never silently rewrite a legacy timestamp.

## Acceptance and gating

The formal progress remains **8/24 accepted gates = 33%**. This turn delivers substantial safe code, tests and a local operator runbook; it does NOT complete the full production integration/financial cutover gate. No private SQL/financial customer row committed. GitHub CI V2 tests and MySQL jobs are being evaluated; distinguish independently green V2 stages from the existing older finance-safety regression failure. Real MySQL historical import cannot be executed in the connected GitHub/ChatGPT environment without violating privacy; local owner action is the next dependency.
