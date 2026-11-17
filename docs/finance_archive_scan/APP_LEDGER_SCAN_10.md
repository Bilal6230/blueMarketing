# V2 application ledger access scan — batch 10
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 17 app PHP files (145–161 of 189).

## `app/Providers/AdminProvider.php` (59 lines)
- No directly matched ledger/raw-statement token
## `app/Providers/AppServiceProvider.php` (20 lines)
- No directly matched ledger/raw-statement token
## `app/Providers/AuthServiceProvider.php` (36 lines)
- No directly matched ledger/raw-statement token
## `app/Providers/BroadcastServiceProvider.php` (22 lines)
- No directly matched ledger/raw-statement token
## `app/Providers/EventServiceProvider.php` (43 lines)
- No directly matched ledger/raw-statement token
## `app/Providers/RouteServiceProvider.php` (53 lines)
- No directly matched ledger/raw-statement token
## `app/Repository/Common/AppRepository.php` (92 lines)
- No directly matched ledger/raw-statement token
## `app/Repository/Lead/LeadRepository.php` (279 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Booking/BookingAmendmentService.php` (187 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Booking/BookingEditLifecycleInspector.php` (91 lines)
- L36 `inspect`: `$hasResale = Ledger::query()`
## `app/Services/Booking/BookingEditLifecycleResult.php` (16 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Booking/BookingPaidAmountResolver.php` (82 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Booking/BookingPriceCalculation.php` (33 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Booking/BookingPriceCalculator.php` (108 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Booking/BookingSalesAccountingResolver.php` (255 lines)
- L92 `resolve`: `$ledgers = Ledger::query()`
- L158 `resolve`: `$sharedOutsidePrincipal = Ledger::where('customer_ledger_id', $customerLedgerLine->customer_ledger_id)`
## `app/Services/Booking/BookingSalesAccountingResult.php` (29 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/CommissionVoucherIntegrityInspector.php` (69 lines)
- No directly matched ledger/raw-statement token