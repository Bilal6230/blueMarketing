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

## 2026-11-10 — Approved new policy / implementation batch 1
- User approved implementation of role permission `voucher approver`, assigned-only queue for normal approvers, and universal Super Admin decision authority. Current code's original `super-admin` check mismatched the actual `superadmin` role seeded in `RoleSeeder`.
- Created additive idempotent permission migration **without silently assigning roles**, and shared `VoucherApproverAccess` policy for consistent Super Admin and eligible normal approver checks.
- Voucher Settings restricted to Super Admin for edits; eligible list now requires active user, current project and `voucher approver`. Existing policy values unchanged.
- Approval queue/history now filtered by assigned_approver_id for non-superadmins. Super Admin can approve/reject all pending project requests including own, with existing locked transaction and audit evidence; normal approvers cannot approve own requests.
- Preserved `daily_voucher_approvals.status` for pending/approved/rejected, `ledgers.is_active` for accounting effect and deletion separate. No ledger schema changes or preexisting approvals changed.
- IMPORTANT staged rollout: existing assigned approvers need voucher approver permission assigned through Role/Permission UI; until granted, they cannot act. Browser testing required before proceeding to status badges and further flow changes.

- Safety review correction: restored `VoucherController::canAccessFinanceApprovalsForUser` legacy `read voucher + super-admin/direct-update` policy (with `superadmin` spelling support) because this helper also guards unrelated finance actions. New `voucher approver` authority applies only in the dedicated voucher-approval controller/workflow. Approval dashboard route accepts either read voucher or voucher approver, with project-access check for non-superadmins.

- Fixed narrow Eloquent approver projections in both controllers: fetched `project_id` and `status_id` alongside `id,name` before `VoucherApproverAccess::eligibleForAssignment` checks, preventing the authorized dropdown from appearing empty due to missing model attributes.

## Batch 2 — 2026-11-10
- Batch 1 browser PASS confirmed by owner. Implemented read-only project-scoped approval status resolver; JV listing and Daily Voucher Directory show Pending/Approved/Rejected explicitly instead of inferring approval from is_active.
- Inactive CR/CP source appears only if pending/rejected approval exists, not merely because is_active is zero; soft-deleted records remain excluded. JV save flash explicitly reports Pending approval where an approval was just submitted.
- No ledger writes or schema changes. Browser PASS pending; next workflow lifecycle changes blocked until testing.

- Post-commit review: JV save banner is now keyed to exactly the transaction-returned voucher id (including durable-request replay), never to some other recent pending voucher created by the same user. Updated directory skeleton from 8 to 9 columns and JV conditional colgroup to match the new approval status column.

## 2026-11-10 — Owner screenshots / JV legacy status and modern UX
- Root cause: legacy `journal_vouchers.status` is not approval state. Removed fallback that incorrectly labeled legacy posted JVs Pending. Only matched project-scoped approval rows control Pending/Approved/Rejected; no approval record => Posted. Directory has same fix.
- Replaced legacy JV index presentation with blue-accent modern filter toolbar in one row, compact DataTable with per-column filters, status badges and icon action buttons. Add/Edit open the unchanged original forms inside a Bootstrap modal using same-origin iframe; fallback Open in new tab. Original validation, journal posting and reversal remain on their own existing routes.
- Create/Edit forms can render embed layout without duplicate sidebar/top navigation. No accounting migrations or ledger mutations.
- Needs browser testing for DataTables columns, modal save/redirect, edit/print, legacy fallback, filter search, approval statuses. Phase gate remains open.

## 2026-11-10 — JV page-only scroll correction
- Owner screenshot confirmed page-level scrollbar after modern JV list. Root cause: `min-height:calc(100vh - 130px)` plus layout footer and an unconstrained DataTables wrapper.
- JV viewport is now measured relative to actual top offset and visible footer by jQuery on load/resize. Body scroll disabled only on this route. Card flexes into remaining viewport, with DataTables table in dedicated internal `jv-record-scroll`; controls and pagination remain outside the scroller. Blue theme preserved, no global layout or posting logic changed.
- Browser retest required at 100% browser zoom and typical desktop viewport; test 25/50 rows, table internal scrollbar, footer visibility, pagination, Add/Edit modal. Batch 2 browser gate remains open.

