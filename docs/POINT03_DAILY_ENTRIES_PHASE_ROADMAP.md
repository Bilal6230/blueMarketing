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

## 2026-11-11 — Existing six-voucher UI: CR/DR full-method wiring batch (browser gate OPEN)
- Owner screenshots confirmed the SIX existing modern Daily Voucher Directory pages and Bootstrap modal are the *only* UI surfaces to modify. **No new voucher forms/pages/routes have been created** in this batch.
- CR New Voucher now loads existing unfixed Cash In route with `series=CR&embed=1`; DR loads existing unfixed Cash Out route with `series=DR&embed=1`, enabling the original Cash/Online/Cheque conditional fields instead of hard-coding Cash mode.
- VoucherController admits CR/DR series for all 1/2/3 methods and labels the existing embedded form Credit Voucher / Debit Voucher while preserving all old settlement paths.
- LedgerController accepts and validates DR display series against CP's legacy source type for all methods; **legacy DB type CP is preserved**, not mass-renamed or converted. CR stays CR source. Approval and V2 safety branches unmodified. The UI/identity and storage type are deliberately not conflated.
- DailyVoucherDirectoryController CR/DR directory filter now includes all payment methods, and DR still recognizes historic CP series; existing legacy history remains available. **This does NOT implement real DR persisted transaction_type nor CCR/CDR/BOR/BOP backend posting.**
- Pending in subsequent gated batches: uploader and secure persistence (all six), actual CCR/CDR ledger types and separate lists, verified-source BOR/BOP and idempotent bank posting; DR typed migration compatibility analysis; full browser acceptance. Browser verification has NOT occurred; no production migration or writes. Owner browser PASS required before another functional batch.

## 2026-11-11 — Rejected CR/CP revision root-cause repair; approved mutation gate OPEN
- Owner browser evidence: rejected CR revise shows FINANCE_SOURCE_OWNER_MISMATCH; approved CR missing Edit/Delete; revised modal lacks Account Type and Head Account. No owner browser PASS yet.
- Root cause: FinancialSourceOwnership::assertLinked required active original customer_ledger even when RejectedCashVoucherResubmission intentionally creates both a NEW inactive replacement source and a NEW inactive ledger pending approval. Updated ownership rule to permit only a matching inactive source + inactive new ledger; active ledger with inactive source still forbidden; type/project ownership guards remain. No old financial data touched.
- Rejected revise form now renders read-only voucher identity, read-only original payment method, editable Date/Amount, Account Type, Head Account, Child Account with jQuery cascading, conditional bank/cheque inputs, Description and mandatory Reason for revision. Controller supplies scoped account type/head metadata. Corrected resubmit accepted CR/DR display series for all relevant modes.
- **Approved voucher Edit/Delete remains UNSOLVED, deliberately not re-enabled via old mutation routes.** Existing VoucherApprovalMutationGuard blocks approved-source legacy Edit/Delete; a safe auditable approved correction / soft-delete (no hard delete, mandatory reason, bank lifecycle reconciliation, ledger/source deactivation, approval history preservation) must be implemented as a dedicated workflow across JV/CR/DR/CCR/CDR/BOR/BOP. Merely exposing buttons would lead to 422 or unsafe financial changes.
- No local PHP/runtime/browser tests performed; requires owner CR rejected revision retest including pending approval and approval posting, account dropdown cascades and bank instrument negative cases. Approved editing/deletion remains a blocking requirement before this batch can be considered complete.

