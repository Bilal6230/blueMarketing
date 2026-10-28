## 2026-10-28 verified structural compatibility results

The approved structural preflight completed with exit code 0 and all seven result sets. Identity was exact: database `bluemarketing_l13_staging`, authenticated account `blue_l13_audit@127.0.0.1`, port 3306 and identity gate 1. Terminal validation returned 42 scoped, 41 present, one absent (`expences`), all four gates 1, and `REVIEW_REQUIRED`.

The exact 35-target comparison is recorded in [the structural expectation manifest](STAGING_MIGRATION_STRUCTURAL_EXPECTATIONS.md): **19 COMPATIBLE, 13 INCOMPATIBLE and 3 UNKNOWN**. Compatibility means the original declared structure was observed; it is not execution-provenance evidence and does not authorize a history row. Every present scoped table is InnoDB/`utf8mb4_unicode_ci`; migrations did not specify those attributes.

Verified blockers include unsigned/signed and width drift in project/account relationships; required/nullable drift in project heads, bookings, customer ledger, dasticash, draft ledgers, labours and labour attendance; physical LONGTEXT versus declared JSON in pending updates and booking audit; and unexplained additive fields/FKs in ledgers, sites, labour ledgers and booking vouchers. `expences` is absent while the distinct active `accounting_expenses` schema is present; no rename/equivalence evidence exists.

The JV create is incompatible: staging has signed INT `voucher_number` and UNIQUE `(project_id,type,voucher_number)`, whereas the migration declares VARCHAR plus global UNIQUE `voucher_number`. This is a separate compatibility blocker. The commission-detail `down()` method still drops `journal_voucher_details`; it remains a separate destructive rollback blocker and was not modified.

The nine-table stock FK dependency chain matches its unsigned BIGINT relationships and delete actions. Eight stock tables are compatible; `stock_movements` is incompatible because its declared JSON column is physical LONGTEXT plus `json_valid`, not MySQL JSON. This does not prove migration execution or approve history repair.

Next gate: independently review the classification and prepare a hash-pinned, approval-only reconciliation manifest separating compatible history candidates, unknown-provenance research and forward schema remediation. No migration replay, history write or schema change is authorized. **Production deployment remains BLOCKED.**

---

## 2026-10-28 structural compatibility assessment prepared

The verified 55-row matrix was independently rechecked against the complete staging capture and repository filenames: 55 files, 17 exact history names, batches 14/1/1/1 for batches 1/2/3/4, 38 missing rows, 35 missing-history creates, three missing-history alters, and no orphan history entries. The 35 create migrations resolve to 34 physically present targets plus absent `expences`; no count correction was required.

Added [the structural expectation manifest](STAGING_MIGRATION_STRUCTURAL_EXPECTATIONS.md), which maps all 35 create declarations to columns, types, nullability, defaults, indexes, unique constraints and active foreign keys. No migration explicitly sets a table engine, charset or collation, so those are captured facts requiring explicit compatibility review rather than repository expectations. Laravel/runtime-derived names and historical cumulative changes are classified `UNKNOWN` unless independently resolved.

Added [the SELECT-only structural preflight](STAGING_MIGRATION_STRUCTURAL_PREFLIGHT.sql). It has seven result sets: identity, 42-table scope coverage, columns, indexes, foreign keys, checks, and final coverage/gate totals. Scope includes all 35 create targets, `accounting_expenses`, and six relevant dependency tables. It reads only identity functions and `information_schema`; it returns no business rows, amounts, voucher details or customer data. An identity mismatch suppresses result-set 2-6 metadata and ends with `BLOCKED_IDENTITY`. The terminal status can only be `REVIEW_REQUIRED` or `BLOCKED_*`, never `PASS`. Any identity gate other than 1, scope count other than 42, unexpected `expences` presence, missing one of the other 41 scope tables, missing/reordered result set, SQL error, or incomplete metadata is rejected.

Repository structural risks confirmed before execution:

