# BlueMarketing — Point 03 Daily Entries — Phase Roadmap
**Source:** [Original Roman Urdu requirements](./BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md) (preserved without rewriting).
**Branch:** `upgrade/laravel-framework`
**Start:** 2026-11-10
**State:** Phase 01 and Phase 02 COMPLETE — owner browser PASS 2026-11-10. Phase 03 ACTIVE, 03.1 and 03.2 owner browser PASS; 03.3 Batch 1 owner browser PASS, Batch 2 JV modal alignment browser PASS, typography adjustment browser gate PENDING; Batch 3A and 3B1 browser PASS; 3B2A/B implemented with browser testing ON HOLD due to CR/CP create validation. Phase 04+ LOCKED.
**Rules:** `docs/README.md`, root `AGENTS.md`. Changes to business logic only within current approved milestone. Non-V2 code may be used safely; V2 pilot safety guards remain untouched.

## ACTIVE DELIVERY ORDER — OWNER RESEQUENCED 2026-11-11 (AUTHORITATIVE)

Owner explicitly changed phase priority to resolve the dependency inversion: do NOT require end-to-end CR/CP revision and approval browser PASS while six voucher forms and their persistence are still incomplete. **This delivery-order section supersedes the earlier 01–09 sequencing table and all older 'Phase 04 LOCKED until Phase 03.3 PASS' notes below.** Prior milestone IDs, existing code, browser evidence and historic notes remain unchanged for traceability. A reordering is NOT a browser PASS and does NOT complete Phase 03.3.

| Delivery order | Phase / gate | Scope | Current state |
| --- | --- | --- | --- |
| 01 | Cheque Management | Receipts, verification, clearing and cheque source evidence | COMPLETE, owner PASS |
| 02 | Online Transaction Management | Receipt/payment confirmation, eligible sources | COMPLETE, owner PASS |
| 03 | Voucher Foundation (completed portions) | 03.1 common UI, 03.2 approval settings, 03.3 Batch 1 and verified JV/guard sub-batches; preserve all existing code and evidence | FOUNDATION PRESENT; **03.3 not closed**, typography and 3B2A/B gates carried to Phase 05 |
| **04** | **Six Functional Vouchers — NOW FIRST ACTIVE IMPLEMENTATION PRIORITY** | **04.1 CR; 04.2 CP; 04.3 CCR; 04.4 CDR; 04.5 BOR; 04.6 BOP; 04.7 six lists, print, safe attachments and regression** | **NEXT**; each batch needs owner browser PASS before following batch |
| 05 | Final Voucher Approval & Lifecycle | Pending/Approved/Rejected, edit/reverse/delete safety and resubmission acceptance across actual voucher types; finish old 03.3 3B2A/B gates, JV typography gate, preserve history | DEPENDS ON Phase 04 functional voucher browser PASS |
| 06 | Bank Narration, Bank Movement & Linked Voucher History | Verified bank source-to-voucher links, bank deposit/withdrawal, linked audit and print, no duplicate posting | DEPENDS ON BOR/BOP and eligible Phase 01–02 sources |
| 07 | Plot Receive Payment Voucher | Cash and verified bank/cheque plot receipts, multi-plot allocation, printable receipts | DEPENDS ON functional cash/bank vouchers and bank links |
| 08 | Plot Purchase & Auto Purchase Voucher | Seller, plot cost/inventory and auto voucher audit/reversal | DEPENDS ON voucher and ledger safety foundation |
| 09 | Cheque & Online Dashboard | Live source and voucher reporting, counts, filters, drilldowns | DEPENDS ON settled source-to-voucher relationships |
| 10 | Cross-Module Acceptance & Delivery | Regression, authorizations, accounting, duplicate/source uniqueness, historic data | DEPENDS ON 01–09 owner PASS |

