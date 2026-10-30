# V2 application ledger access scan — batch 6
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 17 app PHP files (77–93 of 189).

## `app/Http/Controllers/RoleController.php` (99 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/SettingController.php` (141 lines)
- L17 `index`: `$x['types'] = Ledger::distinct()->pluck('type')->toArray();`
## `app/Http/Controllers/StockController.php` (1689 lines)
- L1331 `postEntryLedger`: `Ledger::create([`
- L1482 `postBillLedger`: `Ledger::create([`
## `app/Http/Controllers/UserController.php` (189 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/WorkController.php` (86 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Controllers/ZoneController.php` (123 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Kernel.php` (74 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/Authenticate.php` (22 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/CheckUserStatus.php` (29 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/EncryptCookies.php` (18 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/FormTokenAuth.php` (41 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/PreventRequestsDuringMaintenance.php` (18 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/RedirectIfAuthenticated.php` (33 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/TrimStrings.php` (20 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/TrustHosts.php` (21 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/TrustProxies.php` (29 lines)
- No directly matched ledger/raw-statement token
## `app/Http/Middleware/VerifyCsrfToken.php` (18 lines)
- No directly matched ledger/raw-statement token