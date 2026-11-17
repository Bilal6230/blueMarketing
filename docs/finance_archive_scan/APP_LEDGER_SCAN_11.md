# V2 application ledger access scan — batch 11
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, not reachability.

Scanned 17 app PHP files (162–178 of 189).

## `app/Services/Finance/DurableFinancialRequest.php` (93 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/ExtraChargeAccounts.php` (101 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/ExtraChargeBankSettlement.php` (234 lines)
- L38 `assertSourceLegs`: `$legs = Ledger::query()->where('customer_ledger_id', $payment->id)`
- L82 `balances`: `if (Ledger::where('customer_ledger_id', $payment->id)->exists()) {`
- L87 `balances`: `if (Ledger::where('customer_ledger_id', $payment->id)`
- L177 `resolve`: `if (Ledger::where('customer_ledger_id', $payment->id)`
- L206 `resolve`: `Ledger::where('customer_ledger_id', $previous->id)->where('type', 'ECP')->exists()) {`
- L218 `resolve`: `Ledger::create([`
## `app/Services/Finance/FinancePostingSchemaGate.php` (34 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/FinanceRolloutAssessment.php` (73 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/FinanceSchemaPreflight.php` (121 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/FinancialPostingFingerprint.php` (72 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/FinancialReadinessInspector.php` (81 lines)
- L27 `inspect`: `$scope = DB::table('ledgers')->where('type', 'JV')`
## `app/Services/Finance/FinancialReversalPlanner.php` (65 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/FinancialSourceOwnership.php` (66 lines)
- No directly matched ledger/raw-statement token
## `app/Services/Finance/FinancialSourceReconciliationInspector.php` (64 lines)
- L31 `inspect`: `$ledger = DB::table('ledgers')->where('id', $a->ledger_id)->first();`
## `app/Services/Finance/JournalVoucherIntegrityInspector.php` (92 lines)
- L59 `inspect`: `$candidateLedgerRows = DB::table('ledgers')`
## `app/Services/Finance/JournalVoucherRecoveryPlan.php` (70 lines)
- L48 `inspect`: `if (DB::table('ledgers')->where('type', 'JV')->where('type_id', $journalId)->exists()) {`
## `app/Services/Finance/JournalVoucherReplayIntegrity.php` (92 lines)
- L38 `assertIntact`: `$allLedgerCount = DB::table('ledgers')->where('type', 'JV')`
## `app/Services/Finance/LedgerSourceAttributionWriter.php` (60 lines)
- L37 `recordNewPosting`: `$ledgerExists = DB::table('ledgers')->where('id', $ledgerId)`
## `app/Services/Finance/LedgerVoucherDisplayService.php` (59 lines)
- L24 `display`: `$ledgerOwned = DB::table('ledgers')->where('id', $ledger->id)`
## `app/Services/Finance/LegacyEvidenceApprovalCheckpoint.php` (84 lines)
- No directly matched ledger/raw-statement token