### Phase 04 implementation order and acceptance
- **04.1 CR** — existing cash/online/cheque conditional behavior, create validation fixes, real CR source and ledger, modern form, list, print, permission and appropriate approval-pending guard.
- **04.2 CP** — existing cash/online/cheque, selected pending receipt and locked amount, Pass/Return/Bounce clearance semantics, real CP saving and list; do not convert clearance actions into BOP.
- **04.3 CCR** — new real persisted CCR type; cash-only; no bank/cheque/online fields; list, print, correct cash incoming direction.
- **04.4 CDR** — new real persisted CDR type; cash-only; no bank/cheque/online fields; list, print, correct cash outgoing direction.
- **04.5 BOR** — real persisted BOR; online/cheque ONLY; incoming confirmed/cleared source required, immutable source amount, one-time link and bank posting protections.
- **04.6 BOP** — real persisted BOP; online/cheque ONLY; outgoing confirmed/cleared source required, immutable amount, unique source link and correct payment direction.
- **04.7 Shared completion** — all six independent type-filtered listings, modern jQuery UI, print, optional safe attachment/file upload, complete validation, accounting and historical regressions.
- **All six show the same eight fields:** Voucher No, Date, Amount, Account Type, Accounts, Child Account, Attachment/File Uploader (optional), Description/Narration (required). See [canonical voucher spec](./POINT03_CR_CP_CCR_CDR_BOR_BOP_FUNCTIONAL_SPEC.md).
- Implement basic approval/ledger protections within each voucher batch to prevent unsafe posting; Phase 05 owns full cross-type approval, revision and resubmission browser acceptance. No bypassing existing security, source checks, V2 pilot guards, or mutation policies.
- No production data migrations or mutation without explicit authorization. A browser HOLD is not PASS. Owner's message authorizes **reordering**, not inventing browser evidence.

### Re-sequenced gating rule
Phase 04 is the next authorized implementation priority even though the **historical** Phase 03.3 end-to-end browser gate is still open, because its remaining CR/CP tests depend on Phase 04 functionality. Run Phase 04 batches in order, with owner browser PASS required **for each completed batch** before progressing. Old 03.3 acceptance is deferred—not waived—to Phase 05. Other later phases stay gated on their prerequisites and owner PASS. All older milestone tables below are preserved as historical records, not the active execution order.

---

## Historical original sequence (superseded by ACTIVE DELIVERY ORDER above)

## Strict sequence and browser gates
A phase is complete ONLY when (1) its milestones and relevant automated tests pass, (2) owner performs real browser testing on their running application, (3) failures are repaired/retested, and (4) owner explicitly confirms **PHASE PASS**. A failed milestone or phase blocks every subsequent milestone/phase's implementation, including cross-phase dependencies. Record browser environment/URL, scenarios, observed outcomes, date and tester. "Source inspected" is NOT browser PASS.

| Order | Source sections | Phase | Milestones (each with code check + browser gate) | State |
| --- | --- | --- | --- | --- |
| **01** | **E** | **Cheque & Online Transaction Management — CHEQUE scope only** | **01.1** Existing source, ownership, permissions and safe cheque-entry flow; mandatory Purpose/Description and original amount identity. **01.2** Cheque Receiving Slip view/print with live status, no financial posting on receipt. **01.3** Send for Bank Clearing vs Direct Bank Withdrawal, fixed amount, person/CNIC where applicable and bank details. **01.4** Cleared/returned/memo/withdrawn states, mandatory server-backed verification popup, actor/time/audit history; eligible-source gating; full phase browser regression. | COMPLETE — owner browser PASS |
| **02** | **F** | **Online Transaction Management** | **02.1** Receipt/payment source capture, fields, proof and reference uniqueness. **02.2** Pending→Confirmed confirmation popup, server-side authorization, bank verification, actor/time and immutable amount. **02.3** eligible confirmed source exposure for future bank vouchers, no pending/duplicate posting, history and full browser regression. | COMPLETE — 02.1, 02.2 and 02.3 owner browser PASS |
| 03 | A + B | Common Voucher UI and Voucher Approval Settings | **03.1** standard views/print and shared fields/filters, **03.2** per-type configuration and approver permissions, **03.3** pending/approve/reject/remarks/history and no posting before approval. | ACTIVE — 03.1 and 03.2 PASS; 03.3 Batch 1 PASS, Batch 2 typography browser gate PENDING; Batch 3B2A/B HOLD |
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

