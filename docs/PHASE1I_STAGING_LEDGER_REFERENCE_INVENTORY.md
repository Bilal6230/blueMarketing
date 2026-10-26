# Phase 1I — Read-only Staging SQL Ledger Reference Inventory

Date: 2026-10-26 | Branch: `upgrade/laravel-framework`

## Input and method
- Input: the user-supplied **staging** SQL export `u429032065_staging_bluema (1).sql`; **not** verified current production data.
- Performed local Python read-only parsing of SQL `INSERT INTO` statements, collecting only row counts, ledger `type` and JV `type_id` references. No SQL imported or executed, and no private row values published.
- The parser reads emitted one-line value rows and initial columns, not a full SQL grammar. Counts are preliminary and should be verified through SELECT-only queries on an approved disposable restored snapshot.

## Aggregate findings from the supplied dump

| Metric | Parsed count |
|---|---:|
| Ledger rows | 17,850 |
| Ledger type `CP` | 11,068 |
| Ledger type `CR` | 3,698 |
| Ledger type `JV` | 2,823 |
| Ledger type `BO` | 101 |
| Ledger type `BR` | 77 |
| Ledger type `PPR` | 77 |
| Ledger type `SV` | 6 |
| Journal Voucher headers | 991 |
| Journal Voucher detail rows | 5,080 |
| Unique `type_id` values among `JV` ledger rows | 975 |
| Commission Voucher `INSERT` rows | **0 found** |
| Commission Voucher detail `INSERT` rows | **0 found** |

The export **does** define `commision_vouchers` and `commision_voucher_details` tables, but does not contain corresponding INSERT data. Consequently it **cannot** establish the actual frequency of historic CV/JV collisions in a populated Commission Voucher database.

## JV ledger reference ambiguity

Classifying each of the 2,823 `ledgers.type='JV'` references against the SQL export's **numeric IDs**:

| Numeric ID membership | Ledger rows |
|---|---:|
| ID exists in both `journal_vouchers.id` and `journal_voucher_details.id` | 2,742 |
| ID exists in details only (not JV header ID) | 81 |
| ID exists only in headers | 0 |
| ID exists in neither | 0 |

**Interpretation warning:** Numeric ID overlap is not an error by itself. Detail IDs and header IDs come from independent tables; a row whose `type_id` happens to exist in both has **ambiguous referent**, not proven corruption. The 81 detail-only references may be legacy detail-based postings or other migration conventions; they must NOT be bulk rewritten or automatically marked orphaned. This snapshot cannot confidently determine which entries originated as CV.

## Evidence-backed implications

- The exercised in-memory regression already confirmed a CV update can remove an unrelated JV posting if header IDs collide. That does not imply a known number of production incidents.
- Legacy `JV` rows should be preserved until typed `source_type/source_id` mappings are validated row-by-row.
- Header-vs-detail ambiguity, project/account matching, amounts and dates must be reconciled before proposing updates. Avoid unsafe bulk type conversions and destructive fixes.

## Next gates
1. Read-only test fixture for **Journal Voucher update** with colliding header/detail IDs; ensure unrelated voucher postings are untouched.
2. Inventory the relevant `type/type_id` report readers and write paths further, including Blade reports/API exports.
3. Before any migration, plan a **disposable MySQL restoration** of snapshot with SELECT-only aggregate reconciliation against the independently parsed counts.
4. Build a high-confidence mapping algorithm with an **unresolved/ambiguous** category; no automatic classification for ambiguous records.
5. Separate additive fresh-install schema baseline from production-safe migration and approved backfill workflow.

**No app logic, DB, production data, migrations or `master_live` changed.**
