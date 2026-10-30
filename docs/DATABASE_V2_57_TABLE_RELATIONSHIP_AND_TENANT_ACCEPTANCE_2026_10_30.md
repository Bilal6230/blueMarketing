# Database V2 — 57-table physical relationship acceptance and project-scope exceptions

**Verified:** 2026-10-30, read-only against uploaded `u429032065_dev.sql` (MariaDB 11.8.9). SHA-256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`. Exact GitHub branch `upgrade/laravel-framework`. No private row bodies/PII stored in GitHub.

## Coverage and source of truth

- **57** original SQL `CREATE TABLE` declarations and **all 120,165** exported rows inspected offline.
- Four human-readable metadata parts `database/v2_schema/legacy_columns_01.json` through `04.json` capture all **57** source tables and their exact legacy columns, nullability, defaults and data types without any personal rows. `database/v2_schema/legacy_constraints.json` captures **133** original ALTER blocks including primary/unique/index declarations and **40** declared FK definitions.
- Machine-readable `database/v2_schema/v2_verified_extra_fks.json` adds **46** source-specific, independently classified V2 FK candidates. **54** previously unconstrained `*_id` candidates therefore split into **46 actual source-key candidates + 8 deliberate business/polymorphic/unproven identifiers**. Avoid a foreign key on any of these eight without a separate typed design:
  `customer_ledger.type_id`, `draft_ledgers.type_id`, `ledgers.type_id`, `pending_updates.record_id`, `personal_access_tokens.tokenable_id`, `stock_movements.source_id`, `stock_movements.source_line_id`, `users.status_id`.
- All **40 original FKs** and **46 additional candidate numeric parent references** have zero missing parent IDs in the uploaded dataset when linked to their **correct parent**. This does *not* establish voucher/source ownership, valid accounting totals, or project equality.
- **Bank IDs:** `getPakistanBanks()` defines numeric IDs 1–27. Exported 1,345 customer and 46 booking voucher bank links (1,391 total) all resolve. New V2 `banks` lookup preserves the source IDs and avoids incorrect project-account FKs.
- **Misnamed labour site column:** legacy `sites.subhead_accounting_id` actually references `project_head_subheads.id` in `LabourController::createVoucher()`; 7/7 site mappings valid, zero account project mismatches. V2 both retains this original column as historical evidence and adds `sites.project_head_subhead_id` as a proper nullable FK; importer must populate derived column only after validated project equality.
- **Broker correct parent:** `Booking::broker()` returns `belongsTo(SubheadAccounting::class, 'broker_id')`. **163 non-null** historical booking broker values resolve to `subhead_accountings.id` (zero missing). A prior *provisional* `broker_id -> leads.id` hypothesis was **wrong** and is retired. Current V2 registry links brokers to `subhead_accountings`; no parent/project composite claim is made for this global accounting master.

## Project ownership differences detected (not automatically invalid)

All following counts compare a child `project_id` to a related parent's `project_id` on non-null keys. A mismatch may be expected for a shared lookup, assigned employee, or global/broker identity. Do not delete, deactivate, or silently remap these historical rows.

| Child link | Compared rows | Project mismatches | V2 disposition |
|---|---:|---:|---|
| `bookings.customer_id -> leads.id` | 198 | **0** | Direct physical FK; retain owner/project validation |
| `bookings.plot_id -> plots.id` | 198 | **0** | Direct physical FK; tenant rules may be enforced at service level |
| `booking_vouchers.booking_id -> bookings.id` | 81 | **0** | Direct FK, project-consistent in snapshot |
| `booking_vouchers.customer_ledger_id -> customer_ledger.id` | 81 | **0** | Direct FK, project-consistent |
| `booking_vouchers.customer_id -> leads.id` | 81 | **0** | Direct FK, project-consistent |
| `booking_vouchers.plot_id -> plots.id` | 81 | **0** | Direct FK, project-consistent |
| `customer_ledger.plot_id -> plots.id` | 1,851 | **0** | Direct FK, project-consistent |
| `project_head_subheads.plot_id -> plots.id` | 100 | **0** | Direct FK, project-consistent |
| `project_head_subheads.customer_id -> leads.id` | 100 | **0** | Direct FK, project-consistent |
| `sites.subhead_accounting_id -> project_head_subheads.id` | 7 | **0** | Actual labour site account FK |
| `leads.area_id -> areas.id` | 13,391 | **7,976** | Area may be shared/legacy tenant mapping; block composite project FK until policy reviewed |
| `leads.follow_id -> users.id` | 13,391 | **8,022** | User assignment may span projects; prevent unauthorized assignment at service level |
| `punches.user_id -> users.id` | 30 | **20** | User/project membership requires permission-aware mapping; do not assume `users.project_id` is exclusive |

**Additional verification:** 5,147 JV details' account projects matched the owning Journal Voucher's project in this snapshot for all checked rows; no mismatch detected. This is still NOT journal-detail-to-ledger reconciliation. Original `journal_vouchers` unique `(project_id, type, voucher_number)` had no observed duplicates in export.

## What the V2 Laravel migration actually does

- `database/v2_migrations/2026_10_30_000010_create_v2_bank_lookup.php`: real banks master (seed historical IDs 1–27).
- `database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php`: **56** source application tables recreated via Laravel `Schema::create`, with original PK/unique/index/column metadata, normalized source FK widths, 40 existing + 46 additional enforceable source links, one extra named sites-project-account FK, and a separate `legacy_migration_history` (old 14 migration history rows eventually imported here). Source `migrations` table is **not** restored over Laravel's own new migration table. Adds `deleted_at` only for appropriate masters; keeps financial ledgers non-soft-deleted for reversal-based policy.
- `database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php`: typed posting batches/lines, seven physically source-linked families, exact JV-detail ledger link. Cross-family exactly-one claim remains a separate explicit service+DB policy gap.
- All three are **excluded from normal `database/migrations` discovery** and require an explicit V2 path, isolated database naming gate and freshly created source-schema dependency checks.
- No V1 tables, migration history or historical data have been changed.

## Acceptance and limitations

**P1.G2 structural relationship dictionary: EVIDENCE COMPLETE.** This gate is a *schema/FK/candidate and project-scope evidence inventory*, not application behaviour or owner approval. P1.G3 (complete indirect write/read call-graph and route audit), P1.G4 (owner-approved business exception policy), P2.G4 (final architecture approval) remain separate blockers. Do **not** interpret numeric validity as universal tenant authorization or polymorphic source proof.

**MySQL execution:** the separate disposable MySQL 8.4 GitHub Actions job runs all V2 migrations, checks physical FK topology, bank identity, missing-ledger-parent rejection, rollbacks and schema re-application. See live CI result before stating this gate PASSED. Full historical imported row parity and real MySQL source reconciliation are pending.
