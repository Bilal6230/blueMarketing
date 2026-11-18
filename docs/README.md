# BlueMarketing — Active Development Handoff

**Updated:** 2026-11-18  
**Branch:** `upgrade/laravel-framework`  
**Local project:** `C:\laragon\www\blueMarketing`  
**Configured database:** User verified `bluenew_clean_final`; **never hard-code this name**, always follow `.env`.  
**Documentation policy:** This is the **one** current status/handoff file. Update after **every implementation batch**; do not create another roadmap/handoff document. `docs/BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md` holds original client requirements. Superseded documents are recoverable in Git history.

## The only active roadmap — five milestones

| # | Milestone | Status / next gate |
|---|---|---|
| 01 | Nonblocking global loaders and color-matched voucher skeletons | **COMPLETE — owner browser PASS + 8 PHP tests PASS** |
| 02 | Individual module/action permissions; modern Roles UI | **Code implemented, tests PASS; owner browser CR Edit visibility PASS.** Full cross-module Edit/Delete/Approve/Reject/Print acceptance not yet established; avoid claiming full completion. |
| **03** | Voucher relationships, safe Create/Edit/Delete, FK and soft-delete / is_active inventory | **CURRENT — PARTIALLY IMPLEMENTED.** Owner confirmed 7/7 targeted tests PASS and MySQL FK/orphan audit PASS (7 FKs present, 1 missing; zero orphans, zero cross-project account links). Missing FK migration and broader CRUD acceptance pending. |
| 04 | Cleared cheque/online edit/delete plus linked-voucher cascade | **NOT STARTED under new rules.** |
| 05 | Configurable reversal ON/OFF and full application regressions | **NOT STARTED under new rules.** |

## Current Milestone 03: committed code

**1. Real schema / orphan audit, read-only.** `app/Console/Commands/AuditVoucherSchema.php` implements `php artisan finance:audit-voucher-schema` and `--json`. Lists **all tables with neither `deleted_at` nor `is_active`**, 8 exact foreign-key candidates, orphan counts and cross-project JV/CV detail/account mismatches in the **configured** database. It does **not** alter data. **Owner ran this on `bluenew_clean_final`: exact missing-column inventory is now available in their terminal output (and JSON generated locally).** Technical/append-only event/history tables should not indiscriminately be assigned business delete semantics.

**2. Exact foreign keys, staged not yet applied.** `database/migrations/2026_11_18_150000_enforce_voucher_source_foreign_keys.php` adds missing single-target FKs only after preflighting all orphan references and cross-project detail/account links; uses **RESTRICT** for physical DELETE/UPDATE (never cascade). Candidate relationships:
- `journal_voucher_details.journal_voucher_id → journal_vouchers.id`
- `commision_voucher_details.commision_voucher_id → commision_vouchers.id`
- `journal_voucher_details.account_id → project_head_subheads.id`
- `commision_voucher_details.account_id → project_head_subheads.id`
- `ledgers.journal_voucher_detail_id → journal_voucher_details.id`
- `ledgers.customer_ledger_id → customer_ledger.id`
- `ledgers.project_head_subheads_id → project_head_subheads.id`
- `ledger_source_attributions.ledger_id → ledgers.id`

`ledgers.type/type_id` is **polymorphic**; never add one misleading FK to it. Existing matching FKs are reused. **Owner audit confirms the preconditions: all eight target relationships exist, orphan counts are zero, cross-project account mismatches are zero.** Migration may now be applied on this *local* database after pulling latest code and checking `db:show`; it will preserve seven existing FKs and add only the missing `ledger_source_attributions.ledger_id` constraint. Added exact MySQL column-type and InnoDB preflight to prevent incompatible DDL. No rows are silently deleted or relinked.

**3. Legacy JV / CV safe linked lifecycle.** `app/Services/Finance/LinkedVoucherLedgerRetirement.php` validates parent project, locks header/details/linked ledgers, compares every active debit/credit/account leg as a multiset, rejects cross-project or contradictory rows, and deactivates **only the voucher's exact linked old ledgers** (`is_active=0`). Legacy JV/CV Delete controller transactions now soft-delete voucher header and details after retiring those ledger rows. Legacy JV/CV Edit transactions retire old ledger legs and soft-delete old details, then create correctly linked replacement details/active ledger legs under the **same voucher ID**. Previously posted ledger history remains as inactive rows instead of creating duplicate compensating entries in these **legacy branches**. CV AJAX Edit/Delete and JV delete messages updated.

**4. Draft voucher delete.** `LedgerController::draftDestroy` now requires `finance draft delete`, scopes the draft to selected project, sets `is_active=0`, uses its existing `deleted_at` soft-delete within a transaction, and supports AJAX JSON. The old `direct-update` / queued `PendingUpdate` path is no longer used for this action.

**5. Regression tests committed and locally PASS (owner, 2026-11-18):**
- `tests/Unit/Finance/VoucherRelationshipSafetyMilestoneTest.php`: FK preflight, no-hard-delete contracts, JV/CV and Draft source logic.
- `tests/Feature/Finance/LinkedVoucherLedgerRetirementTest.php`: isolated in-memory SQLite transaction tests for JV/CV success, cross-project rejection and amount mismatch rollback. Does not write to `bluenew_clean_final`.

