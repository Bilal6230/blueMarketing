# BlueMarketing — Point 03 Daily Entries — Phase Roadmap
**Source:** [Original Roman Urdu requirements](./BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md) (preserved without rewriting).
**Branch:** `upgrade/laravel-framework`
**Start:** 2026-11-10
**State:** Phase 01 and Phase 02 COMPLETE — owner browser PASS 2026-11-10. Phase 03 ACTIVE, 03.1 owner browser PASS; 03.2 owner browser PASS; 03.3 code implemented / browser PASS pending. Phase 04+ LOCKED.
**Rules:** `docs/README.md`, root `AGENTS.md`. Changes to business logic only within current approved milestone. Non-V2 code may be used safely; V2 pilot safety guards remain untouched.

## Strict sequence and browser gates
A phase is complete ONLY when (1) its milestones and relevant automated tests pass, (2) owner performs real browser testing on their running application, (3) failures are repaired/retested, and (4) owner explicitly confirms **PHASE PASS**. A failed milestone or phase blocks every subsequent milestone/phase's implementation, including cross-phase dependencies. Record browser environment/URL, scenarios, observed outcomes, date and tester. "Source inspected" is NOT browser PASS.

| Order | Source sections | Phase | Milestones (each with code check + browser gate) | State |
| --- | --- | --- | --- | --- |
| **01** | **E** | **Cheque & Online Transaction Management — CHEQUE scope only** | **01.1** Existing source, ownership, permissions and safe cheque-entry flow; mandatory Purpose/Description and original amount identity. **01.2** Cheque Receiving Slip view/print with live status, no financial posting on receipt. **01.3** Send for Bank Clearing vs Direct Bank Withdrawal, fixed amount, person/CNIC where applicable and bank details. **01.4** Cleared/returned/memo/withdrawn states, mandatory server-backed verification popup, actor/time/audit history; eligible-source gating; full phase browser regression. | COMPLETE — owner browser PASS |
| **02** | **F** | **Online Transaction Management** | **02.1** Receipt/payment source capture, fields, proof and reference uniqueness. **02.2** Pending→Confirmed confirmation popup, server-side authorization, bank verification, actor/time and immutable amount. **02.3** eligible confirmed source exposure for future bank vouchers, no pending/duplicate posting, history and full browser regression. | COMPLETE — 02.1, 02.2 and 02.3 owner browser PASS |
| 03 | A + B | Common Voucher UI and Voucher Approval Settings | **03.1** standard views/print and shared fields/filters, **03.2** per-type configuration and approver permissions, **03.3** pending/approve/reject/remarks/history and no posting before approval. | ACTIVE — 03.1 browser PASS; 03.2 browser PASS; 03.3 code / browser gate PENDING |
| 04 | C.1–C.7 | Cash, Bank, Deposit, Withdrawal and Debit/Credit Vouchers | **04.1** CR/CP cash mappings, **04.2** confirmed-source-only bank receipt/payment and opposite-side allocations, **04.3** deposit/withdrawal and cheque-linked withdrawal, **04.4** debit/credit multi-line balancing, view/print and regression. | LOCKED |
| 05 | C.8 | Plot Receive Payment Voucher | **05.1** cash receipt, **05.2** verified cheque/online source and fixed bank side, **05.3** multiple plots/customers, allocation sum, one bank posting, per-plot printable receipts. | LOCKED |
| 06 | D | Plot Purchase & Auto Purchase Voucher | **06.1** seller/plot purchase form, **06.2** auto voucher and exact source link, **06.3** inventory sync/cost and duplicate guard, **06.4** authorized edit/cancel/reversal and history; preserve plot sale. | LOCKED |
| 07 | G | Bank Narration, Linked Voucher History & Receipt-vs-Voucher Separation | **07.1** auto transaction metadata in narration, **07.2** linked voucher history + print, **07.3** no duplicate links / receiving slip not proof of clearing; regression. | LOCKED |
| 08 | H | Cheque & Online Dashboard | **08.1** cheque counts/amounts, **08.2** online counts/amounts, **08.3** bank/customer/status/date filters, live DB data and drilldowns. | LOCKED |
| 09 | I + J + K | Cross-module Acceptance & Delivery | **09.1** all 15 integration/control rules, **09.2** no extra unapproved voucher types, **09.3** full end-to-end approval/reversal/print/duplicate/transaction linking and preservation testing. | LOCKED |

## Important dependencies
- Phase 01 creates/validates cheque source and statuses, **not** final Bank Receipt/Payment posting. Bank voucher generation and opposite-side allocation belong to Phase 04, linked voucher history/detail to Phase 07.
- Phase 02 provides online source and confirmation, **not** automatic bank voucher posting; Phase 04 consumes confirmed sources.
- Check source design for Phase 07 history/linkage requirements already in phases 01–02, without implementing downstream pages prematurely.
- There is no assumed V2 production rollout. Pilot-only routes/writers cannot be enabled against live data.

## Phase-specific acceptance
Each milestone: intended routes, request/response validation, required/optional fields, permissions, existing data compatibility, no duplicate writes, consistent UI in actual browser, print where specified; tests must include negative scenarios, refreshing/re-submission, unauthorized actor and cross-project source rejection. Store evidence under `docs/POINT03_PHASE_01_CHEQUE_WORKLOG.md` for Phase 01. Create later phase worklogs only **after** previous phase PASS.

## Progress report policy
Use `COMPLETE`, `PARTIAL`, `PENDING`, `BLOCKED` grounded in evidence. Never invent percentages. Every milestone has its own browser PASS gate. No phase transition without user's explicit confirmation.