- `expences` and `accounting_expenses` are separate conventionally mapped models. Active finance code uses the latter's richer fields; the former migration creates only an ID and timestamps and performs no rename. Their relationship is unresolved and structurally incompatible as declared.
- The legacy JV create declares a string globally unique voucher number, while prior captured staging evidence shows a numeric voucher number and a composite project/type/voucher unique index. This is a verified mismatch, not authorization to change it.
- Several migrations use integer relationship columns where dependencies commonly use unsigned BIGINT, omit foreign keys entirely, or leave proposed keys commented. Compatibility must compare the actual declaration, not comments or model intent.
- `create_commision_voucher_details` has a dangerous `down()` defect: it drops `journal_voucher_details`, not its own table. It must never be used for rollback; fixing application/migration code requires separate approval.
- Stock migrations form an ordered FK dependency chain. History reconciliation cannot be decided table-by-table without verifying the complete chain and referential actions.

Proposed later command, after explicit approval, using the existing masked saved-login path:

```powershell
$mysql = 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe'
& $mysql --login-path=blue_l13_audit --protocol=TCP --host=127.0.0.1 --port=3306 --database=bluemarketing_l13_staging --connect-timeout=5 --batch --raw --execute="source C:/laragon/www/blueMarketing/docs/STAGING_MIGRATION_STRUCTURAL_PREFLIGHT.sql"
Write-Host "EXIT CODE: $LASTEXITCODE"
```

Do not run it until separately approved. A zero process exit is necessary but insufficient; all seven result sets and semantic gates must also be validated against the exact schemas in the structural expectation manifest. Expected terminal counts are 42 scoped, 41 present and 1 absent (`expences`), all four gates equal 1, and status `REVIEW_REQUIRED`. That status authorizes only offline comparison. No schema/history change is proposed. **Production deployment remains BLOCKED.**

---

## 2026-10-28 verified staging migration-history reconciliation

The approved SELECT-only capture completed with exit code 0. It selected `bluemarketing_l13_staging`, authenticated as `blue_l13_audit@127.0.0.1` on port 3306, reported `REPEATABLE-READ`, and returned `identity_gate_passed=1`. All six result sets are present. This is physical metadata and migration-history evidence from the captured staging snapshot; it does not establish how an unrecorded object was created.

Repository/history reconciliation is exact: **55 repository migration files, 17 staging history rows, 38 missing history rows, and 0 orphan history rows**. Batch 1 contains 14 recorded migrations, batch 2 contains the landing-page create migration, batch 3 contains the landing draft migration, and batch 4 contains the finance identity migration.

The 38 missing rows divide into 35 `Schema::create` files and three ALTER files. Thirty-four missing-history create targets already exist and would collide if their original migrations were replayed. The exact `expences` target is absent, while an `accounting_expenses` table exists; that naming/provenance relationship is unverified, so the create migration is not a safe candidate. The three missing ALTER effects are also absent: `journal_vouchers.site_id` and its index, `plots.amount`, and both landing-builder JSON fields. No recorded targeted effect mismatch was observed in the captured fields, but the capture does not contain complete cumulative definitions for most tables.

### Exact 55-file history/effect matrix

`Present; detail unknown` means the target table was observed but the capture did not prove every column, index, foreign key, default, or later cumulative dependency. It is physical evidence only.

