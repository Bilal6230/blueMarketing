# BlueMarketing — Typed Voucher Source Identity and Reversal Design
**Date:** 2026-10-26 | **Status:** Proposed architecture; no schema change approved or executed
**Branch:** `upgrade/laravel-framework`
**Verified baseline:** 285 passing / 2 failing / 1328 assertions on user's local isolated test run. The two RED tests concern ambiguous `ledgers.type='JV',type_id` numbers. Do not remove or reinterpret them.

## 1. Why this change is mandatory

Legacy Commission Voucher creation wrote ledger rows using `type='JV'` and `type_id=commission_voucher.id`. Genuine Journal Vouchers also use `type='JV'` and `type_id=journal_voucher.id`. Those two header ID sequences overlap. Hence matching a `ledgers.type_id` to a `journal_vouchers.id` does NOT prove its source. The active DataTables ledger reader currently overrides the ledger's voucher number using that numeric match, potentially turning an original displayed `JV-7001` into unrelated `JV-9001`. Some genuinely sourced JV rows nevertheless use header numbering for their display; merely preferring one field globally could break existing behavior.

**Hard rule:** Distinguishing the two historical types is impossible from their shared legacy type and numeric ID *alone*. Never infer ownership from coincident IDs, dates, amounts, string prefixes, or voucher numbers. For historical rows with no authoritative evidence, retain `unknown` status. Never edit or delete existing ledger rows as part of discovery.

## 2. Proposed append-only typed provenance

Use a dedicated immutable attribution table **after schema review/approval**; do not overload existing `type` or `type_id` in place. Proposed `ledger_source_attributions`:
| Column | Proposed type | Meaning |
|---|---|---|
| `id` | unsigned bigint PK | immutable attribution record |
| `ledger_id` | unsigned bigint, indexed | original ledger row |
| `source_kind` | constrained string: `journal_voucher`, `commission_voucher`, `unknown_legacy` | typed origin |
| `source_id` | nullable unsigned bigint | corresponding header ID; null when unknown |
| `evidence_kind` | string | `new_write_transaction`, `verified_external_reference`, `manual_review` |
| `evidence_reference` | nullable string | durable, non-secret approval/evidence key |
| `attributed_by` | nullable user ID | reviewer/action actor |
| `attributed_at` | timestamp | time of attribution |
| `created_at` | timestamp | insertion record |

**Constraints to evaluate against actual MySQL schema:** one current verified attribution per ledger, unique ledger ID when no historical correction workflow is needed, or append-only revisions plus a separate active pointer. A nullable source ID is required for ambiguous legacy rows. Never populate `source_id` from `type_id` alone. Foreign-key implementation must wait for actual production schema/orphan inventory; avoid cascading ledger deletion.

**Preferred audited correction model:** append-only source attribution revisions with an immutable supersession reference; unique current marker maintained in one transaction. Do not treat reclassification as a financial balance mutation.

## 3. New financial posting protocol (future code, not yet enabled)

1. Authorized actor submits project, voucher type, ordered balanced lines, date, and **client-generated idempotency key**.
2. Server validates all subaccount-to-project and head/subaccount mappings using exact integer cents. Reject malformed, negative or unbalanced amounts before any write.
3. Transaction acquires the same project-level lock as JV numbering, plus a unique durable request identity scoped to project, voucher type and idempotency key.
4. On duplicate request key: return the previously committed voucher without inserting new headers/details/ledger rows. A reused key with a differing canonical payload must fail with HTTP 409.
5. Allocate project-local voucher sequence through an approved database uniqueness strategy. Locking alone is insufficient proof of at-most-once behavior.
6. Create typed header, details, ledger postings and explicit source attribution **in one transaction**. Every created ledger has source kind and exact header ID proved by its transaction.
7. Roll back all writes if *any* header, detail, attribution or ledger insert fails. Respond without exposing confidential SQL errors.
8. Preserve signed amount semantics and debit/credit equality to the cent; treat schema decimal widths as an approval gate.

No CV posting/update/delete safety gate can be lifted until this protocol and its real permission tests pass.

## 4. Reversal instead of destructive update/delete

A posted voucher is immutable financial history. An authorized correction creates a **new balanced reversal entry** linked to the original typed posting, then optionally a replacement voucher. Proposed append-only `financial_reversals`: unique original voucher typed key (source kind + source ID), reversal voucher typed key, project ID, user ID, reason, approved timestamp, created timestamp. Duplicate reversal must be rejected idempotently. Reversal amounts negate the original directional effects in balanced lines. A second reversal, cross-project reversal, or unmatched ledger source must fail closed. A CV whose original ledger source is unresolved remains blocked pending reviewed evidence. No `DELETE` or soft-delete used as a substitute for reversal.

## 5. Historical migration as an explicitly separate, reviewed stage

A. Read-only export from **backed-up staging only**: project IDs, JV/CV header IDs, original ledger voucher number, account mapping, amounts and timestamps. Do not export PII/secrets into Git.
B. Run current `finance:audit-jv --type=ALL --only-issues` and `finance:audit-voucher-collisions` only in an approved environment; results identify *risk*, never authoritative ownership.
C. Classify each legacy ledger: `verified_jv` / `verified_cv` **only with verifiable independent evidence**, or `unresolved`.
D. Manual finance reviewer signs each nontrivial attribution. Preserve all original ledger values; write only new attribution rows after schema approval.
E. Compare full project-by-project opening/closing balances, row counts, debits, credits, and customer-ledger relationships before/after staged attribution. Any difference is release-blocking.
F. Implement controlled rollback for metadata migration only, preserving the historical original ledger.
G. Production deployment requires backup proof, change window, rollback rehearsal, written approval, and monitoring.

## 6. Reader behavior after source identity exists

- **Verified JV attribution**: join its *typed* journal header (and check project match) for authoritative display number.
- **Verified CV attribution**: join its *typed* commission header; display CV-specific number without borrowing JV ID space.
- **Unknown legacy attribution / no attribution**: display original ledger voucher number, mark `source_unverified` in UI/export; never silently overwrite from ambiguous JV header.
- **Missing or conflicting attribution**: flag an integrity error without dereferencing a guessed header.
- Reader must stay read-only and project scoped.

## 7. Acceptance suite required before release

- New JV/CV creates ledger, header, details and typed metadata atomically, including forced late-failure rollback.
- Repeated client request with same key inserts no duplicates; mismatched payload with same key fails.
- Two parallel submissions allocate non-colliding per-project voucher numbers on actual MySQL engine.
- Cross-project and missing account mappings reject prior to posting.
- Known JV and CV with same numeric IDs render their **respective** typed header numbers.
- Unknown legacy collision retains original ledger voucher number and explicitly shows unverified status.
- Old ledger rows never updated/deleted by migration, edit, delete or reversal.
- Only authorized actors can create, reverse, attribute and approve changes.
- Full test suite and known RED ledger display diagnostics pass because **actual provenance is supplied**, not by weakening test assertions.
- Production schema compatibility, history reconciliation, staged backup/restore and manual signoff all recorded.

## 8. Current decision and stop conditions

**No migration, CV feature restoration, backfill, automatic source attribution or production release is authorized.** The two red display tests remain intentional indicators of a defect. The already-working read-only reconciliation and collision inventories are a preparation stage only.

**Implementation dependency order:** schema inventory → design approval → migrations on isolated staging → new typed immutable posting/idempotency → provenance-aware reader → audited migration of provable history → append-only reversals → full-suite and staging reconciliation → explicit deployment permission.