## 2026-11-11 — Approved CASH CR/DR correction and audited soft-delete code batch (browser UNVERIFIED)
- Existing modern Daily Voucher Directory now exposes Edit for permitted approved **cash** CR/legacy CP-as-DR sources. Same revision modal accepts approved original and mandatory revision reason, creating a separate inactive pending source/ledger/approval. The original approved cash source stays active if pending/rejected; on replacement approval `DailyVoucherApprovalWorkflow` atomically deactivates original cash source + matching ledger, writes an immutable `superseded_by_approved_correction` event, and activates replacement. Only same project/source type/one active leg with original approval status approved is accepted. No in-place changes to amounts.
- Approved cash source Delete button available to holders of `delete voucher`; `LedgerController::destroy` writes a required-reason `approved_cash_soft_deactivated` audit event and sets the original source and linked ledger `is_active=0` in one DB transaction. Never hard delete. Old pending/rejected approval mutation guards remain in force.
- **LIMITATIONS:** Bank/cheque approved corrections and deletion remain blocked because settled instrument reversal/linkage needs a dedicated verified workflow. JV/CV and new real CCR/CDR/BOR/BOP approved mutations are NOT implemented in this patch. The owner requested all voucher types; this remains PARTIAL. Secure attachment uploader/storage remains pending.
- **Testing:** Remote GitHub code commits only, no PHP executable/browser/DB tests run here. On local disposable data verify: reject CR revise/approve; approved cash edit → pending → approve old becomes inactive; reject replacement old stays active; approved cash delete leaves all rows present but inactive with event/reason; cannot edit approved bank/cheque or tamper cross-project; duplicate submission guard.

## 2026-11-11 — Modal parity, canonical numbering, approval reason visibility (CURRENT BATCH, browser gate OPEN)

**Owner screenshots:** CR Add uses modern three-column modal; approved cash Edit, rejected Revise and historical Posted Edit were visually different. Requirement: same modern layouts across existing six pages, mandatory `Reason for Edit` or `Reason for revision`, resubmit to approver when policy enabled, show saved reasons to approver/user, and use the canonical DB source/ledger number (not configurable display prefix). NO new forms/routes/pages.

**Code changes committed:**
- `rejected_cash_revision.blade.php`: approved and rejected forms aligned to the compact three-column Add order, dynamic status colors, voucher/date/amount, account type/head/child account cascading, source payment method, bank/cheque conditional fields, narration and required dynamic reason field.
- `cash_voucher.blade.php`: embedded historical Edit layout restyled to same 3-column structure, CR/DR titles and narration labels, mandatory `edit_reason` jQuery payload (220-character bound).
- `LedgerController::update` validates Edit reason; `LegacyCashVoucherCorrection` retains reason on its reversal source `note`, preserving old narration. When a policy is enabled for **legacy untracked CASH** CR/CP, creates inactive reversal and corrected posting with a new Pending approval instead of direct active posting; no approval policy retains existing append-only corrected posting. Policy-on bank/cheque method changes blocked until safe instrument reversal is designed.
- `DailyVoucherApprovalWorkflow` reviews the legacy correction event and, on approval only, atomically activates exactly one same-project inactive reversal leg plus the corrected posting; original stays posted and is offset by reversal. Rejection leaves original posted and both correction legs inactive. `VoucherApprovalMutationGuard` prevents original mutations/deactivation while correction Pending.
- `approvals.blade.php` displays explicit Reason for Revision/Edit in pending and history (from existing immutable approval event JSON), and Rejection Reason in history; `DailyVoucherDirectoryController` exposes a rejected owner's reason in Details, while status badge tooltip shows it.
- `ProjectVoucherDisplayIdentity` now uses persisted `customer_ledger.type_id` for **CR and legacy CP-as-DR** instead of configurable project fiscal prefix; `ProjectVoucherDisplay::SERIES` admits DR; historic DB records are NOT renumbered/migrated. Directory and Edit readouts align through canonical source identity. `VoucherController` CR/CP form preview reads max source, ledger and existing counter, never gap-fills. `LedgerController::store` rejects a stale visible number before creating source/ledger; CP clearance creation also checks it. Existing display-number records remain for compatibility.
- Browser/DB acceptance **NOT EXECUTED HERE**. Test fresh CR/DR Add, legacy Posted Edit with approval OFF and ON, approved Edit, rejected Revise, mandatory reasons and account dropdowns, approver Reason/Rejection columns, ledger offset integrity and no duplicate posts, concurrent stale-number rejection, and rejected/approved money/status history. Verify bank/cheque old flows are unchanged. Run PHP lint/tests locally before using live data.

