# V2 application ledger access scan — batch 02
Branch `upgrade/laravel-framework`; exact-file GitHub reads; syntactic evidence only, never assume method is reachable or mutates ledgers.

Scanned 14 app PHP files (15–28 of 189).

## `app/Console/Commands/AuditLegacyVoucherCases.php` (58 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/AuditProjectVoucherSources.php` (141 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/AuditVoucherCollisions.php` (32 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/BuildJournalRecoveryEvidence.php` (95 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/FinanceMetadataPreflight.php` (24 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/FinanceMysqlStagingPreflight.php` (85 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/FinanceRolloutAssessmentCommand.php` (31 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/InspectAllMissingJournalPostings.php` (86 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/InspectLedgerSchema.php` (69 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/InspectMissingJournalPosting.php` (82 lines)
- L35 `handle`: `$potentialLegs = DB::table('ledgers')->where('type', 'JV')->where('type_id', $id)`
## `app/Console/Commands/ProbeConcurrentVoucherPosting.php` (67 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/ProbeLedgerProjectLock.php` (49 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/RehearseLedgerConstraints.php` (85 lines)
- No directly matched ledger/raw-statement token
## `app/Console/Commands/RepairMissingGeneralJournalLegs.php` (168 lines)
- L92 `handle`: `if ($hasDetailFk && DB::table('ledgers')->where('journal_voucher_detail_id', $d->id)->exists()`
- L144 `handle`: `DB::table('ledgers')->insert($values);`
- L147 `handle`: `$posted = DB::table('ledgers')`