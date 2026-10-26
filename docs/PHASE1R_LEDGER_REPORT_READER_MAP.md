# Phase 1R — Financial Ledger Report Reader Map

**Date:** 2026-10-26
**Mode:** read-only source evidence; no financial mutations, migration or deployment.

## Verification received
Phase 1Q `VoucherSafetyHttpRouteTest`: **2 passed / 17 assertions** on user Laragon, after pulling commit `d048614`. The tests exercise actual URLs with middleware disabled and independently check declared permission middleware. **End-to-end authenticated permission behavior is not yet tested.**

## Inspected sources

| Source | Observed reader/writer behavior | Risk / next verification |
|---|---|---|
| `app/Http/Controllers/LedgerController.php` ~lines 1109–1150 | Active/project-scoped ledger query is used in a DataTables response. For `$ledger->type === 'JV'` it looks up `JournalVoucher::where('id', $ledger->type_id)` and **overrides display voucher number** with the matched JV header's `voucher_number`, falling back to ledger's own number when no header found. | An historical CV-created `type='JV'` ledger could display an unrelated Journal Voucher number if its numeric `type_id` collides. **Source-level possible misattribution, not yet reproduced in isolation or established in production.** Preserve raw row provenance. |
| `app/Http/Controllers/CommissionVoucherController.php` `store` | Behind HTTP 409 guard, legacy code creates CV header but writes `Ledger.type='JV'` and `type_id=CV header ID`. | Core reason report reader cannot safely infer voucher kind from legacy `type/type_id`. |
| `app/Http/Controllers/CommissionVoucherController.php` `update` | Behind HTTP 409, legacy code deletes `JV` rows by header numeric ID and inserts `JV` rows keyed to detail ID. | Legacy references are inconsistent; four source-level RED checks intentionally remain. |
| `app/Http/Controllers/JournalVoucherController.php` `store` | Writes `Ledger.type='JV'` and `type_id=JV header ID`. | Genuine JV and historical CV entries can share ambiguous `JV` reference space. |
| `app/Http/Controllers/LedgerController.php` ~line 803 | JV type mentioned in a comment. | Not actionable as an active report reader by itself. |
| `app/Http/Controllers/ReportController.php`, `AccountingController.php`, `Finance/VoucherController.php`, `Api/V1/Mobile/MobileReportsController.php` | Inspected code for literal JV/CV or type ID occurrences. No other exact literal JV/CV resolution observed in inspected files. | **Not an exhaustive whole-repository dependency search**: helper functions, Blade views, query scopes, model relations, raw SQL, or indirect readers may still exist. |

## Design constraints before changes

1. Do NOT bulk replace legacy `type='JV'` rows with `'CV'`, or resolve all historical `JV` ledger `type_id` values against `journal_vouchers`.
2. Determine a reliable typed source identity (new postings) and separately classify historical rows without changing their amounts or deleting audit data.
3. For historical ambiguous references, avoid confidently displaying a resolved external voucher identity unless supported by unambiguous provenance. Reconcile display totals and label changes in a disposable environment first.
4. Add an isolated regression around **display_voucher_number** where CV and JV header IDs collide, then redesign display with explicit provenance. Protect user-facing financial accuracy.
5. Expand map to templates, helpers, models, voucher prints, exports and report queries. Separate read-only display behavior from any posting logic.

## Work gates

- **Completed:** Phase 1Q user verification: 2 passed / 17 assertions.
- **Completed in this phase:** limited controller/report reader audit, this mapping document.
- **Pending:** run full suite after latest guards, end-to-end authentication/authorization fixtures, display-number collision regression, broader reader inventory.
- Production DB, `master_live`, migrations and financial code remained untouched.
