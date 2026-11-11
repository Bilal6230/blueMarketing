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

## 2026-11-11 — First UI implementation for corrected six-voucher naming

Owner corrected previous flow: new **CR Credit Voucher / DR Debit Voucher**, with legacy Cash In / Cash Out behavior respectively; **CCR Cash Credit / CDR Cash Debit / BOR Bank Online Receipt / BOP Bank Online Payment**. Existing persisted CP type must remain accessible and cannot be blindly renamed to DR.

Implemented new project-scoped GET-only forms controller, protected route `finance.voucher.six-form` and shared Blade view. Six forms display eight common fields (including optional attachment input, mandatory narration UI), cash-only CCR/CDR exclude instrument inputs, bank-only BOR/BOP restrict payment choices to Online/Cheque, CR/DR conditional payment fields, and first-party jQuery account cascades. No native DOM/AJAX added. Existing Cash In/Out entry paths linked and left unchanged. Submission disabled pending typed back-end and safe file/source/ledger integration; attachment not yet uploaded; no DB/schema migrations or financial writes. Browser PASS NOT CLAIMED.

Next: implement real CR/DR backend compatibility and safe source/ledger numbering, then other four real types, attachment persistence and six lists; approval lifecycle acceptance after functional vouchers. See roadmap latest correction.
