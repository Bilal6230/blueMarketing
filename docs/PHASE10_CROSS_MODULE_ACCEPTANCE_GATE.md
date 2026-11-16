# Phase 10 — Cross-Module Acceptance Gate (active checklist)

**Date:** 2026-11-16  
**Code branch:** `upgrade/laravel-framework`  
**Local root:** `C:\laragon\www\blueMarketing`  
**Status:** OPEN — do not mark delivered until required local PHPUnit, MySQL audits and browser scenarios are recorded.
**Source:** `docs/ACTIVE_POINT03_HANDOFF.md`, `docs/POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md` and `docs/POINT03_PHASE_03_VOUCHER_WORKLOG.md`.

## Safety boundary

All read-only audits and isolated SQLite PHPUnit tests can run without creating production vouchers. Use only owner-approved backed-up disposable local DB for manual creation / approve / reverse / release scenarios. Never run V2 pilot-only migrations on staging/live or overwrite customer data. No deployment or production mutation is authorized by this checklist. Keep the owner-deferred BD/BW and Plot Receipt A5 two-page print work **ON HOLD**.

## Gate A — current shared design closeout (owner local)
Run in **PowerShell**, stop and share output of first failing command:

```powershell
cd C:\laragon\www\blueMarketing
git status --short
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=FinanceVoucherUnifiedComponentsTest
php artisan test --filter=FinanceVoucherCrVisualParityTest
php artisan test --filter=FinanceVoucherCanonicalDesignTest
php artisan test --filter=VoucherGlobalVisualParityTest
php artisan test --filter=CommissionVoucherListingResponseTest
```

**Owner browser, selected Project 3:** CR reference; JV and SV exactly one centered pager; CV table loads (no permanently stuck "Processing..." panel), handles zero rows, dates and permissions; Draft and Extra Charge table header immediately under toolbar, one visible card, pager at bottom; Cheque/Online date range & voucher-links filter. Test Next/Previous, Show 10/25/50, search and reset against records where available. Record PASS/FAIL per page; no posting needed. Screenshots of a single empty table establish geometry only, not nonempty pagination functionality.

## Gate B — isolated financial regressions (code tests, owner local)
Each is an independent safe test class; inspect failures before proceeding:

```powershell
php artisan test --filter=DailyVoucherApprovalWorkflowTest
php artisan test --filter=BankVoucherLegIntegrityTest
php artisan test --filter=BankVoucherRevisionEvidenceTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan test --filter=BankSourceLinkedVoucherHistoryTest
php artisan test --filter=CommissionVoucherCollisionTest
php artisan test --filter=CommissionVoucherListingResponseTest
php artisan test --filter=V2BankTransferWriterTest
php artisan test --filter=V2PlotEntryWriterTest
php artisan test --filter=PlotPurchaseGlobalVoucherDirectoryTest
php artisan test --filter=PlotReceiptGlobalVoucherDirectoryTest
```

Do not infer production readiness from green isolated tests; the tests exercise fixtures and specific invariants.

## Gate C — isolated V2 reconciliation (read-only, owner local)
On the previously approved isolated local Project 3 database **only**, after selecting its correct environment/connection:

```powershell
php artisan finance:audit-v2-bank-transfers 3
php artisan finance:audit-v2-plot-receipt 3
php artisan finance:audit-v2-plot-purchases 3
```

**Command verification:** `finance:audit-v2-bank-transfers` uses the existing read-only `V2BankTransferReconciliation`; `finance:audit-v2-plot-receipt` is the guarded, isolated local storage/readiness audit (not a ledger repair); `finance:audit-v2-plot-purchases` uses the existing read-only purchase reconciliation. Run all three only with the previously approved isolated test connection and Project 3, never as a substitute for confirming the database target.

Expected previously witnessed: PP-1, batch 4, 1 plot, Rs1,000; debit Rs1,000 and credit Rs1,000, PASS, 0 exceptions. Compare fresh result with this baseline; discrepancies require investigation before any data repair. For bank movement and plot receipts, use only existing documented read-only audit commands and compare to earlier owner's PASS evidence. Do **not** run schema migrations as a verification shortcut.

## Gate D — owner browser / database workflow (disposable local data only)
- **Approval lifecycle:** Pending -> Approve and Pending -> Reject for six voucher families; verify permissions, actor, event history and inactive ledger while pending. Rejected -> revise/resubmit and approved same-source edit with source identity unchanged, exact reversal logic and no duplicate active posting. Test a second project cannot see another project's vouchers/attachments.
- **Bank sources:** Cheque and online Confirmed/Cleared eligibility, exact single-source selection, allocations, partial release/void, historical linked-voucher drilldown and same-source uniqueness; verify rejected/returned sources are not selectable as passed.
- **Bank movement:** BD/BW read-only reconciliation against original BD-1/BW-2 baseline; test successful posting/void only on disposable records. A5 print issues are owner-HOLD.
- **Plot:** single and multi-plot receipts, plot purchase seller/inventory reconciliation, duplicate/foreign-project rejection, cancellation and idempotent retry. Plot Receipt A5 print remains owner-HOLD.
- **UI:** global CR-like filters, rows, pagination, modal-specific extra actions, attachments, export and printing with correct permission-aware access. CV processing/network error state must be meaningful (403/419/general failures) rather than indefinitely spinning.

## Acceptance status summary — evidence, not percentages
| Milestone | Current evidence | Gate |
| --- | --- | --- |
| Phase 01 Cheque and 02 Online | Owner browser PASS | COMPLETE within recorded scope |
| Phase 03 shared UI/settings | Owner browser PASS for 03.1/03.2 | COMPLETE within recorded scope |
| Phase 03.3 / 05 lifecycle | Various individual browser and isolated tests PASS | PARTIAL final end-to-end coverage |
| Phase 04.7 attachments | Owner 7/7 browser PASS | COMPLETE for named attachment batch |
| Phase 06 BD/BW core | BD-1/BW-2 reconciliation owner PASS | PARTIAL print hold |
| Phase 07 Plot Receipt | Details and single-plot owner PASS | PARTIAL print hold |
| Phase 08 Plot Purchase | PP-1 audit, 0 exceptions, tests 21/108 | ACCEPTED named pilot evidence |
| Phase 09.1/09.2/09.3 | Owner browser PASS | COMPLETE functional |
| Shared finance design | User screenshots show latest Extra Charge one-card/table-footer geometry | OPEN full-page/nonempty QA + PHPUnit rerun |
| Phase 10 | This gated acceptance plan | OPEN |

## Current code batch (2026-11-16)
Commission Voucher directory hardening: table Actions header schema is stable even when permissions hide individual action buttons; the AJAX handler safely displays 403/419/generic load failures and avoids a permanently blocking Processing indicator; non-AJAX listing avoids full-table preload; users with no assigned role no longer trigger numeric-index notices; isolated SQLite cases added for project-scoped date-range pagination and missing-role listing. Source fixes committed; **local PHPUnit and browser PASS not asserted**.

## Exact next handoff
1. Owner shares output from Gate A, especially the revised `FinanceVoucherUnifiedComponentsTest` and new `CommissionVoucherListingResponseTest`.
2. Assistant fixes actual failures in the same shared code; no cosmetic page-by-page CSS overlays.
3. After Gate A PASS, execute Gate B regressions. Owner supplies failing output; assistant fixes root cause.
4. After Gate B PASS, confirm Gate C DB reconciliation and Gate D local browser cases. HOLD printing remains deferred until explicitly resumed.
5. Only after all non-HOLD gates pass: update authoritative handoff + roadmap and mark Phase 10 conditional acceptance; separately track deferred print/app-wide design scope.
