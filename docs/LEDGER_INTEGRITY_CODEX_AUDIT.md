# BlueMarketing Ledger Integrity CodeX Audit

Date: 2026-10-28  
Branch: `upgrade/laravel-framework`  
Primary target: `ledgers` (`App\Models\Ledger`, table `ledgers`)

This is a source-code audit only. No MySQL connection, migration, seed, repair, attribution, or ledger write was performed. Customer and financial records were not copied into this report.

## Scope and classification

The audit searched application PHP, routes, migrations, helpers, repositories, services, observers, jobs, commands, listeners, raw SQL, and tests for writes and destructive operations affecting `ledgers`. Related tables were reviewed as dependencies only.

- **ACTIVE**: reachable production application path.
- **BLOCKED**: source path exists but a fail-closed guard prevents execution.
- **LEGACY**: historical or compatibility path retained in source.
- **DEAD**: commented or unreachable code.
- **TEST ONLY**: isolated SQLite fixture or regression setup.

## Writer inventory

| Classification | File:line | Flow / route family | Ledger operation and type | Authorization / guard | Risk |
|---|---|---|---|---|---|
| ACTIVE | `JournalVoucherController.php:464` | Journal voucher store | One `JV` row per detail; `type_id=journal_vouchers.id` | Accounting create permission | Typed attribution is conditional; no physical source FK. |
| BLOCKED | `JournalVoucherController.php:561,636-658` | JV destroy/update | Deletes/recreates JV ledgers | Explicit HTTP 409 safety gates | Historical overwrite/delete remains prohibited. |
| ACTIVE | `BookingController.php:925,2448,2532,2560` | Booking sale, payment, transfer | `SV`, cash and paired rows | Booking permissions and project scope | `firstOrCreate` and reference checks lack database uniqueness. |
| ACTIVE | `LabourController.php:199` | Labour payment | `CP` linked to customer ledger | Labour payment permission | Uses `now()` instead of the business date. |
| ACTIVE | `LabourController.php:954,992` | Labour JV voucher | Two `JV` rows | Labour create-voucher route | Uses current date and does not call typed attribution writer. |
| ACTIVE | `ReportController.php:220` | Bank clearance | `BR` row after cheque pass | Pass-cheque permission | Idempotency reference is application-only. |
| ACTIVE | `Finance/VoucherController.php:936` | Pending bank clearance | `BR` row | Pass-cheque permission | Current-date fallback can misstate historical clearance. |
| ACTIVE | `StockController.php:1344,1508` | Stock entry/bill | Balanced `JV` legs | Authenticated admin routes | `JV` type is overloaded; two-row cardinality is not FK-enforced. |
| ACTIVE | `LedgerController.php:339` | Manual ledger store | Direct ledger create | Create-voucher permission | Request-derived type/reference requires strict source validation. |
| ACTIVE | `LedgerController.php:216` | Cash-out clearance | Ledger create | Transaction and voucher permission | Customer-ledger linkage is logical only. |
| ACTIVE | `Api/V1/Mobile/MobileStockController.php:1000` | Mobile stock | Stock ledger create | Mobile auth/permission | Same stock collision risks as web flow. |
| BLOCKED | `CommissionVoucherController.php` | Commission voucher | CV path protected by finance gates | Finance permissions and fail-closed gates | CV/JV ID collisions remain unresolved. |
| DEAD | `BookingController.php:1434-1626` | Older booking accounting | Commented ledger writes/deletes | Unreachable | Preserve for provenance; do not reactivate. |
| TEST ONLY | `tests/Feature/**`, `tests/Unit/**` | SQLite fixtures | Synthetic ledger mutations | Test process only | Does not prove MySQL constraints or production behavior. |

Related writers include `CustomerLedger`, `JournalVoucher`, `JournalVoucherDetail`, `LabourLedger`, `DraftLedger`, stock entities and pending-update approvals. They are dependencies, not additional primary `ledgers` targets.

## Source-to-ledger matrix