## 2026-11-10 — Embedded JV modal spinner fix
- Owner screenshot: Add New Journal Voucher modal displayed endless blue circular spinner. Root cause: full master layout renders global `#load_screen.preloader` inside same-origin embedded form iframe, above the actual form. Added embed-only CSS to hide nested global preloader on both Create and Edit forms, and parent jQuery iframe-load handler explicitly hides it on successful document load. Parent overlay is still removed only after the `#journalForm` is detected; missing form produces actionable error with Open in new tab.
- Existing full-page route preloader unchanged; no accounting, permissions or ledger changes. Browser retest pending.

## 2026-11-10 — JV modal layout alignment
- User confirmed form loads, but screenshot showed narrow inset form within a wide parent modal. Embed-only CSS removes nested content/layout max-width and padding, makes form full-width, uses compact CR-style 3-column fields, blue accented table headers, reduced padding and right-aligned Save button. Keeps original journal form inputs, names, scripts, validation, debit/credit and save controllers intact. Parent modal width expanded slightly to 1180px while maintaining viewport bounds. Both create and edit embedded views updated; full-page routes unchanged.
- Browser re-test required for modal layout, dynamic Add/Remove row, Account/Subaccount dropdowns and save flow.

## 2026-11-11 — Embed offset and Edit route fix
- User screenshot showed embedded Add JV form displaced ~80px to right and cut off at modal edge. Corrected embedded (iframe-only) master-layout `.main-content.page-content` sidebar margin, position and width; reset wrapper width. Standalone create/edit views are unaffected.
- Actual Edit URL uses plural `/journal-vouchers/{id}/edit` while old jQuery delegated click selector used singular `/journal-voucher/`. Changed delegated selector to match the actual plural route (and legacy singular), with iframe modal reuse. Existing Print/Reversal handling unchanged.
- Needs browser retest for Add + Edit modal alignment/field interactions and existing print actions before gating next batch.

## 2026-11-11 — Embedded JV form still offset right (specificity fix)
- Screenshots show both Add and Edit iframe forms offset about one collapsed sidebar width (~80px), clipping right-hand Date and submit button. Root cause is AdminLTE's higher-specificity sidebar-collapse margin on the *nested .content-wrapper*; previous low-specificity CSS overrides and master .main-content reset did not defeat it.
- Added scoped, high-specificity embedded-only CSS for `body.sidebar-mini.sidebar-collapse .main-content.page-content > .content-wrapper`, nested .content/.container-fluid, and box-sizing. Parent jQuery iframe load handler also removes only iframe body layout classes and explicitly resets offset/width as defensive fallback. Standalone pages and parent sidebar are untouched, as are voucher save/ledger logic.
- Browser test: Add and Edit both aligned with equal inner gutters, Date and Save/Update entirely visible; no horizontal clipping.

## 2026-11-11 — JV font readability adjustment
- Owner confirmed embedded Add/Edit modal layout browser PASS. Increased font sizes slightly on JV listing, filters, column inputs, buttons and pagination, as well as embedded Add/Edit form labels, fields and debit/credit table. Blue theme, layout heights, scoped iframe alignment, and voucher accounting logic are unchanged. Browser recheck pending for font readability and clipping.

## 2026-11-11 — Documentation checkpoint / new-chat handoff
- Updated Phase 03.3 stage record: **Batch 1 browser PASS; Add/Edit modal alignment browser PASS; latest typography `d8289e98` pending separate owner browser confirmation**. Do not equate layout PASS with typography PASS.
- Batch 2 in final UI verification; main page must not scroll; only table card scrolls; avoid right-hand clipping in modal. No new PHP/backend changes in this documentation checkpoint.
- NEXT Batch 3: audit/edit/reverse/delete/resubmission restrictions and approval-state transitions for JV/CR/CP, preserve legacy posted records, soft-delete/void semantics, authority and journal trail. Not yet implemented. Phase 04+ locked.
- Full project/new-chat continuation: [ACTIVE_POINT03_HANDOFF.md](./ACTIVE_POINT03_HANDOFF.md).

