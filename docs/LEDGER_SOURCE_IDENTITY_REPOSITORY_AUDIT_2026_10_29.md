# Ledger source identity audit — 2026-10-29

Branch: upgrade/laravel-framework. PARTIAL VERIFIED INVENTORY (NOT complete repository coverage).

| Family | Exact current writer | ledger type | type_id source | reference | provenance/risk |
|---|---|---|---|---|---|
| General JV | JournalVoucherController::store (430-483) | JV | journal_vouchers.id | form reference | detail_id optional, LedgerSourceAttributionWriter when metadata ready |
| CV | CommissionVoucherController::store/update/destroy | blocked 409 | N/A | N/A | historical JV/CV misclassification unresolved |
| Stock entry | StockController::postEntryLedger (1251-1348) | JV | getLastLedgerIdByType('JV')+1 | STOCK-ENTRY:{entry.id} | customer_ledger_id points to synthesized customer source, not JV header |
| Stock bill | StockController bill posting (1411-1499) | JV | getLastLedgerIdByType('JV')+1 | STOCK-BILL:{bill.id} | customer_ledger_id points to synthesized customer source |
| Labour payment | LabourController::labourPayment (148-210) | CP | getLastLedgerIdByType('CP')+1 | request reference | customer_ledger_id links CP customer source |
| Labour attendance JV | LabourController::createVoucher (1050-1135) | JV | journal_vouchers.id | NULL | detail created but examined ledger inserts omit journal_voucher_detail_id |
| CR/CP and clearance | LedgerController (162-390), Finance/VoucherController (870-1000) | CR/CP per branch | generated voucher series/number | request or calculated clearance reference | customer_ledger_id; complete per-branch identity pending |
| Booking, PPR, EC/ECP, raw SQL, jobs and remaining paths | PENDING full branch traversal | UNKNOWN | UNKNOWN | UNKNOWN | do not claim exhaustive coverage |

Verified protections: Ledger model booted() ownership assertion for Eloquent new linked CR/CP/PPR/EC/ECP; raw query-builder writes bypass it. LedgerSourceAttributionWriter::recordNewPosting currently accepts only journal_voucher and commission_voucher and validates JV type and type_id=header PK. LedgerVoucherDisplayService::display and LedgerController now avoid unproven JV-header lookups, falling back to ledger's own number. JV update/delete and CV store/update/delete are 409 guarded.

ROOT CAUSE: type='JV'/numeric type_id span incompatible source domains: genuine journal header, historical commission header/detail and stock generated sequence. Numeric equality cannot authorize ownership, report header resolution, deletion, reconciliation or backfill. reference is sometimes a source-tagged business identifier but not universal FK.

PERMANENT DIRECTION: preserve legacy fields and add independently verified source-kind + actual source PK + project provenance per posting (extend existing attribution rather than invent a competing system); source-specific checks and constraints, atomic detail/ledger writes, idempotency and regression coverage. Do not coerce stock's generated type_id into JV header ID or backfill ambiguous historical rows.

NEXT GATES: complete full-tree writers/readers including query builder, observers, jobs and views; inspect all LedgerController/Finance Voucher branches, booking, EC/ECP; test labour 1:1 detail linkage and rollback on disposable DB; check attribution schema before extending stock; verify MySQL 8.4 migration on isolated restore. No financial SQL, deployment, schema changes or test execution in this audit. JV 1012 extra row 11822 source remains unverified. P0.1 98% engineering estimate is not certification.