| # | Repository migration | Staging history | Captured targeted physical effect | Disposition |
|---:|---|---|---|---|
| 1 | `2014_10_12_000000_create_users_table.php` | Recorded, batch 1 | `users` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 2 | `2014_10_12_100000_create_password_resets_table.php` | Recorded, batch 1 | `password_resets` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 3 | `2019_08_19_000000_create_failed_jobs_table.php` | Recorded, batch 1 | `failed_jobs` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 4 | `2019_12_14_000001_create_personal_access_tokens_table.php` | Recorded, batch 1 | `personal_access_tokens` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 5 | `2022_03_19_102456_create_permission_tables.php` | Recorded, batch 1 | Five permission tables, targeted pivot keys, indexes, and four cascading foreign keys present | Targeted effects consistent; no replay |
| 6 | `2022_03_29_105225_create_settings_table.php` | Recorded, batch 1 | `settings` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 7 | `2022_10_22_145548_create_leads_table.php` | Recorded, batch 1 | `leads` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 8 | `2022_10_22_150000_create_project_head_subheads_table.php` | Missing | `project_head_subheads` present; detail unknown | Create collision; do not replay |
| 9 | `2022_10_22_164312_create_zones_table.php` | Recorded, batch 1 | `zones` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 10 | `2022_10_22_164343_create_areas_table.php` | Recorded, batch 1 | `areas` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 11 | `2022_10_22_195724_create_projects_table.php` | Recorded, batch 1 | `projects` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 12 | `2022_10_23_041047_create_project_zones_table.php` | Recorded, batch 1 | `project_zones` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 13 | `2022_10_23_140316_create_plots_table.php` | Recorded, batch 1 | `plots` present with targeted `id` and `unit`; full create effect unknown | Leave history; `amount` is a separate absent ALTER effect |
| 14 | `2022_10_23_155559_create_facings_table.php` | Recorded, batch 1 | `facings` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 15 | `2022_10_23_160843_create_roads_table.php` | Recorded, batch 1 | `roads` present; detail unknown | Leave history; obtain cumulative schema evidence |
| 16 | `2022_10_27_230651_create_lead_user_table.php` | Missing | `lead_user` present; detail unknown | Create collision; do not replay |
| 17 | `2022_10_29_180639_create_works_table.php` | Missing | `works` present; detail unknown | Create collision; do not replay |
| 18 | `2022_11_15_135624_create_expences_table.php` | Missing | Exact `expences` table absent; `accounting_expenses` present | Provenance/name mapping unknown; do not run |
| 19 | `2024_01_14_150839_create_ledgers_table.php` | Missing | `ledgers` present; detail unknown | Create collision; do not replay |
| 20 | `2024_04_20_165837_create_bookings_table.php` | Missing | `bookings` present; detail unknown | Create collision; do not replay |
| 21 | `2024_04_20_172252_create_installments_table.php` | Missing | `installments` present; detail unknown | Create collision; do not replay |
| 22 | `2024_04_20_173235_create_booking_details_table.php` | Missing | `booking_details` present; detail unknown | Create collision; do not replay |
| 23 | `2024_04_25_221615_create_customer_ledger_table.php` | Missing | `customer_ledger` present; detail unknown | Create collision; do not replay |
| 24 | `2025_01_27_142048_create_journal_vouchers_table.php` | Missing | `journal_vouchers` present; selected legacy columns/index captured | Create collision; do not replay |
| 25 | `2025_01_27_143458_create_journal_voucher_details_table.php` | Missing | `journal_voucher_details` present; detail unknown | Create collision; do not replay |
| 26 | `2025_08_01_055838_create_dasticash_table.php` | Missing | `dasticash` present; detail unknown | Create collision; do not replay |
| 27 | `2025_08_08_101553_create_draft_ledgers_table.php` | Missing | `draft_ledgers` present; detail unknown | Create collision; do not replay |
| 28 | `2025_08_08_112841_create_labours_table.php` | Missing | `labours` present; detail unknown | Create collision; do not replay |
| 29 | `2025_08_08_163402_create_stocks_table.php` | Missing | `stocks` present; detail unknown | Create collision; do not replay |
| 30 | `2025_08_14_123002_create_hold_plots_table.php` | Missing | `hold_plots` present; detail unknown | Create collision; do not replay |
| 31 | `2025_09_23_235842_create_charge_types_table.php` | Missing | `charge_types` present; detail unknown | Create collision; do not replay |
| 32 | `2025_09_24_020119_create_pending_updates_table.php` | Missing | `pending_updates` present; detail unknown | Create collision; do not replay |
| 33 | `2025_09_25_142048_create_commision_vouchers_table.php` | Missing | `commision_vouchers` present; detail unknown | Create collision; do not replay |
| 34 | `2025_09_25_143458_create_commision_voucher_details_table.php` | Missing | `commision_voucher_details` present; detail unknown | Create collision; do not replay |
| 35 | `2025_11_13_025816_create_sites_table.php` | Missing | `sites` present; detail unknown | Create collision; do not replay |
| 36 | `2025_11_13_050518_create_labour_attendances_table.php` | Missing | `labour_attendances` present; detail unknown | Create collision; do not replay |
| 37 | `2025_12_01_031728_create_labour_ledgers_table.php` | Missing | `labour_ledgers` present; detail unknown | Create collision; do not replay |
| 38 | `2026_01_08_000000_create_account_types_table.php` | Missing | `account_types` present; detail unknown | Create collision; do not replay |
| 39 | `2026_01_21_043816_create_stock_items_table.php` | Missing | `stock_items` present; detail unknown | Create collision; do not replay |
| 40 | `2026_01_21_043844_create_stock_parties_table.php` | Missing | `stock_parties` present; detail unknown | Create collision; do not replay |
| 41 | `2026_01_21_043857_create_stock_entries_table.php` | Missing | `stock_entries` present; detail unknown | Create collision; do not replay |
| 42 | `2026_01_21_043911_create_stock_entry_lines_table.php` | Missing | `stock_entry_lines` present; detail unknown | Create collision; do not replay |
| 43 | `2026_01_21_043924_create_stock_bills_table.php` | Missing | `stock_bills` present; detail unknown | Create collision; do not replay |
| 44 | `2026_01_21_043937_create_stock_bill_lines_table.php` | Missing | `stock_bill_lines` present; detail unknown | Create collision; do not replay |
| 45 | `2026_01_21_043949_create_stock_movements_table.php` | Missing | `stock_movements` present; detail unknown | Create collision; do not replay |
| 46 | `2026_01_21_044002_create_stock_item_balances_table.php` | Missing | `stock_item_balances` present; detail unknown | Create collision; do not replay |
| 47 | `2026_01_21_044017_create_stock_counters_table.php` | Missing | `stock_counters` present; detail unknown | Create collision; do not replay |
| 48 | `2026_03_31_000001_add_site_id_to_journal_vouchers_table.php` | Missing | `journal_vouchers.site_id` and generated site index absent | Additive effect absent; approval/rehearsal required |
| 49 | `2026_04_20_180000_create_booking_vouchers_table.php` | Missing | `booking_vouchers` present; detail unknown | Create collision; do not replay |
| 50 | `2026_05_02_000001_add_amount_to_plots_table.php` | Missing | `plots.amount` absent | Additive effect absent; default/data impact review required |
| 51 | `2026_09_08_000001_create_booking_edit_audits_table.php` | Missing | `booking_edit_audits` present; detail unknown | Create collision; do not replay |
| 52 | `2026_10_24_000001_create_landing_pages_table.php` | Recorded, batch 2 | `landing_pages` present; full base definition not captured | Leave history; obtain cumulative schema evidence |
| 53 | `2026_10_24_000002_add_drafts_to_landing_pages_table.php` | Recorded, batch 3 | `draft_payload` JSON nullable and `published_at` timestamp nullable present | Targeted effects consistent; no replay |
| 54 | `2026_10_24_000003_add_landing_builder_fields.php` | Missing | `section_order` and `project_overrides` absent | Additive effect absent; remains explicitly deferred |
| 55 | `2026_10_27_000001_create_financial_source_identity_tables.php` | Recorded, batch 4 | Both identity tables and required targeted indexes present | Targeted effects consistent; no replay |

