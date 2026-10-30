# Database V2 — Concrete identity/schema blueprint (design, not deployed)

**Date:** 2026-10-30  
**Evidence:** `DATABASE_V2_RELATIONSHIP_MATRIX_2026_10_30.md`, exact branch controller/helper inspection, private SQL dump SHA-256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`.  
**Approval:** DRAFT / NOT YET SAFE TO EXECUTE. No `php artisan migrate` on V1 or production.

## 1. V2 account and project foreign keys

- Normalize all primary IDs on independent V2 entities to `unsigned BIGINT` (Laravel `id()`), including current `project_head_subheads.id` (V1 is unsigned INT). Normalize all FK columns to identical size and signedness; audit max IDs and uniqueness before import. Preserve original IDs wherever possible.
- `ledgers.project_head_subheads_id -> project_head_subheads.id`, `journal_voucher_details.account_id -> project_head_subheads.id`, and `commision_voucher_details.account_id -> project_head_subheads.id` are proposed enforceable links. Use project-account consistency checks, ideally DB-level composite FK `(project_id, account_id)` against a unique `(project_id,id)` on accounts if clean schema design permits.
- `journal_vouchers.project_id`, `bookings.project_id`, `customer_ledger.project_id`, `leads.project_id`, `plots.project_id`, `areas.project_id`, `users.project_id`, `punches.project_id` require type harmonization and nullability/legacy default review before FK enforcement.
- Existing V1 `journal_voucher_details.journal_voucher_id` DELETE CASCADE is unsafe for immutable posted financial history; in V2 favor RESTRICT and explicit audited correction/reversal, not cascading removal.

## 2. Verified bank identity — create `banks` entity, not account FK

- Current `AppHelper::getPakistanBanks()` defines **27 stable numeric IDs (1–27)**; `getBankNameById()` resolves these, not a database account. A project account FK for `bank_id` would be a false relationship.
- Actual dev dump: **1,345** non-null `customer_ledger.bank_id`, **46** non-null `booking_vouchers.bank_id`, **0** `draft_ledgers.bank_id`; **all 1,391 values map to the 27 current code-list IDs**. No unknown bank IDs in this snapshot, but confirm no late/live data before rollout.
- Introduce `banks` (id UNSIGNED BIGINT primary, `display_name` string, active status, timestamps; do not fabricate historical created/updated timestamps for newly modeled lookup rows); seed explicit historical 1–27 ID/name mappings with static-list validation and prevent renumbering.
- `customer_ledger.bank_id`, `booking_vouchers.bank_id`, `draft_ledgers.bank_id` become nullable unsigned BIGINT foreign keys to `banks.id`, default RESTRICT / cautious SET NULL only with verified retention semantics. Preserve bank ID values and original payment metadata on import.
- Existing `getPakistanBanks()` and `getBankNameById()` must transition to the lookup *after* parity tests; never change historical bank names silently. The list contains apparently overlapping naming that requires stable ID ownership, not merging.

## 3. Verified labour site account identity — rename misleading field

- Existing `sites.subhead_accounting_id` is written from LabourController::siteStore form sub-account and used in `LabourController::createVoucher` and labour JV detail filters as **`project_head_subheads.id`**, not `subhead_accountings.id`.
- The uploaded dump has **7/7** non-null site values matching `project_head_subheads.id`, with **0 cross-project mismatches**. The earlier guessed `subhead_accountings.id` mismatch (2 IDs) is **NOT an orphan defect**.
- In V2 rename to `sites.project_head_subhead_id` and add nullable FK `project_head_subheads.id` with matching BIGINT UNSIGNED; add application project equality check / validated composite FK after schema design. Preserve original IDs and audit column rename in old→new import map. Keep `head_accounting_id` separately until its intended meaning/cardinality verified.
- New labour posting should use actual `journal_voucher_details.id` for every ledger leg, and explicit typed header posting provenance, atomically. Do not treat site ID or account ID as voucher source identity.

## 4. Canonical finance posting — design for enforceable ownership

Use dedicated clean-V2 tables, names to finalize after writer audit:

| Entity | Core fields | Proposed constraints |
|---|---|---|
| `finance_posting_batches` | `id`, `project_id`, business `series`, `voucher_number`, `request_key`, `request_hash`, `effective_date`, `posted_by`, `posted_at`, `reversal_of_batch_id`, `created_at` | FK project, creator and optional self reversal; UNIQUE project+request_key (when non-null); UNIQUE reversal target as policy permits |
| `finance_posting_lines` | `id`, `posting_batch_id`, `project_head_subhead_id`, `debit`, `credit`, `detail`, `created_at` | FKs batch/account RESTRICT; non-negative fixed DECIMAL; exactly-one-positive-side CHECK; batch sum debit == credit via transaction/service and verification, not imagined SQL CHECK across rows |
| `finance_journal_posting_sources` | `posting_batch_id`, `journal_voucher_id` | Both actual FKs; UNIQUE batch and policy-driven uniqueness of journal header; no numeric `type_id` join |
| `finance_commission_posting_sources` | `posting_batch_id`, `commision_voucher_id` | Actual FKs; no misclassified JV headers |
| `finance_stock_entry_posting_sources` | `posting_batch_id`, `stock_entry_id` | Actual FKs; stock need not fake a `journal_vouchers.id` |
| `finance_stock_bill_posting_sources` | `posting_batch_id`, `stock_bill_id` | Actual FKs and source PK |
| Cash/booking/labour link tables | Batch FK, actual typed source FK and approved cardinality | Define separate typed links after full code & history review; no blanket multiple foreign-key columns with contradictory nullable values |

**Design gates:** preserve posted historical entries; no delete/update by overloaded `type_id` or free-form `reference`. Retain V1 `type`, `type_id`, `reference`, `voucher_number` as optional *legacy evidence* fields in staging/import audit, not operational source identity. Exact detail source mapping (JV detail ID) needs a UNIQUE per active posted leg and explicit reversal policy. All financial changes must be transactional, balanced, idempotent and project scoped. Use separate immutable reversals rather than rewriting posted batches.

## 5. Proposed fresh-migration and import order (dependency-aware)

1. `users`, permission master tables, `projects`, account hierarchy `head_accountings/subhead_accountings`, finance `banks` lookup and other independent reference masters.
2. `areas/zones/roads/facings`, `leads`, `plots`, `project_head_subheads` (then enforce source/project-account links), `sites` and labour master tables.
3. Booking, customers/payment source headers, stock items/parties/entries/bills, journal and commission headers/details, labour attendance, typed source headers.
4. `finance_posting_batches` → `finance_posting_lines` → exact typed source mapping tables (once their parent tables exist), then booking ledger links and approval audit history.
5. Deferred nullable/cyclic relationships in a checked second pass; post-load FK/uniqueness validations; preserve unchanged source IDs, `created_at`, `updated_at`, `deleted_at`, status, amounts and descriptions.
6. Compare every source row and financial aggregate independently; unreconciled ambiguous rows go into an immutable evidence/exception register, **never discarded, auto-remapped or auto-posted**.

This order is a **planning outline**, not a finalized migration graph. Full 57-table schema and all writer/reader mapping must be approved to finalize it. Isolated MySQL 8.4.3 tests are required; the uploaded dump was generated on MariaDB 11.8.9.

## 6. Next implementation gates

- Finish P1.G2: 54 candidate source semantics verified against code, FK/nullability/project/cardinality map and historical exception classification. P1.G3: all ledger writers/readers traced, including raw SQL, aliases, scheduled commands, mobile APIs, booking sales and extra charges.
- P2: freeze full schema, source-specific typed links, strict DB constraints and lossless import mapping. Only then create **separate clean-V2 migrations**, test with disposable MySQL and allow import to a new database.
- Never touch legacy `bluenew`, `u429032065_staging_bluema`, `u429032065_dev`, `master_live` or migration history without separate approval.

**Progress tracker:** `DATABASE_V2_REBUILD_ROADMAP.md`. This blueprint is substantial P1/P2 design evidence but does not increment a gate before its complete acceptance criteria pass.
