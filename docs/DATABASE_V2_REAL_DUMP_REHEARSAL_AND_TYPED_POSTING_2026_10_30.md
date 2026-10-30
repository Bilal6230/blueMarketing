# Database V2 — Real 120,165-row offline rehearsal, typed source exclusivity and MySQL import gate

**Date:** 2026-10-30. **Branch:** `upgrade/laravel-framework`. **Source evidence:** user-uploaded private `u429032065_dev.sql`, MariaDB 11.8.9, SHA-256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`.

## M1 — Source family exclusivity is now physically enforced

Migration `database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php` now creates `finance_posting_source_claims` (unique posting batch + canonical source kind). Each of seven source-specific tables uses a **composite foreign key** referencing the same batch/kind claim. Real MySQL 8.4 enforces seven per-table `CHECK (source_kind = '<family>')` constraints. This prevents a numeric ID collision such as `stock_entries.id == journal_vouchers.id` from being recorded as both source families for one posting batch.

**Limit:** Database now enforces **at most one typed source family**, but an unreferenced source claim can be created without a child in raw SQL. The V2 journal writer inserts the claim + typed child + detail lines in ONE transaction; V2 report reader verifies that the actual typed child exists and fails closed if missing. Other family writers need equivalent transaction behavior and project ownership review.

Schema tested on disposable **MySQL 8.4**: **70 tables, 116 FK-column references, >=7 source CHECK constraints**. Older documents citing 69/108 describe the pre-claim schema and are superseded here.

## M2 — All real historic rows imported and round-tripped privately in disposable SQLite

Private, **in-memory SQLite compatibility mirror** created from original 57 source table/column specifications and 40 declared + 46 verified additional FK candidate parent relationships. All original SQL tuples were parsed and inserted parent-first, and compared on **all original columns** using per-table multisets (duplicates and NULL retained). Ledger, customer, booking and voucher data remained untouched in source.

| Real source verification | Result |
|---|---:|
| Original tables | **57** |
| Original records | **120,165** |
| Re-imported disposable SQLite mirror records | **120,165** |
| Legacy parent FK relationships checked | **86** |
| SQLite `PRAGMA foreign_key_check` violations | **0** |
| Row multiset parity mismatched tables | **0 / 57** |
| Verified derived sites project-account links | **7 / 7** |
| Historical Laravel migration rows archived separately | **14** |
| Original SQL/source databases modified | **No** |

Evidence generated offline from the original user-uploaded SQL, without any GitHub/customer row upload. Reproducible standard-library entrypoint is `tools/database_v2/rehearse_legacy_in_sqlite.py`; synthetic tests use the same verified 57-table metadata.

**Hard limitation:** SQLite in-memory TEXT/foreign-key compatibility rehearsal does **not** establish that all 120,165 rows have been inserted into new **MySQL 8.4**. Strict MySQL typing, charset, collations, original MariaDB SQL modes, max packet, timezone interpretation and 120,165-row MySQL ledger/finance report parity require the real isolated MySQL import before P4.G4/P6 acceptance.

## M3 — Actual MySQL 8.4 import executor verified with fake historical records

`tools/database_v2/v2_import_plan.py` now produces checksum-verified, parent-first **data-only** staging files, keeps source IDs, `created_at`, `updated_at`, `deleted_at`, money and voucher fields, and redirects only `migrations` inserts to `legacy_migration_history`. The new **`tools/database_v2/import_staged_mysql.php`** is a separate, explicitly invoked importer, not an application route or regular Laravel migration. It enforces:

- Source SHA-256 exactly matching the approved dev export, unless a synthetic fixture is running under isolated CI-only `--ci-synthetic` access.
- Exactly 57 verified target tables, manifest/file/statement SHA256, 40+46 known parent edges, expected row count, no old `migrations` overwrite, and staging location outside Git.
- Read-only preflight unless `--apply` is deliberately given. Apply requires MySQL + local/testing environment + a `bluemarketing_v2_*` database that is *not* dev/staging/live/prod/client.
- Physical FK checks **enabled**. Existing 57 target tables must be empty; the 27 bank lookup IDs must already be seeded; all INSERTs in **one transaction**, with exact per-table row counts and full rollback on errors. New `sites.project_head_subhead_id` is set only after matching source account/project IDs.
- Unhandled SQL exceptions return **nonzero exit** and do not print raw private SQL/record data in errors.

GitHub Actions disposable MySQL 8.4 job passed an **invalid source account FK** fake import that rolled back all rows, followed by a **valid fake two-record import across 57-table manifest** that retained original fake ID/time/text and archived old migrations separately. This tests actual MySQL import execution/rollback but **NOT the user's original 120,165-row MySQL import**, because the confidential dump was never uploaded to GitHub or CI.

## M4 — Full-repository indirect writer search and safety classification

- `tools/database_v2/audit_ledger_writers.py` scans **360** application/controller/service/command/database/route/Blade PHP/SQL files and identified **71 files with 291 syntax matches**, including **39 dynamic builder/connection matches**.
- All 39 dynamic matches are classified in `DATABASE_V2_INDIRECT_LEDGER_ACCESS_CLASSIFICATION_2026_10_30.md`. Main true risk: web `Finance/VoucherController::approveAdmin` dynamic ledger table UPDATE and MobileApprovalController `applyPendingUpdate` dynamic UPDATE (the latter has financial-field forbiddance).
- Legacy `LedgerController::update`, booking PPR/sales/extra-charge, labour CP/JV and stock-generated JV sources remain V2 refactor blockers. 291 is not the number of writers; SQL aliases, authorization and route reachability still require semantic regression.
- Historical financial forensic report `DATABASE_V2_HISTORICAL_JV_RECONCILIATION_2026_10_30.md`: **988** real JV headers, **914** exact project/amount candidate multiset matches, **74** requiring review, **124** not-matched candidate detail legs and **2** extra candidate ledger legs, **8** header-vs-active-detail total inconsistencies. These are **not proven source attribution**; no automatic repair authorized.

## M5 — V2-only report source identity

`app/Services/Finance/V2PostingReportReader.php` reads posting lines with their canonical source claim and the **actual typed source header ID**. It rejects invalid date periods, missing source children, unclaimed lines and lines linked to a different project account, rather than showing partial/misleading balances. Added isolated SQLite regressions for genuine typed header, project/date filtering and both tamper cases. Existing production reports are NOT switched until V2 cutover UAT and parity.

## Verified progress and gate boundaries

- **P2.G2 ACCEPTED (design+physical invariant):** source-kind exclusivity, typed FKs, idempotent JV writer and reversal/immutable architecture policy. Other family writer implementations not done; P5 stays open.
- **P4.G2 ACCEPTED (source import ordering):** actual 57-table parent-first acyclic graph, 86 parent edges, original IDs retained and complete 120,165-row in-memory round trip. Two-pass cycle resolution is NOT required because the verified FK graph is acyclic; this is **not MySQL source-data acceptance**.
- **P4.G1/G3/G4 remain OPEN** pending owner-approved historical exceptions and actual MySQL 8.4 private data import plus independent row/finance parity.
- **P1.G3 remains OPEN** pending complete semantic writer call-graph/route certification, despite broader source scan.
- Existing CI may remain overall RED at the older finance suite even when the independent V2 SQLite and MySQL jobs PASS. Do not equate these separate results.
- **No production, staging or existing local financial database changed; no private SQL dump committed.**
