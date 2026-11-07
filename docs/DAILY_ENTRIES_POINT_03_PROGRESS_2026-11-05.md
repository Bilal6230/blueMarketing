# Daily Entries Point 03 — Implementation Progress

Updated: 2026-11-05
Source plan: `DAILY_ENTRIES_POINT_03_REQUIREMENTS_AUDIT_2026-11-04.md`

## Progress — corrected after client clarification (2026-11-05)

The prior 97% figure described backend/API work against the first audit plan. It did **not** mean the client-facing set of separate voucher screens was delivered. Client clarification requires six distinct Cash/Bank Online/Cheque receipt/payment screens; those are tracked below as a new delivery track. Original M1–M7 backend work remains recorded below, with known gaps retained.

### Client-facing voucher delivery track

- [x] C1 — Add six separate receipt/payment entry URLs and navigation items; existing `/finance/voucher/in` and `/out` routes remain for compatibility.
- [x] C2 — Method-specific fields, lists, draft/edit behavior, and print. Fixed screens enforce the selected payment method on create, draft, and edit; legacy and isolated-pilot list rows are filtered by direction+method; method print routes verify project, source, and method identity.
- [x] C3 — Deliver distinct Bank Receipt and Bank Payment screens with source selection, project bank identity, allocation validation, and traceable lists/print. Browser acceptance remains pending.
- [x] C4 — Deliver distinct Bank Deposit and Bank Withdrawal screens, including eligible cheque withdrawal and balanced account validation. Engineering complete; browser acceptance pending.
- [x] C5 — Deliver Plot Receive Payment with multi-plot allocation and per-plot receipts. Engineering complete; browser acceptance pending.
- [x] C6 — Deliver Plot Purchase workflow with inventory integration and traceable list/print. Engineering complete; browser acceptance pending.
- [x] C7 — Unified directory, supported approval routing, source details, and print coverage integrated.
- [x] C7 — Focused finance regression and browser acceptance guide handed off; browser execution remains user-owned.

Progress bar: **C1–C7 code and automated regression complete** (`██████████ 100%`). Local browser acceptance is currently blocked because `.env` selects `bluenew` while typed V2 workspaces require an explicitly isolated `bluemarketing_v2_*` database and the pilot API flag is unset. Percentages track code milestones, not deployable/browser-ready acceptance.

### C7 — Unified daily voucher directory (2026-11-05, engineering complete)

- Added a project-scoped unified register with type/date/account/search filters, accurate source counts, AJAX details, and links to source-specific print views. It includes legacy CR/CP/PPR, JV/CV and D/C/BR/BO/BW records plus typed bank transfer and plot receipt/purchase sources.
- Plot receipts remain one directory row per voucher; its details expose each plot allocation and corresponding plot-specific receipt print. This preserves voucher counts and total amounts while retaining allocation traceability.
- Approval links are shown only where the existing approval policy supports the source. Current posting gates cover configured legacy CR/CP and JV paths; unsupported typed pilot sources are not presented as if they had an approval workflow. Existing D/C functionality is preserved and represented in the directory.
- Project/source/account filters fail closed when the needed relation is unavailable. No unrelated records are updated or removed.
- Verification: integrated focused finance suite passed **68 tests / 474 assertions**; PHP lint, named-route inspection, Blade cache compilation, and `git diff --check` passed. Browser acceptance remains user-owned.
- Browser follow-up: all new typed voucher pages fail closed on the current local config before rendering; read authorization now returns a clear HTTP 409 explanation. Do not enable writes or migrate `bluenew`; use a separately named isolated V2 clone, apply/reconcile its migrations, then enable the local pilot flags before browser QA.

Latest implementation slice: fixed-method GET/POST/draft/update/print routes are registered; the six screens hide the method selector, reject mismatched writes, and point their lists to direction+method filtered endpoints. Draft edits are project-scoped and cannot cross methods; print validates the selected project's voucher source and method. Legacy combined routes/endpoints remain unchanged. Static verification passed: PHP syntax, named print route registration, Blade cache compilation, `git diff --check`, and the existing cash-list regression. Browser QA not run in this slice.