| Type / flow | Source header/detail | Identity fields | Cardinality | Main ambiguity |
|---|---|---|---|---|
| JV journal | `journal_vouchers` + `journal_voucher_details` | `type=JV`, `type_id`, reference, account | One row per detail | `JV` is also used by labour and stock; no FK. |
| CV commission | `commision_vouchers` + details | Intended CV type/id | One or more detail rows | No polymorphic FK can prove ownership. |
| SV booking sale | Booking/payment voucher + customer ledger | Type/id, customer ledger, reference | Usually paired rows | Application-only idempotency. |
| CR/CP cash | Customer ledger | Type/id, customer ledger | One or more rows | Per-type counters and no FK. |
| BR clearance | Customer ledger/check history | Type, customer ledger, `BANK_CLEARANCE#id` | At most one intended | Reference uniqueness is not database-enforced. |
| Labour | Attendance/payment and optional JV | CP or JV, customer ledger/JV | One CP or two JV rows | Current-date posting and missing typed attribution. |
| Stock | Entry/bill and lines | JV type, generated id, reference | Two balanced legs | Overloaded JV type and race-prone duplicate checks. |

`type_id` is a shared integer namespace. Matching it alone never proves ownership. Real foreign keys should be source-specific or represented by a typed attribution table with separate validated source relationships.

## Voucher-number and date risks

- JV headers are project-scoped, while ledger rows call `getVocuherNumber('JV')`; equality is not guaranteed.
- SV rows copy the JV header number; BR/CP/CR and stock use generated presentation numbers.
- Legacy exports contain numeric voucher columns while Laravel comparisons are string/presentation-oriented. Numeric equality is a candidate match only.
- JV rows use the validated source date; labour payment and labour JV paths use `now()`/`Carbon::now()`; bank clearance can fall back to today; stock uses entry/bill dates.
- `created_at` and `updated_at` are audit timestamps, never substitutes for business dates.

## Delete, overwrite and collision hazards

- JV hard-delete/recreate code is currently blocked with HTTP 409 and must remain prohibited.
- `is_active=0` is business deactivation, not immutable financial history.
- `Ledger` has no `SoftDeletes`; blanket SoftDeletes would not provide reversal semantics.
- Unscoped numbering/lookups and overloaded `type_id` permit source collisions.
- No physical FK guarantees a source header, detail or customer ledger still exists.
- Rollback/drop paths for financial tables remain prohibited.

## Read-only reconciliation design

A future project-scoped reconciler must be SELECT-only, aggregate-first and fail closed. It should compare source existence, project, active/deleted status, voucher number, business date, account, debit/credit and linked customer ledger, returning:

- **PROVEN**: source-specific evidence/FK, same project, matching account/date/amount, no conflict.
- **AMBIGUOUS**: numeric reference-only match, duplicate candidate, overloaded type, or missing discriminator.
- **UNKNOWN**: required source schema/evidence unavailable.
- **MISSING/ORPHAN**, **INACTIVE**, and **CONFLICT** for their respective conditions.

Every result must include scanned count, completeness, truncation, category arithmetic, identity/project gates and anonymized samples. A bounded sample is never a complete count. No repair, attribution, delete or backfill is authorized.

## Safe design and dry-run repair plan

Keep destructive mutations blocked. Add append-only reversals with independent approval and original-row hashes. Add source-specific attribution FKs only after disposable-clone compatibility evidence. Add uniqueness only after duplicate inventory and an approved deduplication plan. Treat legacy identity fields as read-only. Run a SELECT-only reconciler, obtain independent finance review, capture before/after checksums, and apply any later repair only through a separately approved reversible command.

## Tests and limitations

Existing isolated SQLite coverage exercises JV, CV, SV, CR, CP, BR, PPR, booking, labour, stock and finance safety gates. It does not prove MySQL FK/index behavior, concurrent numbering, triggers, stored routines, MariaDB-to-MySQL compatibility or historical provenance. No real database was accessed.

## Release blockers

- Historical finance ownership unresolved.
- Polymorphic `type_id` and overloaded `JV` semantics unsafe for automatic attribution.
- Voucher-number uniqueness and numeric/string drift.
- Labour and bank-clearance date propagation risks.
- Missing source FKs/project constraints.
- Migration-history drift, structural incompatibilities and UNKNOWN DDL provenance.
- Destructive rollback and hard-delete paths prohibited.
- No approved secured real-data staging clone.

## Audit limitations

This is complete for repository-visible code paths. Runtime-generated SQL, database triggers, stored routines, queue workers outside the repository and administrator grants were not observable. Unsupported provenance remains UNKNOWN.

## Independent deep audit: approval updates and synchronization