## Active Phase 03.3 checkpoint (2026-11-11)
- **Batch 1 — Approver permissions/authority:** owner browser PASS.
- **Batch 2 — Status visibility, legacy JV correction, blue JV listing, internal-only table scroll, Add/Edit modal:** implementation committed; owner confirmed Add/Edit alignment browser PASS. **Latest font adjustment** `d8289e98` code committed but browser PASS not yet reported; finish that gate first.
- **Batch 3 — Pending/Rejected JV/CR/CP edit, reverse/delete and resubmission safety:** NEXT after Batch 2 gate; start with source/database lifecycle audit. No premature schema change.
- Canonical new-chat continuation instructions: [Active Point03 handoff](./ACTIVE_POINT03_HANDOFF.md).

## Phase 03.3 Batch 3 — progress 2026-11-11
- **3A — Legacy mutation safety:** Implemented code safeguards for approval-tracked JV/CR/CP; owner browser test pending. All approval statuses (pending/approved/rejected) block direct legacy Edit/Reverse/Delete, regardless of is_active. Untracked legacy vouchers unchanged.
- **3B — Audited revise-and-resubmit:** NOT STARTED; requires explicit safe revision identity/audit design and browser pass for 3A.
- Do not mark Phase 03.3 complete; Phase 04 remains locked.

### Phase 03.3 — 3B1 (2026-11-11)
- **3A owner browser PASS.**
- **3B1 rejected JV revision & resubmit** coded, browser gate pending; reuses unique approval row and appends historical audit event, never activates ledger before re-approval. CR/CP resubmission **not implemented** (3B2), Phase 04 remains locked.

### Phase 03.3 3B2A — 2026-11-11
- Batch 3B1 owner browser PASS. Rejected cash CR/CP append-only revise/resubmit **implemented, browser PASS pending**. Bank/online/cheque resubmit 3B2B pending separate clearance-safe audit. Phase 04 remains locked.

### Phase 03.3 — 3B2B (2026-11-11)
- Owner places 3B2A browser testing **ON HOLD** due to existing CR/CP create validation errors, explicitly authorizes 3B2B next. Bank/online/cheque rejected revision code prepared; only unsettled and inactive sources eligible. Full browser gate held until CR/CP validation fixed. Phase 03 NOT complete. 04–09 remain locked.

### Remaining acceptance work after 3B2B (checkpoint 2026-11-11)
- Phase 03: fix CR/CP create validation blockers (not yet diagnosed); run 3B2A and 3B2B browser/DB scenarios; cover negative rejected/approved/pending, clearance and financial-history regression, QA and close 03.3/Phase 03 only after owner PASS.
- Phase 04 (04.1–04.4): cash CR/CP mapping, verified-source bank receipt/payment, bank deposit/withdrawal, debit/credit balance and regression.
- Phase 05 (05.1–05.3): plot receiving and bank/cheque allocations.
- Phase 06 (06.1–06.4): plot purchase and automatic purchase accounting.
- Phase 07 (07.1–07.3): narration and bank-linked voucher history.
- Phase 08 (08.1–08.3): cheque/online dashboard.
- Phase 09 (09.1–09.3): end-to-end acceptance and delivery.

## 2026-11-11 — Clarified six-voucher type requirements
- Owner specification: docs/POINT03_CR_CP_CCR_CDR_BOR_BOP_FUNCTIONAL_SPEC.md (source-inspected, documentation only).
- CR/CP retain original conditional Cash/Online/Check flows and CP pending clearance, modern UI only. CCR/CDR require real database types and seven visible fields including mandatory Details / Description (only bank/cheque/online-related inputs removed). BOR/BOP require real database types, Online/Check only, created from eligible confirmed online or cleared cheque sources, with six independent voucher lists.
- Current app only persists CR/CP source and ledger types; other four are display series. Phase 04 implementation cannot be marked complete. CR/CP create validation blocker and 3B2A/B browser HOLD remain unchanged.

- Owner correction: CCR/CDR keep **Details / Description**. Their seven visible fields are Voucher No, Date, Amount, Account Type, Accounts, Child Account, Details. Previous six-field wording superseded.

## 2026-11-11 — Latest owner correction: eight common fields for SIX voucher forms
- Supersedes earlier six/seven-field summaries: CR/CP/CCR/CDR/BOR/BOP all show **Voucher No, Date, Amount, Account Type, Accounts, Child Account, Attachment/File Uploader (optional), Description/Narration (required)**. CCR/CDR hide only bank/cheque/online-specific fields; CR/CP and BOR/BOP preserve previously specified conditional flow. Full canonical spec updated; docs only, no application code changes.