### C5 — Plot Receive Payment (2026-11-05, engineering complete)

- Added a dedicated sidebar entry and workspace for one receipt allocated across multiple eligible plots belonging to the selected customer/project. The form and history/details refresh use AJAX.
- Enforces active booking/plot/customer/receivable-account project ownership, active project cash/bank receiving account, distinct plot allocations, and exact receipt/allocation total. Uses idempotent V2 posting; each allocation has its own printable receipt and source details.
- Verification: `V2PlotEntryWriterTest` passed (6 tests / 34 assertions); PHP lint, named-route registration (6 routes), Blade cache compilation, and `git diff --check` passed. No business database migration/write was run. Browser acceptance is pending and pilot posting gates remain enforced.

### C6 — Plot Purchase Voucher (2026-11-05, engineering complete)

- Added a dedicated project-scoped Plot Purchase screen and navigation entry. It captures seller/payable account, voucher/date/narration, and multiple plot lines with identity, area/unit, road/facing, type/definitions, corner flag, rate, and inventory account.
- AJAX loads project plot definitions and recent purchase history, posts without a page reload, and opens source details with printable voucher. Uses the existing isolated V2 plot writer: balanced inventory debit/seller credit, creates available inventory plots without changing selling price, rejects duplicate plot codes and cross-project accounts, and replays idempotently.
- Verification: `V2PlotEntryWriterTest` passed (6 tests / 34 assertions); controller/routes PHP lint, all six named routes, Blade cache compilation, and `git diff --check` passed. No business database migration/write was run. Browser acceptance remains pending; isolated-pilot write gate remains enforced.

### C3 — Bank Receipt and Bank Payment (2026-11-05, engineering complete)

- Added separate project-scoped Bank Receipt and Bank Payment workspaces. They only offer eligible verified online/cheque sources of the matching direction, fix the amount from the source, require an active bank account owned by the selected project, validate balanced opposite-side allocations, and post through the existing isolated-pilot settlement workflow with unique source linkage.
- Added AJAX source and posted-voucher history lists plus project-scoped printable voucher details and posting lines. Posting refreshes both lists without a full page reload. Bank GL amounts are serialized as two-decimal strings to satisfy exact financial validation.
- Verification: `V2BankTransferWriterTest` passed (3 tests / 21 assertions); controller PHP lint, named-route registration, Blade cache compilation, and `git diff --check` passed. No migration or business database write was performed. Browser acceptance remains user-owned; pilot posting remains environment/gate restricted.

### C4 — Bank Deposit and Bank Withdrawal (2026-11-05, engineering complete)

- Added dedicated project-scoped Deposit and Withdrawal screens, sidebar entry points, AJAX source/history lists, and printable voucher output.
- Both directions post balanced cash↔bank lines through the isolated V2 transfer writer. Withdrawals may select an eligible cleared cheque; the selected source fixes the exact amount/reference and is protected against duplicate linkage or amount mismatch. Existing source cheque rows remain unchanged.
- Verification: `V2BankTransferWriterTest` passed (4 tests / 25 assertions); PHP lint, five named-route registrations, Blade cache compilation, and `git diff --check` passed. No configured business database migration/write was run. Browser acceptance remains user-owned; pilot write gates remain enforced.

M0 `██████████` 100% · M1 `██████████` 100% · M2 `██████████` 100% · M3 `██████████` 100% · M4 `██████████` 100% · M5 `██████████` 100% · M6 `██████████` 100% · M7 `██████████` 100%
Overall engineering implementation: **100%**. Browser acceptance remains user-owned. The isolated MySQL clone migration rehearsal for M6 remains pending because the selected clone lacks the required V2 business baseline; no migration was applied. Shared attachments mentioned in broad M1 wording remain outside the delivered directory/details/print scope and require a separately designed storage layer.

