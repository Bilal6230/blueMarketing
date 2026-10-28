# Staging migration structural expectations

This manifest is derived from the 35 repository migration classes whose `Schema::create` history rows are absent in the verified staging capture. It describes the original migration declarations, not the full current application schema and not proof that any migration ran. Laravel shorthand is expanded using MySQL conventions (`id` = unsigned BIGINT auto-increment primary key, `foreignId` = unsigned BIGINT, `increments` = unsigned INT auto-increment, `timestamps` = nullable `created_at`/`updated_at`, and `softDeletes` = nullable `deleted_at`). No migration specifies a table engine, table collation, or table charset; those properties depend on the connection defaults at execution time and therefore remain `UNKNOWN` until captured.

`FK` lists only active migration statements. Commented foreign keys are not expectations. Unnamed index and constraint names must be compared by ordered columns, uniqueness, referenced target, and actions because framework-generated names can vary with historical code/configuration.

| Migration / table | Expected columns (type; `?` nullable; `=x` default) | Expected keys and constraints |
|---|---|---|
| `2022_10_22_150000_create_project_head_subheads_table.php` / `project_head_subheads` | `id id`; `project_id`, `head_accounting_id`, `subhead_accounting_id`, `plot_id`, `customer_id` unsigned BIGINT?; timestamps | PK `id`; no active FKs or added secondary indexes |
| `2022_10_27_230651_create_lead_user_table.php` / `lead_user` | `id increments`; `lead_id`, `user_id` foreignId; timestamps | PK `id`; FK `lead_id->leads.id`, `user_id->users.id` |
| `2022_10_29_180639_create_works_table.php` / `works` | `id id`; `lead_id` foreignId; `comment` LONGTEXT?; `call_duration`, `recording_path` VARCHAR?; `call_status`, `user_id` INT?; `type` INT=1; `follow_up` TIMESTAMP?; timestamps | PK `id`; FK `lead_id->leads.id` |
| `2022_11_15_135624_create_expences_table.php` / `expences` | `id id`; timestamps | PK `id`; no secondary keys/FKs |
| `2024_01_14_150839_create_ledgers_table.php` / `ledgers` | `id id`; `type`, `reference`, `delete_reason`, `status` VARCHAR?; `type_id`, `create_by`, `update_by` INT?; `project_head_subheads_id` unsigned BIGINT; `amount_in`, `amount_out` DECIMAL(10,2); `detail` TEXT?; `is_active` BOOLEAN=true; `date` DATE; timestamps | PK `id`; no declared FK/index beyond PK |
| `2024_04_20_165837_create_bookings_table.php` / `bookings` | `id id`; `project_id`, `customer_id`, `plot_id`, `broker_id` INT; `plot_type` TINYINT=0; `plot_size` VARCHAR; `plot_rate`, `park_facing`, `carner_price`, `total_price` DECIMAL(10,2); `is_corner`, `is_park` INT=0; `booking_date` TIMESTAMP; `status` ENUM(active,inactive)=`active`; `user_id` foreignId; `cancel_status` ENUM(0,1)=`0`; `reason` LONGTEXT?; timestamps | PK `id`; FK `user_id->users.id` |
| `2024_04_20_172252_create_installments_table.php` / `installments` | `id id`; `instalment_name` VARCHAR; `is_active` INT=1; timestamps | PK `id` |
| `2024_04_20_173235_create_booking_details_table.php` / `booking_details` | `id id`; `booking_id` foreignId; `installment_details` VARCHAR; `amount` DECIMAL(10,2); `due_date` DATE; timestamps | PK; FK `booking_id->bookings.id` DELETE CASCADE |
| `2024_04_25_221615_create_customer_ledger_table.php` / `customer_ledger` | `id id`; `date` DATE; customer/project IDs unsigned BIGINT; `transaction_type` VARCHAR; amounts DECIMAL(10,2); `description`, `delete_reason` VARCHAR?; `check_history` JSON?; timestamps | PK; FK `customer_id->leads.id` DELETE CASCADE; no project FK |
| `2025_01_27_142048_create_journal_vouchers_table.php` / `journal_vouchers` | `id id`; `voucher_number` VARCHAR; `reference` VARCHAR?; `description` TEXT?; `date` DATE; totals DECIMAL(15,2); `created_by` unsigned BIGINT; `updated_by` unsigned BIGINT?; status ENUM=`pending`; timestamps | PK; UNIQUE `voucher_number`; no declared FKs |
| `2025_01_27_143458_create_journal_voucher_details_table.php` / `journal_voucher_details` | `id id`; voucher/account IDs unsigned BIGINT; debit/credit DECIMAL(15,2)=0; `description` TEXT?; timestamps; soft delete | PK; FK `journal_voucher_id->journal_vouchers.id` DELETE CASCADE; account FK commented out |
| `2025_08_01_055838_create_dasticash_table.php` / `dasticash` | `id id`; `name`, `amount` VARCHAR?; `description` TEXT?; `project_id` unsigned BIGINT; timestamps | PK; FK `project_id->projects.id` DELETE CASCADE |
| `2025_08_08_101553_create_draft_ledgers_table.php` / `draft_ledgers` | `id id`; `date` DATE; `reference` VARCHAR(250)?; `type_id`, `project_id`, `plot_id`, `payment_type`, `bank_id`, `passing_status` BIGINT? (`passing_status`=0); `customer_id` unsigned BIGINT?; `t_number` VARCHAR(250)?; `passing_date`, `bank_post_at` TIMESTAMP?; `check_history` LONGTEXT?; `transaction_type`, `description`, `type`, `status` VARCHAR?; `amount_in`, `amount_out` DECIMAL(10,2)?; `is_active`, `is_approve` BIGINT?; `note`, `detail` TEXT?; `project_head_subheads_id` unsigned BIGINT?; `create_by`, `update_by` INT?; timestamps | PK only; no declared secondary indexes/FKs |
| `2025_08_08_112841_create_labours_table.php` / `labours` | `id id`; identity/contact strings nullable except unique `cnic`; wage DECIMAL(10,2)?; `join_date` DATE?; status ENUM=`active`; `project_id` unsigned BIGINT; timestamps | PK; UNIQUE `cnic`; FK project DELETE CASCADE |
| `2025_08_08_163402_create_stocks_table.php` / `stocks` | `id id`; `stock_name` VARCHAR?; integer totals/quantity=0; type ENUM purchase/sale; `project_id` unsigned BIGINT; timestamps | PK; FK project DELETE CASCADE |
| `2025_08_14_123002_create_hold_plots_table.php` / `hold_plots` | `id id`; plot/user IDs unsigned BIGINT; `reason` LONGTEXT?; timestamps | PK; FKs to plots/users DELETE CASCADE |
| `2025_09_23_235842_create_charge_types_table.php` / `charge_types` | `id id`; `name` VARCHAR?; timestamps | PK only |
| `2025_09_24_020119_create_pending_updates_table.php` / `pending_updates` | `id id`; `table_name` VARCHAR; `record_id` unsigned BIGINT; old/new JSON; status ENUM=`pending`; submitter unsigned BIGINT; approver unsigned BIGINT?; timestamps | PK only; no declared FKs |
| `2025_09_25_142048_create_commision_vouchers_table.php` / `commision_vouchers` | `id id`; `voucher_number` VARCHAR; `project_id` unsigned BIGINT; reference/description nullable; date; totals DECIMAL(15,2); creator/updater IDs; status ENUM=`pending`; timestamps; soft delete | PK; UNIQUE voucher number; FK project DELETE CASCADE |
| `2025_09_25_143458_create_commision_voucher_details_table.php` / `commision_voucher_details` | `id id`; voucher/account IDs unsigned BIGINT; debit/credit DECIMAL(15,2)=0; description TEXT?; timestamps; soft delete | PK; FK commission voucher DELETE CASCADE; account FK commented out |
| `2025_11_13_025816_create_sites_table.php` / `sites` | `id id`; head/subhead IDs unsigned BIGINT?; site name/address VARCHAR?; timestamps | PK only; no active FKs |
| `2025_11_13_050518_create_labour_attendances_table.php` / `labour_attendances` | `id id`; `project_id`, `labour_id` unsigned BIGINT; `site_id`, `marked_by` unsigned BIGINT?; `date` DATE; `status` ENUM(present,absent,leave,holiday,not-marked)=`not-marked`; `hours`, `ot_hours` DECIMAL(5,2)=0; `rate` DECIMAL(10,2)?; `amount` DECIMAL(10,2)=0; `remarks` VARCHAR?; `is_approved` BOOLEAN=false; `paid_status` ENUM(paid,unpaid)=`unpaid`; `voucher_status` ENUM(created,notcreated)=`notcreated`; `ratings` DECIMAL(10,1)=0; timestamps | PK; FKs project/labour DELETE CASCADE, site/marker DELETE SET NULL; UNIQUE `(labour_id,site_id,date)` |
| `2025_12_01_031728_create_labour_ledgers_table.php` / `labour_ledgers` | `id id`; `labour_id` unsigned BIGINT; date?; amount DECIMAL(10,2)=0; details JSON?; timestamps | PK; FK labour DELETE CASCADE |
| `2026_01_08_000000_create_account_types_table.php` / `account_types` | `id` unsigned TINYINT; `name` VARCHAR; `status` BOOLEAN=1; timestamps | PK `id` |
| `2026_01_21_043816_create_stock_items_table.php` / `stock_items` | `id id`; indexed name; `unit` VARCHAR(40); `default_rate_minor` unsigned BIGINT=0; active=true; creator/updater unsigned BIGINT?; timestamps; soft delete | PK; INDEX name |
| `2026_01_21_043844_create_stock_parties_table.php` / `stock_parties` | `id id`; indexed type ENUM; indexed name; mobile VARCHAR(40)?; address/account strings?; active=true; creator/updater IDs?; timestamps; soft delete | PK; indexes type and name |
| `2026_01_21_043857_create_stock_entries_table.php` / `stock_entries` | `id id`; unique slip VARCHAR(30); indexed date/flow; `party_id` foreignId; vehicle/driver/reason nullable; indexed billed timestamp?; creator/updater IDs?; timestamps; soft delete | PK; UNIQUE slip; indexes listed; FK party (default restrict/no-action delete) |
| `2026_01_21_043911_create_stock_entry_lines_table.php` / `stock_entry_lines` | `id id`; entry/item foreignIds; qty DECIMAL(18,3); rate/amount unsigned BIGINT; timestamps | PK; FK entry DELETE CASCADE; FK item default action; INDEX `(entry_id,item_id)` |
| `2026_01_21_043924_create_stock_bills_table.php` / `stock_bills` | `id id`; unique bill VARCHAR(30); indexed date/type; party foreignId; nullable entry foreignId; total unsigned BIGINT=0; creator/updater IDs?; timestamps; soft delete | PK; UNIQUE bill; FKs party default action and entry DELETE SET NULL |
| `2026_01_21_043937_create_stock_bill_lines_table.php` / `stock_bill_lines` | `id id`; bill/item foreignIds; qty DECIMAL(18,3); rate/amount unsigned BIGINT; timestamps | PK; FK bill DELETE CASCADE; FK item default action; INDEX `(bill_id,item_id)` |
| `2026_01_21_043949_create_stock_movements_table.php` / `stock_movements` | `id id`; indexed date/direction; item FK; qty DECIMAL(18,3); rate/amount unsigned BIGINT=0; indexed source type/id; source line unsigned BIGINT; nullable party FK; meta JSON?; creator ID?; timestamps | PK; UNIQUE `(source_type,source_line_id)`; INDEX `(item_id,movement_date)` plus singles; FKs item default, party SET NULL |
| `2026_01_21_044002_create_stock_item_balances_table.php` / `stock_item_balances` | `item_id` foreignId; `on_hand_qty` DECIMAL(18,3)=0; timestamps | PK/FK item, DELETE CASCADE |
| `2026_01_21_044017_create_stock_counters_table.php` / `stock_counters` | `key` VARCHAR(50); `next_number` unsigned BIGINT=1; timestamps | PK `key` |
| `2026_04_20_180000_create_booking_vouchers_table.php` / `booking_vouchers` | `id id`; `booking_id`, `project_id`, `customer_id`, `plot_id`, `bank_id` unsigned BIGINT? indexed; `customer_ledger_id`, `ledger_id` unsigned BIGINT? unique; `voucher_series` VARCHAR(20) indexed; `voucher_number` unsigned INT?; `slip_reference` VARCHAR? indexed; `payment_type` unsigned TINYINT?; `amount` DECIMAL(12,2)=0; `receipt_date` DATE? indexed; `description` TEXT?; `t_number` VARCHAR?; `passing_date` DATE?; `is_active` BOOLEAN=true indexed; `is_approve` BOOLEAN=false indexed; `create_by`, `update_by` unsigned BIGINT?; timestamps | PK; two UNIQUE IDs; named INDEX `(project_id,voucher_series,voucher_number)` plus declared singles; no FKs |
| `2026_09_08_000001_create_booking_edit_audits_table.php` / `booking_edit_audits` | `id id`; `booking_id`, `project_id` unsigned BIGINT; `user_id`, `journal_voucher_id` unsigned BIGINT?; `operation` VARCHAR(40); `reason` TEXT?; `old_values`, `new_values` JSON; `old_total`, `new_total`, `delta`, `old_accounting_principal`, `delta_from_accounting` DECIMAL(10,2)?; `request_key` VARCHAR? unique; timestamps | PK; UNIQUE request key; INDEX `(booking_id,created_at)`, `(project_id,operation)`; no FKs |