## 2026-11-11 — Batch 3 started / safety gate 3A implemented
- Owner instructed to start next batch after previous JV UI fixes. Latest font-change browser PASS was not explicitly re-confirmed; treating request to proceed as go-ahead, NOT retroactively claiming a browser PASS.
- Audited JV update (creates immediate active reversal+new posting), JV destroy (reverses active ledger and deletes source/details), CR/CP update via `LegacyCashVoucherCorrection` (creates active reversal+correction), CR/CP destroy (deactivates source+ledgers). These legacy write paths are dangerous on pending/rejected/approved approvals.
- Introduced centralized read-only `VoucherApprovalMutationGuard`: if source has **any** `daily_voucher_approvals` row (pending/rejected/approved), legacy mutation fails with explicit `DomainException`, regardless of `is_active`. Checks occur inside locked source transactions where relevant. Legacy sources without approval rows retain existing behavior. JV Edit form now refuses approval-tracked records; JV listing hides Edit/Reverse for approval-tracked records but continues Print.
- This is protection only, **NOT** rejected-voucher revision/resubmission implementation. Need separately design and implement auditable revision request, source identity, approval events and activation; do not implement unguarded edit or reactivate old rejected ledger records.
- No schema, no existing database writes, no ledger activation changes. Manual browser gate for 3A pending before 3B resubmission.

## 2026-11-11 — Batch 3B1 Rejected JV revise/resubmit (code implementation)
- Owner explicitly marked Batch 3A browser PASS and requested continuing Batch 3.
- Discovered `daily_voucher_approvals` enforces UNIQUE(source_table,source_id): **resubmission must reuse the same approval row** and retain rejected decision as immutable `daily_voucher_approval_events`, not create a duplicate approval.
- Implemented `RejectedJournalVoucherResubmission` service for **JV only**: locked project-bound voucher and rejected approval, creator/Super Admin authorization, current valid approver policy, account/project checks, detail provenance, exact one-to-one inactive ledgers vs detail rows; reject mismatched, unproven, active or cross-project data. Transactionally update unposted original detail+ledger rows, retain previous header/line/ledger+rejection snapshot in `resubmitted` audit event, change same approval to pending, clear old decision fields. No ledger activation until normal approval `decide()`.
- Added `Revise` to rejected JV only for creator/Super Admin with edit permission, existing Add/Edit modal reused with `?resubmit=1`, required revision reason and POST route. Normal edit/reversal guard stays intact.
- No schema changes, no automatic approval, no existing production-data mutation by migration. **Browser testing PENDING**: rejected JV Revise/Resubmit, valid re-approval, duplicate submit should 409, pending/approved edit blocked, unauthorized project/actor blocked, original reject audit preserved. CR/CP resubmit NOT implemented in this sub-batch; separate gated 3B2 after PASS.

- **3B1 correction:** The Ledger model forbids updating account/amount/date. Replaced in-place revision with append-only new inactive detail/ledger records, archived previous detail rows and preserved original inactive ledger rows. Approval activates only current non-archived detail-linked legs, not rejected historic revisions. Browser gate pending.

## 2026-11-11 — Critical JV listing regression (3B1)
- User screenshot: JV page empty, DataTables toolbar/pagination absent after 3B1. Root cause: generated client-side handler had a malformed regex expression (`if(!url||!\\/\\/...`) without the opening JS regex slash, causing script parse error that blocked *all* DataTables initialization. Replaced with a simple jQuery-safe URL substring check (`url.indexOf('/edit')===-1`). The Edit and `?resubmit=1` actions remain delegated to existing modal. No backend, data or financial records affected.
- Browser verification pending: existing 127+ JV records, toolbar/pagination and Edit/Revise modal. Do not advance 3B2 without owner PASS.

## 2026-11-11 — Phase 03.3 Batch 3B2A Rejected cash CR/CP
- Owner confirmed Batch 3B1 browser PASS. Implemented guarded append-only rejected **cash** CR/CP Revise & Resubmit: original source, ledger and rejected approval preserved, new source/ledger inactive with new project/series ID, pending approval and cross-linked audit events. Creator or Super Admin authorization, one-to-one inactive ledger check, project account, approval policy, duplicate revision and amount guards. jQuery AJAX revision modal on Daily Voucher Directory, original tracked edit/delete buttons hidden. No schema migration, no live data mutation by deployment. Bank/cheque revision remains next 3B2B after browser PASS.

- Post-commit adjustment: rejected CR/CP revision creator authorization now uses immutable `daily_voucher_approvals.submitted_by` rather than source creator fields that may be absent on legacy cash sources. Read-only approval resolver exposes `submitted_by`; server/controller/directory all use consistent original submitter + selected-project checks.

