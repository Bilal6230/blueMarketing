# V2 application ledger access scan — batch 4
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 17 app PHP files (43–59 of 189).

## `app/Http/Controllers/Api/V1/Mobile/MobileAuthController.php` (72 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/MobileCrmController.php` (570 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php` (48 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/MobileProjectController.php` (19 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/MobileReportsController.php` (730 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Api/V1/Mobile/MobileStockController.php` (1128 lines)
- L986 `postEntryLedger`: `Ledger::create([`
## `app/Http/Controllers/AreaController.php` (139 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Auth/ConfirmPasswordController.php` (41 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Auth/ForgotPasswordController.php` (23 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Auth/LoginController.php` (168 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Auth/RegisterController.php` (74 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Auth/ResetPasswordController.php` (31 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Auth/VerificationController.php` (43 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/BookingController.php` (3871 lines)
- L537 `postResaleProfitSplit`: `$exists = Ledger::where('reference', $booking->id)`
- L925 `createSalesVoucherLedger`: `return Ledger::firstOrCreate([`
- L972 `nextLedgerTypeId`: `$max = Ledger::where('type', $type)->lockForUpdate()->max('type_id');`
- L1473 `update_backup`: `//         $ledger = Ledger::where('type', 'BO')`
- L1492 `update_backup`: `//             $ledger = Ledger::create([`
- L1521 `update_backup`: `//         $ledger = Ledger::where('reference', $booking->id)`
- L1540 `update_backup`: `//             $ledger = Ledger::create([`
- L1606 `update_backup`: `//             $data = Ledger::create([`
- L1665 `destroy`: `|| Ledger::where('customer_ledger_id', $ledger->id)`
- L2504 `deposit`: `$matchingPprLedgers = Ledger::query()`
- L2544 `deposit`: `$ledger = Ledger::create([`
- L2659 `deposit`: `Ledger::create([`
- L2734 `deposit`: `Ledger::create([`
- L3233 `resolveNextBookingVoucherNumber`: `$ledgerQuery = Ledger::query()`
- L3488 `destroy_booking`: `$linkedLedgerExists = Ledger::query()`
- L3596 `cancel`: `$hasLedger = Ledger::query()`
- L3611 `cancel`: `Ledger::query()`
- L3722 `cancel`: `$alreadyPosted = Ledger::where('reference', $booking->id)`
## `app/Http/Controllers/CommissionVoucherController.php` (175 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/Controller.php` (14 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/DashboardController(1).php` (237 lines)
- No directly matched ledger/raw-statement token