### Reconciliation decision gates

1. Capture SELECT-only cumulative column, index, foreign-key, engine and collation metadata for every collision target, plus the application/model mapping between `expences` and `accounting_expenses`. Capture only structural and aggregate impact evidence.
2. Compare each physical object with the cumulative repository schema. Do not use table existence or a history row as proof of execution provenance.
3. For a missing history row whose cumulative physical effect is proven equivalent, prepare a separately reviewed history-reconciliation manifest with filename, file hash, evidence, proposed batch treatment, exact rollback of the history-only write, backup/restore evidence and post-checks. Do not replay its migration and do not insert history automatically.
4. Treat every schema change separately from history reconciliation. Rehearse any approved additive migration on a disposable MySQL copy with representative legacy schema and data-volume characteristics. The `site_id` path must remain schema-only; `plots.amount` needs default/backfill impact review; landing builder fields remain deferred.
5. Resolve mismatches with an explicit forward corrective migration after dependency and data compatibility review. Never run a legacy `Schema::create` migration against an existing target.
6. Require explicit approval for every history write or schema change, a tested rollback/restore plan, and fresh post-change read-only verification.

Unknown execution provenance remains the central blocker. No migration-history rows should be manufactured from this matrix. **Production deployment remains BLOCKED.**

---

## 2026-10-28 migration compatibility hardening