- [x] M0 — Preparation: existing source and posting map captured; no legacy records rewritten.
- [x] M1 core — Common voucher identity, search/list, details, and print delivered for the supported CR/CP/PPR, JV/CV, and legacy ledger sources.
- [x] M2 — Per-voucher approval settings and posting gate for supported CR/CP, CP bank-clearance, and legacy JV writers.
- [x] M3 — Cheque lifecycle and purpose (code, focused tests, and local MySQL migration complete; browser QA pending).
- [x] M4 — Online transaction capture, append-only status lifecycle, and source history/print.
- [x] M5 — Bank receipt/payment and allocation posting.
- [x] M6 — Receipt allocation, plot purchase posting, existing plot inventory availability bridge, and reversal safety.
- [x] M7 engineering — Integrated automated regression for plot, bank, cheque/online, approval, and existing JV workflows.

Preparation: **M0 complete**. Implementation: **M1–M7 engineering work complete**; browser QA remains user-owned. M3/M4 migrations are applied locally; M5 bank-account and transfer-source migrations are verified on the isolated MySQL clone `bluemarketing_v2_phase5_test`. The M6 plot migration has passed SQLite integration tests. Its MySQL rehearsal is pending because a no-write `--pretend` attempt found the selected clone has no `projects` V2 baseline; no migration was applied. The broader M1 wording in the audit also mentions shared attachments; those are not part of the delivered common list/details/print core and need a separately designed storage layer. Percent is milestone-based rather than an effort-weighted estimate.

## M0 findings

| Requirement/source | Current repository contract | Safety/design note |
|---|---|---|
| CR/CP source | `customer_ledger` (`transaction_type`, `type_id`, `reference`, `project_id`, `payment_type`, amount sides, bank/check metadata) | A source can own a posted `ledgers` row; its financial identity is immutable after posting. |
| BR clearance leg | `ledgers`, linked through `customer_ledger_id`, source reference `BANK_CLEARANCE#<customer_ledger.id>` | Clearance is project scoped, row locked, pending-only, and rejects duplicate/conflicting BR rows. Keep source event and accounting leg separate. |
| Booking/PPR receipt | `customer_ledger` plus `booking_vouchers`; `BookingController::cash_in` / `deposit` | Preserve booking/source IDs and existing allocation semantics; don't infer a Plot Purchase from booking cancellation labels. |
| JV/CV | `journal_vouchers` / `journal_voucher_details`; commission voucher tables and legacy ledger rows | Keep existing voucher IDs; integrate via adapters rather than replacing posted history. |
| Bank identities | `project_bank_accounts` + project COA mapping | Every selected account must belong to the active project and remain active for new posting. |
| Cheque status history | `customer_ledger.check_history` JSON and status/bank fields | Existing lifecycle is partial. Add append-only event history for new lifecycle work; avoid rewriting historic JSON/status. |
| Existing approvals | `pending_updates` for proposed changes to financial records; finance approval routes | This is not a per-voucher approval gate. New policy/queue must be distinct and must stop posting before approval. |
| Online payment | Payment type/labels exist; no equivalent transaction confirmation lifecycle found in focused paths | Add a source state machine before enabling linked bank posting. |
| Plot Purchase | No land-plot purchase inventory/workflow found; stock purchase is material inventory | Must be a separate plot/seller/inventory module, not reuse stock purchases or cancellation codes. |

## Verification / limits

- Reviewed the audit, `routes/web.php`, `CustomerLedger`, `LedgerController`, `Finance/VoucherController`, `ReportController`, `ProjectBankAccount`, and source migration.
- No database migration or production/history mutation performed.
- No automated tests run in this documentation/source-mapping slice.
- Remaining external acceptance: browser QA and MySQL V2 clone migration rehearsal after restoring/choosing a clone with the required baseline; production deployment remains separate.

## 2026-11-05 implementation update

