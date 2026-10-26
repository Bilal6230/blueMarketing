# Phase 1D — Application Write/Delete Risk Audit

Date: 2026-10-26 | Scope: targeted inspection of 11 major controllers on `upgrade/laravel-framework`. READ ONLY; no application code, DB, or schema modified.

## Confirmed source-level risks

| Severity | Source | Lines (approx.) | Verified behavior / concern |
|---|---|---|---|
| CRITICAL | `app/Http/Controllers/StockController.php` | 713–775 | `deleteItem` deletes related stock movements, entry lines, entries, bill lines, bills and balances using `DB::table()->delete()`, then calls `$item->delete()`. Transaction protects atomicity but not history preservation. |
| CRITICAL | `StockController.php` | 829–886 | `deleteParty` likewise deletes related entries/bills/movements before `$party->delete()`. |
| CRITICAL | `app/Http/Controllers/JournalVoucherController.php` | 432–444 | `destroy` deletes detail rows and `Ledger` rows with `type='JV'` then soft-deletes JV parent. Ledger model has no SoftDeletes, so this is destructive. |
| CRITICAL | `app/Http/Controllers/CommissionVoucherController.php` | 233–243, 292–309 | `destroy` deletes `CV` ledger rows; `update` deletes ledger rows with `type='JV'`. Mismatched type may remove unrelated JV posting with the same type_id or leave stale CV posting. MUST reproduce in disposable database; no direct hotfix without tests. |
| HIGH | `app/Http/Controllers/UserController.php` | 175–184 | `destroy` calls `$user->delete()`; User model lacks SoftDeletes. Can permanently remove actor identity / violate FK history requirements. |
| HIGH | `app/Http/Controllers/LabourController.php` | 471–479 | `destroy` calls `$labour->delete()`; Labour model lacks SoftDeletes. |
| HIGH | `app/Http/Controllers/BookingController.php` | 3290–3321 | Booking is soft-deleted, but booking history is appended to `plots.plot_history` JSON alongside customer personal details; audit policy, consistency and edit history need review. |
| HIGH | `app/Http/Controllers/BookingController.php` | 1904 | `BookingDetail::where(...)->delete()`; check whether BookingDetail has SoftDeletes and whether replacing schedules destroys prior commitments. |
| HIGH | `app/Http/Controllers/Finance/VoucherController.php` | 325–340 | Some approval paths use `DB::table($request->table)` builder updates, which bypass Eloquent model events; central activity logs cannot rely solely on model observers. Verify validation / allowlist and authorization in full surrounding flow. |
| MEDIUM | `app/Http/Controllers/LedgerController.php` | 245,359,926–930 | Draft ledger deletion; ledger `is_active=0` deactivation and linked updates. Review deletion semantics, financial impact and audit trace. |
| MEDIUM | `app/Http/Controllers/PlotController.php` | 283–315 | Plot delete paths; inspect model soft-delete status and FK implications before changes. |

**Note**: Line numbers track the reviewed commit; re-check before fixes. Static inspection proves code paths exist, not that every path is reachable by all users or that erroneous data loss has occurred. Authorization, permissions, and business preconditions require tracing and tests.

## Findings that affect new audit logging
- Query builder `DB::table()->delete()/update()` bypasses Eloquent model observers. Observer-only activity logging cannot satisfy 100% create/update/delete coverage.
- Deletion in DB::transaction does not prevent historical data loss: it makes deletion atomic.
- Mutations to voucher and ledger rows require transactionally consistent, request-correlated audit entries; logs must record exact actor, device/session ID, IP and redacted before/after values.
- Distinguish business **void/reversal** from soft-delete; financial effects must be balanced.

## Required next implementation design (NOT AUTHORIZED yet)
1. Write failing regression tests on disposable test DB for JV/CV update/delete isolation, duplicate type_id across voucher types, and no-loss stock/user/labour operations.
2. Inventory relevant authorization and cross-project checks for each destructive route; deny rather than quietly delete transaction history.
3. Formalize immutable voucher reversal and inventory correction flows; implement reversible soft-delete for master records only where appropriate.
4. Read-only orphan/duplicate/financial reconciliation against full dump copy, verifying any attempted FKs.
5. Design audited write service / domain events for both ORM and direct DB mutations; preserve backups/restore procedure.

## Boundaries
This is **not** a complete audit of all application controllers, all models, scheduled jobs or all data rows. No database migration, import, SQL write or code refactor has run. The current branch is `upgrade/laravel-framework`; `master_live` remains untouched.
