# Database V2 — 57-table relationship and FK design audit (2026-10-30)

**Status:** Verified read-only SQL structural inventory + targeted Laravel source checks. **Not a completed full-code relationship sign-off**; P1.G2 remains in progress. Source: user-uploaded `u429032065_dev.sql` (MariaDB 11.8.9 export), SHA-256 `bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed`. No SQL restore, DB connection or data modification.

## Exact SQL inventory and reproducibility

- **57 CREATE TABLE**, **120,165 exported rows**, **40 declared foreign keys**, **54 unlinked `*_id` candidates** (an actual parse of column definitions excluding declared FK columns and PK fields; correct earlier tentative 56 estimate), **10 tables with deleted_at**.
- **All 40 declared FK checks have zero missing parent IDs** among exported non-null child values. Some FKs have zero child rows; do not present this as production integrity certification.
- The **54 unconstrained candidates are NOT 54 missing FKs**; some are polymorphic / code list / business identity values or have no matching parent table. Avoid blindly foreign-keying `type_id`, `record_id`, `model_id`, `tokenable_id`, `source_id`, `source_line_id`, `status_id`, or `bank_id`.
- **28** candidate parent mappings show integer type or signedness mismatch in populated tables (heuristic parent map, not 28 approved relationships). Examples: `ledgers.project_head_subheads_id` BIGINT UNSIGNED vs parent `project_head_subheads.id` INT UNSIGNED; `journal_voucher_details.account_id` same; `journal_vouchers.project_id` signed INT vs `projects.id` BIGINT UNSIGNED. Normalize ID types together *before* creating genuine FKs.
- Ledger: **18,003 rows**; JV-typed **2,970**; general JV headers **991**, details **5,147**. Among JV-typed entries, **81** `type_id` numbers do not match any general JV header, and **3** numeric header matches have a different account project (ledger IDs 6155, 6156, 11822). Numeric collision is NOT a valid ownership/deletion determination. `ledgers.journal_voucher_detail_id` column and `ledger_source_attributions` table are **absent in this dump**.

## Full 57-table census (row counts, actual FK counts, soft-delete column)

| Table | Rows | FKs | deleted_at |
|---|---:|---:|:---:|
| `account_types` | 6 | 0 | N |
| `accounting_expenses` | 1 | 0 | N |
| `areas` | 20 | 0 | N |
| `booking_details` | 3,970 | 1 | N |
| `booking_edit_audits` | 0 | 0 | N |
| `booking_vouchers` | 81 | 0 | N |
| `bookings` | 198 | 1 | Y |
| `charge_types` | 0 | 0 | N |
| `commision_voucher_details` | 0 | 1 | Y |
| `commision_vouchers` | 0 | 1 | Y |
| `customer_ledger` | 5,580 | 1 | N |
| `dasticash` | 4 | 1 | N |
| `draft_ledgers` | 1 | 0 | N |
| `facings` | 5 | 0 | N |
| `failed_jobs` | 0 | 0 | N |
| `head_accountings` | 44 | 0 | N |
| `hold_plots` | 0 | 2 | N |
| `installments` | 0 | 0 | N |
| `journal_voucher_details` | 5,147 | 1 | Y |
| `journal_vouchers` | 991 | 0 | Y |
| `labour_attendances` | 2,695 | 4 | N |
| `labour_ledgers` | 627 | 2 | N |
| `labours` | 165 | 1 | N |
| `lead_user` | 13,380 | 2 | N |
| `leads` | 13,396 | 0 | N |
| `ledgers` | 18,003 | 2 | N |
| `migrations` | 14 | 0 | N |
| `model_has_permissions` | 0 | 1 | N |
| `model_has_roles` | 52 | 1 | N |
| `password_resets` | 0 | 0 | N |
| `pending_updates` | 159 | 0 | N |
| `permissions` | 93 | 0 | N |
| `personal_access_tokens` | 18 | 0 | Y |
| `plots` | 726 | 0 | N |
| `project_head_subheads` | 561 | 3 | N |
| `project_zones` | 0 | 2 | N |
| `projects` | 6 | 0 | N |
| `punches` | 30 | 0 | N |
| `roads` | 11 | 0 | N |
| `role_has_permissions` | 227 | 2 | N |
| `roles` | 9 | 0 | N |
| `settings` | 13 | 0 | N |
| `sites` | 7 | 1 | N |
| `stock_bill_lines` | 0 | 2 | N |
| `stock_bills` | 0 | 0 | Y |
| `stock_counters` | 2 | 0 | N |
| `stock_entries` | 1 | 1 | Y |
| `stock_entry_lines` | 2 | 2 | N |
| `stock_item_balances` | 2 | 1 | N |
| `stock_items` | 2 | 0 | Y |
| `stock_movements` | 2 | 2 | N |
| `stock_parties` | 3 | 0 | Y |
| `stocks` | 0 | 1 | N |
| `subhead_accountings` | 448 | 0 | N |
| `users` | 52 | 0 | N |
| `works` | 53,405 | 1 | N |
| `zones` | 6 | 0 | N |

