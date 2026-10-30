# V2 application ledger access scan — batch 12
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 11 app PHP files (179–189 of 189).

## `app/Services/Finance/LegacyEvidenceConflictGate.php` (69 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/LegacyEvidenceCoverageInspector.php` (58 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/LegacyEvidencePortfolioReconciliation.php` (55 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/LegacyEvidenceReleaseGate.php` (66 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/LegacyEvidenceReviewWorksheet.php` (121 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/LegacyVoucherCollisionInspector.php` (52 lines)
- L27 `inspect`: `$candidates = DB::table('ledgers')`
## `app/Services/Finance/LegacyVoucherCollisionInventory.php` (213 lines)
- L19 `unverifiedQuery`: `$query = DB::table('ledgers')->where('type', 'JV')`
## `app/Services/Finance/LegacyVoucherEvidencePacket.php` (100 lines)
- L43 `inspect`: `$ledger = DB::table('ledgers')->where('id', $ledgerId)`
## `app/Services/Finance/ProjectVoucherAllocator.php` (62 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/StockLedgerPostingGuard.php` (224 lines)
- L76 `alreadyPosted`: `$existing = Ledger::query()`
## `app/Services/Finance/VerifiedLedgerSourceResolver.php` (76 lines)
- No directly matched ledger/raw-statement token