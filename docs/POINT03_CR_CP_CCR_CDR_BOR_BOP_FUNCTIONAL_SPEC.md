# BlueMarketing — CR, CP, CCR, CDR, BOR, BOP Functional Specification (Roman Urdu)
Date: 2026-11-11 | Branch: upgrade/laravel-framework | Status: Requirements captured; implementation NOT started.

## SOURCE-CHECKED CURRENT BEHAVIOR
- Current old Cash In: /admin/finance/voucher/in. Old Cash Out: /admin/finance/voucher/out.
- Current LedgerController stores customer_ledger.transaction_type and ledgers.type ONLY as CR or CP; CCR/CDR/BOR/BOP are project_voucher_display_numbers display-series identities, NOT distinct persisted accounting types. Validator enforces CR-/CP- numbers and requires detail and payment_type.
- Current Cash In/Out method codes: Cash=1, Online=2, Check=3. Cash hides bank_group. Online opens Number, Bank, Passing Date. Check adds required Cheque Purpose.
- Existing CP Online/Check route also opens pending receipts clearance list, amount lock and statuses Pass=1, Return=2, Bounce=3. Some branches only update original pending source rather than create a new CP voucher. Keep this behavior for old CP and do not mistake it for independent BOP.
- Current source can require approval to post; pending and rejected accounting stays inactive. Current approval support is CR/CP/JV, not real CCR/CDR/BOR/BOP.

## FINAL COMMON FORM FIELDS — OWNER CONFIRMED (2026-11-11)
All SIX CR, CP, CCR, CDR, BOR, BOP voucher forms MUST show these **eight common fields**:
1. Voucher No (auto assigned according to voucher type)
2. Date
3. Amount
4. Account Type
5. Accounts (Head Account)
6. Child Account
7. Attachment / File Uploader (**OPTIONAL**)
8. Description / Narration (**required**)

- These eight fields are ALWAYS present in the six specified voucher forms; optional applies to attachment only, not its visibility.
- Form-specific fields/conditional logic stay as described below (CR/CP Cash/Online/Check; CCR/CDR cash only with NO bank/cheque extras; BOR/BOP Online/Check with linked verified source and NO Cash).
- Validate attachment file type/size/storage/access on frontend/backend when implementation starts; avoid storing unsafe arbitrary uploads. No extra mandatory uploader validation when no file chosen.
- This latest OWNER correction supersedes all earlier “six-field” and “seven-field” form counts. CCR/CDR have **eight common fields only**, including optional attachment and required description. No new app code has been implemented by this docs update.

## OWNER-REQUESTED FINAL BEHAVIOR
### CR — original Cash In Voucher
- Preserve old business logic for Cash / Online / Check payment type selection and all conditional fields, account cascades, save rules, project isolation and approval/clearance. Only visual UI should remain modern.
- Eight common fields listed above always show, including optional Attachment and required Description. CR additionally shows Payment Type (Cash/Online/Check); Online: Number, Bank, Passing Date. Check: those fields plus Cheque Purpose.
- CR database type remains CR. Own CR-only list.
### CP — original Cash Out Voucher (old DR screen)
- Preserve old business flow, including Cash / Online / Check; CP Online/Check pending receipt selection, locked amount, status Pass/Return/Bounce, no duplicate unrelated voucher.
- Bank/cheque conditional fields same old form; preserve historical accounting and validations; design remains modern.
- CP database type remains CP. Own CP-only list. All eight common fields shown, including optional Attachment. CP Online/Cheque conditional bank/clearance fields remain.
### CCR — NEW Cash Credit / Cash Receipt Voucher
- Must be a real database type CCR in transaction_type / ledgers.type, not display series only. Cash-only.
- Exactly EIGHT visible common fields: Voucher No, Date, Amount, Account Type, Accounts, Child Account, optional Attachment / File Uploader, required Description / Narration.
- Details / Description stays visible and required. NO Payment Type selector, transaction/cheque Number, Bank, Passing Date, Cheque Purpose, bank/cheque clearance panel, or other bank/online instrument fields.
- Backend must require and validate Details / Description, accept optional file uploader with safe file validation/storage, and use entered description as ledger narration. Fixed internal Cash method (payment_type=1) if schema needs it; no bank/cheque/online fields required.
- Own CCR-only listing.
### CDR — NEW Cash Debit / Cash Payment Voucher
- Must be real database type CDR; outgoing cash; same eight visible fields as CCR, including optional Attachment and required Details / Description. No bank, cheque, or online instrument fields.
- Own CDR-only listing, correct outgoing ledger amount, project scope and approvals.
### BOR — NEW Bank Receipt Voucher
- Display title Bank Receipt Voucher. Must persist real BOR type.
- Payment Type offers ONLY Online or Cheque. NO Cash.
- All eight common form fields always appear, including optional Attachment and required Description. Created ONLY from eligible confirmed Online Management receipt or passed/cleared Cheque Management incoming source. Require source id, status, project, amount, direction and unique linkage. Do not permit arbitrary fake bank receipts.
- Source financial reference/amount must be immutable and bank fields derive from verified source. Exact bank-side allocation details need separate implementation audit.
- Own BOR-only list.
### BOP — NEW Bank Payment Voucher
- Display title Bank Payment Voucher. Must persist real BOP type.
- Payment Type offers ONLY Online or Cheque. NO Cash.
- All eight common form fields always appear, including optional Attachment and required Description. Created ONLY from eligible confirmed Online Management OUT payment or passed/cleared issued cheque source. Ensure true outgoing direction, amount, bank account, unique linkage and non-duplication.
- Do not turn old CP's clearance-of-an-incoming-receipt operation into an outgoing BOP.
- Own BOP-only list.

