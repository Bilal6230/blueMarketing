# Staging migration reconciliation manifest (proposal only)

This is a non-executable review artifact for the verified 2026-10-28 staging capture. It does not authorize migration replay, schema changes, or insertion into `migrations`. Every SHA-256 value pins the current repository migration bytes at base commit `b0acb63eac2d4bed5e1dcb233851b9a972ae5429`. A hash mismatch invalidates the proposed disposition and requires a new review.

The separate disposable rehearsal controls are documented in `docs/STAGING_MIGRATION_REHEARSAL_PROPOSAL.md`; that document is also non-executable and does not provision a database.

The categories describe the next review path. `History-only candidate` means the original CREATE declaration matched the captured target; it does not prove the migration ran. `Forward remediation` means current structure differs and any repair needs a separately designed forward-only change. `Unresolved provenance` means cumulative legacy additions lack complete, independently verifiable DDL history. No row below is approved for execution.

## History-only candidates

| Migration | SHA-256 |
|---|---|
| `2022_10_27_230651_create_lead_user_table.php` | `ab87c4f3617236be5072a1dcd82ef1b476987976d0fc87ccbbd5d9619a567189` |
| `2022_10_29_180639_create_works_table.php` | `694a408a2c929caa0d768d8f235f6661a42b7a24c7e3950e8ed7621dfc6f5226` |
| `2024_04_20_172252_create_installments_table.php` | `9ac5997309873854939034c782195d08f9c99fd527ef25f49f52b0613126661a` |
| `2024_04_20_173235_create_booking_details_table.php` | `c623df19275f45068ab1af6398df01bb7ac50264f8f8c74fdb884feaa6e5d5c2` |
| `2025_01_27_143458_create_journal_voucher_details_table.php` | `d35dd7cec58230bc30d4b7a5b6017a57b8f04166904da34d745941ff9db83654` |
| `2025_08_08_163402_create_stocks_table.php` | `1ab1c43b5d86d3cf6b38c65d0752ced31d73e7dff7fb60f0109eb6645a9982ba` |
| `2025_08_14_123002_create_hold_plots_table.php` | `b34b4f19e03140d67513aeaa0615dec92bfead7c6289df50aa081160bf6a5184` |
| `2025_09_23_235842_create_charge_types_table.php` | `69d8e1143f13a85bd0b1d8e19c71a0f64b6b0dbb028bd0527c4ffccfe4f595fa` |
| `2025_09_25_142048_create_commision_vouchers_table.php` | `5da0dbaad7c8cc2c5df432fb24ae674fc2bd2ad0b43332d68efa3232d2cc006a` |
| `2025_09_25_143458_create_commision_voucher_details_table.php` | `d789c2417083646b365778e989ba75291257c14977b4576547bccdadf757c360` |
| `2026_01_08_000000_create_account_types_table.php` | `630cb72e7eeb192d1d3cbf11e4f17f56cd68651ebb77628c1b76315120ce2eaf` |
| `2026_01_21_043816_create_stock_items_table.php` | `8baf9f2f42bc4da88c2d8233a61ed0c2695344cabb1d801ece5f095f4f8998bb` |
| `2026_01_21_043844_create_stock_parties_table.php` | `a12c9e480ef5561adceddb92fc4018f4ea2964f2f68fbae3446a7820b25f9fbf` |
| `2026_01_21_043857_create_stock_entries_table.php` | `65a60108d59c7133acc48eba390f4335520617aa8cc4107b7ed3e4ef7eee8092` |
| `2026_01_21_043911_create_stock_entry_lines_table.php` | `809f7205047d61da1ecabe153f7e538e319f77985baa2952ac4d2805e93482e2` |
| `2026_01_21_043924_create_stock_bills_table.php` | `babbde197eeeecbceb217e4167d7b658bd58cac10809ae3ebc581e2afc606424` |
| `2026_01_21_043937_create_stock_bill_lines_table.php` | `81f448fa3c949b9ae0a3f5afcbbabae619a643e5ef0848e248e132fb3589c45d` |
| `2026_01_21_044002_create_stock_item_balances_table.php` | `e95c13f1c9f87111837d124cfb95e27d00f085c41e046e3b568c539f8da6ba9b` |
| `2026_01_21_044017_create_stock_counters_table.php` | `1efaf75a3024e2cf14de0df73617380410e824b24a23937472308e70343bfc46` |

