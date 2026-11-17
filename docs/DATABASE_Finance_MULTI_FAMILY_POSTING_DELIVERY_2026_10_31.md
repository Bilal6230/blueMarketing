# BlueMarketing — multi-family finance V2 implementation delivery

Date: 2026-10-31. Branch: upgrade/laravel-framework.
Execution state: IMPLEMENTED ON GITHUB; FINAL TESTS AND OWNER ACCEPTANCE DEFERRED.

## Source-grounded implementation

**Commission / CV:** `V2CommissionPostingWriter` reads `commision_vouchers` with its actual `project_id`, `status=approved`, `date`, exact `total_debit/credit`, and all active `commision_voucher_details` lines. Validates exact account ownership and balanced details, posts typed commission source FK and a per-detail FK using additive isolated migration `2026_10_31_000110_create_v2_commission_detail_links.php`. Textual original voucher numbers are hashed but not cast into legacy numeric `type_id`. Replays validate physical source IDs, amounts, project, narrative and hash.

**CR/CP/PPR/EC/ECP:** Existing `V2CashPostingWriter` retains CR/CP baseline behavior. `V2PostingGateway::postCustomerVoucher` requires an explicit PPR/EC/ECP series and actual `customer_ledger.id`, project, active and approved source. It checks source amount direction, approved account legs, project-owned account IDs, idempotency and physically typed `finance_posting_cash_sources.customer_ledger_id`. It does not interpret legacy `customer_ledger.type_id` as voucher ownership for PPR/EC/ECP. Source families not represented by verified customer_ledger rows fail closed; do not guess alternative source tables.

**Booking:** `V2ApprovedSourcePostingWriter` accepts only active, approved booking_vouchers with matching physical `project_id`, positive `amount`, valid `receipt_date`, and explicit balanced account allocation. It checks linked customer ledger project and rejects a customer ledger already posted as cash; linked original ledger must belong to same project via actual account FK. No automatic booking-to-ledger backfill.

**Labour:** Requires physical `labour_ledgers.project_id`, matching `labours.project_id`, real source amount and non-null date, and approved per-project debit/credit allocation. Does not invent source ownership from labour attendance IDs.

**Stock entries/bills:** Original `stock_entries` and `stock_bills` have NO `project_id`. Implemented separate `finance_stock_entry_project_approvals` and `finance_stock_bill_project_approvals` tables with real source FKs, project/user FKs, source SHA and evidence reference. `V2StockSourceSnapshot` verifies original line totals and prevents posting both a stock entry and its linked bill. `V2StockProjectApprovalWriter` requires authenticated actor and explicit `approve-v2-stock-finance` Gate before authorizing a source. Posting refuses missing/stale project approval, or amount mismatch. No policy ability is auto-granted by this delivery; stock posting remains disabled until separately approved source attribution.

**Shared financial posting and reversal:** `V2PostingGateway` now exposes 13 operations: JV, CR/CP, CV, explicit PPR/EC/ECP, booking, labour, stock entry, stock bill, immutable reversal, posting lines, project source summary, project trial balance, account statement. All writers retain V2IsolatedConnectionGuard, row locks, DB transactions, one physical typed source claim, multi-leg debit/credit balance and idempotent source replay. Reversal preserves original and uses source-typed original FK.

**Actual application integration:** `V2FinancePilotController` and new `routes/api.php` routes provide source-family posting, reversal, posting lines, typed source summary, trial balance, account statement, and safe CSV exports. Routes register ONLY when APP_ENV is local/testing AND V2_FINANCE_PILOT_API_ENABLED is explicitly enabled; writes also require V2_FINANCE_PILOT_WRITE_ENABLED. Sanctum, throttle, explicit project membership, Gate ability, active project and isolated V2 DB guard apply. Defaults in `config/finance.php` are BOTH FALSE. Legacy production routes/controllers remain unchanged.

