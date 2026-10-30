# Database V2 — Indirect source writer and dynamic table resolution audit

**Source branch:** `upgrade/laravel-framework`, GitHub CI run 41097625500 (app/routes/database/Blade static scan). Source code only, no financial database changes. Reproducible scanner: `tools/database_v2/audit_ledger_writers.py`.

## Repository coverage

The scanner enumerated **360 PHP/SQL files** in `app/`, `routes/`, `database/`, and `resources/views/`; found **71 files** with **291 code-token matches** (129 direct model tokens, 39 dynamic builder/connection tokens, 128 finance-table tokens, 24 literal table tokens, one raw statement). Categories **overlap**, so the individual counts must not be summed as distinct source paths. Comments, reads and query-builder aliases are included: 291 is not the count of writers.

## All 39 dynamic-table/connection leads — classification

| Path(s) | Matched count | Source-level classification | Required policy |
|---|---:|---|---|
| `app/Http/Controllers/Api/V1/Mobile/MobileApprovalController.php` lines 434, 452, 465, 696 | **4** | L434/L696 target existence checks; **L452/L465 dynamic UPDATE** of allowed table, including ledgers, using field allowlist and ledger financial-field guard | **CRITICAL**: Query Builder bypasses Ledger model creating/updating hooks; tests must verify amount, account, project and source identifiers stay forbidden |
| `app/Http/Controllers/Finance/VoucherController.php` lines 313, 326, 335 | **3** | Dynamic `$request->table`, includes allowed financial tables; **L335 dynamic UPDATE**; L313 unusual `DB::table(... )::findOrFail` expression needs route-specific execution coverage | **CRITICAL**: enforce typed-source and posted immutable guard before V2 cutover; do not approve amount/source rewrite via generic approvals |
| `app/Console/Commands/RehearseLedgerConstraints.php` lines 16, 54–62 | **9** | Isolated schema rehearsal command; synthetic `$db->table($source/$account/$leg)->insert(...)` and negative FK checks | Ensure command's target database guard is enforced and cannot point to live/staging or V2 imported historical database |
| `app/Console/Commands/{AuditJournalLedgerParity,FinanceMysqlStagingPreflight,InspectLedgerSchema,ProbeConcurrentVoucherPosting,ProbeLedgerProjectLock,RepairMissingGeneralJournalLegs}.php` | **8** | Driver/database identity introspection via `DB::connection()` (not itself a write). Repair command has separate literal `DB::table('ledgers')->insert` flagged in direct scan | Keep repair `--apply` disabled for V2 unless independently approved; forensic SQL remains read-only by default |
| `app/Console/Commands/AuditJournalVouchers.php` line 50 | **1** | Dynamic `$otherTable` read in voucher collision audit | Confirm fixed table whitelist; must never classify matching numeric `type_id` alone as proof of source |
| `app/Services/Finance/{DurableFinancialRequest,FinancialSourceReconciliationInspector,LedgerSourceAttributionWriter,LedgerVoucherDisplayService}.php` | **4** | Typed header lookup with variable `$headerTable`/`$table`; current function snippets perform `where/first` ownership/read checks | Require strict typed table whitelist and project match; attribution writer is separate source-map write path |
| `app/Services/Finance/FinanceSchemaPreflight.php` | **2** | Driver/schema introspection | Read-only; never use introspection as source proof |
| `app/Services/Finance/V2JournalPostingWriter.php` | **1** | Driver check for isolated V2 name | V2-only source posting service, tested in SQLite and MySQL |
| `database/seeders/V2LegacyBanksSeeder.php` | **1** | Driver identity guard | V2-only bank lookup insertion |
| `database/v2_migrations/2026_10_30_000010_create_v2_bank_lookup.php` | **1** | Driver identity guard | Isolated migration only |
| `database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php` | **1** | Driver identity guard | Isolated migration only |
| `database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php` | **4** | Driver and database identity checks | Isolated migration only |
| **Total** | **39** |  |  |

**Important:** `DB::connection()` appears in the dynamic token class, but this is NOT evidence of dynamic financial UPDATE. The major currently active generic mutation risks are the **Finance/VoucherController and MobileApprovalController** approval routes; the latter already denies a known set of financial/identity fields, while the former needs proof that the web approval path does likewise.

## Confirmed separate direct ledger writers

Historical `CR/CP`: `LedgerController::store/update`; bank BR: `Finance/VoucherController::updatePendingBankPaymentStatus`; PPR/EC/ECP/SV/booking cancellations: `BookingController::deposit/createSalesVoucher/createSalesVoucherLedger/recordSalesVoucherEntry`; labour CP and JV: `LabourController::labourPayment/createVoucher`; stock JV: `StockController::postEntryLedger/postBillLedger`; genuine JV: `JournalVoucherController::store`; previous commission JV collision now restricted by 409; repair command directly writes ledgers only with explicit apply. Canonical account/value/source matrix: `DATABASE_V2_LEDGER_WRITER_MATRIX_2026_10_30.md`.

## Remaining certification blockers

1. Full method-level `->update`, `->delete`, `->save` and relation-create alias dataflow in **all** files, not just token matches and key routes.
2. Route and API/UAT exercise of both approval controllers' dynamic UPDATE against each forbidden financial field and project tenant mismatch; no bypass via raw Query Builder.
3. Lock/transaction and project/actor authorization across all legacy financial writer families. Do not confuse direct code coverage with finance correctness.
4. V2 reporting adapters, customer cash/booking/stock/labour posting routes and controlled replacement of legacy readers/writers remain unimplemented.

**Gate P1.G3 remains OPEN.** This document closes the **39 dynamic token classification** subtask; it does not certify all 291 candidates as safe.
