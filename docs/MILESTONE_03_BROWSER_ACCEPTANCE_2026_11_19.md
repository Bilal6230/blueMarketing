# CURRENT CROSS-MILESTONE ACCEPTANCE — 2026-11-24 (authoritative)

- **Milestone 05 CLOSED / OWNER ACCEPTED**: grouped Voucher Settings browser PASS after pull; `Milestone05ReversalSettingsContractTest` **8 PASS / 122 assertions**; `view:cache` PASS. Six working reversal-enabled browser families `CR, CP, BD, BW, PPR, PP` grouped above approval-only families; unsupported reversal-only switches removed. Earlier M05 pending notes below are historical and superseded.
- **Milestone 03**: Stock Entry/Billing edit, skeleton and narration browser fixes previously owner-accepted; all other voucher-family browser tests passed. **Only Labour Payment CP browser acceptance remains, explicitly ON HOLD**. Do not repeat passed tests.
- **Milestone 04**: CLOSED/OWNER ACCEPTED. **Milestone 02**: cross-module permission matrix formal acceptance remains. **Milestone 01**: CLOSED. **Milestone 06**: deferred until LAST (application activity audit).
- **No further Milestone 05 browser or terminal checks pending.** Do not reopen M05 without a new change/defect.

## M05 CLOSED — owner accepted 2026-11-24; settings grouping layout follow-up

Owner confirms final six supported reversal switches correctly displayed in browser; unsupported extra switches removed, approval settings unaffected; final contract test **7 PASS / 111 assertions**, view cache PASS and all previous finance regression gates verified. Mark Milestone 05 **CLOSED / OWNER ACCEPTED**. Latest cosmetic Voucher Settings refinement groups **six Reversal-capable types first** (CR, CP, BD, BW, PPR, PP) and **approval-only types second** with distinct headings/counts; no finance policy or DB writes changed, new layout-only contract test and browser smoke pending owner pull. M03 Labour CP remains ON HOLD and M06 stays last.

## M05 owner acceptance scope correction — 2026-11-24

Owner confirms final browser Tests **1/2/3 PASS**. Test 4 was withdrawn because EC/ECP/Stock/Labour/Booking entries have no functional browser Reverse button. Removed their reversal-only cards and restricted the reversal-settings Save endpoint to **CR, CP, BD, BW, PPR and PP**; existing approval setup, posted history, and M05 backend posting guards remain unchanged. Latest small settings-contract test and view cache need owner smoke-run after pull. Re-doing earlier passed tests is unnecessary. M03 Labour CP remains ON HOLD.

## M05 source-aware API / independent Delete — final code handoff (2026-11-24)

The M05 generic authenticated API reversal now resolves original project-scoped typed claim and delegates to guarded journal/commission, cash, bank or plot lifecycle service; generic Stock/Booking/Labour reversal fails closed until dedicated safe lifecycle exists. Original Plot Receipt/Purchase **ordinary Delete** retains its compensating posting even when user-initiated Reversal is OFF. Typed batch alias `PR/PPR` and `CHR/BOR`, `CHP/BOP` switches are unified. Reversal-only policy entries cover EC/ECP and typed Stock/Labour/BO series without enabling their approval controls. Contract, SQLite integration and posted JV integration regressions committed. **Latest code requires owner's final local test results and limited browser checks; existing M03 accepted tests must not be reopened.** Full commands in `docs/README.md`. Labour CP remains ON HOLD.

## M05 latest route audit — 2026-11-24

Final code review includes four web Reverse endpoints **plus three authenticated finance API reversal/void endpoints**, all routed to the same central policy-guarded writer. Critically, saved Plot Receipt batch series `PR` now maps to Voucher Settings `PPR`; an OFF policy blocks direct API reversal, not merely the browser button. Added stock/labour and EC/ECP reversal-only switches, explicit API 409 policy error, SQLite direct-reversal regression, real JV posted OFF/ON test, and project-scoped per-series policy lookup optimization. **Code pushed, newest owner terminal/browser gate pending.** Already-passed second-batch M05 browser tests must not be repeated. M03 Labour Payment CP remains ON HOLD.

