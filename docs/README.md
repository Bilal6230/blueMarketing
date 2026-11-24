## 2026-11-24 — Stock Entry/Billing Edit UI and modal dismiss fix (owner re-test pending)

Owner screenshot confirmed the item rows were absent in Edit Stock Entry; Stock Entry/Billing edit forms and all stock module modal X/Cancel or post-AJAX close behavior needed correction. Root cause: `stockDocAddLine` appended the row's fields container to itself rather than the row wrapper. Fixed in `resources/views/admin/stock/index.blade.php`, added Edit rows with line totals, corrected shared modal dismissal handling for static/dynamic modals, and an extra contract assertion in `StockDocumentLifecycleContractTest`. Changes are code-committed but **local tests and browser acceptance are NOT yet reported**. Existing Entry/Bill Delete functionality was owner-reported working. **Labour Payment CP is explicitly ON HOLD** until the owner finishes Stock re-testing. No other passed Milestone 03 browser gate should be requested again.

## Milestone 03 owner acceptance update — 2026-11-24

**Only two browser acceptance gates remain:** (1) Stock Entry / Stock Bill, and (2) Labour Payment (CP). Owner confirms all other Milestone 03 terminal and browser tests PASS, including JV/SV/CV, Plot, BD/BW, CR/DR/CCR/CDR, BOR/BOP, PPR, EC/ECP, verified narration 7/7, Booking #242 and posting audit. Do not request passed browser tests again. Milestone 04 linked instrument custody/cascade remains separate. Refer to [Milestone 03 browser acceptance](MILESTONE_03_BROWSER_ACCEPTANCE_2026_11_19.md) for the exact two tests. Milestone 03 is not fully signed off until both remaining browser gates PASS.

> **LATEST ACCEPTED STATUS — 2026-11-23:** **Milestone 04 CLOSED / OWNER ACCEPTED** (Cheque/Online correction, pending approval, Withdraw & Replace, zero-voucher same-ID edits, compact source narration, approval review, pending-state badge/actions). Owner reported all core browser scenarios PASS and confirmed the final compact design; final BankManagementRegisterJavaScriptContractTest **2 PASS / 28 assertions**, BankLinkedCorrectionApprovalIntegrationTest **19 PASS / 138 assertions**, and read-only posting audit **6 projects clean**. **Milestone 03 now next active work; NOT yet accepted.** Use the final M04 behavior specified below; older M04 sections are retained as historical superseded notes. Milestone 06 Activity Audit remains LAST/deferred.

# BlueMarketing — Current Development Handoff

**Updated:** 2026-11-23  
**Branch:** `upgrade/laravel-framework`  
**Local application:** `C:\laragon\www\blueMarketing`  
**Database:** whichever database is configured in `.env`. Owner currently uses `bluenew_clean_final`. Never hard-code database names, introduce separate V2 finance, or reinstate V2 isolation.
**Documentation policy:** Update **this single document after every code batch**. Do not open new milestone/handoff documents. Historic requirements: `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md`. Earlier superseded handoffs are not authoritative.

## Roadmap — six milestones (Activity Audit last)

| # | Milestone | Verified status |
|---|---|---|
| 01 | Nonblocking loading and voucher-tinted skeletons | **COMPLETE** — owner browser + PHP tests PASS |
| 02 | Action-specific permissions, Roles page and backend enforcement | **PARTIAL ACCEPTANCE** — implemented, tests PASS, owner confirmed CR Edit visibly works; complete cross-module browser permission matrix not yet accepted |
| **03** | FK/relationship integrity, safe Create/Edit/Delete, lifecycle columns | **ACTIVE — PARTIAL**. Owner confirms browser batches 1/2/3 PASS; new seven-voucher bank-source and counter-account expansion underway. Plot/JV/CV differential correction owner-accepted; Approved CV browser PASS, 16 tests PASS / 139 assertions. BD/BW same-batch Edit/Delete local **13 tests PASS / 102 assertions**, posting audit 6 projects clean (BD/BW browser still pending). SV production Create/Post and exact typed/legacy Edit/Delete now committed, **SV tests/browser pending**. PPR/EC/ECP and Stock/Labour remaining source-domain lifecycle acceptance incomplete. |
| **04** | Cheque/Online correction, approval and linked voucher replacement | **COMPLETE — OWNER ACCEPTED / CLOSED (2026-11-23)**. No voucher history: original ID edited. Any voucher history: old source withdrawn, pending voucher approvals cancelled, posted voucher/ledger soft-voided, new source created for bank re-verification. Approval ON/OFF, project guard, review modal, compact status/actions and one-line narration accepted; 19 integration PASS / 138 assertions; final register JS 2 PASS / 28 assertions; all 6 project posting audit clean. |
| 05 | Reversal settings ON/OFF and full regression | **NOT STARTED under new rules** |
| **06** | **Centralized application-wide activity audit (LAST)**: user/role/project/action/request identifiers, exact changed tables and primary keys, field-level before/after values, create/update/delete/approve/restore/cancel, blocked attempts; include Super Admin/Admin/staff; secure/immutable storage, controlled permissions, no sensitive secrets | **DEFERRED BY OWNER** — design planned, DO NOT implement until Milestones 01–05 and current voucher workflows are complete |

## Milestone 03 — 2026-11-24 second browser remediation batch (owner tests pending)

**Owner findings:** Booking creates BO+SV but Edit did not amend original SV, blank discount creation lacked visible errors, any auto-SV prevented Booking Delete; EC/ECP rows were mixed in main listing and EC Edit omitted other source fields; Stock Entry Edit/Delete rejected its own posted customer-ledger amount, Edit differed from Create; Labour was incorrectly project-scoped, had failed Add Labour/Site.

**Committed implementation:**
- `BookingAmendmentService::syncOriginalSale`: on changed price **or booking date**, selects exact source-owned SV and BO from canonical FKs, verifies original two balanced SV legs and detail rows, then updates BO customer ledger, SV original ledger legs, original JV detail amounts/date and SV header totals in the same transaction as Booking Edit and amendment audit. Missing/mismatched source fails closed; no replacement voucher or ID changes.
- Booking Create accepts empty optional `discount_value` as zero; Create Blade defaults to zero and displays validation/server error messages rather than silent failure.
- Original Booking Delete is permitted only for isolated initial BO+SV with two verified balanced GL legs and no BookingVoucher/receipt/other finance history. It soft-deletes original SV/header details, deactivates BO and both linked ledgers, archives Booking and reopens Plot within one DB transaction. Any resale, receipts, EC or unrelated GL records still require audited Booking Cancellation. Nothing is physically deleted.
- EC register again shows **EC only**. New Payments action on each EC opens `fvcPaymentHistoryModal` with only that EC's matching ECP payments, their voucher number/date/reference/amount/type and Cash ECP Edit/Delete, with nested dialogs over payment history; the page updates via existing AJAX refresh. For EC full Edit the customer, plot, charge type and slip reference fields are shown; exact account mapping and source type are synchronized for an **unpaid/unreserved EC only**, after active booking and unique source checks. Paid/reserved EC keeps these identity fields immutable but allows Date/Amount/Description corrections with linked balances verified. ECP own identity stays locked. Existing payment source IDs and bank custody protections remain.
- Stock new posting writer now converts money **minor units to decimal rupees** with integer arithmetic. Existing exact 100× legacy posting pattern is accepted only if original source, customer-ledger side and both GL legs all match, then a normal Edit normalizes amounts atomically to correct source monetary units; Delete archives even uncorrected but internally consistent legacy sources. Other inconsistent records remain blocked. Edit modal now presents original number, direction, party, vehicle/driver (read-only), date, narration and item cards matching Create layout; editable quantity/rate remain guarded.
- Labour master is **global**: worker Create/List/Edit/Attendance/Payroll eligibility no longer filters by `labours.project_id`. A focused, conditional migration drops that obsolete column and its own FK (no new tables). Sites, Attendance, Site Vouchers and cash Payroll remain project-scoped by their respective tables. Site dropdown posts `subhead_accounting_id`, not `project_head_subheads.id`; AJAX error alerts show the actual server validation message.

**Test gate (no owner results yet):** pull; `php artisan optimize:clear`; `php artisan migrate --path=database/migrations/2026_11_24_120000_make_labours_global_master.php`; `php artisan view:cache`; `php artisan test --filter=Milestone03OwnerBrowserRepairBatchContractTest`; `php artisan test --filter=Milestone03ActualBrowserRepairsContractTest`; `php artisan test --filter=BookingStoredPlotSizePrecisionTest`; `php artisan test --filter=StockDocumentLifecycleContractTest`; `php artisan test --filter=StockDocumentEditorTest`; `php artisan test --filter=ExtraChargeCashEditorTest`; `php artisan test --filter=LabourPaymentEditorTest`; `php artisan finance:audit-posting-balances`. Schema and source writes were not executed by the assistant; verify only on owner's disposable local DB before browser tests.

**Browser targets not yet accepted:** Booking #241 amend price/date and verify identical BO/SV IDs, booking create without discount, no-receipt new Booking delete archives initial BO/SV; EC main list no ECP, per-EC Payments history modal with nested Cash ECP Edit/Delete, unpaid EC full Edit + paid EC identity restriction; Stock Entry/Bill Edit/Delete both old and new stock sources; Labour global worker visible in another project, Site assigned to selected PHS, payments project-scoped. Previously accepted JV, CR/DR/CCR/CDR/BOR/BOP/PPR/BD/BW and no-flash UI checks remain PASS.

## Milestone 03 — 2026-11-24 owner browser defects: booking, EC/ECP, stock, labour

**Owner browser acceptance:** JV full lifecycle PASS. SV is created by Plot Sale/Booking, **not** by the standalone SV directory; do not ask owner to create SV from SV listing. Existing M03 CR/DR/CCR/CDR/BOR/BOP/PPR/BD/BW, counter-account and no-flash filter browser cases remain PASS and must not be repeated.

**Fixes committed, owner local/browser retest outstanding:**
- Labour `/admin/labours` fatal `Class App\\Services\\Finance\\V2OriginalWorkflowAccess not found`: remove obsolete condition and unreachable mode-only form from `resources/views/admin/labours/index.blade.php`; normal unified workflow retained. No V2-named class reintroduced.
- Plot Sale deletion: jQuery posts `booking_id` but `BookingController::destroy_booking` previously read `id`; now validates and reads exact submitted `booking_id`. Existing project scoping and financial history guard remain. The register now shows returned 409/403 in SweetAlert instead of `alert('Error deleting booking')`. **SV or receipt history legitimately blocks ordinary Delete; use audited Booking Cancellation**. Booking #241's **Edit** failure remains unresolved until the exact Laravel log exception is reviewed; current `BookingController::updateBooking` logs `Booking amendment failed.` with the exception. Do not claim it fixed.
- EC/ECP: Extra Charge register previously selected only `null` and `EC` sources, hiding all active `ECP` payments from Edit/Delete buttons. Query now includes ECP; list displays its actual `amount_in` and linked payment label. Cash ECP uses existing same-source editor. The Edit JSON provides actual debit/credit account names and the modal displays them read-only. Cash ECP debit = project Extra Charge Cash, credit = customer/plot Extra Charge Receivable; EC debit = Receivable, credit = Income. These are source-bound project mappings, not arbitrary changeable party accounts. Parent EC Delete remains correctly forbidden while payments exist; delete eligible ECP first. Other EC customer/plot/charge type changes need an audited source-specific workflow, not ledger identity mutation.
- Stock Entry/Bill: Existing late-open Edit modal waited for both meta and details requests; failure gave no visible modal. Edit now opens immediately, displays loading/errors, explains linked/billed/typed restriction and enables Save only on eligible detail. Both Edit/Delete modals use Bootstrap 5, jQuery Bootstrap or working jQuery fallback if CDN unavailable. `Entry No (optional)` is **the existing Stock Entry Slip No** and links a bill to a specific entry; blank for independent bill; linked entry cannot be deleted/edited independently. No DB migration.

**Local run:** `php artisan optimize:clear`; `php artisan view:cache`; `php artisan test --filter=Milestone03ActualBrowserRepairsContractTest`; `php artisan test --filter=ExtraChargeCashEditorTest`; `php artisan test --filter=StockDocumentEditorTest`; `php artisan test --filter=StockDocumentLifecycleContractTest`; `php artisan test --filter=LabourPaymentEditorTest`; `php artisan finance:audit-posting-balances`. The new focused test is added to finance CI. **Do not claim PASS before owner logs.**

**Owner browser retest:** Labour sidebar > Labour > Labour List (formerly crashed), create cash payment using Labour Payments flow; Stock > Entries > disposable unbilled entry Edit/Delete and Stock > Bills > standalone Purchase/Sale Bill Edit/Delete; Extra Charges > cash EC > Receive Cash > ECP row Edit/Delete; parent EC Delete only after child payments removed; Plot Sales > Booking Edit #241 retry after capturing `storage/logs/laravel.log` latest `Booking amendment failed.` exception, and test Delete on a disposable no-history booking. Do **not** delete actual booking #241 if it has SV/PPR financial history.

## Milestone 03 — DR actions and voucher-list top-filter removal (2026-11-23)

**Code committed; new owner tests pending:** The Daily Voucher Directory previously excluded the `DR` canonical transaction type from the Edit, rejected-revision and Delete URL eligibility lists, although `LedgerController::destroy` and `SameSourceCashVoucherEdit` already supported DR. `DailyVoucherDirectoryController` now includes DR with exactly the same role-scoped checks as other cash vouchers; `RejectedCashVoucherController` GET and POST also admit the canonical DR series. Pending/rejected/verified bank vouchers still use their existing approval, typed reverse, or audited bank-release gates; only eligible cash postings expose Edit/Delete. Delete remains a soft-deactivation of every original ledger leg, not a hard delete. No database migration.

**Top filter-card flash permanently removed from six shared voucher listing views:** daily CR/DR/CP/CCR/CDR/BOR/BOP directory, BD/BW transfer workspace, PPR/Plot Receipt workspace, Plot Purchase workspace, JV/SV directory, and CV directory. Each top filter block is **removed from the server-rendered Blade** rather than hidden after load. Kept table Quick Search, Excel-style column filters, record pagination, and existing New Voucher permission-gated actions, moving BD/BW, Plot Purchase and CV create buttons into their header where needed. Dedicated BD/BW counter account label now explicitly says Counter Account for both cash/manual and verified source; PPR Receiving Account labels the debit side, Plot Purchase Seller/Payable labels the credit side. These families already write both posting sides, so the fix does not invent an unrelated third account. The new `DailyVoucherDrActionAndFilterContractTest` guards the route and HTML contract and is part of focused finance CI.

**Owner terminal commands:** `php artisan optimize:clear`; `php artisan view:cache`; `php artisan test --filter=DailyVoucherDrActionAndFilterContractTest`; `php artisan test --filter=DailyVoucherRevisionCounterAccountContractTest`; `php artisan test --filter=StandardVoucherBalancedLifecycleContractTest`; `php artisan finance:audit-posting-balances`. No execution result from the owner has yet been received for this batch.

**Browser test focus:** Cash DR-2790 (Approved): Edit and Delete actions should now appear for a user with `finance dr edit/delete` permissions; open Edit, see preselected Debit Account and submit same-ID revision; verify approval and both GL rows. Delete an independent disposable approved cash DR only after confirming both ledger sides retire; do **not** delete DR-2790 just to test. For BD/BW, PPR, PP, daily voucher CR/DR and JV/SV/CV listings, reload with hard refresh and confirm no top filters ever appear during skeleton loading; Quick Search/column filter and New Voucher remain usable. Do not repeat previously accepted source narration and posting tests.

## Milestone 03 — Daily Directory actual Edit iframe counter-account repair (2026-11-23)

**Owner browser failure:** CR-846 Edit at `/admin/finance/daily-vouchers?type=CR` omitted the marked **Credit Account** field although Create displayed it. This was **not** the `cash_voucher.blade.php` Edit modal previously changed. Daily Directory renders `finance.daily-vouchers.revise.edit` in an iframe, served by `RejectedCashVoucherController::edit` and `rejected_cash_revision.blade.php`. The prior fix did not reach this route.

**Fixed in active route (pending new browser/terminal gate):** The revision controller loads both original project-owned ledger legs in canonical ID order and the existing counter mapping; the real Edit Blade shows a full-width matching Credit Account for CR/CCR, Debit Account for CP/DR/CDR with the original account preselected and changeable, submits `counter_account_id`, and blocks identical party/opposite mappings before POST. The controller validates the new field; `SameSourceCashVoucherEdit` already validates active selected-project mapping, captures old/new counter account in approval snapshots and updates both ledgers atomically on approval or direct edit. BOR/BOP revisions show the immutable verified bank counter identity read-only (by exact source allocation and active project mapping), avoiding an unauthorized bank allocation change. Single-leg historical vouchers show an explicit legacy read-only note rather than inventing a second row. No migration, no changes to already approved postings. Added `DailyVoucherRevisionCounterAccountContractTest` to the focused M03 CI.

**Required local gate:** `php artisan view:cache`, `php artisan test --filter=DailyVoucherRevisionCounterAccountContractTest`, `php artisan test --filter=StandardVoucherBalancedLifecycleContractTest`, `php artisan test --filter=CashCreditDebitEditContractTest`, `php artisan finance:audit-posting-balances`.

**Required browser gate:** After pulling and cache clear, open CR-846 > Edit from the **daily voucher listing**, check Credit Account is visible and preselected. Repeat DR, CP, CCR and CDR; change a two-leg Cash voucher counter mapping and submit with reason; approval ON should retain old balance until approval, then update both GL lines. Reopen Edit to confirm selected account persists; reject main/counter collision; BOR/BOP rejected revision shows read-only bank counter. Historic single-leg Edit shows explicit legacy note. Earlier M03 create/approval browser tests are owner-confirmed PASS and must not be repeated.

## Milestone 03 — Counter-account Edit modal parity (2026-11-23)

**Owner acceptance update:** Milestone 03 browser + local terminal groups 1/2/3 (standard two-leg vouchers, PPR, verified bank adjustments) explicitly confirmed PASS; separate CCR-7 / CDR-5 approval browser tests also confirmed PASS. Do not relist those accepted tests as pending.

**New focused fix (committed, pending owner local/browser verification):** Edit previously hid the opposite account when the API returned NULL (notably CP) and the jQuery AJAX save payload omitted `counter_account_id` even when the selector appeared. `LedgerController::show` now returns the canonical party-side ledger, the exact second standard CR/DR/CP/CCR/CDR posting account, and for BOR/BOP the matched `project_bank_accounts` bank identity. The Edit modal now auto-selects the existing standard opposite account, allows switching it from the same selected-project account list, sends it in the AJAX edit request, validates selection, and resets between vouchers. The backend still revalidates different active same-project mappings and applies both ledger-leg updates within the existing same-source edit/approval snapshot flow. The bank-only BOR/BOP counterpart is displayed with its actual Bank name/account title **read-only**, since bank custody allocation is immutable in normal Edit; any bank-side mapping change still requires the audited bank-source correction workflow. Old single-leg historic vouchers have no fabricated counter-account. No migration.

**Verification gate:** `php artisan view:cache`, `php artisan test --filter=StandardVoucherBalancedLifecycleContractTest`, `php artisan test --filter=CashCreditDebitEditContractTest`, and posting-balance audit. Browser: open existing two-legged CR/DR/CP/CCR/CDR Edit, see the correct selected opposite Debit/Credit Account; change to another active selected-project mapping, submit with reason, confirm approved/draft workflow and both GL mappings; reopen to ensure persistence; switch between vouchers to ensure no stale account. BOR/BOP Edit must show the immutable actual linked Bank Account rather than an unrelated party mapping. An approved BOR/BOP is **not** editable via this route; bank-source correction/reissue is separate. No need to repeat already accepted create/post/approval tests.

## Milestone 03 — Legacy CP standard voucher counter-account parity (2026-11-23)

**Implementation (code committed; local acceptance pending):** The standard `CP` Cash Payment form is also included in generic opposite-account selection, project validation and two-leg Dr/Cr writer, except where a request updates an already-existing Online/Cheque pending clearance (which must not create another ledger leg). `BalancedStandardVoucherIntegrity`, same-ID cash Edit, approval validation and cash register one-row deduplication support new CP, preserving historical one-leg CP entries and DR bank-clearance handling. `BalancedStandardVoucherIntegrityTest` and `StandardVoucherBalancedLifecycleContractTest` include CP. No schema change.

## Milestone 03 — Original single-booking PPR balanced Cash/Bank counter-account (2026-11-23)

**New original PPR code (pending owner local/browser verification):** The existing Plot Receive Payment > New Voucher modal now exposes a project Cash/Bank opposite account. Payment Type Cash filters Cash account options; cleared Cheque/confirmed Online filters selected-project active bank mappings. `BookingController::deposit` validates the account in its project-locked transaction, prohibits same-account collision with the booking receivable, and creates a second PPR ledger row (`amount_out`) balancing the existing receivable (`amount_in`). `booking_vouchers.ledger_id` retains its original canonical receivable row ID. The duplicate-slip replay checks permit exactly the two verified, same-source opposite rows instead of mistaking them for an extra payment. Existing historical one-leg receipts are not backfilled. No new table/migration.

**PPR lifecycle:** New `BalancedOriginalPprIntegrity` checks exact two-leg polarity, distinct account, original customer-ledger source and canonical receivable ledger ID. `DailyVoucherApprovalWorkflow` checks and activates the two linked legs at approval; creation deactivates both for pending approval. `BookingReceiptCashEditor` now Edit/Delete updates or soft-retires both Cash PPR ledgers with the same original receipt, booking voucher and source IDs. `BankVoucherAllocationRelease`, `BankManagementCascadePlanner`, and `BankSourceReissueCorrection` validate/release both verified Online/Cheque PPR ledger legs under audited source correction; one-leg historic receipts remain supported. PPR listing de-duplicates the counter-leg while general ledger retains both. Original PPR same-ID *verified bank amount* correction remains on audited bank-source reissue path, not unsecured Cash Edit.

**Added test coverage:** `BalancedOriginalPprIntegrityTest`, expanded `BookingReceiptCashEditorTest` for two-leg original Cash Edit/Delete and `BankVoucherAllocationReleaseTest` for two-leg PPR release, updated `PprVerifiedSourceContractTest`. The dedicated finance CI job lints this PPR guard and editor and runs these focused cases. **No verified new GitHub CI or Laragon result yet.** Required new browser checks: Original PPR Cash, Cheque and Online create with distinct selected project account, exact two GL lines/one PPR slip, approval OFF/ON, Cash Edit/Delete and bank correction/reissue; original booking voucher and historical one-leg records still function.

## Milestone 03 — M04 compatibility for balanced bank corrections and typed source reissue (2026-11-23)

**Additional implementation:** Existing `BankLinkedCorrection` same-ID amount adjustment previously assumed one `ledgers` row and updated that row alone. It now locks/verifies the exact balanced BOR/BOP or CR/DR legs and applies the updated amount to **every ledger leg in its original polarity** within the same transaction, preserving IDs and recording both ledger identities in the fingerprint/snapshot. Legacy single-leg source correction remains available. `BankSourceReissueCorrection` now accepts two-leg BOR/BOP and CR/DR, checks all posting states before source replacement, and uses typed BD/BW and PPR_TYPED/PPR_TYPED_DRAFT identities for preview instead of casting their IDs to `customer_ledger` IDs. Existing `BankManagementSourceMutation` continues to perform typed custody releases within its transaction; all preview changes are subject to fingerprint checks. Missing typed source schema fails closed, and historical allocations are not rewritten. No new table/migration.

**Added coverage:** Two in-memory `BankLinkedCorrectionTest` scenarios verify that direct amount correction preserves 2 balanced CR/DR rows; `BankLinkedCorrectionContractTest` now asserts multi-leg updates. Focused finance CI lints both source-correction services and runs corresponding tests, but a definitive new-run PASS has not been obtained. Old owner browser acceptance of Milestone 04 predates these new balanced-ledger changes and is **not** treated as validation of this extension.

## Milestone 03 — Universal standard voucher two-leg accounting implementation (2026-11-23)

**Current branch code batch (unverified locally):** CR/DR and CCR/CDR create forms now require one project-specific opposite ledger account, while the original Account Type / Accounts / Child Account still selects the party leg. BOR/BOP retain their active-project-bank-only counterpart. New CR/DR store and CCR/CDR cash-only writer post linked debit/credit ledger rows in the existing `ledgers` table and reject a same-account/cross-project/deactivated-account choice. CCR/CDR revalidate both accounts in their posting transaction; pending CCR/CDR and CR/DR approvals hold **both** legs inactive. No new financial table or migration.

**Lifecycle:** `BalancedStandardVoucherIntegrity` validates exact CR/DR/CCR/CDR polarity, both equal amounts, two independent account mappings, and ledger series. `DailyVoucherApprovalWorkflow` checks pending two-leg sources before activation, preserving historical one-leg paths. `SameSourceCashVoucherEdit` supports same-ID **cash** CR/DR/CCR/CDR two-leg amount/account/date edits, two-leg pending before-image checks and simultaneous approval/direct updates; it also corrects DR amount_out proposal and DR source eligibility. New verified Online/Cheque CR/DR editing remains controlled via existing bank-source correction/release, not direct cash editing. M04 `BankVoucherAllocationRelease` and `BankManagementCascadePlanner` now validate and release both two-leg verified CR/DR entries; historic one-leg verified sources retain their original release contract. CR/DR/CCR/CDR cash and debit registers now show one canonical voucher row while the GL retains both ledger legs. The edit modal loads editable opposite account only for supported two-leg standard sources.

**Tests added:** `BalancedStandardVoucherIntegrityTest`, `StandardVoucherBalancedLifecycleContractTest`, three new source-release scenarios in `BankVoucherAllocationReleaseTest`, updated `CashCreditDebitEditContractTest`. Dedicated M03 CI adds PHP syntax checks and new focused tests. CI execution outcome has not yet been independently verified, and the user's Windows/Laragon tests and browser sign-off remain necessary. Do not mark Milestone 03 closed from code commits alone.