- Final code audit: typed LedgerSourceAttributionWriter only accepts JV/CV, not cash CR/CP. Removed unsupported call from rejected cash resubmission; customer_ledger_id + project account remains canonical CR/CP source link and original ledger ownership guards still execute on create.

## 2026-11-11 — Batch 3B2B source-safe rejected bank/online/cheque CR/CP revisions
- User **explicitly paused** Batch 3B2A browser testing because new CR/CP creation has validation issues; HOLD is not PASS. User explicitly authorized next 3B2B implementation despite the prior gate. Existing cash revision service extended to 2/3 only for rejected inactive sources with no bank-posted date, no cleared/returned/bounced status, no subsequent cheque/online lifecycle event, exact one inactive ledger leg, project/actor/approval constraints. Enforces bank instrument fields, uniqueness against active vouchers, new pending source + ledger, original rejected history untouched. Bank/cheque fields and explanatory restrictions added to revision modal; status reset to pending clearance 0; preserves old/new bank reference in approval event. Browser testing 3B2A + 3B2B both HOLD pending resolution of CR/CP creation validation.

## 2026-11-11 — Owner clarified CR/CP/CCR/CDR/BOR/BOP requirements
- Original CR/CP routes and payment-type conditional logic inspected in LedgerController, VoucherController and cash_voucher.blade.php. Preserve old logic but modern UI. CP Online/Check has pending-clearance list with locked original amount and Pass/Return/Bounce.
- CCR/CDR are new actual stored database types with seven visible fields (Voucher No, Date, Amount, Account Type, Accounts, Child Account, mandatory Details); only bank/cheque/online inputs removed. BOR/BOP are new actual types and source-linked bank receipt/payment, Online or Check only, from confirmed Online Management or passed Cheque Management. All six types need independent lists.
- Important gap: current DB code persists CR/CP while CCR/CDR/BOR/BOP are display-series only, and current server requires detail. No feature implementation in this turn; full source-based spec saved at docs/POINT03_CR_CP_CCR_CDR_BOR_BOP_FUNCTIONAL_SPEC.md. Phase 03 testing HOLD.

## 2026-11-11 — Owner correction for CCR/CDR fields
- Previous six-field interpretation was incorrect. **Keep mandatory Details / Description** alongside Voucher No, Date, Amount, Account Type, Accounts, Child Account. Remove only bank/cheque/online-related fields. Updated functional spec, roadmap and handoff. No code change.

## 2026-11-11 — Final owner clarification for common voucher fields
- For all six CR, CP, CCR, CDR, BOR and BOP forms show eight common fields: Voucher No, Date, Amount, Account Type, Accounts, Child Account, Attachment/File Uploader (optional), Description/Narration (required). This supersedes all prior six/seven-field descriptions. Other method-specific behavior unchanged. No application/schema changes.

## 2026-11-11 — CR/CP create validation repair, targeted first batch
- CR/CP create form validator now safely converts nullable Tom Select values to strings (no null.trim crash), scoping all field lookups to #voucherForm, highlights missing values with inline errors, checks Payment Type 1/2/3, conditionally requires bank transaction number/bank/passing date, cheque purpose, and CP selected pending clearance/status. Cash mode does not require bank fields. Fixed voucher hidden/visible equality and ensured Tom Select fallback for non-enhanced selects.
- Server validation now enforces Account Type presence, integer account IDs, positive bank ID and ISO passing date, plus matching head Account Type. Accounting transactions and ledger posting untouched. Browser test required on CR Cash/Online/Cheque and CP Cash/Online/Cheque, including pending amount lock and approval.
- **Caution:** This does not claim to resolve all owner-reported validation problems without exact browser 422 payload. Batch 3B2A/B tests remain HOLD. Next gated stage is optional uploader on six voucher forms + real type rollout only after safe schema audit and owner PASS.