## Existing 40 actual FK relationships

- **Bookings:** `bookings.user_id -> users.id`; `booking_details.booking_id -> bookings.id`.
- **Commission:** `commision_vouchers.project_id -> projects.id`; `commision_voucher_details.commision_voucher_id -> commision_vouchers.id`.
- **Customer/cash:** `customer_ledger.customer_id -> leads.id`; `dasticash.project_id -> projects.id`.
- **Plots:** `hold_plots.plot_id -> plots.id`; `hold_plots.user_id -> users.id`.
- **Journal:** `journal_voucher_details.journal_voucher_id -> journal_vouchers.id` (currently ON DELETE CASCADE; revisit immutable audit protection).
- **Labour:** `labours.project_id -> projects.id`; `labour_attendances.(project_id,labour_id,marked_by,site_id)` each have separate FKs to projects, labours, users, sites; `labour_ledgers.(project_id,labour_id)` separately to projects/labours.
- **CRM pivots:** `lead_user.(lead_id,user_id)` to leads/users; `works.lead_id -> leads.id`.
- **Ledgers:** `ledgers.charge_type_id -> charge_types.id`; `ledgers.customer_ledger_id -> customer_ledger.id`, both ON DELETE SET NULL (data-loss-of-attribution risk if source is hard deleted).
- **Role system:** `model_has_permissions.permission_id -> permissions.id`, `model_has_roles.role_id -> roles.id`, `role_has_permissions.(permission_id,role_id)` to permissions/roles; polymorphic model IDs must not be forced into users FK.
- **Accounts:** `project_head_subheads.(head_accounting_id,project_id,subhead_accounting_id)` to heads/projects/subheads; `project_zones.(project_id,zone_id)` to projects/zones.
- **Sites/stocks:** `sites.project_id -> projects.id`, `stocks.project_id -> projects.id`; `stock_entries.party_id -> stock_parties.id`; `stock_entry_lines.(entry_id,item_id)` to stock_entries/stock_items; `stock_bill_lines.(bill_id,item_id)` to stock_bills/stock_items; `stock_item_balances.item_id -> stock_items.id`; `stock_movements.(item_id,party_id)` to stock_items/stock_parties.

## Unconstrained candidate groups to resolve before V2 DDL

