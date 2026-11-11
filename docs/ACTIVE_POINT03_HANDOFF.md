# BlueMarketing — ACTIVE HANDOFF (new chat continuity)
**Updated:** 2026-11-11
**Repo:** `Bilal6230/blueMarketing`
**Branch:** `upgrade/laravel-framework`
**Owner local:** `C:\laragon\www\blueMarketing` (PowerShell, Laragon, local browser `localhost:8000`)
**Primary roadmap:** [Point03 roadmap](./POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md)
**Current worklog:** [Phase03 voucher worklog](./POINT03_PHASE_03_VOUCHER_WORKLOG.md)
**Original client requirements:** [Roman Urdu Point03](./BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md)
**IMPORTANT:** Read this handoff, the roadmap, worklog, and latest repository files at the start of a new chat. This is not a substitute for verifying current GitHub HEAD.

## How to work with this user
- Reply in simple **Roman Urdu**. User prefers actual code changes/commits and **PowerShell git pull** commands, not screenshots/mockups or unnecessary explanations. Give implemented changes, tested vs untested, upcoming milestone.
- First inspect current `upgrade/laravel-framework` source; make focused edits and commit them to the same branch through GitHub connector. Do not claim local browser tests passed unless user explicitly confirms.
- **Only jQuery** for first-party frontend code (including inline Blade JS); third-party JS excluded from this constraint. Keep changes isolated to the active phase, do not touch unrelated systems or production data.
- Milestone/phase gated by owner's browser testing: fix current bugs until PASS; never prematurely start later phase or batch.
- Maintain `docs/POINT03_PHASE_03_VOUCHER_WORKLOG.md`, `docs/POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md`, and this handoff after each significant step.

## Progress as of 2026-11-11
| Item | State / evidence |
| --- | --- |
| Phase 01 — Cheque (E) | COMPLETE; owner browser PASS |
| Phase 02 — Online Transactions (F) | COMPLETE; 02.1–02.3 owner browser PASS |
| Phase 03.1 — Common Voucher UI/Print | COMPLETE; owner browser PASS |
| Phase 03.2 — Voucher Approval Settings | COMPLETE; owner browser PASS |
| Phase 03.3 — Approval Workflow & History | ACTIVE; iterative batches, overall milestone browser gate NOT YET CLOSED |
| 03.3 Batch 1 — `voucher approver` permission and authority | Owner browser PASS |
| 03.3 Batch 2 — Status visibility, JV listing/modals/layout | Major UX sub-fixes tested repeatedly; owner **explicitly confirmed Add/Edit modal alignment browser PASS**, then asked to increase font size |
| Latest font-size change | **Code committed, browser PASS NOT YET CONFIRMED** — do not mark Batch 2 final PASS until owner verifies |
| Batch 3 — Pending/Rejected edit, reverse/delete, resubmit safety | **ACTIVE**: 3A mutation guard coded, browser PASS pending; 3B revise/resubmit NOT STARTED |
| Phase 04–09 | LOCKED until Phase 03 browser PASS |

### Recent important commits (verify against HEAD)
- `d8289e98d979626f172972bcacef26860653e457` — latest JV font sizes on listing and embedded Add/Edit forms; browser gate pending.
- `ad3d25d452e31612fa75d5c689b55cf68943fa38` — fix nested AdminLTE collapsed-sidebar offset in Add/Edit modal; owner browser PASS.
- `568e63f615ebb02353641bda6f5c04f7b8d9ad35` — actual plural Edit route interception and embedded offset.
- `386923ed612555e38af21ddf9540207f78f240cd` — embedded form loader/preloader.
- `77da0b1199cb32aaf08b4b5994762f478a24461a` — internal JV list scrolling, no whole-page scroll.
- `0a9566c1445867ac5ec12095a21034c304b8e436` — undefined `$showActions` render crash.
- `aa3ff546ff355eaebb4e61e199eafe3adb4c9dc5` — JV + daily directory approval-status separation.
- `8da53a107b6040b3e70696d64b4b7b66c5b71fd6` — permission-based approver / Super Admin authority.
- `980560cc360a020df062548686ef9d3e56b895a1` — original 03.3 approval dashboard.
- `9285e9925be91a16e9ac997951a5c228f23ae9ac` — approval settings.

