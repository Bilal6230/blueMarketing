# Laravel 13 continuation audit — 2026-10-27

Deployment status: **BLOCKED**. This report distinguishes observations made in this session from the supplied handoff. No production or historical database writes are authorized.

## Repository and environment

- Verified branch: `upgrade/laravel-framework`; HEAD `19f559e0e1bfdf385d08f7798785ba9cf0414a6e`.
- Initial working tree: only `?? vendor.before-laravel13-incomplete/`. Backup preserved.
- PHP CLI 8.3.0; installed Laravel 13.38.0; PHPUnit 12.5.40. PDO MySQL and PDO SQLite extensions are available.
- Sanitized configuration inspection confirms `.env` selects MySQL `127.0.0.1:3306/bluenew`, does not select `blue_l13_stage`, and contains no DATABASE_URL override. No credentials printed; `.env` unchanged.
- No `bootstrap/cache/config.php`, staging `.env` file, or staging/database credential environment variables were found. The user confirmed the password exists only in a previous PowerShell session's `$password` variable and directed this session to skip authenticated staging inspection. No private credential-file search, password reset or MySQL connection was attempted.
- Git 2.37.3 rejected workspace ownership. Command-scoped `-c safe.directory` and `GIT_CONFIG_COUNT` did not resolve it. A temporary Git config containing only the exact repository safe-directory entry, selected through process-scoped `GIT_CONFIG_GLOBAL`, enabled read-only Git inspection without modifying user/global/repository configuration.
- `php artisan route:list --json`: 314 registered routes, exit 0. The earlier documented 319 count is not the current observation. `module:list` exits 0 with no module rows; this does not prove module discovery.
- `composer validate --no-check-publish`: valid, exit 0 (Git ownership warnings on that invocation).
- CI workflow inspected: PHP 8.3, SQLite in memory, targeted regression groups and a full-suite gate. GitHub connector lookup returned no PR-triggered runs for this HEAD; its documented PR-only filter cannot verify push-triggered CI. Current remote CI success is not independently certified here.

## Migration reconciliation

There are **55 repository migration files**. Current staging history, 60-table inventory, 17 history rows, and finance indexes cannot be independently checked without the restricted staging connection. The handoff reports those values; they are not substituted for live metadata.

| Class | Required evidence | Current conclusion |
|---|---|---|
| A: recorded migration | Exact filename in staging `migrations` | Unknown for each file; handoff reports finance migration recorded |
| B: existing table, missing history | Missing history row plus physical table and complete schema comparison | Previously reported; exact current membership unverified |
| C: genuinely unapplied | Missing history plus absent intended schema effects, including alter migrations | Unknown; never infer from Pending alone |
| D: schema drift | Column type/length/unsigned/null/default, indexes, constraints versus cumulative definitions | Repository/model mismatches confirmed below; current staging drift unverified |
| E: unsafe execution | Existing-object collisions or historical data updates in migration code | Concrete hazards confirmed below |

Confirmed repository hazards:

1. `2026_03_31_000001_add_site_id_to_journal_vouchers_table.php` is **not schema-only**. Its `up()` updates journal voucher `site_id` using project, description and optional account/credit matching. The update runs even when the column already exists. Do not run it to repair migration history or automatically infer historical site ownership.
2. Create migrations, including finance metadata, use `Schema::create`. Replaying a create against an existing table is unsafe; a missing history record is not permission to replay it.
3. `2026_05_02_000001_add_amount_to_plots_table.php` and the landing-page alter migrations add columns without existence guards. Existing-column cases need exact reconciliation first.
4. The original ledger create migration lacks `voucher_number`, `customer_ledger_id` and `charge_type_id`. The JV create migration lacks `project_id` and `deleted_at`, while the current model uses project ownership and SoftDeletes. A successful suite using hand-built fixture schemas does not establish that historical migrations reproduce the operational schema.
5. The finance metadata migration intentionally refuses rollback. Do not use rollback as its recovery strategy.
6. The permission migration uses `PermissionRegistrar::$pivotPermission` and `::$pivotRole` as static properties. Reflection against the installed package confirms both are instance properties. This is another fresh-schema migration compatibility hazard; existing fixture-based permission tests do not establish that this old migration executes successfully. No migration executed or rewritten.

Further March site migration findings: an empty `subhead_accounting_id` removes the detail/account condition entirely; duplicate matching site names/accounts can make the first site returned by an unordered query claim a voucher, after which `whereNull(site_id)` excludes it from later sites. It does not require unique source evidence, voucher amount agreement, or an undeleted header. The column-existence guard does not guard the UPDATE. The current labour controller already assigns an explicit site ID on new vouchers when the column exists; that does not justify retroactive description matching. Separate any future approved schema-only addition from a separately reviewed historical attribution procedure; do not edit or execute the old migration as a shortcut.

