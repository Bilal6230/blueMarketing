# Point 03 — Daily Entries Requirements Audit

**Date:** 2026-11-04  
**Scope:** Compare the client-provided Point 03 requirements with the current repository. This is an audit and implementation plan; no feature implementation or database change is included.

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
| Plot Receive Payment with multiple plot allocations and per-plot receipts | **Partial** | Booking payment/deposit, payment schedule, and receipt routes/views exist (`booking.customer.deposit`, `payment_schedule.cash`). | Add single source transaction / single bank leg with multiple plot/customer allocations, exact total, source linking, per-plot receipt printing, and duplicate protection. |
| Plot Purchase module and Purchase Voucher | **New** | Plot sale/booking module exists. `plot_purchase` found in `BookingController::cancel` as a cancellation action type; this is **not** a plot-purchase inventory module. Stock purchase applies to material inventory, not land plots. | Create seller/plot purchase workflow, seller account, plot identity/size/unit/rate/cost/documents, inventory availability update, automatic linked purchase posting, edit/cancel via reversal/history, and safe coexistence with Plot Sale. |
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