## Voucher approval policy agreed with owner
1. New permission `voucher approver`; active project users with that permission (direct or inherited via role) are eligible as Authorized Approver for a voucher type. Must explicitly assign permission to desired roles; migration does not auto-assign.
2. Ordinary approver sees/decides **only own assigned approval requests**. Super Admin (`superadmin` role; old code also spelled `super-admin`) sees/decides **all selected-project pending** approvals even without being assigned. Decisions audited. Self-approval prevented for ordinary approvers; Super Admin override allowed.
3. **No automatic Super Admin approval was authorized**: Super Admin has universal manual decision authority. Do not implement automatic posting without user approval.
4. Approval enabled -> source+detail+ledger records may be saved, `ledgers.is_active=0` while pending, approval row `status=pending`. Approve -> existing guarded workflow activates accounting; Reject -> remains inactive and records reason. `is_active=0` **does not mean deleted**; pending/rejected come from `daily_voucher_approvals.status`; deleted/reversed are different evidence/workflows.
5. `ledgers` **no new approval column**. Reuse `daily_voucher_approval_policies`, `daily_voucher_approvals`, `daily_voucher_approval_events`. Existing voucher types without guarded posting enforcement stay locked for Approval ON until safely integrated.
6. Voucher Settings editing restricted to Super Admin; approver selection is project and active-user scoped; existing finance helper reused by other operations must not be globally replaced with the new voucher permission.

## Important application paths & UI
- Voucher Settings: `/admin/finance/voucher-settings`; `app/Http/Controllers/Finance/VoucherSettingsController.php`.
- Approval dashboard: `/admin/finance/daily-vouchers/approvals`; controller `app/Http/Controllers/Finance/DailyVoucherApprovalController.php`; service `app/Services/Finance/DailyVoucherApprovalWorkflow.php`.
- JV listing: `/admin/journal-voucher`; `resources/views/admin/reports/vouchers/index.blade.php`; controller `app/Http/Controllers/JournalVoucherController.php`.
- JV create/edit forms: `resources/views/admin/reports/vouchers/journal_voucher.blade.php`, `edit_journal_voucher.blade.php`. Actual edit URL plural: `/admin/journal-vouchers/{id}/edit`.
- JV create/edit open in Bootstrap modal with **same-origin iframe** and `?embed=1`; embedded-only CSS removes nested AdminLTE sidebar offsets/preloader. Parent jQuery handler intercepts Edit links and clears iframe on close. **Do not break this alignment.**
- Daily Voucher Directory: `/admin/finance/daily-vouchers`; `resources/views/admin/finance/voucher/daily_directory.blade.php`; controller `app/Http/Controllers/Finance/DailyVoucherDirectoryController.php`.
- Read-only approval status lookup: `app/Services/Finance/VoucherApprovalStatusReader.php`. Legacy JV records with **no matching approval record** display `Posted` instead of incorrectly displaying legacy `journal_vouchers.status=Pending`.
- Existing JV modal UI blue theme; listing has single-row filters, per-column filters, compact actions, internal table-only scroll. Don't reintroduce main page scroll.
- Latest JV typography: list table 13px, filter labels 13px, inputs 13px, modal labels 13px, modal inputs 14px and debit/credit table 13px.
- New permission migration already committed: `database/migrations/2026_11_10_000008_create_voucher_approver_permission.php`. Owner reported Batch1 browser PASS. Do not rerun production migrations without instruction.

## NEXT milestone — 03.3 Batch 3 (NOT STARTED)
**Pending/Rejected Voucher Edit, Reverse/Delete & Resubmission Safety**
1. First obtain owner confirmation whether latest `d8289e98` typography change browser PASS and Batch 2 final gate closed. If an issue is shown, fix within Batch 2.
2. Audit (read source first): `JournalVoucherController` create/update/delete/reversal; `LedgerController` CR/CP save/update/delete; `DailyVoucherApprovalWorkflow` source locking, ledger activation and audit, `DailyVoucherApproval` tables; current print, filters, permissions and project scoping. List exactly where pending/rejected/edit/delete conflict exists, how changes alter source/ledger/approval/event rows. Investigate legacy posted and soft-deleted semantics; do not equate inactive with deleted.
3. Define testable safeguards: pending voucher not accidentally posted/reversed, rejected voucher not silently edited into approved, approved posting cannot be silently deleted, revision/resubmission audit and duplicate prevention, authorized actors only, Super Admin override behavior. **Do not create new schema or add ledger columns without explicit scoped need/approval**.
4. Implement in focused batches after audit; update docs, commit, provide pull command and await owner browser PASS. Phase 04 remains locked.