| Group | Candidate fields | Disposition |
|---|---|---|
| Accounting/project | `accounting_expenses.project_head_subheads_id`, `areas.project_id`, `journal_vouchers.project_id`, `customer_ledger.project_id`, `ledgers.project_head_subheads_id`, `draft_ledgers.project_head_subheads_id` | Correct key types, verify source ownership and set restrictive finance deletes |
| JV/CV detail | `journal_voucher_details.account_id`, `commision_voucher_details.account_id` | Account PK FK after width alignment, project match; exact posting source identity separate |
| Booking / plot | `bookings.(project_id,customer_id,plot_id,broker_id)`, `booking_vouchers.(booking_id,project_id,customer_id,plot_id,customer_ledger_id,ledger_id)`, `booking_edit_audits.(booking_id,project_id,user_id,journal_voucher_id)`, `plots.(project_id,road_id,facing_id)` | Verify customer/broker domains, project match, non-destructive retention, nullable keys |
| CRM | `leads.(area_id,zone_id,follow_id,project_id)`, `works.user_id`, `project_head_subheads.(plot_id,customer_id)` | Code-level actor/customer resolution and historical null/default policies |
| Labour / users | `punches.(user_id,project_id)`, `users.project_id` | Actor ownership and retention; do not cascade financial history |
| Payments / possible polymorphic | `customer_ledger.(type_id,plot_id,bank_id)`, `draft_ledgers.(type_id,project_id,customer_id,plot_id,bank_id)`, `ledgers.type_id`, `pending_updates.record_id`, `personal_access_tokens.tokenable_id`, `model_has_permissions.model_id`, `model_has_roles.model_id`, `stock_movements.(source_id,source_line_id)`, `users.status_id` | Many are NOT physical FKs; require a dedicated typed source/lookup design |
| Stock master | `stock_bills.(party_id,entry_id)` | Parent FK verified from code/migration design, but both tables empty in this dump |
| Sites | `sites.(head_accounting_id,subhead_accounting_id)` | Head IDs all match; **2/7 subhead IDs lack existing subhead_accountings parent** under naive mapping; investigate source meaning before FK |
| Bank identity | `booking_vouchers.bank_id`, `customer_ledger.bank_id`, `draft_ledgers.bank_id` | **Do not FK to project_head_subheads**: `AppHelper::getBankNameById()` resolves IDs using `getPakistanBanks()`, i.e. code-defined bank lookup. Propose a versioned `banks` table retaining legacy list IDs after compatibility tests |

**Important correction:** An initial naive candidate mapping tried `bank_id -> project_head_subheads.id` and observed 9/46 and 254/1,345 unmatched values respectively. Code inspection proves that *mapping itself was false*, so those are **not actual orphan bank records**. Do not present them as FK violations.

## Verified current Laravel routes/write implications

- `LedgerController::store` creates `CustomerLedger` and `Ledger`; CR/CP's `customer_ledger_id` is a real source FK. `LedgerController::update` (approx L950–1030) **can update posted CR/CP ledger amounts/account/date** after locking and verifying source; replacing with immutable reversal/correction requires design and isolated regression, not blind disabling.
- `LedgerController::destroy` now rejects deletion of posted ledgers; `draftDestroy` sets only `DraftLedger.is_active=0` (draft, not posted).
- `BookingController::deposit` (~L2504–2565) creates or updates PPR ledger linked to `customer_ledger_id` and `booking_vouchers`; accounting updates must not affect unrelated sources. `BookingController::cancel` includes precheck for financial history and writes sale cancellation entries; trace `recordSalesVoucherEntry` implementation and reversal proof.
- `StockController::postEntryLedger/postBillLedger` writes `type=JV`, **generated type_id rather than journal header PK**, references `STOCK-ENTRY:id`/`STOCK-BILL:id`; `customer_ledger_id` is separate. `LabourController::createVoucher` writes genuine JV header IDs but does not fill the optional exact-detail FK in examined code.
- `AppHelper::getLastLedgerIdByType()` returns latest Ledger `type_id` by ledger row PK ordering; a global series marker **must not** be substituted for a header FK. `get_new_typeID()` returns the latest `CustomerLedger.type_id` for transaction_type, another independent numbering domain.

## Proposed safe architectural decisions to design/test (not yet implemented)

1. New V2 schema based on consistent unsigned PK/FK widths, tenant/project invariants, and explicit audited source identity; cannot make one overloaded `ledgers.type_id` a true FK to multiple sources.
2. Central immutable posting batches + balanced lines with FK to batch/account; typed source linking records per original entity (JV, CV, stock entry/bill, booking, labour, cash) with actual DB-enforced source FKs. Preserve legacy reference/type/number as historical metadata, not ownership.
3. Create `banks` lookup instead of assumed FK; retain source ID semantics, verify full historical bank list. Determine site subhead mismatch policy explicitly.
4. Use SoftDeletes for appropriate masters, reversible financial documents and append-only reversal postings; never rely on `cascadeOnDelete` for audited financial detail.
5. Separate greenfield migration baseline from deployed Laravel history; lossless import preserving original IDs, created_at/updated_at/deleted_at; quarantine ambiguous ownership with source evidence and owner review.

**Remaining P1.G2:** inspect model/controller relationships for every candidate, compute project-scope and duplicate cardinality per link; finalize FK/no-FK classification on all 54 rows. This is an in-progress evidence map, not gate completion. No schema changes executed.
