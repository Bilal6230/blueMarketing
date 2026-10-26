# Phase 1U — Voucher Print, Report and Export Reader Inventory

Date: 2026-10-26. **Read-only limited source audit.** Branch: `upgrade/laravel-framework`.

## User verification

Phase 1T documentation commit `0d03512` successfully pulled. The actual `LedgerController::fetch_data_url` DataTables ID-collision regression previously reached its display assertion: expected `JV-7001`, received `JV-9001` in isolated SQLite (1 failed, 5 assertions). Not proof of any production row's real CV origin.

## Direct evidence from inspected code

| Path | Mechanism | Implication |
|---|---|---|
| `LedgerController::fetch_data_url` | For ledger `type='JV'` resolves `type_id` to `JournalVoucher.id`, replacing displayed voucher number if header exists | Real isolated endpoint regression shows possible mistaken label when references overlap; code cannot distinguish historic CV-posted JV rows |
| `resources/views/admin/finance/reports/show_ledger.blade.php` | AJAX to route `fetch-data-url`, column `display_voucher_number`; also enables DataTables print export | The erroneous display value can propagate to the ledger screen and its DataTables print presentation, if the same row is returned |
| `resources/views/admin/reports/vouchers/print_journal_voucher.blade.php` | Displays header voucher-number via `get_jv_number($voucher->voucher_number)` for ordinary JV, raw integer for `SV` | Separate numbering semantics; must test separately rather than forcing a universal display-number rule |
| `resources/views/admin/reports/vouchers/commision_index.blade.php` | DataTables uses `voucher_number` field of commission index records | Voucher listing may use a distinct source, not necessarily the ledger display callback; trace controller response before asserting mismatch |
| `resources/views/admin/finance/voucher/print-eng.blade.php` | Prints ledger/voucher `voucher_number` field and conditional CR/CP/BO captions | Different voucher-type print path, do not assume JV logic applies |
| `resources/views/admin/finance/reports/details_index.blade.php` | No matching JV/CV/type-id/voucher-number references found in inspected file | Negative lexical finding only, NOT proof of lack of indirect data dependencies |

## Safe read policy design considerations

1. Preserve raw immutable fields and historical voucher numbers during remediation; never mass update ambiguous `JV/type_id` records.
2. For **new** entries, durable typed source provenance and posting-batch identifiers would support deterministic lookup (schema needs separate approval).
3. For **legacy** entries, do not infer CV vs JV based solely on numeric ID. Label uncertain cases explicitly only after reconciliation and UX review.
4. Protect print/export screens from silently reusing an unverified header voucher number. Changes must be tested against both genuine JV headers and CV collision scenarios.
5. Full dependency inventory requires broader search through controllers, helpers, Blade views, DataTables backends, exports and any raw SQL. This phase is intentionally a **limited initial map**.
6. Transaction balances and amounts must not change when modifying any presentation-only logic.

## Next gates

- Reproduce a genuine JV positive control and ambiguous CV-style collision in isolated report output side by side.
- Verify actual route authentication/permission middleware end-to-end, not only declarations.
- Trace commission index AJAX source and `get_jv_number` formatter, voucher print actions and exports.
- Propose a view-level "source uncertain" treatment with authorization and business sign-off before any behavioral changes.
- Current full suite remains intentionally RED on historic CV source guards and the collision diagnostic tests; do not skip these to claim green.

No application logic, schema, MySQL data, `master_live`, or production deployment was changed in Phase 1U.