Static review reconfirmed all 55 migration files. No MySQL connection or operational migration was executed; migration tests used only isolated SQLite memory. Schema existence remains independent from migration-history evidence: no file is classified as already applied without an exact row from the staging `migrations` table, and even a history row does not prove its schema effects remain intact.

Two verified source defects were corrected. The legacy permission migration now resolves `pivotPermission` and `pivotRole` from the installed `PermissionRegistrar` instance; the installed package exposes them as public instance properties, so the previous static access would fail on a fresh migration. The March 2026 site migration is now schema-only: it adds a nullable indexed `site_id` only when `journal_vouchers` exists, the column is absent and the generated index name is unused. It performs no historical attribution. A missing table, pre-existing column or index-name collision throws before mutation and requires manual schema/history reconciliation. Automatic rollback is disabled because migration-history drift makes column ownership unprovable.

The removed site backfill was unsafe because it used description text plus optional account/credit evidence, weakened matching when the site account was empty, and allowed unordered duplicate site candidates to claim rows. Neither exact text, numeric IDs nor voucher references independently prove site ownership. Any historical site attribution remains a separately approved finance process requiring source evidence and human review.

Current classifications:

| Scope | Classification | Reason / required gate |
|---|---|---|
| Permission migration on a clean absent-table schema | Safe in isolated regression | Installed registrar pivot names are resolved through the supported instance; actual staging execution still requires history/schema review |
| Site migration with existing `journal_vouchers` and absent `site_id` | Safe schema-only path | Adds nullable column and index; performs no data backfill |
| Site migration with missing table, pre-existing `site_id`, or generated index-name collision | Fail-closed / manual approval | Refuses to infer whether history is missing or schema was created elsewhere; checks before DDL |
| Every `Schema::create` migration when its target table exists | Unsafe to replay | MySQL existing-table collision; schema existence is not execution-history proof |
| `add_amount_to_plots` and both landing-page alters when any target column exists | Unsafe to replay | Unguarded duplicate-column failure; exact history and column definitions required |
| All 55 staging execution-history statuses | Uncertain | Exact staging `migrations` rows have not been captured in this task |
| Historical site ownership/backfill | Unsafe / manual finance approval | Repository code cannot authenticate ownership evidence |

Previously verified staging evidence establishes six finance tables are InnoDB and records legacy integer/nullability differences for JV, ledger and project-account keys. Those observations prove compatibility requirements for read-only audit joins; they do not establish which migrations ran. In particular, the original ledger/JV create definitions still differ from verified legacy physical types, so replay remains prohibited.

Added `docs/STAGING_MIGRATION_DRIFT_PREFLIGHT.sql`, containing only explicit SELECT statements for identity, migration history, table metadata, targeted columns, indexes and foreign keys. It returns no customer rows, voucher details, descriptions or financial amounts. Run it only through the already verified restricted audit account after separate approval; any identity mismatch, missing metadata object, SQL error or incomplete output blocks classification. Separate statements do not share a guaranteed snapshot.