**Local gate (non-destructive):**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=BalancedStandardVoucherIntegrityTest
php artisan test --filter=StandardVoucherBalancedLifecycleContractTest
php artisan test --filter=CashCreditDebitEditContractTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan test --filter=DailyVoucherApprovalWorkflowTest
php artisan test --filter=BalancedBankVoucherAccountContractTest
php artisan finance:audit-posting-balances
```

**Required browser sign-off:** create CR/DR/CCR/CDR each with distinct opposite project account, inspect exact two GL legs and one listing/print face amount; confirm two legs held inactive pending approval and activated together, rejected approval inactive; edit cash amount/party/counter account with same voucher ID and approval ON/OFF, then void with history; for verified CR/DR source release/replace verify both ledger legs retired and allocation immutable; retry cross-project/collision/same-source overspend; regress BOR/BOP, BD/BW and original/typed PPR. Existing pre-batch **9 tests / 47 assertions** and six-project audit are owner-verified PASS; this new batch still needs re-run locally.

## Milestone 03 — Bank selector double-border screenshot repair (2026-11-23)

**Owner local gate confirmed PASS:** BalancedBankVoucherAccountContractTest 3 tests / 27 assertions, BalancedBankVoucherIntegrityTest 6 tests / 20 assertions, `view:cache` PASS, active posting balance audit six projects clean (before this UI-only adjustment).

**Issue from screenshot:** BOR/BOP bank picker showed a nested/double field. Unlike every other TomSelect field, its original `<select>` had a `form-control` class, and the newly added extra boxed row amplified the doubled visual control. **Fix:** removed the bootstrap input class from bank source select, removed the separate boxed row, placed the bank control inside the normal three-column voucher form grid, and styled TomSelect wrapper as borderless while giving the actual `.ts-control` a single border. Added contract regression for one input and grid integration. No new database tables or migrations.

**Unverified:** Browser screenshot after this change, account availability, CR/DR generic counterpart posting/edit lifecycle, and all remaining 9 acceptance scenarios. Never count frontend source assertions as browser acceptance.

## Milestone 03 — Pending approval all-leg fix and bank selector layout (2026-11-23)

**Implemented (not yet verified locally/browser):** The standard cash voucher writer previously deactivated only the first ledger row for a newly submitted BOR/BOP approval while leaving its linked bank-side second row active. `LedgerController::store` now deactivates **all project-scoped ledgers for the exact customer_ledger source** atomically within the existing approval transaction; the existing `DailyVoucherApprovalWorkflow::setPostingState` already activates/deactivates both project-scoped rows on decision. `BalancedBankVoucherAccountContractTest` now guards against regression to an `id = first leg` update. The BOR/BOP bank account selector also has a compact contained layout and consistent TomSelect width/spacing in the embedded cash voucher form. No database migration or new table was added in this batch.

**Verification still required:** `php artisan test --filter=BalancedBankVoucherAccountContractTest`, `php artisan test --filter=BalancedBankVoucherIntegrityTest`, `php artisan view:cache`, and BOR/BOP Approval ON browser check that **neither** leg affects active balances before approval. Run `php artisan finance:audit-posting-balances` on the user's configured development database. Fresh CI was not established from this edit. Existing CR/DR generic two-leg account UX and its Edit/Delete/release lifecycle remain pending; do not claim Milestone 03 complete.

## Milestone 03 — Typed multi-plot PPR verified bank custody completed in code (2026-11-23)

**Latest verified isolated CI:** Dedicated Milestone 03 finance source integration job PASS **112 tests / 789 assertions**, including inherited original PlotEntryWriter regressions, new typed multi-plot PPR verified Cheque direct post, immutable claim, double-spend denial, release/soft-void, approval-pending reservation, approved draft transfer, rejected draft capacity release, bank-profile validation, BOR/BOP balanced posting, BD/BW typed custody, original PPR, and M04 correction/reissue. Shared jQuery Online/Cheque narration tests PASS. Note: these are isolated SQLite tests; user Laragon migration and browser tests remain pending. The unrelated historical Database V2 CI jobs continue to fail their existing legacy schema/seeder checks and do not establish an M03 finance failure.

**Typed PPR implementation:** `PlotEntryWriter::receive` accepts `payment_type` (Cash=1, Online=2, Cheque=3) and a selected `passed_source_method`/`passed_source_id`. Verified PPR requires an ACTIVE selected-project bank `cash_account_id` profile for its Debit Bank leg, while chosen booking receivables are credited in one balanced typed batch. The management intake's `account_mapping_id` is not falsely treated as the bank-side account. Cheque/Online clearance and unallocated remaining balance are reverified inside posting transaction before immutable `PPR_TYPED` allocation. `PlotVoucherApprovalDraftWriter` reserves `PPR_TYPED_DRAFT` at submission (no posting), and `DailyVoucherApprovalWorkflow` approves by atomically transferring the reserved amount into a posted source; rejected approvals release capacity with immutable history. `TypedPlotReceiptBankCustody` implements M04 source-correction release, exact original receipt source/posting/legacy-ledger/booking allocation integrity checks, active source soft-void and ledger leg deactivation. `BankManagementSourceMutation` and `BankManagementCascadePlanner` now route both posted and pending typed PPR allocations without mistakenly referring to `customer_ledger`. Ordinary typed Edit/Delete/Reverse is blocked for source-linked PPR until audited source release; Cash PPR retains prior editing. **No new table or migration is needed for typed PPR; existing shared allocation table supports types `PPR_TYPED` and `PPR_TYPED_DRAFT`.**

**UI:** The separate typed multi-plot PPR modal now offers Cash, Online or Cheque, shares the same confirmed/cleared incoming-source radio picker and single SOURCE narration formatter with the original single-booking PPR modal. It enforces selected source and available balance over the aggregate of all selected booking allocations. The main New Voucher original single-booking PPR entry remains unchanged and already supports verified sources.

**Local gate (M03 now has a migration for balanced BOR/BOP only):**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan migrate
php artisan view:cache
php artisan test --filter=BalancedBankVoucherIntegrityTest
php artisan test --filter=BalancedBankVoucherAccountContractTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan test --filter=TypedBankVoucherCustodyTest
php artisan test --filter=TypedPlotReceiptBankCustodyTest
php artisan test --filter=TypedPlotReceiptBankCustodyContractTest
php artisan test --filter=PprVerifiedSourceContractTest
php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest
php artisan finance:audit-posting-balances
node tests/JavaScript/verified-source-narration.test.cjs
```

**Browser sign-off still REQUIRED:** test BOR/BOP active bank selector, exact Dr/Cr balanced ledger, one listing row, print face amount, approval ON/OFF, cancellation/reissue; BD/BW cleared Cheque and confirmed Online with release; original PPR Online/Cheque with approval and booking voucher; typed PPR multi-plot modal with at least two active bookings and partial verified source allocation, approval ON/OFF, source mutation and no duplicate allocation. Also confirm manual Cash paths unchanged and six-project audit clean.

**Further account UX requested historically but not yet accepted:** CR/DR one generic opposite-side debit/credit account selector (not only bank vouchers) needs its own full dual-leg cash/posting edit lifecycle and regression. Do not conflate the completed **BOR/BOP bank-only opposite-side** with CR/DR generic counterpart redesign, and do not close Milestone 03 before owner's browser sign-off and decision on this remaining UX item.

## Milestone 03 — BOR/BOP bank-only opposite-side and balanced ledger integration (2026-11-23)

**Owner accepted:** Original verified source narration **7/7 manual browser checks PASS** (CR/DR/BOR/BOP, BD/BW, PPR). Prior manual BD/BW checks also PASS.

**Implemented in this batch:** The BOR and BOP voucher creation forms now expose ONE **project-active bank-only** dropdown. BOR label: **Debit Account (Bank)**; BOP label: **Credit Account (Bank)**. Bottom Account Type/Accounts/Child Account remains the opposite (party) account. Both controls are required. The bank picker is populated from `project_bank_accounts` scoped to the selected project and active records, and the POST writer revalidates project and mapped `project_head_subheads` under a project DB lock, prohibiting identical bank/party mappings. `LedgerController` atomically creates two `ledgers` rows for new bank vouchers: **BOR: Dr Bank / Cr Party**, **BOP: Dr Party / Cr Bank**. The original `customer_ledger` maintains the voucher identity and face amount. The immutable allocation now records the selected `bank_account_id` via a **single nullable column** added to the existing `finance_bank_voucher_allocations` table. There is no new table. Migration file: `2026_11_23_170000_add_bank_account_identity_to_finance_voucher_allocations.php`. Historical allocations with NULL bank ID retain existing single-leg shape and are NOT rewritten.

**Approval and release:** `BalancedBankVoucherIntegrity` validates project bank identity, exact opposite account, both posting directions and amounts, and leg cardinality. The approval handler now activates the two pending legs only after integrity verification; M04 bank-source replace/withdraw releases both legs atomically without deleting financial history. The dependency planner accepts both balanced new vouchers and historical single-leg records. Existing bank source partial balance, permissions, and custody controls are unchanged. Cash voucher list filters out the additional bank-side row to avoid duplicate BOR/BOP display while the actual GL keeps both legs; listed/printed amounts come from the canonical `customer_ledger` face value, not the opposite-polarity party leg.

**Tests:** Extended isolated `BankVoucherAllocationReleaseTest` with posted balanced BOR release and BOP wrong-direction rejection. Added `BalancedBankVoucherAccountContractTest` to guard form, migration, writer, release and non-duplicated list. Dedicated M03 GitHub CI job now lints `BalancedBankVoucherIntegrity`, `LedgerController` and migration, and runs the new contracts plus previous BD/BW/PPR financial regressions. CI PASS is not the same as actual Laragon browser sign-off; owner local testing remains required.

**Run this migration and tests locally FIRST:**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan migrate
php artisan view:cache
php artisan test --filter=BalancedBankVoucherAccountContractTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest
php artisan test --filter=TypedBankVoucherCustodyTest
php artisan test --filter=PprVerifiedSourceContractTest
php artisan finance:audit-posting-balances
```

**BOR/BOP browser tests REQUIRED (disposable records only):**
1. BOR > New: only active banks for selected project appear in Debit Account (Bank). Choose separate Party Account, cleared incoming Cheque/Online source; save with approvals OFF. Confirm amount, BOTH real GL rows (Dr bank, Cr party), one voucher in listing, correct print amount, immutable source allocation, no duplicate.
2. BOP > New: only active banks in Credit Account (Bank), cleared outgoing source; save. Confirm Dr party, Cr bank, one voucher, print amount.
3. Approval ON: both bank and party legs inactive while pending; approving activates both and does not create extra legs; rejecting keeps both inactive. Cross-project bank ID and bank ID mapping equal to party account must reject.
4. Bank management source replacement/withdrawal: old posted BOR/BOP source deactivates BOTH legs, one immutable release record, original evidence remains in historical view; replacement creates a new source ID and cannot falsely spend old balance.
5. Previously posted single-leg BOR/BOP records still display in reports, print and release through their historical legacy path; they must NOT be rewritten. Finance audit should remain clean.

**Remaining Milestone 03:** The separate typed multi-plot `postPlotReceipt()` API intentionally remains fail-closed for verified Cheque/Online custody because it has a different source/draft/release identity than the **original single-booking New PPR voucher** users select in the browser. Do not count this separate API as source-linked or claim all internal variants complete. User's 7 visible voucher forms now have the implemented source picker, but new BOR/BOP approval/browser acceptance is still pending.

## Milestone 03 — BOR/BOP narration parity for BD/BW and original PPR (2026-11-23)

**User browser observation:** BOR/BOP verified Online descriptions automatically append `SOURCE Online | Transaction ID / Reference | Sending Bank | Receiving Bank | Clearing Date`. The same source evidence was not appended to BD/BW or original PPR; their picker previously overwrote manual narration with management `purpose`.

**Implemented:** Shared jQuery utility `public/js/finance/verified-source-narration.js` now builds BOR/BOP-identical source lines for all three voucher entry screens: existing CR/DR/BOR/BOP cash voucher iframe; BD/BW bank transfer workspace; original single-booking PPR. Cheque fields: `SOURCE Cheque | Title | Cheque Number ****last4 | Bank Name | Clearing Date`. Verified source is selected from the existing project/eligibility-filtered picker; user narration is retained *above* the appended evidence. Changing sources replaces prior source evidence rather than duplicating it; returning to manual/Cash removes the generated evidence without wiping the operator's text. The selected management `purpose` no longer overwrites the narration. If the final description exceeds the form's `maxlength`, application blocks source Apply with an error and leaves the manual text unchanged. No schema change and no posting writer change in this batch.

**Regression:** `tests/JavaScript/verified-source-narration.test.cjs` exercises exact Online/Cheque output, five voucher families, source re-apply and replacement, Cash clearing, old multiline evidence cleanup, max-length refusal and presence of utility on all three forms. The dedicated Milestone 03 GitHub Actions job runs this Node test before existing isolated SQLite 50-test finance suite.

**Browser check required:** In BOR/BOP, BD/BW and Plot Receive Payment > New Voucher, choose Online with a confirmed source and verify the same SOURCE block in Details/Description, with manually typed narration on the first line. Reopen and choose a different verified source or switch Payment Type to Cheque: previous SOURCE line is replaced (no duplicate), with masked cheque number. Switch to Cash/Manual where supported: source line disappears, manual text remains. Save sample BD/BW/PPR and verify voucher history/report narration retains the same text. Use disposable test records only. PHP safety tests and all-project audit remain necessary for M03's wider unaccepted integration. **This narration batch does not close outstanding BOR/BOP accounting counter-leg work.**

## Milestone 03 — Verified BD/BW typed transfer integration (2026-11-23)

**Implemented and committed (NOT YET LOCAL-TEST VERIFIED):** Bank Deposit (BD) and Bank Withdrawal (BW) now have a Payment Type selector (Cash/Manual, Online, Cheque) with a modern single-radio verified management source modal from the shared passed-source endpoint. The selected incoming/outgoing instrument's mapped project account is used as the **counter-account**; the active `project_bank_accounts` dropdown remains the bank-side debit/credit leg. Original Cash/Manual BD/BW remains available and unchanged. Voucher date, amount, reference, counter-account and narration use the existing Ajax modal. Cash/Manual exposes cash-only counter-accounts; verified Cheque/Online uses the source's current `project_head_subheads.account_mapping_id` and strictly rejects unverified/cross-project/mismatched amounts.

**Backend custody/approvals:** `BankTransferWriter` accepts optional verified bank intake method/ID (separate from the old `linked_customer_ledger_id` Cheque flow), checks active source and amount in a project-locked posting transaction and creates `finance_bank_voucher_allocations` with type BD/BW and `voucher_id = finance_posting_batches.id` only AFTER balanced general ledger linkage is established. `BankTransferApprovalDraftWriter` reserves `BD_DRAFT`/`BW_DRAFT` allocation before returning a pending approval and persists the source identity in **two new nullable columns** on `finance_bank_transfer_approval_drafts`. `DailyVoucherApprovalWorkflow` transfers the immutable draft reservation to the posted batch via an append-only `approval_transfer` release; rejection cancels the draft claim. `TypedBankVoucherCustody` implements strict corrected-source withdrawal/cancellation with project, batch, typed ledgers, amounts and original allocation checks; M04 `BankManagementSourceMutation` dispatches typed source releases rather than erroneously using customer-ledger IDs. `BankManagementCascadePlanner` now includes BD/BW typed/draft dependencies. Linked typed vouchers cannot use ordinary Edit/Delete/Reverse; released source leaves history and typed ledger legs inactive. No new table. **Forward migration**: `2026_11_23_150000_add_verified_bank_source_to_transfer_approval_drafts.php`.

**Existing current PPR:** Original single-booking PPR uses M04 `customer_ledger` allocation and is integrated as described below. The legacy typed **multi-plot** `PlotEntryWriter` API is separate and still rejects an injected bank source; the "New voucher" button intentionally uses the original single-booking PPR modal.

**Focused code tests added:** `TypedBankVoucherCustodyTest` (isolated SQLite writer/custody plus inherited existing BD/BW tests); `SevenVoucherTypedBankCustodyContractTest`; updated `SevenFamilyBankSourcePickerContractTest`; PPR `BankVoucherAllocationReleaseTest` expanded; `PprVerifiedSourceContractTest`. **These have NOT run on user's Laragon yet.**

**Run BEFORE browser testing**:
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan migrate
php artisan view:cache
php -l app/Services/Finance/TypedBankVoucherCustody.php
php -l app/Services/Finance/BankTransferWriter.php
php -l app/Services/Finance/BankTransferApprovalDraftWriter.php
php -l app/Services/Finance/DailyVoucherApprovalWorkflow.php
php -l app/Http/Controllers/Finance/VoucherController.php
php -l app/Http/Controllers/BookingController.php
php artisan test --filter=SevenVoucherTypedBankCustodyContractTest
php artisan test --filter=TypedBankVoucherCustodyTest
php artisan test --filter=PprVerifiedSourceContractTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan test --filter=SevenFamilyBankSourcePickerContractTest
php artisan test --filter=BankTransferWriterTest
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest
php artisan finance:audit-posting-balances
```

**Browser acceptance REQUIRED after local tests green**:
- Manual BD and BW: create/edit/delete retains accepted cash behavior.
- BD Cheque and BD Online: choose only cleared/confirmed **incoming** instrument in selected project; verify source mapping, source reference, amount <= remaining; Save with approval OFF posts exact balanced bank debit/counter credit and source allocation; repeat source over budget rejected.
- BW Cheque and BW Online: only cleared/confirmed **outgoing**; bank credit/counter debit, source allocation, no double spend.
- Approval ON for BD/BW: Save creates pending draft and source reservation with NO posting; approval creates original batch and audited transfer release; rejection releases pending capacity; correction/reissue while pending cancels draft and source claim without posting.
- M04 source correction/reissue for posted BD/BW: retires exact source and all typed/legacy posting lines, appends single allocation release; history retained; no duplicate ledger effect; wrong project source rejected.
- Original PPR Cheque/Online: source modal, source claim/booking voucher, approval and bank management reissue, as detailed below; original Cash PPR remains functional.
- CR/DR/BOR/BOP: existing verified picker remains working; exercise cash/cheque/online accounting as appropriate.
- Verify all-project `finance:audit-posting-balances` remains clean and report histories are correct.

**Still open for full Milestone 03 closure:** BOR/BOP's requested **single real bank-only opposite posting account** is NOT implemented; original single-leg accounting writer must be reconciled to balanced posting first. Untouched typed multi-plot API still requires a distinct Cheque/Online allocation lifecycle if used as a new production form. All the current new typed/source features must pass local and browser acceptance before closed. Prior Milestone 03 batches beyond user's already accepted 1/2/3 still need independent confirmation. Do not mislabel M03 as 100% complete.

## Milestone 03 — Original PPR verified-source writer + release and modal (2026-11-23)

**Implemented new functional PPR scope:** The current **New voucher** entry on Plot Receive Payment opens the original single-booking PPR modal (`prOriginalCreateModal`), which writes a `customer_ledger` PPR plus its canonical `booking_vouchers` and one receivable `ledgers` entry. This writer can use the already-installed M04 bank voucher allocation table. Online/Cheque Payment Type now opens the verified management source picker with project-scoped cleared incoming Cheque / Confirmed incoming Online only; chosen reference, verified passing date and available amount fill the form. Existing manual Cash selection remains unchanged. The separately available legacy *typed multi-plot* `postPlotReceipt()` still does not accept bank source fields and is NOT silently routed through the single-booking writer.

**Backend:** `BookingController::deposit` PPR branch now requires `passed_source_method` + `passed_source_id` for payment types 2/3, rejects bank claims on Cash, validates management evidence and remaining amount using `PassedBankVoucherClaim::verify()` while holding project transaction lock, and writes immutable `PPR` allocation in the SAME transaction after source/ledger creation. A replay with a different instrument identity is rejected. Source reference is kept in `t_number` and immutable claim; the original PPR slip/reference remains a unique voucher identity and cannot be conflated with a bank instrument reused for partial allocation. Bank selection is required when noncash. Existing PPR approval submission remains unchanged; allocation is reserved even while PPR waits for approval.

**Source correction and release:** M04 `BankVoucherLegIntegrity` now validates original PPR's *customer_ledger.amount_out* against the single *ledgers.amount_in* leg, and `BankManagementCascadePlanner` knows PPR. `BankVoucherAllocationRelease` authorizes verified `PPR` identity, deactivates source and ledger through existing posted/pending/rejected release transaction and retires the matching `booking_vouchers` record without hard deletion. Existing CR/DR/BOR/BOP behavior remains. Tests extended: `BankVoucherAllocationReleaseTest` has posted PPR reissue/once-only release and pending PPR rejection cases; `PprVerifiedSourceContractTest` guards frontend + source linking contracts. No migration or new table in this PPR batch.