### MobileApprovalController direct ledger updates

`app/Http/Controllers/Api/V1/Mobile/MobileApprovalController.php:397-455` applies approved pending values with `DB::table($table)->where('id', ...)->update($safeValues)`. The table name is constrained to `ledgers`, `draft_ledgers`, `customer_ledger`, or `leads` by `assertAllowedApprovalTable()` at lines 230-239. Non-privileged users are project-scoped through `resolveApprovalProjectId()` and `scopeAccessibleApprovals()` at lines 287-320; privileged `direct-update`, `super-admin`, and `superadmin` users bypass that scope. The ledger project is resolved through `project_head_subheads.project_id` at lines 374-382.

Before the Phase 1 hardening, the generic `getSafeUpdateValues()` path allowed arbitrary non-sensitive ledger keys to reach the query builder. The implementation now calls `assertSafeLedgerApprovalValues()` and fails closed when a ledger approval contains amount, date, activity, reference, status, deletion, source, account, project or customer-ledger identity fields. The ledger-specific allowlist returns only `detail`; the query-builder update still bypasses model events, so no financial identity mutation is accepted through this path. Customer-ledger synchronization therefore occurs only for the permitted detail change.

| Finding | Evidence | Classification | Test coverage / gap |
|---|---|---|---|
| Table name is allowlisted | `MobileApprovalController.php:230-239` | VERIFIED control | Finance approval integration tests cover allowed table names. |
| Project scope derives from ledger account project | `:287-320,374-382` | VERIFIED for non-privileged path | Tests cover cross-project approval rejection; no MySQL FK proof. |
| Privileged users bypass project scope | `:269-273,280-284` | VERIFIED behavior / RISK | Requires independent approval policy; no per-project audit assertion for privileged users. |
| Direct query-builder update bypasses Eloquent events | `:442` | VERIFIED risk | No test proves event/observer parity because `Ledger` has no observed mutation contract. |
| Ledger field allowlist is now table-specific | `:437-440,502-527` | VERIFIED control | SQLite regression coverage rejects protected identity and financial fields and permits only detail. |
| Query-builder update bypasses model events | `:442` | VERIFIED residual risk | The remaining permitted detail-only path still requires future observer/audit-contract review. |
| Approval request cannot change ledger activity or identity | `:530-538` | VERIFIED control | SQLite regression coverage rejects `is_active`, source, project, reference, voucher and customer-ledger fields. |

### LedgerController amount/date/account and customer-ledger paths

`app/Http/Controllers/LedgerController.php:33-370` creates cash voucher/customer-ledger and ledger rows in a transaction. It validates amount, account, subaccount, date, payment type and project account selection before creating both records. The ledger stores `customer_ledger_id`, generated voucher number, type, per-type ID, account, amounts, reference, date, active flag and creator.

`LedgerController.php:955-1120` updates an existing ledger after validating `id`, amount, detail, account, subaccount, date, payment type and optional clearance fields. It resolves the row through `where('id', ...)->whereHas('projectHeadSubhead', project scope)` and updates amount, account, reference, date, status and activity state through the model path. It also updates the linked `CustomerLedger` fields for amount/date/reference/status/clearance. This is a live mutation path requiring the route's update-voucher permission and independent review of whether historical rows may be changed. Pending-update branches record old/new values for approval when the user lacks direct-update privilege.

The controller's synchronization is not a source-specific FK. It can preserve matching amounts/date in normal paths, but a changed account or project-head-subhead does not establish a corresponding source ownership record. No database FK prevents reassignment or orphaning.

### Source-family voucher/date consistency matrix