Safe reconciliation procedure: verify `SELECT DATABASE()` independently on the restricted session; capture `migrations`, INFORMATION_SCHEMA TABLES/COLUMNS/STATISTICS/KEY_COLUMN_USAGE/REFERENTIAL_CONSTRAINTS for the exact staging schema; compare all 55 migrations' cumulative effects, including data effects. Classify A–E per file, with overlap where applicable. Inspect engines, collations, signedness, defaults, index prefixes and cascade rules. No automatic history insert is justified by table existence alone.

Only after this matrix is complete can an exact, separately approved history-reconciliation or additive schema proposal be prepared. Do not run blanket migrations, mark history executed, or change old migration semantics in this audit.

## Historical finance evidence

The following is **handoff evidence only**, awaiting a fresh restricted staging scan:

| Review order | Project | JV headers | Unverified JV-type ledgers |
|---|---:|---:|---:|
| 1 | 6 | 672 | 1430 |
| 2 | 5 | 186 | 749 |
| 3 | 3 | 122 | 692 |
| 4 | 1 | 0 | 2 |
| 5 | 2 | 0 | 0 |
| 6 | 4 | 0 | 0 |
| Total | All six | 980 | 2873 |

Existing `LegacyVoucherCollisionInventory` and `LegacyVoucherEvidencePacket` were reviewed. Their numeric-header candidate categories are not independent provenance evidence. Packets currently contain IDs and voucher references, not full detail/amount reconciliation. A confirmed audit gap was fixed: inventory previously excluded any ledger having an attribution row, whereas readiness additionally evaluates project/source/evidence fields. All three inventory modes now use the same apparent-attribution predicates as readiness. Invalid metadata remains visible; no attribution is created or certified. Four new isolated regression cases cover mismatched project, mismatched source ID, unsupported source kind and unsupported evidence kind, including CLI exit status and unchanged records.

Do not relabel candidate categories as the requested evidence classes:

- Strong independently verifiable evidence requires corroborated original source records/references and detail/account/project reconciliation, followed by the existing review/checkpoint process.
- Multiple plausible candidates includes competing JV/CV origins; one numeric match alone does not establish a strong case.
- Missing evidence includes absent source/header/detail/reference support; absent numeric headers alone do not establish a financial error.
- Contradictory evidence requires observed conflicting project, source, reference or detail evidence, not an assumed match.
- All unresolved cases require manual finance review. No counts for these five classes have been newly established in this session.

After credential availability, independently verify the selected database before each finance command. Run the existing pinned staging preflight, then `finance:audit-legacy-cases PROJECT --summary --max-rows=10000` in order 6,5,3,1,2,4. Check each scan's `incomplete` flag. Use existing bounded evidence/review tools for selected cases; keep raw sensitive review material out of Git/chat. Preflight or legacy-audit exit 1 can mean unresolved cases, not command failure. Never insert attributions from these summaries.

## MySQL-specific release gate

Source review confirms the metadata migration declares nonnullable unique `ledger_id` and unique `(project_id, source_kind, request_key)`. `FinanceSchemaPreflight` rejects MySQL prefix keys, nullable unique-key columns and incorrect column sequences. This is not full type, collation, engine or foreign-key certification.

`DurableFinancialRequest` uses a transaction, project row lock, request row lock, unique scope, payload-hash check and replay voucher validation. Concurrency correctness on actual MySQL remains untested here. The configured connection uses `utf8mb4_unicode_ci`; actual index collation and case behavior must be captured and tested, including mixed-case request keys. No collation change is proposed without data/compatibility review.

Required disposable MySQL test evidence before concurrency approval:

1. Separately authorized database and restricted test account, neither `bluenew` nor `bluemarketing_l13_staging`; independently verified session identity and privileges. No such target/credentials have been supplied.
2. Reviewed minimal fixture schema with actual production-compatible engines, column types, collation and full-length constraints. Never run the repository's blanket migration set as a shortcut.
3. Two independent sessions submitting the same project/source/key/payload: exactly one request, voucher, detail/ledger posting set and replayed voucher identity.
4. Same key with different payload, same key in different project/source scopes, lock wait/deadlock retry, callback failure rollback, missing/soft-deleted replay target and unique-constraint enforcement.
5. Capture counts, transaction outcomes and constraint metadata without customer data. SQLite results and source-level locking assertions cannot replace these observations.

## Remaining release requirements

Restricted staging credentials; fresh schema/history comparison and historical evidence counts; independently reviewed ownership and financial reconciliation; approved disposable MySQL concurrency run; manual authenticated role-based UAT for CRM, booking, customer ledger, JV/CV, labour, stocks and landing pages; backup checksum/location and restore verification; explicit release approval. Previously reported backup/restore success was not rerun or independently inspected in this session.