- M0 source map is complete.
- M1: fixed CR/PPR AJAX list display-number lookup to resolve both source-level and ledger-level display identities without an undefined collection.
- M1: CR/CP voucher-number filter now matches project-scoped configured display numbers as well as legacy ledger numbers.
- No posting, deletion, renumbering, or historical data change was made.
- M1: added a project-scoped `Daily Voucher Directory` route and sidebar entry with AJAX search, date/type/account filters, pagination, and common CR/CP/PPR, JV/CV, and unlinked legacy D/C/BR/BO/BW listing.
- M1: added a detail modal with source fields, available cheque/source history metadata, JV/CV account lines, and project-owned ledger lines; ledger display respects this project's account boundary.
- M1: linked rows use existing source-specific print routes; unposted legacy customer-ledger sources use a new project-scoped source print view that clearly identifies missing accounting lines. JV/CV rows require `read jv`; their print link is shown only to `print jv` users.
- M1 validation: PHP syntax, route registration, Blade compilation, and `git diff --check` passed. No automated tests or browser QA were run.
- M1 follow-up from the broader audit wording: no shared voucher attachment table/storage or append-only common audit-event model exists in the inspected project paths; this change exposes available legacy timestamps/check history only and does not fabricate history or migrate files.
- M2: added project/voucher-type approval policies with an assigned, qualified project approver; submitter self-approval is prohibited.
- M2: supported legacy CR/CP, bank-clearance CP and JV writers now keep ledger lines inactive until approval; rejection requires a reason. Submission/decision events and in-app notifications are recorded. V2 posting APIs fail closed when the policy is enabled.
- M2: added approval settings, pending queue, decision and history UI/routes. CV remains out of scope because its production posting path is disabled.
- M2 validation: `DailyVoucherApprovalWorkflowTest` passed (4 tests / 12 assertions); `JournalVoucherLegacyUpdateTest` passed (3 tests / 23 assertions); route registration, Blade compilation, PHP syntax, and `git diff --check` passed.
- M2 local migration is defined but has not been applied to the configured database. Browser QA remains user-owned; migrate locally before testing the settings/queue.
- M3: added required cheque purpose for new cheque receipts and direct cheque withdrawals, while keeping selected pending-cheque clearance from demanding a new purpose; correction/edit payloads retain purpose and posted source identity protects it from in-place changes.
- M3: cheque creation and pass/clear/return/reject decisions append project-scoped lifecycle events; existing `check_history` remains readable for compatibility. Common voucher details and source print show purpose, current status, and event history.
- M3 validation: `ChequeLifecycleHistoryTest` passed (2 tests / 5 assertions); `LegacyCashVoucherCorrectionTest` passed (2 tests / 7 assertions); Blade cache compilation, PHP syntax checks, and `git diff --check` passed.
- M3 migration `2026_11_05_000002_add_cheque_lifecycle_history` applied successfully to local MySQL (`bluenew`) after Laragon MySQL started.
- M4: added project-scoped append-only online lifecycle events with transaction number, bank identity, passing date, status transition, note, actor, and event timestamp.
- M4: source creation records online capture; supported legacy and isolated V2 settlement paths record confirm/reject/cancel; EC bank settlement records online and cheque resolution events without changing posting amounts/accounts.
- M4: common directory audit details and source print include online transaction metadata and lifecycle history.
- M4 validation: online/cheque focused tests passed (6 tests / 18 assertions), PHP syntax and Blade cache checks passed, `git diff --check` passed.
- M4 migration `2026_11_05_000003_create_finance_online_lifecycle_events` applied and status confirmed on local MySQL.
- M5: bank-settlement events retain the selected project bank-account identity; settlement validates active project ownership, exact source amount, one matching bank GL leg, correct bank side, and balanced totals. Fixed PPR receipt direction: customer-ledger `amount_out` does not imply a bank credit; receipt series debit the bank account.
- M5 regression: `OriginalVoucherV2WorkflowHttpTest` passes (47 tests / 366 assertions), including pending-cheque clearance, PPR booking clearance, idempotent replay, failed foreign-account allocation rollback, and an explicit assertion that PPR clearance debits the selected bank GL account.
- M5: added isolated V2 standalone Bank Deposit/Withdrawal posting with project bank/COA validation, balanced multi-account allocations, cents-accurate totals, idempotency, and reversal support. Linked bank settlements now record typed source links with unique customer-ledger claims and narration; voucher register, original-ledger detail, and posting reports include the source and display number.
- M5: added `finance_bank_transfer_sources` schema. On the isolated MySQL clone, safely resumed the partially-applied bank-account migration after adding its required composite account key, then applied and inspected the bank-transfer source migration. Configured `bluenew` and production were not migrated or changed.
- M5 validation: `OriginalVoucherV2WorkflowHttpTest` + `V2BankTransferWriterTest` pass (50 tests / 390 assertions); standalone deposit/withdrawal, replay, allocation balancing, project isolation, reversal, voucher-register display, and ledger details are covered. Migration status and MySQL foreign keys/indexes verified on `bluemarketing_v2_phase5_test`; PHP syntax and `git diff --check` pass. Browser QA remains user-owned.
- M6 plot receipt/purchase posting and legacy plot inventory availability bridge are implemented; M7 integrated automated regression is complete.
- M5 migration `2026_11_05_000004_add_bank_account_to_settlement_events` applied and status-verified on isolated MySQL clone `bluemarketing_v2_phase5_test`; configured `bluenew` was not changed.
- M3/M4/M5 focused verification: cheque lifecycle, online lifecycle, and original voucher workflow tests pass (51 tests / 379 assertions) in the isolated SQLite test environment. A first combined command inherited the clone DB override and was stopped by the test isolation guard; rerunning with a clean environment passed.