| Source family | Voucher number source | Ledger business date | JV header present? | Classification |
|---|---|---|---|---|
| Journal Voucher | Newly created `journal_vouchers.voucher_number` copied to every ledger leg | Validated JV date | Yes | **VERIFIED for the Phase 1 store path**; legacy rows remain subject to provenance review. |
| Labour JV | `getVocuherNumber('JV')` for ledger, `getLastJvVNumber()+1` for header | `Carbon::now()` | Yes, created in `LabourController.php:867-876` | **VERIFIED mismatch risk**: separate number generators and current-date posting. |
| Stock entry/bill | `getVocuherNumber('JV')` | Entry/bill date | No journal header created by stock writer | **VERIFIED**: `JV` ledger without journal header; source is stock entry/bill. |
| Sales voucher | Header voucher number copied to ledger | Booking payload date | Journal voucher header | **RISK**: no physical uniqueness or source FK. |
| CR/CP cash | Generated per-type number with collision loop | Request date | No JV header | **VERIFIED** non-JV source family. |
| BR clearance | Generated BR number | Bank posting date or current-date fallback | No JV header | **RISK**: fallback can alter historical date; idempotency is application-only. |
| PPR/booking | Customer-ledger/payment references | Source/payment date | No universal header | **AMBIGUOUS** where only type/reference remains. |
| Mobile stock | Mirrors stock writer | Entry request date | No JV header | **VERIFIED** same JV-overload risk as web stock. |

### Source identity, FK, index and project/account consistency

- `ledgers.project_head_subheads_id` is the only project-bearing ledger key and is used for project scope, but the migration does not define a physical FK to `project_head_subheads`.
- `customer_ledger_id` is logically linked through the model and controller, but no physical FK is defined in the ledger migration.
- `type_id` has no FK because it is polymorphic in practice; it cannot safely reference JV, CV, stock, labour or booking sources without a discriminator and source-specific constraint.
- The repository migration defines no unique index for `(project_head_subheads_id, type, voucher_number)`, `(type, type_id, reference)`, or source-specific cardinality. Application locks and existence checks are not equivalent to uniqueness under all concurrency modes.
- Account consistency is checked in selected controllers by resolving a project head/subhead. MobileApprovalController now rejects ledger account/project/source identity changes and permits only detail edits; the remaining query-builder path still bypasses model events for that permitted field.
- Stock and labour use `type='JV'` without a `journal_vouchers` header in stock, and labour creates a header with independent number/date behavior. Any reconciler must classify these rows as source-family-specific, never as journal vouchers solely from `type='JV'`.

## Read-only reconciliation SQL design

The following is a design for a future aggregate-only query; it is not executed here. It must run only against an explicitly verified read-only target and must reject missing schema. It returns counts and anonymized IDs, never names, phones, email addresses, descriptions, amounts or raw voucher rows.

```sql
-- Identity gate must be executed separately and pass first.
SELECT
  p.project_id,
  l.type,
  COUNT(*) AS scanned_count,
  SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) AS missing_account,
  SUM(CASE WHEN cl.id IS NULL AND l.customer_ledger_id IS NOT NULL THEN 1 ELSE 0 END) AS orphan_customer_ledger,
  SUM(CASE WHEN jv.id IS NULL AND l.type = 'JV' THEN 1 ELSE 0 END) AS no_journal_header_candidate,
  SUM(CASE WHEN a.ledger_id IS NULL THEN 1 ELSE 0 END) AS no_typed_attribution,
  SUM(CASE WHEN l.is_active = 0 THEN 1 ELSE 0 END) AS inactive_rows,
  COUNT(DISTINCT CONCAT(l.type, ':', COALESCE(l.type_id, 'NULL'), ':', COALESCE(l.reference, 'NULL'))) AS distinct_identity_keys
FROM ledgers AS l
LEFT JOIN project_head_subheads AS p ON p.id = l.project_head_subheads_id
LEFT JOIN customer_ledger AS cl ON cl.id = l.customer_ledger_id
LEFT JOIN journal_vouchers AS jv
  ON jv.id = l.type_id AND l.type = 'JV' AND jv.project_id = p.project_id
LEFT JOIN ledger_source_attributions AS a ON a.ledger_id = l.id
WHERE p.project_id IN (1,2,3,4,5,6)
GROUP BY p.project_id, l.type;
```

This query is intentionally incomplete for production use because the physical `ledgers.project_id` and `ledger_source_attributions` schema may not exist. A final reconciler must derive project through `project_head_subheads`, handle stock/labour JV subtypes explicitly, validate category arithmetic, expose `complete`/`truncated` flags, and return `UNKNOWN` when source tables or constraints are unavailable. Matching IDs or voucher numbers alone remain `AMBIGUOUS`.

## Regression requirements

Before any future mutation-policy change, add isolated SQLite tests that:

