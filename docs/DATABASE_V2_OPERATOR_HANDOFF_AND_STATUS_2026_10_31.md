# BlueMarketing V2 — 24-gate status and mandatory owner handoff protocol

Date: 2026-10-31. Branch: `upgrade/laravel-framework`.

## NON-NEGOTIABLE RULE — OWNER ACTION MUST BE FLAGGED IMMEDIATELY

Whenever any next step, test, approval, environment access, manual UAT, source-data evidence, or acceptance depends on the owner, the FIRST part of the next user-facing report MUST be a prominent heading:

# SIR, AAPKI TARAF SE KAAM PENDING HAI

Specify: (1) exactly what the owner must do, (2) safe and precise step-by-step instructions, (3) expected success output/screenshots, (4) which gate this blocks, (5) what the assistant can continue independently in parallel, and (6) forbidden targets (production/live/staging/real source database if relevant).

Do not wait until several turns later. Do not ask the owner to repeat a check already evidenced by screenshots/logs. If NO owner action is pending, explicitly say `Owner action: NONE at this checkpoint`. Never imply that a needed manual decision or approval has been performed. Do not confuse missing engineering implementation with an owner blocker. Never mark unrun CI as PASS; never claim a background task.

## Acceptance scope and current formal count

**10/24 = 41.7%** accepted, subject to the following explicit scope statement for Gate 12: the isolated V2 historical read-model domain compatibility contract (22 adapters, 148 casts, 38 relations and classified 9 V1-only differences) is verified by user-supplied screenshot of successful disposable MySQL 8.4 and finance-safety jobs showing `V2_MODEL_DISPOSITION_PASS`. **This does not certify all original V1 CRUD models running unmodified against V2.** Any future scope expansion needs a separate acceptance gate; do not silently broaden this proof.

Previous 9 accepted gates remain: 01, 02, 05, 06, 09, 10, 11, 14, 17.

| # | Gate | Status | Remaining work |
|---|---|---|---|
| 01 | Original DB snapshot/audit | COMPLETED | — |
| 02 | Source relationship dictionary | COMPLETED | — |
| 03 | Full CRUD/ledger writer call graph | PARTIAL | Dynamic/indirect writes, views, jobs, commands certification |
| 04 | Historical source ownership mapping | PARTIAL | Ambiguous IDs and owner decisions |
| 05 | Normalized V2 schema | COMPLETED | — |
| 06 | Typed financial posting design | COMPLETED | — |
| 07 | Void/delete/reversal policy | PARTIAL | Original-module lifecycle approvals |
| 08 | V1-to-V2 column policy | PARTIAL | Exception and mapping sign-off |
| 09 | Isolated V2 migrations | COMPLETED | — |
| 10 | Disposable MySQL migration | COMPLETED | — |
| 11 | FK/index/rollback verification | COMPLETED | — |
| 12 | Isolated V2 model compatibility | COMPLETED (SCOPED) | Original V1 CRUD portability NOT included |
| 13 | Backup and exception approvals | PARTIAL | Owner immutable backup acceptance |
| 14 | Dependency-planned historical import | COMPLETED | — |
| 15 | UTC-correct historical import | PENDING | Fresh authorized isolated target; source UTC evidence |
| 16 | Full historical reconciliation | PENDING | 120,165-row parity, financial identities |
| 17 | Central isolated V2 posting engine | COMPLETED | — |
| 18 | Original voucher controllers and approvals | IN PROGRESS | Real controller integration, approval semantics, tests |
| 19 | Booking/stock/labour/reports | PARTIAL | Full web/mobile/report workflow implementation |
| 20 | Full cross-module integration | PARTIAL | Exhaustive routes, concurrency and user acceptance |
| 21 | V1/V2 record parity | PENDING | Independent original-row match |
| 22 | Independent financial reconciliation | PENDING | Cash/bank/ledger + historical JV attribution |
| 23 | Browser/mobile/security/performance UAT | PENDING | Operator acceptance |
| 24 | Backup/rollback/production cutover | PENDING | Restore rehearsal and explicit authorization |

Phase accepted bars: P1 2/4=50%; P2 2/4=50%; P3 4/4=100% **within Gate 12 scoped boundary**; P4 1/4=25%; P5 1/4=25%; P6 0/4=0%.

Counts: completed 10; partial 7; in progress 1; pending 6. Do not treat partial/in-progress as accepted.

## Immediate development focus

**IN PROGRESS: Gate 18.** Real original voucher/approval controller integration must be implemented and regression-tested; preliminary 56 verb-based route candidates = 33 write, 8 approvals, 15 POST-read candidates, not 56 confirmed DB writers. Production V1 and ledger paths must NOT be swapped over before historical import parity, approvals, and controlled cutover.

**Next engineering focus: Gates 19 and 20** after overlapping controller integration makes them actionable. Continue Gate 03 writer tracing concurrently where relevant.