Each candidate still requires a fresh identity-gated capture, exact hash verification, dependency review, backup/restore proof, a written rollback that removes only an approved history row, and explicit human approval. The proposed action, if later approved, is history reconciliation only; none may run its `up()` method against the existing table.

For each row, the physical-effect assessment is the corresponding `MATCH` evidence in the 35-row structural matrix: all declared columns, defaults, keys and foreign keys matched the captured target. The dependency gate is the ordered foreign-key review in that matrix and the stock dependency-chain review in `STAGING_MIGRATION_STRUCTURAL_EXPECTATIONS.md`. The post-verification condition is a fresh identity-gated metadata capture with exact table/column/index/FK cardinality and an unchanged application-data aggregate. A failed or incomplete condition leaves the row blocked. These conditions apply independently to every candidate; the list is not a blanket approval.

## Missing-history ALTER migrations tracked separately

The 35-row CREATE manifest is only one part of the 38 missing-history scope. These three ALTER migrations remain separate because their targeted effects were absent in the capture and none is a history-only candidate:

| Migration | SHA-256 | Observed effect | Disposition |
|---|---|---|---|
| `2026_03_31_000001_add_site_id_to_journal_vouchers_table.php` | `99c5375c2fe9870dbe257587f3cfbb1ca63d9d4161fe8296fcab3e957bb3198f` | `journal_vouchers.site_id` absent | Forward review only; historical backfill prohibited |
| `2026_05_02_000001_add_amount_to_plots_table.php` | `162e8f6340b0bf680a440a4a918f73a200dbb27e8449f8580bbf644f507c774d` | `plots.amount` absent | Forward review only; no replay against unrelated schema |
| `2026_10_24_000003_add_landing_builder_fields.php` | `f7df6d53227b2ba7c7d7fe12da832be6de737ed40a2663bc190b86165e968c7e` | `section_order` and `project_overrides` absent | Forward review only; verify exact columns before approval |

Together with the 35 CREATE rows these account for all 38 missing history records; no ALTER row is omitted.

## Forward schema remediation candidates

| Migration | SHA-256 | Required disposition |
|---|---|---|
| `2022_10_22_150000_create_project_head_subheads_table.php` | `8c73d0cc472f7fa43176adb9154d48ee440044410a8daf4e176e02dbfc737590` | Resolve key widths, nullability, signedness, and extra FKs in a forward-only design. |
| `2022_11_15_135624_create_expences_table.php` | `7dd799b23947978ab52d516ee4f5b236007a0629d4fdb3412641019c0b847799` | Keep `expences` distinct from `accounting_expenses`; establish intended domain before any schema action. |
| `2024_04_20_165837_create_bookings_table.php` | `c8191f02c2414b01e8ffea6124fa9ddd21cd88dba2e1033fa1592dbaa3869609` | Review nullable broker/cancel status and later cancellation/soft-delete evolution. |
| `2024_04_25_221615_create_customer_ledger_table.php` | `5e9ab60252cc9e07670e4a60147d5a370766fc2b29a387d552793f93b00813c1` | Resolve signedness, nullability, LONGTEXT/JSON, and cumulative extras without rewriting financial history. |
| `2025_01_27_142048_create_journal_vouchers_table.php` | `e49887ae6ffcbbdde0413842304d60baf6f0eafcce4aa763587a20c8ed79df02` | Preserve numeric project/type voucher semantics; do not recreate the missing global unique key blindly. |
| `2025_08_01_055838_create_dasticash_table.php` | `f482476f7dcda917f338270341993833f873688085d362eed04a2d46ab1be95c` | Review nullable project and FK update action. |
| `2025_08_08_101553_create_draft_ledgers_table.php` | `44c7c150a6d93c058fa2b099f1d0ddead00491d4ae42a698a54b7d02988b535f` | Resolve required versus nullable passing status. |
| `2025_08_08_112841_create_labours_table.php` | `10a1aae5c2e599e51679dc7842770725780724931e97acd342c3a08be52ec201` | Review required fields, nullable project, and later advance column. |
| `2025_09_24_020119_create_pending_updates_table.php` | `d88b23cdbc8766b12bf3c1d3c77716ceac3b831818a1093effacb8bda6326da2` | Decide native JSON versus validated text using application and server compatibility evidence. |
| `2025_11_13_050518_create_labour_attendances_table.php` | `9e2a3c415f0cd063e1ddf9647a44f8690319c85ca56de294dd15c2c29d411d3e` | Resolve nullability and project FK action without orphaning attendance history. |
| `2025_12_01_031728_create_labour_ledgers_table.php` | `5fd178aee5a59481e6b45dbb1e8771251e5c93f39fc2f1d03721ed77c6136f73` | Decide JSON representation and explain the extra project relationship. |
| `2026_01_21_043949_create_stock_movements_table.php` | `f18335bd04192c5f687c90d368bafda3c487474abdacf849c279db528b7766bf` | Review native JSON versus LONGTEXT plus `json_valid`; stock FK chain otherwise matched. |
| `2026_09_08_000001_create_booking_edit_audits_table.php` | `72c2fd180407bf579dde23f8525efe373ce376accc345150eaf7c17386484c7f` | Review native JSON versus validated LONGTEXT before forward remediation. |

