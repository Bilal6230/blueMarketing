# BlueMarketing Database V2 — Five-milestone engineering delivery, 2026-10-30

**Branch:** `upgrade/laravel-framework`. Old SQL dump privately analyzed; GitHub contains **schema only**, no INSERT rows, credentials, customer names or amounts.

## Milestone M1 — full legacy schema & correct source identity

**Done:** All **57** actual CREATE TABLE declarations, **133** ALTER TABLE blocks, all **40** existing FK definitions and PK/index/unique clauses captured as human-readable JSON in `database/v2_schema/`. Separately classified **54** unconstrained ID fields into **46 verified additional FK candidate mappings** and **8 intentionally non-FK business/polymorphic identifiers**. Corrected bank lookup (27 code ID values), misnamed site account relation and booking broker: `bookings.broker_id` references `subhead_accountings.id`, not `leads.id`.

**Important tenant exceptions:** Parent numeric IDs can exist yet project scope differ. Observed `leads.area_id` 7,976 cross-project pairs / 13,391 comparisons; `leads.follow_id` 8,022 / 13,391; `punches.user_id` 20 / 30. These are **not automatically corruption**, since lookups/users may be shared. No tenant-composite FK added for these unknown business policies. See `DATABASE_V2_57_TABLE_RELATIONSHIP_AND_TENANT_ACCEPTANCE_2026_10_30.md`.

## Milestone M2 — full clean Laravel V2 structural baseline

**Done:** `database/v2_migrations/` contains three isolated opt-in migrations:

1. `2026_10_30_000010_create_v2_bank_lookup.php` — actual `banks` table, 27-ID strict seeder `V2LegacyBanksSeeder`.
2. `2026_10_30_000020_create_v2_legacy_domain_baseline.php` — **56 legacy application tables** via Laravel `Schema::create` from the 57-table metadata (old `migrations` deliberately excluded). Original SQL columns/types, indexes and scoped uniqueness are source-captured, FK widths normalized to BIGINT UNSIGNED where required; adds **40 old + 46 additional** true parent-FKs plus site project account link; appropriate master soft-delete fields, NOT ledger soft deletion; adds separate `legacy_migration_history` preserving old 14 history rows for later import.
3. `2026_10_30_000100_create_v2_finance_posting_core.php` — posting batches/lines, seven source-specific physical FK link tables and exact journal-detail posting provenance, independent of overloaded `type_id`.

**Important:** `sites.project_head_subhead_id` is new and nullable, while legacy misnamed `subhead_accounting_id` is retained for source-data parity. V2 import must copy verified same-project mapping into the new column; do not overwrite the old historical value.

## Milestone M3 — real MySQL 8.4 full migration & integrity rehearsal

**Done in disposable GitHub Actions MySQL only:** `.github/workflows/laravel13-finance-safety.yml` contains a separate `database-v2-mysql` job with MySQL 8.4 container and isolated DB `bluemarketing_v2_ci`. It runs V2-only `php artisan migrate --path=database/v2_migrations`, seeds all 27 legacy bank IDs, verifies 69 tables and **at least 108 physical FK columns**, validates ledger/account BIGINT widths, correct booking-broker and bank/account/site references, performs a deliberately rejected invalid ledger FK insert with rollback, and exercises **complete isolated migrate:rollback + clean reapply**. Composite unique journal number and duplicate bank PK checks were added to this verification script.

**CI distinction:** The V2 MySQL job and V2 SQLite/Python step may PASS while the pre-existing general finance-safety job can still FAIL at its separate legacy finance regressions. Report both statuses independently; do not call all application CI green. Historical MySQL 8.4 **import** and reconciliation are NOT yet certified by this migration-only test.

## Milestone M4 — historical lossless import staging pipeline

**Implemented, not applied:** `tools/database_v2/v2_import_plan.py` uses verified source SHA256, original 57-table/40-FK metadata and single-source V2 registry for another 46 links to derive deterministic dependency order and generate **DATA-ONLY** SQL staging files. The source `migrations` INSERT is rewritten **only in staging output** to `legacy_migration_history`; the original `created_at`, `updated_at`, `deleted_at`, PKs, voucher fields, amounts, description and other column values are kept as in uploaded INSERT statements. Requires exact source checksum and a new directory outside Git before any sensitive output is emitted; cannot connect to any database, disable FKs, apply imports or auto-attribute ambiguous JV lines.

**Synthetic verification:** Python tests now cover 57 table dependency ordering, 40+46 FK classification, archived Laravel history, exact timestamp literal preservation, checksum mismatch and row-count failure. Test fixtures include legacy tables with no `id` column (e.g., password reset/pivots). **Unresolved:** deriving the new site account field, running actual 120,165 rows through an isolated MySQL import, preserving post-import auto-increment positions and full row/cash/ledger parity.

## Milestone M5 — finance write-path coverage & isolated JV posting

**Done as initial repository-wide scan:** 189/189 `app/**/*.php` files direct-token scanned; **96** ledger/raw-statement access candidates indexed across `docs/database_v2_scan/APP_LEDGER_SCAN_01..12.md`; critical `JV/CV/SV/CR/CP/PPR/BR/EC/ECP/stock/labour` source domains described in `DATABASE_V2_LEDGER_WRITER_MATRIX_2026_10_30.md`. Identified hidden dynamic `DB::table($table)->update` routes in finance/mobile approvals, which bypass model events, and current posted CR/CP editing. Existing unsafe legacy voucher action guards unchanged.

**Implemented:** `V2JournalPostingWriter` is **not wired to production routes**. It uses persisted journal detail IDs, project-scoped accounts, atomic 2+-line balance checks, new typed FKs, stable request-key idempotency and strict amount/account verification on retry; isolated SQLite regression suite exercises rollback and duplicate source protection. Full route/query-builder alias/SQL/jobs semantic review and all family-specific writers are **not** certified.

## Safety and remaining production gates

- No application V1 finance code was modified; no user dump committed; no old MySQL, local, staging, live or production data connected to or changed.
- **Do not execute** migrate:fresh on `bluenew`, `u429032065_dev`, staging, or production. V2 migrations are in an explicit isolated directory and guard DB identity.
- Formal acceptance checklist lives in `DATABASE_V2_REBUILD_ROADMAP.md`. Code completion differs from historical import, route refactor and production readiness.
- **Next milestones:** (1) exact indirect/raw financial writer certification, (2) typed one-source exclusivity and service refactor for all voucher families, (3) import data into a disposable MySQL 8.4 DB with no FK disable, and (4) 120,165-row lossless timestamp/money parity, detailed JV financial reconciliation and user-reviewed exceptions before cutover.