Isolated SQLite regression executes the real migrations and covers registrar pivot names, permission primary indexes and foreign tables, safe site-column addition, pre-existing column, missing table, generated index collision, and non-destructive rollback refusal. Focused result: **6 tests, 26 assertions, exit 0**. Full isolated suite: **473 tests, 1,934 assertions, exit 0**.

**Production deployment remains BLOCKED.** Required next evidence is the read-only staging migration/schema capture, followed by a per-file history/effect matrix and manual approval for any history repair or additive schema work.

---

## 2026-10-28 fresh staging finance aggregate verified

This section supersedes earlier statements that the 2,873-row inventory was only a historical report. A SELECT-only staging aggregate returned exactly six rows and exit code 0: project 6 = 1,430, project 5 = 749, project 3 = 692, project 1 = 2, and projects 2/4 = 0. The fresh total is 2,873, a zero difference from the initial baseline. No scan was truncated; identity, project, integrity and arithmetic gates passed for every row, and all six statuses were `ACCEPTABLE_FOR_MANUAL_REVIEW`.

Schema and integrity preflights passed before execution. Post-audit verification reported 34/34 schema checks and 9/9 integrity checks passing, with every `passed=1`, both semantic statuses `PASS`, and wrapper validation `PASS`. Separate post-audit MySQL exit codes were not printed and remain undocumented.

Observed categories are recorded in [the historical reconciliation report](HISTORICAL_JV_RECONCILIATION_2026_10_27.md). They are evidence triage, not ownership conclusions: 0 rows were apparently attributed, 0 invalid-metadata rows were observed, independently verified ownership remains `NULL`/not assessed, and source verification and deployment approval remain false. Matching references do not certify ownership; disagreements do not by themselves prove financial errors. Manual review priority is projects 6, 5, 3 and 1. No application code changed and no tests were required for this documentation-only update. **Production deployment remains BLOCKED.**

---

## 2026-10-27 current authorized staging audit preparation

HEAD verified f7e7336cd57d7a693505788b27bcbd05ba3d9470 on upgrade/laravel-framework; prior tooling is committed/pushed and this commit's CI success is user-confirmed. Read-only staging access is now authorized, superseding earlier prohibitions below. Authenticated inspection remains blocked solely on secure restricted credential availability: four conventional process password variables and the previous PowerShell password variable are absent. No secret values printed, private files searched, or MySQL connections attempted.

Six existing audit command help executions passed (exit 0) under SQLite-only process settings. Source inspection confirms general evidence commands lack target/account enforcement; staging preflight verifies environment/host/database but not restricted account/grants or read-only transaction. The reconciliation report now records same-connection identity/privilege/snapshot gates, aggregate-only command sequence and one process-scoped credential handoff. Raw packets contain voucher references; conflict/coverage commands require real review artifacts and do not exhaustively detect metadata anomalies.

Focused isolated SQLite verification: 88 tests / 365 assertions, exit 0. No application changes; full suite not rerun (previous 467/1908 remains historical). Reported 2873 rows and 60 tables have not been freshly verified; no new ownership, category counts or discrepancies asserted. Next: securely hand off BLUE_L13_STAGE_PASSWORD to the agent process, verify the restricted staging connection, then run existing bounded aggregate reports. Deployment BLOCKED; remaining schema/history, finance reconciliation, disposable concurrency, backup/restore, UAT and release gates unchanged. Only documentation updated; no commit/push or protected-resource changes. See HISTORICAL_JV_RECONCILIATION_2026_10_27.md for the execution procedure.

---

# Laravel 13 continuation audit — 2026-10-27

## Latest resumed evidence-audit status

Baseline is now `14560873feac8e2f3ff5f25aa3afa6622ddc88d3`. CI run 40498082989 was independently verified completed/success for this commit during the evidence audit; earlier CI observations below are historical. New changes remain uncommitted and are not covered by that CI run.

