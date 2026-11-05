# Definitions & Settings: remaining engineering milestones

Browser acceptance is assigned to the client and is excluded from this engineering plan. Code completion does not imply deployment completion. Each milestone closes only after its focused automated tests pass.

| Order | Milestone | Engineering exit criteria | Status |
|---|---|---|---|
| 1 | DS-07 payroll accounting | An approved immutable run can be linked to real project-scoped payment vouchers, without changing posted rows or allowing duplicate/overpaid links. | Complete locally; deployment migration pending |
| 2 | DS-08 voucher settings | January calendar year, project-scoped prefixes and display sequences, custom payment-method master, and bank-account identity checks cover all supported writers. Existing numeric voucher IDs retain their meaning. | In progress: booking PPR assignment, retry stability, list and print identity are now covered. Audit of remaining voucher families and legacy BO list/print regression coverage remain |
| 3 | DS-01 to DS-06 integrity closure | Remaining inactive/foreign-ID posting paths, merge concurrency, charge-rate consumers, and definition history are tested and fixed. | Complete locally; automated regression gates pass. Production migration/deployment and client browser QA remain external |
| 4 | DS-09 inventory closure | Project item master, imports, stock posts, large-file/permission cases and mobile paths pass focused tests. | Complete locally; deployment migration pending |
| 5 | DS-10 automated acceptance | Isolated migration rehearsal, row/totals reconciliation, permission and regression gates, and deployment runbook are complete. Live database changes remain a separate deployment action. | Historical reconciliation complete for the recorded 2026-10-31 source snapshot: 69 tables / 120,192 compared row records, zero unexplained mismatch; all 31 DS migrations in this checkout match clone history. Details in `DEFINITIONS_SETTINGS_DEPLOYMENT_GATE_2026-11-04.md`. |

Progress is tracked in this file because the older audit file has a legacy text encoding. Current combined focused voucher/display/account-integrity gate: 73 tests / 468 assertions. Deployment migration and browser acceptance are separate from these local results.

## 2026-11-04 DS-08 custom method source integration

- Cash voucher UI now offers active custom methods for the selected project and selects the method's configured Cash/Online/Cheque behavior.
- Legacy and V2 source validation enforce project ownership, active status, and matching base behavior; source vouchers retain `custom_payment_method_id` without changing `payment_type` semantics.
- OriginalVoucherV2WorkflowHttpTest: 47 passed, 355 assertions, including cross-project scoped custom method persistence. Browser QA and migration deployment remain pending.
- ProjectVoucherSettingsMigrationTest verifies source-link, behavior FK, display-number, and bank-profile migrations on isolated SQLite; MySQL clone rehearsal is recorded below.
- Added append-only project display identity storage and wired immutable `TYPE-YEAR-0001` assignments into legacy/V2 cash source creation and JV header creation. Numeric source IDs remain untouched; replay returns the same cash display number.
- Remaining engineering: DS-01–06 closure; focused coverage for the legacy PPR writer and print/list identity; audit of less common voucher families; full historical-table reconciliation and deployment gates.

## 2026-11-04 voucher display and MySQL clone checkpoint

- Added immutable SV numbering to BookingController sales voucher creation; SV and CV lists/prints now render saved project display identities. V2 commission draft creation stores a CV identity transactionally. Existing numeric voucher IDs and old unassigned rows retain their legacy fallback.
- Bank profile migration test now rejects cross-project COA mappings; custom-method composite FK test rejects payment behavior mismatches. `ProjectVoucherSettingsMigrationTest`, `ProjectVoucherDisplayTest`, and `OriginalVoucherV2WorkflowHttpTest`: 49 passed, 382 assertions on SQLite.
- Rehearsed DS-08 migrations 000005 and 000006 on disposable MySQL clone `codex_ds_rehearsal_20261104`. Fixed signed-BIGINT/TINYINT FK incompatibility and missing referenced composite unique key; both migrations pass. Clone `ledgers`: 18,003 rows, in 1,476,647,756.25, out 1,276,102,791.25; `customer_ledger`: 5,580 rows, in 2,663,018,587.25, out 1,460,129,450.00. Counts/totals exactly match pre-migration baseline; custom-method composite FK and display identity table verified.
- Remaining: DS-01–06 untested posting-family and merge/rate/history gates; direct PPR print request coverage and less common voucher-family audit; broader all-table reconciliation baseline. Client browser QA and production deployment remain separate.

## 2026-11-04 DS-01 to DS-06 focused integrity regression

- Re-ran the available active/inactive account, accounting project isolation, COA group merge preview, mapping history, and contractor isolation gates: 20 passed, 83 assertions on SQLite.
- These tests verify the existing guarded cases only. They do not prove every legacy voucher writer or all concurrent charge/definition update paths, so DS-01–06 stays partial.

## 2026-11-04 booking display integration checkpoint

- Legacy new PPR deposits receive an immutable project-scoped display identity in the same transaction as source creation. Existing/replayed source rows are not renumbered.
- Booking receive list rows and print receipts resolve the saved identity, with the legacy voucher identifier as a compatibility fallback. Cash-in listing resolves PPR identities by the linked customer-ledger source and selected project.
- Verification: `CashInDataTest`, `OriginalVoucherV2WorkflowHttpTest`, and `ProjectVoucherDisplayTest` passed. PPR creation test asserts project/year/sequence identity and retry creates no duplicate identity; cash-in list test asserts the assigned display value. Direct booking print request is not covered yet.

