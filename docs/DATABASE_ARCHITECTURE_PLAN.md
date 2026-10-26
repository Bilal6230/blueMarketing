# Database Architecture and Migration Strategy

**Status:** planning / audit; NOT an approved migration script. **Updated:** 2026-10-26.

## User's requirements
- Complete application/database schema and relationship audit; proper FK constraints and migration definitions.
- Some columns may have been added directly in phpMyAdmin/MySQL; compare physical schema with migration definitions.
- New clean installs should have a coherent **complete** table definition rather than a fragmented historical patch sequence.
- Existing rows must be preserved: application-initiated hard deletion forbidden for business records; prefer soft deletion. For approved financial entries, prefer append-only corrections and reversal/void semantics.
- New IDs/columns/FKs only where justified and proven compatible with existing data.
- Support future data import without conflicts after an isolated fresh schema test.

## Why NOT “migrate:fresh then import full SQL dump” blindly
`migrate:fresh` destroys all tables in the target database; never execute on real/shared databases. A full `mysqldump` normally includes CREATE TABLE/DROP TABLE/ALTER definitions, which may overwrite or conflict with fresh migrations and bring old constraints back. A data-only migration requires stable IDs, compatible column mapping, FK-safe import order, detection of orphans/duplicates and post-import reconciliation. Disabling FK checks is not an acceptable substitute for validation.

## Two-track migration model
**A. Clean-install baseline**: accurate canonical create-table definitions (columns, nullability, types, defaults, indexes, soft-delete timestamps and valid constraints), reproducibly created on a disposable database. Baseline migration consolidation is a deliberate schema/version strategy, not retroactive mutation of production history.

**B. Upgrade on existing databases**: separate additive/versioned migrations and reviewed data backfills. Never rewrite the meaning of historical migration records already applied in environments. Avoid unconditional `Schema::create` on tables that already exist. Compare physical schemas and migration history before altering anything.

## Financial invariants to verify
- Identify the authoritative posting source (general `ledgers`, `customer_ledger`, voucher detail lines) and document the ledger posting lifecycle.
- Atomic multi-table financial actions using DB transactions, including row locks/idempotency where needed.
- No destructive cascading deletes from customers/projects/users to booking, voucher, ledger or audit history.
- Deleting financial entries should be controlled **void/reversal**, with reason, who, when and immutable original.
- Debit/credit reconciliation, account/project boundaries, voucher numbering uniqueness and precision; audit before adding unique indexes.
- Explicit FK naming, compatible unsigned/signed types, indexes, nullable status and on-delete behavior; existing orphans must be resolved by an approved plan BEFORE enabling constraints.

## Existing evidence (preliminary; do not assume this is final)
- User's `bluenew` DB: `ledgers`, `bookings`, `journal_vouchers`, `labours`, `landing_pages` all exist, while numerous historical migrations appear Pending.
- `2026_10_24_000003_add_landing_builder_fields` had not been run at last report; two JSON columns absent. Do not run automatically.
- Initial staging dump inspection reported approximately 57 tables, 40 FK constraints, 19 ON DELETE CASCADE actions, 10 tables containing `deleted_at`; counts require confirmation by reproducible audit before final certification.
- `customer_ledger.customer_id` create migration uses cascading delete to `leads` — conflicts with no-history-loss requirement.
- `ledgers` create migration lacks fields used by the current model (e.g. `customer_ledger_id`, `voucher_number`); candidate schema/migration drift.
- `journal_vouchers.voucher_number` types reported inconsistent (migration string vs staging dump integer): reverify exact current schema and data before design.
- `Booking`, `JournalVoucher` models use SoftDeletes; `Ledger`, `CustomerLedger`, `User`, `Lead`, `Labour` currently do not.
- Eloquent relations DO NOT create MySQL constraints. A FK's `ON DELETE` does not implement Eloquent soft deletion.

## Deliverables and gates
1. Per-table inventory: canonical name, record counts, columns/type/null/default, PK/index/FK, deleted_at, owner, business criticality.
2. Actual-vs-migrations diff and relationships diagram; annotate polymorphic/non-FK links.
3. Data quality: orphans, duplicates, invalid IDs, inconsistent amount precision, voucher imbalance, date anomalies.
4. Approved target ERD and hard/soft delete/reversal matrix (special case audit/session/pivot records).
5. Clean baseline test and data-only restore rehearsal on disposable database, with row-count/checksum reconciliation, migration status and automated finance tests.
6. Separate, explicitly approved live upgrade/backfill/deployment strategy with verified backups and rollback instructions.

**No migration, import, delete, Git merge, production change or repair has been performed by this document.**