## Deterministic comparison

For every table, normalize actual and expected columns by name, MySQL base/type parameters, unsigned flag, nullability, default value, generated/auto-increment attributes, and collation where applicable. Normalize indexes by ordered columns, prefix length, uniqueness and type. Normalize foreign keys by ordered local/referenced columns plus update/delete action. Compare engine, table collation and charset only as captured properties because the migrations do not specify them.

Classify each attribute and each migration:

- `MATCH`: the captured normalized attribute equals the declaration.
- `MISMATCH`: both sides are supported and materially differ.
- `MISSING`: the declaration has no captured attribute or target table.
- `EXTRA`: captured schema contains an attribute not declared by this migration; this may be legitimate cumulative legacy evolution.
- `UNKNOWN`: runtime-derived names/defaults, unsupported grammar, historical/cumulative effects, application-only relationships, or missing/incomplete output prevent a deterministic conclusion.

A migration is never `COMPATIBLE` merely because its table exists. It may be considered structurally compatible only when every required original attribute is `MATCH`, every extra attribute is explained by later approved evolution, no dependency is unresolved, and engine/collation differences are accepted explicitly. History status and structural status remain separate.

## Expected preflight output

All seven result sets are mandatory and must appear in order. Zero-row metadata sets still need their column header in the captured CLI output.