1. Reject a pending ledger approval attempting to change `type`, `type_id`, `voucher_number`, `reference`, `project_head_subheads_id`, `customer_ledger_id`, amounts, date or activity.
2. Define and test any future approved synchronization policy for amount/date/detail/activity fields before reopening them.
3. Verify model events/observers cannot be bypassed by approval updates, or explicitly document the approved query-builder exception.
4. Reject cross-project ledger approval for non-privileged users and require an explicit audit trail for privileged approvals.
5. Distinguish stock/labour `JV` rows from journal-voucher headers in reconciliation fixtures.
6. Verify source date and voucher-number equality rules per family without certifying legacy ownership.

The Phase 1 implementation intentionally changes two guarded behaviors: journal ledger legs now inherit the created header voucher number, and mobile ledger approvals fail closed for all financial/source identity fields. No historical data or live database behavior was changed.

## Phase 1 implementation (isolated SQLite only)

The following narrowly scoped controls were implemented after the source audit:

- `JournalVoucherController::store()` now copies the newly created journal header's `voucher_number` to every ledger leg. The existing validated business date remains the ledger date, and the existing transaction wrapper still covers the header, details, and all ledger rows.
- `MobileApprovalController` now fails closed for ledger approval payloads containing identity, source, project, status, activity, amount, date, reference, or deletion fields. Ledger approval updates are limited to `detail`; the project resolver and non-privileged project scope remain enforced. Query-builder updates therefore cannot silently reassign a ledger or bypass model-level financial identity rules.
- Journal voucher and commission voucher destructive endpoints remain explicit HTTP 409 safety gates. `LedgerController::destroy()` deactivates through the existing approval path rather than hard-deleting a ledger; no blanket `SoftDeletes` behavior was introduced.

Regression coverage was added in isolated SQLite tests. The posting integration test verifies that all journal legs use the header voucher number and exact source date. `MobileLedgerApprovalIntegrityTest` verifies rejection of each protected ledger field, the detail-only allowlist, and project resolution through the ledger account. Focused execution passed 57 tests with 214 assertions. No MySQL, staging, production, migration, rollback, or historical repair was performed.

The remaining limitation is that SQLite tests do not prove MySQL concurrency, grants, triggers, stored routines, or historical provenance. Cross-project approval behavior remains dependent on the existing authorization and project-scope guards and requires a separately authorized integration exercise before any real-data use.

## 2026-10-28 implementation and verification continuation

- Phase 1 was committed and locally verified: mobile ledger approval mutability restricted to detail, and fresh Journal Voucher ledger legs use the persisted header voucher number.
- A separate project-ownership guard now applies to `ledgers` approval actions even for privileged approval users, and is checked again within approve/reject transactions. The user's focused local run passed **17 tests / 73 assertions**.
- Labour Journal Voucher posting now copies both voucher number and business date from the persisted header; a database transaction surrounds header, details, ledger legs and attendance-status update. The user verified PHP syntax and **417 Feature tests / 1,860 assertions** before the stock changes.
- Stock web entry/bill and mobile stock writers now call `StockLedgerPostingGuard` before treating a duplicate reference as already posted. Existing postings with partial, excess, inactive, cross-project, wrong-account, amount/date or inconsistent identity legs fail closed. This is *not* a historical repair and does not establish a source FK.
- User's post-stock full Feature run: **422 passed, 1 failed / 1,865 assertions**. The single failure was test infrastructure, not a failed financial guard assertion: PHPUnit did not register the old docblock `@dataProvider conflicts`, causing `ArgumentCountError` with zero supplied arguments. Replaced it with `#[\\PHPUnit\\Framework\\Attributes\\DataProvider('conflicts')]` in `StockLedgerPostingGuardTest.php` (commit `794fc543`). **Post-fix tests still require local execution**; do not report the full suite as passing until verified.
- No production/staging/clone database write, migration, source attribution/backfill or financial record repair was performed. Historical JV ownership, legacy voucher dates/numbers, stock source-specific FK design, concurrency uniqueness and deletion policy remain blocked.

### Next test gates

1. Run isolated `StockLedgerPostingGuardTest` to confirm the six conflict datasets execute under the installed PHPUnit version.
2. Run full isolated Feature suite; capture final test and assertion count.
3. Inspect source/ledger linkage and business date for every stock and labour writer; retain provenance ambiguity instead of inferring a foreign key.
4. Require a separately approved disposable MySQL rehearsal before source-FK DDL, cleanup, reversible recovery or release.
