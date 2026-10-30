# V2 application ledger access scan — batch 7
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 17 app PHP files (94–110 of 189).

## `app/Http/Resources/UserResource.php` (34 lines)
- No directly matched ledger/raw-statement token
## `app/Models/AccountType.php` (21 lines)
- No directly matched ledger/raw-statement token
## `app/Models/AccountingExpense.php` (25 lines)
- No directly matched ledger/raw-statement token
## `app/Models/Area.php` (19 lines)
- No directly matched ledger/raw-statement token
## `app/Models/Booking.php` (81 lines)
- No directly matched ledger/raw-statement token
## `app/Models/BookingDetail.php` (29 lines)
- No directly matched ledger/raw-statement token
## `app/Models/BookingEditAudit.php` (24 lines)
- No directly matched ledger/raw-statement token
## `app/Models/BookingVoucher.php` (65 lines)
- L52 `ledger`: `return $this->belongsTo(Ledger::class);`
## `app/Models/ChargeType.php` (14 lines)
- No directly matched ledger/raw-statement token
## `app/Models/CommisionVoucher.php` (37 lines)
- No directly matched ledger/raw-statement token
## `app/Models/CommisionVoucherDetail.php` (53 lines)
- No directly matched ledger/raw-statement token
## `app/Models/CustomerLedger.php` (148 lines)
- L139 `ledger`: `return $this->hasOne(Ledger::class, 'customer_ledger_id', 'id');`
## `app/Models/Dasticash.php` (20 lines)
- No directly matched ledger/raw-statement token
## `app/Models/DraftLedger.php` (51 lines)
- No directly matched ledger/raw-statement token
## `app/Models/Expence.php` (12 lines)
- No directly matched ledger/raw-statement token
## `app/Models/HeadAccounting.php` (34 lines)
- No directly matched ledger/raw-statement token
## `app/Models/HoldPlot.php` (27 lines)
- No directly matched ledger/raw-statement token