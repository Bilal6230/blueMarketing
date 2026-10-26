# Read-only Audit Worklog & Decision Register

**Updated:** 2026-10-26. Keep new entries dated, evidence-based and append-only where practical.

## 2026-10-26 — Documentation and scope established
- User requested comprehensive schema/migrations/FK/soft-delete and data-integrity audit, plus user activity tracking and new-device/IP/location-based OTP verification.
- User supplied full staging SQL dump in the chat; not to be uploaded to Git or embedded in this documentation.
- Confirmed local environment reports a large set of migrations Pending though corresponding business tables exist.
- Local checks reported missing landing-page builder fields, and user explicitly deferred running the corresponding migration.
- Cross-checked selected repo files: `app/Models/Ledger.php`, `CustomerLedger.php`, `Booking.php`, `JournalVoucher.php`, `User.php`, `Lead.php`, `Labour.php`, `StockItem.php`; finance/login controllers; several baseline migrations.
- Findings recorded in `DATABASE_ARCHITECTURE_PLAN.md` are **preliminary**; no complete per-table/per-row inspection yet, and no claims of integrity certification.
- Decision: READ-ONLY analysis first; no migration or code refactor without risk assessment and authorization. Preserve finance history and production.

## Backlog — not yet verified / not implemented
- [ ] Parse and validate uploaded SQL structure against each tracked migration.
- [ ] Table-by-table physical columns/indexes/constraints and source-of-truth mapping.
- [ ] Record-level orphan/duplicate/ledger and voucher reconciliations using non-mutating tools.
- [ ] Search all controllers, services, repositories, jobs, commands and raw SQL for write/delete and bypassed model events.
- [ ] Inventory currently missing/extra direct-database columns vs create migrations and patch migrations.
- [ ] Design and review all FKs, unique indexes, soft-delete exceptions and accounting reversals.
- [ ] Build clean schema on isolated DB; rehearse **data-only** transfer with preserved identifiers.
- [ ] Implement activity logs, device trust, OTP and tests after separate approval.
- [ ] Re-run automated tests and staging regression checks.
- [ ] Draft production deployment, backup, restore and rollback plan.

## Change tracking template
`YYYY-MM-DD | Area | Finding/Decision | Evidence (file/table/query) | Code commit | Tests | Remaining risk | Next task`

## Not authorized
- Production deploy/merge to `master_live`.
- Running any migration or modifying a real/staging database.
- Destroying or rewriting finance/accounting data.

## 2026-10-26 — Phase 1B reproducible snapshot scan
- Inspected attached SQL snapshot read-only (57 table definitions, 40 FK constraints, 19 CASCADE actions, 394 INSERT statements). No DB connection or mutation.
- Compared critical finance, booking, user and stock columns to selected migrations and models; verified structural drift and soft-delete inconsistency.
- Detailed observations and remaining work: [PHASE1B_SCHEMA_FINDINGS.md](PHASE1B_SCHEMA_FINDINGS.md).
- Next: complete all-table migration matrix and read-only data integrity checks. No production authorization.
