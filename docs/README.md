# BlueMarketing — Current Development Handoff

**Branch:** `upgrade/laravel-framework`

## Source of truth for a new chat
1. Read `AGENTS.md` for the coding conventions (first-party UI/AJAX uses jQuery).
2. Inspect the current branch code and the latest commits; implementation is authoritative over historical phase notes.
3. Read `docs/ACTIVE_POINT03_HANDOFF.md` only for still-relevant finance context. Older milestones, V2 separation language, and dated phase gates in legacy notes are **historical**, not live instructions.
4. Use the client's current requirements; do not resurrect V2-specific namespaces, filenames, routes, isolated database requirements, or outdated approval restrictions. The user requested a normal unified application that works with whichever configured database is connected.
5. Each completed batch must update this README and the active handoff, state precisely what was committed, what remains unverified, and give the user a pull/test command. Do not mark browser checks as PASS without owner confirmation.

## Active batch — 2026-11-18: voucher table loading consistency

Four views changed on `upgrade/laravel-framework`:
- Bank Deposit and Bank Withdrawal (one shared `bank_transfer_workspace.blade.php`): display `$.blueTableSkeleton` while voucher history loads; clear it for success/failure and preserve the error message.
- Plot Receive Payment (`plot_receipt_workspace.blade.php`): the same 8-column skeleton on initial and repeat AJAX history loads.
- Plot Purchase Voucher (`plot_purchase_workspace.blade.php`): the same 8-column skeleton around the async history fetch.
- Extra Charges (`booking/extra_charge.blade.php`): conceal the raw tall table before DataTables initialization and present a skeleton until the `init.dt` event. The table is only revealed after it is initialized.

All changes are frontend only; there are no accounting, database, voucher permissions, or API changes. Code review and automated tests do not count as browser verification. **Owner browser check pending** for all five page names above, including Extra Charges during throttled initial page load.

## Documentation maintenance
Keep this index short and current. Historical reports under `docs/DATABASE_Finance_*` and the dated `POINT03_*` worklogs are evidence, not instructions to reintroduce the former V2 isolation. Remove or archive them only after confirming no active requirements are lost. Do not use historical status claims to override current code.
