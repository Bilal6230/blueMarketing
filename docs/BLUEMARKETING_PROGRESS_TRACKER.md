# BlueMarketing — Combined Development & Financial Integrity Tracker

Updated: 2026-10-28 (Asia/Karachi)
Branch: `upgrade/laravel-framework`

This tracker combines the earlier project-progress dashboard and the newer database `ledgers` integrity investigation. Progress figures are **planning estimates**, not verified release readiness. Never increment a number merely because a code file was inspected.

## P0 — PRIMARY DELIVERY ROADMAP: DATABASE `ledgers` INTEGRITY

**These five workstreams supersede the Laravel upgrade, demo, Hand Cash investigation, and generic testing as active priorities.** The older project baseline below is *secondary*, not a release gate. Progress figures below are approximate implementation/validation coverage, **not** historical financial correctness, and must not be treated as certified completion.

| Rank | Primary task | Estimated progress | Implemented evidence | Remaining acceptance gate |
| --- | --- | ---: | --- | --- |
| P0.1 | **Ledger protection:** audit and fix every live INSERT/UPDATE/DELETE/deactivate/overwrite, project scope and identity guard | **40%** | Mobile approval identity/amount/date block and project scope; existing JV/CV mutation gates; Stock duplicate/inactive guard | Every producer/mutator classified and tested, protected ledger rows cannot silently disappear |
| P0.2 | **Voucher integrity:** propagate correct source voucher number/date for JV, CV, Stock, Labour, Booking, CR, CP, BR, PPR | **30%** | Core JV header number, Labour JV number/date, Stock existing-posting date and amount checks | Every source creates ledger rows with verified source number/date, regression tests for all families, preexisting mismatch inventory |
| P0.3 | **Foreign-key / typed source identity:** source-aware, project-consistent relational integrity | **15%** | Existing physical `customer_ledger_id` and `charge_type_id` FKs confirmed on restored clone; typed source attribution design exists | Per-source FK/typed links, uniqueness & concurrency checks, vetted migration rehearsal; do **not** force `type_id` into one FK |
| P0.4 | **Soft-delete / append-only reversal:** protect historical business records and posted financial events | **15%** | JV/CV destructive write gates and CR/CP/PPR deletion tests exist | Cover all applicable business tables and side effects; financial reversals/append-only protection; MySQL schema review |
| P0.5 | **Historical recovery:** reconcile missing/inactive/overwritten JV and other ledger entries, then controlled repair | **10%** | 17,855 restored ledger rows inventory; 155 inactive; source ambiguity evidenced; Project 5 balance reproduced | Independently prove provenance per row, compare older backup/audit logs, approved dry-run, reversible repair and balance validation |

### Primary delivery sequencing

1. **Prevent new data loss** first: finish source-specific mutation guards, database authorization, concurrency and comprehensive regression tests.
2. **Fix new voucher/date propagation** for every ledger-producing module. Compare source and ledger fields; never rewrite historical dates silently.
3. **Introduce source-aware FKs/constraints** through tested, backup-first migrations on an approved disposable environment; maintain compatibility with existing overloaded `JV` rows.
4. **Implement safe deletion/reversal** across modules without blanket soft-deletes on posted journal rows or destructive migration rollbacks.
5. **Repair historical rows last**, after ownership evidence and explicit authorization; no balance plug or guessed `type_id`.

### 2026-10-28 P0.1 / P0.2 new implementation checkpoint

- Implemented a **fail-closed linked customer-ledger date/activity check** in `StockLedgerPostingGuard`. Existing stock `ledgers` legs may only be considered already posted if the related `customer_ledger` entry shares the original business date and is active. Previously the guard checked its project, reference and transaction type, but not its date/activity.
- Added two SQLite cases for wrong `customer_ledger.date` and inactive `customer_ledger.is_active`; expanded synthetic fixture columns. GitHub commits `d935b2a`, `9386532`.
- **Not yet locally verified:** rerun the focused Stock regression and full Feature suite; latest previously verified baseline remains **428 passed / 1,871 assertions**.
- P0.1 remains estimated **40%**, P0.2 remains **30%** until these tests pass and other writer families receive coverage. No percentage increase for unverified implementation.
- No MySQL database, source attribution, migration, recovery or production deployment operation.