## 2026-11-11 — Owner-requested dependency-first phase reordering (documentation only)
- Owner identified that testing CR/CP revision/resubmission before finishing six voucher forms is premature. Delivery sequence explicitly changed: 01 Cheque PASS; 02 Online PASS; 03 implemented foundation preserved, outstanding gates deferred; **04 CR, CP, CCR, CDR, BOR, BOP fully functional first**; 05 final approval/edit/reverse/delete/resubmission acceptance; 06 bank narration/history/transfer; 07 plot receipt; 08 purchase/auto purchase; 09 cheque/online dashboard; 10 full integration.
- Phase 04 sub-batches are 04.1 CR, 04.2 CP, 04.3 CCR, 04.4 CDR, 04.5 BOR, 04.6 BOP, 04.7 separate listings/print/attachment/regression, each gated by owner browser PASS. Six forms share eight fields including optional uploader and required narration.
- Historical 03.3 3A/3B1 browser PASS remains valid; 3B2A/B and latest JV typography gates remain not passed and transfer to Phase 05. Existing committed code is preserved, not rewritten or claimed verified. No database or code mutation in this documentation checkpoint.
- Full rationale and authoritative order: [Point03 roadmap](./POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md).

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


## 2026-11-11 — OWNER BROWSER: Approved vs historical Posted Edit mismatch; unified correction batch (unverified)
- Owner screenshots showed two different Edit interfaces: tracked Approved CR opened the modern correction form, untracked historical Posted CR opened legacy green nested Edit. Requirement: use the SAME modern correction modal for Posted, Approved, Rejected; state-specific color and Reason for Edit/Revision; current policy ON => Pending approval, OFF => guarded direct posting; Super Admin Approved By name visible in directory.
- `DailyVoucherDirectoryController` routes untracked Posted CR/legacy CP (DR-facing) edit to the SAME `finance.daily-vouchers.revise.edit` controller/view as approved/rejected. No new form/pages/routes, old legacy financial records untouched. Existing rejected Revise button preserved.
- `RejectedCashVoucherController` now authorizes and preloads untracked posted rows, as well as approved/rejected tracked rows; POST selects the correct protected transaction path. `rejected_cash_revision.blade.php` now handles nullable approval, identical form fields/layout and dynamically shows Reason for Edit or Reason for Revision with required server validation, current policy messaging and posting button.
- Approval ON: tracked approved/rejected uses guarded Resubmission; untracked posted CASH uses `LegacyCashVoucherCorrection` to keep reversal/corrected source+legs inactive until approver decision. Approval OFF: untracked Posted or already Approved **cash** correction uses append-only immediate posting with reversal and mandatory reason/audit event; rejected resubmission can post directly while original rejected remains inactive. Existing financial/method/duplicate and ownership checks retained, and pending correction blocks concurrent changes. Approved bank/cheque correction still blocked until bank settlement reversal is audited; policy ON change of payment method is blocked.
- `VoucherApprovalStatusReader` joins `decided_by` to users and exposes actual approval actor name, not assigned approver. `DailyVoucherDirectoryController` returns approved_by only to Super Admin. `daily_directory.blade.php` shows an Approved By table column only to Super Admin; Posted/no-decision rows show dash. JV approval rows also receive source-linked decided-by when present.
- This is **code committed, not browser PASS**. No remote/local PHP integration tests were run; verify on disposable local records: Posted Edit policy ON/OFF; Approved cash Edit policy ON/OFF; Rejected Revise ON/OFF; correct pending/current posting, only one new source, audit reason, approved_by actual decision actor, project isolation, duplicate edit prevention, accounting balance and no hard-delete. Pending six-type backend and bank/cheque approval-safe edits remain separate work. Do not start another milestone until owner PASS.