These rows are prohibited from history-only treatment. Any future proposal must state data validation, lock/runtime impact, application compatibility, backup and exact rollback/recovery behavior.

## Unresolved provenance

| Migration | SHA-256 | Verified evidence and unresolved origin |
|---|---|---|
| `2024_01_14_150839_create_ledgers_table.php` | `3d4e0e509f7c6922e6bbd8f6eea882127bee543cb2cc211f21fefb871a2c5147` | Declared structure matches. Git commit `a82ed439430d96ac3e0615157f2399cc468c032e` first verifies application use of `customer_ledger_id` and `charge_type_id`; `8043f13d0d60f3a336454f40c998ccdb27e45287` first verifies `voucher_number` model/write use. A local DDL snapshot independently contains those columns, `idx_customer_ledger_id`, `idx_charge_type_id`, and SET NULL/CASCADE FKs. No reviewed repository migration or commit establishes when or how the columns, indexes, or FKs were created. |
| `2025_11_13_025816_create_sites_table.php` | `6aefc7d91a7a60e1ab96b5f295808d665d9363c2d2cc89f962ec43040fd103bb` | Declared structure matches. Commit `6b028f63d2f79ad238b0f265624b1350e88e6890` first verifies `Site::project_id` fillable/write/filter behavior. The DDL snapshot contains `project_id`, `idx_sites_project_id`, and a SET NULL/CASCADE project FK. No reviewed migration or commit establishes their DDL origin. |
| `2026_04_20_180000_create_booking_vouchers_table.php` | `9f8589bfab1e3c73163f9c04920f3ceeaa0cc37ca2f5bbf499f3ebfd83b139a1` | Declared structure matches. Commit `564896aa312df5071aaf158df38a9051e89e4471` first verifies writing `delete_reason` to `BookingVoucher` via `forceFill`; commit `dc9bf7e8e3e464cc9d743cc3ec5b5325782807e6` removes that write in favor of soft deletion. The DDL snapshot contains nullable `delete_reason` with no related index/FK. Its DDL origin remains unverified, and current model/controller behavior does not require it. |

The local SQL artifact was inspected only for these tables' `CREATE TABLE` and `ALTER TABLE` definitions. No INSERT statement or business row was read or published. The artifact corroborates captured physical structure but is not timestamped execution provenance and cannot prove any Laravel migration ran. These three classifications remain `UNKNOWN`, refined as **verified application provenance with unknown DDL provenance**.

## JSON versus LONGTEXT rule

Native MySQL `JSON` and `LONGTEXT` with a `json_valid` CHECK are not treated as structurally identical. The comparison must preserve base type, collation, default/nullability, and the exact validation constraint. Laravel casts or `json_encode`/`json_decode` usage can show application compatibility, but cannot prove storage equivalence or authorize a conversion. LONGTEXT without a captured validation CHECK remains incompatible with a declared JSON column.

## Prohibited rollback and replay paths

- Never replay any of the 35 CREATE migrations against the existing staging tables.
- Never call `down()` on `2025_09_25_143458_create_commision_voucher_details_table.php`; its destructive rollback defect remains a release blocker pending a separately reviewed migration-code fix.
- Never use rollback as a way to reconcile history. History-row correction, if ever approved, must have a history-only recovery procedure and must not drop schema objects.
- Never treat a matching hash, physical object, application dependency, or DDL snapshot as proof of historical migration execution.

## Required approval record

For every future disposition, record: migration filename and SHA-256; exact capture identifier; history and physical statuses; dependencies; observed application use; proposed operation; dry-run/rehearsal evidence; backup and restore evidence; recovery steps; reviewer; approver; and approval timestamp. A missing field leaves the item blocked.