## Owner-confirmed Milestone 03 verification (2026-11-18)

- `VoucherRelationshipSafetyMilestoneTest`: **4 PASS, 54 assertions**.
- `LinkedVoucherLedgerRetirementTest`: **3 PASS, 10 assertions**.
- `php artisan view:cache`: PASS. DB audit connected to `bluenew_clean_final`.
- **7/8** exact source/detail/account relationships already have actual FKs, even though initial baseline DDL showed only index definitions. **Only `ledger_source_attributions.ledger_id → ledgers.id` missing**. **8/8 orphan counts = 0**, JV and CV cross-project detail links **0**. Do not introduce duplicate FK constraints; retain names already applied.
- The audit's “neither `deleted_at` nor `is_active`” table list includes immutable events/audits such as `daily_voucher_approval_events`, `finance_online_lifecycle_events`, `finance_cheque_lifecycle_events`, `ledger_source_attributions` (mapping evidence), and infrastructure tables such as `migrations` and `failed_jobs`. **Do not blanket-add lifecycle flags to these.** Potentially mutable business roots warranting later per-module migrations and CRUD review include `finance_plot_receipts`, `finance_plot_purchases`, `finance_cheque_intakes`, `finance_online_intakes`, `finance_bank_transfer_sources`, `finance_extra_charge_sources`, `finance_extra_charge_payment_sources`, `charge_types`, `account_types`, and related operational stock/employee records. Some are part of future Milestone 04; creating an `is_active` column alone does not implement logical deletion and should not masquerade as completed CRUD.
- The new `finance:audit-voucher-schema --require-fks` read-only gate returns nonzero if any targeted FK is missing, any orphan exists, or a cross-project JV/CV detail link exists. It is expected to FAIL **before** the missing FK migration and PASS **after** that migration (provided no schema changes introduce new issues). Full MySQL migration execution and this new gate have not yet been run by owner.

## What is still required before marking Milestone 03 COMPLETE

- **Owner DB evidence obtained:** eight FK/orphan relationships and lifecycle missing-column list. Apply the one missing FK via the prepared migration on local DB after checking target, verify with `--require-fks`. Decide actual business-table lifecycle migrations after tracing source writers and consumers, not blind all-table ALTER.
- **Non-legacy and remaining modules:** Posted/typed JV/CV (some existing controllers still require reversal or draft-only corrections), SV and the other CR/DR/CCR/CDR/BOR/BOP/Bank/Plot/Extra Charges source-write paths require full relationship-safe create/edit/logical-delete review, implementation and tests. Do not silently weaken linked accounting or project ownership. Milestone 04's cheque/online cascades stay separately tracked.
- **Acceptance:** latest code PHP lint/view cache + both tests + representative browser create/edit/delete and ledger balance checks have **NOT** been performed by assistant. Do not claim completed until those pass.

## Next local verification (owner's Windows PowerShell)

```powershell
cd C:\laragon\www\blueMarketing
git pull --ff-only origin upgrade/laravel-framework
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=VoucherRelationshipSafetyMilestoneTest
php artisan test --filter=LinkedVoucherLedgerRetirementTest
php artisan finance:audit-voucher-schema
php artisan finance:audit-voucher-schema --json | Set-Content -Path .\storage\app\voucher-schema-audit.json -Encoding UTF8
# Owner audit verified zero orphans and cross-project issues. After reconfirming local DB:
php artisan db:show
php artisan migrate --path=database/migrations/2026_11_18_150000_enforce_voucher_source_foreign_keys.php
php artisan finance:audit-voucher-schema --require-fks
```

The audit command is **read-only**. **FK migration is now cleared for the verified local database only** after `db:show`; its SQL type/InnoDB preflight fails closed on incompatible schemas. Do not apply to live/staging without separate authorization. The remaining business lifecycle columns and CRUD flows are pending. Browser testing of any updated JV/CV must use disposable data in local database, not actual production vouchers.

## Nonnegotiable instructions for every next batch/chat

1. Read this file and root `AGENTS.md`; inspect current code, **implement real functionality and regression tests**, update this exact README in the **same turn**, and give a pull command. Do not return after merely updating documents.
2. Per-action authorization is server-enforced; independently grant each voucher's View/Create/Edit/Delete/Approve/Reject/Reverse/Print and specialized actions. No generic permission should silently override a specific denied action.
3. First-party UI DOM/AJAX uses **jQuery only**. Follow shared CR/DR compact cards, tables, filters, Tom Select, colored skeletons, nonblocking loading, modern modal/toast and floating row-action styles.
4. Business/finance records: **no hard deletes**. Prefer `deleted_at` where present; otherwise `is_active=0`; transactional updates only to proven source-linked rows. Validate selected-project ownership and exact source/detail/ledger references. Do not bind polymorphic `type_id` to a single FK. Avoid introducing database-name isolation.
5. Report **separately** code committed, local tests, actual DB audit/migration and owner browser PASS. Full Milestone 03 is not yet accepted.