### Latest verified testing checkpoint

- User's focused `StockLedgerPostingGuardTest`: **11 passed / 11 assertions**, six conflict datasets executed.
- User's full isolated Feature suite: **428 passed / 1,871 assertions**; working tree clean after pull at `c524251`.
- The earlier `@dataProvider` failure is **resolved and verified**, not an open blocker.
- These synthetic SQLite tests **do not** certify MySQL concurrency, original voucher provenance or production safety.

## Historical project-progress baseline

The earlier dashboard shown in the screenshot reported the following estimates:

| Track | Prior reported estimate | Current evidence/status |
| --- | ---: | --- |
| Overall application migration | 88% | Historical estimate only; deployment is blocked |
| Laravel 13 application | 98% | Full isolated Feature suite 428 passed / 1,871 assertions on 2026-10-28 |
| Migration compatibility | 82% | Schema/history and DDL provenance unresolved |
| Financial reconciliation | 60% | Historical estimate; unverified JV/CV ownership and missing ledger rows remain |
| Database restore & connectivity | 100% | Local restricted clone was restored and read successfully; **not** production reconciliation |
| Client demo readiness | 55% | Financial correctness has not been certified |

**Dashboard note:** The local SQL clone reproduced Project 5 Hand Cash Balance of **-76,910** by subtracting Dasti Cash **83,910** from active ledger net **7,000**. This verifies the formula for that snapshot, **not** why an earlier displayed balance of 11 changed; no prior-image audit or independently proven historical delta is available.

## Current five-track implementation board (not a percentage of release readiness)

| Workstream | State | Concrete evidence and next acceptance gate |
| --- | --- | --- |
| Laravel 13 and isolated tests | Active / previously green | 428 Feature tests passed / 1,871 assertions after Stock provider fix; additional integration and MySQL rehearsal pending |
| Ledger write-path integrity | Partially implemented | JV and Labour number/date fixes, Mobile approval scope/field guards, web and mobile Stock fail-closed guard; source-specific integrations/concurrency unfinished |
| Source attribution, FKs and numbering | Design / blocked | Existing ledger FKs: charge_type_id and customer_ledger_id. `type_id` is shared; typed source FK design and schema compatibility await approval |
| Historical ledger reconciliation/recovery | Not authorized | Missing/inactive/duplicate/source mismatch inventory and independent audit evidence needed before any backfill or reversal |
| Deletion protection and deployment | Blocked | JV/CV destructive endpoints currently gated; append-only reversal and schema-aware SoftDeletes are future work; no production deployment |

## 2026-10-28 test-failure tracking

- User's earlier local Feature suite: **417 passed / 1,860 assertions**; PHP syntax clean and git clean after Labour fix.
- After Stock guard: **422 passed, 1 failed / 1,865 assertions**; failing test: `StockLedgerPostingGuardTest::test_conflicting_stock_posting_is_rejected`.
- Root cause: PHPUnit ran method with no arguments because its docblock data provider was not registered.
- Code fix pushed: explicit PHPUnit attribute `#[\\PHPUnit\\Framework\\Attributes\\DataProvider('conflicts')]` at commit `794fc543`.
- **RESOLVED:** post-fix 11 focused tests / 11 assertions and 428 Feature tests / 1,871 assertions passed; all six datasets were executed.
- No database operations, production deployment, migration or historical repair were performed.

## Working conventions

1. **ChatGPT implements via GitHub**; Codex provides independent review/inspection only, not unreviewed direct implementation.
2. User receives **one consolidated PowerShell command block per turn** for pull, isolated tests, syntax checks and git status.
3. Keep the restricted local clone SELECT-only; never run PHPUnit against it. No migration, seed, financial UPDATE/DELETE/INSERT on production/staging/clone.
4. Preserve `vendor.before-laravel13-incomplete/` locally, excluded through `.git/info/exclude`; never stage it.
5. Require passing local tests, database FK and source matching evidence, financial reconciliation and approved reversible recovery plan before deployment.
