# Database V2 — Five evidence milestones, owner-verified checkpoint

**Date:** 2026-10-31  
**Branch/owner-local HEAD at verification:** `upgrade/laravel-framework` / `fe16de2d`.  
**Target:** isolated local MySQL 8.0.30 database `bluemarketing_v2_local_rehearsal` ONLY. No staging/live/V1 changes authorized.  
**Original private SQL SHA256:** `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`. Raw SQL, JSON evidence and customer rows remain outside Git.

## Five completed and evidenced sub-milestones (not five phase acceptance gates)

1. **Stock ENUM root-cause isolated and correction run on local V2 — VERIFIED.** Operator's read-only preflight identified exactly three case-only definitions: `stock_entries.flow`, `stock_movements.direction`, `stock_parties.type`. After backup instruction, operator ran explicit `--apply --confirm-database=bluemarketing_v2_local_rehearsal`; output: `V2_ENUM_CASE_SCHEMA_RESTORED` for all three and `V2_ENUM_CASE_REPAIR_PASS`. Script validated ordinal preservation. This was local V2 DDL, not a source-row UPDATE or production migration.
2. **Full original-column 57-table multiset proof — VERIFIED.** Operator executed `verify_mysql_full_row_digests.php` using private source evidence and received `V2_FULL_ROW_PARITY_PASS: 57/57 tables, 120165/120165 rows, all original columns compared, including duplicate identical rows`. This supersedes the older 54/57 mismatch checkpoint.
3. **Independent financial aggregate reconciliation — VERIFIED.** Operator executed `verify_imported_mysql_financial.php` with staged private `manifest.json`, `source_fingerprints.json`, original SHA and expected rows. Output: `V2_FINANCIAL_PARITY_PASS: 57/57 historical tables, 120165 records, exact ID/timestamp bounds and 34 source numeric aggregate columns match`. A preceding argument-free invocation aborted before verification; do not count it as a data failure.
4. **Legacy JV exception evidence classification — DOCUMENTED from prior offline source audit, not newly resolved.** Existing `DATABASE_V2_HISTORICAL_JV_RECONCILIATION_2026_10_30.md` records 988 JV headers, 914 exact candidate multiset matches, 74 candidate mismatches, eight header/detail total mismatches, three cross-project numeric collisions, and 81 unmapped JV-typed numeric IDs. These remain **UNKNOWN ownership** until independently resolved; 124 unmatched candidate legs are **not 124 proven missing ledgers**. No typed source claim should be created from `type_id` alone.
5. **Safe transition / gate register — DOCUMENTED.** Full-row and aggregate evidence is now separated from application posting correctness, historical attribution, derived-column policy and deployment authorization. Roadmap, overall tracker, and audit worklog carry the same checkpoint, explicit blockers and next read-only work. This closes the documentation handoff, **not** P4.G4/P6.G2.

## Formal phase acceptance disposition

**Remain at 8/24 = 33%** pending gate-by-gate review. P4.G3 now has strong source-values and timestamp evidence, but original timezone interpretation, audit history and any derived-column policy require independent acceptance before checkbox change. P4.G4 requires FK/account/project/financial-leg reconciliation, exception register decisions. P6.G1 requires full V1/V2 application-level row/report parity and acceptance; P6.G2 requires JV source ownership, financial balancing and approved exceptions. P3.G4, P5 and UAT are outstanding.

## Next safe work in order

1. Verify local branch/HEAD and clean tree after pulling documentation updates.
2. Design and run bounded **read-only** per-project JV detail↔ledger exception inventory against isolated local V2, with actual typed source evidence; aggregate/category output only, no customer values.
3. Reconcile the eight JV header/detail amount mismatches separately with source-document review, no automatic correction.
4. Audit `finance_posting_source_claims` / derived-column policy and V2 model/cast usage without posting or changing historical rows.
5. Advance formal gates only after operator-provided terminal output and documented independent acceptance. No live cutover, `migrate:fresh`, historical backfill or source reassignment.

**Production deployment BLOCKED.** GitHub edits in this checkpoint are documentation only; no CI or on-prem MySQL commands were executed by the assistant. Operator's pasted terminal outputs are the evidence for local data checks.