## M05 final code batch — 2026-11-24 (owner acceptance pending)

All **four existing HTTP reversal routes** (typed cash, bank BD/BW, Plot Receipt and Plot Purchase) were audited and confirmed to share the central `PostingReversalWriter` policy enforcement. This is not the same as ordinary Edit/Delete, booking cancellation or cheque/online custody release, and no nonexistent legacy Reverse route should be claimed. Added EC/ECP policy-only settings without generic approvals, per-series policy caching for daily/bank listings, SQLite policy integration tests and verified-JV posting ON/OFF integration regression. **Code committed, latest tests and final EC/ECP browser acceptance still pending.** Already owner-verified M05 batch 2: 3 PASS/40 assertions, browser PASS, 6 clean projects. M03 Labour CP ON HOLD; do not repeat its already passed modules. Authoritative final M05 test commands and sign-off criteria in `docs/README.md`.

## M05 owner verification — 2026-11-24

**Second M05 reversal settings batch PASSED:** owner confirmed `Milestone05ReversalSettingsContractTest` 3 PASS/40 assertions; `view:cache` PASS; all six project financial postings clean; and all focused browser checks PASS for the implemented reversal ON/OFF behavior and button visibility. No need to re-test this gate. M05 continues only for remaining reversal-path coverage and final regression. M03 Labour CP remains ON HOLD.

## Milestone 05 handoff note — 2026-11-24

Milestone 05 active independently of the M03 Labour browser hold. M05 initial settings migration and contract tests were owner-verified PASS (2 tests / 25 assertions; all six projects clean). A second code batch adds reversal UI availability to bank BD/BW, plot PPR/PP and typed cash. Current M05 browser acceptance is separate from already signed-off M03 CRUD tests, and will test only reversal ON/OFF visibility and direct-route enforcement. Do **not** reopen passed M03 cases. The M05 authoritative details remain in `docs/README.md`.

## 2026-11-24 — Owner accepted Stock Entry/Billing browser fixes; Labour CP remains only M03 browser gate

Owner explicitly reports "**every thing is fine**" following final Entry/Bill Edit skeleton, narration, compact design and modal close fixes. Mark the latest Stock UI/browser remediation **PASS (owner-confirmed)**. Previously passed voucher-family browser gates remain closed and must not be re-requested. **Milestone 03 now has only Labour Payment (CP) browser acceptance outstanding**, and the owner previously placed it **ON HOLD**. Formal M03 completion awaits that test. Milestone 04 is already owner-accepted, Milestone 02 still has the cross-module permissions acceptance gap, Milestone 05 reversal settings remain unstarted, and Milestone 06 full activity audit remains the final/deferred milestone. Existing older historical "pending" sections are superseded by this status.

## 2026-11-24 — Stock Entry/Bill Edit skeleton loader (owner browser retest pending)

Owner confirmed Stock Edit item rows and form are functioning but rejected the teal informational loading alert. Replaced the `Loading original voucher and item mappings...` message with a blue-grey animated **field-shaped skeleton** inside the shared Entry/Bill Edit modal. While fetching metadata/details, the actual form fields are hidden; once AJAX settles, skeleton disappears and the existing populated form or error is shown. Includes reduced-motion support and a contract regression in `StockDocumentLifecycleContractTest`. No accounting, stock balances, or modal close paths changed. **Code pushed; local test/browser confirmation pending. Labour CP still ON HOLD.**

# Milestone 03 — Browser Acceptance and Local Verification (2026-11-19)

**Current status (owner update 2026-11-24): All previously reported Milestone 03 browser gates, including Stock Entry/Bill, are PASS except Labour Payment (CP), which remains explicitly ON HOLD. Full milestone sign-off awaits only that Labour CP gate.** This document is the active browser checklist and exact handoff for `upgrade/laravel-framework`. Record actual PASS/FAIL and screenshots in a later owner response; do not mark untested areas green. Local data must be a disposable/test database. The configured project ID must come from current application selection, never a hardcoded number.