| Result set | Cardinality | Columns | Acceptance gate |
|---:|---|---|---|
| 1 - identity | Exactly 1 row | `selected_database`, `authenticated_account`, `connection_account`, `server_port`, `transaction_isolation`, `identity_gate_passed` | Database, authenticated account and port must match; gate must equal 1 |
| 2 - table scope | Exactly 42 rows | `scope_role`, `table_name`, `table_present`, `TABLE_TYPE`, `ENGINE`, `TABLE_COLLATION` | Every scope name exactly once; `expences` absent; the other 41 present as base tables unless a newly explained change requires review |
| 3 - columns | Variable, nonzero | `TABLE_NAME`, `ORDINAL_POSITION`, `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `EXTRA`, `COLLATION_NAME`, `GENERATION_EXPRESSION` | Every present scope table represented; unique `(TABLE_NAME,ORDINAL_POSITION)` and column names per table |
| 4 - indexes | Variable, nonzero | `TABLE_NAME`, `INDEX_NAME`, `NON_UNIQUE`, `SEQ_IN_INDEX`, `COLUMN_NAME`, `SUB_PART`, `COLLATION`, `INDEX_TYPE` | Ordered index rows complete; each expected primary/unique/secondary index explicitly classified |
| 5 - foreign keys | Variable; zero is syntactically valid but incompatible with expected FKs | `TABLE_NAME`, `CONSTRAINT_NAME`, `ORDINAL_POSITION`, `COLUMN_NAME`, `REFERENCED_TABLE_NAME`, `REFERENCED_COLUMN_NAME`, `UPDATE_RULE`, `DELETE_RULE` | Every expected active FK classified; unexpected or absent FK requires review |
| 6 - checks | Variable and may be zero | `TABLE_NAME`, `CONSTRAINT_NAME`, `CHECK_CLAUSE` | Complete header/result set required; each returned check classified; absence does not prove enum compatibility |
| 7 - terminal validation | Exactly 1 row | `scope_table_count`, `present_table_count`, `absent_table_count`, `scope_definition_complete`, `expences_expected_absent`, `required_scope_tables_present`, `identity_gate_passed`, `validation_status` | Counts 42/41/1, all four gates equal 1, and status exactly `REVIEW_REQUIRED` |

The script intentionally never emits `PASS`. An identity mismatch suppresses metadata rows in result sets 2-6 and produces `BLOCKED_IDENTITY` in result set 7. `REVIEW_REQUIRED` means the capture is complete enough for deterministic offline comparison; it does not certify compatibility or authorize migration/history changes. Any SQL/client error, nonzero exit code, missing/reordered result set, different cardinality, false gate, or `BLOCKED_*` status rejects the capture.

## `expences` versus `accounting_expenses`

The repository contains both `App\Models\Expence` and `App\Models\AccountingExpense`. Their conventional inferred tables are `expences` and `accounting_expenses`. `ExpenceController` is an empty resource scaffold. Active accounting and voucher code uses `AccountingExpense`, including `project_head_subheads_id`, `price`, `detail`, `cash_in`, `create_by`, and `is_active`. The missing migration creates only `expences.id` and timestamps and contains no rename or data-transfer operation. The schemas therefore cannot be treated as equivalent from repository evidence. Classification: **unresolved provenance and incompatible declared purpose**, pending structural metadata and historical change evidence.

## Verified staging classification - 2026-10-28

The approved capture completed with exit code 0 and all seven result sets. It selected `bluemarketing_l13_staging`, authenticated as `blue_l13_audit@127.0.0.1` on port 3306, returned `identity_gate_passed=1`, and ended with 42 scoped / 41 present / one absent, all scope gates equal 1, and `REVIEW_REQUIRED`. All 41 present tables are InnoDB with `utf8mb4_unicode_ci` table collation. The migrations do not specify engine/collation, so these are accepted snapshot characteristics rather than execution proof.

`COMPATIBLE` means every declared structural requirement was observed; it does not prove provenance or justify a history row. `INCOMPATIBLE` means a required object is absent or a declared attribute materially differs. `UNKNOWN` means declared requirements match but unexplained extra legacy structure prevents full cumulative classification.

| Migration target | Classification | Verified evidence |
|---|---|---|
| `project_head_subheads` | **INCOMPATIBLE** | `id` unsigned INT vs expected unsigned BIGINT; project/head/subhead IDs required vs expected nullable; plot/customer IDs signed vs expected unsigned; three extra active FKs |
| `lead_user` | **COMPATIBLE** | Exact unsigned INT identity, unsigned BIGINT IDs, timestamps, PK and both FKs |
| `works` | **COMPATIBLE** | All declared columns, defaults, PK and lead FK match |
| `expences` | **INCOMPATIBLE** | Target absent; `accounting_expenses` is a separate incompatible schema |
| `ledgers` | **UNKNOWN** | Declared fields match; unexplained voucher/customer-ledger/charge-type columns, indexes and two SET NULL FKs are extra |
| `bookings` | **INCOMPATIBLE** | Broker and cancel status are nullable rather than required; unexplained cancellation fields and soft delete are extra |
| `installments` | **COMPATIBLE** | Exact columns, defaults, timestamps and PK |
| `booking_details` | **COMPATIBLE** | Exact columns, PK and booking FK CASCADE |
| `customer_ledger` | **INCOMPATIBLE** | Customer nullable; project signed/nullable; description LONGTEXT vs VARCHAR; JSON expectation stored as LONGTEXT without captured JSON CHECK; many extras |
| `journal_vouchers` | **INCOMPATIBLE** | Voucher number signed INT vs VARCHAR; global voucher unique absent, replaced by UNIQUE `(project_id,type,voucher_number)`; extra type/project/soft delete |
| `journal_voucher_details` | **COMPATIBLE** | Exact columns/defaults/soft delete, PK and voucher FK CASCADE |
| `dasticash` | **INCOMPATIBLE** | Project ID nullable vs required; FK has additional UPDATE CASCADE |
| `draft_ledgers` | **INCOMPATIBLE** | `passing_status` required vs declared nullable; other declarations match |
| `labours` | **INCOMPATIBLE** | Name, wage and join date required vs nullable; project nullable vs required; extra advance column |
| `stocks` | **COMPATIBLE** | Exact columns/defaults, PK and project FK CASCADE |
| `hold_plots` | **COMPATIBLE** | Exact columns, PK and plot/user FKs CASCADE |
| `charge_types` | **COMPATIBLE** | Exact columns, timestamps and PK |
| `pending_updates` | **INCOMPATIBLE** | Declared JSON columns are LONGTEXT/binary with no captured JSON CHECKs |
| `commision_vouchers` | **COMPATIBLE** | Exact columns/defaults/soft delete, unique key and project FK CASCADE |
| `commision_voucher_details` | **COMPATIBLE** | Exact structure and parent FK CASCADE; rollback code remains separately unsafe |
| `sites` | **UNKNOWN** | Declarations match; unexplained project ID, index and SET NULL FK are extra |
| `labour_attendances` | **INCOMPATIBLE** | Project nullable; voucher status/ratings nullable; project FK SET NULL vs declared CASCADE |
| `labour_ledgers` | **INCOMPATIBLE** | Declared JSON details is LONGTEXT/binary without a captured JSON CHECK; unexplained project ID/index/SET NULL FK are also extra |
| `account_types` | **COMPATIBLE** | Exact unsigned TINYINT PK, status default and timestamps |
| `stock_items` | **COMPATIBLE** | Exact columns/defaults/soft delete, PK and name index |
| `stock_parties` | **COMPATIBLE** | Exact columns/defaults/soft delete, PK and type/name indexes |
| `stock_entries` | **COMPATIBLE** | Exact columns, unique slip, indexes/soft delete and party FK NO ACTION |
| `stock_entry_lines` | **COMPATIBLE** | Exact columns/index; entry FK CASCADE and item FK NO ACTION |
| `stock_bills` | **COMPATIBLE** | Exact columns/indexes/unique/soft delete; party NO ACTION and entry SET NULL FKs |
| `stock_bill_lines` | **COMPATIBLE** | Exact columns/index; bill FK CASCADE and item FK NO ACTION |
| `stock_movements` | **INCOMPATIBLE** | Declared JSON meta is LONGTEXT/binary plus `json_valid(meta)` rather than MySQL JSON; other columns/indexes/unique/FKs match |
| `stock_item_balances` | **COMPATIBLE** | Exact primary/FK, quantity default and timestamps; CASCADE matches |
| `stock_counters` | **COMPATIBLE** | Exact VARCHAR PK, unsigned counter/default and timestamps |
| `booking_vouchers` | **UNKNOWN** | Every declaration matches; unexplained nullable `delete_reason` is extra |
| `booking_edit_audits` | **INCOMPATIBLE** | Declared JSON columns are LONGTEXT/binary, though `json_valid` CHECKs exist; other declarations match |

Totals: **19 COMPATIBLE, 13 INCOMPATIBLE, 3 UNKNOWN**. No target is approved for replay or history insertion.

### Provenance review for the three UNKNOWN targets

Repository history and the local DDL artifact refine the reason for each UNKNOWN result without changing its classification:

- **`ledgers`:** commit `a82ed439430d96ac3e0615157f2399cc468c032e` (2025-09-24) is the earliest reviewed Git evidence that application code writes/relates `customer_ledger_id` and writes `charge_type_id`; it also adds `customer_ledger_id` to the model. Commit `8043f13d0d60f3a336454f40c998ccdb27e45287` (2025-10-25) adds `voucher_number` to the model and ledger write path. The DDL artifact contains those three columns, indexes on the two relationship columns, and SET NULL/UPDATE CASCADE FKs. No reviewed migration or commit creates those columns, indexes, or FKs. Application provenance is verified; DDL origin and execution provenance remain unknown.
- **`sites`:** commit `6b028f63d2f79ad238b0f265624b1350e88e6890` (2026-01-02) is the earliest reviewed Git evidence adding `project_id` to `Site::$fillable`, assigning it on site writes, and using it for project-scoped site queries. The DDL artifact contains the column, index and SET NULL/UPDATE CASCADE FK. No reviewed migration or commit creates them. Application provenance is verified; DDL origin and execution provenance remain unknown.
- **`booking_vouchers`:** commit `564896aa312df5071aaf158df38a9051e89e4471` (2026-06-12) is the earliest reviewed Git evidence writing `delete_reason` to a booking voucher through `forceFill`. Commit `dc9bf7e8e3e464cc9d743cc3ec5b5325782807e6` (2026-06-16) removes that write and changes the workflow to soft deletion. The DDL artifact contains nullable `delete_reason`, with no related index or FK. Current model/controller behavior does not depend on that column, and no reviewed migration establishes its DDL origin.

The DDL artifact was inspected only for relevant CREATE/ALTER definitions; no INSERT data was read. It corroborates the captured physical metadata but cannot prove when a DDL statement ran or that a Laravel migration caused it. Consequently all three remain **UNKNOWN (verified application provenance, unknown DDL provenance)** and remain prohibited from replay or history insertion. The proposed hash-pinned disposition register is in `docs/STAGING_MIGRATION_RECONCILIATION_MANIFEST.md`.

### Signedness, dependencies and disposition

- `project_head_subheads.id` is unsigned INT while finance references are unsigned BIGINT. Its plot/customer IDs are signed BIGINT despite unsigned declarations. `customer_ledger.project_id` is signed BIGINT while project IDs are unsigned BIGINT.
- The nine stock tables consistently use unsigned BIGINT relationships. Parties feed entries/bills; items feed lines/movements/balances; entries feed lines and nullable bill links. All captured stock FK delete actions match declarations. Eight stock tables are compatible; `stock_movements` remains incompatible solely because physical LONGTEXT plus a JSON-validity check is not the declared MySQL JSON type.
- Never replay these creates. Treat the 19 compatible rows only as candidates for a separately approved, hash-pinned history manifest with backup and exact rollback. The 13 incompatible rows need separately designed forward remediation and disposable MySQL rehearsal. The three unknown rows need provenance for every extra field/FK.
- Keep `expences` and `accounting_expenses` separate. Do not create, rename, merge or copy data without approval.
- Keep the commission-detail `down()` defect as a release blocker; never invoke it before a separately reviewed fix.