**Reporting:** `V2PostingSummaryReader` groups real typed voucher families and reversals with exact integer cents. `V2FinanceCsvExporter` produces fixed-schema trial balance and account statement CSV with spreadsheet formula sanitization. These reports represent posted/verified V2 data ONLY, not full historical V1 balance certification.

## Deferred tests and edge decisions

Deferred PHPUnit class `tests/Feature/Finance/V2MultiFamilyPostingTest.php` contains CV exact FK/replay tampering, PPR/EC/ECP expected-series protections, booking and labour project ownership, stock approval/snapshot and typed voucher summary cases. **The class has NOT been executed or certified.** Existing CI triggers on branch push automatically; this commit does not claim CI PASS.

Open owner/business decisions: actual transaction_type availability and approval semantics of PPR/EC/ECP in source; any stock project ownership evidence and permission grant; booking-linked cash and ledger double posting policy; signed/negative labour amounts; production finance API route/role rollout; MySQL concurrency, permissions/UAT. No inference or data update performed.

## Implementation coverage (engineering scope; not a formal gate)

- Canonical V2 typed source families with posting writer code: **2/7 → 7/7** (journal, cash, commission, booking, labour, stock_entry, stock_bill).
- Explicit customer voucher series behind physical customer_ledger source: **CR/CP → CR/CP/PPR/EC/ECP**, with new three series requiring explicit expected type.
- Posted-V2 report formats: **3/4 → 4/4** (lines, trial balance, account statement, source summary).
- CSV formats: **0/2 → 2/2** (trial balance + account statement).
- Central posting gateway operations: **6/13 → 13/13**.
- Local pilot HTTP routes: **0/8 → 8/8 authored**, all disabled by default.

## Formal acceptance

**8/24 (33.3%) accepted unchanged.** P5.G1 central API/transaction work substantially implemented but MySQL concurrency, authorization and full workflow acceptance pending. P5.G2 voucher families' pilot posting logic authored but historical ownership reconciliation and production rollout pending. P5.G3 pilot reporting/export API authored; legacy booking/stock/labour controllers, views and all workflows are NOT refactored. P5.G4 full regression pending. P4.G3 remains blocked due source UTC instant mismatch (41/57 affected original source table digests on earlier local rehearsal).

NO production, V1, staging, live, original SQL or affected local V2 rehearsal DB mutation. No tests or migrations were manually executed in this implementation turn.

## Additional reporting and authorization delivered in same code batch

- `V2VoucherRegisterReader` resolves exact typed source voucher display identifiers: original textual CV number and physical stock slip/bill references, never ledger.type_id. Exposed by `V2PostingGateway::voucherRegister` and local-only GET voucher-register API.
- New finance and stock approval abilities must be explicitly registered (`Gate::has`), even if the app's global superadmin bypass would otherwise authorize unknown abilities. This code does NOT register/grant the permissions.
- Deferred unit test `V2FinanceCsvExporterTest` covers fixed columns, formula sanitization and report scope.
- **Implementation submeter updated:** 7/7 typed source families, 14/14 gateway operations, 5/5 pilot report/read formats, 2/2 CSV report exports, and 9/9 gated local HTTP endpoints.
- All new code remains untested during this turn by owner request; no runtime/CI PASS claimed. Historical UTC P4.G3 and production UAT remain blocked. Accepted gates stay **8/24 = 33.3%**.

## Final local pilot activation hardening (code only)

Added `V2FinancePilotServiceProvider`, passively registered in `config/app.php`. It defines `v2-finance-read`, `v2-finance-post`, and `approve-v2-stock-finance` only when `APP_ENV=local/testing` and the explicit read API flag is enabled. Read is limited to existing superadmin role; write and stock approval additionally require the separate write flag. Every request still checks active project ownership and isolated database. In production or when flags are false, the provider does nothing. Existing V1 role/permission definitions remain unchanged.

**No local pilot flag was turned on, no stock approval was granted, and no test suite or migration was executed manually.**