## Code fixes completed in the latest review batch

- `ExtraChargeBankSettlement::balances()`: select typed vs legacy EC balance reader by the actual `finance_extra_charge_sources` FK, not a global application switch. Legacy cash EC/ECP does not accidentally become an invalid typed source. Contract regression added.
- `BookingReceiptCashEditor`: before cash PPR Edit/Delete, check actual `finance_posting_booking_sources.booking_voucher_id` ownership **in addition to** cash source custody; refuse cross-writer mutation without touching amounts. Integration regression added.
- `LabourPaymentEditor`: reject legacy cash CP labour Edit/Delete if any related member is claimed by `finance_posting_labour_sources` (typed payroll). Corrected fixture and added integration test.
- `StockDocumentEditorTest`: added negative inventory rejection and foreign detail-ID rollback tests. `StockDocumentLifecycleContractTest` now checks exact source-FK, ledger and stock controller plus AJAX UI.
- Stock page uses **Bootstrap 5**: changed new Stock Edit/Delete modal helpers to Bootstrap 5's `bootstrap.Modal.getOrCreateInstance` rather than assuming `$(...).modal()` (Bootstrap 4 plugin). Custom events and AJAX remain jQuery.
- `DastiCashController::destroy`: restrict deletion to selected project, matching existing Edit/Update. Added `DastiCashProjectCrudContractTest`.

## Local verification — run tests before running migrations

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan route:list --path=admin/stocks
php artisan migrate:status

php -l app/Services/Finance/StockDocumentEditor.php
php -l app/Services/Finance/LabourPaymentEditor.php
php -l app/Services/Finance/BookingReceiptCashEditor.php
php -l app/Services/Finance/ExtraChargeCashEditor.php
php -l app/Services/Finance/ExtraChargeBankSettlement.php
php -l app/Http/Controllers/StockController.php
php -l app/Http/Controllers/BookingController.php
php -l app/Http/Controllers/JournalVoucherController.php

