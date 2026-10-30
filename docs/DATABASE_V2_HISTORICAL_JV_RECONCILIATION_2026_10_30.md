# BlueMarketing V2 — Historical Financial Reconciliation Evidence, 2026-10-30

**Status:** Source SQL read-only **forensic checkpoint**, NOT automatic posting recovery or financial parity certification. Source: privately uploaded `u429032065_dev.sql` (MariaDB 11.8.9), SHA-256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`. No original customer rows, descriptive notes, amounts from any single customer, or PII committed.

## Data integrity baseline

| Check | Snapshot result | Interpretation |
|---|---:|---|
| Source tables | 57 | Full exported schema |
| Historical records | 120,165 | 44 nonempty tables |
| Original declared FK parent orphans | **0 / 40 constraints** | Non-NULL referenced IDs only; some FKs empty |
| Original primary-key duplicates | **0** | Every table with PK inspected |
| Original unique-key duplicates | **0 / 16 unique indexes** | SQL NULL semantics honored |
| Original bank IDs unresolved against IDs 1–27 | **0 / 1,391** | 1,345 customer_ledger + 46 booking_vouchers |
| Journal voucher headers | **991** | 988 type JV + 3 type SV |
| JV historical detail rows | **5,141** | 3,008 active + **2,133 soft-deleted** |
| SV historical detail rows | **6** | All active |
| Ledger rows typed JV (all statuses) | **2,970** | Includes non-JV source provenance; not direct general JV FK |
| JV-typed ledger rows with numeric type_id **not** a general JV header ID | **81** | Source identity unresolved |
| Numerically matched JV ledger header ID in a **different project** | **3** | Collision/incorrect attribution candidates, not deletion targets |

## Voucher header/detail/ledger evidence

An offline data-only multiset comparison grouped each general `journal_vouchers.type='JV'` header by its ID and `project_id`, counted its **non-soft-deleted** `journal_voucher_details` by `(account_id, debit, credit)`, and compared to `ledgers.type='JV' AND ledgers.type_id = header.id` whose `project_head_subheads.project_id = header.project_id`, using `(project_head_subheads_id, amount_in, amount_out)` with decimal arithmetic. **This is a candidate match, NOT authoritative typed source proof.** Duplicate legs were counted as separate multiset occurrences.

| Reconciliation metric | Result |
|---|---:|
| General JV headers | **988** |
| JV headers with exact candidate ledger multiset | **914** |
| JV headers not matching this candidate comparison | **74** |
| Candidate detail legs not matched in those comparisons | **124** |
| Additional candidate ledger legs in those comparisons | **2** |
| General JV headers with active detail legs but **no project-matched ledger candidate** | **64** |
| General JV headers with **zero active details** | **8** |
| Header debit/credit totals disagreeing with their active JV detail sums | **8** |
| Total active JV detail legs | **3,008** |
| Project-matched JV candidate ledger legs across general header IDs | **2,886** |

**Do not interpret** 124 as 124 confirmed missing ledger rows; stock ledger numeric identities and historical commission overlap, soft-deleted records, duplicate amount/account legs, and source metadata make that unsafe. The 8 header/detail-total disagreements are a separate accounting integrity blocker to inspect before re-posting; they must not be automatically “corrected.” A canonical `finance_posting_source_claims` association must be evidence-backed.

## Other source family overlaps

- `ledgers.customer_ledger_id` is a physical FK to customer_ledger but **ledger type can legitimately differ from customer transaction_type**, e.g., `CR` linked to a `PPR` source (**99** candidates), `CP` to `PPR` (**44**), `CP` to `CR` (**33**), and `BR` bank clearance to source customer_ledger. These are workflow research leads, NOT automatically invalid links.
- Exactly **2** JV-typed ledger rows have `STOCK-ENTRY:` reference prefixes in the export. That does not exhaust all historical stock-origin JV entries or provide universally reliable identity.
- Existing `SV` is a real `journal_vouchers.type='SV'` header (3 headers, 6 detail rows); it must be distinguished from normal JV in V2 posting logic.
- Active ledger type distributions and sums are retained only as aggregates; project/account report parity requires isolated V2 import before certification.

## Decision register and import disposition

| Category | Required handling |
|---|---|
| 914 matching JV headers | **Candidate attribution only.** Require evidence/approved explicit source or deterministic unique one-to-one leg proof before typed V2 claim; never merge indistinguishable rows |
| 74 mismatching JV headers | Import unchanged into archival legacy tables; record unresolved provenance; do not auto-insert, reclassify or delete financial legs |
| 8 JV header vs active-detail total disagreements | Block automatic journal re-post; owner-reviewed correction/reversal record only |
| 3 project-conflicting numeric JV matches | Keep original ledger row and type/reference evidence; never attach to header by numeric type_id |
| 81 unmapped JV numeric type IDs | Treat as legacy source-unverified, not orphaned general JV records |
| 2,133 soft-deleted JV detail rows | Preserve `deleted_at` as historical evidence; exclude from active posting calculation while preserving records |
| 1,391 bank IDs | Preserve exact original code-list IDs in newly seeded V2 `banks` parent; no inference to account IDs |

## Acceptance

**P4.G1 partial evidence** — file fingerprint, structural checks, uniqueness, source ambiguities and exception categories are captured. This is not the full backup/exception-resolution approval.

**P6.G2 NOT ACCEPTED** — financial integrity and V1/V2 balance parity cannot be signed off until the historical 120,165 rows have been imported to isolated MySQL 8.4, their original IDs/timestamps/amounts compared independently, and unresolved posting ownership reviewed.

No financial database connection, historical repair, import, or deployment performed during the offline audit.