**LOCAL TESTS REQUIRED before browser sign-off:**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Http/Controllers/BookingController.php
php -l app/Services/Finance/BankVoucherAllocationRelease.php
php -l app/Services/Finance/BankVoucherLegIntegrity.php
php artisan test --filter=PprVerifiedSourceContractTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest
php artisan test --filter=VerifiedBankInstrumentContractTest
php artisan finance:audit-posting-balances
```

**PPR browser acceptance REQUIRED after green local tests (disposable only):**
1. Plot Receive Payment > New voucher: choose Cash. No bank-source picker; previous PPR Cash save/approval stays functional.
2. Choose Online: modal opens with only confirmed receipt sources in selected project. Select one and Apply. Transaction reference/passing date/bank and remaining amount appear; choose a valid Bank if the source's bank name does not match an option. Save a partial amount, confirm PPR voucher and bank-source remaining balance.
3. Choose Cheque: only cleared incoming cheques, choose one; create PPR, confirm 1 source allocation and linked BookingVoucher/ledger (subject to approval). Reject forged source ID, over-allocation, failed clearance, cross-project ID and duplicate replay without mutation.
4. Approval ON: pending PPR remains inactive until approval. Approved shows posted; rejection keeps original history.
5. M04 source correction/replace: posted PPR becomes inactive, its booking voucher becomes inactive, release record exists once, original financial evidence stays; repeat release rejected.
6. Existing CR/DR/BOR/BOP selection popup remains operational.

**Still NOT COMPLETE in Milestone 03:** BD/BW typed verified-source Payment Type and release/draft approval writer + frontend; typed multi-plot receipt source claim (separate from currently used original single-booking PPR); BOR/BOP genuine bank-only opposite-side posting account. Do not mark M03 complete or run unimplemented flows as browser tests.

## Milestone 03 — BD/BW browser ACCEPTED; seven-source integration active (2026-11-23)

**Owner acceptance:** BD/BW manual Create/Edit/Delete, bank/cash ledger and voucher history **6/6 browser checks PASS**; local BD/BW source-identity and writer tests **12 PASS / 83 assertions**, PlotEntryWriter **45 PASS / 298 assertions**, all-project financial audit **6/6 CLEAN**. Do NOT repeat the already accepted manual BD/BW regression unless a new change affects it.

**Seven-family verified-source work:** CR/DR/BOR/BOP production passed-source picker already works, and its direction validation now recognizes BD/BW/PPR. Added `VerifiedBankInstrument`, a non-mutating, project-row-locked backend verification foundation accepting only CR/DR/BOR/BOP/BD/BW/PPR with strict receipt/payment polarity. It checks cleared cheque / confirmed online evidence, original amount integrity, project account mapping, pending corrections, legacy full claims, and unreleased allocation remaining balance in integer cents. The method REQUIRES a surrounding posting transaction; it deliberately does NOT claim/post a voucher. Added `VerifiedBankInstrumentContractTest` and retained the earlier seven-picker contracts. These new tests must be run locally.

**Structural issue still blocking production BD/BW/PPR Online/Cheque creation:** Existing `finance_bank_voucher_allocations.voucher_id` is a **non-null FK into `customer_ledger`**. BD/BW source identities are `finance_bank_transfer_sources.posting_batch_id`; typed PPR source identities are `finance_plot_receipts.id`. Existing M04 `BankVoucherAllocationRelease`, `BankVoucherLegIntegrity`, correction planner and bank source replacement cascade are explicitly CR/DR/BOR/BOP **single-ledger-leg** only. Simply writing an allocation with a typed batch ID would link the wrong FK and may void an unrelated legacy voucher. **DO NOT DO THAT.** New typed identity metadata and a valid release/correction transaction across posted typed legs, draft approval, and PPR booking allocations are mandatory before enabling the BD/BW/PPR Payment Type/Bank Source save UX.

**Remaining acceptance work (NOT COMPLETE):** (1) Forward-compatible typed allocation storage that does not invent `customer_ledger` placeholders; (2) approve-time atomic BD/BW and PPR source claims; (3) M04 linked-source replacement to cancel typed drafts, retire typed posting batches and safely free allocations; (4) BD/BW and PPR Cheque/Online single-selection modals and narration; (5) BOR/BOP actual single counter-account with only project-active bank mappings, not a fake non-posted field; (6) seven-family browser matrix. Do not mark any of these as complete until implemented and locally accepted. No migration or new table has been added in the current verification-guard batch.

**Local focused gate:** `php artisan test --filter=VerifiedBankInstrumentContractTest`, `php artisan test --filter=SevenFamilyBankSourcePickerContractTest`, `php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest`, `php artisan finance:audit-posting-balances`. No browser test needed for the currently committed **backend guard only**; browser tests required after production voucher forms/writers are connected.

## Milestone 03 — BD/BW accounting regression accepted on local tests (2026-11-23)

Owner's latest Laragon result: **BankTransferWriterTest 11 PASS / 74 assertions**, **PlotEntryWriterTest 45 PASS / 298 assertions** (previous run), and **six-project posting audit CLEAN**. Source identity runtime bug in `BankTransferEditor` is resolved. Only newly introduced isolated `BankTransferSourceIdentityContractTest` failed before executing assertions because it extended `PHPUnit\\Framework\\TestCase` but invoked Laravel `base_path()` / `app_path()` without bootstrapping an application. Fixed this test to use `__DIR__` relative paths and literal `$sourceId` assertion; production code unchanged in this latest commit. **Local rerun of that isolated contract pending.** No browser test needed for this PHPUnit-only fix; the prior BD/BW Create/Edit/Delete browser verification remains recommended for the earlier runtime fix.

## Milestone 03 — Latest owner regression follow-up (2026-11-23)

**Owner run after prior fixture update:** `PlotEntryWriterTest` improved to **42 PASS / 3 FAIL**. Remaining failures were stale assertions summing historical inactive posting lines as if they were active. `BankTransferWriterTest` still **10 FAIL / 1 PASS** because `TypedLedgerBackfill` incorrectly reads `finance_bank_transfer_sources.id`, even though the table primary key is **`posting_batch_id`**, not `id`.

**Committed corrections:** Fix the real BD/BW source identity bug in `TypedLedgerBackfill` to use `$link->posting_batch_id` (matches schema, claims, attributions); update the three Plot lifecycle tests to sum only active `finance_posting_lines.is_active=1`, while asserting archived historical credit remains untouched. Do not disable posting ledger gates, change existing printed numbers, or physically remove archived legs. No database migration.

**Local execution still required:** `php artisan test --filter=BankTransferWriterTest`, `php artisan test --filter=PlotEntryWriterTest`, followed by the read-only six-project posting integrity audit.

**Browser tests:** No new browser testing is necessary for the corrected *test assertions*. The BD/BW `TypedLedgerBackfill` runtime change **does** require a focused disposable-data browser check after passing tests: create a normal manual BD and BW in their existing screens, verify successful posting, balanced bank/cash ledgers and correct voucher numbers, then verify each voucher history. Do not test Cheque/Online linking from BD/BW yet: the seven-family source allocation/UI integration is still pending.

## Milestone 03 — Focused PHPUnit regression repair (2026-11-23)

**Owner's latest Laragon log (initial 7-voucher batch, commit f22f826f):**
- `StandardVoucherAccountSelectionContractTest`: **2 PASS / 18 assertions**.
- `SevenFamilyBankSourcePickerContractTest`: **2 PASS / 24 assertions**.
- `BankLinkedCorrectionApprovalIntegrationTest`: **19 PASS / 138 assertions**.
- `finance:audit-posting-balances`: **6 projects CLEAN**.
- `BankTransferWriterTest`: 10 failed, 1 passed because older test setup creates a minimal `ledgers` table missing real balance and attribution columns. Production `PostedVoucherLedgerGate` correctly refuses the unlinked typed lines.
- `PlotEntryWriterTest`: 30 failed, 13 passed: older fixture numbers `PR-TEST-1`, `PP-TEST-1` etc violate the canonical `PPR-N` / `PP-N` writer allocator. In addition the legacy test fixtures lacked original booking receipt numbering fields and an authentic typed-ledger attribution structure.

**Corrections committed:** Add realistic `ledgers` amount/type/status columns and `ledger_source_attributions` to isolated in-memory BankTransfer/Plot fixtures, model `booking_vouchers` project-series numbering columns, use canonical PPR-1/PP-1 for initial creates while preserving intentional edit/correction values, and assert every newly posted typed leg is linked to an authentic general ledger row. **No relaxation** of `PostedVoucherLedgerGate`, no writer number looseness, no database migration and no existing production data modification. **UPDATED TESTS MUST STILL BE RUN LOCALLY**; do not declare green until owner sends results.

```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=BankTransferWriterTest
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=StandardVoucherAccountSelectionContractTest
php artisan test --filter=SevenFamilyBankSourcePickerContractTest
php artisan finance:audit-posting-balances
```

**Browser acceptance for THIS fixture-only repair:** None required if both previously failing test suites pass, because only `tests/Feature/Finance/*` changed in this repair batch. The earlier 7-family rollout DOES require browser tests after actual BD/BW/PPR verified-link writer/UI implementation; its scope is explicitly not yet complete. On the earlier form cleanup, open CR/DR/BOR/BOP and confirm unused top Debit/Credit panel no longer appears, bottom Accounts/Child Account and source popup remain operational; no save is necessary just to check the UI.

## Milestone 03 — Seven-voucher verified bank source + account UI consolidation (2026-11-23)

**Owner approved expanded scope:** Same approved/Cleared Cheque or Confirmed Online management source picker must support all **seven** voucher families: **CR, DR, BOR, BOP, BD, BW, PPR**. Existing voucher-specific lifecycle/approval/ledger/reporting must not be bypassed. Owner reports previous M03 batches 1, 2 and 3 functional browser tests PASS; this *new* seven-family expansion has **NOT** been accepted yet.

**Source direction:**
| Series | Required verified source | Accounting intent |
|---|---|---|
| CR, BOR, BD, PPR | Cleared incoming Cheque or Confirmed receipt Online | Receipt |
| DR, BOP, BW | Cleared outgoing Cheque or Confirmed payment Online | Payment |

**Code inspection finding:** The current production (standard) cash voucher endpoints use selected-project `acct_type`, `accounts_id`, `subaccounts_id` to create canonical `customer_ledger` + `ledgers` rows. The extra `posting_lines[0/1]` Debit and Credit selectors in `typed_posting_fields.blade.php` belong to a disabled alternate balanced writer; `FinanceWorkflowAccess::selected()` returns false. Displaying them together was confusing and their submitted values did not select the normal posting account. **Implemented now:** suppress this obsolete top double-dropdown in the normal ERP mode; preserve the actual bottom account fields and current posting. Regression contract added. This is an interim UI correction, **NOT** a claim that the desired new single opposite-side posting control has been implemented.

**Target one-counter-account business rule:** Credit voucher bottom Accounts/Child Account denotes its credit-side account; show only relevant Debit counter-account. Debit voucher bottom side denotes debit; show only Credit counter-account. For BOR/BOP the counter-account dropdown must be restricted to *selected project's active `project_bank_accounts`* joined with valid `account_mapping_id` rather than arbitrary `project_head_subheads`; ownership and accounting polarity must be checked server-side. Never merely hide an input whose value is required by the active writer. Posting writers must create valid balanced financial evidence; avoid silently creating a phantom counter-leg.

**Specific BD/BW finding:** `bank_transfer_workspace.blade.php` and `VoucherController::postTransferVoucher()` currently run a **cash ↔ bank transfer** through `BankTransferWriter`, where `bank_account_id` is mapped through `project_bank_accounts` and `cash_account_id` is a CASH counter-leg. Optional `cheque_source_id` refers to a different **customer_ledger** cheque custody, not a Cleared `finance_cheque_intakes` or Confirmed `finance_online_intakes` management source. Treating that old field as the requested source picker would cause false linkage. New Payment Type (Online/Cheque) and M04-compatible picker must be integrated into typed transfer writer, approval draft, correct posting claim and release process, plus real bank-specific counterpart treatment.

**Specific PPR finding:** `plot_receipt_workspace.blade.php` currently has no Payment Type and uses the multi-plot `PlotEntryWriter::receive()` to post a receipt against booking/receivable allocations plus its chosen `cash_account_id`. It is NOT the same storage/ledger shape as CR/BOR. Online/Cheque requires adding source identity, matching customer/mapping, atomic allocation after approval (not at pending-draft time), soft-void/reversal custody on edit/delete and source replacement, and source narration; existing cash PPR must remain valid.

**Implemented in this initial expansion batch:** shared read-only verified-source picker now accepts optional `voucher_series` for all seven listed types and rejects a series/direction mismatch (backward compatible to existing callers). CR/DR/BOR/BOP picker sends the real display series. BD/BW/PPR controllers explicitly reject injected `passed_source_id`, `passed_source_method`, or `payment_type` until their typed voucher writer is integrated: **never save a voucher claiming to be bank-linked without a real immutable claim**. No change to prior M04 verified source correction/withdraw and no extra database table or migration.

**Still REQUIRED to complete this expanded scope:** (1) typed BD/BW and PPR source claim/approval/posted voucher link and audited release extension compatible with existing M04 replace-withdraw behavior; (2) BD/BW and PPR Payment Type + single-record Cheque/Online selection modal and one-line details; (3) restrict BOR/BOP single counter-account to active project bank accounts and add true balanced posting; (4) lifecycle permission, idempotency, remaining-balance, multi-voucher allocation, bank source replacement, pending approval and cross-project tests; (5) browser sign-off for all seven series. **Do not mark the seven-family extension complete until the above are done.**

**Initial batch local gate:**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=StandardVoucherAccountSelectionContractTest
php artisan test --filter=BankSourceReissueContractTest
php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest
php artisan finance:audit-posting-balances
```

## Milestone 04 — CLOSED / OWNER ACCEPTED (2026-11-23)

**Closure basis:** Owner confirmed all functional Cheque/Online browser scenarios PASS, reviewed the redesigned Pending Bank Corrections modal, verified the final `Repl. Pending` badge and button-only Actions menu (no long request/status text), and ran the last terminal gate: `BankManagementRegisterJavaScriptContractTest` **2 passed / 28 assertions**, alongside the previously recorded `BankLinkedCorrectionApprovalIntegrationTest` **19 passed / 138 assertions**, and **six-project posting balances CLEAN**. Other focused bank release contracts were also tested in prior turns. No new schema/table required for final UI fixes.

**Final approved policy:** A Cheque or Online source with **no voucher history** updates its original ID; any historical voucher allocation, even pending/rejected/already released, uses a new unique bank reference, archives/withdraws the old source and related vouchers, creates an unverified replacement and requires fresh bank clearance/confirmation before creating new vouchers. Independent approval settings remain enforced: pending request displayed as `Repl. Pending` with lifecycle/edit/delete actions hidden, original bank status retained internally until decision. Pending approvals cancelled and active financial legs soft-voided only upon approval. Approval review shows human-readable field changes and voucher impacts; bank details auto-append as a single compact narration line.

**Final UI change verified by owner:** Cheque Management snapshot showed `Repl. Pending` in the status cell, no extra `Bank: cleared` or `Request # ... awaiting decision` row text, and existing action-menu buttons instead of inline explanatory strings. Equivalent Online implementation shares the accepted contract.

**Milestone status: CLOSED. Do not reopen or redesign Milestone 04 without a newly reported actual regression.** Previous Milestone 04 notes lower in this README are chronological and superseded wherever they describe old same-ID linked-voucher behavior or pending acceptance.

## Milestone 03 — NEXT ACTIVE BATCH (2026-11-23)

Milestone 03 stays **PARTIAL** because repository documentation does not yet record full browser acceptance for the remaining voucher/source families. The immediate next batch is **verification-first, then targeted fixes**; do not rewrite already accepted Plot/JV/CV flows.

1. **SV and JV latest lifecycle:** run `SalesVoucherFullLifecycleContractTest`, `JournalApprovedCorrectionContractTest`, `JournalPostingWriterTest` and test draft/approved Create, same-ID Edit, add/remove legs, logical Delete; verify current ledgers, no duplicate postings, and matching AJAX response.
2. **Cash voucher family CR/DR/CCR/CDR and BOR/BOP:** run `CashPostingWriterTest`, `LinkedVoucherLedgerRetirementTest`, `BankTransferWriterTest`; browser test each original voucher Create, Edit, Delete, approval and relevant cheque/online picker selection. Reuse the **closed M04 custody writer** for linked source effects; never mutate bank source tables directly from voucher controllers.
3. **PPR, EC/ECP and Labour:** run `BookingReceiptCashEditorTest`, `ExtraChargeCashEditorTest`, `LabourPaymentEditorTest`; verify source/booking/extra-charge/worker ownership, same-source edits and soft deletes, approval and matching Party Ledger. Do not override typed-source ownership.
4. **Stock Entry / Bill:** run `StockDocumentEditorTest` and `StockDocumentLifecycleContractTest`; browser verify quantity and line corrections, inventory non-negative guards, billed source dependencies, AJAX Bootstrap 5 modals and archival of stock/ledger rows.
5. **Permissions / shared reporting:** verify staff vs approver vs admin project-scoped permissions (also outstanding M02), Daily Voucher Directory, Party Ledger, Trial Balance, stock/labour reports, full all-project posting-balance integrity. Only mark M03 complete when user confirms browser matrix and tests. No new tables except where demonstrably required.

**Reference for scenario-by-scenario gates:** `docs/MILESTONE_03_BROWSER_ACCEPTANCE_2026_11_19.md`, which remains an unclosed checklist. Historical test-status lines within that older document are superseded by fresh owner runs, not automatically PASS merely because M04 closed. **M05 Reversal ON/OFF** follows M03; **M06 centralized Activity Audit** stays last.

## Milestone 04 — FINAL CORRECTION RULE (2026-11-23): linked records replaced, no-voucher records edited on original ID

**This business decision supersedes the earlier same-ID soft-void/reissue approach; owner local tests/browser acceptance PENDING.**

- **Any actual voucher allocation history** (including pending, approved, posted, rejected, or already released) forces **Withdraw old source + create a NEW source ID requiring bank verification**. Old Cheque becomes Withdrawn; new Cheque starts Received/Issued. Old Online becomes Withdrawn; new Online starts Pending. No source is automatically re-cleared or re-confirmed. New unique Cheque Number+Bank or Online Transaction Reference is mandatory due existing unique indexes; no fake reference suffixes or silent original-reference changes. Only after the normal Cheque clearance / Online confirmation may users create fresh vouchers against the replacement record.
- **No voucher allocation history at all**: edit the existing Cheque/Online source in place **regardless of its current status**, preserve source ID and do NOT create a replacement. Verified identity change (cheque number/bank/direction or online reference/type) resets that same ID to appropriate unverified status to prevent retaining old verification for a different instrument; non-identity changes preserve existing status, with audit snapshot under the reviewed Correct workflow. Legacy missing-claim evidence remains fail-closed.
- For linked sources, the **existing BankManagementSourceMutation::replace() and withdraw()** are called from the approved source-correction engine. Inside a project-locked transaction, pending voucher approvals are cancelled with archived reason and event, approved/posted voucher/ledger rows soft-voided, already rejected or released history preserved; every still-active allocation receives one audited release. Any failed leg or stale fingerprint rolls back ALL changes and prevents creating the replacement.
- Correction permissions and CHQ_CORR/ONL_CORR approval ON/OFF settings are retained. Approval ON stages an immutable proposed change until independent approver decision; both original vouchers and original bank source remain unchanged before approval. Legacy management PUT will not bypass ON settings or pending correction requests. Approved request performs the entire withdraw/replacement in one transaction.
- Cheque/Online register primary Edit/Correct action is now approval-aware for users granted `finance cheque correct` or `finance online correct`. Linked and unlinked sources both use reviewed preview/confirm. Transaction ID / Cheque Number are editable and checked for database uniqueness; a linked replacement cannot reuse the old bank identity. Confirmation clearly distinguishes **same-ID update** from **old Withdrawn + new Pending/Received record**.
- No new database migration or table in this change. Prior auto-appended single-line Cheque/Online Details remain unchanged.
- Tests adapted: `BankLinkedCorrectionApprovalIntegrationTest` (linked replacement, zero-voucher same-ID, pending cancellation, approval ON/OFF, stale guard, historical released links, Online/Cheque), `BankManagementMutationContractTest`, `BankSourceReissueContractTest`, `BankLinkedCorrectionHttpContractTest`. **Terminal test results for these latest changes are not yet available.**

**Local commands, in configured disposable Laragon test database:**
```powershell
cd C:\laragon\www\blueMarketing
git -c gc.auto=0 pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/BankManagementSourceMutation.php
php -l app/Services/Finance/BankSourceReissueCorrection.php
php -l app/Http/Controllers/Finance/BankManagementMutationController.php
php -l app/Http/Controllers/Finance/BankLinkedCorrectionController.php
php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest
php artisan test --filter=BankManagementSourceMutationTest
php artisan test --filter=BankManagementLinkedCascadeTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan test --filter=BankSourceReissueContractTest
php artisan test --filter=BankManagementMutationContractTest
php artisan test --filter=BankLinkedCorrectionHttpContractTest
php artisan finance:audit-posting-balances
```

**Browser testing REQUIRED after green local tests:** 1. **No-voucher** Cheque (including Cleared) Edit amount/detail -> same source ID, no Withdrawn/new record; repeat Online confirmed without vouchers. 2. **Linked Pending** Cheque / Online -> Correct/Edit with NEW unique cheque number or reference -> Preview lists pending voucher, approval OFF Confirm -> old source Withdrawn, pending approval cancelled, voucher inactive/released, NEW source Received/Issued/Pending; verify original voucher numbers remain historical. 3. **Linked Approved/Posted** -> posting legs inactive, releases audited, new unverified source awaits clearance; after actual clearance/confirmation new source selectable with FULL corrected amount and fresh voucher IDs/numbers. 4. **Approval ON** -> pending bank-correction request causes no changes until authorized approver Approve; Reject leaves all unchanged. 5. **Already released history** still creates new source (not same-ID). 6. Trying to use original linked cheque/reference unchanged must show clear uniqueness error and leave DB untouched; check cross-project and denied permissions. 7. Recheck compact source Details and 6-project posting balance audit. Do NOT run these tests on real cleared bank funds.

## Milestone 04 — Owner browser fixes: pending approvals, withdrawn status and compact details (2026-11-23)

- Source correction now supports active **posted, pending-approval, and rejected** bank vouchers. Only the explicitly privileged source correction path passes `cancelPendingForSourceCorrection=true` to `BankVoucherAllocationRelease`; ordinary release retains the existing pending-approval block. Pending vouchers must have inactive ledger legs, otherwise the whole source correction fails. Pending voucher approvals are marked rejected/cancelled with deciding actor, reason, event and one immutable allocation release; already posted vouchers are soft-voided; historical allocations remain in the database.
- On successful source correction, original cleared Cheque / confirmed Online source ID and verified status remain intact; fresh allocations can use the entire corrected amount and issue new voucher numbers. An unverified source without vouchers can edit without being incorrectly promoted to Cleared. Previously Withdrawn source may be restored only when original bank clearance evidence exists AND it was archived by an audited replacement which itself has been withdrawn. Genuine bank withdrawal remains Withdrawn after field editing, even with old clearance history. An active replacement cannot coexist with restored original funds. Otherwise it stays unverified or the operation refuses with a clear reconciliation message. No physical deletion and no additional migration.
- Verified Cheque/Online register's primary action now offers **Edit** on an unlinked source or **Correct / Reissue** when historical vouchers exist, rather than prioritizing the confusing **Replace & Reverify** route which was moving old source to Withdrawn. Existing genuine replacement backend remains separate.
- Voucher picker source details now append as **one compact line** exactly `SOURCE Cheque | Title: ... | Cheque Number: ****1234 | Bank Name: ... | Clearing Date: ...` or `SOURCE Online | Transaction ID / Reference: ... | Sending Bank: ... | Receiving Bank: ... | Clearing Date: ...`. Manual user narration remains; the former multiline bracket block and previous generated line are removed on source change.
- Added / revised integration tests for pending Cheque and Online voucher cancellation, fail-closed unexpected active pending posting, verified withdrawn Cheque restoration, no-proof withdrawn exclusion, and unverified unlinked status retention. Source and vendor IDs remain stable. Register and correction wizard JavaScript syntax passed static parse verification via connected repository content; **owner's PHP/feature/browser tests have not yet been run for these latest commits**.
- Owner local gate (disposable data only): `php artisan optimize:clear`, `php artisan view:cache`, `php artisan test --filter=BankLinkedCorrectionApprovalIntegrationTest`, `php artisan test --filter=BankVoucherAllocationReleaseTest`, `php artisan test --filter=BankSourceReissueContractTest`, `php artisan test --filter=BankManagementRegisterJavaScriptContractTest`, `php artisan finance:audit-posting-balances`.
- **Mandatory browser checks**: (a) cleared Cheque with pending BOR → Edit/Correct → source approval OFF: voucher becomes inactive and approval cancelled; cheque remains Cleared; fresh voucher picker offers corrected full amount, (b) correction approval ON: pending vouchers remain unchanged before decision, then cancel on approval; Reject does not change data, (c) confirmed Online with pending CR/BOR equivalent, (d) prior Withdrawn Cheque only restored where original verified clearance evidence and no live replacement exist, (e) no-voucher source across statuses allows edits without falsely becoming cleared, and (f) cheque/online picker Details appear as single compact source line, preserving manually entered text.
- Do not alter live bank accounting for browser tests. Milestone acceptance remains pending until owner confirms.

## Milestone 04 — REVISED source correction + soft-void and reissue (2026-11-22)

**New flow supersedes prior same-ID voucher amount editing. Code pushed; owner terminal and browser acceptance PENDING.**

- **Correct** on cleared Cheque / confirmed Online opens original source fields; verified cheque number, bank identity, reference and direction cannot silently change. Genuine instrument changes still use separate **Replace & Reverify**.
- **Review Correction** shows before/after fields and active linked voucher impacts. No manual per-voucher redistribution.
- Approval OFF: one project-locked transaction revalidates source/ledger/approval identities; BankVoucherAllocationRelease soft-voids EVERY active linked voucher, releases allocations, retains original voucher IDs/numbers and posting history; then updates SAME verified source ID, preserves cleared/confirmed state and records bank_source_fix before/after snapshot. All-or-nothing rollback. Works with zero, partial or full allocation.
- Approval ON: same proposal persists in existing finance_bank_correction_requests; old vouchers remain posted until authorized independent approval. Approve revalidates fingerprint and atomically releases vouchers/updates source; Reject keeps originals. Source cannot create vouchers while correction pending.
- Existing original online bank confirmation event stays immutable. PassedBankSourcePickerController and PassedBankVoucherClaim now honor latest audited correction snapshot for corrected amount, so updated source becomes available for fresh new voucher numbers/IDs. Historical released allocations remain retained, not deleted.
- Verified source picker returns Online Reference, Sending Bank, Receiving Bank, Clearing Date; or Cheque Title, masked last four digits, Bank and Clearing Date. Selection appends replaceable [BANK SOURCE] detail block to voucher Details, preserving manual narration, preventing duplicate blocks, validating maxlength 255.
- Correction permission remains independent from delete rights. Legacy BankLinkedCorrection is not invoked by the public correction path; its old per-voucher wizard removed.
- Migration / permission policies unchanged from previous batch: existing CHQ_CORR and ONL_CORR policy switches and finance_bank_correction_requests table reused. No additional table created for revised reissue flow.

### Revised flow local gate (disposable local database)

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/BankSourceReissueCorrection.php
php -l app/Services/Finance/BankSourceCorrectionEvidence.php
php -l app/Services/Finance/BankLinkedCorrectionApproval.php
php -l app/Http/Controllers/Finance/BankLinkedCorrectionController.php
php artisan view:cache
php artisan test --filter=BankLinkedCorrection
php artisan test --filter=BankManagementLinkedCascadeTest
php artisan test --filter=BankVoucherAllocationReleaseTest
php artisan finance:audit-posting-balances
php artisan migrate:status
```

Only if previously added correction request migration is not applied, use `php artisan migrate --path=database/migrations/2026_11_22_180000_create_finance_bank_correction_requests_table.php` on disposable local database. Never run migrate:fresh.

### Browser acceptance REQUIRED after green local tests

1. Correction approval OFF: cleared cheque Rs 10,000 with zero vouchers -> Correct to Rs 9,000. Same ID, no ledger created, full Rs 9,000 available to source picker.
2. Partially allocated Rs 10,000 source with Rs 6,000 linked approved voucher -> correct to Rs 9,000. Review shows 1 voucher; confirm soft-voids exactly one voucher, preserves historical ID, frees full Rs 9,000 for NEW voucher number.
3. Fully allocated Rs 10,000 with Rs 6,000 + Rs 4,000 vouchers -> both soft-voided after correction, both audited releases retained, source ID unchanged, fresh voucher numbers. Stale/invalid/pending linked vouchers must fail atomically.
4. Correction approval ON: submitter sends request -> original vouchers remain active; other eligible approver Approve -> atomic source/ledger mutation; Reject -> no changes. Pending source hidden from new voucher picker.
5. Repeat all on confirmed Online source and check original confirmation evidence remains. Verify fresh voucher passed-source chooser and Details auto-append for Online and Cheque, clearing date, last 4 masked digits, manual narration preservation, and no duplicate block on changing selection.
6. Deny unauthorized/cross-project corrections and verify original genuine replacement remains separate. Run all-project finance:audit-posting-balances after tests.

**Do not mark Milestone 04 accepted until user terminal/browser confirmation.**

## Milestone 04 — Safe Partial/Full/Zero Allocation Bank Correction + Independent Approval (2026-11-22)

**New code committed, OWNER LOCAL TESTS AND BROWSER ACCEPTANCE PENDING. Do not mark Milestone 04 complete.**

- Verified/cleared Online/Cheque management sources may have **0, some, or all** amount allocated to posted vouchers. `BankLinkedCorrection` accepts exactly the active unreleased voucher IDs (including empty array for zero allocations), excludes released history from writable legs, requires total proposed active allocations <= corrected source amount and displays before/after **allocated and remaining** amounts. Unmatched historical claims are refused. No automatic pro-rata voucher adjustments. All original IDs and voucher numbers remain unchanged.
- **Separate Correct action** in Cheque and Online registers directly opens the review wizard, not the misleading Replace & Reverify modal. Existing Edit/Replace/Delete remain independent. Active voucher amounts, source amount, remaining balance, historical release count and before/after preview are shown. Approval ON yields **Submitted for Approval** rather than claiming save.
- **Independent Spatie permissions** added by one additive migration: `finance cheque correct`, `finance online correct`, `finance cheque correction approve`, `finance online correction approve`. Existing Super Admin roles receive these automatically; others must be granted deliberately through Roles/Permissions. No blanket `delete voucher` requirement for ordinary correction.
- **Independent approval settings** `CHQ_CORR` and `ONL_CORR` reuse `daily_voucher_approval_policies` on `/admin/finance/voucher-settings`. OFF -> permissioned correction applied atomically after Preview/Confirm. ON -> proposed changes queued with **NO** financial mutation; assigned project approver (or authorized Super Admin) decides from **Pending Bank Corrections** button in Cheque/Online registers or central Approval Dashboard. Approve rechecks locked source/voucher/ledger fingerprint and updates exactly once; Reject leaves all originals intact. Original staff submitter and deciding approver separately recorded. Requests must be resolved before another correction of that source.
- **Exactly ONE added table** `finance_bank_correction_requests`, because existing `daily_voucher_approvals` has unique `(source_table, source_id)` and cannot safely represent repeat bank corrections. Existing `daily_voucher_edit_snapshots` continues storing source and voucher before/after history. No duplicate per-voucher audit tables or isolated V2 connections.
- **Safety scope:** This batch enables correction of bank source and linked voucher **AMOUNTS** only. Head/Subhead, Customer/Plot mapping, bank reference, identity, dates and bank-confirmed evidence are NOT auto-rewritten in a linked correction; those fields require exact relationship validation and where relevant re-verification. Existing genuine replacement remains separate. Do not mark full-field correction implemented.
- **Latest tests added but not yet owner-run:** `BankLinkedCorrectionTest` (zero/partial/full/released allocations, source identity and rollback), `BankLinkedCorrectionApprovalIntegrationTest` (approval ON pending/approve/reject/stale/unauthorized), `BankLinkedCorrectionApprovalContractTest`, `BankLinkedCorrectionHttpContractTest`, and existing `BankLinkedCorrectionContractTest`. Previous owner PASS 9 tests/66 assertions and 6-project balance audit predate these changes.

**Owner local verification order (configured disposable local database only):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/BankLinkedCorrection.php
php -l app/Services/Finance/BankLinkedCorrectionApproval.php
php -l app/Http/Controllers/Finance/BankLinkedCorrectionController.php
php -l database/migrations/2026_11_22_180000_create_finance_bank_correction_requests_table.php
php -l tests/Feature/Finance/BankLinkedCorrectionApprovalIntegrationTest.php
php artisan view:cache
php artisan test --filter=BankLinkedCorrection
php artisan finance:audit-posting-balances
php artisan migrate:status
# ONLY when tests are green and correct disposable local database is selected:
php artisan migrate --path=database/migrations/2026_11_22_180000_create_finance_bank_correction_requests_table.php
php artisan permission:cache-reset
php artisan optimize:clear
php artisan view:cache
```

**BROWSER TESTING REQUIRED** after tests and migration:
1. Super Admin opens `/admin/finance/voucher-settings`: check CHQ_CORR and ONL_CORR switches separately. Grant `finance cheque correct` or `finance online correct` to a test staff user; grant corresponding correction approve and `voucher approver` to a different active project user.
2. On **test project** `/admin/finance/cheques`, create and clear a *new disposable* cheque amount Rs 10,000: use Correct with **zero vouchers**, change to 9,500, Preview -> Submit/Apply, confirm same source ID and no ledger/voucher creation.
3. Create a separate cleared cheque Rs 10,000, post just Rs 6,000 BOR/BOP, keep Rs 4,000 unallocated. Correct source amount to Rs 9,000 but leave existing voucher Rs 6,000; Preview should show **Rs 3,000 remaining**. Confirm preserves original voucher and ledger IDs/number.
4. Fully allocate another Rs 10,000 source (two approved vouchers Rs 6,000 + Rs 4,000). Change source to 9,000 and explicitly adjust one voucher to Rs 5,000. Preview should show Rs 0 remaining; ledger and Party Ledger reflect corrected amounts. Trying to lower source below unchanged allocations must give a clear refusal with no DB changes. Repeat same cases on Online Management `/admin/finance/online-transactions`.
5. Turn CHQ_CORR approval **ON** and select a *different* eligible approver. Submit a correction: source and vouchers remain unchanged; approver opens `/admin/finance/daily-vouchers/approvals` -> Pending Bank Corrections -> Approve; only then amounts apply. Submit another and Reject; amounts must remain unchanged. Permission-off users get 403, cross-project sources cannot be touched, repeat confirm must not duplicate rows, and stale request approval must refuse. Turn policy OFF afterwards if appropriate. Verify report and all-project posting audit.

**Owner acceptance:** Only mark code tests and browser scenarios PASS after actual terminal/browser evidence. No browser testing on real customer cheque data.

## Milestone 04 — Batch 03 Update (2026-11-22): reviewed amount correction web integration — LOCAL/BROWSER GATE OPEN

- Owner verified previous service-only tests: `BankLinkedCorrectionContractTest` **2 PASS / 19 assertions**, `BankLinkedCorrectionTest` **7 PASS / 47 assertions**, and read-only posting integrity audit all 6 projects **zero findings**. This acceptance PRECEDES the following web integration changes.
- Added `BankLinkedCorrectionController` and POST `finance.bank-management.correction.preview` / `finance.bank-management.correction.confirm` routes. Both require selected project, granular `finance online edit` or `finance cheque edit`, `delete voucher` and Super Admin privilege. Confirm requires the exact reviewed fingerprint and valid reason.
- Added shared jQuery `bank_linked_correction_script.blade.php` and the separate **Correct Linked Amounts** action inside existing Cheque and Online management editing modals for verified sources with linked allocations. Wizard closes the Bootstrap modal before SweetAlert2 inputs (avoids focus trap), shows each linked voucher amount, allows explicit allocations and a reason, POSTs preview, shows before/after and POSTs confirm, refreshes source register without full page reload. No old source withdrawal, new cheque ID, automatic voucher distribution, or reversal is part of this action.
- Source and voucher amount changes are transactional; IDs, display numbers, account ownership and bank identifiers remain unchanged. Historic approvals, released allocations, foreign projects, malformed ownership and pending revisions cause safe refusal. Added `BankLinkedCorrectionHttpContractTest`.
- **Local tests NOT RUN for new UI/controller code:** `php -l app/Http/Controllers/Finance/BankLinkedCorrectionController.php`; `php -l app/Services/Finance/BankLinkedCorrection.php`; `php -l tests/Unit/Finance/BankLinkedCorrectionHttpContractTest.php`; `php artisan view:cache`; `php artisan route:list --path=finance/bank-management`; `php artisan test --filter=BankLinkedCorrection`; `php artisan finance:audit-posting-balances`.
- **Browser testing REQUIRED after green tests (local disposable records only):** Super Admin with edit + delete voucher permissions, open Cheque Management or Online Management, create and clear/confirm a local test source, create 2 linked approved vouchers, open Edit and press **Correct Linked Amounts**, lower source and one voucher amount (new allocation total <= new source), Preview -> Confirm. Confirm source ID, voucher IDs/numbers and ledger IDs unchanged; amounts/Party Ledger update; no released allocation or replacement/new source. Retry an old fingerprint should fail, reducing source below allocations should fail without changes. Test alternate project and permission-revoked user (403). Check original Replace & Reverify remains separate. Do not test on real customer data.
- **Scope boundary:** This rollout is verified-linked **amount** correction only. Head/Subhead/Customer/Date or account remapping is not yet implemented by this engine, because exact downstream customer/plot/ledger relationships require separate validation. Do NOT claim entire Milestone 04 or comprehensive financial correction complete until that scope and owner browser acceptance close.

## Milestone 04 — Batch 03 (2026-11-22): Same-ID verified bank linked correction engine — PARTIAL

- New `BankLinkedCorrection` implements a project-scoped locked `preview()` and `confirm()` for explicit per-voucher amount corrections on confirmed Online / cleared Cheque sources. It preserves original management source ID, allocated voucher ID/number, allocation ID, and linked ledger ID, with **no physical deletion** or replacement; the old genuine Replace/Withdraw workflow is separate.
- `preview()` requires one proposed amount for every linked voucher, validates one active project-owned ledger leg, original direction/amount identity, permitted approval status, and blocks released, duplicated, inactive, conflicting and pending-edits allocations. The revised allocation total cannot exceed the corrected source amount. No automatic proportional allocation.
- `confirm()` re-locks and re-inspects the graph, requires matching SHA256 reviewed fingerprint plus actor and reason, and atomically updates source amount, allocation amounts, exact original voucher/ledger rows and writes per-voucher before/after evidence in existing `daily_voucher_edit_snapshots`. No additional business/audit table. Failures roll back everything.
- Added `BankLinkedCorrectionContractTest` and disposable SQLite `BankLinkedCorrectionTest`: same-ID online two-voucher changes, source/ledger snapshots, stale-fingerprint rejection, overflow/missing proposals, pending approval, released allocation refusal, foreign project and cleared cheque preservation. GitHub connector committed code and tests; **these tests have not been executed in owner's Laragon and are NOT a browser acceptance**.
- **Deliberately not exposed through existing Edit route yet**: it currently invokes verified-source audited replacement. Batch 04 must add explicit Review & Confirm authorization/UI; no automatic bank-number replacement, reference mutation or hidden financial edits. Batch 03 is currently **amount-correction engine only**, not completed full Head/Subhead/Customer/Date correction. Implement exact FK/account mapping and source-history safeguards before exposing that scope. Do not claim whole Batch 03 or Milestone 04 accepted.
- Local gate: `php -l app/Services/Finance/BankLinkedCorrection.php`; `php -l tests/Feature/Finance/BankLinkedCorrectionTest.php`; `php artisan test --filter=BankLinkedCorrectionContractTest`; `php artisan test --filter=BankLinkedCorrectionTest`. No new migration. Browser test: NOT YET REQUIRED because this engine is not exposed via controller/route; Batch 04 adds the explicit Review & Confirm UI and requires browser acceptance. Integration and concurrent-write tests plus source/voucher/ledger reconciliation remain mandatory before allowing staff use.

## Milestone 04 — Batch 2 (2026-11-22): management Edit/Delete and linked atomic cascade

- Added `BankManagementSourceMutation` and `BankManagementMutationController` with scoped routes for Online/Cheque Edit, Update and Delete. Existing management register forms now support jQuery AJAX Edit and audited Withdraw. No migration or extra mapping tables.
- **Unverified Pending/Received/Issued, unlinked:** Edit original source record under selected project lock. Delete archives it as `withdrawn` instead of removing bank/cheque evidence. Historical claims and verification events block unsafe direct edits.
- **Confirmed/Cleared, linked or unlinked:** "Edit" is an audited **replacement**, not a historical rewrite: require authorized Super Admin with voucher deletion privilege and 3–500 character reason; original source and linked voucher ledger entries are voided/released in one DB transaction; new intake ID with NEW unique Online reference or Cheque number is created `pending` / `received` / `issued` and requires fresh bank verification. Old evidence is retained; original proof is never silently reused. Delete withdraws without creating replacement.
- Verified native `BOR`/`BOP`/`DR` and documented old `CR`/`CP` source identities for the audited `BankVoucherAllocationRelease`; original project ledger/source amounts and directions must match. Pending approvals/corrections block cascade. Multiple allocations either all release or all rollback.
- Authorization check for linked Delete is performed under project row lock, so another concurrent voucher writer cannot add an allocation between privilege check and mutation. Independent finance granular `edit`/`delete` permissions apply to every route; financial cascade also requires Super Admin and `delete voucher`.
- Regression tests added: `BankManagementSourceMutationTest`, `BankManagementLinkedCascadeTest`, `BankManagementMutationContractTest`. Re-run existing `BankVoucherAllocationReleaseTest` and `BankManagementCascadePlannerContractTest`. **Not yet executed on owner's Laragon environment**. Never claim verified completion before these pass and user signs off browser scenarios.
- **Browser test matrix:** Online pending edit/delete; Cheque received/issued edit/delete; confirmed/cleared unlinked withdraw/replace; one linked approved BOR/BOP release; multi-allocation source cascade; pending approval refusal and atomic rollback; project 3 and alternate project; permission-off user; repeat/duplicate reference refusal; view history and Party Ledger after void.
- **No automatic transfer of old voucher approval to a new replacement.** New source must pass actual Online/Cheque management again and create newly numbered vouchers via existing workflow. No physical rows are hard deleted.
- **Remaining acceptance:** local PHP feature/unit tests, live browser UX and multi-project ledger totals. Any blocked historic source remains untouched until reviewed.

## Milestone 04 — Batch 1 (2026-11-22): linked voucher impact preflight

- Implemented `BankManagementCascadePlanner`, a project-scoped **read-only** dependency view for confirmed Online and cleared Cheque management sources, using immutable `finance_bank_voucher_allocations` IDs and recorded releases. It reports linked voucher source/ledger validity, pending approval, released state and whether a controlled cascade is required.
- Both Online and Cheque history JSON now include `cascade_impact`; their existing history modals display a clear warning about dependent vouchers. No new tables, migrations or financial updates.
- Regression coverage: `BankManagementCascadePlannerContractTest`. Local owner PHP/browser execution pending.
- **Not yet implemented:** management source Edit/Delete forms/routes, atomic linked approved voucher retraction, multi-allocation cascade, status/evidence corrections, full project browser acceptance. Do not call Milestone 04 complete or attempt to alter linked sources based only on this preview.
- The correct next batch is transactional management Edit/Delete with verified allocation and voucher ownership, approval state and balance assertions; retain immutable source, ledger and release evidence with no duplicate postings.

## Milestone 03 — Verified work

**Owner-confirmed on `bluenew_clean_final`:**
- `2026_11_18_150000_enforce_voucher_source_foreign_keys.php` migrated. Read-only `finance:audit-voucher-schema --require-fks` PASS: **8 of 8 FKs present**, zero orphan references, zero cross-project JV/CV detail/account links.
- `2026_11_18_160000_add_plot_voucher_lifecycle_columns.php` migrated. `finance_plot_receipts` and `finance_plot_purchases` now have `is_active` and `deleted_at`. Existing source records preserved.
- `VoucherRelationshipSafetyMilestoneTest`: **4 PASS / 57 assertions**, `LinkedVoucherLedgerRetirementTest`: **3 PASS / 10 assertions** (earlier owner run), `PlotVoucherLifecycleColumnsTest`: **3 PASS / 23 assertions** in latest run. PHP lint `PlotEntryWriter.php` PASS; view cache PASS.
- JV/CV **legacy branch** transactional Edit/Delete: linked old ledger rows deactivated with exact project/source/detail/amount checks; previous details/header soft-deleted where appropriate; no business hard deletion. Draft Delete archives the project-scoped draft.
- Plot History/Details hide inactive/deleted source roots. Old request-key replay now rejects an archived Plot Receipt/Purchase instead of returning it as active.

**Prior owner failure (RESOLVED):**
- `PlotEntryWriterTest` reported **23 FAILED / 0 assertions**, all due to the **same setup exception**: `Class "App\\Services\\Finance\\V2IsolatedConnectionGuard" not found`, triggered by `database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php:11`. This is a leftover historical dependency after the user already merged old V2 architecture into the normal application. The failure happened in test fixture setup, before test assertions; it does **not** establish that all 23 Plot posting operations have functional bugs.

**Previous GitHub fix, now owner verified PASS:**
- Replaced the deleted `V2IsolatedConnectionGuard` with existing **`FinanceConnectionGuard`** in all **four** Plot storage fixture/recovery migrations: `database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php` and three files under `database/local_finance_migrations/2026_11_14_*`. They now use the application's configured DB; no DB-name prefix or environment isolation check. Existing required-table, no-overwrite, partial-schema refusal, driver-specific MySQL FK recovery and RESTRICT FKs remain.
- Replaced three old Plot feature-test assertions that specifically demanded **`V2_ISOLATED_CONNECTION_REQUIRED`** with assertions that verify already-installed storage is preserved regardless of environment and that MySQL-only FK repair still refuses SQLite.
- Added `tests/Unit/Finance/PlotLegacyMigrationCompatibilityTest.php` to prevent reintroduction of the deleted guard dependency.
- **Owner terminal confirmed FIX PASS:** `PlotLegacyMigrationCompatibilityTest` **2 / 24 assertions**, `PlotEntryWriterTest` **23 / 121 assertions**, `PlotVoucherLifecycleColumnsTest` **3 / 23 assertions**. **Total: 28 tests, 168 assertions PASS.** The configured `bluenew_clean_final` FK integrity audit also remains **8/8 FKs, zero orphans, zero cross-project JV/CV account links**. No need to repeat older FK/Plot schema migrations.

## 2026-11-18 — Owner test PASS and subsequent Plot archive/reversal safety batch

The last owner terminal output confirms **all 23 PlotEntryWriterTest methods PASS (121 assertions)**, legacy migration compatibility **2 PASS / 24 assertions**, and Plot lifecycle **3 PASS / 23 assertions**; total **28 PASS / 168 assertions**. The earlier deleted V2 class fixture blocker is closed. DB read-only FK audit still **8/8 and clean**.

**Additional code committed after this owner PASS:** `PlotEntryLifecycleService::reverse` now checks the source root's **is_active=1** and **deleted_at IS NULL** (when columns exist) in the same project-scoped locked transaction. Previously an archived source could still produce a second financial mutation via the separate reversal endpoint even though Details/History/Replay hid it. New isolated SQLite integration tests cover both archived Plot Receipt (no new posting batch or lines) and archived Plot Purchase (no new batch, original inventory still active), in `PlotEntryWriterTest`. This blocks a conflicted action; it is **not** an implementation of ordinary Edit/Delete or a reversal-only substitute. No business rows are deleted or archived by this code batch.

**Latest code is committed, but the two NEW regression tests have not yet been run locally.** Next commands:
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotEntryLifecycleService.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotLegacyMigrationCompatibilityTest
php artisan finance:audit-voucher-schema --require-fks
```

## 2026-11-18 — Owner accepted archived-source regression tests; browser gate policy

**Owner verified on local `bluenew_clean_final`:** `PlotEntryWriterTest` **25 PASS / 127 assertions**, `PlotLegacyMigrationCompatibilityTest` **2 PASS / 24 assertions**; total **27 PASS / 151 assertions**. `PlotEntryLifecycleService.php` PHP lint PASS. `finance:audit-voucher-schema --require-fks` remains PASS (**8/8 FKs**, zero orphans, zero cross-project JV/CV detail links). This validates both recently added **archived Plot Receipt and Plot Purchase reversal refusal** integration tests. No browser pass has been reported for these flows.

**Owner instruction:** proactively flag browser-testing gates when they actually arise; specify URL/page, role/project, exact Create/Edit/Delete/reversal action, expected result, and whether disposable local test data is necessary. **No browser testing is required for this strictly backend archived-source regression batch.** A browser gate becomes mandatory once actual user-facing Plot Edit/Delete and linked posting/inventory workflow is implemented. Do not mark complete on unit tests alone. Maintain this single README after each implementation batch and keep future handoffs up to date.

## 2026-11-18 — Milestone 03: Audited Plot Receipt/Purchase narration Edit (LATEST)

**Owner verified preceding batch:** PlotEntryWriterTest **25 PASS / 127 assertions**, PlotLegacyMigrationCompatibilityTest **2 PASS / 24 assertions**; total **27 PASS, 151 assertions**. FK audit **8/8 present**, zero orphan references, zero cross-project JV/CV detail links. This does not verify the following new code.

**Implemented and committed (NOT yet tested locally):**
- New append-only audit table via `database/migrations/2026_11_18_170000_create_plot_voucher_edit_audits_table.php`, storing project, source family/id, before/after narration, editor and timestamps; no destructive down migration.
- `PlotVoucherNarrationEditor` makes a real **transactional narration-only Edit** for Plot Receipt and Plot Purchase, locks active same-project source, validates exact same-project posting link, disallows previously reversed source, updates source narration, writes an audit record atomically, and preserves original finance posting lines, amounts, allocation records and purchased plot inventory untouched.
- `VoucherController` PATCH handlers and `routes/web.php` PATCH routes enforce **independent granular Edit** permissions and workflow-selected project authorization, with JSON status and 409 conflict for actual business constraints.
- Both Plot workspaces now offer **Edit narration** inside the shared row Actions control, protected by their module's own Edit permission (not by Reverse permission), compact Bootstrap modal and jQuery AJAX PATCH with inline popup error, success notification and history refresh. No page navigation/reload required for this edit.
- Added `PlotNarrationEditContractTest` for route/permission/UI/source contract and extended `PlotEntryWriterTest` with isolated SQLite integration cases for same-project receipt edit/audit/unchanged financial lines and reversed Plot Purchase edit rejection.

**Known scope / still pending:** This is **narration-only** editing, not full amount/date/customer/allocation/inventory Edit. Full transactional Edit/Delete of posted Plot vouchers and remaining voucher modules still need the accounting-balance lifecycle design and implementation; do not mark Milestone 03 COMPLETE. Existing reversal remains a separate action, not a substitute for ordinary Delete. The new audit migration **must be applied to the intended local DB before browser testing**. Do not run `migrate:fresh` or previous one-off FK/Plot migrations again.

**Owner local gate (code tests first, then migration):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/PlotVoucherNarrationEditor.php
php -l tests/Unit/Finance/PlotNarrationEditContractTest.php
php artisan test --filter=PlotNarrationEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan db:show
php artisan migrate --path=database/migrations/2026_11_18_170000_create_plot_voucher_edit_audits_table.php
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED AFTER LOCAL TESTS/MIGRATION PASS:** Open `/admin/finance/voucher/plot-receipt` and `/admin/finance/voucher/plot-purchase` with selected local test project. Login as user granted **Plot Receipt Edit** or **Plot Purchase Edit**, without requiring Reverse; open an active unreversed disposable voucher's floating Actions menu -> **Edit narration** -> change note -> Save; expect modal close, success toast, same-row narration updated via AJAX and no full-page refresh. Reload and confirm note persists. Revoke that specific Edit permission (leave Reverse as desired) and verify Edit action disappears and direct PATCH is forbidden (403). If source was reversed, editing must return 409 with visible popup error. Verify original monetary amount, allocation list, posting balance and plot inventory unchanged. **Browser acceptance unverified until owner explicitly confirms.**

## 2026-11-18 — Simplify schema: consolidate redundant Plot narration audit table (LATEST)

**Owner concern:** do not create a new MySQL table for every voucher action. The previous Plot narration feature introduced `finance_plot_voucher_edit_audits`, and owner applied its `2026_11_18_170000` migration to local `bluenew_clean_final`. It is excessive for a two-field before/after history already handled by the shared voucher edit snapshot structure.

**Owner latest terminal verification:** `PlotNarrationEditContractTest` **2 PASS / 33 assertions**; `PlotEntryWriterTest` **26 PASS, 1 FAILED** (136 assertions) where `assertSame($before, $after)` compared separate `stdClass` object instances even though accounting line data displayed the same; `php artisan view:cache` PASS; `finance:audit-voucher-schema --require-fks` **8/8 FKs PASS, zero orphans, zero JV/CV cross-project links**; `2026_11_18_170000_create_plot_voucher_edit_audits_table.php` **DONE** on local DB. **No application-level Plot browser test reported.**

**This code batch (committed; latest owner tests pending):**
- `PlotVoucherNarrationEditor` now writes before/after JSON into **existing** `daily_voucher_edit_snapshots`, with distinct `status=plot_note`, `approval_id=NULL`, project and source id, and source kind embedded in both snapshots to disambiguate Plot Receipt vs Purchase vs customer_ledger numeric IDs. Requires valid actor ID. Retains same transaction, project-scoped source lock, original finance amounts/allocations/inventory untouched, Edit permission and AJAX UI. Existing pending cash approval queries check `status=pending` and never treat `plot_note` as an approval. **No new table required for Plot Edit going forward.**
- `2026_11_18_180000_consolidate_plot_narration_audit.php` is **a one-time cleanup migration**: confirms shared table exists; checks all legacy Plot edit records for complete attribution; copies existing records to shared snapshots, preserving original before/after notes, actor and timestamps; uses stable per-row reason to avoid duplicate copy on retry; **only after successful copy drops the redundant `finance_plot_voucher_edit_audits` table**. On invalid evidence or copy failure, migration fails without dropping legacy table. Does **not** modify any financial source, postings, inventory or ledger. This is the only new migration to apply locally; previous `170000` already ran.
- Updated Plot integration fixture to use existing snapshot layout and assert JSON before/after values. Fixed false test failure by comparing arrays of posting line **field values**, rather than `stdClass` object identity; ordering fixed by id. Added a SQLite feature case confirming legacy audit rows copy correctly and redundant table is removed without losing history. Updated contract test to prohibit use of the redundant table by runtime service.

**Run on local test database after code pull; confirm selected .env database before migration:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/PlotVoucherNarrationEditor.php
php -l database/migrations/2026_11_18_180000_consolidate_plot_narration_audit.php
php artisan test --filter=PlotNarrationEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan tinker --execute="dump(DB::connection()->getDatabaseName());"
php artisan migrate --path=database/migrations/2026_11_18_180000_consolidate_plot_narration_audit.php
php artisan finance:audit-voucher-schema --require-fks
```
**Expected:** New tests PASS, shared `daily_voucher_edit_snapshots` remains, redundant `finance_plot_voucher_edit_audits` disappears. If migration finds old rows without an editor, it intentionally fails and preserves old table; inspect those rows, do not force-drop. **Browser test still required for both Plot Edit Narration workspaces** after code/migration pass (disposable voucher, user with Edit ON, test independent Reverse OFF and 403 on direct PATCH without Edit).

**Follow-on engineering policy:** Before adding any further business or audit table, explicitly inspect existing tables and prefer reuse where semantics and integrity can be preserved. Schema growth needs a demonstrated functional necessity, not one table per new Edit/Delete action. **Milestone 03 still PARTIAL**: full amount/allocation/inventory edits and ordinary logical deletion remain unimplemented.

## 2026-11-18 — Owner PASS + full-table redundancy audit (LATEST)

**Owner local confirmations:** `PlotNarrationEditContractTest`: **2 PASS / 35 assertions**; `PlotEntryWriterTest`: **28 PASS / 150 assertions**; combined **30 PASS / 185 assertions**. `2026_11_18_180000_consolidate_plot_narration_audit.php` migrated **DONE** on configured `bluenew_clean_final`, copying evidence from the redundant `finance_plot_voucher_edit_audits` table to shared `daily_voucher_edit_snapshots` and removing the old table. FK audit still **8/8 FKs present**, **0 orphans**, **0 cross-project JV/CV detail/account links**. The user wants additional genuinely unused tables cleaned, without arbitrary schema proliferation.

**This batch: read-only whole-database inventory, NOT destructive removal.** New `app/Console/Commands/AuditUnusedTables.php`, command `php artisan schema:audit-unused-tables --json`, inspects **all tables in currently configured schema**, estimated row counts, exact row counts for unreferenced candidates, **incoming and outgoing FK relationships**, MySQL view and trigger references and application source matches under `app`, `routes`, `resources/views`, `resources/js`, `public/js`, and `config`. No other databases are queried or modified. Results include `REVIEW_EMPTY_UNREFERENCED`, `REVIEW_UNREFERENCED_WITH_DATA`, and `REFERENCED_OR_DEPENDENT`. The test `UnusedTableAuditSafetyTest` checks that this tool is read-only and requires evidence. **Current true row counts, current trigger/view dependencies and complete runtime-source matches are not available to GitHub-only review; owner must run it locally. No other table was removed in this batch.**

**Interpretation rules:** Zero records or absence of direct literal references does **NOT** prove a table is useless. Dynamic table names, queued jobs, integrations, reporting, historical migrations, raw SQL, offline consumers and accounting audit evidence require manual trace. Never drop a finance source, posting, reversal, claim, ledger, approval, receipt allocation, purchase inventory, audit/history, permission, migration or system table without confirming all consumers and preserving any relevant data. A candidate with rows is **not delete-ready**. Do not issue broad `DROP TABLE`, `migrate:fresh`, `db:wipe`, `TRUNCATE` or mass rollback. Only after a verified report and confirmed no consumers may a minimal, reversible, data-preserving migration be authored for specific tables. The owner explicitly prefers **reuse/consolidation of shared tables** rather than one new table per Edit/Delete feature.

**Owner next commands (READ ONLY; no browser test for this audit):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Console/Commands/AuditUnusedTables.php
php artisan test --filter=UnusedTableAuditSafetyTest
php artisan tinker --execute="dump(DB::connection()->getDatabaseName());"
php artisan schema:audit-unused-tables
php artisan schema:audit-unused-tables --json | Set-Content .\storage\app\unused-table-audit.json -Encoding UTF8
```

The concise terminal candidate table and the `storage/app/unused-table-audit.json` contain the required evidence for a **specific** cleanup batch. Do not invent a numeric “tables to delete” count before owner provides actual DB results. **Browser acceptance for Plot narration Edit remains pending separately.**

## 2026-11-18 — Owner full DB usage audit PASS; corrected false positives (LATEST)

**Owner ran** `schema:audit-unused-tables` on **`bluenew_clean_final`**: **147 tables / 536 runtime source files**, and `UnusedTableAuditSafetyTest` **1 PASS / 24 assertions**. Initial scan listed only three candidates: `landing_pages` (exact **0** rows, zero literal SQL references), `legacy_migration_history` (**14** rows), `personal_access_tokens` (**18** rows). **NO TABLES WERE DROPPED.**

**Crucial dependency verification:** The original candidate classification was too aggressive because SQL-string-only inspection misses Eloquent model inference and third-party framework consumers:
- **`landing_pages` MUST remain.** `app/Models/LandingPage.php` is an active Eloquent model with inferred table name, `LandingPageAdminController` reads/writes/drafts/publishes page data, and `routes/web.php` defines six admin actions for it. An empty table means public landing content is still on defaults, **not** that the feature is unused.
- **`personal_access_tokens` MUST remain.** `laravel/sanctum` is installed, and `SanctumTokenRegressionTest` creates/verifies/revokes bearer tokens for mobile API. The 18 token records also merit retention subject to explicit expiry policy; removing the table breaks login.
- **`legacy_migration_history` MUST remain.** 14 rows are historical migration evidence; zero first-party runtime matches is insufficient to justify deleting upgrade/reconciliation records. Historical migration files are present.
- Other **144 tables** had source and/or FK/view/trigger references according to initial scanner. This does not certify every table useful, but there is **no verified removable table** among the three flags.

**Implemented scanner accuracy repair (GitHub committed, new local run pending):** `AuditUnusedTables` now infers Eloquent conventional table names and explicit `$table` mappings from `app/Models`. It marks implicit model tables `ELOQUENT_MODEL_DEPENDENT`, and framework/migration infrastructure `FRAMEWORK_OR_HISTORY_PROTECTED`; these **never** appear as removal candidates. Added regression in `UnusedTableAuditSafetyTest` against real LandingPage, LandingPageAdminController and Sanctum test evidence. This is a **code/test fix only**, no migration, no schema changes or table drops. **Never use the initial report to delete `landing_pages`.**

**Verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Console/Commands/AuditUnusedTables.php
php artisan test --filter=UnusedTableAuditSafetyTest
php artisan schema:audit-unused-tables
```

Expected new classification: `landing_pages` **ELOQUENT_MODEL_DEPENDENT**, `legacy_migration_history` and `personal_access_tokens` **FRAMEWORK_OR_HISTORY_PROTECTED**. In this verified set there is **nothing safe to remove**; do not create a cleanup migration. The previously completed Plot-only redundant audit table cleanup remains successful. Browser test for Plot narration Edit is **still pending**, but this scanner fix requires no browser testing.

## 2026-11-18 — Milestone 03: Plot Receipt/Purchase same-ID full-field Edit (LATEST)

**Owner directive:** Stop shipping narration-only edit as if it were full voucher Edit. Speed up actual CRUD completion, reuse the existing Create forms and finance tables, keep one canonical handoff, report any browser test. **No new MySQL table or migration** in this batch.

**Code implemented on GitHub (owner acceptance pending):**
- New `app/Services/Finance/PlotVoucherEditor.php` updates **same source ID, posting batch ID, and posting line IDs** in one DB transaction; validates project ownership, active source, unreversed posting, active project accounts, balanced debit/credit, unique voucher numbers, valid bookings and inventory relationships.
- **Receipt:** updates voucher number/date/customer/amount/narration, cash account posting, each linked plot allocation and receivable credit posting. Requires same count of allocations as prior source. Snapshot of source/allocations/prior posting lines saved to existing `daily_voucher_edit_snapshots` with `status=plot_edit`; no new audit table.
- **Purchase:** updates number/date/seller/narration, area/rate/cost/inventory account/plot metadata and linked purchase-item and posting rows; locks actual existing plot IDs, refuses sold/booked/unavailable inventory, and keeps existing purchased plot count. Prior source/items/plots/posting lines captured in shared snapshot; no new table. No new physical plots or duplicate posting batch inserted.
- `VoucherController` has PATCH `updatePlotReceipt`/`updatePlotPurchase` with **separate Edit permissions**. Existing detail responses expose the source cash account and existing purchased-plot dimensions/definitions for form prefill. `routes/web.php` has source-scoped PATCH routes.
- Both Plot workspace templates use their **full existing Create modal** to load fields for **Edit voucher**, show Save voucher, AJAX PATCH and refresh listing, while keeping independent Create/Reverse authorization. The earlier narration-only endpoint remains for backward compatibility but is no longer the primary Edit button.
- Prior original Create request key no longer replays stale payload after same-source full Edit (`PlotEntryWriter::existingReplay` guards the shared `plot_edit` snapshot status).
- Added `PlotFullVoucherEditContractTest`, three integrated scenarios in `PlotEntryWriterTest` for receipt full-field rewrite/balance, purchase inventory/amount, rejected allocation-cardinality change with rollback, plus stale replay assertion.

**Honest limitations:** Editing **the number of allocated plot lines or purchased inventory rows** is not yet supported: those operations currently fail with a clear 409 and roll back without partial writes. Neither ordinary Plot Delete nor other remaining voucher families' full CRUD is delivered in this commit. Existing exact financial posting lines are modified **in place** under a transaction and before-image snapshot rather than physically deleted; this changes their original amounts, so accounting/provenance acceptance must explicitly confirm it meets owner policy before live deployment. No claim of completed Milestone 03 yet. Browser acceptance required for each full Edit flow.

**Follow-up quality fixes in the same implementation batch:** Full Receipt Details now reads the original cash and individual receivable accounts from the exact posting lines, so editing does not silently replace a historical account with the current default. Purchase Edit Details reads optional plot-definition columns conditionally for database portability. Added two more integration regressions: booked Purchase inventory and wrong-project Receipt edits fail without changing postings. Old original Create idempotency request keys now reject stale replay after full editing. The full form's Save preserves existing source/batch/plot IDs and does not add a table. **These new tests are code-committed only; owner local PASS is still pending.**

**Owner local gate:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Http/Controllers/Finance/VoucherController.php
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED** *after local tests PASS*, only with disposable local vouchers on selected project:
1. `/admin/finance/voucher/plot-receipt` → Actions → **Edit voucher**. Verify full existing fields prefill; change number/date/amount and a plot's allocation amount (do not add/remove allocation rows yet). **Save voucher** should close modal, update history by AJAX without page reload, preserve source ID and balanced account register.
2. `/admin/finance/voucher/plot-purchase` → Actions → **Edit voucher**. Verify full purchased-plot fields prefill; change plot description/area/rate, seller and date, Save; confirm amount/inventory metadata updated and original plot ID retained. Do not test on booked/sold real inventory.
3. Edit permission OFF while Reverse ON must hide Edit button, direct PATCH 403; wrong-project ID must reject. Changing number of allocation/plot rows should show 409 without changing original source or lines.
4. Report screenshots/logs for errors; owner browser PASS not yet received.

**Follow-on:** finish safe add/remove allocation/item line lifecycle, plot ordinary logical Delete, other voucher CRUD, then Milestone 04 linked cheque/online and 05 configurable reversal. Do not add new tables for these unless evidence demonstrates existing schema cannot safely represent source and history.

## 2026-11-18 — Plot Receipt/Purchase ordinary logical Delete (LATEST)

**GitHub implementation, not owner-accepted yet:**
- `PlotEntryLifecycleService::archive` performs **ordinary Delete** of active Plot Receipt/Purchase inside a DB transaction: locks same-project source, uses the established exact linked-posting compensation writer internally to neutralize debit/credit effects, retires unbooked/unsold purchased plot inventory, and marks the original voucher `is_active=0`, `deleted_at=now()`. **It never hard-deletes financial source, allocation, purchase item or posting rows.** If any step fails, the transaction rolls back.
- `VoucherController::deletePlotReceipt` and `deletePlotPurchase` enforce **independent `finance plot-receipt delete` / `finance plot-purchase delete` permissions**. Their DELETE routes and both directory Actions add jQuery AJAX Delete + confirmation/reason modal. Users do not need Reverse permission merely to perform ordinary Delete. Archive supports future-dated original posting through a chronological compensation date and normalizes user-entered multiline reasons.
- New `PlotEntryWriterTest` SQLite integration tests cover Receipt archive (source inactive, old allocations retained, original and compensating batches offset, second Delete rejected) and Purchase archive (source + unsold plot inactive, financial balancing, retained item evidence); `PlotFullVoucherEditContractTest` additionally covers independent Delete permissions/routes/modal.
- Full-field Edit from the same batch remains: original Create modal prefilled, same source and existing posting IDs, changed amounts/dates/accounts/plots, shared before/after snapshot; source/account lookups project scoped and stale original request key replay forbidden. Additional tests reject booked Plot Purchase updates and cross-project receipt updates.

**Still NOT complete:** The present Edit does not support **changing the number of Receipt allocations or Purchase items**. It returns a conflict instead of hard-deleting line evidence or creating wrong mappings. This requires a safe per-line lifecycle extension. Other voucher modules' full CRUD are not implemented by this batch, so **Milestone 03 remains PARTIAL, pending local tests and browser acceptance**. Historical original posting amounts are updated under same-ID Edit with shared before-image snapshot; confirm reconciliation expectations before using real data. No new database table or migration in this batch.

**Exact owner PowerShell verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Services/Finance/PlotEntryLifecycleService.php
php -l app/Http/Controllers/Finance/VoucherController.php
php artisan view:cache
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED after automated tests PASS:** Use **disposable vouchers in local selected project**.
- `/admin/finance/voucher/plot-receipt`: Actions→Edit voucher; entire form prefilled (number, date, customer, account, amount via allocations, narration), change amount and other fields, Save; expect AJAX update and same voucher ID/balanced posting. Actions→Delete; enter reason; expect confirmation close, original hidden from active history, account balance neutralized and original posting evidence retained.
- `/admin/finance/voucher/plot-purchase`: Edit voucher existing purchased plot metadata/area/rate/seller; Save; expect same original plot ID, purchase cost and source history updated; Delete an unbooked unsold test purchase and expect source/plot inactive plus compensated account balance.
- Independent permissions: turn Edit off while Delete on and Reverse off; Edit hidden, Delete works, direct PATCH 403. Turn Delete off: Delete button hidden, direct DELETE 403. A booked/sold purchase must be rejected without changes. No full-page reload or raw error page; toast/modal error only.
- Record browser PASS/FAIL separately; **NO browser acceptance yet reported for new full Edit/Delete** (previous narration-only browser pass does not cover it).

## 2026-11-18 — Plot full Edit: allocation/item add-remove lifecycle (LATEST)

**Supersedes the earlier same-count Edit limitation.** This subsequent code batch implements adding/removing existing Plot Receipt allocations and Plot Purchase items while retaining source ID and financial integrity. **No new tables**; one idempotent schema migration only adds `is_active` default true to `finance_plot_receipt_allocations`, `finance_plot_purchase_items`, and shared `finance_posting_lines`. Already-stored records remain active. This migration has **NOT** run on owner MySQL yet.

- `PlotVoucherEditor::replaceLines` now updates debit/credit sides independently, keeps old cash/seller posting-line IDs, inserts additional active posting lines for new allocations/inventory, and archives removed posting lines with `is_active=0` and **zero debit/credit** (so historic inactive legs cannot distort balances). Full before-image is kept in existing shared `daily_voucher_edit_snapshots`; no destructive child-row deletion.
- `receipt` can add/reassign/remove multiple plot allocations. No longer requires fixed count. Old removed allocations stay physically present with `is_active=0`; newly selected ones link to same receipt. The original source, posting batch, project and balance remain consistent. `PlotEntryWriter::receipt` filters inactive allocation rows.
- `purchase` now uses `legacy_plot_id` to retain precise existing plot identity, allows new purchased plots via the existing validated `PlotEntryWriter::createAvailablePlot` method, and archives removed purchased plot items and unsold/unbooked plot inventory logically. It refuses duplicates or booked/sold inventory. `purchaseDetails` and purchase list counts filter inactive items. Purchase Edit form includes hidden linked plot IDs and enables Add/Remove controls.
- `PostingReversalWriter::reverse` now ignores inactive (zeroed) historical posting lines, and `PlotEntryLifecycleService::reverse` checks only active purchased plot items for reversal/archive inventory.
- Integration fixture now installs the line lifecycle columns. New tests exercise Receipt allocation addition and later archive (balances, source ID and before-image history), Purchase plot addition and later archive (original plot ID retained for active item, removed plot inactive, account balance maintained), plus existing linked-CRUD and Delete regressions.
- **No local PHP tests or real browser tests have yet been executed by assistant for this newest extension.** Previous verified Owner PASS (30 tests/185 assertions) applies only to the earlier schema cleanup, not the new Plot full Edit/Delete batch.

**Final same-turn UI guard:** While Plot Receipt Edit is open, the optional booking search is disabled so table rerender cannot silently discard or restore changed allocation selections; normal Create mode still supports the search. Purchase Edit uses a hidden `legacy_plot_id` to distinguish an explicitly new row from an existing plot (including empty-string-to-null middleware conversion). No new table was added. All new changes still await the owner's local PHP and browser pass.

**Additional code safety checks in this same batch:** Updated `FinanceActionPermission` route action map for both Plot Edit/Delete/narration endpoints, so granular permission middleware correctly recognizes the new routes. `PlotPurchaseReconciliation` now excludes archived inactive purchase items and posting lines; the dynamic Purchase integration test asserts a clean reconciliation after add/remove. `PlotFullVoucherEditContractTest` verifies the migration adds **columns only**, zeroed archived lines, and that reversal/audit consider only active evidence. Runtime line changes, extra regression tests, and the migration are committed but remain unverified locally. No manual database cleanup or earlier migration rerun is required.

**Final UI feedback pass:** Plot Receipt and Plot Purchase now call the common `$.financeVoucherFeedback.toast` (or toastr/fallback) for visible Save/Delete success; receipt success no longer hides inside its closed modal. Inline validation errors remain visible inside form modal. There are still no new tables. This is code-committed but local/browser acceptance pending.

**Important deploy order:** Because the new tests use the migration in disposable SQLite fixtures, first verify test commands below; then confirm the connected DB is `bluenew_clean_final`; apply the NEW `190000` migration once; then perform browser acceptance against disposable local Plot vouchers, not live financial sources. Do NOT rerun old 150000/160000/170000/180000 migrations, nor migrate:fresh.

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Services/Finance/PlotEntryLifecycleService.php
php -l app/Services/Finance/PlotEntryWriter.php
php -l app/Services/Finance/PostingReversalWriter.php
php -l database/migrations/2026_11_18_190000_add_plot_detail_lifecycle_columns.php
php artisan view:cache
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan tinker --execute="dump(DB::connection()->getDatabaseName());"
php artisan migrate --path=database/migrations/2026_11_18_190000_add_plot_detail_lifecycle_columns.php
php artisan finance:audit-voucher-schema --require-fks
```

**BROWSER TEST REQUIRED after tests/migration PASS:**
1. Plot Receipt on `/admin/finance/voucher/plot-receipt`: Edit full voucher; change date/account/amount and **add second booking allocation**, save and verify balanced posting; reopen and remove it, save again; source ID unchanged and inactive historical allocation excluded from details.
2. Plot Purchase on `/admin/finance/voucher/plot-purchase`: Edit; **Add plot**, set unique code, definition/road/facing, area/rate/inventory account; save and verify new linked plot ID. Reopen and remove a **not-booked/not-sold** plot, save; removed inventory inactive, original record/audit preserved and active price updated. Reverse/ordinary Delete should preserve balance and must reject booked/sold plots.
3. Test Edit/Reverse/Delete permissions independently; ensure no full-page reload, server errors shown inside modal and the history refreshed through AJAX.
4. Confirm original JV/CV/CR/etc voucher behavior and reporting are not negatively affected by the shared `finance_posting_lines.is_active` column. Broader Milestone 03 acceptance (other voucher families) is still pending; do not declare whole milestone complete.

## 2026-11-18 — Owner Plot full Edit test gate: UNIQUE allocation correction (LATEST)

**Owner local results at commit `f4262105` (database `bluenew_clean_final`):** `PlotFullVoucherEditContractTest` **4 PASS / 78 assertions**, `PlotEntryWriterTest` **35 PASS, 1 FAIL / 214 assertions**. All requested Plot PHP files passed lint and `view:cache` passed. Failed test: `full receipt edit adds and archives allocation rows without hard delete`. SQLite `UNIQUE(receipt_id, booking_id)` rejected an attempted positional reassignment of row #1 to booking #32 while booking #32 already existed. **This is an application matching bug, not a redundant database constraint.** No schema migration has been reported as executed yet for the latest Plot line lifecycle.

**Code repair after owner output (GitHub committed; owner retest pending):**
- `PlotVoucherEditor::receipt` no longer updates allocation child rows by array index. It locks all receipt allocations, indexes by **booking_id**, deactivates old active bookings that have been removed, updates matching booking records, **reactivates an archived booking row** if re-added, and inserts only genuinely unseen booking IDs. This respects the existing composite unique key and preserves same-child identity.
- A changed set of booking IDs requires the Plot lifecycle `is_active` migration, **even if allocation counts happen to be equal**. Editing existing booking amounts without a membership change remains supported on older schema.
- `PlotEntryWriterTest` integration test now verifies **add booking 32 → archive booking 31 → restore booking 31**, preserving its original allocation row ID, exactly 2 unique booking rows, balanced account credits, same posting batch and 3 edit snapshots. No new table, column or migration created in this repair.
- **Do not report PHP tests PASS until owner terminal confirms.** The earlier 35 PASS/1 FAIL is the only verified feature-test outcome.

**Local verification (first, no migration):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan test --filter=PlotEntryWriterTest
```

If the tests pass, confirm the configured database and inspect the shared-posting impact before applying the existing `2026_11_18_190000_add_plot_detail_lifecycle_columns.php` migration. **Do not add or drop any new table.** Specifically, `finance_posting_lines` is shared across voucher series: audit readers/reporters that sum all lines must be checked for correct inactive-line treatment. The migration itself adds `is_active` to 3 existing tables only and has not yet been owner-applied. After a safe migration and accounting report checks, **browser test is required** for complete Plot Receipt/Purchase Edit, dynamic row add/remove and Delete; previous narration-only browser PASS does not cover it.

## Remaining Milestone 03 work — do not mark complete yet

1. Rerun `PlotEntryWriterTest` on local; resolve any subsequent actual assertion failures. Verify Plot History/Details/Replay after root logical archive.
2. Implement **real atomic Plot Receipt/Purchase Create/Edit/Delete** across sources, receipt allocations, purchased plot inventory, linked finance posting batches/lines and downstream balances. The current `PlotEntryLifecycleService::reverse` is a **separate reversal operation**, not a substitute for ordinary delete. No new V2 isolation, no hard deletes of business records and no silent reversal-only restriction.
3. Review and implement remaining editable/soft-deletable **Bank Deposit/Withdrawal, Extra Charges, typed JV/CV, SV, CR/DR/CCR/CDR/BOR/BOP** workflows, including project-scoped linked relationships and independent action permissions. Retain immutable audit/history evidence.
4. Classify other tables that lack both lifecycle columns, but add them **only where business operations need logical deletion**; audit/event/metadata tables should not be blanket altered.
5. Run representative browser Create/Edit/Delete and financial balance/relationship checks; get owner PASS. Milestone 02's remaining permission browser acceptance also stays explicit.

## Exact next commands for local Windows PowerShell

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php
php -l database/local_finance_migrations/2026_11_14_000100_create_finance_plot_receipt_storage.php
php -l database/local_finance_migrations/2026_11_14_000101_repair_finance_plot_receipt_claim_fk.php
php -l database/local_finance_migrations/2026_11_14_000200_create_finance_plot_purchase_storage.php
php artisan test --filter=PlotLegacyMigrationCompatibilityTest
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotVoucherLifecycleColumnsTest
php artisan finance:audit-voucher-schema --require-fks
```

**No database migration required for this latest fixture/test repair.** Do not run `migrate:fresh`, `db:wipe`, destructive rollbacks, or prior one-off Plot/FK migrations again. The feature test fixture uses in-memory SQLite; it should not mutate local MySQL.

## Requirements every next turn must respect

- Implement **code and tests first**, then update this exact handoff in the same response; do not return with only progress text or another status document.
- Server-side granular voucher permissions for View/Create/Edit/Delete/Approve/Reject/Reverse/Print etc. No old generic grant silently overriding an independently denied action.
- First-party DOM/AJAX uses jQuery only; shared CR/DR design system, colored skeleton loading, compact table cards/filters, Tom Select, modal/toast errors, AJAX CRUD, no whole-page loader/reload.
- Any configured database should work via `.env` without special database-name isolation, custom V2 modules, or renamed copies of finance.
- Financial mutations must be transactional, source-linked, project-scoped, and never hard-delete business rows. Maintain account/ledger integrity and preserve immutable evidence.
- Separate code-committed, user terminal PASS, browser PASS, and pending acceptance in the progress report.


## 2026-11-18 — Owner confirmation: Plot full Edit/Delete browser gate PASS

**Owner local confirmation:** `bluenew_clean_final` selected; `2026_11_18_190000_add_plot_detail_lifecycle_columns.php` migration **DONE**. `finance:audit-voucher-schema --require-fks` reported **8/8 foreign keys present, 0 orphan references, 0 cross-project JV/CV account links**. The lifecycle migration adds `is_active` to three existing tables (`finance_plot_receipt_allocations`, `finance_plot_purchase_items`, `finance_posting_lines`), no new tables.

**Automated local gate:** `PlotFullVoucherEditContractTest` **4 PASS / 78 assertions**, `PlotEntryWriterTest` **36 PASS / 226 assertions**, combined **40 PASS / 304 assertions**; editor PHP lint PASS.

**Owner reports Plot Receipt and Plot Purchase browser tests PASS.** Treat their full Edit/Delete browser acceptance as owner-confirmed. Do not request the same browser gate again without a new change that affects it. This does not independently establish that all shared financial reports were regression-tested or that remaining voucher families have complete CRUD acceptance.

**Next engineering priorities:** (1) inspect readers and reporters of shared `finance_posting_lines` and validate inactive-row semantics and balances across unrelated voucher families; (2) proceed with Milestone 03 remaining voucher Create/Edit/Delete workflows and project-scoped account integrity; (3) keep full application AJAX, common modal/filter/dropdown/skeleton/toast patterns consistent; (4) update this single README after each actual implementation batch. **Milestone 03 remains PARTIAL overall.**


## 2026-11-18 — Milestone 03 continuation: Plot Receipt duplicate booking guard

**Owner acceptance carried forward:** Plot Receipt/Purchase full Edit/Delete browser tests PASS; configured `bluenew_clean_final` lifecycle migration `2026_11_18_190000` DONE; 8/8 FK integrity, no orphans or cross-project JV/CV links; 40/40 automated Plot tests (304 assertions) passed **before this new code batch**.

**Code change committed:** `PlotVoucherEditor::receipt` now detects duplicate `booking_id` values in an Edit allocation payload even if the repeated booking appears with a different plot. Previously the code checked duplicated `plot_id` only; a repeated booking could reach the `(receipt_id, booking_id)` unique constraint, leading to a database exception rather than a clear business validation error. The editor now throws a `DomainException` before writing the updated lines, and the existing transaction protects financial state. `PlotEntryWriterTest` includes a new integration regression that asserts a duplicate-booking edit refuses the write and leaves voucher number, allocation rows, posting lines and edit snapshots unchanged. **No DB schema migration, new table, or UI change.**

**Verification required (not yet locally executed for this new regression):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Services/Finance/PlotVoucherEditor.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

**Milestone 03 is still PARTIAL**: shared posting-line inactive handling and cross-family reports need verification, and other voucher families require lifecycle CRUD completion. Do not repeat prior Plot browser acceptance without relevant UI change. Do not claim new tests have passed before the owner runs them.


## 2026-11-18 — Plot full Edit related-record identity hardening (new local gate)

**Owner verified preceding batch:** PlotEntryWriterTest 37 PASS / 231 assertions, PlotFullVoucherEditContractTest 4 PASS / 78 assertions, total 41 PASS / 309 assertions, plus previous Plot browser acceptance. This predates the following code changes.

**Implemented on GitHub:**
- Posting legs are matched by original source identity: booking_id for Plot Receipt credit legs, legacy_plot_id for Plot Purchase debit legs. Row reordering no longer overwrites another source's active posting line. Unchanged sources retain same posting line ID; truly new related sources append within the original batch; removed legs are deactivated, not hard-deleted.
- Existing Plot Purchase items require their explicit legacy_plot_id on Edit. Missing ID signifies a genuinely new item and no longer silently reuses an existing plot by array index. This avoids writing changes to an unrelated plot when the form is reordered.
- Plot Receipt Details prefill now excludes inactive posting lines so archived credit legs are not treated as active accounts.
- Added SQLite integration regression tests for repeated reordered Plot Receipt/Purchase edits, unchanged source IDs, allocation/item/plot IDs and posting line IDs, balanced amounts and no duplicate posting batch. Existing replacement test now expects archived old and appended new credit leg instead of recycling its ID, and existing purchase edit test supplies explicit related plot ID.

**Scope/limitations:** no new table or migration. An identical Save may still create an additional audit snapshot (not an additional business voucher or posting batch). Previously archived posting lines are not reactivated when a removed booking is reintroduced; an additional active leg may be created while inactive history is retained. A persistent allocation-to-posting-line association would be needed to safely reactivate the exact historical line across arbitrary account changes. Broader financial reports and other voucher CRUD flows remain pending.

**New code requires owner local tests (NOT verified locally by this assistant):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Http/Controllers/Finance/VoucherController.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

No additional migration and no repeated browser gate unless relevant UI regression appears.


## 2026-11-18 — Owner regression follow-up: booked Purchase test identity corrected

**Owner latest local gate on preceding commit:** PlotEntryWriterTest 38 PASS / 1 FAIL (244 assertions), PlotFullVoucherEditContractTest 4 PASS / 78 assertions; PHP lint passed. The only failure was the booked-plot edit test's expected DomainException text. After positional fallback removal, this test submitted an existing purchased plot without its `legacy_plot_id`, so the service correctly interpreted it as new inventory and refused to remove the booked original, producing a different business error. This is a test payload mismatch, not evidence that booked inventory was mutated.

**Targeted correction committed:** Updated booked-plot regression input to pass the actual linked `legacy_plot_id`. This exercises the intended existing-plot edit branch and verifies the explicit booked-plot guard, rollback of source and posting balance, and no audit snapshot. Application service and database schema were not changed in this batch. Owner retest is pending.

**Local gate:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

Do not report 43 PASS until terminal output confirms. No migration or browser re-test needed for this test-only correction.


## 2026-11-18 — Milestone 03 relationship integrity continuation (code batch; owner gate pending)

**Previous owner pass:** PlotEntryWriterTest 39 PASS / 248 assertions and PlotFullVoucherEditContractTest 4 PASS / 78 assertions, combined 43 PASS / 326 assertions. This pass preceded the changes below.

**Implemented and committed:**
- Plot Receipt Details AJAX prefill no longer derives receivable accounts from posting-line array offsets. It selects active posting legs scoped to the same source batch and project, matches credit account by actual customer and plot relationship, and resolves cash leg separately. A missing/ambiguous leg returns a JSON 409 rather than silently pre-filling another plot's account. This fixes a potential wrong-account Edit after source rows are removed and new posting legs are appended.
- Plot Receipt/Purchase full Edit now verifies old active posting amounts and related account/plot identity against active allocation and inventory item rows before changing anything. A corrupted historical linkage throws a business conflict inside the existing transaction, preserving the source and audit history. This specifically guards against overwriting an unrelated record in a corrupted or reordered source.
- Added two isolated SQLite integration regression tests: corrupt Receipt credit account/plot relationship and corrupt Purchase inventory debit amount both refuse Edit, preserve original source, and create no edit snapshot or second batch. Added a contract regression ensuring Receipt AJAX prefill never uses posting array indexing.
- Reused existing source, financial tables and lifecycle columns; no new migration or table, no destructive edits. Previous full Plot browser acceptance remains recorded, but this batch affects prefill so a focused browser check may be warranted after local tests.

**New owner verification (NOT YET EXECUTED):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PlotVoucherEditor.php
php -l app/Http/Controllers/Finance/VoucherController.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
```

**Remaining scope:** overall Milestone 03 cross-family voucher CRUD and shared financial reporting regression are still not certified complete. Do not imply this focused integrity batch completes those unrelated modules. No new DB migration needed.


## 2026-11-18 — Follow-on: historical posting line identity after archive/restore

Further source inspection found a crucial edge case in the new integrity guard: after an allocation was archived then restored, the allocation row ID may be older than the new active posting line ID. An index-based old-line check would reject a valid subsequent Edit. This was corrected before owner testing.

**Committed code fix:** Existing Receipt credit legs are now matched against active allocation rows by project/customer/plot account relation and exact amount, not line sequence; original posting-line ID maps to the matching booking_id for the replacement operation. Existing Purchase inventory debit legs are matched by linked source item's unique plot-code description, inventory account and amount, and mapped to the matching legacy_plot_id regardless of row order. Ambiguous/mismatched original evidence fails safely before writes; no extra posting batch. Historic lines remain deactivated, not hard-deleted.

**Regression extensions:** The existing add/archive/restore Receipt test now performs one more valid Edit after restoring the older booking and asserts balance, original allocation row count and single batch. The existing Purchase dynamic add/archive test likewise performs an additional Edit after removing a prior plot and verifies no duplicate purchase items or batch.

**Owner verification pending:** Run the PlotEntryWriterTest and PlotFullVoucherEditContractTest commands in the preceding section after pulling latest branch. This is a code/test change, not a new migration. No new SQL tables or columns.


## 2026-11-18 — Owner accepted Plot relationship browser tests + cross-series posting audit batch

**Owner acceptance:** Local `bluenew_clean_final` browser scenarios 1) Plot Receipt add/remove/restore + correct receivable account prefill, 2) Plot Purchase existing record Update/no duplicate inventory, and 3) AJAX validation error without full-page reload were all explicitly confirmed PASS. The immediately preceding owner terminal results were `PlotEntryWriterTest` **41 PASS / 263 assertions**, `PlotFullVoucherEditContractTest` **5 PASS / 88 assertions** (46 total / 351 assertions); `view:cache` and PHP lint passed. Do not request these browser tests again without relevant UX changes.

**This GitHub implementation batch (new local verification PENDING):**
- `PlotPurchaseReconciliation::audit` excludes logically deleted (`is_active=0` or `deleted_at` set) Plot Purchase roots from *active* inventory reconciliation. The original source, posting, reversal and item evidence remain stored; no financial mutation occurs. Extended Plot Purchase Delete integration test to assert no false-positive active inventory findings after archive.
- Added reusable read-only `PostingBatchIntegrityAudit`, which scans every posted series (including reversal batches) for a requested project, considers only active posting legs, checks debit/credit positivity and equal amounts, verifies account project ownership, and reports invalid or missing line legs. It does not change rows or introduce a table.
- Added Artisan command `finance:audit-posting-balances --project=N` (`--json` optional). Explicit project scoping is required to avoid accidental cross-project reporting. It exits nonzero if findings exist. This is an integrity check; it does not certify every voucher module's business lifecycle or replace full reports.
- Added isolated integration regression for a valid Receipt and Purchase pair, tampered financial credit, restored balanced posting, an inactive credit leg, and another project's empty result. It checks that no extra posting batch is created.

**Owner local gate (no migration or browser test for backend/read-only change):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l app/Console/Commands/AuditPostingBatchIntegrity.php
php -l app/Services/Finance/PlotPurchaseReconciliation.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances --project=3
```

The example project ID 3 is only valid if project 3 exists in the user's current DB; use the selected local project ID. Audit is read-only. Do not misreport failures if actual historical data is inconsistent; investigate each batch first.

**Roadmap:** Milestone 03 remains PARTIAL. This batch advances shared posting integrity and Plot reconciliation, but remaining JV/CV/cash/stock/etc full CRUD and permissions browser acceptance are not certified, and Milestones 04/05 are pending. The preceding browser gate is closed and does not require repetition.


## 2026-11-18 — Owner cross-series audit PASS; reversal source-state integrity extension

**Owner verified on configured local database `bluenew_clean_final`:** latest `PlotEntryWriterTest` **42 PASS / 275 assertions**; `PlotFullVoucherEditContractTest` **5 PASS / 88 assertions**, combined **47 PASS / 363 assertions**. PHP lint for PostingBatchIntegrityAudit, AuditPostingBatchIntegrity and PlotPurchaseReconciliation PASS. Read-only `php artisan finance:audit-posting-balances --project=3` returned **no imbalanced or invalid active posting batches**. This certifies the checked active batches in project 3, not every project or every voucher CRUD workflow. Previously owner confirmed all three Plot relationship browser scenarios PASS.

**Further implementation this turn (GitHub committed, new local gate pending):** Extended existing `PostingBatchIntegrityAudit` without schema changes to validate accounting reversal source integrity in addition to active posting totals: any batch with `reversal_of_batch_id` must refer to an existing, posted, non-reversal original in the same project, and carry voucher series `REV`; a `REV` batch with no original reference is flagged. This helps prevent cross-project or orphan reversal anomalies from appearing clean merely because debit and credit totals happen to balance. All checks are read-only; business rows unchanged.

**Regression:** Added a test to `PlotEntryWriterTest` that temporarily labels a valid balanced receipt batch `REV` without original linkage, checks that audit flags `REVERSAL_ORIGINAL_MISSING`, restores series, and verifies clean audit. Earlier Project 3 audit success predates this change and must not be represented as a retest of it.

**Owner verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances --project=3
```

**Milestone 03 remains PARTIAL:** pending comprehensive remaining voucher family Create/Edit/Delete and shared reporting checks; the new audit extends safety but does not implement those families. No new DB migration and no browser gate for this read-only change.


## 2026-11-18 — All-project posting audit default (owner correction)

**Owner local test gate PASS:** PlotEntryWriterTest 42 PASS / 278 assertions and PlotFullVoucherEditContractTest 5 PASS / 88 assertions, total **47 PASS / 366 assertions**. PHP lint PASS. `finance:audit-posting-balances --project=3` on the configured local database reported no active posting anomalies in project 3; that is a project-specific observation only. The owner then ran `finance:audit-posting-balances` and rightly received `Supply an explicit positive project ID`, contradicting the required default all-project behavior.

**Code fix committed:** `app/Console/Commands/AuditPostingBatchIntegrity.php` now treats `--project` as optional. Without it, audits every project ID from the **currently configured** database `projects` table plus any project IDs present in posted batches; with it, still audits only the requested ID. Results include `project_id` for each finding, the number of projects scanned, and the selected project (null if all) in JSON output. No project/database name hard-coded and no schema or data mutation. Existing balance/relationship audit service still enforces exact project scope per iteration.

**New isolated integration regression:** Covers the default all-project command for two configured test projects, detects a deliberately tampered voucher in the proper project, confirms an explicit other-project filter stays clean, and validates rejection of a malformed project ID.

**New owner local gate (NOT yet verified for this code change):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Console/Commands/AuditPostingBatchIntegrity.php
php -l tests/Feature/Finance/PlotEntryWriterTest.php
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances
```

`finance:audit-posting-balances --project=N` remains optional and read-only. Do not demand project 3 or a specific database name. Browser test and migration are not required for this command-only change. Milestone 03 cross-family CRUD acceptance remains pending.


## 2026-11-18 — Owner verified 48/48, six-project posting audit and shared Daily Voucher Directory correction

**Verified by owner:** `PlotEntryWriterTest` **43 PASS / 291 assertions**, `PlotFullVoucherEditContractTest` **5 PASS / 88 assertions**: **48 PASS / 379 assertions**, zero failures. PHP lint PASS. `php artisan finance:audit-posting-balances` checked **all 6 projects on current configured database**, zero imbalanced/invalid active posting batches. All-project default behavior is now verified and no per-project special casing required. Earlier Plot browser acceptance is still PASS.

**Next actual application code batch, GitHub committed (new local verification pending):**
- Fixed shared `DailyVoucherDirectoryController` Plot Receipt/Purchase listing to exclude logically archived source rows (`is_active=0` / `deleted_at` present), avoiding deleted vouchers showing as active records in the unified Daily Voucher Directory. Existing source and accounting evidence remains in storage.
- Fixed account filters in typed bank-transfer and Plot Receipt/Purchase directory sections to consider **only active finance posting lines**; previously an archived line could still match an account filter and produce misleading results after full voucher Edit.
- Fixed `postingLines()` used by the shared directory's voucher detail output to exclude inactive financial legs so retired credit/debit rows do not display as active. Project/account join constraints remain.
- Added `DailyVoucherDirectoryPostingLifecycleContractTest` to prevent regression of archived-source and inactive-line filters in both typed source listing and directory detail output. No new database tables, migrations, hard delete, or DB-name/project-specific behavior.

**New verification commands (not run locally by GitHub connector):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php -l app/Http/Controllers/Finance/DailyVoucherDirectoryController.php
php -l tests/Unit/Finance/DailyVoucherDirectoryPostingLifecycleContractTest.php
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=PlotFullVoucherEditContractTest
php artisan finance:audit-posting-balances
```

**Focused browser check required** because the unified Daily Voucher Directory listing/filter UI is affected: open that page in a disposable local project with an archived Plot Receipt/Purchase and an active edited voucher containing retired finance legs; confirm logically archived voucher is absent in active register, account filters ignore retired legs and voucher detail lines only show active posting legs. Previous Plot create/edit/delete browser tests need not be repeated.

**Reality check:** Milestone 03 is still partial across other voucher CRUD families (JV/CV, cash, stock, cheque/bank, extra charges), and Milestones 04 and 05 are not accepted. A clean balance audit does not prove those Create/Edit/Delete paths. Do not claim the entire remaining batch has been completed.


## 2026-11-18 — Owner verified Daily Voucher directory contract; JV/CV related-update hardening batch

**Owner verification carried forward:** new `DailyVoucherDirectoryPostingLifecycleContractTest` **1 PASS / 12 assertions**, Daily Voucher Directory PHP lint PASS. `php artisan finance:audit-posting-balances` checked **all 6 projects, zero imbalance/invalid active posted batches**. Prior Plot tests **48 PASS / 379 assertions** and all three Plot browser cases PASS. The new contract PASS does not certify the changed directory browser UI; its focused browser gate remains open.

**New code committed this batch:**
- Legacy JV and CV Update now call `VoucherUnchangedUpdateDetector` after locking current voucher/its details, before retiring related ledger rows. Identical repeated Save preserves same existing header, detail and active ledger IDs instead of creating another generation of financial/history rows. A changed date, reference, narration, account, side, amount or line order still invokes the existing audited transactional correction path. Validator also checks stored header debit and credit totals if available.
- Normal Commission draft `updateDraft` now applies the same no-op check before archiving active draft details and re-inserting replacements, preventing unnecessary duplicate history on identical repeated Edit. No-op keeps existing detail identities; material edits still archive/replace as before.
- Added nullable `ledgers.commision_voucher_detail_id` with a MySQL RESTRICT FK to `commision_voucher_details` via additive migration `2026_11_18_220000_link_commission_details_to_ledger.php`. Existing ledger rows and tables remain intact; old CV ledger rows with a NULL detail link are supported through existing exact account/amount matching. No V2 database isolation or database hard-coding. The down migration intentionally preserves the accounting link rather than dropping history.
- Legacy Commission Update now persists each new ledger leg's specific newly created commission detail ID (when column exists), matching JV detail linkage. `LinkedVoucherLedgerRetirement` validates the attached CV detail belongs to that voucher, is unique across active legs, and has the same account and debit/credit values, before any retirement. Legacy NULL details continue to validate through exact account/amount multiset.
- Added `VoucherUnchangedUpdateDetectorTest`, `CommissionVoucherDetailRelationContractTest`, and extended `LinkedVoucherLedgerRetirementTest` to cover exact CV detail IDs, swapped/duplicate detail links and legacy NULL fallback. New tests and migration have NOT been executed locally by GitHub connector. Do not claim this batch PASS before the owner runs it.

**New migration needed once on local DB before browser verification:**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/VoucherUnchangedUpdateDetector.php
php -l app/Services/Finance/LinkedVoucherLedgerRetirement.php
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l database/migrations/2026_11_18_220000_link_commission_details_to_ledger.php
php artisan test --filter=VoucherUnchangedUpdateDetectorTest
php artisan test --filter=CommissionVoucherDetailRelationContractTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan migrate --path=database/migrations/2026_11_18_220000_link_commission_details_to_ledger.php
php artisan finance:audit-posting-balances
```

**Acceptance:** after local tests and migration, test normal JV/CV and Commission draft Edit with identical Save twice, then actual value change, confirming no extra details or active ledgers on no-op; original IDs preserved. Milestone 03 overall remains PARTIAL: additional cash/bank/stock/extra-charge lifecycle and cross-module permission/browser acceptance are not yet complete. Milestones 04 and 05 pending. Do not re-run old Plot browser tests.


### Follow-up in same CV/JV edit safety batch

- Commission draft no-op guard now uses `array_key_exists` when resolving optional `reference` / `description`, so deliberately clearing a field to `null` is recognized as a real change rather than skipped.
- The Commission relation contract test was corrected to use literal PHP variable tokens in its static string assertions and now checks that nullable draft metadata is handled explicitly.
- No extra migration was added after the single additive `2026_11_18_220000_link_commission_details_to_ledger.php` migration listed above. The new code has not yet passed owner-local lint/test/migration gates; do not claim full Milestone 03 complete.


## 2026-11-18 — Commission Voucher draft/approval Ledger parity with JV (code batch; owner validation pending)

**User evidence:** Owner's local phpMyAdmin screenshot shows `commision_vouchers.id=1` in status `draft`, two active `commision_voucher_details` rows (1000 debit, 1000 credit), and zero `ledgers` rows for `type='CV'` / `type_id=1`. Code review confirms `CommissionDraftWorkflow::createDraft()` previously persisted only header + details; `CommissionPostingWriter::post()` posted to `finance_posting_lines` without creating legacy `ledgers` legs. This was a genuine functional gap relative to the requested JV-like three-table design.

**Implementation committed:**
- Added `CommissionLedgerSynchronizer` to mirror CV detail rows into `ledgers` with a **real enforced database FK** `commision_voucher_detail_id` (column added by the already owner-applied migration `2026_11_18_220000_link_commission_details_to_ledger.php`). MySQL `information_schema` and SQLite FK metadata checks refuse work when FK is not actually enforced. It validates the source project, exact voucher-detail ownership, account, amount and unique detail identity, never matching simply by row order.
- Draft Create now stores header + detail rows + one inactive (`is_active=0`) legacy ledger row per active detail in the same transaction. Draft Update keeps the same ledger and detail IDs on a no-op; a real detail edit archives old detail rows, retains their inactive ledger evidence, writes new details, and inserts new inactive FK-linked ledgers. Draft Cancel checks relationships and logically deletes source/details; no hard deletion of linked ledgers.
- Approve/Post uses existing immutable `finance_posting_batches` / `finance_posting_lines` writer, then validates/activates the exact current CV legacy ledger legs (`is_active=1`) in **the same transaction**. Retry does not duplicate the legacy ledger IDs. A failed posting or failed FK/amount integrity check rolls back approval and ledger transition together; a reversed CV cannot be reactivated through this path.
- Posted CV Reversal also deactivates that CV's FK-linked active legacy ledger legs in the same reversal transaction, retaining both posting and ledger history. This is required so legacy ledger balance readers don't keep counting a reversed CV.
- Added `finance:sync-commission-draft-ledgers` (default read-only preview, optional `--apply` to create only missing inactive rows for existing unposted drafts, across all projects in configured DB; `--project=N` optional). The already-existing CV ID 1 in screenshot can be repaired this way without any SQL inserts or hardcoded database names. The command acquires project/voucher locks, is idempotent per detail FK, and reports any integrity failure without cross-source mutation.
- Added isolated `CommissionLedgerSynchronizerTest` covering Draft Create, exact FK, no-op Edit, changed Draft Edit, activation after posted batch, duplicate prevention, reversal deactivation, existing draft backfill and command dry-run/idempotency, plus `CommissionLedgerActivationContractTest` for workflow integration. These new tests are **not yet locally verified**; do not mark them PASS until owner output is supplied.

**Owner commands (no new migration; previous Commission ledger FK migration already DONE):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/CommissionLedgerSynchronizer.php
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Services/Finance/VoucherLifecycleService.php
php -l app/Console/Commands/SyncCommissionDraftLedgers.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=CommissionVoucherDetailRelationContractTest
php artisan finance:sync-commission-draft-ledgers
php artisan finance:sync-commission-draft-ledgers --apply
php artisan finance:audit-posting-balances
```

**Browser acceptance to follow after green local tests:** On a disposable CV draft, Create should generate exactly two inactive FK-linked ledger legs; repeated unchanged Edit should not add rows; changed Edit should archive old details and create two new inactive ledger legs; after approval exactly current two become `is_active=1`, and repeating approval with the original request key must not add new rows. Existing Drafts can be synchronized via the opt-in command. A posted CV's *full amendment Edit* is still protected by the separate approved-posting reversal workflow; this batch aligns Draft Edit + Approve/Post + Reverse, not arbitrary mutation of an immutable posted financial batch.

**Milestone 03:** This targeted CV three-table lifecycle and FK requirement is code-implemented but owner-local tests/browser acceptance are still pending. Other voucher-family lifecycle completion and permission acceptance should not be marked complete based on these tests. Do not re-run the already accepted Plot browser gates.


## 2026-11-19 — JV/CV differential detail editing + Commission AJAX modal lifecycle (implementation; owner tests pending)

**Owner observation:** CV `type_id=1` accumulated 8 inactive `ledgers` rows across repeated Edit, with historical details 13/14,17/18,19/20 and current details 21/22. Existing changed Draft Update archived all old detail IDs and created replacements on every edit. The Commission edit iframe used a native `form.submit()` which left the modal open until manual page refresh. Owner supplied previous JV row-differential `detail_ids[]` Update/Destroy source as a design reference; its `saveQuietly()` existing-posting overwrite was intentionally NOT copied.

**Code implemented and committed:**
- Both JV and CV Edit forms now submit one hidden `detail_ids[]` per existing row, and a blank ID for each newly added row (inside the correct table cell). Both Update controllers validate ID array key alignment, nullable distinct positive IDs and normalize the empty debit/credit side to zero before validation. Non-owned/duplicate detail IDs are refused instead of guessed by account or row position.
- `CommissionDraftWorkflow::updateDraft()` now performs a **true row-identity differential** for unposted drafts only: unchanged existing rows keep exact detail + ledger IDs, changed existing rows update their still-inactive detail and FK-linked ledger in place, removed rows are soft-archived and old FK-linked ledger evidence remains inactive, new rows create new details and new inactive ledgers. All changes remain inside the project-locked DB transaction; `CommissionLedgerSynchronizer` validates the enforced FK and relationships before and after. Existing idempotent Draft Create/Approve/Reversal behavior unchanged.
- Legacy JV Update now validates strict same-voucher detail FK ownership, correct debit/credit and account for **only current active** ledger rows, skips identical rows and preserves their original detail/ledger IDs, archives changed/deleted source-detail+active-ledger rows, and creates fresh FK-linked detail+ledger rows for truly new or changed posting lines. Old accounting amounts are NEVER overwritten with `saveQuietly()`. Archived historical legs do not falsely block repeated edits. A whole voucher-header date/reference change replaces affected ledger evidence, preserving history.
- Commission Edit confirmation now submits via guarded jQuery AJAX, uses the existing `finance:commission-saved` parent event, automatically closes the parent modal, reloads available DataTables via AJAX, and falls back to automatic parent refresh if no AJAX table is present. Errors are shown via existing finance toast helper; no native form navigation on Save.
- New isolated `CommissionLedgerSynchronizerTest` cases cover existing detail-ID stability despite amount changes, adding new allocations, soft-archiving removed rows, correct inactive ledger FK, and rejection of foreign voucher detail IDs without mutation. New `VoucherDifferentialEditContractTest` verifies both forms/controllers and CV AJAX response; existing JV no-op contract was preserved.

**Previous verified owner gate:** `CommissionLedgerSynchronizerTest` 4 PASS / 22 assertions, `CommissionLedgerActivationContractTest` 1 PASS / 14 assertions, `CommissionVoucherDetailRelationContractTest` 2 PASS / 20 assertions, 3/3 local drafts synced, all six projects clean on read-only posting balance audit. THESE RESULTS PRECEDE THE CODE IN THIS SECTION.

**New owner verification (NOT executed for changes above):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php -l tests/Unit/Finance/VoucherDifferentialEditContractTest.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan test --filter=VoucherUnchangedUpdateDetectorTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan finance:audit-posting-balances
```

**Browser acceptance:** On a disposable JV and CV, Edit existing row amount with balanced other-side correction (existing IDs unaffected for unchanged rows), add one row, remove another, Save. Ensure only intended detail IDs remain active, new ledger FK identifies the actual created detail, old rows preserved where archival applies, and no cross-voucher mutation. Commission Edit modal must close automatically after success and the listing must refresh without manual reload. No new migration required: previous `commision_voucher_detail_id` FK migration already applied and validated. Posted CV remains immutable under the existing approval/reversal workflow.

**Milestone 03 overall remains partial until owner browser gates and remaining cash/bank/etc lifecycle acceptance.** Do not claim all modules complete based on this focused JV/CV batch.


## 2026-11-19 — Commission differential no-op regression corrected

Owner verification after JV/CV differential change: `CommissionLedgerSynchronizerTest` **1 FAILED / 5 passed** because the original no-op integration test called `updateDraft()` without persisted `detail_id` values. With the newer row-identity contract, a missing ID explicitly means a newly inserted row; it cannot be silently inferred from array position, account or amount. The application form already submits existing hidden `detail_ids[]` and a blank ID only for new rows. **Committed fix:** update the regression to submit the two actual persisted detail IDs, exactly as the browser does, and assert their detail and linked ledger IDs remain unchanged. Remaining owner tests were green: `VoucherDifferentialEditContractTest` 3 PASS / 28 assertions; `VoucherUnchangedUpdateDetectorTest` 3 PASS / 11; `CommissionLedgerActivationContractTest` 1 PASS / 14; `LinkedVoucherLedgerRetirementTest` 6 PASS / 17. All-project posting balance audit scanned 6 projects with zero findings. Corrected test requires new local run and has not yet been verified. No migration, business data mutation or browser retest required for this test-only correction.


## 2026-11-19 — CV Approve/Post 409 iframe error and missing Party Ledger activation

**Owner browser evidence:** CV Edit iframe's native Approve form produced HTTP 409 Laravel debug page `Commission approval rejected to protect accounting history`, with no toast/modal. Previously CV Draft creation and differential edits were accepted, ledgers existed with is_active=0. Approval had not completed; therefore draft CV postings do not yet appear in active-party ledger reporting.

**Implemented code fix (owner verification pending):**
- Removed `CommissionDraftWorkflow::approveAndPost` blanket conflict check that treated any JV `ledgers.type_id` matching the CV header ID or any CV detail row ID as an ambiguous posting. Different source tables legitimately overlap numeric IDs. Approval must validate the existing enforced `commision_voucher_detail_id` FK, source voucher/project and exact accounting evidence instead. `CommissionLedgerSynchronizer::assertDraftCanReplace` is used only when source status is draft; approved idempotent retry continues through posted batch replay and ledger activation checks.
- `CommissionVoucherController::approve` now returns structured success JSON on AJAX; handles domain integrity conflicts as HTTP 409 with safe, specific user-friendly messages, logs diagnostic error, and returns a safe JSON 500 response for unexpected failures rather than HTML exception inside iframe. Ordinary non-AJAX requests still redirect with errors.
- Commission Edit Approve form now has a dedicated jQuery AJAX handler with Accept: application/json, busy/disabled-state protection, existing skeleton, toaster and error popup. On success it emits `finance:commission-saved`, closes parent modal and reloads parent listing. On failure it displays the JSON message without navigating iframe into exception page. Fixed longstanding `projectID: 3` literal in CV Edit subaccount Ajax, replacing with the selected configured project ID.
- Added integration regression `CommissionLedgerSynchronizerTest::test_approve_succeeds_despite_unrelated_jv_numeric_identity_collision` using a real separate JV ledger with overlapping numeric CV identity. It executes Draft -> Approve/Post -> replay, verifies exactly two active FK-linked CV ledgers, two posting lines, one batch and no duplicated records. Added `CommissionLedgerActivationContractTest` assertions for collision removal, AJAX feedback, absence of HTML abort and selected-project dropdown.

**New local verification (tests not yet run on this code):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/CommissionDraftWorkflow.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan finance:audit-posting-balances
```

**Browser acceptance after tests:** In local testing project, open existing Draft CV, click `Approve saved voucher and post` once. Expect success toast, automatic modal close/list refresh; `commision_vouchers.status=approved`, exactly current two detail-linked ledger legs `is_active=1`, old archived legs remain inactive, finance posting batch balanced, Party Ledger can include account only if its account type is part of that report. Failed approval must show toast + popup, leave Draft status and inactive ledgers unchanged. Approval replay with same key must not create duplicates. Neither schema migration nor hardcoded project-specific DB operation is needed.

**Note:** Other JV/CV differential Edit tests were previously confirmed PASS (CommissionLedgerSynchronizerTest 6 PASS/31 assertions; VoucherDifferentialEditContractTest 3 PASS/28), and all-project posting audit found no issues in 6 projects. The approval fix and new test are additional code changes and have not been owner-verified yet.


## 2026-11-19 — Approved CV reopened after posting: read-only modal instead of HTTP 409

**Owner confirmed:** Commission approval is now successful; linked legacy `ledgers` active entries and Party Ledger visibility verified via browser. The next issue was opening Approved CV from the Commission Voucher register: `CommissionVoucherController::edit` explicitly aborted HTTP 409 and rendered Laravel's debug exception inside the modal iframe. This was a *read-route UX defect*, not a failure of the approved accounting posting.

**Code fix committed:**
- `CommissionVoucherController::edit` no longer throws 409 simply because an approved CV is selected; it loads the existing voucher and passes `readOnly=true` into the shared Edit/View Blade template for a posted typed CV. Other project scoping remains unchanged.
- Approved CV displays an explicit 'View only' explanation. All voucher form fields, row add/remove buttons and enhanced TomSelect dropdowns are disabled, and the Update and Approve buttons are absent. The server-side Draft-only mutation restriction is unchanged: a posted CV cannot be amended by forged PUT requests. Voucher header/detail/ledger records are never modified by merely opening the modal.
- Added `CommissionApprovedReadOnlyModalContractTest` to prevent reintroducing HTTP 409 in the approved read route and ensure non-editable controls are present in the Blade view. No database migrations or background repair required.

**Owner verification (new local gate pending):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Http/Controllers/CommissionVoucherController.php
php artisan test --filter=CommissionApprovedReadOnlyModalContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan finance:audit-posting-balances
```

**Browser check:** Open already Approved `CV-2026-0001` from Commission register: modal must show header and posted detail rows as View only, without Laravel error page or Save/Approve actions. Open a Draft CV: existing editable Add/Remove/Save/Approve flow must still work. Party Ledger approved CV entries already accepted by owner; no repeat posting needed. This batch does not permit posted vouchers to be directly re-edited; authorized correction/reversal remains separate.


## 2026-11-19 — Owner priority and handoff decision (authoritative)

**Roadmap revision:** six milestones, with Activity Audit as **Milestone 06 LAST**. Owner explicitly wants normal Create/Edit/Update/Delete for staff now, including already-approved CV corrections, not indefinite edit locks; preserve valid account relationships and no duplicate financial effect, but avoid customer-facing unnecessary reversal rows. Detailed user actions and affected table/PK before/after tracking will be implemented in Milestone 06, with proposed three new audit tables; those tables are NOT created now. Handoff/new chats must follow this order, avoid adding V2 isolation or hardcoded project/db names, use jQuery for custom frontend behavior, and keep milestone completion evidence truthful.

**Owner browser evidence (accepted):** CV Draft Create/Edit/Add/Remove with actual FK-linked ledgers works; CV Approve/Post now works; approved CV appears in Party Ledger, and approved CV opens in view-only mode without the earlier iframe 409. JV flow independently browser-accepted. Existing CV approved source is read-only by current implementation; **this does not satisfy the owner’s latest requirement**. Implement full approved CV differential edit/correction including original same CV ID/number, accounts, amounts, add/remove existing details, FK links to ledgers, posting-line source identity and consistent customer-facing balance **without duplicating financial impact**. Do not mark approved correction complete until code/test/browser evidence.

**Milestone 03 STATUS: PARTIAL (do not close prematurely).** Existing completed subdomains and owner validations are documented above. Other modules’ combined accepted CRUD/permission matrix is not established by current evidence; Milestones 04/05 remain pending.


## 2026-11-19 — Approved CV Full Edit/Update implementation (CURRENT CODE BATCH — owner local tests pending)

**Owner decision:** Activity Audit is the new sixth and LAST milestone, no activity tables/migrations now. Within current Milestone 03, the owner requires normal Create/Edit/Update/Delete even for Approved vouchers, avoiding needless customer-visible reversal documents for staff corrections. The owner confirmed CV approval, FK-linked active ledgers and Party Ledger display work, as did earlier JV/Plot owner browser checks.

**Actual code changes on upgrade/laravel-framework branch:**
- New `CommissionApprovedVoucherEditor` applies **same CV ID, same voucher number, same financial posting batch ID** corrections after project/source/batch locks, verified `commision_voucher_detail_id` actual enforced FK, exact source detail to active ledger and typed posting-line links, per-leg account/amount checks, positive balanced totals, and reversal-source conflict check. Existing unchanged rows preserve IDs; changed/removed rows are archived/inactivated and replacement detail+ledger+typed posting lines are inserted with exact FK identity. No new reversal batch and no duplicate active accounting amounts. This is a **controlled mutation of the current posted batch** (fingerprint and date updated), preserving old retired line evidence; it is not an immutable-posting reversal workflow.
- Original `CommissionVoucherController::edit` now allows Approved CV to open editable; already-reversed documents remain view-only. `update()` dispatches Approved CV to the new editor; Draft CV still uses `CommissionDraftWorkflow`. Existing jQuery AJAX modal Save, toast, auto close/refresh remain in place.
- `CommissionPostingWriter` idempotent replay now verifies **active** posting lines only and the corrected source fingerprint. Fingerprint is recalculated using persisted, PK-ordered active details (including nullable description values), matching replay format.
- `PostingReportReader` integrity validation, account statement/trial balance line query, original reversed-leg verification and empty-batch scan now correctly ignore archived posting lines, so customer-facing financial balances see only current active effects. Existing `PostingBatchIntegrityAudit` and Daily Voucher Directory already filter active legs.
- New SQLite integration coverage in `CommissionLedgerSynchronizerTest`: approved Edit changes amount/adds a row and retains unchanged IDs, preserves one posting batch and balanced current legs; no-change Save and approve idempotent replay add nothing; foreign detail ID refusal leaves records unchanged. `CommissionApprovedCorrectionContractTest` and revised previous `CommissionApprovedReadOnlyModalContractTest` guard controller dispatch, FK evidence, report read semantics and allowed Approved editing. **No owner green test results yet for this new batch**.

**Correction semantics deliberately chosen:** Draft rows can be edited in place while inactive. Approved posted changes archive the old leg evidence and create corrected FK-linked active legs; users see current corrected amount without a separate reversal transaction. This is a current-business correction, not global activity auditing, which is Milestone 06. Follow existing permission checks and keep the operation restricted to the selected project and original voucher; never use hardcoded project ID/database, hard deletes, or blanket foreign-ID guesses.

**Owner verification commands (NEXT REQUIRED GATE):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/CommissionApprovedVoucherEditor.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Services/Finance/CommissionLedgerSynchronizer.php
php -l app/Services/Finance/CommissionPostingWriter.php
php -l app/Services/Finance/PostingReportReader.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=CommissionApprovedCorrectionContractTest
php artisan test --filter=CommissionApprovedReadOnlyModalContractTest
php artisan test --filter=CommissionLedgerActivationContractTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan finance:audit-posting-balances
```

**Owner focused browser acceptance after green tests:** Choose a disposable Approved CV, open Edit, update a debit/credit pair with balanced total, add a new leg, remove an old leg, Save. Verify modal closes and list/Party Ledger reflect new net amount, exactly one source voucher ID and one posting batch remain, all current ledger legs active with exact detail FK, old legs inactive, unrelated vouchers untouched. Reopen and no-op Save must not increase records. No additional migration required on the already-migrated local CV FK + posting line lifecycle schema. DO NOT run this against real customer financial records without a controlled backup/acceptance window.

**Remaining Milestone 03 not yet accepted:** Cash CR/DR, CCR/CDR, BOR/BOP, SV, PPR, EC/ECP, labour/stock and other source families must get explicit project-scoped Add/Edit/Delete/approval/ledger accuracy reviews and owner browser acceptance; the all-project balance audit checks posted batches only, not every business workflow. Milestone 02 browser permission matrix is also not fully signed off. Milestones 04/05 are not started under latest rules. Do NOT declare entire Milestone 03 100% yet; close only after its actual remaining modules pass acceptance.

### New-chat handoff prompt

Continue BlueMarketing GitHub `Bilal6230/blueMarketing` branch `upgrade/laravel-framework`, configured local project `C:\laragon\www\blueMarketing`. Follow `docs/README.md` as sole authoritative handoff, and this last section as the latest code/verification batch. User wants **actual committed/pushed code, not docs-only work**; deliver complete batches and exact PowerShell pull/tests. Custom frontend JS must use jQuery, global consistent AJAX/skeleton/toasts/modals, no V2 names/isolation or hardcoded project/database. Roadmap is SIX milestones: M01 complete; M02 partial permission acceptance; M03 currently active remaining voucher lifecycle, approved CV correction newly implemented **not yet locally verified**; M04 bank/cheque linked cascade pending; M05 reversal settings/regression pending; M06 centralized activity audit LAST and NOT started (proposed 3 audit tables). Owner verified CV approval/Party Ledger, existing CV draft and JV tests, 6-project posting audit. Next action: request/interpret local test and browser gate above, fix any failures, then finish remaining M03 modules with genuine code and coverage; do not misstate completion.


### Final integrity hardening for approved CV correction (same implementation batch)

- Approved CV editor now rejects any existing active financial batch with a mismatched original SHA-256 source fingerprint, date or voucher series **before** mutating source/detail/ledger/posting rows; this prevents silently overwriting pre-existing tampered history.
- It also rejects any unmapped active posting lines in the source batch, even if the mapped detail count otherwise looks correct. It requires the real database-enforced `ledgers.commision_voucher_detail_id` FK and project scoping. The old posting rows are deactivated, never hard-deleted.
- Local PHP lint, feature tests, owner browser acceptance and full remaining milestone 03 multi-voucher completion are STILL outstanding as of this commit. Code-review-only and contract tests are not proof of browser acceptance.


## 2026-11-19 — Latest owner PASS and remaining Milestone 03 bank voucher CRUD implementation (THIS IS THE CURRENT HANDOFF)

**Owner acceptance already confirmed for approved CV full differential Edit:** local `CommissionLedgerSynchronizerTest` **9 PASS / 51 assertions**, `CommissionApprovedCorrectionContractTest` **1 PASS / 17**, `CommissionApprovedReadOnlyModalContractTest` **1 PASS / 16**, `CommissionLedgerActivationContractTest` **2 PASS / 27**, `VoucherDifferentialEditContractTest` **3 PASS / 28**. Total **16 PASS / 139 assertions**, all PHP lint and Blade view caching successful. Owner also explicitly confirmed approved CV full Edit **browser PASS**. All-project posting audit checked 6 projects and returned zero findings. Plot/JV earlier browser checks accepted; **do not require them again without code changes**.

**Actual new code committed for Milestone 03 this turn (LOCAL TESTS AND BROWSER ACCEPTANCE STILL PENDING):**

1. **BD/BW manual bank voucher same-ID Edit:** new `BankTransferEditor` updates the selected project's existing `finance_bank_transfer_sources` and `finance_posting_batches` without a second batch. It locks the source, batch and exact two active bank/cash posting legs, checks account/project mapping, bank direction, balances, original source SHA256 fingerprint, and absence of a posted reversal. Changed active legs are archived with `is_active=0` and two corrected active legs are inserted under the same batch; no-op Edit preserves all IDs. Multi-allocation historical transfers are refused until they have stable per-line source FKs; no index guessing or unrelated mutation.
2. **BD/BW independent ordinary Delete:** New non-destructive migration `database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php` adds `is_active` and `deleted_at` to the **existing** `finance_bank_transfer_sources` table; no new table or hard deletion. `BankTransferEditor::delete()` locks and validates the exact source, amounts and fingerprint; logically archives it and retires its two active legs without a customer-visible REV batch. Original batch, source, FK links and retired posting legs remain in the database. Idempotent Create replay and separate Reverse reject archived sources.
3. **Project-scoped UI/API:** new GET Details, PUT Update and DELETE endpoints for `finance/voucher/bank-transfer/{kind}/{postingBatch}`; registered with `FinanceActionPermission` as Edit/Delete. `bank_transfer_workspace.blade.php` now offers Edit and Delete for eligible manual bank transactions using the existing modal, jQuery AJAX, toast/error display, confirmation, no full-page refresh, and live history reload. Action-specific bank Edit/Delete permissions are checked server-side; linking cleared cheque/source remains a separate Milestone 04 custody process.
4. **Correct reports and lifecycle:** Bank history, printed vouchers, typed/unified Daily Voucher Directory, `BankTransferReconciliation`, `PostingReportReader`, and `PostingBatchIntegrityAudit` now use only active posting legs and exclude logically deleted bank source roots. All-project audit separately reports `ARCHIVED_BANK_WITH_ACTIVE_POSTINGS` when archived bank evidence is corrupt (unexpected active legs); customer report fails closed instead of displaying wrong amount. Plot Receipt shared-directory print options now ignore archived allocations.
5. **Regression tests:** `BankTransferWriterTest` now has same-batch Edit/re-edit/no-op, project isolation, reversed-source refusal, ordinary Delete preserving source/posting history and hiding customer statement, archive replay refusal, and archived source tamper detection. `BankTransferCorrectionContractTest` guards routes, granular permissions, modal jQuery AJAX and active-only bank reporting. Older tests are retained. **Never claim these passed without local owner output.**

**Owner verification:**

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache

php -l app/Services/Finance/BankTransferEditor.php
php -l app/Services/Finance/BankTransferReconciliation.php
php -l app/Services/Finance/PostingReportReader.php
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l app/Http/Controllers/Finance/VoucherController.php
php -l database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php
php -l tests/Feature/Finance/BankTransferWriterTest.php
php -l tests/Unit/Finance/BankTransferCorrectionContractTest.php

php artisan test --filter=BankTransferWriterTest
php artisan test --filter=BankTransferCorrectionContractTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=PlotEntryWriterTest

php artisan migrate --path=database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php
php artisan finance:audit-posting-balances
```

Run migration only against the intended configured local TEST database, after tests pass; do not use `migrate:fresh`, and don't hardcode a database or project ID.

**Browser acceptance still required for NEW BANK changes:** On disposable manual Bank Deposit and manual Bank Withdrawal vouchers, Edit existing amount/date/cash/bank/narration, confirm modal closes, refreshed history/print and account balance reflect corrected amount. Reopen and Save unchanged (no extra posting). Ordinary Delete -> disappears from active lists and balances, no REV voucher, original rows remain archived. Test linked-cheque withdrawal Edit/Delete refusal without touching custody; that linked-cascade workflow is intentionally Milestone 04. Do not re-test approved CV/Plot/JV if nothing relevant changed there.

**Milestone 03 honest status:** **NOT YET CLOSED.** All-project posting integrity checks alone do not prove end-to-end CRUD. Remaining work includes comprehensive original CR/DR/CCR/CDR/BOR/BOP, PPR, EC/ECP, stock entry/bill/SV and labour payment CRUD/corrections, relationship-safe customer ledger effects and permission/browser acceptance. Some of these have existing partial routes; inspect the exact active endpoints before changes. User wants this work completed as rapidly as safely possible; deliver real code, not docs-only updates. Milestone 04 = cheque/online linked cascade; Milestone 05 = optional reversal settings; **Milestone 06 = centralized app Activity Audit LAST, deferred** (no audit tables yet).

### Updated fresh-chat handoff (highest priority)

Continue the same BlueMarketing development on GitHub `Bilal6230/blueMarketing`, branch `upgrade/laravel-framework` and local `C:\laragon\www\blueMarketing`. Configured DB comes exclusively from `.env`; no project 3 hardcode, no V2 isolation/names, no custom vanilla JS (use jQuery), consistent AJAX + toast/modals/skeleton/filters. **Owner verified CV approved Edit and Party Ledger, JV and Plot browser checks, and all-six-project audit.** Latest bank transfer same-ID Edit/Delete is code-committed but has unverified local tests and new additive lifecycle migration; read the last section and run its exact commands, fix failures, get focused browser PASS. Then finish remaining Milestone 03 voucher families (CR/DR/CCR/CDR/BOR/BOP/PPR/EC/ECP/stock/labour), not just Plot/JV/CV, and only mark COMPLETE after verified matrix. Roadmap: M01 complete, M02 permission browser partial, M03 active partial, M04 linked bank cascade pending, M05 reversal setting pending, M06 Activity Audit LAST, explicitly deferred. Keep a single authoritative `docs/README.md` and commit/push real changes; never promise future/background work. 


## 2026-11-19 — Milestone 03 continuation after owner BD/BW green gate: posted JV/SV correction, ordinary JV/CV Delete, active source filters (LATEST HANDOFF)

**Owner-confirmed local PASS before this new batch:**
- `BankTransferWriterTest`: **11 PASS / 72 assertions**, including BD/BW same batch Edit, ordinary archive/Delete, remnant integrity, reconciliation and linked-cheque refusal.
- `BankTransferCorrectionContractTest`: **2 PASS / 30 assertions**.
- Finance posting read-only audit: **all 6 projects clean**.
- Previously confirmed: Approved CV differential full Edit 16 PASS / 139 assertions and browser PASS, along with JV legacy and Plot full Edit browser acceptance.
- `database/migrations/2026_11_19_110000_add_bank_transfer_lifecycle_columns.php`: owner local migration successfully applied.
- Old `database/finance_legacy_migrations/2026_11_05_000200_create_finance_bank_transfer_sources.php` fixture was corrected to use `FinanceConnectionGuard`; **all 11 test failures resolved and rerun PASS**. No further V2 class reference in that fixture.

**Additional real application code committed THIS BATCH — LOCAL TESTS/BROWSER PENDING:**

1. **Global Cash active ledger account/detail correctness:** `DailyVoucherDirectoryController` now matches active `customer_ledger` records only to active `ledgers` legs when reading current details and filtering by account. Archived historical legs from previous edits cannot cause an old account to appear as the voucher's current account. Inactive rejected/pending source history is still readable. Added tests to `DailyVoucherDirectoryPostingLifecycleContractTest` to cover this, plus deleted BD/BW visibility.
2. **Posted JV/SV full differential Edit:** New `JournalApprovedVoucherEditor` verifies source/project/posting-batch uniqueness, enforced posted-line-to-detail IDs, original posting SHA256, exact account and debit/credit values, optional legacy JV ledger FK ownership, absence of a reversal and pending approval, and prevents foreign detail IDs. Unchanged rows preserve detail and posting line IDs; changed/removed rows archive old evidence, new current lines are inserted with source FK within the SAME batch and voucher ID/number. Same-ID no-op adds nothing. The typed `JournalPostingWriter` idempotent replay now checks only active posted lines, so old retired legs do not cause duplicates. `JournalVoucherController::edit/update` routes already-posted JV/SV through this editor instead of an iframe 409/immutable-source rejection. Already-reversed sources still reject corrections. Added integration regressions to `JournalPostingWriterTest` and new `JournalApprovedCorrectionContractTest`.
3. **Approved JV/SV/CV ordinary Delete (separate from Reverse action):** New `PostedVoucherArchiver` validates the original source's SHA256 fingerprint, owned typed batch, active financial lines, per-detail FK ownership, account amounts and optional/current ledger FKs. On normal Delete it sets active posting/legacy ledger lines inactive and soft-archives original header and details, all inside one transaction. It creates NO separate REV batch; exact historical rows and original batch remain stored. `JournalVoucherController::destroy` and `CommissionVoucherController::destroy` default to ordinary logical Delete for posted vouchers; explicit `reverse=1` retains the older reversal path when legitimately required. Pending approval decisions and unrelated/cross-project IDs refuse this transition. Added `PostedVoucherArchiveContractTest` and JV/CV integration tests.
4. **Customer-report integrity:** `PostingReportReader` and `PostingBatchIntegrityAudit` exclude intentionally archived source batches from live balances/empty-batch errors, but detect and reject any active posting leg left attached to an archived JV/SV/CV source. For BD/BW the previously verified active-only/archived lifecycle remains unchanged. All report endpoints sourced through `PostingReportReader` inherit this behavior.
5. **No additional database migration added in this newest batch**. The existing finance posting and JV/CV detail linkage migrations are prerequisites.

**Owner next verification (new code NOT YET RUN on their local environment):**

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/JournalApprovedVoucherEditor.php
php -l app/Services/Finance/PostedVoucherArchiver.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Services/Finance/JournalPostingWriter.php
php -l app/Services/Finance/PostingReportReader.php
php -l app/Services/Finance/PostingBatchIntegrityAudit.php
php -l tests/Feature/Finance/JournalPostingWriterTest.php
php -l tests/Feature/Finance/CommissionLedgerSynchronizerTest.php
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=PostedVoucherArchiveContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=VoucherDifferentialEditContractTest
php artisan test --filter=BankTransferWriterTest
php artisan finance:audit-posting-balances
```

**Focused new browser acceptance after GREEN tests:** On a disposable/local posted JV (and any typed SV), Edit an amount/line/account with balanced totals, Add and Remove a detail row and Save, confirm same voucher number and corrected account statements and unchanged row IDs; re-open/no-op Save should add no posting legs. Delete a disposable Approved CV or posted JV and confirm active register/Party Ledger/Trial Balance no longer includes its amount, but historical source/ledger/posting rows remain archived and no REV voucher appears. Confirm no cross-project writes and proper permission-denied JSON/toast. The previously accepted CV full Edit and BD/BW unit tests do not require re-testing unless regression is observed.

**Honest Milestone 03 status: ACTIVE — NOT CLOSED.** New typed JV/SV and JV/CV Delete batch has not yet passed owner local tests or browser acceptance. Stock Entry/Stock Bill/SV inventory-linked corrections, labour payment source, PPR/EC/ECP and some approved bank/cheque instrument corrections still require full source-to-ledger/account dependency mapping, user-facing Edit/Delete path and tests. Stock CRUD is not safely addressable by deleting source alone: there are project balances, linked stock movements, inventory lines, stock financial JV/customer ledger source and billed-entry dependencies. Do not mark these complete without coordinated writes and validation. Some cheque/BOR/BOP linked custody corrections are assigned to Milestone 04, not to bypass/disable. Milestone 06 Activity Audit is explicitly last; no new activity audit tables now.

**New-chat handoff:** Continue `Bilal6230/blueMarketing` branch `upgrade/laravel-framework`, configured `C:\laragon\www\blueMarketing`, no V2 database isolation, no hardcoded DB or project ID. Use jQuery for authored browser script, unified AJAX/toast/skeleton/modal conventions. Owner insists *complete actual code* not documentation-only and expects pull/tests per batch. Treat latest owner Bank tests and original CV/Plot/JV acceptance as already passed. First action: run/repair new typed JV & archive regression gates above, then deliver remaining Milestone 03 modules with explicit CRUD matrix and relationship-safe edits, followed by Milestones 04/05. Six-milestone roadmap: 01 complete, 02 partial acceptance, 03 active partial, 04 linked bank custody pending, 05 reversal settings pending, 06 Activity Audit deferred last.


## 2026-11-19 — Labour payment exact source FK + remaining legacy migration-name cleanup (LATEST)

**Implemented, owner local verification pending:**
- `database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php` adds nullable enforced `labour_ledgers.customer_ledger_id` FK to `customer_ledger.id` (restrict physical delete). Historic labour ledger rows stay NULL unless their exact source evidence can be established; no amount/date/number-based backfill guessing, no data deletion.
- Existing `LabourController::labourPayment` now records newly created labour detail row IDs in the original transaction, creates the customer-payment voucher, then binds exactly those IDs to its real `customer_ledger_id` while enforcing the selected project and confirming all rows were linked. If any insert/update fails, the transaction rolls back original labour rows, source and ledger as one. New payment submissions now require this additive migration before writing. `LabourLedger::customerLedger()` relationship provided. Removed exposed PHP `file`/`line` exception metadata from its browser-facing 500 JSON, keeping detailed server log.
- New `LabourPaymentSourceRelationContractTest` validates the migration, FK link and error JSON no-stack-trace contract. **The complete Labour Payment Edit/Delete lifecycle is STILL NOT DONE**; separate attendance-to-payment/member settlement identities must be reconciled before ordinary correction.
- Legacy finance fixture migrations using the removed `V2IsolatedConnectionGuard` class (`2026_10_31_000110` commission detail links, `2026_10_31_000120` stock project approvals) now use the configured `FinanceConnectionGuard`; the old finance posting core and bank lookup fixture migrations no longer impose `bluemarketing_v2_*` database-name allowlists. Existing schema requirements and refuse-overwrite checks remain. These fixtures are **NOT** normal production migrations, should never be manually replayed onto an existing populated ERP.
- The separate historical `2026_10_30_000020_create_finance_legacy_domain_baseline.php` 56-table bootstrap remains **retired/quarantined**, not safe to apply to existing DB, regardless of its isolation/name guard. It must not be part of a normal `php artisan migrate` deployment. Do NOT remove its overwrite protection and run it on live tables.
- On explicit `reverse=1`, both JV and CV posted Delete endpoints now require independent `finance jv reverse` / `finance cv reverse` permission, not only the route's ordinary Delete grant. New contract assertions added.
- Updated `JournalPostingWriterTest` local in-memory fixture with real active head/subhead project account relationships so typed JV integration tests exercise the actual account availability gate.

**Do NOT mark Milestone 03 complete:** Bank BD/BW 13 PASS/102 and CV full Edit 16 PASS/139 plus earlier browser acceptance are real owner results, but all new posted JV/SV Edit, JV/CV logical Delete, Labour FK migration and unified migration fixture changes require owner local tests. Remaining Stock Entry/Bill actual full inventory/ledger CRUD, Labour Payment full correction, PPR/EC/ECP charge/payment/receipt semantics and bank-instrument linked custody remain unresolved. Dependencies must be handled by exact FKs, not arbitrary blocking by name or blindly deleting old rows. M04 cheque/online linked cascades and M05 reversal configuration are later; M06 complete Activity Audit LAST and NOT YET STARTED.

**Next local verification (only after pulling latest):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/JournalApprovedVoucherEditor.php
php -l app/Services/Finance/PostedVoucherArchiver.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Http/Controllers/LabourController.php
php -l app/Models/LabourLedger.php
php -l database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
php -l tests/Feature/Finance/JournalPostingWriterTest.php
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=PostedVoucherArchiveContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=LabourPaymentSourceRelationContractTest
php artisan test --filter=FinanceCoreMigrationUnifiedConnectionContractTest
php artisan test --filter=OriginalVoucherWorkflowHttpTest
php artisan finance:audit-posting-balances
# ONLY on the intended local TEST DB once tests are clean:
php artisan migrate --path=database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
```
**New local/browser acceptance pending:** test posted JV/SV Add/Remove/Edit, same-ID no-op and no duplicate batch; JV/Approved CV ordinary Delete preserves inactive FK-linked historical evidence without REV and removes current balances; labour source rows link to the payment voucher after migration. Reuse previously accepted CV/Plot/BD/BW tests; don't request unnecessary re-tests.

**Fresh chat priority:** Start from this section in `docs/README.md`, connected repo `Bilal6230/blueMarketing` branch `upgrade/laravel-framework`. Never claim the whole milestone closed. After fixing any new local regression failures, implement remaining Stock Entry/Bill, Labour Payment, PPR/EC/ECP full CRUD with stock balances/movements, source-to-ledger and custody exact relationships. Commit actual code, no docs-only round; preserve six-milestone roadmap and defer Activity Audit to final M06.


### Final exact-FK hardening — posted JV/SV and JV/CV logical Delete

- The posted `JournalApprovedVoucherEditor` now discovers any historical active `ledgers` by their **real `journal_voucher_detail_id` FK**, not merely matching `ledgers.type_id` numeric values. A different JV/SV sharing an integer `type_id` is not proof of ownership; if a row actually has this detail FK but the wrong voucher type/header, the correction fails without writes.
- `PostedVoucherArchiver` uses the same exact-FK approach for JV/SV and enforced `commision_voucher_detail_id` for CV. Unrelated numeric collisions are not archived; incorrectly attributed real FKs fail closed.
- Typed Posted JV/SV / ordinary Posted JV/CV Delete / migration normalization / new Labour source FK tests in the above commands remain **locally unverified**, so no milestone closure claim yet.

**Verification gate for this latest set (run tests before applying the new labour FK migration to the configured local TEST DB):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Services/Finance/JournalApprovedVoucherEditor.php
php -l app/Services/Finance/PostedVoucherArchiver.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/CommissionVoucherController.php
php -l app/Http/Controllers/LabourController.php
php -l database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=PostedVoucherArchiveContractTest
php artisan test --filter=DailyVoucherDirectoryPostingLifecycleContractTest
php artisan test --filter=LabourPaymentSourceRelationContractTest
php artisan test --filter=FinanceCoreMigrationUnifiedConnectionContractTest
php artisan test --filter=OriginalVoucherWorkflowHttpTest
php artisan finance:audit-posting-balances
php artisan migrate --path=database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php
```
Do not run the migration before validating that you're on the intended local test database. Owner does not need to rerun already-accepted browser CV and BD/BW unless there is an actual regression. Explicit posted JV/SV and posted JV/CV Delete browser gate is still pending.

**Legacy fixture rollback safety (2026-11-19):** Historical bank lookup fixture `database/finance_legacy_migrations/2026_10_30_000010_create_finance_bank_lookup.php` now refuses destructive `down()`. Removing its old DB-name allowlist must never make an accidental rollback drop real ERP `banks` master data. The file is not part of normal automatic production migrations. Do not run historical fixture migrations against a populated ERP database.


### Stock physical source-line relationship step — implemented, owner test pending

- Added additive migration `database/migrations/2026_11_19_130000_link_stock_movements_to_physical_source_lines.php`: nullable `stock_movements.stock_entry_line_id` FK to `stock_entry_lines.id` and nullable `stock_movements.stock_bill_line_id` FK to `stock_bill_lines.id`, each with RESTRICT on physical parent deletion. Existing `source_type`/`source_id`/`source_line_id` polymorphic fields are retained for compatibility; no guessed/backfilled historical relationship in the migration.
- `StockController::storeEntry/storeBill` now writes the **actual physical detail ID** to the corresponding exact FK whenever the migration is installed, alongside existing polymorphic source identity, in the same inventory source/movement/ledger transaction. Existing un-migrated test fixtures continue to use the old columns until the additive migration is installed.
- New `finance:link-stock-movement-sources` command defaults to READ-ONLY. It audits existing movement FKs using physical source line ID + source header ID + project + item + qty + rate + amount. Only explicitly running `--apply` writes verified exact links; ambiguous/invalid links are counted as errors, never silently guessed. No financial postings, inventory balances or stock header/detail source amounts are modified by this command. New `StockMovementExactSourceFkContractTest` and `StockMovementSourceLinkAuditContractTest` added.
- **Important:** This fixes source relationship attribution, NOT complete Stock Entry/Stock Bill correction CRUD. Correcting quantities or deleting posted stock also requires synchronized stock balance adjustments, movement lifecycle, linked bills and customer/typed-JV posting adjustments, and must not simply delete header/rows.

**Additional focused local test/migration gate (configured local TEST DB only):**
```powershell
php -l app/Console/Commands/LinkStockMovementSources.php
php -l app/Http/Controllers/StockController.php
php -l database/migrations/2026_11_19_130000_link_stock_movements_to_physical_source_lines.php
php artisan test --filter=StockMovementExactSourceFkContractTest
php artisan test --filter=StockMovementSourceLinkAuditContractTest
php artisan test --filter=StockDeletionIntegrityTest
php artisan migrate --path=database/migrations/2026_11_19_130000_link_stock_movements_to_physical_source_lines.php
php artisan finance:link-stock-movement-sources
# Review read-only results first. DO NOT automatically run --apply on ambiguous rows.
```


## 2026-11-19 — Milestone 03 continuation after Bank tests PASS: JV/SV route and source lifecycle repairs

**Owner confirmed green new bank test gate:** `BankTransferWriterTest` **11 PASS / 72 assertions**, `BankTransferCorrectionContractTest` **2 PASS / 30 assertions**. READ-ONLY all-project posting integrity audit on all 6 projects: **zero issues**. The earlier legacy bank fixture migration `database/finance_legacy_migrations/2026_11_05_000200_create_finance_bank_transfer_sources.php` still referenced deleted `V2IsolatedConnectionGuard` and failed all 11 tests; this was fixed in commit `d129702c` by switching to `FinanceConnectionGuard`. **BD/BW browser acceptance is not documented as PASS**, do not claim it passed.

**Actual additional code changes in this turn:**
- Fixed a production JV/posted SV Update contradiction: `JournalVoucherController::update` had an unconditional 409 `abort_if(isMergedPostedJournal)` *before* its existing safe `JournalApprovedVoucherEditor::update` branch. Removed the unreachable 409 so posted JV/SV can actually use same-ID, exact-detail FK, original posting batch correction. Extended `JournalApprovedCorrectionContractTest` to assert that no early 409 precedes the editor. This change requires **fresh local tests/browser check on an approved typed JV**, even though prior owner accepted legacy JV.
- **SV production Create/Save**: Added `sales.voucher.create` and `sales.voucher.store` routes. Sales listing now shows its New Voucher button to users with `finance sv create`; shared modern journal form uses the SV action, preview and return URL. `JournalVoucherController::store` uses validated balanced journal lines, durable request key, project-scoped auth, refuses unsupported direct-post approval configuration, dispatches `OriginalJournalWorkflow::createAndPost(...,'SV')`. `OriginalJournalWorkflow` now supports both JV (default unchanged) and SV: project-locked `nextSalesVoucher()`, actual `journal_vouchers.type='SV'` parent and `journal_voucher_details` rows, `ProjectVoucherDisplayIdentity::assign`, `PostingGateway::postSalesVoucher` exact typed link and idempotent replay. Added `SalesVoucherFullLifecycleContractTest` and an actual in-memory SQLite `JournalPostingWriterTest::test_sales_voucher_create_uses_own_counter_and_retries_without_new_source`.
- **SV Update/Delete for existing legacy sources**: `JournalVoucherController::update/destroy` now keeps the actual legacy voucher series for `SV` instead of restricting to `JV` or constructing `ledgers.type='JV'`. `LinkedVoucherLedgerRetirement` supports `SV`, while strict `journal_vouchers.type='SV'` owner verification refuses cross-series collisions. A new SQLite regression checks both JV/SV/CV retirement and refuses a JV-header pretending to own an SV ledger. The safe posted typed SV path already uses `JournalApprovedVoucherEditor` and `PostedVoucherArchiver`; added integration test for posted SV correction and delete without a second financial batch.
- **Cash/PPR/EC/ECP replay reader**: `CashPostingWriter` now checks only active financial legs on idempotent replay, so historical inactive correction lines cannot be mistaken for current posting rows. No new migrations for SV/JV/cash code.

**Owner verification gate (new changes UNVERIFIED locally):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Services/Finance/OriginalJournalWorkflow.php
php -l app/Services/Finance/LinkedVoucherLedgerRetirement.php
php -l app/Services/Finance/CashPostingWriter.php
php -l tests/Feature/Finance/JournalPostingWriterTest.php
php -l tests/Feature/Finance/LinkedVoucherLedgerRetirementTest.php
php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=SalesVoucherFullLifecycleContractTest
php artisan test --filter=CashPostingWriterTest
php artisan test --filter=BankTransferWriterTest
php artisan finance:audit-posting-balances
```

**Targeted browser check after local green:** New SV form -> Save balanced lines -> one SV row in sales list and two real posting lines; Edit amount and add/remove rows -> same source/batch and correct report balance; Delete -> archive original source/posting legs without customer-facing REV document. Typed posted JV Edit must open and Save rather than HTTP 409; no-op Edit shouldn't add rows. Repeat-safe requests must not create duplicate SV. All changes must operate only under configured project and account relations. Do not use production customer data for exploratory correction.

**Milestone 03 still NOT CLOSED as of this turn:** PPR/EC/ECP original-source CRUD and downstream booking/extra-charge relationships remain unimplemented/partially implemented; Stock Entry/Bill amount/quantity correction and Delete, labour payment correction, approved BOR/BOP custody changes (Milestone 04 linked-source dependency), and full browser/permission matrix acceptance not proven. Owner requested the entire milestone complete; it would be inaccurate to claim completion based on these partial paths. **Milestone 06 centralized user activity audit remains LAST, NOT started.** Project source of truth is this `docs/README.md`; follow this last entry in fresh conversations.

### Next-chat handoff continuation prompt

Continue repo `Bilal6230/blueMarketing` branch `upgrade/laravel-framework` (local `C:\laragon\www\blueMarketing`). Owner demands actual complete Milestone 03 with every voucher's Create/Edit/Delete and exact FK/accounting relationships; do not stop at docs. The latest verified output is BD/BW 13 PASS/102 assertions and six-project audit clean; previous JV/Plot/CV browser accepted. New code added SV production Create, SV legacy and typed Update/Delete, and removed a real unreachable posted JV 409; tests/browser still need local verification. Run exact gate in last docs section and fix any failures. Remaining PPR/EC/ECP, Stock Entry/Bill, labour, CR/DR/CCR/CDR/BOR/BOP end-to-end acceptance still missing; check existing `SameSourceCashVoucherEdit` before duplicating functionality, and handle cheque/online custody (Milestone 04) without corrupting linked sources. Use existing unified DB and jQuery AJAX/toasts/skeleton, no separate V2 names or hardcoded project/database. Roadmap is six milestones; activity audit is Milestone 06 LAST and deferred. Do not claim done without test/browser evidence.


### 2026-11-19 — Final SV common-entry UI and cash replay regression additions

- The shared `resources/views/admin/finance/voucher/daily_directory.blade.php` voucher type chooser now exposes SV New Voucher (gated by `finance sv create`) and routes it to the dedicated SV Create endpoint. The Sales Voucher list's New Voucher button likewise checks granular create permission.
- `CashPostingWriterTest::test_cash_replay_ignores_archived_posting_history_but_checks_current_balanced_legs` added: physically archived inactive old line + active replacement must replay idempotently without a second batch. This covers CR/CP/PPR/EC/ECP writer's active-only balance replay.
- **SV local tests, new integration fixture and browser acceptance are not yet performed**. Before reporting 'SV complete', owner must run `JournalPostingWriterTest`, `LinkedVoucherLedgerRetirementTest`, `SalesVoucherFullLifecycleContractTest`, `JournalApprovedCorrectionContractTest` and `CashPostingWriterTest`, and exercise SV New/Edit/Delete plus approved typed JV Edit in the browser.
- **This turn is NOT full Milestone 03 closure.** Stock Entry/Bill financial/inventory lifecycle, labour voucher corrections and PPR/EC/ECP linked business corrections still require exact source/ledger/downstream mapping; do not implement blanket hard-delete or silent direct-row amount changes without balancing inventory/booking/cheque evidence. M04 linked cheque/online cascade remains separate; M06 activity audit remains deferred last.


## 2026-11-19 — Milestone 03 large additional code batch (LATEST AUTHORITATIVE HANDOFF)

**Owner request:** Continue until ALL remaining M03 original voucher Create/Edit/Update/Delete and source/ledger foreign keys resolved. Never hide financial mutations, hard-delete posted history or create unnecessary customer-facing reversals for a routine staff correction. Activity Audit is M06 LAST and explicitly deferred; no new activity tables added.

**Verified BEFORE this latest code batch:** BD/BW bank transfer feature tests **11 PASS/72 assertions** + contract **2 PASS/30**, six-project read-only posting audit clean; approved CV differential correction **16 PASS/139 assertions plus browser PASS**; previous JV and Plot browser acceptance. No NEW Stock, Labour, EC/ECP, PPR or SV local green acceptance exists yet.

### Newly committed source and UX implementations (code-complete portions only)

**JV/SV:** Removed an unreachable prior HTTP 409 in Journal Update so posted JV/SV can reach `JournalApprovedVoucherEditor`. New independent `sales.voucher.create/store` route and shared jQuery modal creation under `finance sv create`, SV-specific project voucher counter `nextSalesVoucher`, exact journal detail->posting source FKs, typed `postSalesVoucher`, idempotency request key. SV legacy Edit/Update/Delete now use `SV` not mistaken `JV` ledgers in `LinkedVoucherLedgerRetirement` and controller. Added SV feature/contract tests, shared daily directory SV chooser. `CashPostingWriter` and `ApprovedSourcePostingWriter` idempotent replay now compare **active** posting legs only (retired correction legs do not inflate balances).

**Cash PPR / BookingVoucher:** New `BookingReceiptCashEditor` provides same-ID cash PPR Edit and logical Delete under one project-locked transaction; enforces exact one customer_ledger PPR + one linked ledger + one matching BookingVoucher FK/amount/customer/plot. Preserves original identities and amounts orientation: original PPR customer_ledger.amount_out, ledger.amount_in, booking_vouchers.amount. Cash update/delete dispatched from `BookingController::updateCashIn` and `destroy`, with 409 JSON feedback. Noncash existing correction/relink now shares one DB transaction. Typed finance batches/bank custody/unresolved approvals are explicitly excluded. Feature and unit regressions added.

**Cash EC/ECP / Extra Charge:** New `ExtraChargeCashEditor` corrects or logically deletes selected legacy cash EC/ECP using exact two ledger leg IDs, parent charge identity and `ExtraChargeBankSettlement` paid/pending/available caps; no new reversal batch, invalid foreign-account/project and typed postings blocked. `ExtraChargeBankSettlement::linked` now ignores archived ECP payments for live outstanding. New GET/PUT/DELETE `finance.extra-charge.cash.*` JSON API under granular extra-charge view/edit/delete permission; modern jQuery AJAX Edit/Delete modals in `resources/views/admin/booking/extra_charge.blade.php`, toast and partial table+balance refresh. Previous inert unsafe legacy button handlers replaced. SQLite integration and unit contracts added.

**Stock Entry/Stock Bill:** New `StockDocumentEditor` handles legacy cash-posted `stock_entries` and `stock_bills` (not separate typed batch owner). Ensures exact document detail ID -> original stock_movements.source_line_id, direction, item, party, qty/rate/amount; original source `customer_ledger` reference `STOCK-ENTRY:<id>` / `STOCK-BILL:<id>`, two exact balanced legacy JV ledger legs, project/scoped accounts. One transaction corrects source rows and materialized inventory delta, with materialized `stock_project_item_balances` verified against sum of all current active movements and fail-closed negative/overflow guards. Changed/removed movement evidence archived, unchanged FK IDs preserved. Logical Delete archives source/line/movement and exact customer-ledger/ledger rows, adjusts stock and removes current effect without a separate REV voucher. Already-billed entries and typed posted batches rejected until owning workflow can correct them. New GET/PUT/DELETE APIs, jquery Add/Remove/Edit/Delete modal and partial register refresh on stock dashboard; stock listing/reconciliation/report filters now ignore archived movement and detail rows. Non-destructive migration `2026_11_19_150000_add_stock_document_archive_columns.php` adds archived columns to existing Stock tables. SQLite feature + unit tests added.

**Legacy Labour CP:** New `LabourPaymentEditor` locks existing CP customer_ledger + exact one ledger + active labour_ledgers.customer_ledger_id members. Validates each worker belongs to current project and sum of member amounts equals original parent cash CP amount_out. Supports same-ID amount/date/reference update, per-member Add/Remove/Update (retains IDs for unchanged members), logical Delete without REV; typed payroll memberships, typed cash posting and pending approval remain separately owned. New `labours.payment.details/update/delete` APIs with granular CP permissions; Labour History lists exact linked CP sources and has jQuery Edit/Delete modal with member fields/toasts/partial list+balance refresh. Labour history/report active-payment queries exclude archived members; payments group by payment DATE rather than original created_at. Non-destructive migration `2026_11_19_160000_add_labour_payment_archive_columns.php` adds active/archive fields to existing labour_ledgers. SQLite feature + unit tests added.

**No new source table or Activity Audit tables** created in this batch. Two migrations add archive columns to existing stock/labour tables, in addition to earlier owner-applied bank lifecycle migration.

### REQUIRED local owner verification — do this BEFORE claiming Milestone 03 closure

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache

php -l app/Services/Finance/OriginalJournalWorkflow.php
php -l app/Services/Finance/StockDocumentEditor.php
php -l app/Services/Finance/BookingReceiptCashEditor.php
php -l app/Services/Finance/ExtraChargeCashEditor.php
php -l app/Services/Finance/LabourPaymentEditor.php
php -l app/Http/Controllers/JournalVoucherController.php
php -l app/Http/Controllers/StockController.php
php -l app/Http/Controllers/BookingController.php
php -l app/Http/Controllers/LabourController.php

php artisan test --filter=JournalPostingWriterTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan test --filter=JournalApprovedCorrectionContractTest
php artisan test --filter=SalesVoucherFullLifecycleContractTest
php artisan test --filter=CashPostingWriterTest
php artisan test --filter=BookingReceiptCashEditorTest
php artisan test --filter=BookingReceiptSameSourceContractTest
php artisan test --filter=ExtraChargeCashEditorTest
php artisan test --filter=ExtraChargeCashLifecycleContractTest
php artisan test --filter=StockDocumentEditorTest
php artisan test --filter=StockDocumentCorrectionContractTest
php artisan test --filter=LabourPaymentEditorTest
php artisan test --filter=LabourPaymentCorrectionContractTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan test --filter=BankTransferWriterTest
php artisan finance:audit-posting-balances
```

**If ALL tests pass**, apply these two additive migrations only on the selected local disposable test DB:
```powershell
php artisan migrate --path=database/migrations/2026_11_19_150000_add_stock_document_archive_columns.php
php artisan migrate --path=database/migrations/2026_11_19_160000_add_labour_payment_archive_columns.php
php artisan optimize:clear
php artisan view:cache
php artisan finance:audit-posting-balances
```

Avoid `migrate:fresh`, arbitrary DB/project hardcode, or using real customer financial data without backup.

**Targeted browser acceptance after local tests:** Disposable SV Create/Edit/ordinary Delete and approved JV Edit; BD/BW manual Edit/Delete (feature tests already passed); cash PPR Edit/Delete and booking/party balance; cash EC/ECP Edit/Delete and remaining EC outstanding; Stock Entry/Bill Add/Remove/Change/Delete with stock balance and legacy JV ledger; Labour CP worker member add/remove/update/delete with CP ledger and attendance report. Failed operations must show toast+popup and cause zero partial financial/inventory mutations. Reopen unchanged Save should not append duplicate rows.

**Still genuinely unresolved:** Current typed-approved Stock Entry/Bill, typed Payroll/Attendance, BOR/BOP cleared-cheque/online custody source lifecycle and some original CR/DR/CCR/CDR permission/browser matrices are NOT fully accepted in this latest batch. These have different authoritative posting/source claim structures, and are not safe for a generic Edit/Delete that simply changes DB rows. Linked cheque/online cascade is the dedicated Milestone 04; do not accidentally bypass custody; typed-stock/payroll correction needs dedicated source-line attribution for full M03 closure. M02 roles/permissions browser matrix also partial. **M03 remains ACTIVE/PARTIAL, not 100% or CLOSED until test/browser evidence and remaining typed flows.**

### Highest-priority fresh-chat handoff prompt

Continue BlueMarketing repo `Bilal6230/blueMarketing`, `upgrade/laravel-framework`, local `C:\laragon\www\blueMarketing`. Owner insists M03 all voucher CRUD/relationships must be closed, not just docs. Read latest `docs/README.md` section, fetch actual code, run/fix newly added tests from gate. Verified earlier BD/BW 13/102 + all 6 projects posting audit clean, CV 16/139 and approved CV browser PASS. Latest changes this turn: posted JV early 409 removed, SV Create+typed Edit/Delete/legacy retirement fixed, cash PPR same-source Edit/Delete and BookingVoucher FKs, cash EC/ECP same-source CRUD and live balance guard, stock entry/bill same-source + inventory balance exact movement links, original Labour CP Edit/Delete, UI jQuery modals/partial refresh, additive stock/labour archive migrations. New changes NOT tested locally, require owner tests/browser. Pending typed Stock/Payroll correction source attribution and M04 linked cheque/online custody. Do not claim M03 complete without evidence. M06 centralized Activity Audit is LAST and NOT started. Custom frontend jQuery (no standalone vanilla JS), consistent modal/toast/skeleton, no V2 names/isolation, configured default DB/project only. Commit actual fixes; keep docs single authoritative handoff.


### M03 supplementary fixes and evidence — 2026-11-19

- Added `StockDocumentEditorTest::test_stock_bill_purchase_edit_and_delete_preserve_financial_and_entry_fk` so Stock Bill purchase correction is covered separately from Stock Entry (inventory, old posting IDs and soft Delete). Stock Editor now verifies materialized stock against the sum of every active stock movement *before* changing inventory and checks qty × rate integer overflow.
- Fixed EC/ECP action UI to honor *granular* `finance extra-charge edit/delete` directly on individual buttons, not merely the parent column.
- Updated Labour person-wise report to exclude archived payment detail rows; payment grouping on Labour History now uses current payment DATE rather than unchangeable created_at. Labour CP service checks its source FK/archive migration before database writes.
- **Newly detected CCR/CDR Edit blocker fixed:** `LedgerController::update` previously accepted only `CR` and `CP` although `SameSourceCashVoucherEdit` already supported `CCR/CDR`. Its source lookup now handles `CR/CP/CCR/CDR` together with the same granular permission and project/account checks. `CashCreditDebitEditContractTest` added.
- Latest NEW tests remain **unexecuted by owner** and have not been claimed passed. Newly added scenarios must run locally before any closure.
- Additional owner test gates: `php artisan test --filter=CashCreditDebitEditContractTest`, `php artisan test --filter=StockDocumentEditorTest`, `php artisan test --filter=LabourPaymentEditorTest`. Re-run `php artisan test --filter=SameSourceCashVoucherEditTest` if present. Browser test CCR/CDR Edit and attached Ledger balances after green tests.


## 2026-11-21 — Milestone 03: DR / CV / Plot Receive / Plot Purchase approvals (latest branch changes)

**Implementation pushed; local automated tests and browser acceptance are NOT YET confirmed.** Owner requested to finish the four voucher types previously marked `Integration pending` in `/admin/finance/voucher-settings`, while preserving the existing normal ERP and without resurrecting isolated V2 databases.

- **DR:** `LedgerController` continues to persist its real `CP` accounting-source representation but now submits the selected **DR** display series into the project-scoped approval workflow. Decision guard requires matching project-owned `CP` source and exact saved `DR` display identity; normal ERP policy bypass now explicitly permits DR.
- **CV:** `CommissionDraftWorkflow::createDraft` submits a pending request in the same transaction if CV approval is enabled. Existing inactive commission draft ledgers remain inactive until the shared dashboard's authorized approval calls `CommissionDraftWorkflow::approveAndPost`. Reject never posts; original direct CV Approve endpoint rejects centralized pending/rejected sources, and pending CV draft Edit/Delete are blocked against post-submission changes. CV directory now shows central approval status.
- **PPR (typed Plot Receipt) and PP (Plot Purchase):** one shared migration creates `finance_plot_approval_drafts` with project/type/request-key/number uniqueness, immutable JSON payload fingerprint, decision and posted source references. On approval ON, source posting and plot inventory creation are **deferred**; on Approve, the existing `PlotEntryWriter` revalidates all accounts, bookings and inventory constraints and creates the source/posting only inside the approval decision transaction. Reject creates no financial or inventory effect. The exact draft ID gates reserved voucher numbers under the same project lock as direct posting. Pending/rejected records display in both Plot voucher lists without early Print/Edit/Delete actions.
- Settings and approval dashboard enable DR/CV/PPR/PP; approval details render project-scoped CV lines and preview plot allocations/purchases. Granular permission routing maps typed Plot PPR to `finance plot-receipt approve` rather than unrelated booking cash PPR.
- New contract: `tests/Unit/Finance/RemainingVoucherApprovalIntegrationsContractTest.php` checks all four types, guarded source routing and deferred posting.

**Required owner local verification on disposable local DB only (backup before migration):**
```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --path=database/migrations/2026_11_21_130000_create_finance_plot_approval_drafts_table.php
php artisan view:cache
php artisan test --filter=RemainingVoucherApprovalIntegrationsContractTest
php artisan test --filter=DailyVoucherApprovalWorkflowTest
php artisan test --filter=PlotEntryWriterTest
php artisan test --filter=CommissionLedgerSynchronizerTest
php artisan finance:audit-posting-balances
```

**Browser acceptance required before milestone sign-off:** For DR/CV/PPR/PP separately: policy OFF Create -> direct posting (CV retains its prior own draft/approve lifecycle); policy ON Create -> central pending queue; authorized Approve -> correct one-time finance posting, and for PP only then plot inventory added; fresh pending Reject -> no posting/no plot inventory; confirm project permissions and status lists. CV is already inherently a draft and must be centrally approved when policy ON, with no direct button bypass. Neither these changes nor Milestone 03 as a whole are marked browser PASS. SV approval bugs remain separately deferred per owner's instruction.
