# Point 03 — Phase 01 — Cheque Management
**Status:** STARTED — initial read-only code audit; implementation, tests and browser acceptance NOT done.
**Selected client source:** Roman Urdu requirements, section **E. CHEQUE & ONLINE TRANSACTION MANAGEMENT** (cheque portion), with relevant cross-cutting rules from I and K.
**Last updated:** 2026-11-10

## Milestone 01.1 — Cheque record baseline, identity and mandatory purpose
**Evidence from source inspection:**
- `resources/views/admin/finance/voucher/cash_voucher.blade.php` already includes payment_type=3 (Cheque), `t_number`, `bank_id`, `passing_date` and `cheque_purpose`; the form toggles purpose required for a new cheque.
- `app/Http/Controllers/LedgerController.php::store` validates required cheque purpose (except selected pending clearance), cheque number/bank/passing date and saves source data to `customer_ledger` in the legacy path.
- Existing flow is a cash voucher form, **not yet verified** as the required dedicated “Add Cheque” form with all E.1 fields (branch, receiving date, receipt evidence, from person, attachment, etc.).
**Status:** PARTIAL (source evidence only). Next: map all existing cheque capture/edit/write paths and permission boundaries, implement only missing E.1 fields/workflows safely, then actual browser tests.

## Milestone 01.2 — Cheque Receiving Slip
**Requirement:** unique slip, company details, original cheque data, status on repeat prints, explicit “receipt only, not cleared/payment” wording, no automatic final bank voucher on reception.
**Status:** PENDING — specific implementation not established by this audit.
**Browser gate:** create cheque → print slip immediately → reopen slip → check live status and ensure no final bank accounting posting.

## Milestone 01.3 — Bank clearing vs direct withdrawal
**Requirement:** two actions, original source linkage and immutable amount, clearing bank/deposit info; withdrawal person/date/CNIC if required; status and audit.
**Status:** PARTIAL — existing pending-bank-payment flows, `VoucherController::pendingBankPayments` and `updatePendingBankPaymentStatus` seen; exact E.3/E.4/E.5 workflows unverified.
**Browser gate:** both paths with fixed amount, authorization, cross-project and replay checks.

## Milestone 01.4 — Status, verification and history
**Requirement:** Received/Pending Clearing/Passed/Returned/Bounced/Rejected/Memo/Withdrawn, non-bypassable confirmation at Pending→Cleared, bank statement verification confirmation, confirmer/time, immutable amount, rejection reason and attachment, eligible confirmed sources only.
**Evidence:** `app/Services/Finance/ChequeLifecycleHistory.php` stores lifecycle events when finance table exists; `routes/web.php` includes a cheque report/bank posting action. This does NOT prove complete client workflow exists or passes browser testing.
**Status:** PARTIAL / not browser-tested.
**Browser gate:** verified, cancelled and invalid transitions, audit history, denied duplicate posting; then full Phase 01 browser regression.

## Baseline constraints and next step
1. Confirm all E.1–E.6 required fields and action paths against live code with focused review before patching.
2. Do not conflate incoming cheque receipt with bank clearance; do not generate BOR/BOP on cheque receipt.
3. Do not touch V2 safety guards or client/production financial data; preserve existing CR/CP paths.
4. For any first-party UI code, use jQuery for DOM/events/AJAX. No vanilla DOM/fetch.
5. Browser testing must be done on actual owner's application. No evidence of browser PASS yet.

**Current gate:** Milestone 01.1 NOT PASSED. Phase 02 locked.