## 2026-11-04 consolidated focused gates

- Combined voucher/display/account-integrity tests: 69 passed, 461 assertions. `git diff --check` passed; Git only reported expected LF/CRLF normalization notices.
- DS-01–06 subset included inactive-account rejection, project isolation, account-group merge preview, account-mapping history guards, contractor isolation, and labour migration preflight: 20 passed, 82 assertions. This does not establish full concurrent merge or all effective-rate consumers.
- DS-10 remains conditional: all DS migrations completed on the disposable MySQL clone after the documented manual evidence-based mapping. Ledger and customer-ledger counts/totals were reconciled, but other historical tables lack a pre-migration snapshot, so full reconciliation cannot be certified from the retained evidence.

## 2026-11-04 final engineering follow-up

- Added regression tests that prove the PPR print controller passes the saved project display number and that PPR deposit retry creates exactly one immutable display identity.
- Closed a cross-project data exposure in `BookingController::getPlotCustomer`: malformed plot IDs are rejected, a project must be selected, and plot lookup is constrained by the selected project. Added tests for both same-project result and cross-project isolation.
- Added effective-date model coverage for plot-size rates: latest revision on/before the booking date is used, and a future revision is not returned early.
- Consolidated targeted gate: 73 passed, 468 assertions. Remaining DS-01–06 gaps are listed in the milestone table; DS-10 historical full-table before/after reconciliation still lacks a source baseline snapshot.

## 2026-11-04 focused voucher-family follow-up

- Added immutable `EC`/`ECP` project display identity assignment to legacy charge, cash-payment and pending bank-payment writers. Extra Charges list and pending-clearance queue show the saved display number while preserving the source reference.
- Focused legacy settlement regression: 3 passed, 44 assertions. PHP syntax checks and `git diff --check` pass.
- A broad legacy `VoucherClearanceFlowTest` run is not a clean gate in this checkout: 14 unrelated failures (booking project selection/redirect, PPR expected detail text, and legacy model-not-found/replay expectations); 170 passed. The failures need triage before claiming that suite green.
- Further writer audit: labour-generated JV headers now receive the same immutable project/year identity as other JVs; BR bank-clearance rows created through both clearance controllers receive ledger-sourced BR identities, and Finance voucher print/list falls back to that identity.
- Fixed booking creation to persist the date-effective plot rate actually used in pricing, instead of saving the submitted rate while computing totals from the configured rate.
- Focused follow-up verification: 5 tests passed, 63 assertions across EC/ECP settlement, project display identity, V2 extra charge, syntax and diff checks.

## 2026-11-04 voucher display audit closure

- Audited low-frequency legacy display paths, including BO booking creation, file transfer BO/CP sources, cancellation CR, stock and mobile stock JV sources, BR clearances, labour JV, EC/ECP, PPR, SV and CV.
- BO form preview now shows the selected project's next display sequence (`BO-YYYY-NNNN`) without reserving it; actual number is still allocated immutably inside the posting transaction. Booking, customer-recovery and booking print reports resolve the saved BO identity, with legacy fallback for pre-migration records.
- Finance voucher lists/prints and ledger edit/show payloads use the source's saved identity where available. Existing numeric source IDs remain unchanged.
- Verification: combined display/booking/cash-list/ledger/correction/original-finance regression gate passed (55 tests, 397 assertions); PHP syntax checks passed for changed controllers/helper; `git diff --check` clean after Blade EOF cleanup. The cash-correction test fixture now includes the same project/account/counter/display tables its production transaction uses.
- Historical records created before display identity migration are intentionally shown using their legacy fallback unless separately backfilled; client browser QA/deployment remain external.

## 2026-11-04 DS-01 to DS-06 integrity closure

- Audited legacy posting account resolution across finance vouchers, ledger, booking, stock/mobile stock, labour, commission and extra-charge paths. New postings resolve project-owned mappings through `availableForPosting()` and reject inactive/foreign mappings; historical ledger rows remain readable.
- Account-group create, edit, head assignment and merge now take the selected project's row lock before mutation. Edit/merge re-read and lock the project hierarchy within the transaction; existing project-scoped unique keys prevent duplicate head/group assignments.
- Project charge changes now re-read the project under lock, compare rates against the locked state, and write the revision atomically. For legacy projects without a revision baseline, the former rates are snapshotted from project opening/creation date before a later effective change; backdated changes outside known history are rejected.
- Reviewed rate consumers: booking creation uses the rate effective on booking date and saves that same value; historical booking amendments remain explicit and do not silently inherit a newer definition rate. Plot rates are selected by definition, project and effective date. Duplicate rate dates are protected by a database unique key.
- Verification: DS-01–06 plus booking amendment regression gate passed: 27 tests, 116 assertions. PHP syntax checks passed for changed PHP files; `git diff --check` passed. SQLite does not simulate parallel MySQL row-lock behavior, so concurrency enforcement is implemented with transactional project locks and DB unique constraints; production browser QA/deployment remain outside this milestone.