No operational migration, historical ledger/source update, attribution, permission change, commit, push, merge or deployment was performed. Existing automated tests create fixture schemas and metadata only in SQLite memory.

## Regression evidence and code changes

- Initial full `php vendor/phpunit/phpunit/phpunit --no-progress`: 445 tests, 1742 assertions, exit 0; one deprecated XML-schema warning.
- Diagnostic `--filter MysqlUniqueIndexPreflightTest --display-phpunit-deprecations --no-progress`: 4 tests, 4 assertions, exit 0; confirmed the warning was configuration schema age.
- Ran PHPUnit's `--migrate-configuration` on a temporary copy for review, then changed only the obsolete coverage/source section in repository `phpunit.xml`. Test discovery and forced SQLite settings are preserved.
- Post-change focused `php vendor/phpunit/phpunit/phpunit tests/Feature/Finance/FinancialSourceMetadataIntegrationTest.php --no-progress --display-phpunit-deprecations`: 74 tests, 256 assertions, exit 0, no deprecation warning.
- Both changed PHP files pass `php -l` (exit 0).
- Full post-change `php vendor/phpunit/phpunit/phpunit --no-progress --display-phpunit-deprecations`: **449 tests, 1803 assertions, exit 0**, no deprecation warning (61.576 seconds reported by PHPUnit).

