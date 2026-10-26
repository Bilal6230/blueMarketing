# Phase 1W — Commission Listing and Voucher Number Helper Audit

Date: 2026-10-26 | Read-only source review | `upgrade/laravel-framework`

## Verification received
After pulling `4c46aa4`, the user ran the two real `LedgerController::fetch_data_url` SQLite tests: **1 passed / 1 intentionally RED; 8 assertions**. Genuine JV header positive control displays `JV-9002`; ambiguous numeric collision fixture displays `JV-9001` instead of stored `JV-7001`. This is *fixture evidence*, not proof any actual production row is a CV.

## Inspected code findings

1. `CommissionVoucherController::index` uses `CommisionVoucher::latest()->where('project_id', getSelectedTown())` and, on AJAX, formats header `voucher_number` as `'CV-' . get_jv_number($voucher->voucher_number)`. This is **independent of the LedgerController JV type_id lookup**. It filters by voucher_number/reference/date/debit and returns DataTables JSON. Do not use a ledger display regression to claim commission listing is wrong.
2. `get_jv_number($id)` in `app/Helpers/AppHelper.php` simply left-pads to seven characters using `str_pad($id, 7, '0', STR_PAD_LEFT)`. It **does not query JV tables**, resolve source identity or validate whether an entry belongs to a real JV. Same helper reused for CV listing and JV print formatting.
3. `getLastJvId()` currently returns the **maximum JournalVoucher header ID in the selected project**, including soft-deleted headers. The legacy `CommissionVoucherController::store` (now guarded by HTTP 409) derives a new Commission Voucher `voucher_number` from `getLastJvId() + 1`. This ties commission numbering to a *different table's numeric header ID*, a distinct design issue. Verify numbering requirements and historical examples before redesigning.
4. `getLastJvVNumber()` instead looks up a `JournalVoucher` header of `type='JV'`, ordered by ID and including soft-deleted, returning its `voucher_number`. It differs from `getLastJvId()`; neither helper guarantees atomic serial allocation under simultaneous requests. Concurrency is a **potential risk not proven with a test**.
5. `CommissionVoucherController::index` AJAX query computes `$vouchers = $query->skip($start)->take($length)->get(); $total = $query->count();`. Applying count to the same builder after pagination methods might yield incorrect `recordsTotal/Filtered` depending on query execution semantics; **not verified**, prioritize isolated pagination regression only if needed.

## Identity/read compatibility requirements

- The LedgerController report `JV` number lookup, CV header index and JV voucher print are **three separate presentation paths**, despite shared string formatting.
- Do not rewrite legacy `ledgers.type/type_id` in bulk; historical CV and JV provenance is ambiguous in source fields.
- Do not infer commission voucher numbers from `journal_vouchers.id` when implementing new numbering; determine approved business serial convention first.
- Design new typed source identity, reversible postings, and presentation fallback as in Phase 1K; preserve all source rows and recorded totals.

## Next recommended checks

1. Add separate test coverage for CV listing formatter and independent numbering to guard real behavior.
2. Add authenticated/unauthenticated web-route checks using isolated permission fixtures; existing Phase 1Q bypasses middleware when asserting HTTP 409.
3. Examine other ledger writes/reads and export paths before any schema or report changes.
4. Keep intentional source-contract and ambiguous identity regressions visible until remediation passes reconciliation. Current test suite is intentionally not green.

No MySQL data, migrations, application business logic, `master_live` or live deployment changed in this phase.