## SHARED MODERN UI
- Six separate list pages, each filtered by actual persisted voucher type; no mixed rows across types. Existing historical CR/CP not rewritten automatically.
- Modern forms/modal Add/Edit, confirmation, validation, toast, table column filters, date/account/type filters, fixed-card internal scroll, print and pagination, following already approved BlueMarketing styling.
- Only jQuery for first-party frontend logic; use existing third-party Tom Select integrations appropriately.

## IMPLEMENTATION SAFETY / GATES
1. Audit schema constraints, typed relation consumers, bank clearance registries, approval workflow, posting guards, allocator, revision service, deletion, historical reporting and V2 pilot routes before any real-type migration.
2. Current LedgerController store hardcodes CR/CP and requires detail/payment_type. New types need independent validators and correct account direction, unique numbering, source linkage and approval activation rules.
3. Ensure BOR/BOP source-linked issuance from confirmed online/passed cheque management with immutable amount, one-time idempotent posting, no duplicate ledger or bank movement.
4. Historical CR/CP and existing display-series identities remain readable; no irreversible backfill without separate review.
5. Original CR/CP Create validation is currently reported broken: investigate and repair before 3B2A/B browser tests are claimed PASS.
6. Browser acceptance: all six persistence types, correct conditional field visibility, CCR/CDR eight-field form including optional Attachment and required Details, without bank/cheque fields, six independent lists, permission and approval gates, negative duplicate/cross-project/source-status tests, print, return/bounce, ledger and history.
7. Phase 03 currently open; 3B2A/B testing temporarily HOLD per user. This document records requirements ONLY, not production implementation.

## PROPOSED SEQUENCE (for discussion; not executed)
- Phase 03 corrective milestone: fix old CR/CP creation and conditional validation, then 3B2A/B browser tests.
- Phase 04.1: CCR/CDR true persistent types + eight-field forms (including optional Attachment and required Details) + separate lists, browser gate.
- Phase 04.2: BOR source-linked verified incoming posting and BOP verified outgoing posting, each independently browser gated.
- Phase 04.3–04.4 and Phases 05–09 proceed under established browser PASS gates.

## Owner correction — 2026-11-11 (supersedes previous six-field wording)
- CCR/CDR **Details / Description MUST remain**; user only requested removing bank/cheque/online-related fields. Exactly seven visible fields listed above; prior six-field/no-details requirement is withdrawn. No financial code changed in this correction.

## Final owner correction (2026-11-11) — attachment is COMMON
- Previous seven-field/no-uploader wording is superseded. All six voucher forms CR, CP, CCR, CDR, BOR, BOP share the exact eight common fields listed at the top. Attachment/File Uploader visible and **optional** on all six. Remaining type-specific rules unchanged.
