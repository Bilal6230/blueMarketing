# BlueMarketing V2 — Reversal, typed models and temporal-history evidence checkpoint

**Branch:** `upgrade/laravel-framework`
**Date:** 2026-10-31
**Status:** Real implementation committed; new regression + real MySQL checks require owner/CI verification. NO deployment.

## 1. Immutable transactional V2 posting reversal (P5.G1 implementation)

`app/Services/Finance/V2PostingReversalWriter.php` implements `reverse(projectId, originalBatchId, requestKey, effectiveDate, reason, actorId)`. The writer:

- Is restricted to isolated V2 connections using the existing fail-closed guard; it is **not connected to legacy routes**.
- Locks the project and original posted batch, refuses reversal-of-reversal, requires original physical typed source and valid same-project account legs.
- Enforces balanced original legs; creates a new posting batch with `reversal_of_batch_id` FK and **swapped debit/credit** per original line in one transaction; original batch/lines are never modified.
- Uses a request fingerprint bound to original batch metadata, lines, reason and effective date; verifies persisted reversed legs on retries; a second different key is rejected.
- Does **not** create another typed source claim (those are unique to the original source). The report reader resolves the reversal's typed source through `reversal_of_batch_id`, and marks reversal rows explicitly.

**Regression:** seven new behavioural tests appended to `V2JournalPostingWriterTest.php`: exact typed source and financial line checks, request idempotency and duplicate reversal protection, tamper rejection, reversal-of-reversal rejection, no write on unbalanced origin, and missing typed source rejection.

**Still required for P5.G1:** actual CI/regression PASS, MySQL concurrency/race test, all-family central API/approval wiring, owner-approved reversal policy. V1 endpoints remain unchanged.

## 2. Real typed Laravel models and MySQL contract (P3.G4 implementation)

- Added V2-only models `FinancePostingBatch`, `FinancePostingLine`, `FinancePostingSourceClaim`: explicit table and primary keys, original/reversal relationships and DECIMAL(18,2) money-as-string casts, immutable dates.
- `DatabaseV2PostingModelTest.php` constructs the isolated full V2 schema and validates seven relationship FKs, schema columns, source claim identity and money/date casting.
- `tools/database_v2/verify_mysql_v2_model_schema.php` checks *actual isolated MySQL* tables, seven FK relationship columns, DECIMAL scale and reversal/idempotency unique indexes without reading business data.
- Existing V1 legacy models still expose `journal_vouchers.site_id` and `ledgers.journal_voucher_detail_id` while old source schema lacks these columns. They were intentionally **not silently removed or added to historical tables**.

**Still required for P3.G4:** full existing-model/cast mapping decisions, owner-approved compatibility policy, real MySQL verifier and CI tests PASS.

## 3. Historical temporal/audit inventory (P4.G3 implementation)

`tools/database_v2/audit_local_temporal_history.php` reads only `bluemarketing_v2_local_rehearsal`, checks exact snapshot metadata SHA and 57 original tables, inventories original DATE/DATETIME/TIMESTAMP fields and `deleted_at` counts, verifies 14 archived Laravel migration-history records and outputs only aggregate counts plus current MySQL session/global/system timezone labels. It does **not** update historical values.

**Critical limitation:** Matching row hashes protects stored temporal strings, but original export-session timezone meaning must still be independently established from original source SQL/session evidence before P4.G3 acceptance. The full 57/57 and 120165/120165 per-row source parity previously passed; this new inventory still needs execution.

## 4. Git whitespace issue

Previous `git diff --cached --check` reported only four extra blank EOF lines in Markdown. The original `a0bc6c97` commit/push succeeded; those trailing blank lines have now been removed in new GitHub commits. LF/CRLF warnings on Windows were warnings, not PHP failures.

## Formal gate policy

**8/24 = 33.3% accepted** remains accurate at code delivery. All Phase 3/4/5 acceptance targets retain OPEN status until independent acceptance proof above. No V1, staging, client, source SQL, or live DB was modified. No history repair, inferred source claim, or deployment performed.

**Additional safeguard:** V2 reporting now verifies every reversal has the exact inverse multiset of original account/debit/credit legs, including when the date filter hides the original; a balanced-but-not-inverse tamper regression is included.

### 2026-10-31 — Owner verification PASS; source export timezone proof and cash reversal regression delivered

- Owner verified commit `587114d7` with **41 PASS / 139 assertions**, all new PHP lint PASS and clean working tree. Real local MySQL audit returned `V2_MYSQL_MODEL_CONTRACT_PASS`: 3 V2 typed models, 7 FK relationships, 2 exact DECIMAL casts, 2 posting uniqueness constraints. Independent read-only temporal history returned `V2_TEMPORAL_HISTORY_READ_ONLY_PASS`: 57 source tables, 142 original temporal columns (127 TIMESTAMP, 15 DATE), 320043 non-NULL values, 2158 soft-deleted rows, 14 migration archive rows, 0 new typed source claims. Local server TZ reported `Pakistan Standard Time`, which **does not establish original export-session TZ**.
- `git diff --check a0bc6c97 HEAD` failed **only** on two Markdown trailing whitespace lines. Commit `a0a630e9` fixed them; actual 41 tests and MySQL audits were green before that edit.
- Actual new code: private snapshot SHA-gated `prove_source_export_timezone.py` inspects explicit SQL export timezone directives (read-only, no source row output), plus cash CR/CP immutable reversal source-lineage, idempotency and cross-project negative tests in `V2CashPostingWriterTest.php`. CI executes independent synthetic timezone scanner selftest. This is **not** new 41-test approval: these additions remain unexecuted by operator/CI.
- **Gate status 8/24=33% unchanged**. P3.G4 still needs full legacy model compatibility, P4.G3 needs original export-session provenance + parity/acceptance, P5.G1 needs concurrency and controlled API integration. No V1, staging, live or original source database mutation.