php artisan test --filter=StockDocumentEditorTest
php artisan test --filter=StockDocumentLifecycleContractTest
php artisan test --filter=LabourPaymentEditorTest
php artisan test --filter=BookingReceiptCashEditorTest
php artisan test --filter=ExtraChargeCashEditorTest
php artisan test --filter=ExtraChargeCashLifecycleContractTest
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=SalesVoucherFullLifecycleContractTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=CashPostingWriterTest
php artisan test --filter=BankTransferWriterTest
php artisan test --filter=BankTransferCorrectionContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=BookingReceiptSameSourceContractTest
php artisan test --filter=DastiCashProjectCrudContractTest
php artisan finance:audit-posting-balances
```

**Before browser testing only after tests green**, run pending additive migrations *on the local configured TEST database* (check `php artisan migrate:status` first). Do not run against a live customer database. Existing migrations in place should say "Nothing to migrate" when invoked twice:

```powershell
php artisan migrate --path=database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
php artisan migrate --path=database/migrations/2026_11_19_130000_link_stock_movements_to_physical_source_lines.php
php artisan migrate --path=database/migrations/2026_11_19_150000_add_stock_document_archive_columns.php
php artisan migrate --path=database/migrations/2026_11_19_160000_add_labour_payment_archive_columns.php
php artisan optimize:clear
php artisan view:cache
php artisan finance:audit-posting-balances
```

## Browser acceptance — sequence

**Global rule for each test:** Select the correct local test project, use a disposable voucher, and note original Voucher ID/Number and account balance. Create, Edit (account/amount/date/narration where supported), Add/Remove details, Save unchanged again, Delete, reopen list and reports. Confirm every request stays in its AJAX/modal context, successes refresh the list, failures show toast/popup without Laravel stack trace, wrong-project record cannot be touched, customer balance is accurate and repeated saves do NOT create duplicate active postings. If a correction is blocked because the source is claimed by another writer, write down the exact error and do **not** force it.

1. **Approved CV — already owner browser PASS; regression only if changed.** `/admin/commission-voucher`: Draft Create -> Add/Remove/Save -> Approve -> Edit approved -> no-op Save -> Delete. Check Party Ledger and exact detail FK. Avoid re-testing unless needed.
2. **JV/SV** — `/admin/journal-voucher` (existing menu) and `/admin/sales-voucher`: Create two balanced rows (e.g. debit 100/credit 100), save; for SV use its **New SV Voucher** button. Edit approved JV and SV on disposable records: update two rows to 120/120, add/remove a row keeping balanced. Confirm original voucher ID/number and single batch, customer report shows corrected current legs. Ordinary Delete removes active effect without creating a new REV; invalid/foreign detail IDs refuse.
3. **Manual Bank Deposit/Withdrawal (BD/BW)** — through bank-transfer workspace in Finance menus: Create with bank and cash account, edit date/amount/account/narration, verify active ledger only new amount, no-op Save adds nothing, Delete removes it from active list/report but retains audit evidence. Linked cheque source must **reject** unsafe direct Edit/Delete pending linked custody flow.
4. **Cash CR/DR/CCR/CDR** — Daily Vouchers menu: Create, Edit, update bank/child account where applicable, Delete; validate account pair, optional attachment, balanced ledgers, no duplicate records, cross-project isolation. Note any voucher family whose route still shows reversal-only instead of ordinary Delete.
5. **BOR/BOP (bank online/cheque)** — verify cleared instrument selection, one selected source, populated bank/transaction fields, AJAX save, correct account posting and actual approval status. Do not change passed instrument custody with manual ID guessing; linked approval/cascade is a Milestone 04 dependency.
6. **Cash PPR (Booking receipt)** — Booking/received payments: Create one cash receipt, record CustomerLedger ID, linked Ledger ID and BookingVoucher ID. Edit amount/date/reference, verify IDs unchanged and Party Ledger reflects corrected amount; Delete and confirm all three become inactive, no visible REV. A receipt with a typed Booking posting must refuse legacy Edit/Delete.
7. **Cash EC/ECP** — `/admin/extra_charge/`: Create EC 300 and cash ECP 100. Edit ECP to 120 (check balance), attempt reducing EC to 100 (must show a meaningful error), increase EC to 350 (valid). Delete ECP before EC; all archived ledger rows remain, active balance correct. Do not bypass typed/online/cheque ownership.
8. **Stock Entry / Stock Bill** — `/admin/stocks`: Create stock IN Entry for item A qty 5; Edit to qty 7, check physical on-hand increased by exactly 2 and related ledger new amount; Add item B and remove item A while keeping non-negative balance; no-op Save must preserve movement IDs; Delete must archive source, lines, movement and financial legs and restore net inventory. Create purchase/sale Bill, edit and delete standalone unlinked bill. A billed Entry should refuse direct adjustment until linked Bill workflow handles it. Verify responsive Bootstrap 5 modals and AJAX error toasts.
9. **Labour Payment (CP)** — Labour module: Create cash labour payment with two workers; Edit amounts, Add/Remove worker, Save unchanged twice; check single customer ledger ID, exact worker rows and total. Delete: source, ledger and worker rows inactive with original evidence retained. Typed payroll-source member must reject a legacy override.
10. **Shared reports** — Daily Voucher Directory, Party Ledger, trial balance, Stock report and Labour totals: after corrections/deletes verify active current amounts only; internal archived evidence remains. Finally `php artisan finance:audit-posting-balances` must show zero findings for all selected projects.

## Acceptance record — updated 2026-11-24 (owner-confirmed)

The owner explicitly confirms that all other Milestone 03 terminal **and browser** scenarios passed. Earlier pending labels below are superseded; do not request those browser tests again without a relevant regression.

| Family | Terminal | Browser | Milestone 03 sign-off |
|---|---|---|---|
| Plot Receipt/Purchase, JV, SV, CV | Owner-reported PASS | Owner-reported PASS | PASS |
| BD/BW | Owner-reported PASS | Owner-reported PASS | PASS |
| CR/DR/CCR/CDR | Owner-reported PASS | Owner-reported PASS | PASS |
| BOR/BOP | Owner-reported PASS | Owner-reported PASS | PASS for Milestone 03 scenarios; deeper linked custody/cascade belongs to Milestone 04 |
| PPR cash | Owner-reported PASS | Owner-reported PASS | PASS |
| EC/ECP | Owner-reported PASS | Owner-reported PASS | PASS, including duplicate pagination fix |
| Verified-source narration 7/7 | Owner-reported PASS | Owner-reported PASS | PASS |
| Booking amendment #242 legacy BO/SV | Owner-reported PASS | Owner-reported working | PASS |
| Stock Entry/Bill | Previously implemented; owner reports other terminal tests complete | **PENDING** | **ONLY REMAINING GATE 1** |
| Labour Payment (CP) | Previously implemented; owner reports other terminal tests complete | **PENDING** | **ONLY REMAINING GATE 2** |
| All-project posting integrity | Owner-reported PASS, 6 projects, zero invalid active batches | N/A | PASS |

### Latest Stock UI re-test state (2026-11-24)

Owner confirms Edit item rows render and stock modal close buttons now work; however both Stock Entry and Stock Bill Edit form item rows had inconsistent layout vs the corresponding New form. A focused layout parity correction is committed on `upgrade/laravel-framework`: Item / Unit / Qty / Rate / compact Remove button / Line Amount / Total styling now follows the same compact row components as New Entry/New Bill. Pending owner verification: open Edit Entry and Edit Bill, inspect row layout parity, change quantity and save. Do not reopen earlier passed voucher tests. Labour CP remains ON HOLD.

### Stock Bill narration regression (2026-11-24; owner retest pending)

Owner screenshot shows Billing → Edit narration empty. Root cause: Bill Create had no narration field, Bills had no narration column, and the details API returned `''` for bill narration even after editing. Code now persists `stock_bills.narration` for Create and Edit and includes a New Bill narration input; details API reads the saved value. Run the additive column migration on the selected local TEST database before testing (no new table): `php artisan migrate --path=database/migrations/2026_11_24_160000_add_narration_to_stock_bills.php`. Browser acceptance: New Bill with narration → Save → Edit (prefilled) → modify narration → Update → reopen and verify persistence. Existing historical bills with no authored narration remain blank until explicitly edited. **Labour CP remains on HOLD.**

### Only two remaining browser gates

**A — Stock Entry / Stock Bill (2026-11-24 browser FAIL: Edit item rows missing, modal X/Cancel and post-save/post-delete close inconsistent; code correction committed, owner re-test pending)** `/admin/stocks`: On disposable local test project, create stock IN Entry item A quantity 5; edit quantity to 7 and verify exactly +2 on-hand change and updated ledger; add item B/remove item A without negative inventory; save unchanged twice (no duplicate movements); delete and verify active stock and accounting impacts revert while source history is retained. Create standalone *unlinked* purchase/sale Bill; edit and delete it. Attempt to edit a linked/billed Entry and confirm safe rejection. Verify Bootstrap 5 modals, AJAX toast error and no full-page reload.

**B — Labour Payment CP (ON HOLD at owner request; do not request testing yet)** Labour module: Create cash payment with two worker rows; edit individual amounts, add/remove a worker and verify amount equals worker-row sum; save unchanged twice (no duplicates); delete and verify source/ledger/worker rows inactive while history remains. Confirm typed payroll-claimed worker cannot be changed through legacy payment editor. Check AJAX modal errors, account totals, permissions and project isolation.

**Do not re-request tests 1–7 and 10 above**: their older detailed instructions are historical reference only, not pending gates. Owner still needs to confirm browser PASS for A and B before declaring Milestone 03 complete.

**Do not mark Milestone 03 100% or claim all voucher relationships verified until the new code's tests and this browser matrix are actually accepted.** Milestone 06 application-wide activity audit remains deliberately deferred to the LAST milestone (no audit tables now).