Test commands used process-scoped `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty `DATABASE_URL`, array cache/session and sync queue. The full/focused regression runs also replaced MySQL credentials in the child process with a non-operational test username and empty password. PHPUnit force-selects SQLite memory, and no cached application configuration was present. No `.env` edits.

## Final pre-commit review — 2026-10-27

Reviewed the complete tracked diff and every new task file. No candidate-category predicate, source writer, permission guard or migration changed. Query values remain bound, project scope and limits remain in place, and the new exclusion predicate can only retain additional review candidates compared with the original ledger-ID-only exclusion. The four invalid-attribution cases remain visible in all review modes. This avoids the identified false negatives without treating those cases as confirmed financial errors.

Remaining code-review limitations: apparent attribution still does not independently prove the referenced header exists, the evidence is authentic, or balances reconcile. Unknown/partial metadata schemas may fail the query rather than produce a ready report. The inventory and readiness predicates are duplicated and must remain aligned in future edits. MySQL collation, query plans and concurrent snapshot consistency remain unverified; none is certified by SQLite tests. These are explicit limits of the preliminary report, not approval to auto-attribute history.

Found and fixed a test-isolation gap: XML environment settings alone do not override cached Laravel configuration and can lose to inherited server variables. `tests/Support/DatabaseIsolation.php`, wired into `tests/CreatesApplication.php`, now refuses cached configuration before loading it, validates the effective testing/SQLite-memory configuration before providers boot, rejects URL/read/write overrides, and removes other configured connections. XML additionally forces testing and an empty DATABASE_URL. No cache or environment file is edited/deleted. This guards the configured application suite; it is not a sandbox against test code deliberately opening raw PDO connections or replacing the guard. Repository inspection found no such raw connections or alternate application bootstrap in the suite.

Ten new no-database guard cases cover unsafe driver/default/environment, original/staging names, URL/read/write overrides, cached configuration and removal of non-SQLite connections. Guard plus finance tests: 84 tests / 269 assertions, exit 0. A deliberately hostile process environment selecting MySQL/bluenew was rejected: 459 tests discovered, 370 application-bootstrap errors, exit 2; no connection was opened. This is a negative isolation test, not a passing application regression run. Final safe-environment regression results are recorded in the worklog and latest roadmap snapshot.

Index inspection found zero staged files, hence no staged credentials, `.env`, SQL backups, customer data or vendor backup. Credential-pattern scanning of the task files found zero matches; manual diff review found only synthetic fixtures and permitted aggregate/technical evidence. Vendor backup and unrelated files preserved. No commit/push/merge or operational database access. Deployment remains BLOCKED for the requirements above.

## Repository migration inventory (static inspection only)

The following lists source operations, not staging execution status. A–D classification remains unverified for every row until restricted staging metadata is available. CREATE operations require existing-object checks; ALTER operations require column/index comparison. No migration was executed to generate this inventory.

| Migration | Source operation | Current staging history/schema |
|---|---|---|
| 2014_10_12_000000_create_users_table | CREATE: users | Unverified |
| 2014_10_12_100000_create_password_resets_table | CREATE: password_resets | Unverified |
| 2019_08_19_000000_create_failed_jobs_table | CREATE: failed_jobs | Unverified |
| 2019_12_14_000001_create_personal_access_tokens_table | CREATE: personal_access_tokens | Unverified |
| 2022_03_19_102456_create_permission_tables | CREATE: configured permission tables | Unverified |
| 2022_03_29_105225_create_settings_table | CREATE: settings | Unverified |
| 2022_10_22_145548_create_leads_table | CREATE: leads | Unverified |
| 2022_10_22_150000_create_project_head_subheads_table | CREATE: project_head_subheads | Unverified |
| 2022_10_22_164312_create_zones_table | CREATE: zones | Unverified |
| 2022_10_22_164343_create_areas_table | CREATE: areas | Unverified |
| 2022_10_22_195724_create_projects_table | CREATE: projects | Unverified |
| 2022_10_23_041047_create_project_zones_table | CREATE: project_zones | Unverified |
| 2022_10_23_140316_create_plots_table | CREATE: plots | Unverified |
| 2022_10_23_155559_create_facings_table | CREATE: facings | Unverified |
| 2022_10_23_160843_create_roads_table | CREATE: roads | Unverified |
| 2022_10_27_230651_create_lead_user_table | CREATE: lead_user | Unverified |
| 2022_10_29_180639_create_works_table | CREATE: works | Unverified |
| 2022_11_15_135624_create_expences_table | CREATE: expences | Unverified |
| 2024_01_14_150839_create_ledgers_table | CREATE: ledgers | Unverified |
| 2024_04_20_165837_create_bookings_table | CREATE: bookings | Unverified |
| 2024_04_20_172252_create_installments_table | CREATE: installments | Unverified |
| 2024_04_20_173235_create_booking_details_table | CREATE: booking_details | Unverified |
| 2024_04_25_221615_create_customer_ledger_table | CREATE: customer_ledger | Unverified |
| 2025_01_27_142048_create_journal_vouchers_table | CREATE: journal_vouchers | Unverified |
| 2025_01_27_143458_create_journal_voucher_details_table | CREATE: journal_voucher_details | Unverified |
| 2025_08_01_055838_create_dasticash_table | CREATE: dasticash | Unverified |
| 2025_08_08_101553_create_draft_ledgers_table | CREATE: draft_ledgers | Unverified |
| 2025_08_08_112841_create_labours_table | CREATE: labours | Unverified |
| 2025_08_08_163402_create_stocks_table | CREATE: stocks | Unverified |
| 2025_08_14_123002_create_hold_plots_table | CREATE: hold_plots | Unverified |
| 2025_09_23_235842_create_charge_types_table | CREATE: charge_types | Unverified |
| 2025_09_24_020119_create_pending_updates_table | CREATE: pending_updates | Unverified |
| 2025_09_25_142048_create_commision_vouchers_table | CREATE: commision_vouchers | Unverified |
| 2025_09_25_143458_create_commision_voucher_details_table | CREATE: commision_voucher_details | Unverified |
| 2025_11_13_025816_create_sites_table | CREATE: sites | Unverified |
| 2025_11_13_050518_create_labour_attendances_table | CREATE: labour_attendances | Unverified |
| 2025_12_01_031728_create_labour_ledgers_table | CREATE: labour_ledgers | Unverified |
| 2026_01_08_000000_create_account_types_table | CREATE: account_types | Unverified |
| 2026_01_21_043816_create_stock_items_table | CREATE: stock_items | Unverified |
| 2026_01_21_043844_create_stock_parties_table | CREATE: stock_parties | Unverified |
| 2026_01_21_043857_create_stock_entries_table | CREATE: stock_entries | Unverified |
| 2026_01_21_043911_create_stock_entry_lines_table | CREATE: stock_entry_lines | Unverified |
| 2026_01_21_043924_create_stock_bills_table | CREATE: stock_bills | Unverified |
| 2026_01_21_043937_create_stock_bill_lines_table | CREATE: stock_bill_lines | Unverified |
| 2026_01_21_043949_create_stock_movements_table | CREATE: stock_movements | Unverified |
| 2026_01_21_044002_create_stock_item_balances_table | CREATE: stock_item_balances | Unverified |
| 2026_01_21_044017_create_stock_counters_table | CREATE: stock_counters | Unverified |
| 2026_03_31_000001_add_site_id_to_journal_vouchers_table | ALTER: journal_vouchers; historical UPDATE | Unverified |
| 2026_04_20_180000_create_booking_vouchers_table | CREATE: booking_vouchers | Unverified |
| 2026_05_02_000001_add_amount_to_plots_table | ALTER: plots | Unverified |
| 2026_09_08_000001_create_booking_edit_audits_table | CREATE: booking_edit_audits | Unverified |
| 2026_10_24_000001_create_landing_pages_table | CREATE: landing_pages | Unverified |
| 2026_10_24_000002_add_drafts_to_landing_pages_table | ALTER: landing_pages | Unverified |
| 2026_10_24_000003_add_landing_builder_fields | ALTER: landing_pages | Unverified |
| 2026_10_27_000001_create_financial_source_identity_tables | CREATE: ledger_source_attributions, financial_posting_requests | Unverified |
