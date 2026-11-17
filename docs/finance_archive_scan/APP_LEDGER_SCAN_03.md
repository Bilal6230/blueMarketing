# V2 application ledger access scan — batch 3
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 14 app PHP files (29–42 of 189).

## `app/Console/Commands/ReviewLegacyEvidence.php` (60 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/VerifyLedgerConstraints.php` (70 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Kernel.php` (33 lines)
- No directly matched ledger/raw-statement token
## `app/Exceptions/Handler.php` (93 lines)
- No directly matched ledger/raw-statement token
## `app/Helpers/AppHelper.php` (971 lines)
- L313 `getLastLedgerIdByType`: `$query = App\Models\Ledger::where('type', $type);`
- L326 `getVocuherNumber`: `$numbers = Ledger::where('type', $type)`
## `app/Helpers/SettingHelper.php` (413 lines)
- L346 `get_voucher_number`: `$lastId = DB::table('ledgers')->orderBy('id', 'desc')->value('id');`
## `app/Http/Controllers/AccountingController.php` (777 lines)
- L151 `AccountantLedgers`: `$ledgers = Ledger::query()`
- L739 `details_party_ledger`: `$openingBalance = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)`
- L745 `details_party_ledger`: `$ledgerDetails = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)`
## `app/Http/Controllers/Api/AuthController.php` (100 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/LeadController.php` (107 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/ProjectController.php` (304 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/BaseMobileController.php` (68 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/Concerns/ResolvesMobileProjectAccess.php` (50 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/MobileApprovalController.php` (861 lines)
- L302 `scopeAccessibleApprovals`: `$ledgerIds = Ledger::query()->whereIn('project_head_subheads_id', $projectHeadSubheadIds)->pluck('id');`
- L404 `resolveApprovalProjectId`: `'ledgers' => Ledger::query()`
- L468 `applyPendingUpdate`: `$ledger = Ledger::query()->find($locked->record_id);`
- L720 `ledgerSummary`: `$ledger = Ledger::query()->select(['id', 'voucher_number', 'detail', 'type'])->find($recordId);`
## `app/Http/Controllers/Api/V1/Mobile/MobileAttendanceController.php` (745 lines)
- No directly matched ledger/raw-statement token