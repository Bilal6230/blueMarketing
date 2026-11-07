# Point 03 — Daily Entries Requirements Audit

**Date:** 2026-11-04  
**Scope:** Compare the client-provided Point 03 requirements with the current repository and track the follow-up implementation. Status is split between backend capability and client-facing screens.

## Client clarification — 2026-11-05

The existing combined Cash In / Cash Out pages are not an acceptable final UI for the payment-method workflows. Provide six separate entry screens, each with its own fixed method and direction:

1. Voucher In — Cash (Cash Receipt Voucher)
2. Voucher In — Bank Online (Bank Online Receipt)
3. Voucher In — Cheque (Cheque Receipt)
4. Voucher Out — Cash (Cash Payment Voucher)
5. Voucher Out — Bank Online (Bank Online Payment)
6. Voucher Out — Cheque (Cheque Payment)

The six screens may reuse the existing posting engine where accounting behavior is the same, but the user must enter each through its own clear screen and must not switch method on that screen. Existing combined routes can remain as compatibility routes during migration. Distinct entry screens alone do not deliver the other client-requested vouchers: Bank Receipt, Bank Payment, Bank Deposit, Bank Withdrawal, Plot Receive Payment (multi-plot allocation), and Plot Purchase still need their linked-source/accounting workflows and UI. Debit/Credit Voucher is existing functionality to preserve and expose, not a new replacement.

### Clarified delivery milestones

- **C1 — Six method-specific entry points:** navigation, distinct URLs/titles, fixed method selector, compatible legacy routes.
- **C2 — Method-specific behavior:** only relevant bank/cheque fields, validation, drafts, edits, lists, and print labels per method.
- **C3 — Bank receipt/payment:** source selection, exact source amount and project bank identity, balanced allocations, unique linkage.
- **C4 — Deposit/withdrawal:** dedicated cash↔bank balanced entry, with cheque withdrawal tied to the selected cheque and amount.
- **C5 — Plot receipt:** one receipt across multiple plot/customer allocations, exact total, and per-plot receipt output.
- **C6 — Plot purchase:** seller/plot/inventory flow with linked purchase posting and reversal-safe changes.
- **C7 — Unified directory/approvals/details/print and focused regression:** every delivered type discoverable and traceable without altering unrelated records.

### Implementation status after clarification (2026-11-05)

The six fixed-method cash screens have been started in code. This is a screen/route scaffold and must not be reported as delivery of all eight client voucher types.

| Client-facing milestone | Status | Evidence / acceptance still required |
|---|---|---|
| C1 — Six separate Cash/Online/Cheque In/Out entry points | **Implemented; verify route and view behavior** | Six sidebar links and distinct URLs use the existing writer with a fixed method; old combined routes remain compatible. Verify route generation and initial form state. |
| C2 — Method-specific fields, lists, edits, drafts, prints | **Implemented; browser acceptance pending** | Fixed screens keep payment method immutable across create, draft, and edit; legacy and isolated-pilot lists filter by direction+method; method-specific print checks project/source/method identity and labels output by method. Static checks and existing cash-list regression pass; perform the browser checklist before production use. |
| C3 — Bank Receipt and Bank Payment | **Implemented; browser acceptance pending** | Separate project-scoped source-selection workspaces, exact source amount, project-owned bank account, balanced allocations, unique linked posting, AJAX source/history lists, and project-scoped print are delivered on the isolated pilot workflow. Verify in browser with an eligible source and authorized pilot account. |
| C4 — Bank Deposit and Bank Withdrawal | **Implemented; browser acceptance pending** | Dedicated project-scoped screens, balanced V2 posting, AJAX sources/history, project-scoped print, and cleared-cheque withdrawal with exact amount/reference and duplicate/mismatch protection. Focused writer tests and static checks pass; verify in browser with an eligible bank account and cheque. |
| C5 — Multi-plot Plot Receive Payment | **Implemented; browser acceptance pending** | Dedicated project-scoped AJAX workspace/history, multi-plot allocation, exact total and duplicate-plot checks, source details, and per-plot print use the isolated V2 posting workflow. Focused writer/static checks pass; verify in browser. |
| C6 — Plot Purchase Voucher | **Implemented; browser acceptance pending** | Dedicated project-scoped AJAX purchase form, multi-plot entry, seller/inventory accounts, project definitions, purchase history/details/print, and existing balanced/idempotent isolated V2 posting. |
| C7 — Unified discoverability, approvals, history, print, regression | **Implemented; browser acceptance pending** | Project-scoped unified directory covers legacy and typed voucher sources with filters, details, source-specific prints, and focused regression. Approval actions appear only for supported configured legacy CR/CP/JV workflows; typed pilot sources remain gated. Existing Debit/Credit and other legacy paths are preserved. See `DAILY_ENTRIES_POINT_03_PROGRESS_2026-11-05.md`. |

