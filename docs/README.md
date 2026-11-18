# BlueMarketing — CURRENT Development Roadmap and Handoff

**Updated:** 2026-11-18  
**Development branch:** `upgrade/laravel-framework`  
**Only active roadmap:** **Five milestones from the owner's new rules**, NOT the previous 10-phase roadmap.  
**Project environment:** Laravel / MySQL. The owner is using the newly migrated local database `bluenew_clean_final` via `.env`; never hard-code the database name or add database-name isolation rules. Use the current configured connection.

## Mandatory developer procedure (EVERY implementation batch)
1. Read this file, then `AGENTS.md`, then inspect the real current branch implementation. Do not use previous 10-phase notes or V2 isolation semantics as instructions.
2. Do one actionable code batch, add relevant automated regression coverage, and report precisely which changes are committed and which are unverified.
3. Update **this same file** after **every** batch (completed, partial, pending, reason, relevant commits, next work). **Do not add another handoff, phase-roadmap, or milestone-status document.**
4. First-party frontend DOM/AJAX uses **jQuery**; preserve the approved shared CR/DR components (fonts, card layout, filters, pagination, color-themed skeleton, toast/modals) across all voucher pages.
5. No full-page blocking loader; render the page shell immediately. Show skeleton only inside a data-fetching area and remove it after success/error. Do not fake browser PASS based on static tests.
6. Never claim a task complete without source inspection and required code tests; distinguish GitHub commit, local PHPUnit PASS, and actual owner browser PASS.
7. No hard delete of business/accounting records; no silent cascading loss of unrelated records. Keep edits atomic, project-scoped, FK-safe, and consistent with applicable permissions. Do not add unrequested approval/reversal gates.

## The FIVE active milestones (authoritative order)

### 01 — Global Loader + Unified Skeleton Design — **IN PROGRESS**
- Remove full-screen/application-blocking loaders; display the shell immediately.
- Standardize CR/DR-equivalent table skeletons across ALL finance voucher pages; the skeleton color follows each voucher's own `--voucher-accent`, including Bank Deposit, Bank Withdrawal, Plot Receive Payment, Plot Purchase Voucher, Draft Voucher, Extra Charges, JV/SV/CV, cheque and online lists.
- DataTables pages require special initial server-row concealment until initialized; AJAX pages render skeleton in the table body, not a blocking page overlay.
- Completed in code: master layout nonblocking loader; initial four AJAX list skeletons; new global accent-following neutral skeleton CSS; Draft/Extra initial DataTables skeleton; reusable modal/table skeleton components.
- **Verified by owner locally:** `FinanceVoucherInitialSkeletonTest` 2 PASS (21 assertions); `AdminNonBlockingLoaderTest` 2 PASS (13 assertions); `php artisan view:cache` PASS before latest accent/Draft changes.
- **NOT verified yet:** latest CSS/Draft/Extra changes in local PHPUnit/Blade/browser; comprehensive color and initial-load visual browser PASS on every voucher page. Milestone 01 cannot be marked complete until these pass. No server accounting changes in this UI batch.

### 02 — Granular Daily Entries Action Permissions — **NOT STARTED UNDER NEW RULES**
- All voucher types and management modules: distinct server-side permissions for each supported action (View/Create/Edit/Delete/Approve/Reject/Reverse/Print, plus applicable actions).
- UI must reflect permissions, but backend authorization is authoritative; inventory existing policies/routes first.

### 03 — Voucher Relationship-Safe Create/Edit/Logical Delete — **NOT STARTED UNDER NEW RULES**
- Audit every voucher's parent/detail/ledger references, foreign keys and polymorphic `ledgers.type/type_id` mappings without assuming they all refer to detail IDs.
- Create/edit/delete update only related rows atomically; use `deleted_at` soft delete or `is_active=0`; inventory/report tables with neither; add migrations/FKs only if valid for live schema and existing records. Never physically delete business records.
- Existing voucher CRUD implementations are baseline code, not acceptance of these *new* cascade rules.

### 04 — Cleared Cheque + Online Records Linked-Voucher Cascade — **NOT STARTED UNDER NEW RULES**
- Edit/delete cheque and online records, including cleared items that already generated vouchers.
- Update/logically delete only associated vouchers, ledger allocations and relevant balances using an audited transactional mapping; avoid dangling references and cross-project impact.

### 05 — Configurable Reversal + End-to-End Regression — **NOT STARTED UNDER NEW RULES**
- Setting ON/OFF governs reversal of posted/approved vouchers, with server authorization, single-reversal correctness and original audit history preserved.
- Cross-module regressions: permissions; create/edit/delete; linkage; cheque/online changes; reversal; ledger/balance parity; no hard deletion.

## Current implementation batch (2026-11-18)
**Scope:** Milestone 01, voucher loading theme and initial rendering.
- Earlier: Bank Deposit/Withdrawal `transferRows`, Plot Receipt `prHistoryRows`, Plot Purchase `ppHistory` show shared AJAX skeleton.
- Latest: `public/css/blue-table-skeleton.css` styles neutral skeleton using per-voucher `--voucher-accent`, not an always-grey tone; explicit CR/DR credit/debit tones remain intact.
- Latest: `draft_voucher.blade.php` and `extra_charge.blade.php` present initial skeleton and conceal server table rows until DataTables `init.dt`; common presentation in `public/css/finance-voucher-components.css`.
- `resources/views/admin/layouts/master.blade.php`: bumped skeleton CSS/JS asset versions.
- **Testing state:** GitHub commits present. Regression coverage extended to accent color and Draft/Extra initialization; source contracts inspected. Latest local PHPUnit, view cache and visual browser checks are still PENDING.
- **Next batch:** user browser QA of accent colors + slow reload, then fix any remaining page-specific loader gaps **within Milestone 01**, then move to Milestone 02.

## 2026-11-18 batch completion ledger
- **Code committed:** shared voucher-accent neutral skeleton, Draft and Extra initial DataTables skeleton, common initial-state CSS, versioned asset loading, and regression assertions. The previous Bank/Plot AJAX skeletons remain in place.
- **Documentation cleanup committed:** 49 superseded markdown worklogs/roadmaps/archived audits removed from the active tree. Exactly **two docs files** remain: this authoritative five-milestone README and the original client requirement source. Git history preserves deleted documentation for recovery.
- **Acceptance:** source inspection completed; owner's prior 4 unit tests PASS belongs to the earlier batch. **New code local PHPUnit and browser PASS are NOT claimed.** Current active milestone remains 01, with 02-05 queued and not silently marked complete.
- **Key commits:** `e3ce6e7e`, `9fb867ce`, `59ee2ab3`, `d381ef06`, `27017abc`, `56020b14` (docs purge), `7d73fcea` (test update).

## What was retired
The previous 10 phases and V2-specific migration/isolation phases are historical, NOT active instructions. Their superseded docs were removed from the working `docs/` directory; Git history retains them for evidence/recovery. The original client requirements remain available at `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md` as historical/source scope, not as a replacement for this owner's five new rules.

## Local checkout and verification
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=FinanceVoucherInitialSkeletonTest
php artisan test --filter=AdminNonBlockingLoaderTest
```
For browser QA use hard-refresh and network throttling to confirm colored skeletons, no expanded raw rows, and no full-page loader. Verify a real request/error cycle before calling PASS.