## Owner handoffs (not currently blocking independent coding)

For fresh historical import Gate 15: ask for explicit permission and fresh disposable database name/address (never assume approval); protect previously affected local rehearsal. For original-data parity Gate 16/21/22: request signed source backup/fingerprint evidence and the user's safe local comparison outputs only when the corresponding verified tool workflow is ready. For UAT Gate 23 and deployment Gate 24: request detailed scenario sign-off and separate production authorization only when independently ready. Never run owner DB mutation preemptively.

## Reporting rules

Every major checkpoint must show: formal accepted/24, six phase progress bars, all 24 gates with Completed / Partial / In Progress / Pending, new code/commit, actual tests and CI run evidence, next engineering focus, and a prominent owner-action banner if necessary.

## 2026-10-31 — Phase 5 source inspection, user verification dependency

Original controller source inspection confirms no `V2PostingGateway` invocation in JournalVoucherController, CommissionVoucherController, Finance/VoucherController, BookingController, StockController or LabourController. Source code does not prove a full V2 controller cutover. CommissionVoucherController::store/update/destroy intentionally return HTTP 409; unsafe approval-free enabling is forbidden. Historical record and accounting flow availability still requires an authorized clean source parity rehearsal and browser/API UAT on an isolated copy.

Controller change commit `a5283f00` corrects the CV edit form's voucher type. This isolated UI fix does **not** count as voucher-posting migration or acceptance.

### Owner action — explicit request when isolated integration/UAT is ready

The owner must authorize a **new disposable MySQL V2 test database** and a **redacted or safely controlled historical sample** (or suitable synthetic fixtures) for end-to-end tests, distinct from current V1, production, staging, client clone and damaged historical rehearsal. Request clear permission and fresh target identity before any data write; only run exact guarded commands after authorization. Owner must later execute supplied UAT checklist on that copy and approve workflow parity. This is not a reason to pause independent controller coding.


## MANDATORY DELIVERY POLICY — LARGE, COMPLETE MILESTONE BATCHES (OWNER DIRECTIVE, 2026-10-31)

**No more incremental micro-fix delivery loops.** When the owner assigns one or several milestones for completion in one turn, treat the ENTIRE ORIGINAL ACCEPTANCE SCOPE of EVERY assigned milestone as the required deliverable; do not quietly narrow scope, rebrand a partial fix as completion, or return after small code changes, reports, route inventories, database preflight, or status updates as though the assignment were done.

**Execution contract:**
1. Before coding, inspect all affected source controllers, business services, original CRUD/approval paths, routing, view/report/mobile consumers, database mapping, and regression expectations. Build a cohesive multi-module implementation batch, not a chain of superficial commits.
2. Implement the original requested functionality across all relevant real paths, including approval/idempotency/reversal, booking/stock/labour/reports and cross-module compatibility as applicable. Keep production and V1 data protected; no unsafe cutover or guessed financial source relationships.
3. Author and actually execute relevant unit, integration, MySQL, HTTP, rollback, concurrency, security, and workflow tests when the environment permits. Capture observed run IDs, outputs, failing cases, commit SHAs and exact acceptance evidence. Unrun tests are NEVER PASS.
4. **Do not return merely to say 'In Progress' or to ask for another routine check.** Continue independent engineering work within the current available execution. If owner action is a true prerequisite, IMMEDIATELY surface the prominent heading `SIR, AAPKI TARAF SE KAAM PENDING HAI`, with exact safe instructions and blocked milestone; work on all nonblocked tasks in parallel.
5. Milestones are **Completed** only after their original (not reduced) scope is implemented, automated regression actually PASSes, and required acceptance evidence exists. If one cannot genuinely finish, report exactly what remains, what was implemented/tested, and the real blocker; never claim completion, fabricate progress or promise asynchronous delivery. Incomplete delivery is an exception, not the planned work cadence.
6. A batch that explicitly names milestones 18, 19 and 20 is **not accepted** by isolated CSV exports, schema checks, safety scripts, documentation edits or route inventories alone. Acceptance requires real original voucher-controller workflow integration, booking/stock/labour/report integration, and end-to-end cross-module tests and business UAT as defined before the work.
7. **Do not force the owner to explain the same context in a new chat.** Maintain the latest exact checkpoint, original acceptance scope, open blockers, commits and proven test evidence in this repository handoff file. Persist decisions before context rollover.
8. Avoid user-visible filler and repetitive explanations; prioritize substantive code and verified completion. Do not sacrifice safety to reach a cosmetic 100%.

**Current evidence boundary at adoption:** `bluemarketing_v2_phase5_test` schema has 73 physical tables, 5 V2 migrations, 16 verified source/posting tables and 124 physical FK columns, as owner-tested. Those PASS markers verify only schema readiness. Milestones 18–20 remain unaccepted until original workflow integrations and functional regressions actually pass. No immediate owner task is pending for database setup.