**Implementation status:** C1–C7 engineering is complete; browser acceptance remains outstanding and user-owned. No database migration was added for C7.

Completion must be reported against these client-facing milestones separately from backend API milestones. Do not label a voucher “implemented” while its user-facing entry path and supported list/details/print path are absent.

## Executive summary

The application already has substantial accounting foundations: legacy cash receipt/payment flows, debit/credit vouchers, journal/commission vouchers, booking collection records, cheque status/clearance handling, project bank-account settings, voucher display-number infrastructure, and several finance approval screens.

These foundations do **not** yet amount to the requested integrated Daily Entries system. The largest gaps are a single per-voucher-type approval policy, end-to-end cheque and online transaction lifecycle, fixed-amount linked bank vouchers with multi-account allocation and duplicate protection, Plot Purchase as a real inventory/accounting module, and a unified searchable/printable voucher history.

### Requirement status

| Area | Status | Existing evidence | Remaining work |
|---|---|---|---|
| Common voucher fields, search, date/type/account filters, view/print | **Partial** | Legacy voucher list and print routes/views exist; cash-in/out data endpoints and typed print support exist. `routes/web.php`, `Finance/VoucherController`, `resources/views/admin/finance/voucher/` | Unify field model/layout/history across all supported types; verify reference, attachments, status, prepared/approved by, filters, and consistent detail/print for each type. |
| CR/CP and Debit/Credit voucher | **Partial / existing core** | CR/CP routes and writers; D/C voucher uses existing accounting voucher screen. `LedgerController`, `Finance/VoucherController`, `accounting_index.blade.php` | Match required account sides and narration/reference/attachment behavior; common validation, approval state, and regression coverage. Preserve existing workflows. |
| JV/CV and existing voucher numbers | **Partial / existing core** | Journal and commission voucher routes/writers and display-number work exist. `routes/web.php`, `JournalVoucherController`, `CommissionVoucherController` | Include these in unified listing, settings, approval/history, filtering, and consistent view/print coverage where required. |
| Project bank accounts and payment-method labels | **Already available, partial fit** | Project-scoped bank account and payment label settings in `SettingController` and settings UI. | Reuse as bank identity source; validate every writer against the selected project and enforce enabled/active account behavior consistently. |
| Cheque report, status and clearance posting | **Partial** | `report/check`, `bank_posting_check`, check-history route, and pending-bank-payment status routes exist. Clearance validates project/source/status and prevents repeat processing; a passed record can create BR ledger posting. `ReportController`, `Finance/VoucherController` | Expand to requested receiving-slip → withdrawal/clearing → verified passed/returned/rejected lifecycle; mandatory purpose; non-bypassable confirmation; immutable event history; clear status transition rules; link final bank voucher exactly once. Existing flow currently handles only a subset of this lifecycle. |
| Online transaction management/confirmation | **New / not found in inspected paths** | Existing payment type labels and online payment codes are present. | Add online transaction record, required metadata, submit/confirm/reject states, required confirmation/actor/time history, and eligibility/duplicate controls for bank voucher creation. |
| Bank Receipt / Bank Payment from cleared transaction | **Partial foundation, major workflow gap** | Existing cheque clearing can post a BR-related ledger and retains source identifiers. | Build transaction selection restricted to eligible sources; immutable bank amount/account; multiple opposite-side allocations with exact total validation; source narration; unique transaction-to-voucher link; equivalent bank payment workflow. |
| Bank Deposit / Bank Withdrawal | **New as standalone balanced voucher workflows** | Bank accounts and some cheque/withdrawal status concepts exist. | Add dedicated balanced cash↔bank voucher writers, account-side validation and audit/print. Cheque withdrawal must bind the selected source and fixed amount. |
| Plot Receive Payment with multiple plot allocations and per-plot receipts | **Implemented; browser acceptance pending** | Dedicated AJAX workspace/history/details/print, isolated V2 source posting, project/customer/booking/account validation, exact total, idempotency, and duplicate plot allocation protection. | Browser-check eligible bookings, allocation totals, retry behavior, history/details, and each per-plot receipt. |
| Plot Purchase module and Purchase Voucher | **Implemented; browser acceptance pending** | Dedicated project-scoped AJAX workflow creates available plot inventory and balanced, idempotent purchase posting using seller and inventory accounts; history/details/print are linked to the source. | Browser-check purchase with configured project definitions, multiple plots, duplicate/cross-project rejection, and print. Existing cancellation uses append-only reversal; no inline edits or hard deletes are provided. |
| Per-voucher approval settings and assigned approver | **New / not equivalent to current approvals** | Finance change approvals and general approval/dashboard routes exist. | Add project-aware per-type approval configuration and assigned approver; submission notifications/queue; only authorized selected approver can approve/reject; mandatory rejection remarks; no final posting until approved when enabled; complete event history. |
| Cheque receiving slip vs. final accounting voucher | **Partial** | Existing source print and check history routes exist. | Explicitly separate non-posting receipt slip from final posted voucher; show all linked voucher identities/details and preserve source-to-posting history. |
| Cheque/online dashboard KPIs and filters | **Partial for cheque; new for combined lifecycle** | Cheque report filters by customer, plot, bank, status and date exist. | Add lifecycle KPIs and online equivalents, consistent search/filtering, status queues and links to all posted vouchers. |
| Integration, integrity, audit and no duplicates | **Partial foundation** | Some project scoping, transactions/row locks, duplicate checks and audit/history protections already exist in finance paths. | Apply uniformly to new and legacy writers; enforce tenant/project/account ownership, idempotent posting, amount balancing, immutable source identity and reversible cancellation. Add focused tests across concurrency and failure rollback. |

