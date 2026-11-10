# Phase 02 — Online Transaction Management — Milestone 02.1

**Source:** `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md`, section F.
**Status:** CODE IMPLEMENTED; browser PASS pending.
**Phase 01:** Owner browser PASS 2026-11-10.

## Milestone 02.1 scope
- Dedicated `finance/online-transactions` read-only list and receipt/payment source intake.
- Fields: transaction reference, date, amount, sending/receiving bank + account, sender, receiver, project Head/Child Account, type, purpose, optional remarks, optional proof.
- Enforce project-specific active account mapping, required fields, 2-decimal positive fixed source amount.
- Normalized uppercase unique `project_id + reference_key` to block duplicate receipt/payment references even under concurrent saves.
- Store proof privately under `local` disk, accessible only via project-scoped authorized download.
- New online record is Pending and **does not touch customer_ledger/ledgers or bank voucher writers**. No unapproved confirmation action yet.
- Uses CR-style TomSelect, shared jQuery skeleton, AJAX list, SweetAlert2 confirmation/result, inline required validation, project-scoped type/status/account filters, pagination.
- Schema migration `2026_11_10_000005_create_finance_online_intakes.php` is additive and preserves existing online customer-ledger workflow. `finance_online_lifecycle_events` is a different legacy source; no migration or merging of that table.

## Browser acceptance gate
- Run migration on approved disposable/local database.
- User with read voucher sees register/sidebar; create voucher can add source.
- Receipt save Pending; Payment save Pending; original amount and all fields persist.
- No ledger/bank voucher created.
- Duplicate reference with different case, and repeated concurrent submit, rejected with no duplicate rows.
- Negative validations: missing mandatory, invalid amount/date, incorrect project account pairing, bad upload MIME/oversized upload.
- Account-select Head/Child TomSelect interaction; search/status/type filters, skeleton/pager, proof download (authorized only).
- Cross-project data isolation: IDs, filters, proof routes.
- **Owner must confirm 02.1 browser PASS before implementing 02.2 confirmation workflow.**

## Next milestone
02.2: Pending → Confirmed with mandatory bank statement/audit verification, confirmation actor/time, audit history and state locking. No automatic bank voucher posting.

## 2026-11-10 — Complete code milestone 02.1
- Implemented controller, migration, online register Blade, classic/v2 sidebar links and authenticated routes. Static verification: route endpoints, unique project/ref index, transaction type and account mapping validators, pending only, non-posting, private proof and returned proof download URLs. Browser test pending.
- Any bank confirmation/ledger voucher remains prohibited until separately approved 02.2/04 gate.

## 2026-11-10 — Browser defect: confirmation popup unavailable
- Owner provided browser screenshot with toast `Confirmation popup unavailable.` when saving Online Receipt.
- Root cause: Online Intake used `window.Swal.fire` without loading SweetAlert2, while the existing Cheque and CR Voucher views explicitly load the same library.
- Added SweetAlert2 script include before Online Intake's jQuery handlers, preserving CR-style stacked confirmation/success/error dialogs and existing AJAX flow.
- Source verified; awaiting owner browser retest. Milestone 02.1 is not PASS yet.

## 2026-11-10 — 02.1 browser PASS; 02.2 backend verification
- Owner explicitly confirmed **Milestone 02.1 browser PASS**. Milestone 02.2 started.
- Added additive `finance_online_confirmation_events` audit table, preserving project, transaction, original amount/type, statement reference, verification date, actor/timestamp and remarks. A unique project + intake key prevents duplicate confirmation history.
- Pending → Confirmed only, within project-scoped row lock and database transaction. Bank statement checkbox, reference and date are required; date cannot predate original transaction. No editing amount, source bank, account or type; no ledger posting.
- Added project-scoped read history endpoint (read voucher), confirm endpoint (create voucher), with strict authorization. Browser PASS pending.

## 2026-11-10 — Milestone 02.2 UI completed (code only)
- Added transaction actions: Confirm only when Pending (create voucher permission), History for read voucher. Table skeleton and pagination updated for twelfth column.
- Confirmation form shows original immutable reference/amount, verification date, statement reference, mandatory checkbox and optional remarks. UI text distinguishes Receipt IN bank credit versus Payment OUT bank debit. SweetAlert2 confirmation is stacked above the original form, with cancel/error preserving data.
- AJAX POST confirms and reloads table; history displays actor/time, original fixed amount, statement reference/date and remarks through project-scoped endpoint. No GL posting or voucher generation.
- Browser gate still pending: confirm credit & debit records, checkbox/date/ref validation, cancel, replay/duplicate POST, history, record-isolation and ledger unchanged. Milestone 02.3 remains locked.

## 2026-11-10 — Owner UI feedback on Milestone 02.2
- Removed separate **Remarks** textarea from new online transaction intake and stopped accepting/storing intake Remarks; **Purpose / Description** remains required. Historical `finance_online_intakes.remarks` database column is deliberately retained as nullable to preserve existing data; no destructive migration. **Verification Remarks** in the separate Confirm action and audit events remain unchanged.
- Added second CR-style table header row with column filters for Date, Transaction Reference, Type, Sending Bank, Receiving Bank, Sender/Receiver, Purpose, exact Amount, Status; empty filter cells for #, Proof, Actions. Filters are validated and applied in the scoped SQL query *before* pagination; compound top filters and quick search work together. jQuery debounce, reset, skeleton and pagination retained.
- Code committed, browser verification pending. Milestone 02.2 and its next gate remain open.

## 2026-11-10 — Column filter visual correction and Milestone 02.3
- Owner screenshot: Type/Status column-filter TomSelect expanded the sticky header. Excluded second-row column-filter native selects from TomSelect initializer. Both now use compact 27px native select controls matching input sizes; browser menu renders as overlay, without expanding/filter row. Other dropdowns retain CR-style TomSelect.
- Owner said remaining 02.2 workflow working and explicitly requested next milestone; marking 02.2 browser functional checks owner-reported PASS except visual regression, which remains pending retest.
- 02.3 implemented: project-scoped read-only `GET /admin/finance/online-transactions/eligible` (subject to existing admin route prefix) returns only Confirmed source records with matching bank verification evidence and no existing source voucher claim; Pending, missing audit, mismatched amounts/types, claimed sources are excluded.
- Added additive `finance_online_voucher_claims` source/link contract with DB unique (project, intake) for future Phase 04 voucher writer; **no write endpoint and no claim creation in Phase 02**. Actual posting and source consumption enforcement in future bank voucher writer belongs to Phase 04. This milestone does not pretend a voucher has been posted.
- History modal now shows authoritative source eligibility (or blocked reason) using read-only server response; immutable audit history remains intact. 02.3 browser testing pending. Phase 03 locked until all Phase 02 gates PASS.

## 2026-11-10 — Phase 02 final acceptance and closure
- Owner explicitly confirmed that the Milestone 02.3 browser tests and Type/Status column dropdown fixes **PASS** on the local Online Transaction Management page.
- Milestones 02.1, 02.2 and 02.3 accepted; Phase 02 **CLOSED**. Bank/ledger posting is still intentionally disabled. Phase 03.1 now authorized; 03.2/03.3 remain gated by browser testing.
