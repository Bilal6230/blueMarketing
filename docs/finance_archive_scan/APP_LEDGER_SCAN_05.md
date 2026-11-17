# V2 application ledger access scan — batch 5
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 17 app PHP files (60–76 of 189).

## `app/Http/Controllers/DashboardController.php` (273 lines)
- L52 `index`: `$data = Ledger::with('projectHeadSubhead.project', 'projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')->where('is_active', 1)`
- L57 `index`: `$Bankaccountdata = Ledger::with([`
## `app/Http/Controllers/DastiCashController.php` (85 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/ExpenceController.php` (86 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/FileManagerController.php` (15 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Finance/PaymentClearanceController.php` (312 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Finance/VoucherController.php` (1375 lines)
- L104 `resolveFinancePendingModel`: `'ledgers' => new Ledger(),`
- L261 `cash_in`: `$numbers = Ledger::where('type', 'CR')`
- L289 `print`: `$x['voucher'] = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')->findOrFail($id);`
- L442 `cash_out`: `// $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')`
- L501 `cash_out`: `$numbers = Ledger::where('type', 'CP')`
- L532 `cash_out_data`: `$q = Ledger::with([`
- L729 `cash_out_data_old`: `$q = Ledger::with([`
- L961 `updatePendingBankPaymentStatus`: `$existingClearance = Ledger::query()`
- L983 `updatePendingBankPaymentStatus`: `Ledger::create([`
- L1028 `cash_in_data`: `$q = Ledger::with([`
- L1316 `checkNewVoucherNumber`: `$exists = Ledger::where('type', $type)`
- L1328 `checkNewVoucherNumber`: `Ledger::where('type', $type)`
- L1345 `updateVoucherNumber`: `Ledger::with('projectHeadSubhead:id,project_id')`
## `app/Http/Controllers/HomeController.php` (29 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/JournalVoucherController.php` (612 lines)
- L481 `store`: `$ledger = Ledger::create($ledgerAttributes);`
## `app/Http/Controllers/LabourController.php` (1489 lines)
- L199 `labourPayment`: `Ledger::create([`
- L1077 `createVoucher`: `$toLedgerData = Ledger::create([`
- L1115 `createVoucher`: `$fromLedgerData = Ledger::create([`
## `app/Http/Controllers/LandingPageAdminController.php` (266 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/LeadController.php` (618 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/LedgerController.php` (1275 lines)
- L208 `store`: `$cashOutClearanceLedger = Ledger::where('customer_ledger_id', $pendingCustomerLedger->id)`
- L218 `store`: `$sourceReceiptLedger = Ledger::where('customer_ledger_id', $pendingCustomerLedger->id)`
- L226 `store`: `$cashOutClearanceLedger = Ledger::create([`
- L357 `store`: `$data = Ledger::create([`
- L724 `show`: `$ledger = Ledger::with([`
- L785 `customerLedgerShow`: `// $data_list = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project', 'customerLedge`
- L814 `show_ledger`: `$data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')`
- L840 `destroy`: `$ledger = Ledger::query()`
- L950 `update`: `$ledger = Ledger::where('id', $request->id)`
- L1077 `fetch_data_url`: `$query = Ledger::with(`
- L1135 `show_ledger_party`: `$data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')`
- L1164 `show_ledger_head`: `$data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')`
- L1184 `fetch_data_by_party`: `$data = Ledger::with('projectHeadSubhead.project', 'projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')`
- L1224 `fetch_data_by_head`: `$data = Ledger::query()`
- L1245 `fetch_data_by_head`: `$data = Ledger::query()`
## `app/Http/Controllers/ModuleController.php` (18 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/PermissionController.php` (117 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/PlotController.php` (433 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/ProjectController.php` (229 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/ReportController.php` (302 lines)
- L235 `bank_posting_check`: `$existingClearance = Ledger::query()`
- L257 `bank_posting_check`: `Ledger::create([`