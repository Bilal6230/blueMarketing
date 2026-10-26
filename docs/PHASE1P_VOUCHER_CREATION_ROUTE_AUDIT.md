# Phase 1P — Voucher Creation Safety and Route Audit

**Date:** 2026-10-26  
**Mode:** Read-only source review; no controller/test/schema behavior changed in this phase.  
**Scope:** `JournalVoucherController::store`, `CommissionVoucherController::store`, direct web routes and current temporary gates.

## Source observations

### Journal Voucher creation: still active

- HTTP `POST journal-voucher/create` → `JournalVoucherController::store` with permission `create jv`, per `routes/web.php`.
- Gets voucher number with `getLastJvVNumber()`, then writes `last + 1`. Without locking/unique constraint review, concurrent numbering is a **potential** collision risk, not reproduced yet.
- Requires `voucher_number`, `description`, `date`, nonempty account, subaccount, debit and credit arrays. User-provided `voucher_number` is not the actual newly assigned number. Does not explicitly validate equal array lengths, account/project membership, numeric/precision/non-negative line amounts or prohibit debit and credit on same row.
- Balance comparison is `array_sum(debit) !== array_sum(credit)` using PHP sums; no per-line numeric/decimal policy demonstrated by this source.
- Uses `getSelectedTown()` for header project, but the selected `sub_accounts` are not visibly checked against that project in this method.
- Calls `DB::beginTransaction()` and `DB::commit()`. The `try/catch` and `DB::rollBack()` block are commented out. If create/ledger insert throws after BEGIN, application-level rollback is not explicit in this path; assess request-lifecycle rollback and write an isolated exception test, not a production test.
- Creates JV ledger `type='JV',type_id=journal_vouchers.id`. Ambiguity with legacy CV `JV` postings remains unresolved.
- Does not show an idempotency key, transaction lock or dedicated immutable posting batch.
- No temporary HTTP 409 guard on JV create. Do **not** assume it is safe merely because existing edit/delete are guarded.

### Commission Voucher creation: temporarily blocked

- HTTP `POST commision-voucher/create` → `CommissionVoucherController::store` with permission `create jv`.
- First operation is HTTP 409 `abort()`. Direct-controller protection locally passed on 2026-10-26 (5/5, 10 assertions across guards).
- **Unreachable legacy code still** generates `JV` ledger entries for CV headers; four intentional source-contract RED checks include this mislabeling.
- Route itself is still present; user can reach create page, but cannot complete submission. Review UI messaging before any release.

### Update/delete route surface

Both voucher controllers have `PUT` update routes protected by `edit jv` permission and `DELETE` routes protected by `delete jv`; both controller actions currently abort HTTP 409 before mutating. Direct controller tests do not prove real-route middleware behavior, permission precedence or UX; add route-driven tests.

## Issues to verify next (prioritized)

1. **HTTP routes and auth**: test actual 5 unsafe method routes with permitted/unauthorized/unauthenticated users; verify existing framework middleware remains intact and no mutate path bypasses guards.
2. **JV store input contract**: build isolated in-memory SQLite tests for malformed arrays, forged cross-project subaccounts, negative/nonnumeric amounts, decimal rounding and exception atomicity. Avoid writing to MySQL.
3. **Readers and reports**: inventory all references to `type='JV'`, `type='CV'`, `type_id`, voucher reference columns and financial totals. Establish current report semantics before a new posting identity.
4. **Design decision**: evaluate interim fail-closed JV creation (disables legitimate posting) **versus** narrow validated writer. Do not block it silently without documenting user impact and local verification.
5. **Permanent repair**: approved typed source identity, append-only reversals, balanced batches, idempotency and full reconciliation per `docs/PHASE1K_FINANCIAL_POSTING_SAFETY_BLUEPRINT.md`.

**No migration, production query, live repair or deploy was performed.** Observations concern inspected code only. Four CV source tests remain intentionally RED; full suite last recorded at 233 passing / 4 failing before latest CV-store gate; the targeted five-gate suite passed.