Preserved and reviewed six existing PHP changes and the untracked [historical reconciliation report](HISTORICAL_JV_RECONCILIATION_2026_10_27.md). The new aggregate evidence summary reuses existing packets and the shared collision scope; invalid attribution metadata no longer hides rows from coverage. Partial attribution schema is refused. Exact 100/101-row batch boundaries, bounded examples and conservative classification are covered by isolated tests. No source ownership is certified, and independent-evidence count remains null/not assessed.

The reported 2873 rows (projects 6:1430, 5:749, 3:692, 1:2; projects 2/4:0) remain handoff evidence. Previously recorded project-6 categories are 1420 JV-only candidates and ten missing numeric headers, not verified owners. No fresh MySQL scan, detail/amount reconciliation or source-document authentication occurred. The resumed task explicitly prohibits MySQL access. Future staging reads require renewed authorization and secure restricted credentials; all prior release blockers remain. See the latest worklog for final test results. No commit, push, merge, migration or deployment in this evidence-audit task.

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

## UNKNOWN-table provenance review - 2026-10-28

The three structural UNKNOWN results were traced through current migrations/models/controllers, full Git history and relevant DDL definitions in a local historical SQL artifact. The artifact was never executed; inspection was restricted to the relevant CREATE/ALTER statements, with no INSERT rows or business values read.

- `ledgers`: application dependence on `customer_ledger_id` and `charge_type_id` is verified by commit `a82ed439430d96ac3e0615157f2399cc468c032e` (2025-09-24), and `voucher_number` by `8043f13d0d60f3a336454f40c998ccdb27e45287` (2025-10-25). The DDL artifact independently shows the three columns, two indexes and two SET NULL/UPDATE CASCADE FKs. No reviewed migration or commit supplies their DDL provenance.
- `sites`: commit `6b028f63d2f79ad238b0f265624b1350e88e6890` (2026-01-02) verifies the first reviewed application writes and project-scoped reads using `project_id`. The artifact shows the column, index and SET NULL/UPDATE CASCADE FK; their DDL provenance remains absent from reviewed history.
- `booking_vouchers`: commit `564896aa312df5071aaf158df38a9051e89e4471` (2026-06-12) verifies a historical `forceFill` write to `delete_reason`; `dc9bf7e8e3e464cc9d743cc3ec5b5325782807e6` (2026-06-16) removes that write in favor of soft deletion. The artifact shows only the nullable extra column. Current model/controller behavior does not depend on it, and its DDL origin is unknown.

These findings verify application provenance, not migration execution. The classifications remain **19 COMPATIBLE, 13 INCOMPATIBLE and 3 UNKNOWN**; the UNKNOWN rows are qualified as *verified application provenance with unknown DDL provenance*. The 13 incompatibilities, journal-voucher voucher-number/unique-key drift and destructive commission-detail rollback remain blockers.

`docs/STAGING_MIGRATION_RECONCILIATION_MANIFEST.md` pins all 35 current CREATE files by SHA-256 and separates 19 possible history-only review candidates, 13 forward-remediation candidates and three unresolved-provenance targets. It is intentionally non-executable. Every disposition requires a fresh capture, exact hash, dependency and recovery evidence, plus explicit human approval. No history row is approved for insertion and no CREATE is approved for replay.

## Commission-detail rollback correction - 2026-10-28

The source defect in `2025_09_25_143458_create_commision_voucher_details_table.php` was corrected: `down()` now drops only `commision_voucher_details`, preserving the unrelated `journal_voucher_details` table. An isolated SQLite regression inserts a sentinel journal-detail row and an unrelated ledger row, runs the named migration, and verifies both remain after rollback. Focused migration coverage passed 7 tests / 31 assertions; the full isolated suite passed 474 tests / 1,939 assertions. This verifies repository behavior only; no staging or production rollback was executed, and historical staging rollback safety remains unverified.