## Proposed implementation milestones

Milestones are ordered to reduce accounting risk. Do not enable new posting paths before their schema, project/account checks, and duplicate protections are in place.

1. **M0 — Contract and data model:** map each existing voucher/source table and status; finalize source IDs, approval states, transaction states, allocation model, permissions, and migration/backfill rules. No historical records should be rewritten.
2. **M1 — Shared voucher foundation:** consistent project-scoped voucher identity, fields, list/search/date/type/account filters, detail/print, attachment handling, and audit history. Adapt existing CR/CP/JV/CV/D/C records without replacing legacy IDs.
3. **M2 — Approval settings and workflow:** per-voucher-type settings, assigned approver, queue, authorized approve/reject, mandatory rejection reason, posting gate, notifications, and history.
4. **M3 — Cheque lifecycle:** receiving slip and required purpose, direct withdrawal or clearing, confirmation, passed/returned/rejected handling, source history, and migration-safe state rules.
5. **M4 — Online lifecycle:** transaction capture, required metadata, confirmation, source history, and eligible/pending/duplicate state handling.
6. **M5 — Linked bank postings and allocations:** bank receipt/payment and deposit/withdrawal; fixed source amount/account; multiple opposite allocations; exact balance; one-to-one source uniqueness; automatic narration and linked history.
7. **M6 — Plot receipts and purchase:** multi-plot receipt allocation and separate per-plot print receipts; new plot purchase/seller/account/inventory and linked purchase posting; reversal-safe editing/cancellation.
8. **M7 — Integration and regression:** end-to-end tests for authorization, project isolation, accounting sides, exact totals, duplicate/concurrent posting, rollback, approvals, history, list/detail/print, and preservation of existing voucher flows. Then perform browser QA and migration rehearsal separately.

## Estimate

Estimated engineering effort for implementation and automated regression: **23–36 developer-days** (roughly **5–8 full-time working weeks for one developer**). This is a planning estimate, not a guaranteed calendar deadline.

| Workstream | Estimate |
|---|---:|
| M0 data/source mapping and safe migration design | 2–3 days |
| M1 shared voucher/list/detail/print foundation | 3–5 days |
| M2 approval configuration and workflow | 3–5 days |
| M3 cheque lifecycle | 3–5 days |
| M4 online lifecycle | 2–3 days |
| M5 bank voucher writers, allocations and duplicate safety | 4–6 days |
| M6 plot receipts and Plot Purchase | 4–6 days |
| M7 integration/regression and migration rehearsal | 2–3 days |
| **Total** | **23–36 days** |

Estimate assumes existing application/database access and current project-account conventions remain usable. It excludes waiting for client decisions, production deployment, and the client’s manual browser acceptance. Real-device or bank integration, new approval policy details, document-storage constraints, and historical data repair can change the estimate. Some milestones can overlap during implementation, but financial posting dependencies should remain in the order above.

## Audit boundaries and evidence

Focused inspection covered the client Point 03 document; `routes/web.php`; `Finance/VoucherController`; `ReportController`; `BookingController`; the finance voucher views; and existing project bank/payment settings references. This was a code-level gap audit, not a full route-by-route execution, database baseline reconciliation, or browser QA. “Not found” means absent from these focused paths and should be confirmed during M0 source/table mapping before schema design.

No tests were run because this deliverable is an audit and task plan, not a code change. Existing user modifications in the working tree were left untouched.
