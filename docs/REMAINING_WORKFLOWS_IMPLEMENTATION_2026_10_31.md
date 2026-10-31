# Original workflow implementation checkpoint - 2026-10-31

Owner action: NONE for independent implementation. Owner database and `.env` are unchanged. This is guarded isolated-pilot code, not an activated production release or recovery of the missing historical JV rows.

## Delivered functional batch

- Original CR/CP cash creation now commits source, replay identity and balanced typed posting atomically. Server allocates numbers; conflicting retries, inactive accounts and duplicate references fail closed. CP bank clearance settles the selected existing receipt rather than creating a second cash payment.
- Bank instruments stay pending without GL posting. Pass (1) posts on the actual bank settlement date; Memo (2) remains active and can later pass; Cancel (3) deactivates without posting. An append-only event records each decision. Replaying an old memo after pass never restores the earlier state. Source fingerprints detect changes; failed posting rolls back the entire decision.
- Original labour payment creates one aggregate CP with exact worker-to-payment custody. Same-total/different-worker retries are rejected. Attendance JV uses authoritative locked attendance, explicit balanced allocation and exact attendance-to-journal FKs; attendance cannot be consumed twice. Payroll members cannot subsequently post as separate labour sources.
- Booking PPR preserves the actual receipt FK and instrument metadata. EC charge and ECP payment use explicit booking/charge/source FKs. Pending reservations and checked posted/reversed amounts prevent overpayment; existing extra-charge form aliases and bank-clearance action are supported.
- Original cash/accounting/booking/labour forms now collect request identity and approved account allocation. Client amount handling uses integer minor units and rejects malformed money. Pending instruments do not submit premature posting lines.
- Original ledger, party/head totals and cash voucher list read checked typed postings. Cash lists count source documents once rather than counting both GL legs; net amounts include reversals and date filters. Source-safe print has project/read authorization; typed rows cannot open legacy edit/delete actions. Cash JSON and print escape source text.

## Schema and activation boundary

New isolated-only extension: `database/workflow_migrations/2026_10_31_000010_create_original_workflow_custody.php`. It asserts an allowed isolated connection before DDL, requires the core/source schema, is repeatable and refuses destructive rollback. Six custody tables use restrictive FKs: `finance_original_customer_requests`, `finance_labour_payment_members`, `finance_labour_attendance_journals`, `finance_extra_charge_sources`, `finance_extra_charge_payment_sources`, `finance_bank_settlement_events`.

The extension is deliberately separate from the existing V2 baseline migrations and its historical table-count contract. Neither this extension nor the business/assignment archive migrations has been applied to the owner DB. Do not run a blanket migration against an unidentified database. C2 must rehearse the exact baseline, extensions and archives on an explicitly identified disposable MySQL target before activation.

Typed original reports explicitly state that they cover new finance postings only. They do not silently union unreconciled historical V1 balances. Historical financial reconciliation and approved opening balances remain C3/D3 work.

## Validation and remaining acceptance

Actual original HTTP regressions cover cash replay/conflicts, pending bank clearance/date/history/tampering/rollback, payroll worker membership/rollback, attendance custody, PPR/EC/ECP/reservations, report authorization/source corruption, form rendering, document counting, escaped print and reversal windows. The JavaScript harness executes the actual Blade inline script. Validation results are recorded in ACTIVE_DELIVERY_ROADMAP.md after the final full run. All local PHP tests use SQLite `:memory:` with synthetic fixtures; no real MySQL or browser/mobile UAT is claimed. CI workflow changes are local and unrun remotely.

B3 remains IN PROGRESS. Next implementation: remaining booking creation/SV posting and original lifecycle/report/export paths, including browser cancellation/draft handling. Then representative browser/mobile UAT (D2), isolated MySQL FK/concurrency rehearsal (C2), missing-JV ownership/parity investigation (C3), restore/reconciliation/cutover acceptance (D3). The accepted checklist remains 7/12; newly implemented subflows do not count as completed cross-application acceptance.
