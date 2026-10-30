### 2026-10-30 DATABASE V2 — FIVE ENGINEERING MILESTONES, ACCEPTED PROGRESS 25%

**Canonical progress:** [`docs/DATABASE_V2_REBUILD_ROADMAP.md`](DATABASE_V2_REBUILD_ROADMAP.md) — **6/24 = 25%** fully evidenced acceptance gates: P1.G1 + P1.G2, P2.G1, P3.G1 + P3.G2 + P3.G3. Earlier 4% updates below are historical and superseded; DO NOT infer deployment readiness.

1. **57-table source schema verified and committed without customer data:** `database/v2_schema/legacy_columns_01..04.json` (57 original table structures), `legacy_constraints.json` (133 ALTER blocks / 40 FK constraints), `v2_verified_extra_fks.json` (46 additional correctly classified source relationships). Of 54 inferred ID columns, remaining 8 are deliberately non-FK polymorphic/business identifiers. Owner-approved business policy remains pending.
2. **57-table full audit with corrected parents and cross-project exceptions:** `docs/DATABASE_V2_57_TABLE_RELATIONSHIP_AND_TENANT_ACCEPTANCE_2026_10_30.md`. `Booking::broker()` is `SubheadAccounting`, **NOT** `Lead`; 163 non-null source values valid. `sites.subhead_accounting_id` is really `project_head_subheads.id`, 7/7 valid; bank IDs 1..27 all valid for 1,391 references. Cross-project comparisons may reflect shared global master/assignment: `leads.area_id` 7,976/13,391; `leads.follow_id` 8,022/13,391; `punches.user_id` 20/30; no automatic rectification or composite tenant FK.
3. **Real Laravel clean V2 migration code:** `database/v2_migrations/000010` bank, `000020` full 56 application table Schema::create + isolated `legacy_migration_history`, `000100` 10 typed finance tables. See full filenames in `docs/DATABASE_V2_FIVE_MILESTONE_EXECUTION_2026_10_30.md`. Existing production `database/migrations` untouched. V2 isolated MySQL 8.4 creates **69 tables / >=108 FKs**, preserves original ID/time/money source fields, restricts deletes, tests journal scoped unique and duplicate bank key rejection.
4. **Verified disposable MySQL 8.4 schema + rollback:** GitHub Actions run [41092687968](https://github.com/Bilal6230/blueMarketing/actions/runs/41092687968): V2 MySQL job **SUCCESS**, after creating baseline + bank seed, exact FK/unique/unsigned checks, invalid ledger FK reject, synthetic historical timestamp/amount preserved, parent delete RESTRICT, full rollback and clean re-run. V2 SQLite/Python step **SUCCESS** in same run. The *existing* overall finance-safety workflow is **red on older unrelated finance regressions**; do not describe full CI as green.
5. **Historical import staging code and regression:** `tools/database_v2/v2_import_plan.py` checksum verifies private dev SQL; 57-source/86-FK dependency-aware parent-first plan, historical SQL IDs/timestamps/values untouched, old 14 Laravel migrations redirected to archival `legacy_migration_history`, zero sensitive rows committed; Python synthetic test confirms 57 table order / fidelity / fail-closed. **NOT APPLIED** to MySQL or any user data. Exact JV/sales/labour/stock source attribution unresolved in old rows; no guess backfill.

**NEXT:** complete all indirect/raw writer+reader paths (P1.G3), exact source-kind exclusivity and no-cross-project financial posting (P2.G2), project/softdelete business policy signoff (P2.G3/G4), isolated 120,165-row import and row/timestamp/ledger/booking balance reconciliation (P4), code refactor and cutover (P5/P6). Do not run `migrate:fresh` or import to existing databases.

### 2026-10-30 Database V2 multi-milestone implementation + isolated CI verification

- **APP SOURCE COVERAGE:** 189/189 `app/**/*.php` files on explicit branch reviewed by direct-token scan, 96 candidate ledger/raw-statement matches, no fetch errors. Twelve batch evidence files `docs/database_v2_scan/APP_LEDGER_SCAN_01.md` through `12.md`, canonical `docs/DATABASE_V2_LEDGER_WRITER_MATRIX_2026_10_30.md`. Exact dynamic/raw SQL/route writer certification remains incomplete; direct-token coverage is **not** an exhaustive mutation proof.
- **NEW CODE:** isolated `database/v2_migrations/` bank table and finance posting batch/line + 7 typed source-FK tables + exact journal detail link migration; strict `V2LegacyBanksSeeder` preserving 27 legacy bank IDs. These are manual explicit-path V2-only files; V1 deployed migrations untouched. Finance migration REQUIRES normalized 11-table source/account V2 baseline; 57-table greenfield baseline NOT yet created.
- **NEW CODE:** `tools/database_v2/legacy_import_plan.py` with SHA256/row-count validation, declared FK-parent ordering, optional data-only SQL output outside Git tree; no MySQL connection/restore/import performed. Synthetic tests `tools/database_v2/tests/test_legacy_import_plan.py`. Real uploaded dump independently parsed: 57 tables, 40 FKs, 396 INSERT statements, 120,165 rows, SHA256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`.
- **NEW CODE:** isolated `App\\Services\\Finance\\V2JournalPostingWriter` transactionally derives exact persisted JV detail legs; validates account project, cents-balanced totals/header, idempotency and replay posted-line account/amount integrity. No legacy controllers/production routes call it. SQLite tests cover valid posting, same-key replay, cross-project rollback, unbalanced rejection, duplicate-source failure, conflicting key and tampered amount.
- **CI VERIFIED:** <https://github.com/Bilal6230/blueMarketing/actions/runs/41088160648> — isolated **Database V2 schema lint and import planner regression** step SUCCESS (PHP 8.3 / SQLite memory / Python synthetic tests) after `2afc7666a`. Full GitHub workflow overall FAILS at older `End-to-end finance safety regression suite` step; unrelated full CI remains NOT green. No MySQL 8.4 or actual import tested.
- **PROGRESS:** Four substantive implementation sub-milestones, but gate acceptance still **1/24 = 4%**: P1.G2 full 54 candidate semantic map not complete, P1.G3 indirect dynamic source review not complete; full 57-table clean V2 baseline and MySQL staging/import/cutover remain pending. All V1/staging/live database rows/migrations untouched.

### 2026-10-30 Database V2 — verified bank/site source semantics and schema blueprint

- **Added** `docs/DATABASE_V2_SCHEMA_BLUEPRINT_2026_10_30.md`: proper dedicated banks lookup, correctly renamed site project-account FK, proposed immutable posting batches+lines and separate typed source-FK link tables, dependency-aware import order and explicit approval gates. Design only, NO migrations/run.
- **Proved bank IDs with code + dump:** `AppHelper::getPakistanBanks()` explicitly defines 27 IDs 1–27; uploaded dev dump has 1,345 `customer_ledger.bank_id` values and 46 `booking_vouchers.bank_id` values, **all 1,391 match** the current code list, zero unknown. `bank_id` is NOT project_head_subheads FK; preserve original numeric IDs to `banks` reference table on V2.
- **Proved misleading site field with code + dump:** `LabourController::siteStore` saves form sub-account into `sites.subhead_accounting_id`; `createVoucher` reads it as `project_head_subheads.id`, not `subhead_accountings.id`. **All 7/7 sites** match project account parent, **0 project mismatches**. Previous claimed '2 missing subhead_accounting parents' came from a FALSE inferred target and is corrected. V2 rename `sites.project_head_subhead_id`, align key width, FK correct parent.
- **FK check nuance:** all 40 FK constraints have zero unmatched values; **28** have non-null exported child references and **12** have no non-null child rows. Earlier preliminary 30/10 evidence coverage was incorrect; do not claim empty FK constraints were exercised.
- This is genuine P1.G2 progress, but full **54** unconstrained candidate code relationship map and all code writer paths remain unfinished, so **1/24 gates = 4%** until acceptance. All source data and live/staging databases unchanged.

### 2026-10-30 Database V2 P1.G2 substantial inspection — 57-table relationship matrix (GATE STILL OPEN)

- Inspected uploaded `u429032065_dev.sql` offline, SHA-256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed` and verified 57 tables, **120,165** rows, **40 declared FK relationships with zero missing parent IDs**, and **54** remaining unlinked `*_id` candidate columns (corrected preliminary **56**). The candidates include polymorphic/business values, not 54 missing foreign keys. No private dump values committed.
- New `docs/DATABASE_V2_RELATIONSHIP_MATRIX_2026_10_30.md` contains all 57 table row/FK/deleted_at statistics, the 40 real FK relationships grouped by module, candidate classification, V2 FK/type policy and exact PHP writer evidence.
- Important evidence: Bank `bank_id` is `getPakistanBanks()` code-list identity through `AppHelper::getBankNameById()`, **NOT** `project_head_subheads.id`. Naive orphan counts 9/46 booking vouchers and 254/1345 customer ledger are **false parent assumptions**, not actual bank-orphan proof. Recommend dedicated `banks` lookup with preserved IDs after source validation.
- `sites.subhead_accounting_id` has **two of seven** non-null values without a matching `subhead_accountings.id` in this export. Must inspect semantics and owner-reviewed mapping before FK.
- Checked actual branch code: `LedgerController::update` updates posted CR/CP amounts/account/date under checks and locks; `BookingController::deposit` can change linked PPR ledger metadata; `BookingController::cancel` writes cancellation ledger flows and has preexisting financial-source guards; stock JV `type_id` remains generated sequence. Add correct finance reversal/versioning in V2 instead of blind hard-update.
- Local read-only reproducibility artifacts prepared from supplied dump: `BlueMarketing_V2_Full_Relationship_Dictionary.md` (57-table + 40-FK + 54 candidates) and metadata-only JSON; SQL itself was not imported, connected, modified or committed.
- P1.G2 is **IN PROGRESS** until all 54 candidates and source writers are code-reviewed, project/cardinality checks complete, and exact V2 FK/no-FK disposition confirmed. Overall remains **1/24 = 4% verified gates**; do not inflate completion for unverified work. Production/live/staging unchanged.

### 2026-10-30 — DATABASE V2 REBUILD STARTED — uploaded dev SQL baseline / 4% gate progress

- **Project roadmap:** `docs/DATABASE_V2_REBUILD_ROADMAP.md` — six phases, four independently verifiable acceptance gates each (24 total), progress bar **1/24 = 4%**. Phase 1 initial snapshot check done; full 57-table code relationship map and source ownership audit remain pending; Phase 2-6 not started. Progress is acceptance-gate bookkeeping, NOT a production readiness or engineering time estimate.
- **Read-only assessment:** `docs/DATABASE_V2_LEGACY_SNAPSHOT_AUDIT_2026_10_30.md`. User uploaded `u429032065_dev.sql`: MariaDB 11.8.9 dev dump, **57 tables / 120,165 rows / 40 declared FKs / 16 declared UNIQUE keys / 10 tables with deleted_at**; 14 legacy migrations rows. Distinguish from previous MySQL `bluenew` or staging captures.
- Exact counts: 18,003 ledgers, 2,970 JV-typed ledger rows, 991 JV headers, 5,147 historical JV details, 5,580 customer_ledger sources. 30 materialized FK relationships and selected extra candidate relationships had zero observed missing parent IDs, but **no universal ledger source identity proof**. 3 JV-typed ledger rows match a journal header numeric ID in a different account project; they are ambiguous candidates, not auto-void targets.
- Verified structural incompatibility: `ledgers.project_head_subheads_id` BIGINT UNSIGNED vs `project_head_subheads.id` INT UNSIGNED; `journal_voucher_details.account_id` likewise. `journal_vouchers.project_id` signed INT vs `projects.id` unsigned BIGINT; many booking and customer_ledger key width/signedness gaps. Must resolve before true FK migrations.
- Uploaded dump's ledgers has **no journal_voucher_detail_id**, and dump has **no ledger_source_attributions** table. Current code capability is not deployment confirmation.
- **No** DB restore, schema/data edits, application changes, migration run, financial backfill, or deployment. Private SQL not committed. Keep existing safety guards, immutable history and per-table delete policies. Next: P1.G2 57-table relationship dictionary + key compatibility; P1.G3 exhaustive code writers/readers; then V2 schema approval.

### 2026-10-29 branch audit continuation — verified stock/labour JV identity collision

- New documentation: `docs/LEDGER_SOURCE_IDENTITY_REPOSITORY_AUDIT_2026_10_29.md` (partial VERIFIED matrix; NOT complete whole-tree audit).
- Verified current StockController::postEntryLedger and bill posting both set ledger type JV but type_id=getLastLedgerIdByType('JV')+1, unlike real JV header ID; references encode STOCK-ENTRY:{id} and STOCK-BILL:{id}, with customer_ledger_id. Thus type+type_id alone is not a reliable JV header FK even in current code.
- Verified LabourController::createVoucher creates JV detail rows and two JV ledgers but inspected inserts omit journal_voucher_detail_id. Labour payment CP has independently generated type_id and customer_ledger_id.
- Current LedgerController report calls LedgerVoucherDisplayService, which now falls back to raw ledger number absent trusted typed attribution; do not describe historic unconditional JV header resolution as still active. Current LedgerSourceAttributionWriter only supports journal_voucher/commission_voucher and requires header-based type_id, so stock needs source-specific redesign.
- No finance code, tests, DB, migration or live/staging data changed. Full inventory and tests remain pending. Financial integrity not certified.

### 2026-10-29 handoff — resume comprehensive ledgers.type/type_id/reference write-path inventory

- User's CURRENT request: inspect **the entire BlueMarketing project** and map every `ledgers` CREATE/UPDATE/DELETE writer and the precise meaning of `type`, `type_id`, and `reference` for **every voucher family** (JV, CV, CR/CP, PPR, EC/ECP, stock, labour, booking etc.). Explain source-table keys, whether reference is identity or business slip/freeform, project scope, risk of collision, and exact code file/method. This whole-project inventory is **NOT complete yet**, despite prior targeted audits; next chat must complete it, not claim completion or jump to modifications.
- Verified targeted inspection of current `app/Http/Controllers/JournalVoucherController.php`: `store` creates header and each detail and creates corresponding ledger leg with `type='JV', type_id=journal_voucher.id`, project account in `project_head_subheads_id`, `reference` from form; `journal_voucher_detail_id` written when optional new column exists. `update` and `destroy` currently unconditional 409 safety gates. Historical tests had reproduced destructive cross-JV update behavior before gates.
- Verified targeted inspection of `app/Http/Controllers/LabourController.php`: `labourPayment` creates `LabourLedger` per employee, `CustomerLedger` transaction_type `CP` and an `Ledger` row `type='CP', customer_ledger_id=source.id, type_id=getLastLedgerIdByType('CP') + 1, reference=request.reference`; numeric ledger `type_id` is a generated series marker, **not** `LabourLedger.id`; `customer_ledger_id` is durable source pointer. Verify surrounding transactions and other labour/JV paths before final conclusions.
- Previous code/source audit in `docs/PHASE1H_LEDGER_REFERENCE_AUDIT.md`, `docs/PHASE1D_APPLICATION_WRITE_AUDIT.md` and `docs/PHASE1R_LEDGER_REPORT_READER_MAP.md` documents confirmed historical: legacy CV store wrote `ledgers.type='JV'` with CV header ID, CV update wrote `JV` with CV *detail* ID and deleted `JV` rows by colliding numeric ID; targeted isolated test reproduced unrelated JV ledger deletion. CV store/update/delete now 409-gated on upgrade branch; verify live implementation. `LedgerController` JV report resolves `type_id` as JournalVoucher header ID and can misattribute CV rows. `BookingSalesAccountingResolver` uses header references, `AppHelper` numbering uses type and voucher. **Reference** is not proven globally unique and should not be treated as an FK.
- `StockController.php` inspected targeted: stock inventory entry/bill/movement tables and deletes of stock entities confirmed; do not assume all stock ledgers are written there; trace all stock, booking, ledger, finance writers. Prior audit and source are not exhaustive; GitHub `search` returned no matches even for known `type_id`, so connector code search may index only default branch—use explicit branch `fetch_file` and/or user local read-only ripgrep/PowerShell indexed scan if full tree cannot be obtained. Do not manufacture 'all writers' claims.
- Live server screenshot: `u429032065_staging_bluema` JV header #113 project 5, 12 detail rows of which first six soft-deleted and six active, but `ledgers WHERE type LIKE '%JV%' AND type_id=113` has zero. Strong symptom of historical edit/repost bug, not yet runtime proven. **DO NOT** run repairs against hosting without separate permission. User local `bluenew` was directly repaired after explicit approval: 20 JV headers/148 legs, source-side debit+credit PKR 3,022,883 each. Later dry-run zero eligible; not a complete reconciliation. Local JV 1012 project 3 four legs match; unrelated numeric collision row 11822 project 5 PKR 76,921, user independently deactivated and saw Hand Cash return to PKR 11; original stock source still unverified.
- Correct data model: JV Header.id -> journal_voucher_details.journal_voucher_id; `ledgers.type='JV', type_id=JV header.id` stays for backward compatibility; optional `ledgers.journal_voucher_detail_id` FK+UNIQUE for new-leg provenance. MySQL migration exists but is NOT confirmed applied. Type/type_id polymorphic collision means account project + amount + source-specific attribution required, not just numeric match. Avoid blanket deletion, renumbering, and new financial posting without evidence.
- P0.1 prior **98% engineering estimate** remains NOT certified. Next high priority after comprehensive inventory: fix all ambiguous/dangerous write paths with source-kind provenance, atomic transactions, idempotency, verified one-leg-per-detail uniqueness, regression MySQL testing, compatibility with legacy records. Preserve other modules. Repository `Bilal6230/blueMarketing`, branch `upgrade/laravel-framework`, local path `C:\\laragon\\www\\blueMarketing`, local DB `bluenew` MySQL 8.4.3, user language Roman Urdu; minimize repeated PowerShell commands.

### 2026-10-29 verified live bluenew JV recovery and next prevention phase

- User explicitly authorized live repair to the configured local MySQL 8.4.3 database `bluenew`. After two read-only dry runs, executed `finance:repair-missing-jv-legs --database=bluenew --id=<ID> --apply` for 20 selected JV headers (IDs 9,14,15,25,113,150,158,169,301,378,380,588,657,988,989,990,992,993,994,996); user terminal reported **148 inserted ledger legs**, each transaction_committed=true. Expected restored side debit and credit each PKR 3,022,883.00. A subsequent no-apply rerun returned **0 eligible headers and 0 insertable legs**. Do not conflate this with complete historical reconciliation.
- User investigated journal header 1012 (project 3, 4 detail legs). New read-only `finance:audit-jv-ledger-parity` returned 4 matched ledger rows and zero missing/extra *within owning project*, with legacy displayed voucher numbers 640–643 versus header voucher number 119. A fifth ledger row 11822 reused `type='JV', type_id=1012` but belongs to project 5 (account 408), amount_out PKR 76,921.00, description `Shaikho 12mm 323.2 Kg @238/Kg`, date 2025-12-16. User manually set its `is_active=0` and observed dashboard Hand Cash balance return to PKR 11. Original source provenance and whether this stock entry was valid are NOT proven. Do not automatically deactivate other cross-project JV rows.
- Current `finance:audit-jv-ledger-parity` counts only project-scoped header-ID/account/amount multiset matches and reports legacy voucher-number differences; full audit console excerpt was truncated (980,124,2 without full headings), so do not claim complete audit numbers.
- P0.1 prior **98% engineering estimate remains provisional, not financial integrity certification**. Follow-up highest priority: trace all JV, stock, labour and raw SQL ledger writers; eliminate polymorphic `type='JV'/type_id` collisions with explicit provenance; require atomic detail+ledger write, idempotency and one-leg-per-detail DB uniqueness; regression test 1:N legs, cross-project conflicts, retry, rollback, legacy numbering and posting edits. Test MySQL 8.4 migration before live application, no blanket cleanup of historical stock/labour rows.

### 2026-10-29 physical JV provenance constraint acceptance test implemented

- Created `tests/Feature/JournalDetailProvenanceMigrationTest.php` (`904a6623`): disposable in-memory SQLite schema; applies the new nullable unique `journal_voucher_detail_id` migration; verifies multiple legacy NULLs remain valid, duplicate exact-detail posting rejected and nonexistent detail ID rejected by FK. **Test file committed but not executed on user's Windows PHP/MySQL or in this tool runtime**; do not call it PASS until the feature test runs.
- Source balance restoration is **not yet done**. Real snapshot's 84 missing JV headers all compete with existing same-project `JV` displayed numbers; provenance is not proved by `type_id` or number. `JournalVoucherRecoveryPlan` deliberately blocks these cases; avoid inventing financial postings/approving uncertain cash adjustments. Do not apply migration on user database until tested on isolated restored clone with explicit approval.
- Stronger new-write protection implemented in code; historical 84 JV balance deltas and 101 CR number collisions require owner-reviewed evidence packet for accurate reconciliation. Continue using uploaded dump for investigation rather than asking repeated local audits.

### 2026-10-29 direct implementation after full SQL dump (no user commands)

- Added read-only bulk JV recovery evidence command `finance:jv-recovery-evidence` (`71a9d175`): enumerates missing general JV headers, detail evidence, exact account-level debit/credit impacts, competing same-series ledger rows, flags unresolved historical provenance and never writes. This is for finance approval and restoration planning.
- Added independent fail-closed `App\Services\Finance\JournalVoucherRecoveryPlan::inspect()` (`1287fd28`), checking header validity, nondeleted details, project ownership, cent-balanced nonzero lines, header-detail totals, polymorphic type_id collision and same-number JV ledger competition. Returns explicit blockers. No unapproved repairs.
- Strengthened nullable `ledgers.journal_voucher_detail_id` migration (`6c82a4d8`) with **UNIQUE** per non-null detail ID in addition to FK, guarding new JV posting double inserts at DB level without modifying legacy NULLs. No migration was applied to any user DB; deployment requires isolated restore testing.
- User has supplied the whole SQL dump and is frustrated by repeated manual commands. Continue offline/code work; do not demand another audit command. 84 candidate missing headers all have displayed-number collisions; cannot auto-post without verified provenance. 98% engineering estimate is not a declaration of repaired historical accounting.

### 2026-10-29 JV 9 specific conflict and bulk triage command

- User verified JV 9 on MySQL: project 3, pending, two balanced Rs 10,000 lines, zero JV ledger rows keyed by header 9, but displayed JV voucher number 9 also appears in ledger row 5593 with type_id=1, account 167, Rs 1,334 debit. This is not a proven posting for header 9 and must be attributed to the actual stock/labour/general source before any recovery.
- Added `finance:inspect-all-missing-jvs` (`33b1f8e`), read-only batch inventory of absent general JV header-ledger sets including detail-side sums, ownership, same-project same-series voucher collisions and per-case manual review flags. No historical writes or automatic authorization. Await local execution of batch command; no production migration applied.
- Current status: recovery code architecture and provenance FK committed but historical reconciliation not executed. Do not certify P0.1 100%.

### 2026-10-29 JV #9 recovery preflight results

- User pulled and linted JV recovery/FK changes successfully, Git clean. Read-only `finance:inspect-missing-jv 9` on configured MySQL: project 3, voucher 9, pending header, 2 details, debit and credit each PKR 10,000, accounts/lines valid, 0 JV rows keyed by header ID, but 1 same-project voucher-number ledger row; `candidate_for_manual_recovery_review=false`. **No repair authorized**.
- Added series-scoped collision evidence in inspector (`11dcec6`, `a30cd7c`): return existing `JV` ledger IDs, polymorphic type IDs/accounts/amounts sharing displayed voucher; excludes unrelated voucher series. Read-only. Pending local or offline verification. Do not insert missing JV legs on a mere header/detail match.
- Migration remains unexecuted. No financial data changed. P0.1 estimate unchanged 98%.

### 2026-10-29 implementation: safe JV provenance and recovery evidence

- Uploaded `bluenew.sql` offline investigation: 84 active JV headers (272 nondeleted detail rows) with no JV ledger legs; total missing side debits = credits = PKR 4,941,032.00; project 3=11, 5=10, 6=63. This is a **reconciliation candidate**, not authorization to backfill. `docs/JV_DUMP_RECOVERY_ASSESSMENT.md` committed earlier.
- Added per-header read-only `finance:inspect-missing-jv <id>` command (`60f1257`) checking balanced details, same-project accounts, any JV type_id polymorphic conflicts, and same-project voucher-number legs. Returns a review packet with explicit `automatically_safe_to_post=false`. No writes.
- New nullable `ledgers.journal_voucher_detail_id` FK migration (`73b9229`), new ledger model fillable attribute (`b36294a`), and active `JournalVoucherController::store` writes exact persisted detail ID to new JV ledger leg when column exists (`873f275`). Migration does NOT backfill historical rows and is NOT applied merely by merging code. Needs test on disposable restored database, backup/restore and migration approval before deployment.
- P0.1 still **98% estimated** pending isolated migration + feature tests, historical accounting reconciliation, and approved idempotent repair. Do NOT issue blanket ledger inserts, unknown balance adjustments, production `migrate`, or historical renumbering.

### 2026-10-29 uploaded full SQL dump direct analysis — source numbers != posted voucher numbers

- User supplied `bluenew.sql` MySQL 8.4.3 export. Offline parser read 5,580 `customer_ledger`, 17,855 `ledgers`, 561 project accounts, 991 JV headers, 5,147 JV details without restoring or mutating the dump. Source-number collision groups: Project 3 CR 6, Project 5 CR 91, Project 6 CR 4 (202 involved source rows; 2 have no linked ledger, 7 inactive). **98 of 101 groups have distinct posted ledger voucher numbers**, so historical `customer_ledger.type_id` should not be assumed equal to ledger voucher. Example Project 5 source number 1: source IDs 1621/2692 map to ledger voucher 474/594.
- Overall no linked-ledger orphaned customer-source rows (0), missing project account records (0), cross-project source/account links (0) in this dump. Distinct linked customer-source collisions on actual ledger voucher numbers also exist (16 groups across CR and CP/project); these need case-by-case historical provenance, NOT automatic mutation. No inferred balance repair or renumbering.
- Extended read-only all-project audit to separately count posted ledger voucher number groups with multiple linked sources and show sample groups (`8024d5c`). This is substantive code correction prompted by actual dump evidence; user local test pending.
- Keep P0.1 at **98% implementation estimate**: historical source-level uniqueness not deployable; even posted voucher number has collisions; do not claim database-level constraints or 100% finance certified.

### 2026-10-29 verified refined all-project audit and collision distribution follow-on

- User confirmed local audit: 5,377 financial sources, 101 **positive-number** duplicate groups, 1 missing number/project, 3,771 blank references, 49 repeated PPR references; PHP lint clear, git clean. The real historical number collisions prohibit blanket UNIQUE creation without ownership reconciliation.
- Added nonmutating project/type collision distribution output to all-project audit (`16025769`, corrected grouped-query build `7e779edc`); local verification pending. This is for impact scoping only, not an index migration or automated repair.
- P0.1 **98% estimated**, not production approved; remaining evidence/compatibility is genuinely blocked by 101 historical collisions, not missing command executions.

### 2026-10-29 verified all-project collision classification

- User verified refined MySQL audit syntax/clean working tree: 5,377 sources (CP 2,795 / CR 937 / PPR 1,645); **101 positive voucher-number duplicate groups** (not zero-number artifacts), **49 duplicate reference groups** (PPR reuse), 3,771 incomplete by previous broad rule. By type blank references: CP 2,795, CR 937, PPR 39. CP/CR historical blank references are common; do **not** label all as broken voucher identities. CR has 1 missing/zero number; no EC/ECP sources in this snapshot.
- Fixed audit classification (`8d622e18`): separate missing project/zero number from blank business slip references, report explicit positive-number UNIQUE index readiness and avoid recommending blanket reference UNIQUE. Read-only, local rerun pending.
- **P0.1 remains 98% implementation estimate and cannot be certified 100% on this historical database**: 101 existing positive-number source collisions block conventional (project,transaction_type,type_id) UNIQUE creation. Do not auto-renumber/merge historical ledger sources. Source ownership and reversible recovery require independent evidence/approval under P0.5; new source consistency guards remain in application layer.

### 2026-10-29 real all-project legacy uniqueness evidence — physical index blocked

- User ran `finance:audit-all-source-uniqueness` on configured MySQL: **5,377** finance sources, **101** duplicate project/type/number groups, **49** duplicate project/type/reference groups, **3,771** incomplete identity rows; `candidate_unique_index_ready=false`, no data changed and PHP syntax clean. This is a confirmed **physical UNIQUE index blocker**. Sample number collisions include project 3 and 5 CR; PPR reference reuse in project 2 suggests references may be slip IDs, not guaranteed unique voucher keys. Do not automatically deduplicate, renumber, delete, or apply production indexes.
- Implemented refined read-only `finance:audit-all-source-uniqueness` (`6349a468`): reports positive-number duplicate groups separately from zero/missing legacy rows, per-series zero-number/missing-reference counts, shorter 10-example conflict previews and up to 20 positive collisions. Await local execution. No schema mutation.
- **P0.1 remains 98% engineering implementation estimate, NOT 100% certified**: real historical contradictions prove that a blanket unique constraint cannot be deployed safely. Further source-specific policy and a reviewed migration/legacy-exception design are necessary. P0.5 historical recovery cannot be silently included as an automatic P0.1 repair.

### 2026-10-29 real MySQL index and Project 1 source preflight verified

- User pulled `e6513c4`, lint clear and Git clean. Live configured MySQL schema inventory: `journal_vouchers` has UNIQUE `unique_project_type_voucher(project_id,type,voucher_number)`; `customer_ledger` has only PRIMARY and nonunique customer FK index, and `ledgers` only PRIMARY plus nonunique linked-source/charge indexes. Required finance columns all present. `finance:preflight-ledger-constraints 1` scanned 2 sources and 4780 ledger legs with zero reported issues. This is NOT whole-database assurance; that preflight includes several historical ledger families and does not establish uniqueness for other projects.
- Added `finance:audit-all-source-uniqueness` (`f25998fd`), an **all-project aggregate-only** MySQL/SQLite-compatible read-only inventory for duplicate number and reference groups and missing/invalid identities in CR/CP/PPR/EC/ECP. Returns counts and up to 100 collision examples, no ledger mutations or index creation; acceptance pending local execution.
- P0.1 **98% estimated** pending all-project collision evidence, historically compatible source-aware constraints and verified raw write-path handling; never imply schema/index readiness merely from Project 1 clean scan.

### 2026-10-29 verified local MySQL schema shape + index inspection follow-on

- User pulled `4251c97`, `InspectLedgerSchema.php` syntax PASS and Git clean. Real configured MySQL returned all required columns present for `ledgers`, `customer_ledger`, `project_head_subheads`, `projects`, `journal_vouchers`: `minimum_shape_compatible=true`, but `safe_to_automatically_add_strict_source_fk=false` and `indexes_and_historical_collisions_verified=false`. This is **minimum shape** only, not unique/FK or historical integrity certification.
- Extended read-only schema inspector to list actual MySQL indexes and constituent columns (`694fca88`); await local execution. Do not advance P0.1 beyond **98% engineering estimate** until physical/index and historical collision acceptance. This new inspection makes no application writes or schema changes.

### 2026-10-29 P0.1 final compatibility inspection implementation

- New read-only `finance:inspect-ledger-schema` command (`f02c53c4`) reports actual finance schema required-table/column compatibility and explicitly refuses to infer source FK safety from an overloaded polymorphic `type_id`; records valid CR/PPR-linked CP receipt clearance relationship. Does not mutate financial rows, create schema constraints or guess ledger ownership. Run on authorized local disposable BlueMarketing schema or via approved read-only database access; never run PHPUnit against financial clone.
- Repository audit already documents active JV, Stock, Labour, CR/CP, Booking, BR, Mobile and blocked destructive flows. Physical source FKs on real tables remain **not approved** pending per-family design and historical evidence; no blanket `type_id` FK can be safely implemented. New read-only command awaits user's local run.
- Existing user verification: MySQL synthetic concurrency successfully posted CR-1/CR-2 with unique numbers and matching source/ledger references; MySQL synthetic FK/unique rehearsal 5/5 PASS; 135 feature tests / 830 assertions PASS. P0.1 remains **98% estimated**, not 100% production certified. To close, analyze real-schema output and historical conflicts, finish actual raw-writer coverage and approve tested source-specific constraints. No staging/production writes or migrations.

### 2026-10-29 verified two-process MySQL synthetic financial posting

- User shared read-only MySQL 8.4.3 evidence from disposable `bluemarketing_finance_lock_test`: source IDs 1,2 (CR numbers 1,2), ledger IDs 1,2 with same voucher numbers, matching source IDs and exact `SYNTHETIC-CR-1/2` references; duplicate project/type/number query returned zero rows. Independent process logs: number 1 in 4.109s, number 2 in 7.941s, both `synthetic_only=true`; Git clean. This verifies **ProjectVoucherAllocator + synthetic customer source and ledger writes under two concurrent transactions**.
- MySQL unique/FK rehearsal also previously PASS 5/5; application tables not modified by that rehearsal. P0.1 engineering estimate **96% -> 98%**. Not yet complete: live-schema compatibility and all real writer paths (including raw SQL), historical provenance, source FKs and migration acceptance. Never label 100% or production certified based solely on synthetic fixtures.
- User has finished the synthetic concurrency manual test; no need to rerun it or reset its tables.

### 2026-10-29 concurrent financial allocator/posting probe implementation

- New `finance:probe-concurrent-voucher` command on explicit isolated MySQL database (`9f3476a5`): two separate processes can call the **real ProjectVoucherAllocator::next(project, CR)** inside separate transactions, sleep under the project lock, and insert synthetic customer_ledger/ledger voucher rows. `--results` checks that two sources and both legs hold unique [1,2] numbers (`fa08cfec`). This is beyond earlier project-lock-only probe. It requires a deliberately prepared synthetic minimal schema and is not yet verified locally.
- MySQL constraint rehearsal already verified by user: all five rejection cases true and exit 0 on disposable MySQL 8.4.3; original regression 135 tests/830 assertions PASS. Actual schema FKs and legacy collisions remain unverified; synthetic unique/FK proof does not authorize altering historical tables.
- P0.1 **96% remains the last locally verified engineering estimate** until actual concurrent posting results arrive. No source/ledger DDL on production/staging/client clone.

### 2026-10-29 verified isolated MySQL uniqueness/FK rehearsal

- User pulled `7f78ce0`, PHP lint passed for `RehearseLedgerConstraints.php`, `git status --short` empty.
- On MySQL 8.4.3 isolated `bluemarketing_finance_lock_test`, `finance:rehearse-ledger-constraints` returned exit 0, `all_passed=true`, all five true: duplicate number/reference, orphan source, foreign account, foreign source rejection; `application_tables_modified=false`. Rehearsal uses disposable synthetic tables, **not actual production schema**.
- P0.1 engineering coverage estimate **95% -> 96%** for real MySQL composite-index/FK proof; **not 100%**. Remaining: finance-schema compatibility including legitimate CP receipt clearance and overloaded JV sources, enforceability against raw SQL, MySQL simultaneous real writer posting with unique-number checks, and remaining write-path acceptance. No live database migration or history repair authorized.

### 2026-10-29 P0.1 MySQL lock verified + real constraint DDL rehearsal implementation

- User ran two **actual independent MySQL 8.4.3 processes** on disposable `bluemarketing_finance_lock_test` project ID 1: elapsed **4.024s** and **7.726s**, combined **8.76s** and explicit JSON check `PASS: MySQL project lock serialization verified`. This proves tested cooperating project-row locks serialize, not concurrent end-to-end financial posting.
- User independently verified **135 feature tests / 830 assertions PASS** and two new constraint-preflight tests **2 / 10 PASS**, syntax clean. The earlier PowerShell summary "FAIL" was a blank `Start-Process.ExitCode` reporting issue; JSON timing verification confirmed lock serialization.
- Added `finance:rehearse-ledger-constraints` (`46a2dcd`): only runs under explicit test-DB safeguards, uses **three disposable synthetic tables** for composite unique source number/reference, project-aware source/account FKs, and five deliberate negative inserts. It returns per-check JSON and drops rehearsal tables. **No DDL on real application tables**. Command awaits user's local MySQL run before evidence can be credited.
- P0.1 implementation estimate **93% -> 95%** on completed real MySQL row-lock verification; remaining 5 points include physical production-schema compatibility/constraint integration, actual simultaneous financial posting and all writer-path acceptance. 95% is not finance release approval. No automatic real-data FK or uniqueness migration until read-only historical preflight and finance-approved design.

# BlueMarketing — Combined Development & Financial Integrity Tracker

Updated: 2026-10-28 (Asia/Karachi)
Branch: `upgrade/laravel-framework`

This tracker combines the earlier project-progress dashboard and the newer database `ledgers` integrity investigation. Progress figures are **planning estimates**, not verified release readiness. Never increment a number merely because a code file was inspected.


### 2026-10-29 Labour JV lock / Booking Extra Charge safety matrix — pending local verification

- Labour JV number allocation moved inside the existing transaction, after a selected-project `projects` row lock. Previously `getVocuherNumber('JV')` ran before the transaction and before attendance row locks, permitting an unprotected first-time allocation window. Header and both ledger legs still use the same persisted number/date; attendance state stays in the transaction. The row lock serializes **cooperating Labour writers in the same project only**, not stock/other JV allocators, and cannot substitute for a DB unique constraint or MySQL concurrency test.
- Expanded the disabled Booking Extra Charge regression across cash/online/cheque payments and checks five financial tables, including JV header/detail and booking voucher. The ambiguous legacy writer stays blocked; no guessed accounting mapping, type identifier, source table or ledger entries were introduced.
- Commits: `7363b7ae` (Labour project lock), `01539234` (Extra Charge regression).
- **Pending user local PHP lint and focused tests**. Do not claim verified tests or increment percentages from this batch; P0.1 **93%**, P0.2 **65% estimated in current board**, P0.3 **15%**, P0.4 **15%**, P0.5 **10%**. The earlier handoff's P0.2 55% was a confirmed checkpoint, whereas 65% includes subsequent unverified Labour work.
- Remaining: typed auditable Extra Charge source/account mapping; successful Labour JV header/detail/ledger/attendance integration test; global JV allocator coordination and MySQL concurrent posting; source-aware FK/uniqueness rehearsal. No staging/production financial write, migration or repair.


### 2026-10-29 locally verified safety checkpoint and Labour period validation

- User verified previous batch: Booking Extra Charge disabled financial writer **3 tests / 27 assertions PASS** across cash, online and cheque; Labour invalid-input regressions **13 tests / 52 assertions PASS**; both PHP lint checks passed, Git tree clean at `3c245557`. This does **not** yet verify a successful Labour JV or real MySQL concurrency.
- Labour JV writer now validates exact ISO calendar business period and rejects missing/relative/invalid or reversed start/end dates before voucher inserts; previously `Carbon::parse` normalized relative date values or could throw. New isolated SQLite regression has four invalid-period datasets. Commits `46b57db8`, `1fe157b9`; local execution pending.
- Extra Charge remains disabled; canonical source type, accounts, date and number mapping remain unproven. No new voucher posting enabled. P0.1 **93%**, P0.2 **65% implementation estimate (not newly verified)**, P0.3 **15%**, P0.4 **15%**, P0.5 **10%**; do not infer release readiness.
- Next: valid Labour source/attendance header-detail-ledger two-leg end-to-end regression; coordinate global JV/stock writers; MySQL concurrency rehearsal and typed Extra Charge design. No live DB writes or migrations.


### 2026-10-29 substantial Labour JV source-integrity implementation — awaiting focused local verification

- User locally confirmed previous Labour period guard **4 tests / 16 assertions PASS**, PHP lint clear, working tree clean at `01cca646`.
- Audited live `LabourController::createVoucher` plus `getSiteVoucherList`: before this batch JV posting only checked attendance count/site/project and posted flag. A request could submit absent attendance, outside-period records, or a forged total while still creating balanced header, details and ledger legs. The voucher listing derives payable amount from `SUM(labour_attendances.amount)` for present attendance in selected period; posting now enforces this source total under lock using integer minor units.
- Added strict source attendance date window and `status=present` checks and exact source-total equality; source conflict rolls back and returns 422 without creating financial rows. Locks, header, both detail lines, both ledger legs and attendance update remain within the same transaction.
- New isolated regression: **valid Labour JV end-to-end** checks voucher header total, debit/credit details, two ledger legs, header voucher number/date linkage, attendance status and repeat-submission rollback; four source-conflict datasets cover wrong amount, outside-period date, absent status and already-vouchered row. Code commits `f89dc584`, `5b25bed4`, `a9e61d51`, `b2f69089`.
- **Verification pending**: run only `test_labour_jv_` focused filter locally. If successful and no regressions, a 10-point P0.2 coverage milestone can be independently assessed and then recorded. **Do not pre-credit 10 points before local PASS.** Existing board remains P0.1 93%, P0.2 65%, P0.3 15%, P0.4 15%, P0.5 10%.
- Remaining: other JV/Stock writers do not share a single global voucher-number allocator; DB constraint and MySQL concurrent rehearsal pending. Booking Extra Charge remains disabled pending authoritative chart-of-accounts mapping and properly typed source model. No staging/production writes or migrations.

### 2026-10-29 verified Labour milestone + Booking PPR source input follow-on

- User verified Labour JV end-to-end plus four source conflicts **5 tests / 48 assertions PASS**, PHP lint clear and clean working tree at `ec855f97`. Verified code proves two balanced source-linked header/detail/ledger legs, voucher number/date equality, attendance status and replay rejection in isolated SQLite; NOT MySQL concurrency or live provenance.
- The coherent Labour integration milestone supports estimated **P0.2 65% → 75%**. Other tracks unchanged: P0.1 93%, P0.3 15%, P0.4 15%, P0.5 10%.
- Next Booking PPR posting writer reviewed: amount was compared through permissive `normalizeDecimalString`, where invalid characters could be discarded, and the source's business date had no exact ISO calendar-date check before new financial records. New pre-write guard rejects negative/zero/scientific/excess precision/invalid date/empty reference; seven focused regression datasets assert no source, ledger or BookingVoucher write. Commits `ad67b895`, `0487625a` and tracker commit. **PPR tests pending local verification**, no percentage claimed for this unverified patch.
- Extra Charge remains disabled, source typing and legitimate double-entry mapping outstanding. No production/staging writes, migration or historical repair.

### 2026-10-29 PPR regression correction and first-posting lock

- User ran PPR invalid-input regressions: six datasets PASS and the empty-reference dataset raised `ValidationException` from Laravel's required validation before entering controller try/catch. This is a test harness expectation mismatch, **not a financial write**. Updated test to assert the exception and no financial rows (`84e383e8`). Local rerun pending.
- Inspected PPR source creation and number allocation: existing source/ledger `lockForUpdate` cannot serialize competing first-time requests where no row exists. Added selected-project row lock inside PPR transaction before its source lookup and voucher allocation (`75b917bf`). This serializes cooperating PPR requests on one project row; it does not prove global numbering safety across unrelated writers or MySQL behavior.
- Added fail-closed missing-project regression checking no customer source, ledger or booking voucher rows (`3ed14dee`). New tests pending. No percentage change: P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **15%**, P0.5 **10%** until independently verified; no DB write, migration or historical repair.

### 2026-10-29 PPR BookingVoucher replay source/ledger evidence guards

- Previous PPR checkpoint **8 focused tests / 54 assertions PASS**, controller and test PHP lint clear, clean Git tree at `9386b6a2`.
- Audit found `syncBookingVoucherRecord` uses `updateOrCreate`, and a PPR replay could silently overwrite an existing BookingVoucher whose project/customer/plot, series, active status, slip reference, receipt date, amount, linked ledger ID or persisted voucher number had drifted. These are source ownership and financial identity facts, not repairable display metadata.
- New PPR replay preflight refuses such mismatches before the existing sync path: ten focused mutation datasets check the prior BookingVoucher and ledger are unchanged, with one source/ledger remaining. Commits `eedc045b` and `c08fbb53`. **New tests pending local run.** This also deliberately refuses legacy inconsistent records pending independent reconciliation.
- No progress inflation: P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **15%**, P0.5 **10%**. The implementation improves PPR source integrity, but cross-family source typing, Extra Charge redesign, database uniqueness and MySQL concurrency remain unresolved. No production/staging write, migration or historical repair.

### 2026-10-29 PPR replay verified + booking/payment instrument ownership expansion

- User verified prior PPR replay checks **10 tests / 80 assertions PASS**, PHP lint clear and Git tree clean at `f298a5e5`.
- Follow-on audit confirmed existing BookingVoucher replay checks did not include `booking_id`, payment method, bank ID, instrument number or passing date. A tampered PPR voucher with these fields could be silently overwritten by the existing sync despite financial source identity drift.
- Added explicit equality checks for the selected booking owner and payment method/instrument fields, rejecting mismatch without write. Extended previous drift data provider with two datasets and added three bank-payment drift datasets, each asserting original voucher and ledger remain unchanged. Commits `69ae9289` and `81fd8285`; **new tests pending local verification**.
- P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **15%**, P0.5 **10%** pending verified broader milestone. Extra Charge writer remains disabled; no staging/production mutations or migrations.

### 2026-10-29 booking deletion posted-history protection — new milestone pending local test

- User confirmed prior PPR replay expansion **15 tests / 120 assertions PASS**, both PHP lints clear, Git tree clean at `c82f5595`.
- Broader Booking writer inspection identified a **separate destructive loophole** in `BookingController::destroy_booking`: it soft-deleted any booking and marked the plot unsold without checking existing `customer_ledger`, `booking_vouchers` or `ledgers`. Posted financial source ownership could disappear from booking views while financial postings survived. This is a P0.4 structural safety issue, not another narrow PPR field guard.
- Replaced with project-scoped transaction and booking/plot locks. For any matching financial history, including inactive/stranded customer sources or orphan ledger legs, return HTTP 409 without deleting booking or mutating plot. Foreign-project booking lookup fails closed. Unposted deletion still uses the existing soft-delete/history path.
- Focused regressions cover posted PPR, foreign-project lookup, inactive stranded customer source and orphan ledger; commits `7579048d`, `c269bc40`, `397c8fec`. These tests remain **pending local execution**, do not claim PASS. This path still needs schema/real-data reconciliation and normal-unposted deletion coverage before treating it as complete.
- P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **15%** (candidate increase subject to focused verification), P0.5 **10%**. No live DB writes, migration or historical repairs.

### 2026-10-29 verified booking deletion + cancellation no-payment reversal protection

- User verified booking deletion guard: **4 tests / 17 assertions PASS**, both PHP lint checks clear and Git tree clean at `fa3c7182`. It protects posted/stranded booking financial history, including an orphan ledger and a foreign-project delete.
- Broader review exposed another material P0.4 issue: `BookingController::cancel` accepted any project booking ID and its `payment_not_received` branch *continued into the accounting reversal writer* despite a comment promising no postings. A zero-payment cancellation could generate CR customer source and sale reversal rows; a booking with received payments could be canceled without reviewing those sources.
- Cancellation now scopes booking to selected project, preserves already-canceled idempotency, fails closed with HTTP 409 when any customer source/booking voucher/linked ledger history exists on no-payment action, and returns after updating booking/plot for genuinely unposted no-payment cancellations—before reversal/account allocation.
- Focused isolated regressions cover paid booking conflict (no state/finance mutations), foreign-project rejection, and success/idempotency for unposted no-payment cancellation (no customer ledger or general ledger rows). Code commits `5f2e7fcd`, `99be0a16`, test commits `7906802b`, `07a17d84`, `046633da`. **New focused tests pending local execution**; if verified, candidate +10 points P0.4 milestone to record next.
- Current board until verified: P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **15%**, P0.5 **10%**. Full cancellation refund/reversal accounting, MySQL concurrency and legacy cleanup remain unresolved. No production/staging DB writes/migrations.

### 2026-10-29 verified deletion milestone and cancellation fixture correction

- User confirmed **4/4 booking deletion tests, 17 assertions PASS** on `fa3c7182`, with PHP lint and working tree clean. The posted-history deletion protection is a verified structural P0.4 milestone, therefore advance **P0.4 15% → 25% (implementation estimate)** independently from the later cancellation test outcome. Remaining reversal flows are NOT verified.
- User cancellation run at `793e458a` failed on the first success fixture with HTTP 500; the two following cases were not executed because `--stop-on-failure`. Inspected isolated SQLite fixture against cancellation's plot-history write: test `plots` schema lacked the `plot_history` column used by the controller. Added it (`bcc2ce87`) and added a separate **successful unposted booking deletion** regression that confirms soft-delete, plot history, and zero finance rows (`5da3b0d0`). The error body was not provided, so this is the most concrete fixture mismatch found, **pending local rerun**, not yet a confirmed complete fix.
- Cancellation business logic remains guarded: no-payment booking with financial source must return conflict; real unpaid cancellation must not generate reversal postings; foreign project must be rejected. Run focused cancellation and booking deletion tests next. P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **25%**, P0.5 **10%** (planning coverage, not production signoff).
- No production/staging DB writes, destructive repairs or migrations.

### 2026-10-29 verified no-payment cancellation + paid reversal orphan guard

- User verified cancellation and deletion **8 tests / 43 assertions PASS**, both PHP lint checks clear and Git tree clean at `c4105c12`. Three cancellation tests now demonstrate zero reversal posting for genuine unpaid cancellation, reject paid-history no-payment cancellation, and reject cross-project booking; existing four posted/foreign/stranded deletion guards and unposted deletion are also verified.
- This independently verified no-payment cancellation/reversal boundary supports estimated **P0.4 25% → 35%**, in addition to earlier independently verified deletion milestone. This is NOT proof that paid cancellation refund/reversal accounting is correct.
- Audited paid cancellation for another serious partial-state path: if a `CANCEL#<booking-id>` ledger posting already existed while booking was not marked canceled, code previously updated booking and plot before returning early on the duplicate-marker check. New preflight detects this condition before state mutations and returns 409; regression asserts booking, plot, ledger and customer source unchanged. Commits `d3de5312`, `f6b14c91`. New test pending local verification.
- Board: P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%**. Full paid reversal authorization/balance, global numbering and migration-free reconciliation are remaining priorities. No production or staging DB writes.

### 2026-10-29 verified paid cancellation replay guard and adjustment validation

- User verified **1 test / 6 assertions PASS** for paid cancellation prior-ledger-marker guard at `2aa40f36`; PHP lints clean and working tree clean. Existing P0.4 implementation coverage estimate **35%**, not full reversal certification.
- Inspected cancellation's financial adjustment: `pvMoneyToDecimal2` strips nonnumeric characters and truncates excess decimal precision. This could silently change client-entered fine/profit amounts; a contradictory `cancel_effect=none` could also carry an ignored nonzero adjustment. Added strict pre-write input validation for these cases, without enabling any new financial posting. Five isolated financial-no-write regression datasets: hidden fine, negative, scientific notation, excess precision and nonnumeric text (`32d8a05f`, `f5e9c5ee`). **New tests not yet locally run.**
- Current task-by-task remaining: P0.1 93% — MySQL concurrency, global number uniqueness, production invariant rehearsal; P0.2 75% — typed Booking Extra Charge (disabled), global writer coordination, remaining source joins; P0.3 15% — FK/source discriminator design, legacy key cleanup, database constraints; P0.4 35% — paid cancellation source-owned balanced refund/reversal, complete idempotent recovery and all other deletion writers; P0.5 10% — historical evidence review, staging rehearsal and approval-only repair. No database migration or production/staging writes.
- Progress unchanged until verified milestone completion; no artificial +10% credit for validation patch.

### 2026-10-29 P0.2 three-workstream audit: verified boundaries and remaining blockers

- User verified cancellation malformed-adjustment and paid preexisting reversal guards: **6 tests, 26 assertions PASS**, both PHP lints clear, working tree clean at `f4467c93`. This is P0.4 evidence, not a P0.2 deliverable.
- **Extra Charge source mapping audited:** `BookingController::extraCharge` still reads legacy `customer_ledger.transaction_type='null'`, and `deposit` disables the ambiguous `extra_charge` writer. No unambiguous charge type → project account, source identity, voucher scheme, and cash/bank leg can be inferred from these fields. Posting remains intentionally disabled. This workstream is **NOT completed**; enabling it without approved account/source mapping could corrupt financial history.
- **Global voucher coordination:** Labour JV and Booking PPR use project transaction locks. Added the same selected-project row lock inside manual `LedgerController::store` CR/CP transaction (`656a772e`). Also reject mismatched or zero CR/CP voucher labels before writing (`fc5c240c`), and 3 focused isolated financial-no-write datasets (`0fa44995`). These are pending local verification. Project locking coordinates these cooperating writers but does NOT solve cross-project global JV sequences or uncoordinated sources, nor does it replace a DB uniqueness constraint.
- **Remaining financial writers:** inspected manual CR/CP store and Booking receipt writers; manual source linkage, bank clearance variants, and other JV/stock allocation surfaces need full contract tests and MySQL concurrency rehearsal before completion. Do not classify audit alone as implementation.
- P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%** unchanged pending verified completion. P0.2 cannot truthfully be marked 100% in this turn; next stage requires approved typed Extra Charge accounting map, coordinated allocator design and all-writers proof. No live financial DB writes, destructive migrations or repairs.

### 2026-10-29 manual CR/CP canonical amount follow-up and next task breakdown

- User verified previous **3 tests / 15 assertions PASS** for mismatch/zero manual voucher identities; both PHP lints clean and Git tree clean at `6e61ce39`.
- Further CR/CP writer review identified `is_numeric` and float-conversion accepting scientific notation and excess precision in voucher money before clearance and general-ledger operations. Added strict positive canonical 0–2 decimal and bounded magnitude validation in `LedgerController::store` (`b084820e`), and five isolated no-financial-write regression datasets (`f56dbb75`). Local verification pending.
- **Next priority P0.2 (75%)**: secure authoritative Extra Charge source-to-account mapping and implement typed balanced posting, shared global voucher allocation for all JV/stock/CR/CP writers, bank-clearance contract tests and per-writer inventory. **P0.1 (93%)**: MySQL concurrency/unique identity and independent invariant checks. **P0.3 (15%)**: typed FK/discriminator, legacy key audit and constraints. **P0.4 (35%)**: paid refund/reversal balanced source-aware posting and replay tests. **P0.5 (10%)**: historical mismatch evidence classification, staging rehearsal, approval-gated repair.
- Progress not increased by an unverified guard; no live financial DB writes/migrations.

### 2026-10-29 manual CR/CP replay source ownership work

- User verified prior canonical CR/CP money and voucher-identity regression **8 tests / 35 assertions PASS**, PHP lint checks clear, working tree clean at `44b0f83b`.
- Full `LedgerController::store` posting inspected. Independent CR/CP source creation occurred on every replay even though a project row lock serialized transactions. Added pre-write source identity collision detection using selected project, voucher type, reference, customer and plot; rejects exact retry before `CustomerLedger::create`, preventing duplicate financial source/ledger pairs for the same business identity. Commits `0cc76fcd` and `9da4de41` with CR and CP replay/source immutability regression cases; **pending focused local tests**. Existing blank-reference business workflows are not yet covered and must not be assumed safe. Legacy duplicate records require separate audit, not implicit repair.
- **Open P0.2 milestones:** approved typed Booking Extra Charge account/source mapping and full balanced posting remains disabled; global JV/Stock/CR/CP allocator coordination and real MySQL parallel submission remain unproven; remaining bank clearance/other writers source contracts must be audited. P0.1 **93%**, P0.2 **75%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%** unchanged (no invented completion). No staging/production DB writes.

### 2026-10-29 P0.2 Extra Charge typed cash posting — approval-gated implementation

- User confirmed prior manual CR/CP replay protection: **2 tests / 12 assertions PASS** at `88a60aa5`, both PHP lints clear and Git tree clean.
- Inspected Extra Charge form and `ChargeType` model: charge types only have `name`; **no approved charge-type-to-financial-account mapping exists in repository**. Do not infer accounts from legacy `transaction_type='null'` records.
- Added default-disabled `config/finance.php` map, indexed by project ID and charge-type ID, requiring `approved=true`, distinct debit/credit ProjectHeadSubhead IDs belonging to the selected project. Unconfigured or invalid mapping refuses posting. Existing legacy `null` sources untouched.
- Implemented new **typed EC cash-only** transaction: strict money/date/reference and booking ownership; project lock; one EC CustomerLedger source, two equal-and-opposite EC ledger legs on explicitly approved project accounts; duplicate EC reference refuses replay. Bank/cheque EC deliberately not enabled pending an approved clearance contract. Commits `a220300e`, `eaaa522a`.
- Isolated tests added for disabled/unmapped no-write, configured balanced cash posting and replay refusal (`6eb82dd8`). **Local verification pending**, no operational mapping approved or configured. No production/staging financial writes or migrations.
- P0.1 **93%**, P0.2 **75%** (candidate progress increase only after successful local suite and business-owner mapping approval), P0.3 **15%**, P0.4 **35%**, P0.5 **10%**. Remaining P0.2: approval of real account mapping, bank/cheque EC, cross-writer JV/global voucher allocation, MySQL concurrency, source/ledger reporting integration.

### 2026-10-29 verified EC cash posting milestone + orphan source number safety

- User confirmed focused Extra Charge tests **2 PASS / 15 assertions**, PHP lint of controller/config/tests clean and Git tree clean at `3c31548c`: disabled/unmapped charge writes no financial rows; explicitly configured, test-only approved EC cash mapping creates a source and two balanced ledger legs and refuses duplicate replay.
- **P0.2 planning estimate advanced 75% → 85%**, crediting verified typed/balanced cash Extra Charge implementation. This is not deployment approval: repository `config/finance.php` deliberately has an empty mapping; project finance owner must explicitly approve both account sides. Online/cheque EC still unsupported. Real chart of accounts and MySQL concurrency not tested.
- Audited EC allocator: it previously considered only existing EC `ledgers` voucher numbers, allowing a number already held by an orphaned EC `customer_ledger` source to be recycled. Allocation now considers both EC source `type_id` and ledger `voucher_number` under the project lock (`85405ad9`), with regression covering orphan source and balanced next posting (`16c061a2`). **New test awaiting local verification**.
- Next deliverables: get authorized project/charge-type debit and credit IDs with accounting signoff; source-aware bank/cheque EC posting and clearance; shared coordinator for legacy JV/Stock/manual writers; MySQL double-submit tests. P0.1 **93%**, P0.2 **85%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%**.
- No live database writes, migrations, or historical repair.

### 2026-10-29 EC source reconciliation audit — delivery vs blocked scope

- User independently verified EC tests **3 PASS, 22 assertions** at `566952df` with clean PHP lints and Git tree. This confirms test-only mapped EC cash ledger posting and orphan-source number skipping, not real-account approval or online/cheque bank clearance.
- Implemented **read-only** `php artisan finance:audit-ec-sources PROJECT_ID` that checks all project EC customer_ledger sources against EC ledger legs: missing/extra legs, unbalanced cents, mismatched source reference/voucher number or account project, orphan/foreign source ledger, and duplicate source voucher numbers. Emits JSON + nonzero code on anomalies; no automatic repair. Commit `3532f01b`. Isolated tests for empty project and orphan source detect/no mutation commit `48560802`. **Pending user's local lint and test output**.
- User explicitly requested in one turn (1) production EC mapping and online/cheque clearance, (2) global JV/Stock/manual allocation and concurrency verification, (3) P0.3 source typing and safe constraints. **None of these three is fully complete.** There is no authorized real charge-type-account approval in code; inventing one is prohibited. No MySQL parallel run occurred, so claiming concurrency proven would be false. P0.3 database constraints cannot be responsibly installed until legacy key/null/duplicate audit plus rollback plan.
- Task estimates remain P0.1 **93%**, P0.2 **85%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%**. Audit command is a P0.3/P0.5 preparatory capability, not completion. Next needed: financial owner signs off project/charge debit-credit mappings and bank clearing, shared allocator deployed across all writers with actual MySQL tests, schema-level source links after clean evidence packet. No production/staging DB writes or migrations.

### 2026-10-29 substantial shared voucher allocation integration — Stock and EC

- User verified read-only EC audit regressions **2 PASS / 8 assertions** at `0fb4ec51`; clean lint/status. User requested major financial implementation, not tracker-only changes.
- Added real `App\\Services\\Finance\\ProjectVoucherAllocator` with mandatory transaction, durable project-row lock, per-project voucher-series scope and max of historical `customer_ledger.type_id` AND `ledgers.voucher_number` (including orphan/deactivated sources). Allocator now used by both Stock entry and Stock bill financial posting paths and Booking Extra Charge EC posting. Commits `2398fbb9`, `636d2950`, `aae0ac3b`; integrated regression `9e6f3869`. **New tests pending local verification**.
- This is concrete multi-writer integration, but **not** full global voucher coordination: Labour/general JV use distinct header semantics, legacy other writers remain, no real MySQL parallel test or DB uniqueness constraint. EC production bank/cheque, real finance-approved debit/credit mapping and P0.3 source typing remain blockers.
- Do not inflate completion; board P0.1 **93%**, P0.2 **85%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%** pending full major acceptance. No prod/staging financial writes or migrations.

### 2026-10-29 P02-1 through P02-6 coordinated writer implementation and hard blockers

- General JV headers and Labour JV headers now share `ProjectVoucherAllocator::nextJournal(project)`, reserving both journal_vouchers headers and surviving JV general-ledger / customer source numbers inside existing project-row transactions. Implemented commits `b801ebbb`, `307f279c`, `9719dcdf`.
- Manual CR/CP new voucher and pending-payment CP clearance now allocate from `ProjectVoucherAllocator::next(project,type)` instead of duplicating ledger-only loops; Stock Entry, Stock Bill and EC already use `next`. Implementation `dbf2d86f`; JV header/ledger reservation regression `8f4fba21`, **tests pending local verification**.
- **Not completed, do not report as done:** P02-2 PPR retains separate gap-reusing booking voucher sequence and other legacy financial writers are not fully enumerated; P02-3 actual EC debit/credit account IDs require authorization and remain unconfigured; P02-4 EC online/cheque has no proven source-linked bank-clearance accounting map and is intentionally unsupported; P02-5 comprehensive CR/CP/PPR/EC/Stock/JV reconciliation beyond existing targeted read-only audits not delivered; P02-6 real parallel MySQL posting/unique indexes not tested or implemented. Legacy DB reconciliation plus business-approved rollout necessary.
- No staging/prod database writes, approvals, migrations or destructive historical changes. Current **planning estimates** P0.1 93%, P0.2 85%, P0.3 15%, P0.4 35%, P0.5 10%; do not increase solely for unverified code.

### 2026-10-29 P02 cross-family source numbering and reconciliation delivery

- PPR actual receipt posting now uses the shared transaction-scoped `ProjectVoucherAllocator::nextBookingReceipt`; it reserves PPR source `type_id`, ledger `voucher_number` and historical `booking_vouchers.voucher_number`. Unlike previous gap-reuse logic, it does not recycle an old receipt identity. Commits `5288c1d6`, `c6a4bee9`, PPR orphan-source/header regression `7ff71a4e` (pending local verification).
- Added `finance:audit-project-vouchers PROJECT_ID` read-only cross-family linkage inventory for CR, CP, PPR, EC, JV, SV, BR: orphan/foreign source legs, source without ledger, mismatched project/type/reference, EC amount/leg balance and JV header without any corresponding ledger. Commits `aafa1c92`, `77ce60f2`; isolated no-write orphan CR audit test pending local run. Audit does not modify historical database data and cannot assert balanced debit-credit for voucher families without a proven leg contract.
- **Not certified:** P02-3 project/charge-specific finance-approved account IDs have not been provided; `config/finance.php` remains empty, so production EC postings remain gated. P02-4 bank/cheque EC settlement still disabled pending approved bank and clearance account semantics. P02-6 actual simultaneous MySQL tests and schema-level duplicate constraints still need an accessible MySQL test environment; SQLite tests cannot prove this. Other legacy writers and P02-5 deeper accounting semantics remain unresolved.
- Current planning board P0.1 **93%**, P0.2 **85%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%** until end-to-end acceptance. No staging/production financial writes, destructive migrations, or historical repair.

### 2026-10-29 Extra Charge receivable posting and auto-project account provisioning

- User approved a separate `Extra Charge` accounting head, with amounts allowed to remain outstanding and be paid later. Existing Stock path confirms `HeadAccounting`, `SubheadAccounting`, and `ProjectHeadSubhead::firstOrCreate` creation pattern; no historical EC/legacy-null sources and zero ChargeType rows were present in user's project 1 audit. Account names are user-authorized proposed structure, not evidence of historical mapping.
- Added `App\\Services\\Finance\\ExtraChargeAccounts` (`0d878d1c`): resolves/creates the `Extra Charge` head and `Extra Charge Receivable`/ `Extra Charge Income` subheads inside project-locked database transaction. Receivable mapping is customer/plot-specific; income mapping is project-wide. Reuses existing account rows on subsequent charges.
- Booking Extra Charge (`4ed279fd`) now creates a typed `EC` **unpaid receivable**, debit on receivable account and credit on income account, with no fake cash/bank receipt (`payment_type=null`). Retains replay refusal, positive amount/date checks, and atomic posting. Existing extra charge view (`1800460d`, `ec25fa32`) reflects outstanding payment status and server-assigned voucher number. Typed EC rows are now included in booking Extra Charges list (`7f3d90e6`). Form uses zero payment marker, accepted server-side (`ee642e0f`).
- Updated feature tests (`d03e73ac`): automatic first-time creation, account reuse, two balanced legs and no paid status, and unsupported bank mode no-write. **Pending local lint and feature tests**; do not claim verified milestone or raise P0.2 85% yet.
- **Still pending:** ChargeType creation/input when table empty; dedicated later-payment/partial-payment settlement source and UI, bank/cheque clearance/rejection, confirmed financial reporting balances, real MySQL concurrency and approved account classification validation. No database migration or live writes.

### 2026-10-29 ledger data-loss risk status checkpoint (documentation, not a code fix)

- Reviewed original eight-module Ledger Data Loss Risk Summary and user-confirmed focused tests through `59a3833d` (latest Extra Charge **4 tests / 33 assertions PASS**, clean PHP lints). The eight named risk areas are JV, Labour JV, Stock Entry/Bill, CR/CP, Booking/PPR, Extra Charge, Bank/Cheque Clearance and Paid Booking Cancellation/Refund.
- Risk-area **planning estimates, not production-safety metrics**: JV 80%, Labour JV 80%, Stock 70%, CR/CP 80%, Booking/PPR 80%, Extra Charge 60%, Bank/Cheque Clearance 60%, Paid Cancellation/Refund 35%. These estimates distinguish implemented mitigations from unresolved full-lifecycle exposure; do not treat any module as fully certified.
- Existing P0 workstream estimates retained: P0.1 **93%**, P0.2 **88%**, P0.3 **15%**, P0.4 **35%**, P0.5 **10%**. No verified new functional changes in this documentation-only checkpoint; percentages should not be incremented solely for audit/report work.
- Next turn order: (1) implement Extra Charge source-linked full/partial later-payment settlement and pending bank/cheque lifecycle; (2) bank-clearance duplicate/status safeguards; (3) remaining ledger writer edit/delete/source inventory and unified contracts; (4) isolated MySQL parallel posting tests; (5) paid booking reversal and rollback; (6) historical reconciliation with approval-gated recovery. No direct production/staging repair.
- Existing account auto-provisioning handles basic Extra Charge income and receivable: actual later-payment cash/bank account policy remains an explicit approval boundary. MySQL parallel execution requires an isolated test DB and user-run commands; successful SQLite feature tests do not establish parallel MySQL safety.
- Downloadable two-page status document is available in this conversation as `BlueMarketing_Ledger_Risk_Progress_Updated_2026-10-29.pdf`; no changes to financial data.

### 2026-10-29 substantial P0.2 implementation: deferred EC cash settlement

- User verified preceding Extra Charge auto-account changes **4 tests / 33 assertions PASS**, all targeted PHP syntax checks clear, Git status clean after local exclusion of `ec-account-evidence.json`. Do not conflate previous verified tests with this new settlement change.
- **Implementation:** `BookingController::deposit(action=extra_charge_payment)` now posts a separate immutable typed `ECP` customer source plus two offsetting general-ledger legs, links to the original EC charge by explicit `EC_PAYMENT#chargeId#slipReference`, supports full and partial cash settlement, serializes under the selected project's DB row, and refuses cross-project/customer/plot, duplicate slip, missing approved cash account, overpayment and damaged prior payment legs. Original EC charge/ledger are not mutated. Commits `441b5edb`, `bfd69afd`.
- **Accounting safety:** `config/finance.php` has `extra_charge_cash_accounts[PROJECT_ID] = EXISTING_PROJECT_HEAD_SUBHEAD_ID`, **empty by default**. Only project-approved actual cash account can be used; no guessed cash/bank head. Commit `63249f67`. Until it is configured, cash settlement is deliberately blocked; charge issuance still works.
- **UI:** Extra Charge list shows typed EC outstanding after active ECP payments and a permission-gated inline partial/full cash receipt form only when configured; legacy `null` sources remain visibly distinct and cannot be silently settled. `44d26383`, `3918f0f2`.
- **Reconciliation:** project voucher source read-only audit now includes ECP in source/ledger identity and two-leg balancing checks. `4c9b2845`. New regression coverage for partial + final payment, duplicate, overpayment and unapproved cash account (`c389e60f`). **New code not yet tested on user machine**. Full bank/cheque pending-clearance workflow, reversal/refund, approved cash account mapping, production reporting and MySQL race tests remain open.
- **User manual blocker:** finance owner must identify and approve one `project_head_subheads.id` for cash receipts *for each applicable project*. Do not pick an account by an approximate name or automatically create an actual cash asset account. Add the ID to `config/finance.php` once approved, then test in isolated DB. No production/staging data edited.
- P0.2's previous verified planning estimate **88% retained until new settlement passes local acceptance**; 10-point increase cannot truthfully be asserted yet. P0.1 93%, P0.3 15%, P0.4 35%, P0.5 10%. **Next:** verify settlement, enable approved cash account, implement pending online/cheque and rejection/clearance, MySQL concurrency, paid reversal.

### 2026-10-29 local settlement test FAILURE — investigation open

- User pulled `8b0e055e`; four PHP lint checks passed; first deferred-payment test failed on second/final cash installment (400 then 850 against charge 1250) after duplicate-replay and 851 overpayment rejection. Local result **1 passed, 1 failed, 4 pending (15 assertions)** with `--stop-on-failure`. The failing redirect returned generic `msg`, so the original exception **is not yet known**; do not label this fixed or increase P0.2 progress.
- Commit `84848deb` allows the controller to return exception class/message **only under Laravel testing environment**, preserving generic production errors; rerun focused test to reveal exact code path and fix it in following implementation. This is diagnosis, not settlement fix.
- Untracked local `.env_old` appeared. It has not been touched/committed. It may contain secrets; never add it to Git.
- P0.2 remains **88% provisional**, cash partial/full settlement **not verified** until reproducer passes.

### 2026-10-29 follow-up: ECP second-installment failure triage and regression isolation

- User's focused test at `bcfb6ed0` reports `DomainException: EC_PAYMENT_EXCEEDS_OUTSTANDING` **in the session error bag** on line 181 after deliberately testing Rs 851 overpayment rejection against Rs 1250 with Rs 400 already paid. The error is consistent with the *expected preceding rejection*; the test made several redirect requests in one method and did not clear Laravel's flashed `errors` session state before asserting final success.
- Commit `a1c34de2` isolates prior flashed errors in the test before issuing the final Rs 850 payment. This is an **unverified test isolation fix**, not proof that the final payment saved. The subsequent source-count and balance assertions must pass on user's local focused rerun to confirm actual posting. No live data or existing financial rows altered.
- P0.2 remains 88% until new verification. **Next:** run focused test, then full `test_extra_charge_` suite; if balances fail inspect actual source records before changing accounting algorithm.

### 2026-10-29 verified ECP settlement acceptance checkpoint

- User pulled `52e712d3` and ran `php artisan test --filter="test_extra_charge_" --stop-on-failure`: **6 PASS / 53 assertions / 1.59s**, including deferred partial then full cash posting, duplicate and overpayment refusal, missing cash mapping refusal, automatic EC accounts, typed number reservation, and original EC balanced source posting.
- The earlier apparent second-payment failure was stale flashed validation errors after deliberately rejected overpayment, not proven ledger arithmetic corruption; test now clears prior session errors before final-payment assertion. All six cases are locally verified in their existing SQLite test harness. No MySQL race or production finance acceptance is implied.
- Cash settlement feature implementation is now **verified in focused tests**. Still blocked in deployment unless `finance.extra_charge_cash_accounts[PROJECT_ID]` is set to an independently approved real cash project account ID; no guessed ID. Online/cheque pending-clearance, reversals, audit of legacy writers, and MySQL concurrency remain open.
- P0.2 engineering progress estimate advances from **88% to 90%** on verified settlement completion. This is an internal planning estimate, not a release safety score. Other baselines unchanged: P0.1 93%, P0.3 15%, P0.4 35%, P0.5 10%. Do not claim requested 10-point boost without corresponding work and verification.
- User local `.env_old` remains untracked; do not commit or expose credentials.
- Next implementation priority: bank/cheque ECP pending source and idempotent clearance, with concrete bank account approval and reconciliation tests; no direct production changes.

### 2026-10-29 P0.2 implementation: online/cheque EC payment lifecycle (pending local validation)

- Added `app/Services/Finance/ExtraChargeBankSettlement.php` (`3f8eda0a`): creates source-linked typed ECP receipts for online (`payment_type=2`) and cheque (`payment_type=3`) **without ledger legs while pending**. Requires transaction/cheque details, project lock, no overbooking of cleared+pending receipts, no duplicate slip, and validates linked source ownership. Clearance action can approve or reject exactly once: on approved clearance, two balanced ECP ledger legs (approved project bank debit, EC receivable credit) atomically post and source changes to cleared; on rejection, no legs post and reserved outstanding is released.
- BookingController routes bank/cheque payments and clearance to service (`e8001087`); existing cash payment checks pending reservations and ignores rejected sources (`819064c6`). Approved bank asset account is configured by `finance.extra_charge_bank_accounts[PROJECT_ID]`, empty by default (`5e09d1af`). Bank account MUST be independently finance-approved; no inferred ID.
- Extra Charges page lists pending receipts with permission-gated clear/reject and provides online/cheque pending entry fields (`ee1685f4`, `c6b631d4`).
- New tests for online pending/no premature legs, overbooking, duplicate slip, clearance balanced ledger and replay rejection, cheque rejection/replacement (`2b24aafc`). **New tests are NOT YET locally executed**: ask user to pull and run `php artisan test --filter="test_extra_charge_" --stop-on-failure`; do not assert finished production acceptance until passing. Current verified baseline remains prior cash suite 6/53.
- Important controls still pending: isolated MySQL concurrency acceptance, finance-approved project bank account ID, bank ID lookups/real bank selector and production user permission check; no production financial data was modified.
- P0.2 **90% verified baseline retained** pending local acceptance; update on verified functional test results rather than arbitrary 10-point increase.

### 2026-10-29 substantive correction: Extra Charge settlement accounts AUTO-CREATED

- User reiterated that **Extra Charge is its own Head Account** (like Project Sale), and missing project accounts must be automatically created using existing ERP pattern rather than manually asking for IDs. Previous assistant repeated this question incorrectly. Project 1 read-only search had found only account 72 `Gondal Avenue Recovery / G A Cash (GA Commission)`, which must NOT be reused for EC settlement.
- Updated `ExtraChargeAccounts::settlementAccount(projectId, 'Cash'|'Bank')` (`5d7ee3e8`) to resolve/create `Extra Charge` head, `Extra Charge Cash` and `Extra Charge Bank` subheads and scoped `project_head_subheads` mapping inside a database transaction under a project lock, using the existing first-or-create pattern. Existing `Extra Charge Receivable` and `Extra Charge Income` remain untouched.
- `BookingController` now posts deferred cash ECP into project auto-created `Extra Charge Cash` (`32ce3026`); `ExtraChargeBankSettlement` posts cleared online/cheque ECP into project auto-created `Extra Charge Bank` (`191d7f0a`). Both remain balanced against customer/plot receivable and never pick unrelated accounts by name. Removed obsolete manual finance ID mapping configuration (`447fa046`); adjusted cash acceptance test and bank settlement assertions (`ac85359f`, `c6a88847`).
- Account creation does NOT create a real external bank account, validate external banking instruments or certify the chart-of-accounts asset classification. Treasury transfer and financial statement treatment require finance review before production. This change is **pending user's local tests**, not an accepted completed production feature. Last verified preceding baseline: 8 focused EC tests PASS / 77 assertions; do not claim these new tests already pass.
- P0.2 remains **90% verified baseline** until rerun, then reassess. Next implementation: MySQL concurrency, mixed cash/pending safeguards, reporting and bank external reconciliation. No live database records changed.

### 2026-10-29 Auto-account regression failure: joined ID collision corrected

- User pulled `660f1c6a` and ran focused Extra Charge tests: bank clearance regression failed on `tests/Feature/VoucherClearanceFlowTest.php:193` when asserting the linked ECP ledger uses the auto-created project bank account; PHP lint checks passed. This was not a reported exception in the settlement controller.
- Root cause of failed assertion: `SELECT *` over `project_head_subheads p`, `head_accountings h`, and `subhead_accountings s` returns repeated `id` columns; the resulting PHP row's `id` did not reliably equal `p.id`. The test compared the wrong ID despite bank auto-account existence and two ledger legs. Updated bank test to explicitly select `p.id AS account_id` (`e2a9c5b0`); updated matching cash auto-account test and removed obsolete manual cash/bank mapping fixtures (`b35d6f14`).
- This is a regression-test fix. Ledger account auto-provision and settlement posting code unchanged in these commits. **Awaiting user local rerun**; do not claim the entire suite passed after changes. Previous verified baseline: 8 PASS / 77 assertions before auto-account test additions. Keep P0.2 estimate 90% until verification.
- Next required action: `php artisan test --filter="test_extra_charge_" --stop-on-failure` on pulled branch. No live financial writes.

### 2026-10-29 confirmed Extra Charge auto-account regression acceptance

- User pulled `4745625e` and verified `php artisan test --filter="test_extra_charge_" --stop-on-failure`: **8 PASS, 80 assertions, 1.67s**. This validates dedicated EC project account auto-provisioning and bank/cheque pending-cleared/rejected lifecycle in isolated SQLite feature tests; the earlier joined-account-ID test regression is resolved.
- No bank/cash project account ID needs manually entering for this new EC workflow. Account IDs are allocated/reused under the Extra Charge head at posting time, with no use of project 1 unrelated account `72`.
- P0.2 planning estimate **90% → 92%** on verified acceptance; this is not production finance certification. P0.1 93%, P0.3 15%, P0.4 35%, P0.5 10% unchanged. Next major work is mixed pending/cash reservations, MySQL concurrency and bank reconciliation; no production financial edits.
- User's latest `git status --short` showed no output, so working tree clean.

### 2026-10-29 major mixed-payment accounting hardening (pending local acceptance)

- User confirmed prior EC suite **8 PASS / 80 assertions**, requested larger functional deliverables per turn and NO separate tracker-only turns. This implementation batch changes production code in Finance service, Booking controller, Extra Charge UI and regression tests together with this tracker.
- New shared read-only balance contract in `ExtraChargeBankSettlement::balances` (`4d3b19f0`) partitions each source-linked EC receipt into **paid**, **pending**, **outstanding**, **available** using integer cents. It checks source owner, active status, payment state, expected two balanced ledger legs for cleared/cash receipts, zero legs for pending/rejected, and refuses overbooked/corrupt evidence. Pending reservation now uses this contract.
- Cash payment path also uses the same authoritative available amount rather than its own divergent sum (`bdacc255`). Mixed pending bank + cash can no longer consume the same reserved balance; rejected cheque releases reservation, but old source remains linked. Outstanding UI derives actual settled paid amount separately from pending (`8a575ed7`); page adds Received/Pending columns and caps new payment forms by **available** amount rather than outstanding (`a2c9ac34`).
- New mixed partial cash + pending online + attempted overpayment + rejection + final cash settlement regression added (`e80b4cda`). New changes **not tested on user's Laragon yet**, do not claim acceptance or increase percentages merely because code merged. Tests are SQLite feature tests and do not establish MySQL parallel isolation.
- P0.2 retains **92% verified baseline** until acceptance. Request `php artisan test --filter="test_extra_charge_" --stop-on-failure` and lint in local Laragon. Next: reconciliation of real external bank account instruments, isolated MySQL race harness, audit and reversal flows; never infer production-safe ledger changes.

### 2026-10-29 verified mixed Extra Charge cash/online/cheque acceptance

- User pulled `52e709f8`, verified PHP lint for `ExtraChargeBankSettlement.php` and `BookingController.php`, then ran `php artisan test --filter="test_extra_charge_" --stop-on-failure`: **9 tests PASS, 97 assertions, 1.97 seconds**. `git status --short` was empty.
- Verified regression coverage includes mixed cash + pending/rejected online settlement, available/outstanding calculations, deferred partial/full cash, pending bank and cheque clearance/rejection, duplicate and overbooking rejection, typed voucher numbers and automatic dedicated EC project account creation. These are focused SQLite tests; MySQL concurrent lock behavior and production ledger reconciliation remain unverified.
- P0.2 engineering estimate moves **92% → 94%** for successfully verified combined reconciliation and posting contract. No artificial +10% jump. P0.1 93%, P0.3 15%, P0.4 35%, P0.5 10% unchanged. Prioritize substantial P0.3 source ownership and P0.4 controlled reversals next, alongside isolated MySQL concurrency tests.
- No production/staging financial data has been modified by this review.

### 2026-10-29 Batch 1 — EC/ECP strict source ownership and ledger anti-corruption (new code, pending local test)

- **Implementation shipped in this turn (not tracker-only):** `ExtraChargeBankSettlement::assertSourceLegs` now reads **all** ledger legs linked to an ECP source instead of only type=ECP and validates exact two legs, balanced cents, source reference, active state, `type='ECP'`, matching persisted `type_id` and `voucher_number`, and correct project ownership for each project_head_subheads ID (`1553f428`). Thus unrelated/forged zero-value or mis-series legs do not bypass a seemingly balanced ECP posting.
- `ExtraChargeBankSettlement::linked` checks strict source identity syntax `EC_PAYMENT#chargeId#slipRef` and **fails closed** for malformed/multiple-delimiter references instead of silently excluding them (`00fccf2d`, `208cf115`); `BookingController` refuses `#` delimiter injection in new payment slip references (`0910f489`). Pending/rejected source checks refuse any accidentally linked general-ledger row, not only `type='ECP'`.
- New isolated feature regressions cover delimiter injection and refusal to post a second settlement when an unrelated `CR` ledger leg is attached to the first ECP receipt (`c3699ccc`).
- **Verification gate:** user previously verified **9 tests PASS / 97 assertions** at `52e709f8`. Above code and tests are **not yet locally run**; do NOT claim new batch fully verified or artificially increase the percentage by 10 points. P0.1 93%, P0.2 94%, P0.3 15%, P0.4 35%, P0.5 10% retained pending acceptance. This batch makes concrete source-ownership improvements contributing to P0.3 but does not implement physical typed foreign keys.
- **Next independent 10-point-sized batch:** P0.3 typed-source reference migration plan + legacy row collision audit, test-DB-only constraints and writers; P0.4 source-linked reversal and locked deletion; MySQL real concurrency. Never run production migration prior to evidence/approval. User requested no separate tracking-only turns; update this file in every implementation batch.
- Run `php artisan test --filter="test_extra_charge_" --stop-on-failure` after pulling. No real financial data changed by this code review.

### 2026-10-29 Batch 2 — project-wide read-only ledger source audit hardening (pending new test acceptance)

- User verified previous EC anti-corruption batch: **11 PASS / 106 assertions**, lints successful, clean working tree at `35237a74`. This batch adds actual code plus tests before updating tracker; no tracker-only commit.
- `finance:audit-project-vouchers PROJECT_ID` now audits *all linked ledger rows*, even wrong voucher series, of relevant customer sources; checks EC/ECP ledger `type_id`, `voucher_number`, active state, source reference, and project ownership. Pending or rejected bank/cheque ECP legitimately has zero ledger rows, but any rogue row yields `pending_or_rejected_ecp_has_ledger`. Persisted ECP reference must match `EC_PAYMENT#CHARGE_ID#REF` and resolve to an active EC charge belonging to the same customer and plot. These checks do NOT rewrite or repair data. Commit `311e0e72`.
- Added SQLite feature regressions `test_project_audit_accepts_pending_ecp_without_ledger` and `test_project_audit_flags_rejected_ecp_with_foreign_series_ledger` (`81a57eb9`). Await user local execution; new tests are NOT yet verified and percentages do not increase merely for committed code. This advances P0.3 audit evidence, not physical typed FKs, so P0.3 baseline stays **15%** pending acceptance. P0.1 93%, P0.2 94%, P0.4 35%, P0.5 10%.
- Caveat: JV, Stock and other legacy families have heterogeneous source contracts; this command does not yet constitute full enforced typed ownership for every row and never issues production data repairs. Next work: typed identity migration with legacy duplicate inventory and isolated MySQL enforcement, plus controlled reversal pathways.
- User-run gate: `php artisan test --filter="test_project_audit_|test_extra_charge_" --stop-on-failure`, `php -l app/Console/Commands/AuditProjectVoucherSources.php`. Project audit on real database is read-only but returns nonzero on anomalies; do not auto-fix.

### 2026-10-29 integrated source ownership enforcement delivery (pending local acceptance)

- **Verified incoming baseline:** user pulled `ca5dc50c`, PHP lint passed, **13 focused tests PASS / 112 assertions** (SQLite), clean `git status`. This is acceptance of the preceding source-audit batch.
- **Implementation in this turn (not a tracker-only commit):** created `app/Services/Finance/FinancialSourceOwnership.php` (`f636c65d`) implementing CR/CP/PPR/EC/ECP typed source ↔ account project ownership; on EC/ECP additionally enforces exact reference, voucher series `type_id` and `voucher_number`. Includes duplicate source-number and source-reference rejection for new sources under project/type scope.
- Eloquent `Ledger::creating` now rejects new *explicitly linked* CR/CP/PPR/EC/ECP ledger legs when referenced customer source is inactive, missing, wrong type or belongs to another project/account (`15685207`). Eloquent `CustomerLedger::creating` checks uniqueness of nonempty typed source identities with positive project and number (`e21b1375`). **Important scope:** direct raw `DB::table()->insert` and historical/unlinked legacy write paths bypass these model events; no database UNIQUE/FK constraints yet; existing physical duplicates require review before schema constraints.
- Expanded read-only `finance:audit-project-vouchers` duplicate project/type voucher number and duplicate source reference evidence for CR/CP/PPR/EC/ECP (`cb7a2197`); earlier audit handles orphans, wrong series, cross-project accounts, pending/rejected receipts and ECP-to-EC owner mismatch. No data repair performed.
- Regression tests added for cross-project wrong-owner finance posting rejection, number/reference duplicate guard, historical duplicate source audit, plus existing suite (`86bc5beb`). Previously existing deliberate corrupt-ledger fixtures now use raw inserts to simulate legacy anomalies without being rejected by the new Eloquent write guards (`86bc5beb`). Duplicate historical source fixture bypasses creation hook to simulate existing corrupted data (`824babd3`).
- **Acceptance not yet run** for this new integration. Do not claim complete project-wide protection or physical typed FK enforcement. User should run `php -l app/Models/CustomerLedger.php`, `php -l app/Models/Ledger.php`, `php -l app/Services/Finance/FinancialSourceOwnership.php`, `php artisan test --filter="test_project_audit_|test_finance_source_|test_extra_charge_" --stop-on-failure`, plus full voucher tests. Baseline remains P0.1 93%, P0.2 94%, P0.3 18%, P0.4 35%, P0.5 10%; advancement to be assessed only after clean regression.
- **Remaining risk/gates:** direct DB writes, missing source-linked legacy family rows, genuine MySQL race verification, physical unique/index constraints, source ownership across every controller branch, P0.4 reversal lifecycle, and authentic bank reconciliation. No production ledger mutations performed by this code push.

### 2026-10-29 source ownership integration acceptance regressions and CR/CP numbering fix

- User ran focused and full `VoucherClearanceFlowTest` on `f4b8e3fc`: PHP lint passed; focused **14 passed, 1 failed**, full **49 passed, 2 failed, 81 unexecuted** with `--stop-on-failure`. First failure was malformed test fixture `CustomerDB::table('ledgers')->insert` in rejected ECP foreign-series audit; corrected source to `CustomerLedger::create` and deliberately foreign ledger to raw `DB::table('ledgers')->insert` to reproduce historical corrupt data without triggering new model write guard (`8695b5e2`).
- Second failure uncovered a *real manual CP/CR writer defect*: `LedgerController` created a `CustomerLedger` source before calling `ProjectVoucherAllocator::next`. The allocator sees the newly inserted source, causing first voucher to display `CP-2` while source was `type_id=1`. Moved allocator to BEFORE source creation and use the SAME number for `CustomerLedger.type_id`, `Ledger.type_id` and `Ledger.voucher_number` (`fa3832c6`). Project lock and transaction held by existing store flow. No live ledger rows edited by this patch.
- New fixes **await user Laragon verification**. Re-run `php artisan test --filter=VoucherClearanceFlowTest --stop-on-failure`; only after full suite passes can this P0.3 batch be accepted. Prior recorded estimates unchanged pending verification; no fabricated 10-point progress claim. Historical source anomalies remain read-only.

### 2026-10-29 verification fixes: CP cross-series clearance and remaining fixture typo

- User pulled `4532ee8b` and ran full `VoucherClearanceFlowTest --stop-on-failure`: **51 passed, 2 failed, 79 pending, 326 assertions**; PHP syntax checks passed and working tree clean. Confirmed CP cash-out voucher number now starts at CP-1 (previous CP-2 sequencing defect fixed).
- Two remaining blockers: `CustomerDB::table('ledgers')` typo persisted in rejected ECP audit test despite prior attempt; corrected fixture to `CustomerLedger::create` and deliberately corrupt linked CR leg raw insert (`1555725f`). Pending CR cheque cleared to CP returned HTTP 500 because Eloquent ledger source ownership required CP source even for legitimate `CASH_OUT_CLEARANCE#<CR/PPR source ID>` cross-series clearance. `FinancialSourceOwnership` now allows **only** canonical CP clearance reference to same-project active CR/PPR source, still rejecting forged cross-project/wrong reference links (`e0b5cc5f`). New regression test added to verify both accepted CP linked clearance and forbidden arbitrary CP cross-series linkage (`acc4264f`).
- Newly fixed code **not yet verified by user locally**. Request exact full-suite rerun; do not increase P0 percentages until acceptance and resolve any more failing cases in same implementation work, not tracker-only follow-ups. No existing database records altered by these code changes.

### 2026-10-29 full-suite retest: fix two remaining test defects

- User pulled `b66e971c`, lints passed, full suite ran **53 PASS, 2 FAIL, 78 pending, 348 assertions** with `--stop-on-failure`. Canonical CR/PPR-to-CP clearance now passes, so production ownership compatibility fix is verified.
- The rejected-ECP audit test still contained `CustomerDB::table('ledgers')->insert`. Root cause in prior bot patch was substring replacement: replacing `Ledger::create` also inadvertently altered `CustomerLedger::create` to `CustomerDB...`. Fixed with indentation-anchored targeted replacement; source is `CustomerLedger::create`, intentionally corrupt foreign-series leg uses raw `DB::table('ledgers')->insert`. Confirmed latest fetched GitHub file no longer contains `CustomerDB`.
- PPR pending clearance narration test expected CP-252 although isolated database has no CP history; actual project allocator correctly produces **CP-1**. Aligned both submitted voucher and assertion with CP-1 without changing production allocator or historical number preservation. Both fixes in code commit `290048c4`.
- **Local acceptance pending**: run full `VoucherClearanceFlowTest` on pulled commit. Do not inflate percentages without verification. Existing tests and production finance data unaffected by this test-only patch. No tracker-only work: this entry accompanies corrected regression code.

### 2026-10-29 P0.1 closing-batch implementation: database preflight + isolated MySQL lock probe

- User asks to finish P0.1 93% → 100% in one turn; **cannot honestly certify 100% without actual disposable MySQL parallel runs, complete runtime writer inventory and approved legacy/schema evidence**. Existing `docs/LEDGER_INTEGRITY_CODEX_AUDIT.md` already inventories active JVM, Stock, Booking, Labour, BR, CR/CP, Mobile and blocked destructive JV/CV paths; unresolved raw SQL/database provenance and concurrency remain.
- Implemented **read-only source/account integrity preflight** `finance:preflight-ledger-constraints PROJECT_ID` (`79fe8a57`). Returns JSON for project/type duplicate CR/CP/PPR/EC/ECP source number/reference, missing ledger account, foreign-project ledger account, orphan linked customer source, and mismatched customer source/project. It refuses positive-ID invalid scopes, makes **no** DDL/data repairs and marks `constraint_applied=false`. Legacy uniqueness MUST NOT be imposed until preflight and schema compatibility are approved.
- Implemented opt-in `finance:probe-ledger-lock PROJECT_ID --seconds=3` (`bc3588a5`), a no-write project row lock held inside a DB transaction to measure real serialization across **two independent MySQL command processes**. It refuses to run unless `APP_ENV=testing`, `FINANCE_ISOLATED_MYSQL_LOCK_TEST=YES`, MySQL driver, and a database name containing `finance_lock_test`. Requires existing test projects in separate disposable DB; does not modify financial rows or run on production/staging. It tests lock serialization only, **not** end-to-end concurrent posting.
- Added two isolated regression tests for historical duplicate sources and cross-project account/source linkage with preservation of original tables (`9212e5ff`). Tests and commands **not yet executed on user machine**. No P0.1 percentage increase before acceptance; current **93% estimated**, not release certification. Next: user test run and disposable database concurrency evidence; review any remaining uncoordinated raw writers and approved unique/index rehearsal, never automatic production ALTER/repair.
- User verification: `php -l app/Console/Commands/VerifyLedgerConstraints.php`, `php -l app/Console/Commands/ProbeLedgerProjectLock.php`, `php artisan test --filter="test_ledger_constraint_preflight_" --stop-on-failure`, and existing `php artisan test --filter=VoucherClearanceFlowTest --stop-on-failure`. Note prior 133 PASS / 820 assertions checkpoint from user.

## P0 — PRIMARY DELIVERY ROADMAP: DATABASE `ledgers` INTEGRITY

**These five workstreams supersede the Laravel upgrade, demo, Hand Cash investigation, and generic testing as active priorities.** The older project baseline below is *secondary*, not a release gate. Progress figures below are approximate implementation/validation coverage, **not** historical financial correctness, and must not be treated as certified completion.

| Rank | Primary task | Estimated progress | Implemented evidence | Remaining acceptance gate |
| --- | --- | ---: | --- | --- |
| P0.1 | **Ledger protection:** audit and fix every live INSERT/UPDATE/DELETE/deactivate/overwrite, project scope and identity guard | **93%** | Mobile approval immutability and project scope; JV/CV destructive gates; Stock/Mobile Stock duplicate, orphan, amount/date/project guards; CR/CP editor prevents noncash JV/SV/BO/BR/PPR overwrite and rejects invalid linked sources | Every producer/mutator classified and tested, protected ledger rows cannot silently disappear |
| P0.2 | **Voucher integrity:** propagate correct source voucher number/date for JV, CV, Stock, Labour, Booking, CR, CP, BR, PPR | **85%** | Core JV header number, Labour JV number/date, Stock existing-posting date and amount checks | Every source creates ledger rows with verified source number/date, regression tests for all families, preexisting mismatch inventory |
| P0.3 | **Foreign-key / typed source identity:** source-aware, project-consistent relational integrity | **15%** | Existing physical `customer_ledger_id` and `charge_type_id` FKs confirmed on restored clone; typed source attribution design exists | Per-source FK/typed links, uniqueness & concurrency checks, vetted migration rehearsal; do **not** force `type_id` into one FK |
| P0.4 | **Soft-delete / append-only reversal:** protect historical business records and posted financial events | **15%** | JV/CV destructive write gates and CR/CP/PPR deletion tests exist | Cover all applicable business tables and side effects; financial reversals/append-only protection; MySQL schema review |
| P0.5 | **Historical recovery:** reconcile missing/inactive/overwritten JV and other ledger entries, then controlled repair | **10%** | 17,855 restored ledger rows inventory; 155 inactive; source ambiguity evidenced; Project 5 balance reproduced | Independently prove provenance per row, compare older backup/audit logs, approved dry-run, reversible repair and balance validation |

### Testing cadence and evidence-based progress rules (2026-10-28)

- **Default: run only targeted tests affected by each changed application path**, including neighboring regression tests where dependencies overlap. Do not repeatedly request `php artisan test --testsuite=Feature` after each small fix.
- **Run the full Feature suite only** for architectural/shared infrastructure changes, schema migrations, substantive cross-module behavior changes, pre-release gates, or a regression that cannot be isolated.
- If a new regression test is added, execute that test and the closely related existing test file/filter. Preserve the previous full-suite baseline without falsely claiming it covers newly changed code.
- **Latest user-verified baseline:** 436 Feature tests / 1,883 assertions, including JV-overwrite guard; clean Git working tree and PHP lint. This supersedes the older 417/428/430/435 baselines.
- P0.1 coverage **40% → 50%** (planning estimate): Stock/Mobile Stock idempotency and source consistency plus CR/CP cash editor source/type immutability now have locally passing regression tests. The remaining 50% reflects unreviewed write paths, concurrency and database-level enforcement, full delete/deactivation audit and source ownership gaps.
- Increment progress **when an independently checkable implementation milestone passes**, not on code inspection alone. Record what is implemented, what tests passed, and what is still unverified for every increment; never call percentages release readiness.
- Continue delivering source implementation via GitHub, Codex as independent inspector only; one consolidated commands block for the user, not repetitive commands.

### P0.1 — Posted ledger deactivation loophole closed in code (verification pending)

- Discovered active `LedgerController::destroy()` historically rejected **only** `CR/CP`, but other posted types `JV/SV/BO/BR/PPR` could be changed to `is_active=0` and could have related `customer_ledger` records deactivated; nonprivileged users could submit pending delete requests. This makes records disappear from active ledgers without physically deleting them.
- Replaced this action with a **fail-closed guard for every posted `ledgers` row**, retaining selected-project 404 and 422 `voucher_posted_to_ledger` behavior. Removed unreachable deactivation and deletion-approval branches. `draftDestroy()` remains separate and unchanged. Application code commits `31947f8`, `db9ec63`.
- Added five SQLite datasets checking `JV, SV, BO, BR, PPR` cannot be deactivated and no pending deletion is created, plus existing CR/CP coverage. Commit `7b626be`.
- **VERIFIED:** focused `VoucherDeleteProtectionTest` completed **12 passed / 54 assertions** on 2026-10-28; PHP lint passed, Git tree clean. Latest full Feature suite remains **436 passed / 1,883 assertions** before this change (not rerun by design).
- P0.1 implementation estimate: **60%**, verified by user's targeted test run: **12 passed / 54 assertions**, including five new JV/SV/BO/BR/PPR cases. Remaining gaps: other producers' mutation routes, raw SQL, triggers/grants, concurrent writes and application-wide deletion policies. No MySQL writes, migrations or historical repairs.

### 2026-10-28 P0.1/P0.2 combined implementation — Bank clearance

- Identified a live `ReportController::bank_posting_check()` path that previously permitted source types beyond active pending CR/PPR bank/check receipts, accepted a passed clearance without explicit business date, and treated any matching BR ledger as already posted without cross-project/source, activity, date, account or amount validation.
- Implemented transaction-scoped checks for active CR/PPR sources and bank/check payment types, required `bank_post_at` when passing, and validated all matching BR reference legs for consistency before idempotent reuse. Conflicting/duplicate BR ledger rows now trigger rollback rather than silently accepting a mismatched posting.
- Corrected BR amount selection: CR receipts may carry value in `amount_in`; use positive `amount_out` when present, otherwise `amount_in`, and reject zero/negative resolved amounts. This guards against zero-value BR ledger postings.
- Added two targeted regression datasets checking rejected cash and wrong-source bank clearance attempts do not change the source status or create a BR entry. Commits `04c4323`, `fba678d`, `3df5d78`.
- **Pending targeted verification:** `VoucherClearanceFlowTest`. Retain P0.1 **60%** and P0.2 **30%** until passing tests; no full-suite rerun required. No production or staging data change, migration, or historical repair.

### 2026-10-28 Bank clearance hardening — both active endpoints

- **User verified prior ReportController batch**: `VoucherClearanceFlowTest` **27 passed / 188 assertions**, both invalid-source cases passed, PHP lint passed, clean worktree. This closes its earlier pending gate.
- **New implementation**: `Finance/VoucherController::updatePendingBankPaymentStatus()` now blocks inactive receipts, requires an explicit `bank_post_at` on pass, computes a strictly positive BR amount from source `amount_out` or `amount_in` (CR often credits `amount_in`), and detects existing `BR` reference collisions across all sources. Idempotent reuse requires matching linked source, account, amount, activity and date. Any conflict rolls back source status/history and posting.
- **New synthetic regression cases**: missing date, inactive source, positive-value CR-to-BR posting, foreign source BR reference collision, in `VoucherClearanceFlowTest`. Commits `984be43`, `fa3c2ea`.
- **Progress estimation** P0.1 **60% → 65%**, P0.2 **30% → 35%**: prior ReportController tests are verified and the second clearance endpoint has a concrete implementation plus focused regression cases. **New tests are not yet run locally**; 65/35 is implementation coverage estimate, not verified release readiness; adjust if tests fail.
- Remaining: audit other live ledger source writers, direct SQL mutations, concurrency/unique constraints, actual MySQL source/date/FK compatibility, historical provenance. No production/staging writes or schema migrations.
- **Testing cadence**: run only `tests/Feature/VoucherClearanceFlowTest.php` in this iteration; no full-suite repetition.

### 2026-10-28 Bank clearance fallback-account project isolation

- User verified the previous bank-clearance batch: **31 targeted Feature tests / 206 assertions PASS**, PHP lint clean and working tree clean. No need to repeat the full Feature suite.
- **P0.1 security fix**: `Finance/VoucherController::updatePendingBankPaymentStatus` formerly trusted the existing receipt-linked ledger's account ID as a fallback if the request omitted valid accounts. A corrupted/cross-project source ledger could therefore select another project's account for a fresh `BR` posting.
- The fallback now resolves only a `project_head_subheads` row belonging to the selected project. If the existing account is foreign, the clearance transaction fails and preserves source status and ledger state.
- Two targeted tests added: foreign-project fallback rejection and valid same-project fallback acceptance. Commits `22c85c6` and `35679a2`.
- Progress: P0.1 **65% → 68% implementation estimate**, based on one additionally protected source-ownership path. New tests **not yet locally verified**; mark as pending until focused test execution. P0.2 stays **35%**.
- Remaining gates include full write-path inventory, concurrency/races, MySQL unique constraints and historical source provenance. No staging/production finance writes.

### 2026-10-28 multi-path ledger protection batch

- User-verified prior checkpoint: **2 focused fallback-account tests / 6 assertions PASS**, PHP lint passed, clean Git tree; earlier broader clearance tests **31 / 206 PASS**.
- **Three code paths hardened together**: (1) report BR clearance refuses existing ledger rows with nonzero `amount_in`; (2) finance clearance refuses explicitly wrong or incomplete bank account selections, rather than silently falling back; (3) cash `LedgerController::update` refuses inactive or already-mismatched source/ledger pairs for business date and debit/credit amounts, preventing legacy inconsistency from being silently overwritten.
- Two regression tests were added for finance clearance explicit-invalid and partial-account requests (both must preserve source status and not create BR). Commits `32e57b9`, `547435b`, `2c62efb`, `f419fcf`.
- Progress implementation coverage P0.1 **68% → 76%** and P0.2 **35% → 38%** for the multi-path protections. **New changes remain unverified locally** until focused `VoucherClearanceFlowTest` is run; progress may be revised if tests fail. No claim of total P0.1 completion or database constraints.
- Continue targeted testing only. No production/staging finance writes, migration, historical repair or deployment.

### 2026-10-28 Stock posting completeness (Web entry / bill + Mobile entry)

- User-verified last checkpoint: `VoucherClearanceFlowTest` **35 passed / 219 assertions**, and clean working tree; no full-suite rerun requested.
- **Web Stock entry and bill plus Mobile Stock entry** previously checked `StockLedgerPostingGuard` but still independently skipped each debit/credit leg based on a loose `exists()`. Removed that partial posting skip from all three writers: when preflight permits a fresh posting, both legs are inserted as a unit by the caller's transaction. Existing incomplete/duplicate reference remains rejected by the guard. This does not prove concurrency uniqueness for competing first-time requests.
- **Guard expanded:** both expected project-head-subhead IDs must exist in the intended project even when there are zero ledger rows; invalid amounts are also rejected before posting.
- Added 2 new focused SQLite tests for foreign and missing expected accounts. Commits `54860c8`, `86061a3`, `8bf73dd`, `254f7c0`.
- **Coverage estimate:** P0.1 **76% → 82%**, P0.2 stays **38%**. This is code implementation coverage, *pending fresh focused tests*. It does not certify MySQL concurrent uniqueness or historic record integrity.
- Only run `StockLedgerPostingGuardTest.php` and syntax checks for modified sources. No migration, production mutation, historical backfill or release.

### P0.1 stock first-posting concurrency control — 2026-10-28

- **Verified previous stock milestone:** user ran `StockLedgerPostingGuardTest`: **20 passed / 21 assertions**, syntax checks passed for Web/Mobile controllers and guard, Git working tree clean.
- **Implemented shared lock across all 3 stock writers**: `StockLedgerPostingGuard::alreadyPosted` now acquires a `projects` row `FOR UPDATE` for the selected project *before* checking ledger rows. Because Web Stock Entry, Web Stock Bill and Mobile Stock Entry invoke this guard inside the same transaction that inserts both legs, competing stock postings in that project serialize even when no `ledgers` record exists yet. This closes the first-posting gap not covered by `lockForUpdate` on empty `ledgers` results.
- Added fail-closed project existence check and a new SQLite regression test; fixture includes a synthetic `projects` row. Commits `6b900ae`, `2a0b9aa`. SQLite tests verify fail-closed behavior, **not** real MySQL concurrent scheduling; staging concurrency rehearsal and database constraints remain to be verified.
- **P0.1 coverage estimate 82% → 87%**, contingent on targeted test passing; P0.2 remains **38%**. Not a release readiness claim.
- **Future check**: independent MySQL staging concurrent requests, other raw SQL writers, typed source linkage and per-project voucher uniqueness. No historical repair, production writes or migration.

### P0.1 Stock financial-source consistency batch (2026-10-29)

- **Last verified local checkpoint:** Stock posting guard **21 passed / 22 assertions**, clean PHP syntax and Git worktree. No need to rerun unrelated feature tests.
- **New fail-closed protections** in `StockLedgerPostingGuard` shared by Web Stock Entry, Web Stock Bill and Mobile Stock Entry:
  1. A posting must contain exactly two positive, individually one-sided debit/credit legs, with equal value and **distinct project-owned accounts**. Zero amounts, negative amounts, same-account cancellation and mixed debit/credit legs are blocked.
  2. Already-posted stock references now require **exactly one matching customer source row** globally; an extra duplicate source or missing linked source invalidates replay rather than silently classifying it as safely posted.
  3. Fresh and already-posted stock sources must carry exactly one positive debit/credit-side amount equal to the expected ledger journal total. This prevents source amount drift or mismatched stranded source reuse. The Stock IN and OUT direction conventions are both accepted.
- **11 added targeted test cases**, including six debit/credit invalid expectation datasets, two hidden/missing source cases, and three source-amount scenarios. Commits `6851534`, `a50d130`, `c2cd148`, `2236c32`.
- **P0.1 estimated implementation coverage 87% → 93%** for tested-in-principle protective invariants; *new tests still need local execution*. This is not database-level durability assurance. Remaining: global writer inventory, MySQL concurrent test, database constraints and source-type migration design. P0.2 stays **38%**.
- Historical rows, production MySQL and staging were **not** modified. Any legacy source inconsistencies will be reported and blocked, not silently repaired.

### 2026-10-29 Stock identity and numeric safety checkpoint

- **User-verified:** Stock guard baseline **32 tests / 34 assertions PASS**, PHP syntax clear and Git tree clean. Previous Stock-source integrity batch is verified.
- Implemented explicit Stock reference namespace validation (`STOCK-ENTRY:<id>` and `STOCK-BILL:<id>` with positive integer IDs) and ISO business date shape validation *before touching source postings*. Prevents arbitrary `JV` reference aliases from being classified as Stock.
- Hardened minor-unit money parsing to fail on values above PHP integer capacity instead of risking silent saturation/overflow. New regressions include four malformed references, malformed date and overflowing amount.
- Commits `2bf124c`, `5a27e20`. **New targeted tests pending local verification**; full suite not needed.
- **P0.1 stays 93%** until remaining writer inventory/DB concurrency guarantees are delivered; P0.2 stays 38%. Do not increase milestones merely for narrowly scoped checks. Production MySQL and historical financial rows remain untouched.

### 2026-10-29 verified Stock checkpoint and JV dead-code removal

- User-verified `StockLedgerPostingGuardTest`: **38 tests / 40 assertions PASS**; syntax checks clean; Git working tree clean. This confirms the preceding source-identity/overflow patch.
- Reviewed `JournalVoucherController::destroy` and `update`: both already started with unconditional HTTP 409 but contained extensive **unreachable code** that would physically delete or overwrite historical `ledgers` by ambiguous polymorphic `type_id` if the gate were accidentally bypassed. Removed the unreachable destructive code, retained explicit 409 fail-closed behavior and left the JV edit view/read unchanged.
- Added focused regression checking both mutation endpoints still return 409 and ledger row count stays constant. Commits `4ea88b6`, `3854ea5`. This test remains **pending local execution**.
- **P0.1 remains at 93%**: removing latent dangerous code does not close DB-level cross-writer guarantees, and the user asked for evidence-based progress rather than cosmetic percentage changes. P0.2 remains 38%. Production/staging DB unchanged.
- Next acceptance gates: broader customer/booking/labour writer review, MySQL concurrency checks and source-typed DB constraints.

### 2026-10-29 Booking PPR + Labour JV active writer batch

- **Verified last checkpoint**: legacy JV blocked-mutator regression **1 passed / 3 assertions**, PHP lints clean, Git worktree clean.
- **Booking PPR live writer** (`BookingController`): previously selected just the first `PPR` ledger linked to a customer source, then updated ledger narrative/date even when the amount, activity, account or source owner was inconsistent. Now gathers *all* `PPR` ledger rows sharing a reference with row locks; rejects duplicates, foreign-linked sources, inactive ledger, wrong account, wrong date, amount drift or nonzero credit; rejects reuse of inactive PPR customer sources. This prevents silent repair/hijack of existing posted entries, preserving them for independent reconciliation.
- **Labour JV live writer** (`LabourController::createVoucher`): inside existing transaction, before posting two journal legs, now requires two distinct project-owned account IDs. Rejects cross-project and self-cancelling JV account mappings, rolling back voucher header/details if invalid.
- **3 new focused PPR replay regressions** in `VoucherClearanceFlowTest`: duplicate reference ledger, inactive source, and financial amount drift; all assert no repair/overwrite. Commits `921f00e`, `9bbdd00`, `d746dbb`.
- **Implementation coverage**: P0.1 remains **93%** (final DB/other-writer inventory gates still open); P0.2 **38% → 48% estimated** for PPR source-ledger identity and Labour JV account mapping checks, **not yet verified locally**. No real staging/production data changed; no migration or historical repair.
- Run only focused `VoucherClearanceFlowTest` with PPR filter first and lint both touched controllers. Do not rerun the full suite.

### 2026-10-29 Booking Extra Charge + Labour JV source-number alignment

- User-verified PPR checkpoint: **12 targeted tests / 93 assertions PASS**, PHP lint passed, clean Git tree.
- **Booking extra-charge writer fail-closed:** identified live legacy branch creating `customer_ledger.transaction_type = 'null'` and mixed CR/CP general-ledger rows, referencing `voucher` (not canonical `voucher_number`) and independently allocating wrong voucher types. Removed the unsafe insert sequence and replaced with explicit exception before any financial source or ledger write. **This intentionally disables legacy extra-charge posting** until a properly typed, balanced, tested workflow is designed; other Booking deposit/PPR flows are not disabled.
- **Labour JV voucher consistency:** stopped independently using `getLastJvVNumber()+1` for JV header while ledger legs used `getVocuherNumber('JV')`; the header now gets the same resolved `voucherNumber` as the ledger legs. Existing transaction links header and both ledger rows.
- Added targeted Booking regression checking extra-charge attempt redirects with error and preserves both financial table counts. Commits `999ed70`, `2a4520c`, `1ea2e02`.
- **P0.2 coverage estimated 48% → 55%** as a major broken voucher numbering/typing writer was blocked and Labour JV number alignment corrected. This is an implementation estimate, **new code/tests not yet verified locally**. P0.1 stays 93%; no production/staging data edits. Remaining: typed extra charge redesign, booking/Labour integration cases, overall voucher uniqueness and source typing.
- Only run focused extra-charge regression and relevant Labour tests; full suite not needed.

### 2026-10-29 Labour financial-source lock and double-vouchering protection

- User verified prior Extra Charge fail-closed regression: **1 PASS / 5 assertions**; changed controllers linted and Git status clean.
- **LabourController::createVoucher** now validates unique positive comma-separated attendance IDs before making any writes; rejects empty, duplicate, malformed and zero IDs. Existing site must belong to selected project.
- In the existing JV transaction, selected attendance rows are locked and counted against supplied IDs; every source row must belong to selected project and site and must not already have `voucher_status=created`. A mismatch rolls back before JV header/detail/ledger creation. Attendance update is additionally scoped by project and site, rather than ID only.
- Five synthetic feature test cases added for malformed and repeated attendance identities, all asserting no JV/ledger records. Commits `770a2a0`, `49afba4`. Local execution pending; no claims of verified results.
- **P0.2 estimated 55% → 65%** reflecting material Labour source/posted voucher consistency plus previously verified PPR and Extra Charge controls; P0.1 remains **93%** until database-level gates. Estimate is not production readiness. No migration or production/staging writes.
- Remaining: Labour valid end-to-end scenarios, double-submit concurrency on MySQL, source uniqueness constraints and safely rebuilding typed Extra Charge flow.

### 2026-10-29 Labour voucher input integrity

- User verified previous Labour attendance-identity change: **5 focused tests / 20 assertions PASS**; PHP lints clean. The `git status --short` command was interrupted after showing no lines; rerun to confirm tree.
- **New Labour JV guards**: reject money outside canonical positive decimal precision (scientific notation, negatives, values over 12 whole digits, or more than two decimal places) instead of relying on loose `is_numeric()` and float coercion; validate that decoded labour detail is a nonempty list with each employee having usable name, days and rate, instead of allowing malformed JSON to reach narration/accounting.
- Added **8 focused synthetic regression datasets** verifying malformed amounts/detail fail with 422 and do not create JV header/ledger rows. Commits `54c8182`, `61ed588`.
- New tests **await local execution**. P0.1 remains 93%, P0.2 remains 55%; this batch is a safety improvement, not a proven 10% milestone. Other Labour and Booking financial integrity gates remain open. No production/staging finance writes.

### New-chat handoff checkpoint — 2026-10-29

- **Latest local verification from user:** `php artisan test --filter=test_ambiguous_extra_charge_does_not_create_any_financial_rows --stop-on-failure` completed **1 passed / 5 assertions**. `BookingController.php`, `LabourController.php` and `VoucherClearanceFlowTest.php` PHP lint all passed. `git status --short` returned empty. User pulled `fe90acd` on `upgrade/laravel-framework`.
- **Last implementation**: ambiguous Booking `extra_charge` financial writer disabled before any source/ledger insert (old code mixed `'null'` source type with CR/CP and inconsistent voucher number fields); Labour JV voucher header now uses same resolved voucher number as both ledger legs. Extra Charge protective regression verified; **Labour JV end-to-end test still needed**.
- **Current estimated implementation coverage:** P0.1 ledger protections **93%**, P0.2 voucher/source integrity **55%**, P0.3 source typing/FKs **15%**, P0.4 reversal and soft deletes **15%**, P0.5 historical recovery **10%**. These are implementation estimates, not release readiness; P0.1 remains blocked on global writer inventory/database-level concurrency and source constraints.
- **Next major delivery (not documentation alone):** properly typed, balanced, auditable Booking Extra Charge replacement (define explicit financial source and matching voucher numbers/date), plus Labour JV focused integration regression and further Booking writer audit. Do not invent mappings or modify history without proven provenance. Existing disabled branch must stay fail-closed until correct flow has tests.
- **User workflow requirements:** On each substantive turn inspect and directly implement a coherent large batch through connected GitHub in `Bilal6230/blueMarketing` branch `upgrade/laravel-framework`; code + focused regressions + tracker in **same batch**. User insists on roughly **10 percentage points of meaningful progress per turn**, not decorative increments or one tiny fix. Never artificially inflate percentage; explain true blockers if target unattainable. Avoid document-only commits/turns unless explicitly requested. Ask user to run **only targeted new tests**, and give one consolidated Windows PowerShell pull/lint/test/status block. Full Feature suite only for major integration/release change. Never modify staging/production financial data, run migrations or historical repair without explicit approval and source evidence.
- **Known verified baselines:** Stock guard **38 passed / 40 assertions**, PPR-focused **12 passed / 93 assertions**, clearance **35 passed / 219 assertions**, posted-ledger deletion **12 passed / 54 assertions**, legacy JV blocked edit/delete **1 passed / 3 assertions**, latest Extra Charge **1 passed / 5 assertions**. Earlier broad Feature checkpoint **436 passed / 1,883 assertions**, not repeatedly rerun.
- When new chat begins, read this tracker and `docs/LEDGER_INTEGRITY_CODEX_AUDIT.md` from GitHub first; inspect current branch HEAD before editing. Keep all changes safe, reversible and narrowly scoped without impacting unrelated existing modules.

### Primary delivery sequencing

1. **Prevent new data loss** first: finish source-specific mutation guards, database authorization, concurrency and comprehensive regression tests.
2. **Fix new voucher/date propagation** for every ledger-producing module. Compare source and ledger fields; never rewrite historical dates silently.
3. **Introduce source-aware FKs/constraints** through tested, backup-first migrations on an approved disposable environment; maintain compatibility with existing overloaded `JV` rows.
4. **Implement safe deletion/reversal** across modules without blanket soft-deletes on posted journal rows or destructive migration rollbacks.
5. **Repair historical rows last**, after ownership evidence and explicit authorization; no balance plug or guessed `type_id`.

### 2026-10-28 P0.1 / P0.2 new implementation checkpoint

- Implemented a **fail-closed linked customer-ledger date/activity check** in `StockLedgerPostingGuard`. Existing stock `ledgers` legs may only be considered already posted if the related `customer_ledger` entry shares the original business date and is active. Previously the guard checked its project, reference and transaction type, but not its date/activity.
- Added two SQLite cases for wrong `customer_ledger.date` and inactive `customer_ledger.is_active`; expanded synthetic fixture columns. GitHub commits `d935b2a`, `9386532`.
- **Not yet locally verified:** rerun the focused Stock regression and full Feature suite; latest previously verified baseline remains **428 passed / 1,871 assertions**.
- At this checkpoint P0.1 was estimated **40%**. The newer 2026-10-28 test-confirmed checkpoint above supersedes it with **50%**; P0.2 remains **30%**.
- No MySQL database, source attribution, migration, recovery or production deployment operation.

### P0.1 one-turn implementation — stranded Stock source safeguard

- Implementation: `StockLedgerPostingGuard::alreadyPosted` now checks `customer_ledger` even when **zero** stock ledger legs exist, catching duplicate source rows and cross-project, inactive or business-date-mismatched stranded source records before allowing new postings.
- Scope: Web Stock entries/bills and Mobile Stock share this guard; it does not repair any older incomplete records.
- GitHub implementation and five regression cases: commits `5814329`, `3110572`.
- Baseline before these changes: user verified **430 Feature tests / 1,873 assertions**, working tree clean.
- Post-change focused and full test execution **pending local verification**. Do not mark full P0.1 complete on the basis of this limited guard.
- Strictly no MySQL writes, migrations, production deployment, or historical backfill.

### 2026-10-28 P0.1 cash-ledger editor safety batch

- **Critical bug addressed in source:** the generic `LedgerController::update()` previously computed both debit/credit amounts as **zero** for `JV`, `SV`, `BO`, `BR`, `PPR` etc., while changing the account/date/detail. This could silently destroy posting meaning if a non-cash ledger row reached the cash editor.
- **Implementation:** hard gate inside the locked update transaction restricts the editor to `CR` or `CP` ledger rows that have a linked active `CustomerLedger` with matching project, transaction type and reference. Any missing/foreign/inactive/wrong-type source fails closed. Existing source synchronization remains transactional. No historical rows are modified.
- **Regression:** isolated test added in `VoucherClearanceFlowTest` verifying a synthetic `JV` ledger cannot be zeroed via this editor.
- **Latest previously verified run:** **435 Feature tests / 1,879 assertions**, clean Git status, before this batch. **Post-batch tests NOT YET VERIFIED locally**, so no progress percentage increase or release readiness claim.
- **Follow-on:** Audit CR/CP reference evolution and authenticated route responses; fail-closed may intentionally reject legacy inconsistent cash rows pending separate reconciliation. Preserve immutability and do not auto-align historical references.

### Latest verified testing checkpoint

- User's focused `StockLedgerPostingGuardTest`: **11 passed / 11 assertions**, six conflict datasets executed.
- User's full isolated Feature suite: **428 passed / 1,871 assertions**; working tree clean after pull at `c524251`.
- The earlier `@dataProvider` failure is **resolved and verified**, not an open blocker.
- These synthetic SQLite tests **do not** certify MySQL concurrency, original voucher provenance or production safety.

## Historical project-progress baseline

The earlier dashboard shown in the screenshot reported the following estimates:

| Track | Prior reported estimate | Current evidence/status |
| --- | ---: | --- |
| Overall application migration | 88% | Historical estimate only; deployment is blocked |
| Laravel 13 application | 98% | Full isolated Feature suite 428 passed / 1,871 assertions on 2026-10-28 |
| Migration compatibility | 82% | Schema/history and DDL provenance unresolved |
| Financial reconciliation | 60% | Historical estimate; unverified JV/CV ownership and missing ledger rows remain |
| Database restore & connectivity | 100% | Local restricted clone was restored and read successfully; **not** production reconciliation |
| Client demo readiness | 55% | Financial correctness has not been certified |

**Dashboard note:** The local SQL clone reproduced Project 5 Hand Cash Balance of **-76,910** by subtracting Dasti Cash **83,910** from active ledger net **7,000**. This verifies the formula for that snapshot, **not** why an earlier displayed balance of 11 changed; no prior-image audit or independently proven historical delta is available.

## Current five-track implementation board (not a percentage of release readiness)

| Workstream | State | Concrete evidence and next acceptance gate |
| --- | --- | --- |
| Laravel 13 and isolated tests | Active / previously green | 428 Feature tests passed / 1,871 assertions after Stock provider fix; additional integration and MySQL rehearsal pending |
| Ledger write-path integrity | Partially implemented | JV and Labour number/date fixes, Mobile approval scope/field guards, web and mobile Stock fail-closed guard; source-specific integrations/concurrency unfinished |
| Source attribution, FKs and numbering | Design / blocked | Existing ledger FKs: charge_type_id and customer_ledger_id. `type_id` is shared; typed source FK design and schema compatibility await approval |
| Historical ledger reconciliation/recovery | Not authorized | Missing/inactive/duplicate/source mismatch inventory and independent audit evidence needed before any backfill or reversal |
| Deletion protection and deployment | Blocked | JV/CV destructive endpoints currently gated; append-only reversal and schema-aware SoftDeletes are future work; no production deployment |

## 2026-10-28 test-failure tracking

- User's earlier local Feature suite: **417 passed / 1,860 assertions**; PHP syntax clean and git clean after Labour fix.
- After Stock guard: **422 passed, 1 failed / 1,865 assertions**; failing test: `StockLedgerPostingGuardTest::test_conflicting_stock_posting_is_rejected`.
- Root cause: PHPUnit ran method with no arguments because its docblock data provider was not registered.
- Code fix pushed: explicit PHPUnit attribute `#[\\PHPUnit\\Framework\\Attributes\\DataProvider('conflicts')]` at commit `794fc543`.
- **RESOLVED:** post-fix 11 focused tests / 11 assertions and 428 Feature tests / 1,871 assertions passed; all six datasets were executed.
- No database operations, production deployment, migration or historical repair were performed.

## Working conventions

1. **ChatGPT implements via GitHub**; Codex provides independent review/inspection only, not unreviewed direct implementation.
2. User receives **one consolidated PowerShell command block per turn** for pull, isolated tests, syntax checks and git status.
3. Keep the restricted local clone SELECT-only; never run PHPUnit against it. No migration, seed, financial UPDATE/DELETE/INSERT on production/staging/clone.
4. Preserve `vendor.before-laravel13-incomplete/` locally, excluded through `.git/info/exclude`; never stage it.
5. Require passing local tests, database FK and source matching evidence, financial reconciliation and approved reversible recovery plan before deployment.