## Commands (PowerShell, after a new commit)
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
```
No claim of local migration, browser or PHP test execution unless actually verified. GitHub-only source inspection/commit != browser PASS.

## New chat starter (user can paste, but docs are canonical)
“BlueMarketing ka previous flow continue karo. Branch `upgrade/laravel-framework` par `docs/ACTIVE_POINT03_HANDOFF.md`, `docs/POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md` aur `docs/POINT03_PHASE_03_VOUCHER_WORKLOG.md` padh kar latest source verify karo. Phase 03.3 active hai, Batch 1 browser PASS; Add/Edit modal alignment PASS; latest JV typography change ka browser gate pending hai. Next Batch 3 Pending/Rejected edit/void/resubmit safety hai—Batch 2 browser PASS se pehle next batch nahi. Sirf jQuery; no unrelated changes; commits and PowerShell pull commands do.”

## Update 2026-11-11 — Batch 3A
- Owner requested next batch. Central \`app/Services/Finance/VoucherApprovalMutationGuard.php\` now blocks legacy JV and CR/CP mutation when a matching approval request exists, independent of ledger is_active. JV Edit/Reverse buttons hidden for tracked approvals, Print kept. Legacy records without approval evidence unchanged. No migration/ledger schema or existing database record changes.
- 3A browser testing not yet confirmed. 3B audited revise-and-resubmit remains unimplemented. Next assistant must test status-specific 409 for JV edit/update/reverse, CR/CP correct/deactivate, and legacy no-approval behavior before moving on.

## 2026-11-11 — Batch 3B1 started
- Owner confirmed 3A browser PASS. Implemented rejected JV Revise & Resubmit only: `app/Services/Finance/RejectedJournalVoucherResubmission.php`, POST `journal-vouchers/{id}/resubmit`, Revise button in JV list and modal edit form with revision reason. Uses existing UNIQUE approval row; old rejected reason and complete before-image in resubmitted event, status to pending. Strict provenance and inactive checks; same-number-of-lines only. Do not auto-approve or invoke original legacy JV correction. Browser PASS pending. CR/CP rejected revisions still NOT IMPLEMENTED; next sub-batch only after JV test passes.

- Batch 3B1 append-only correction: old rejected ledger history stays inactive/untouched, new detail/ledger rows inserted inactive; approval only activates current non-archived details. Browser verification still pending.

- JV listing regression from malformed Edit/Revise handler regex diagnosed and fixed in 3B1: script parse error prevented DataTables from starting, no records deleted. Owner browser retest pending.

## 2026-11-11 — Batch 3B2A
- User marked rejected JV 3B1 browser PASS. Cash CR/CP rejected revision now implemented in `RejectedCashVoucherResubmission`, `RejectedCashVoucherController`, `rejected_cash_revision.blade.php`, Daily Voucher Directory row action and routes. Original rejected source/ledger stays inactive; revised voucher gets new source, ledger, identity and pending approval; approval events link old/new. No migration. Browser PASS pending. Bank/online/cheque 3B2B NOT STARTED. Read current source before next batch.

## 2026-11-11 — 3B2B and testing hold
- Owner explicitly put CR/CP 3B2A browser testing HOLD because CR/CP create validation fails. They authorized implementation of 3B2B despite gate. 3B2B extends rejected CR/CP revision to payment_type 2/3 only if source and its single ledger inactive, no bank clearance outcome/posted date, no subsequent finance cheque/online lifecycle decisions. New pending source keeps instrument metadata; old history immutable. Browser test of 3B2A/3B2B still HOLD; investigate create validation next. Phase 03 remains ACTIVE, Phase 04–09 remain locked.

- Final 3B2B UI review: replaced raw numeric bank_id input with getPakistanBanks list-backed select, matching existing CR/CP voucher form bank values. Browser testing remains ON HOLD.

## 2026-11-11 — Six voucher specification documented
- User explicitly asked to discuss existing CR/CP old business logic and document CCR/CDR/BOR/BOP requirements. Canonical file: docs/POINT03_CR_CP_CCR_CDR_BOR_BOP_FUNCTIONAL_SPEC.md.
- CR/CP: old Cash/Online/Check switching and CP pending clearance preserved, visual UI modern. CCR/CDR: real transaction_type/ledger.type and seven visible fields including required Details / Description; remove only bank/cheque/online-related inputs. BOR/BOP: real types, Online/Check only, source-linked to eligible passed Cheque Management and confirmed Online Management transactions. Six separate lists.
- Current code uses CCR/CDR/BOR/BOP as display series over CR/CP: full backend implementation NOT done. Do not claim done. CR/CP validation repair and 3B2A/B browser gate held. No live data/schema changed.

- Latest explicit correction: CCR/CDR Details / Description must stay. Seven fields in total. Do not automatically generate narration or remove Details. All earlier six-field statements are superseded.

## 2026-11-11 — LATEST OWNER DECISION (overrides earlier field-count notes)
- **Eight mandatory-to-display common form fields** for CR, CP, CCR, CDR, BOR, BOP: Voucher No, Date, Amount, Account Type, Accounts, Child Account, Attachment/File Uploader (optional file selection), Description/Narration (required). Attachment uploader visible on all six and optional; not a bank-specific field. CCR/CDR no bank/cheque extras. CR/CP old conditional Cash/Online/Check and pending-clearing functionality, BOR/BOP bank/online source-linked only. All older six/seven-field phrasing is superseded; do not remove description or uploader.
- This turn updated docs only. CR/CP validation and browser testing still on hold.

## 2026-11-11 — CR/CP first repair batch
- User asked to begin work after documenting eight common fields. Started with existing CR/CP create validation. Null-safe jQuery create-form checks, conditional bank/cheque and CP pending receipt inputs, hidden/visible voucher equality, per-field inline feedback, backend Account Type matching, bank/date validation. No ledger mutation or CCR/CDR/BOR/BOP type migration yet. Browser testing required before next milestone. 3B2A/B browser HOLD continues.


## 2026-11-11 OWNER-APPROVED DEPENDENCY RESEQUENCING — READ FIRST
- **ACTIVE ORDER is now Phase 01 Cheque PASS → 02 Online PASS → 03 completed voucher foundation preserved → 04 six functional vouchers FIRST → 05 final approval/revision/resubmission acceptance → 06 bank narration/link history/deposit & withdrawal → 07 plot receive → 08 plot purchase/auto purchase → 09 dashboard → 10 full acceptance.** This supersedes older statements below that Phase 04 is locked behind unfinished 03.3 browser testing.
- Reason: cannot meaningfully browser-test CR/CP resubmission across full six-voucher flows before the vouchers are actually complete. Phase 03.3 is **not** retroactively PASS; 3B2A/B CR/CP browser tests remain HOLD and are carried into Phase 05, with latest JV typography browser gate also still unconfirmed. Existing 3A and 3B1 PASS preserved.
- **Phase 04 batches:** 04.1 CR, 04.2 CP, 04.3 CCR, 04.4 CDR, 04.5 BOR, 04.6 BOP, 04.7 six lists/print/attachments/regression. Each batch must get owner's own browser PASS before the next. Phase 04 work must include necessary source/ledger posting safety but not pretend final Phase 05 approval testing passed.
- Eight common fields across ALL SIX: Voucher No, Date, Amount, Account Type, Accounts, Child Account, Attachment/File Uploader **optional**, Description/Narration **required**. CCR/CDR cash-only with no bank/cheque inputs; BOR/BOP only verified online/cleared cheque incoming/outgoing, not cash. CR/CP original conditional flows preserved.
- Source of truth: [ACTIVE delivery roadmap](./POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md), [six-voucher spec](./POINT03_CR_CP_CCR_CDR_BOR_BOP_FUNCTIONAL_SPEC.md). Earlier roadmap, locked-status and next-milestone statements below are historic and superseded on delivery order only.
- This was a **documentation-only roadmap change**. No code changes or browser tests claimed as part of resequencing.

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


## CURRENT 2026-11-11 — Unified Edit/Revise and Super Admin Approved By
Owner CR screenshots identified nested legacy Posted Edit vs approved correction form mismatch. Same six existing Daily Voucher pages now use one compact correction form/controller for untracked Posted, Approved, Rejected CR/legacy CP (DR-facing). Reason for Edit / Revision mandatory; current approval policy controls whether supported edits submit Pending or post as audited append-only correction. Approved cash with policy OFF is allowed only as guarded direct reversal+replacement with approval audit event; approved bank/cheque remains blocked for safe lifecycle reversal. Rejected policy OFF can resubmit directly. Real decided_by name appears only to Super Admin in listing. No browser tests have been executed here; PHP/integration lint and owner browser PASS still necessary before next batch. No new pages or hard deletions.

## 2026-11-11 — OWNER BROWSER BLOCKER: SQL 1406 approval events / correction identity (CURRENT)
- Owner screenshots from project #3: approvals for pending cash CR corrections failed with MySQL SQLSTATE[22001] 1406 `Data too long for column event`. Schema migration `2026_11_05_000001_create_daily_voucher_approval_workflow_tables.php` declares `daily_voucher_approval_events.event` as varchar(24). Last batch used oversized strings `legacy_cash_correction_activated` and `superseded_by_approved_correction`; approved soft-deactivation and direct approved correction had other oversized strings.
- Fixed event codes **without modifying DB schema**: `legacy_corr_activated` (21), `approved_corr_superseded` (24), `approved_cash_void` (18), `approved_cash_direct` (20); preserved `remarks` JSON provenance and transaction boundaries. Existing consumed event codes `legacy_cash_correction`, `revision_from_approved`, `revised_to_new_source` remain unchanged. Source code inspection confirms changed constants <=24 chars.
- No local database DDL/migration necessary for this fix. The failed transaction should roll back; owner must retry pending decision after pulling and clearing caches, then verify source/ledger activity, approval event, and old/new balance. **Browser and DB PASS remain unverified.**
- User also asked why Edit/Revise creates new rows. Current design intentionally uses append-only financial lineage: existing source/ledger identities are never overwritten; a replacement source/ledger is Pending and linked by immutable events. When approved, a previous approved source is soft-deactivated and new posting activated; historical Posted records are offset by separate reversal rather than destructively editing. This creates distinct database rows and voucher numbers, and is *not* an accidental duplicate money posting when active posting safety holds. It does create separate listing entries, which requires a separate owner-approved design for logical revision grouping if desired. Do not silently mutate original financial rows or promise 'same database row' without a full audited migration/ledger redesign.
- Keep current batch gate OPEN. Test approvals for pending CR source ids shown in owner browser (e.g. #5642, #5644), and test rejection, approved cash soft deactivate and direct edit on disposable records; no production mutation without owner approval.


## 2026-11-11 — CURRENT owner blocker and code checkpoint: same-ID CR/DR Cash correction

Owner refused new CR voucher rows after Edit/Revise. Previous append-only code created visible CR-834 reversal and CR-835 edited voucher alongside CR-709. **New implementation for Cash CR and legacy CP-as-DR now keeps identical source.id/type_id/voucher_number and ledger.id on editing**. A dedicated immutable audit snapshot table `daily_voucher_edit_snapshots` is introduced by migration `2026_11_11_000030_create_daily_voucher_edit_snapshots.php`. No new form/page. Approval ON => original source/ledger values unchanged pending an approval on same source, proposed values displayed in existing list; accept => same linked accounting rows updated transactionally, reject => original untouched and prior status restored. Approval OFF => direct in-place source/ledger update with immutable snapshot and mandatory reason. Both main Daily Voucher Edit/Revise and old Cash In/Out Editor POST use new service. New entries still follow existing posting code.

**Local migration required before any form test:** `php artisan migrate --path=database/migrations/2026_11_11_000030_create_daily_voucher_edit_snapshots.php` (backup local disposable DB). Project ledger single-leg, status, amount, project ownership and previous correction-chain checks prevent unsafe updates. Existing old CR-834/835 etc have NOT been merged/removed and MUST NOT be manually hard deleted. Need one-time reconciled repair separately. Online/Cheque, broader six voucher types remain gated. NO PHP tests/browser PASS or DB migration run on user's environment by assistant. Current milestone HOLD pending owner's browser gate.


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
