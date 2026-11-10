# Phase 03 — Common Voucher UI and Voucher Approval Settings
**Source:** `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md` Sections A and B.
**Phase 02:** COMPLETE, owner browser PASS 2026-11-10.

## 03.1 — Common Voucher Display/Print and Filters
**Status:** Code committed; local/browser gate PENDING.

### Implemented
- Reused existing project-scoped `DailyVoucherDirectoryController` and its read-only source lines/details to prevent duplicate accounting logic, voucher creation or updates.
- General Voucher Directory now exposes existing supported Voucher Type series as a common dropdown; type-preset pages retain their fixed hidden series.
- Added Reset for top Date/Account/Type/Search, quick search and column inputs; restores date-desc sort and first page.
- Added a standard A4 **Print Details** view for the existing read-only Details modal, in addition to the existing source-specific print link. A4 print includes original voucher number/type/date/account/reference/status/narration, source lines and audit fields when returned. It does not manufacture missing debit/credit or approval metadata.
- Existing source-specific print links, editing rights, bank/cheque source gates and original voucher code unchanged; jQuery handlers only.
- No new database tables, migrations, backend writes, approvals or ledger postings.

### Browser acceptance scenarios (must PASS before 03.2)
1. General voucher directory: type filter CR, DR, JV where data exists; project-scoped results.
2. Preset CR/BOP page: fixed series remains fixed even after Reset.
3. Reset clears top, quick and column filters; returns latest-first listing.
4. Open Details for several existing source types; new Print Details (A4) preview shows only actual row data.
5. Existing Open Print View remains available; both PDF/printer preview and screen details display without overlapping page UI.
6. No new bank voucher or ledger posting; existing edit/create flows unchanged.
7. Client/browser tester confirms PASS explicitly.

### Next
03.2 — per-type approval settings and selected approver permissions; do not implement before 03.1 browser PASS.

## 2026-11-10 — Milestone 03.1 browser PASS; 03.2 policy configuration
- Owner explicitly confirmed Common Voucher UI & Print **browser PASS**. Milestone 03.1 CLOSED.
- Inspected existing `DailyVoucherApprovalWorkflow`: only CR/CP/JV have real inactive-until-approval enforcement, project-scoped selected approver and rejection audit. Extending approval_required=ON to unsupported types would be unsafe.
- Added centralized project-scoped **Definitions & Settings → Voucher Settings** page, covering required voucher labels; CR/CP/JV approval ON/OFF and authorized approver are editable, unsupported types clearly locked. No unsupported approval policy can be saved (backend rejects it).
- Reuses `daily_voucher_approval_policies`; no schema, no new posting, no existing source rewrites. Config saves via jQuery AJAX with SweetAlert2 confirmation, validation/error and success responses, manager & read/update setting guards, project-scoped approver validation.
- Owner browser PASS required before 03.3; pending queue/decisions remain on existing Approval Dashboard.

## 2026-11-10 — 03.2 browser PASS, 03.3 implementation
- Owner confirmed **Voucher Settings (03.2) browser PASS**, screenshot of configured CR/CP/JV and locked unsupported vouchers. 03.2 CLOSED.
- Modernized existing project-scoped approval dashboard: Pending queue, readonly recent history, separate decision and audit modals; existing manager access gate, assigned approver-only buttons and no self-approval. Used jQuery AJAX + SweetAlert2 confirmation/validation/toaster, preserving modal on rejected AJAX error, disabling in-flight repeat submissions.
- Rejection reason required client- and server-side; source/project/type checked on server; unchanged transactional guarded `DailyVoucherApprovalWorkflow::decide` remains authoritative for source locking, posting activation, status and immutable actor/time event. JSON API response supports AJAX, old form fallback retained.
- No schema changes, no unsupported source approvals, no new posting logic. Phase 03.3 browser gate pending.
