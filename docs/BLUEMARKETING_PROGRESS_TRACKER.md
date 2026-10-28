# BlueMarketing — Combined Development & Financial Integrity Tracker

Updated: 2026-10-28 (Asia/Karachi)
Branch: `upgrade/laravel-framework`

This tracker combines the earlier project-progress dashboard and the newer database `ledgers` integrity investigation. Progress figures are **planning estimates**, not verified release readiness. Never increment a number merely because a code file was inspected.

## Historical project-progress baseline

The earlier dashboard shown in the screenshot reported the following estimates:

| Track | Prior reported estimate | Current evidence/status |
| --- | ---: | --- |
| Overall application migration | 88% | Historical estimate only; deployment is blocked |
| Laravel 13 application | 98% | Feature suite previously passed, latest verification pending |
| Migration compatibility | 82% | Schema/history and DDL provenance unresolved |
| Financial reconciliation | 60% | Historical estimate; unverified JV/CV ownership and missing ledger rows remain |
| Database restore & connectivity | 100% | Local restricted clone was restored and read successfully; **not** production reconciliation |
| Client demo readiness | 55% | Financial correctness has not been certified |

**Dashboard note:** The local SQL clone reproduced Project 5 Hand Cash Balance of **-76,910** by subtracting Dasti Cash **83,910** from active ledger net **7,000**. This verifies the formula for that snapshot, **not** why an earlier displayed balance of 11 changed; no prior-image audit or independently proven historical delta is available.

## Current five-track implementation board (not a percentage of release readiness)

| Workstream | State | Concrete evidence and next acceptance gate |
| --- | --- | --- |
| Laravel 13 and isolated tests | Active / previously green | 417 Feature tests passed before Stock changes; current full suite blocked by Stock data-provider infrastructure error until rerun |
| Ledger write-path integrity | Partially implemented | JV and Labour number/date fixes, Mobile approval scope/field guards, web and mobile Stock fail-closed guard; source-specific integrations/concurrency unfinished |
| Source attribution, FKs and numbering | Design / blocked | Existing ledger FKs: charge_type_id and customer_ledger_id. `type_id` is shared; typed source FK design and schema compatibility await approval |
| Historical ledger reconciliation/recovery | Not authorized | Missing/inactive/duplicate/source mismatch inventory and independent audit evidence needed before any backfill or reversal |
| Deletion protection and deployment | Blocked | JV/CV destructive endpoints currently gated; append-only reversal and schema-aware SoftDeletes are future work; no production deployment |

## 2026-10-28 test-failure tracking

- User's earlier local Feature suite: **417 passed / 1,860 assertions**; PHP syntax clean and git clean after Labour fix.
- After Stock guard: **422 passed, 1 failed / 1,865 assertions**; failing test: `StockLedgerPostingGuardTest::test_conflicting_stock_posting_is_rejected`.
- Root cause: PHPUnit ran method with no arguments because its docblock data provider was not registered.
- Code fix pushed: explicit PHPUnit attribute `#[\\PHPUnit\\Framework\\Attributes\\DataProvider('conflicts')]` at commit `794fc543`.
- **Post-fix full suite and dataset expansion not yet independently verified**. Expected six conflict datasets must run. A failing assertion after the provider fix is a new separate finding, not to be hidden.
- No database operations, production deployment, migration or historical repair were performed.

## Working conventions

1. **ChatGPT implements via GitHub**; Codex provides independent review/inspection only, not unreviewed direct implementation.
2. User receives **one consolidated PowerShell command block per turn** for pull, isolated tests, syntax checks and git status.
3. Keep the restricted local clone SELECT-only; never run PHPUnit against it. No migration, seed, financial UPDATE/DELETE/INSERT on production/staging/clone.
4. Preserve `vendor.before-laravel13-incomplete/` locally, excluded through `.git/info/exclude`; never stage it.
5. Require passing local tests, database FK and source matching evidence, financial reconciliation and approved reversible recovery plan before deployment.
