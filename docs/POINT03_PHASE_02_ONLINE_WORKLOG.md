# Phase 02 — Online Transaction Management — Milestone 02.1

**Source:** `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md`, section F.
**Status:** CODE IMPLEMENTED; browser PASS pending.
**Phase 01:** Owner browser PASS 2026-11-10.

## Milestone 02.1 scope
- Dedicated `finance/online-transactions` read-only list and receipt/payment source intake.
- Fields: transaction reference, date, amount, sending/receiving bank + account, sender, receiver, project Head/Child Account, type, purpose, optional remarks, optional proof.
- Enforce project-specific active account mapping, required fields, 2-decimal positive fixed source amount.
- Normalized uppercase unique `project_id + reference_key` to block duplicate receipt/payment references even under concurrent saves.
- Store proof privately under `local` disk, accessible only via project-scoped authorized download.
- New online record is Pending and **does not touch customer_ledger/ledgers or bank voucher writers**. No unapproved confirmation action yet.
- Uses CR-style TomSelect, shared jQuery skeleton, AJAX list, SweetAlert2 confirmation/result, inline required validation, project-scoped type/status/account filters, pagination.
- Schema migration `2026_11_10_000005_create_finance_online_intakes.php` is additive and preserves existing online customer-ledger workflow. `finance_online_lifecycle_events` is a different legacy source; no migration or merging of that table.

## Browser acceptance gate
- Run migration on approved disposable/local database.
- User with read voucher sees register/sidebar; create voucher can add source.
- Receipt save Pending; Payment save Pending; original amount and all fields persist.
- No ledger/bank voucher created.
- Duplicate reference with different case, and repeated concurrent submit, rejected with no duplicate rows.
- Negative validations: missing mandatory, invalid amount/date, incorrect project account pairing, bad upload MIME/oversized upload.
- Account-select Head/Child TomSelect interaction; search/status/type filters, skeleton/pager, proof download (authorized only).
- Cross-project data isolation: IDs, filters, proof routes.
- **Owner must confirm 02.1 browser PASS before implementing 02.2 confirmation workflow.**

## Next milestone
02.2: Pending → Confirmed with mandatory bank statement/audit verification, confirmation actor/time, audit history and state locking. No automatic bank voucher posting.

## 2026-11-10 — Complete code milestone 02.1
- Implemented controller, migration, online register Blade, classic/v2 sidebar links and authenticated routes. Static verification: route endpoints, unique project/ref index, transaction type and account mapping validators, pending only, non-posting, private proof and returned proof download URLs. Browser test pending.
- Any bank confirmation/ledger voucher remains prohibited until separately approved 02.2/04 gate.