### M6 — plot receipts and purchase (2026-11-05, complete)

- Added isolated typed plot receipt, per-booking/per-plot allocation, and purchase/item schemas with restrictive foreign keys. No legacy plot, booking, ledger, or voucher row is overwritten.
- Added project-scoped posting APIs: multi-plot receipt requires exact total allocation and each active booking/plot/customer/receivable account to match the selected project; per-plot receipt print and source details are available.
- Added plot purchase lines capturing plot code, area, unit, rate, seller, and inventory account, with balanced posting and request-key replay protection. Each purchase now creates a correctly classified available legacy plot in the same project, linked to its source item. Explicit active road/facing/type and optional project plot-definition checks prevent incorrect inventory setup; purchase cost is not copied into the plot selling price.
- Added append-only reversal for plot receipts and purchases; voucher register and ledger report readers expose their typed sources.
- Purchase cancellation appends exact reversal and deactivates the linked plot without hard deletion. It is rejected when the plot is booked or has booking history.
- Verification: `V2PlotEntryWriterTest` passes (5 tests / 31 assertions), including project isolation, allocation balance, replay, inventory availability, preserved selling price, safe reversal, and booking-history protection. The V2 migration is exercised by SQLite feature fixtures; no configured business database was migrated.
- The plot receipt/purchase sources and writes remain isolated-pilot gated; browser acceptance remains separate. Production data was not changed.

### M7 — integrated automated regression (2026-11-05, engineering complete)

- Ran the integrated finance suite covering plot receipt/purchase and inventory bridge, bank deposits/withdrawals and linked clearance, cheque/online lifecycle histories, voucher approval gates, and legacy JV update/reversal.
- Verification: 7 focused feature files passed, **66 tests / 467 assertions**. PHP lint passed for changed M6 PHP/migration/test files and `git diff --check` passed.
- MySQL clone rehearsal preflight (`--pretend`) stopped safely: `bluemarketing_v2_phase5_test` did not contain the required V2 `projects` baseline. No migration was applied by this attempt. Browser QA remains user-owned; do not migrate production or the normal `bluenew` database for this isolated pilot.
