# Phase 1K — Financial Posting Safety Remediation Blueprint

Date: 2026-10-26 | Branch: `upgrade/laravel-framework` | **Design gate; no business-code or DB changes**

## Evidence (isolated SQLite tests executed by developer)

1. `FinancialHistoryPreservationTest`: **2 RED** — `JournalVoucherController::destroy` and `CommissionVoucherController::destroy` permanently remove matching `ledgers` rows.
2. `CommissionVoucherUpdateIsolationTest`: **4 RED** source guards — CV creation and update use JV type; CV update deletes JV rows and uses detail ID rather than header ID.
3. `CommissionVoucherCollisionTest`: **1 RED** — CV header 41 update deletes unrelated JV ledger `id=1,type=JV,type_id=41` and creates new `JV,type_id=1` ledger.
4. `JournalVoucherCollisionTest`: **1 RED** — JV header 41 has detail ID 88; update deletes unrelated JV ledger `id=1,type=JV,type_id=88` belonging to independent header 88.

These are tested behaviors in disposable fixtures, not evidence of production corruption.

## Immediate safety guard options (need code-review before implementation)

**Preferred short-term approach: fail closed on dangerous updates/deletes, with explicit user-facing messages**, rather than trying to infer mixed historical ledger identities.

- Block JV/CV `destroy` whenever voucher has been posted; return a controlled error explaining that void/reversal is not yet available.
- Block financial-line editing for posted JV/CV until a safe posting API exists. Allow only non-financial draft metadata under strict authorization and unchanged ledger amounts.
- Scope any permitted voucher operation to project/tenant and authorized role; verify route middleware and controller policies.
- Retain all original header/detail/ledger rows. Do not `forceDelete`, `DB::table()->delete`, or replace posted rows.
- Protect against concurrent writes with transaction locks and idempotent operations.
- **Do not silently disable/report-hide historical entries**; doing so changes financial balances.

## Canonical identity model (proposed)

A ledger entry must have a durable, unique source identity:
- `source_kind`: e.g. `journal_voucher` / `commission_voucher`.
- `source_header_id`: ID in the typed voucher header table.
- `source_line_id`: ID of source detail (optional, nullable).
- `posting_batch_id`: groups all ledger rows of the original posting and later reversal.
- `reversal_of_ledger_id` / `reversal_of_batch_id`: append-only compensating references where applicable.

These are **proposal names, not existing schema**. Avoid changing existing `type/type_id` interpretation until reports, queries, and exports are mapped and compatibility strategy tested. Never assume legacy JV type rows necessarily belong to Journal Voucher headers.

## Financial invariants for implementation

1. **Immutable posted rows**. No deletion or edit of posted ledger rows (including model-builder `delete()`).
2. **Balance**: total debits equal total credits within each posting batch. Reversal mirrors original postings exactly with debits/credits swapped; prior postings remain visible in audit history.
3. **Idempotence**: each voucher version/reversal may post once; a repeated request cannot create duplicate reversals.
4. **Atomicity**: voucher state, new ledger entries, audit entry, and reconciliation marker must commit/rollback together.
5. **Isolation**: JV/CV identifiers and unrelated JV header/detail IDs must never collide semantically. Tests with arbitrary independent IDs.
6. **Provenance**: record authorized actor, reason, time, original reference and linked corrections; redact sensitive customer information from activity logs.
7. **Reconciliation**: verify account/project totals and reporting before vs after isolated rehearsals. Unresolved legacy rows stay in a quarantined **classification report** (not physically removed from ledgers).

## Rollout sequence and gates

**Gate A — establish baseline:** full reader/writer inventory; SELECT-only counts and duplicate/orphan checks on disposable MySQL restoration; backup and test restore; confirm statuses and approved/draft semantics.

**Gate B — test harness:** preserve current RED regression evidence; add PASS cases for draft-only updates, unrelated voucher ledger preservation, cross-project denial, malformed input, transaction rollback, reversal double-submission, and report totals. Avoid running destructive fixtures on MySQL; use SQLite `:memory:`.

**Gate C — fail-closed stabilization:** implement protected action policy for JV/CV posted writes; test all affected UI flows; run targeted and full suite; record any backward compatibility breaks. This changes application behavior and is NOT covered by a documentation-only authorization.

**Gate D — additive schema + dual-read:** after explicit migration approval, add typed source identity/void metadata without dropping/retyping existing columns. Legacy data remains unchanged until independently audited.

**Gate E — reversal posting + rehearsal:** implement append-only compensating ledger entries, atomic audit events, and idempotence; run isolated restored-dump reconciliation and compare reports.

**Gate F — approved release:** only after operator review, backup/rollback drill, full tests, change window and separate explicit deployment authorization. Never merge/deploy `master_live` by default.

## What NOT to do

- Do not replace `JV` with `CV` everywhere; historical CV rows may already be encoded as JV.
- Do not adjust ledgers by colliding numeric `type_id` only.
- Do not assume SoftDeletes means ledger reversals or reliable history.
- Do not run `php artisan migrate`, `migrate:fresh`, bulk UPDATE, SQL import or backfill against any real database without explicit approval.
- Do not mark RED regression tests as fixed merely because controller returns redirect; require financial assertions and report reconciliation.

## Current status

Documentation only. No code changes, DB writes, migrations or deployments. Next engineering task: add a **safe isolated security gate** around JV/CV update/delete behavior after review; do not blindly patch individual query filters.