**Known remaining scope:** six-type real CCR/CDR/BOR/BOP persistence, attachment file uploader/storage, audited bank/cheque approved mutations, broad JV/CV edit/delete parity and complete end-to-end six-voucher workflow acceptance. Do not claim PASS or advance next milestone until owner browser PASS. Finance identity mutations are append-only or soft deactivate; no hard delete in this batch.


## 2026-11-11 — Active acceptance gate: unified CR/DR Posted/Approved/Rejected Edit
Old Posted and new Approved were rendering different Edit forms. Both are now wired to the existing same corrected revision Blade/modal (no new pages). Required reason shown for Edit vs Revision; approval policy ON submits Pending, OFF uses audited direct correction for supported cash sources. Super Admin Approved By column uses decided_by user and is hidden for other roles. **Code committed; browser and DB verification NOT PASSED.** Bank/cheque approved edits, JV/CV, complete CCR/CDR/BOR/BOP posting, attachment storage and full integration remain outstanding. Current gate must pass before proceeding.

## 2026-11-11 — OWNER BROWSER BLOCKER: SQL 1406 approval events / correction identity (CURRENT)
- Owner screenshots from project #3: approvals for pending cash CR corrections failed with MySQL SQLSTATE[22001] 1406 `Data too long for column event`. Schema migration `2026_11_05_000001_create_daily_voucher_approval_workflow_tables.php` declares `daily_voucher_approval_events.event` as varchar(24). Last batch used oversized strings `legacy_cash_correction_activated` and `superseded_by_approved_correction`; approved soft-deactivation and direct approved correction had other oversized strings.
- Fixed event codes **without modifying DB schema**: `legacy_corr_activated` (21), `approved_corr_superseded` (24), `approved_cash_void` (18), `approved_cash_direct` (20); preserved `remarks` JSON provenance and transaction boundaries. Existing consumed event codes `legacy_cash_correction`, `revision_from_approved`, `revised_to_new_source` remain unchanged. Source code inspection confirms changed constants <=24 chars.
- No local database DDL/migration necessary for this fix. The failed transaction should roll back; owner must retry pending decision after pulling and clearing caches, then verify source/ledger activity, approval event, and old/new balance. **Browser and DB PASS remain unverified.**
- User also asked why Edit/Revise creates new rows. Current design intentionally uses append-only financial lineage: existing source/ledger identities are never overwritten; a replacement source/ledger is Pending and linked by immutable events. When approved, a previous approved source is soft-deactivated and new posting activated; historical Posted records are offset by separate reversal rather than destructively editing. This creates distinct database rows and voucher numbers, and is *not* an accidental duplicate money posting when active posting safety holds. It does create separate listing entries, which requires a separate owner-approved design for logical revision grouping if desired. Do not silently mutate original financial rows or promise 'same database row' without a full audited migration/ledger redesign.
- Keep current batch gate OPEN. Test approvals for pending CR source ids shown in owner browser (e.g. #5642, #5644), and test rejection, approved cash soft deactivate and direct edit on disposable records; no production mutation without owner approval.


## 2026-11-11 — Owner mandate: SAME-RECORD voucher Edit/Revise (active acceptance gate)

Owner explicitly rejected append-only correction visible as duplicate voucher rows (e.g. old CR-709 generated CR-834 reversal and CR-835 replacement). **New target behavior:** Edit/Revise must retain the existing customer_ledger.id, type_id/voucher number, and original linked ledger.id. Approval ON => SAME voucher shows Pending and proposed values, with original financial posting unchanged until decision; approval OFF => SAME source and ledger change in one atomic transaction. Immutable before/proposed snapshots and reason are audit records, NOT new vouchers.

Implemented for existing CR and legacy CP-as-DR **Cash payment type only**:
- New additive migration `database/migrations/2026_11_11_000030_create_daily_voucher_edit_snapshots.php`. Must be run on a backed-up **local test database** with `php artisan migrate --path=database/migrations/2026_11_11_000030_create_daily_voucher_edit_snapshots.php` before testing.
- New `SameSourceCashVoucherEdit` service with project/source/ledger account ownership, one linked leg and amount checks, active/rejected status safeguards, no pending-duplicate edit, original source and ledger identity preserved. Changes saved in pending snapshot with before/proposed data and required reason, not posted early; `same_source_edit` event links to approval. When approved, guarded transaction updates same customer_ledger and same linked ledger, marks snapshot applied; when rejected, leaves original posting intact, marks snapshot rejected and restores previous status. If approval OFF, same two rows updated immediately and one audit snapshot recorded.
- Both existing Daily Voucher modal POST and legacy Cash In/Out Edit POST now use same service. Legacy append-only correction service remains for **already-created old correction requests/forensics only**, not new UI edits. No new voucher numbers, reversal rows, or source rows created by new same-ID Edit/Revise.
- Directory shows proposed pending Date, Amount, Account, Narration, with Pending status and explicit Details audit warning that actual prior accounting posting remains unchanged until approved. Approvals show same_source_edit reason. Approver controller returns true old status when a proposed edit is rejected, with explicit message that old posting remains.
- Any old source with previously created reversal/correction chain is blocked from new same-ID edits until reconciled. **Historical CR-834/CR-835 or equivalents are not auto-deleted/merged or hidden.** Their old approved active postings require a separate, audited, one-time financial reconciliation after read-only DB evidence. Never hard delete.
- **NOT COMPLETE FOR BANK/CHEQUE or other voucher families**, as instrument settlement and real CCR/CDR/BOR/BOP typed posting still need independent integrity work. No browser PASS, migration execution, PHP lint, test DB mutation or production verification performed in this GitHub-only batch; owner must test on disposable local data. Do not move milestone until owner PASS.


## 2026-11-11 — Same-ID edit delivery safety/CI follow-up
- Both old legacy cash editor POST (`LedgerController::update`) and Daily Voucher Edit/Revise POST now call `SameSourceCashVoucherEdit`; the previously append-only old edit endpoint is no longer active. New same-ID audit checks include source transaction type, identity, original source reference, ledger voucher number, original amounts/account, linked-ledger uniqueness and same-project ownership before applying approved changes.
- Approval history/queue displays latest `same_source_edit` reason; rejected pending edit restores previous original status/approved actor while recording decision event; direct revision of an old rejected voucher with approval disabled returns to Posted. Pending proposed fields can be displayed in listing, but actual underlying ledger remains unchanged until approval.
- Previous correction chain with active reversal/replacement (example owner CR-709/834/835) is NOT auto-merged/removed; automated in-place edits on such original source are refused until a separate reviewed financial reconciliation.
- GitHub finance-safety CI run `43014820613`: posting-form JS test and PHP lint PASS after adapting isolated test harness to current first-party jQuery. Overall run FAIL because existing V2-only baseline missing `project_bank_accounts` and V2CashPostingWriter SQLite fixture missing `head_accountings`; these are independent of this same-ID edit implementation and were not changed here. No isolated same-ID DB feature test or owner browser PASS yet. Additive migration needed locally before browser test.


## 2026-11-11 — Owner UI request: approval Voucher No and comprehensive History modal (browser gate OPEN)
Owner screenshots showed Pending Approval Queue columns Voucher Type and Original Source exposing technical IDs (`customer_ledger #5645`), and Recent Approval History showing Source IDs. Requirement: replace BOTH with a single user-facing Voucher No such as `CR-836`, `DR-...`, `JV-111`; History should contain complete read-only voucher details like Directory Details dialog.
- `VoucherApprovalDetailReader` reads project-scoped customer_ledger/ledger or journal_vouchers/journal_voucher_details, resolves source type_id or saved display number with CP→DR, shows voucher date, reference, amount, narration, payment/bank metadata, accounting lines, status and audit provenance. Source IDs are internal only.
- `DailyVoucherApprovalController::index` preloads per-item details and event actor display names for queued and recent approvals, and passes those to Blade.
- `approvals.blade.php`: replaces 2 technical columns with a single Voucher No in queue and history, updates colspan, decision modal display, wider History modal.
- Reusable read-only `partials/approval_detail.blade.php`: date, voucher number, amount, account ledger lines, debit/credit, payment details, narration, rejection/edit reasons, created/updated, submitted/assigned actor, readable audit timeline, and last 25 before/after same-source edit snapshots. Pending same-record edits explicitly distinguish proposed values from original posted ledger lines. All HTML dynamic values Blade-escaped; UI JS stays first-party jQuery.
- **No voucher numbering or database posting changes** in this batch. No owner browser PASS; lint/integration not locally executed. User must verify Pending CR and JV numbers and Details/History modal, permission/project isolation and pending proposed-vs-posted values. Remain in same milestone until browser PASS.


## 2026-11-11 — CR-836 rejected Edit incorrectly shown Approved: FIX, browser retest pending
Owner reproduced: approved CR-836 was edited (same source, Pending), then approver REJECTED the proposed edit. Source approval status reverted to `approved` to preserve the original active posting, but recent decision history used `daily_voucher_approvals.status` and incorrectly showed **Approved** for the latest rejection. This is a status semantics/UI bug, not proof the rejected edit was posted.
- `DailyVoucherApprovalWorkflow` now writes `edit_rejected` or `edit_approved` audit event (each under event varchar(24)) for SAME-source edits, instead of ambiguously reusing plain `rejected` / `approved`. Original posting status still reverts to previous status on rejected edits, preserving financial data.
- `DailyVoucherApprovalController` reads most recent decided `daily_voucher_edit_snapshots` record by approval ID (including existing CR-836 rejected snapshot). Recent Approval History shows red **Edit Rejected** decision with **Original Approved** context, not a false approval decision. Applied edited snapshots show Edit Approved.
- `VoucherApprovalStatusReader` and CR/DR directory expose latest decided edit status/reason. CR listing shows original green Approved plus red **Edit Rejected** badge, and Details includes the edit rejection reason. Original ledger/source remain posted at previous values; no new voucher or hard-delete.
- Test on backed-up local database: confirm CR-836 latest Edit Rejected in History (and original Approved), CR listing twin badges; view Details audit reason; check source/ledger amount unchanged. Also edit a fresh approved cash CR, reject, verify same ID/voucher number, audit snapshot status rejected, no posting duplication; approve a later edit and confirm Edit Approved. PHP/DB/browser checks not run by assistant; owner browser PASS required. No other milestone started.


## 2026-11-11 — Phase 04: CCR + CDR real cash-only voucher batch (code completed, owner browser HOLD)
Owner required modification of the existing six modern Daily Voucher pages, NOT new pages. CCR/CDR now use the existing `daily-vouchers?type=CCR/CDR` listing and existing Cash In/Out embedded modal, but new **real** `CCR`/`CDR` customer_ledger.transaction_type and ledgers.type (not CR/CP aliases). Both are cash-only; payment-type selector and ALL bank/cheque-specific fields are absent from these forms. Hidden method=1 is server-validated; all eight agreed fields include voucher number, date, amount, account type, accounts, child account, optional attachment and required narration.

Implemented:
- `CashOnlyVoucherController::store` project-locked transactional CCR/CDR source and linked ledger creation; true series-specific number allocation using ProjectVoucherAllocator; stale/mismatched number rejection; account/project/type validation; no bank/cheque methods or fields; preserve V2 original pilot guard; normal approval-policy ON creates Pending with inactive source/leg, OFF posts active. Optional upload (PDF/image <=5MB) stored privately using local disk, metadata in `cash_voucher_attachments`; on transaction failure file cleaned up. GET attachment route checks read-voucher permission, project ID, source type and private file existence.
- `VoucherController` now renders CCR/CDR titles, actual voucher number preview, correct series-specific form type, and accepts CCR/CDR numbers in existing check-new-number endpoint.
- `cash_voucher.blade.php` existing modal now hides bank/cheque/payment selector for CCR/CDR, posts to guarded cash-only endpoint, exposes optional attachment and accepts CCR/CDR voucher number validation, preserving first-party jQuery and existing modal/list design.
- `ProjectVoucherDisplayIdentity` uses stored source numeric CCR/CDR identity (not configurable prefix) and checks source/series match; `DailyVoucherDirectoryController` filters by ACTUAL CCR/CDR transaction_type and shows same-series rows only. `FinancialSourceOwnership` now verifies CCR/CDR source/ledger identity, cash-only method and project ownership.
- `SameSourceCashVoucherEdit`, `RejectedCashVoucherController`, directory action URLs and LedgerController soft-deactivation extended to CCR/CDR (same-ID Edit/Revise with policy ON/OFF, mandatory reason, active/pending guard, never hard delete). `DailyVoucherApprovalController` approval settings accepts CCR/CDR independently.
- `VoucherApprovalDetailReader` and its existing partial display authorized private attachment link; Daily Voucher Details shows attachment link without a new page.
- New additive migration `database/migrations/2026_11_11_000040_create_cash_voucher_attachments.php`. On backed-up disposable/local DB run: `php artisan migrate --path=database/migrations/2026_11_11_000040_create_cash_voucher_attachments.php`. Earlier same-ID snapshot migration remains required for Edit/Revise.
- **Browser HOLD**: no owner PASS and no local DB run. GitHub finance-safety CI PHP lint + UI posting test passed on earlier push; overall CI continues failing unrelated V2 schema fixture/baseline. Do not claim full acceptance until CCR/CDR create (with and without attachment), actual distinct types/number/cash direction, display, download, approval ON/OFF, same-ID revision, soft-delete, print, project isolation and negative bank/cheque input are browser/database verified.
- Remaining limitation: existing Save as Draft behavior in embedded form has not been independently verified for CCR/CDR; file upload on *Edit/Revise* is not yet implemented (create upload works). BOR/BOP deferred until owner browser PASS. Do not auto-modify historical CR/CP voucher rows.


## 2026-11-11 — CCR/CDR draft workflow and CI status update
- Additional guarded `CashOnlyVoucherController::draft` POST and existing modal `saveAsDraft()` jQuery wiring. Creates a `draft_ledgers` row tagged real CCR/CDR type and correct cash direction without inserting customer_ledger/ledgers or reserving a final posted voucher number. Final Save resets form action back to audited CCR/CDR posting route, avoiding draft-action leakage. Draft bank/cheque fields forbidden and Account Type/account mapping verified. Temporary attachments on drafts explicitly rejected (no silent file loss); attach during final Save. Draft listings/reopen/edit workflow not separately acceptance-tested.
- Full code now includes existing CCR/CDR modal real Save, project-based private optional file upload/download from Details and approval history, independent series numeric identity, listing by actual CCR/CDR transaction_type, source-to-ledger ownership guard, project approval policies, same-ID cash edit/revise and soft deactivation. No new UI pages.
- Latest observed GitHub finance-safety run `43023439554`: app boot/routes smoke, PHP financial lint and jQuery posting safety all PASS; overall status FAIL due existing V2-only disposable MySQL migration baseline and V2 isolated schema test setup. Those gates were not modified as part of CCR/CDR. **No local Laravel/MySQL end-to-end test or owner browser PASS yet.**
- Before browser testing ensure backed-up disposable local DB ran additive `2026_11_11_000040_create_cash_voucher_attachments.php` migration and previously required edit snapshot migration. Acceptance includes CCR/CDR distinct source/ledger types, cash-only eight fields, approval ON/OFF, edit pending and same-ID update, reject restore, Edit/Delete reason and soft-delete, file download permissions, Save as Draft (without attachment) and final Save. BOP/BOR next only after owner PASS. Edit/Revise file replacement is not yet supported; do not claim full upload parity.