## 2026-11-11 — OWNER BROWSER BLOCKER: SQL 1406 approval events / correction identity (CURRENT)
- Owner screenshots from project #3: approvals for pending cash CR corrections failed with MySQL SQLSTATE[22001] 1406 `Data too long for column event`. Schema migration `2026_11_05_000001_create_daily_voucher_approval_workflow_tables.php` declares `daily_voucher_approval_events.event` as varchar(24). Last batch used oversized strings `legacy_cash_correction_activated` and `superseded_by_approved_correction`; approved soft-deactivation and direct approved correction had other oversized strings.
- Fixed event codes **without modifying DB schema**: `legacy_corr_activated` (21), `approved_corr_superseded` (24), `approved_cash_void` (18), `approved_cash_direct` (20); preserved `remarks` JSON provenance and transaction boundaries. Existing consumed event codes `legacy_cash_correction`, `revision_from_approved`, `revised_to_new_source` remain unchanged. Source code inspection confirms changed constants <=24 chars.
- No local database DDL/migration necessary for this fix. The failed transaction should roll back; owner must retry pending decision after pulling and clearing caches, then verify source/ledger activity, approval event, and old/new balance. **Browser and DB PASS remain unverified.**
- User also asked why Edit/Revise creates new rows. Current design intentionally uses append-only financial lineage: existing source/ledger identities are never overwritten; a replacement source/ledger is Pending and linked by immutable events. When approved, a previous approved source is soft-deactivated and new posting activated; historical Posted records are offset by separate reversal rather than destructively editing. This creates distinct database rows and voucher numbers, and is *not* an accidental duplicate money posting when active posting safety holds. It does create separate listing entries, which requires a separate owner-approved design for logical revision grouping if desired. Do not silently mutate original financial rows or promise 'same database row' without a full audited migration/ledger redesign.
- Keep current batch gate OPEN. Test approvals for pending CR source ids shown in owner browser (e.g. #5642, #5644), and test rejection, approved cash soft deactivate and direct edit on disposable records; no production mutation without owner approval.


## 2026-11-11 — SAME source/ledger row Edit and Revise implementation; browser HOLD

Owner screenshot shows old `CR-709` Posted and new `CR-834` reversal / `CR-835` Approved after legacy Edit. Owner required **same voucher ID and number** rather than replacement or reversal voucher rows.

Committed additive `daily_voucher_edit_snapshots` migration and `SameSourceCashVoucherEdit` service for CR/legacy CP cash-only. Existing directory/Edit/Revise routes and legacy cash editor now call same-ID service; no new financial voucher/source/ledger rows or counter increments during Edit. Current approval policy read dynamically: ON records a pending proposal and reuses same approval/source, not money-posting new rows; OFF safely updates same source and one linked ledger, storing immutable before/after audit snapshot. Decision handles approval by applying pending changes to same rows and rejection by restoring prior status and unchanged original posting. Pending proposals overlaid read-only in existing list with note original posting unchanged. Rejection/approval reason available in history.

Previous append-only corrections remain in DB. If a source has such a correction/reversal chain, new same-ID edit blocks pending a formal reconciliation; auto-deleting/merging would risk duplicate or missing balances. **This code does not repair CR-709/CR-834/CR-835 already present in the owner's local DB.** They need evidence-backed one-time cleanup in a disposable clone before any live operation.

Mandatory test gate: migrate local test DB; PHP lint changed classes; edit fresh, uncorrected CR cash with approval ON, confirm unchanged source and ledger IDs, same voucher number, one Pending row, proposed value display, original ledger still active with OLD value; approve and verify same ledger row gets new amount exactly once; reject an approved edit and verify old posting remains; approval OFF direct edit; rejected inactive revise -> pending/approve; ensure bank and old reversal-linked sources are blocked safely. No browser PASS claimed.


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


## 2026-11-11 — CCR New Voucher iframe reports "Voucher form unavailable: Routing"
Owner screenshot: /admin/finance/daily-vouchers?type=CCR opens existing modern modal but iframe displays a red 'Voucher form unavailable: Routing' banner, no form rendered. Root exception message is not visible in screenshot; route cache or a named-route resolution inside the embedded Blade is suspected, not yet proven.
- Reviewed route registrations: GET `finance.voucher.in.method` for method=cash and POST `finance.voucher.cash-only.store` / `finance.voucher.cash-only.draft` exist in branch `upgrade/laravel-framework`.
- `resources/views/admin/finance/voucher/cash_voucher.blade.php`: precompute `$voucherCreateAction` / `$voucherDraftAction`, using stable `url('admin/finance/voucher/cash-only')` and `.../draft` only for CCR/CDR, preserving existing named routes for CR/DR. Avoid nested @json(route(...)) expressions during iframe rendering; unchanged existing modern parent modal, no new page. Relevant commits e885e37a, 4267e043.
- User needs `git pull --ff-only origin upgrade/laravel-framework`, `php artisan optimize:clear`, `php artisan route:list --path=finance/voucher/cash-only`, restart local dev server if needed, hard reload CCR. If error persists, inspect the open voucher form standalone and Laravel log for exact RouteNotFound message; screenshot alone cannot establish exact exception.
- Browser PASS still OPEN; no CCR/CDR end-to-end ledger test performed. Do not start BOR/BOP.
