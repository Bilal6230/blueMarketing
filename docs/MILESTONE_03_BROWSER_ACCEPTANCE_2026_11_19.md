# Milestone 03 — Browser Acceptance and Local Verification (2026-11-19)

**Current status (owner update 2026-11-24): All Milestone 03 terminal and browser acceptance tests are reported PASS except Stock Entry/Bill and Labour Payment (CP), which remain the only two browser gates. Full milestone sign-off awaits these two.** This document is the active browser checklist and exact handoff for `upgrade/laravel-framework`. Record actual PASS/FAIL and screenshots in a later owner response; do not mark untested areas green. Local data must be a disposable/test database. The configured project ID must come from current application selection, never a hardcoded number.

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

### Only two remaining browser gates

**A — Stock Entry / Stock Bill (2026-11-24 browser FAIL: Edit item rows missing, modal X/Cancel and post-save/post-delete close inconsistent; code correction committed, owner re-test pending)** `/admin/stocks`: On disposable local test project, create stock IN Entry item A quantity 5; edit quantity to 7 and verify exactly +2 on-hand change and updated ledger; add item B/remove item A without negative inventory; save unchanged twice (no duplicate movements); delete and verify active stock and accounting impacts revert while source history is retained. Create standalone *unlinked* purchase/sale Bill; edit and delete it. Attempt to edit a linked/billed Entry and confirm safe rejection. Verify Bootstrap 5 modals, AJAX toast error and no full-page reload.

**B — Labour Payment CP (ON HOLD at owner request; do not request testing yet)** Labour module: Create cash payment with two worker rows; edit individual amounts, add/remove a worker and verify amount equals worker-row sum; save unchanged twice (no duplicates); delete and verify source/ledger/worker rows inactive while history remains. Confirm typed payroll-claimed worker cannot be changed through legacy payment editor. Check AJAX modal errors, account totals, permissions and project isolation.

**Do not re-request tests 1–7 and 10 above**: their older detailed instructions are historical reference only, not pending gates. Owner still needs to confirm browser PASS for A and B before declaring Milestone 03 complete.

**Do not mark Milestone 03 100% or claim all voucher relationships verified until the new code's tests and this browser matrix are actually accepted.** Milestone 06 application-wide activity audit remains deliberately deferred to the LAST milestone (no audit tables now).
