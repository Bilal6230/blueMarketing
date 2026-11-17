# V2 model and original-controller cutover audit — 2026-10-31

Branch: `upgrade/laravel-framework`. **All real V1, live, staging, client and affected historical rehearsal databases remain untouched.**

## Milestone 12 — domain models

### Real implementation delivered

- Added **18 newly mapped physical source read-only model types** this batch: Customer Ledger, Booking Voucher, Booking, Commission header/detail, Stock entry/line, Stock bill/line, Stock party/item, Labour ledger/worker/attendance, Lead, Plot, Stock Counter, Stock Item Balance. Together with the 4 existing JV/Ledger/account models, V2 now has **22 concrete domain source adapters** with original FK directions, source money/date casts and read-only Eloquent protections.
- `V2DomainModelContract` inspects all 22 V2 source adapters against clean isolated schema, primary keys (including `stock_counters.key`, `stock_item_balances.item_id`), relation foreign keys and MySQL decimal precision. Wired to disposable MySQL verification.
- `audit_legacy_models_disposable.php` inspects every root-level V1 Eloquent model against actual original physical schema in CI and emits exact missing columns/casts, with NO record reads or schema writes.
- The initial MySQL 8.4 CI on the earlier 18-model revision independently verified **18 models / 136 casts / 33 relationships**, and inventoried **50 legacy models / 48 with physical source tables / 9 V1 compatibility findings**. The expanded **22-model** revision awaits its own final CI evidence.

### Nine original V1 model findings (source schema must NOT be silently changed)

1. `Expence`: `expences` table not in pinned 57-table source baseline.
2. `JournalVoucher`: V1 fillable `site_id` not in source header.
3. `LabourAttendance`: V1 fillable `site_name` not in source.
4. `LandingPage`: `landing_pages` table not in pinned source baseline.
5. `Lead`: V1 fillable `sectors` not in source.
6. `Ledger`: V1 fillable `journal_voucher_detail_id` not in original ledger.
7. `Plot`: V1 fillable and cast `amount` not in source.
8. `StockCounter`: V1 cast `id` missing because physical primary key is `key`.
9. `StockItemBalance`: V1 cast `id` missing because physical primary key is `item_id`.

**P3.G4 remains PARTIAL**, not accepted: the V1 model drift is documented, exact V2 source adapters exist, but complete 50-model legacy compatibility and owner-approved intentional exception policy are outstanding.

## Milestone 18 — production voucher controllers and approvals

### Real implementation delivered

- `V2SourceApprovalReadiness` reads the **actual V1 source status** by primary key and project for JV, SV, CR/CP, PPR/EC/ECP, CV, Booking, Labour and Stock entry/bill. It requires CV `approved`, customer voucher `is_approve` and explicit expected series, booking `is_approve`, labour/project match, and an evidence-backed stock/project SHA match.
- The pilot V2 posting API consults this eligibility boundary before dispatching, but transaction writers **independently recheck source/project approval under database locks** to prevent approval change races. Unapproved/wrong-type requests return sanitized `409 Conflict` with no posting; the eligibility endpoint is GET, authenticated and read-only.
- `audit_v1_controller_routes.php` inventories registered mutating routes from seven original production controllers (JV, CV, Finance Voucher, Booking, Stock, Labour, Payment Clearance), so each unmigrated V1 path has a grounded action and route identity. This is an audit, **not** an actual endpoint replacement.
- Existing production controllers, historical ledger writes, approval history and V1 routes remain unchanged and running in their original behavior. V2 pilot routes remain OFF by default and restricted to local/testing isolated data.

**P5.G2 remains PARTIAL / CUTOVER BLOCKED.** Old V1 writes and approvals cannot safely be replaced with V2 post calls until historical 57-table UTC parity, all voucher source ownership/booking/stock/labour approval decisions, V1/V2 financial totals, rollback rehearsal and explicit owner approval exist. A direct overwrite or parallel V1+V2 post would risk double financial entries.

## Final accepted gate accounting

**9 / 24 = 37.5% accepted.** P3.G4, P5.G2, P4.G3, P4.G4, P5.G3–G4 and P6 remain OPEN. Source-compatible coding progress is genuine, but it is not permission to deploy or rewrite old customer data.

## Safe operator verification

Only SQLite in-memory suites and CI are permitted by this checkpoint. Local read-only test run: pull branch on `upgrade/laravel-framework`, set `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, clear `DB_URL`/`DATABASE_URL` for the child shell, then run `tests/Feature/Finance/V2FinancePilotHttpSafetyTest.php` and `tests/Feature/Finance/V2JournalPostingWriterTest.php`. No MySQL migrate, import or DB update command is part of this checkpoint.
