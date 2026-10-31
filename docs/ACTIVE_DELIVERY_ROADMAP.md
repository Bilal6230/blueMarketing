# Active delivery roadmap — 2026-10-31

The owner explicitly authorized simplifying the roadmap to finish useful work sooner. This file supersedes the six-phase/24-gate structure for routine progress reports. Old gates and evidence remain archived; changing the denominator is not newly completed engineering or production acceptance.

Use four workstreams and twelve concrete deliverables. Percentages count checked deliverables, **not effort, time remaining, or production readiness**. Do not count new reports, repeated inventories, test totals, or documentation as additional completed deliverables. Supporting audits are embedded in implementation, not separate development milestones.

| ID | Deliverable | Acceptance evidence/status |
|---|---|---|
| A1 | Business records archive rather than hard-delete; force-delete blocked | COMPLETE in code + SQLite contract tests; real schema rollout is C2 |
| A2 | Booking/JV/CV edit flows retain replaced source/detail rows and correct active totals | COMPLETE in code + workflow/regression tests |
| A3 | Web/mobile CRM and access-control assignments archive on detach, safely reattach, revoke access and roll back failed replacement | COMPLETE in code; assignment/controller/cache/FK rollback tests and full suite PASS |
| B1 | Prevent cross-voucher ledger deletion and model-level deletion of general/customer/labour financial history | COMPLETE in code; collision guards + 3-model deletion contract |
| B2 | Atomic posting, exact typed source links, duplicate/replay protection and immutable reversal in isolated engine | COMPLETE within existing isolated-engine scope; existing accepted evidence and regression suite |
| B3 | Original posting/approval workflows across web/mobile, booking, stock, labour and reports integrated and accepted | IN PROGRESS: JV/CV and stock integration plus original cash creation, audited bank clearance, payroll, attendance JV, PPR/EC/ECP source custody, original forms, ledger/group/cash reports and source-safe print implemented. Remaining booking/SV, lifecycle/report/export paths and browser/mobile UAT prevent acceptance. See REMAINING_WORKFLOWS_IMPLEMENTATION_2026_10_31.md |
| C1 | Isolated typed V2 schema and explicit source-domain design | COMPLETE within existing accepted schema scope; not certification of owner database |
| C2 | Rehearse complete relationship/FK/archive migration and concurrency against isolated MySQL; safe activation plan | OPEN; new archive migrations have not been applied to owner DB |
| C3 | Diagnose missing historical JV rows, verify source ownership, historical UTC/full-row/financial parity | OPEN; no guessed attribution or fabricated recovery |
| D1 | Full automated application regression and focused security/rollback tests pass on latest batch | COMPLETE: 847 tests / 3,912 assertions PASS, SQLite in-memory; not MySQL/UAT evidence |
| D2 | Browser/mobile/report/security/performance UAT on isolated representative data | OPEN |
| D3 | Backup restore rehearsal, approved financial reconciliation, cutover/rollback and separately authorized release | OPEN |

## Scope retained from the old gates

Latest checkpoint: cash/bank, payroll/attendance, booking PPR/EC/ECP and original typed ledger/cash report implementation is delivered. Full regression **847 tests / 3,912 assertions PASS** (84.142 seconds); **122 PHP entries lint PASS**, JavaScript exact-money/allocation checks PASS and diff whitespace check PASS. See [remaining workflow checkpoint](REMAINING_WORKFLOWS_IMPLEMENTATION_2026_10_31.md). B3 acceptance remains open; the implementation expanded without fabricating completion of untested browser/MySQL/historical paths.

Current checklist: **7/12 engineering deliverables complete (58.3%)**. A: 3/3 (100%); B: 2/3 (66.7%); C: 1/3 (33.3%); D: 1/3 (33.3%). This is a rebased checklist, not a claim that old 24-gate acceptance increased. Latest full regression completed in 104.200 seconds. All 79 changed/new PHP file entries lint PASS; diff whitespace check PASS. Initial broader assignment run exposed three unmigrated synthetic fixture errors; updated fixture archive columns, then 89/89 related tests and final full suite passed.

- Old 01/02/03 audit and call-graph requirements support A/B/C code reviews and C2/D2; repeated static indexes are no longer standalone progress items.
- Old 04/08/13/15/16/21/22 source ownership, backup, column policies, UTC/full-row and financial reconciliation remain C3/D3 requirements.
- Old 05/06/09/10/11/12/14/17 evidence supports B2/C1; archive changes and unverified constraints still require C2.
- Old 07 record lifecycle policy is implemented through A and B1; financial reversal requirements remain B2/B3/D3.
- Old 18/19/20 original-controller, cross-module, web/mobile/report/approval integration remains explicitly B3; it is not discarded to raise progress.
- Old 23/24 UAT/restore/release remain D2/D3.

Optional restore UI and new retention-management features are deferred unless required by UAT. Model restore capability is already tested; do not expand product scope into a new admin module just to satisfy the roadmap.

## Current engineering batch

Assignment archival covers `lead_user`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. Migration adds nullable `deleted_at` without dropping rows or composite keys. The relation layer soft-detaches, filters active relationships/eager/existence queries, reactivates original rows with original creation time, and serializes sync on the parent row. User role/direct-permission sync and role-permission sync are transactional. Missing archive columns cause replacement/detach to fail closed; no fallback to hard delete. Initial insertion and legacy reads remain compatible before migration.

Web lead approval now uses the relation layer. Mobile lead approval previously overwrote all assignment user IDs and could not create an assignment for an unassigned lead; it now archives old assignments and synchronizes the requested active user. This branch is covered through the actual controller's transactional apply method, not claimed as a complete HTTP authorization/UAT proof.

General, customer and labour ledger models now reject instance delete, bulk delete, destroy and force-delete. Posted financial rows cannot be hidden through generic soft deletion; corrections require an auditable reversal. Raw SQL, external database clients, unreviewed package operations and DB triggers are outside these model guards and remain C2/D2 review work.

Both archive migrations remain unapplied to owner databases. No .env changes or owner data mutations. Next functional task after this batch: B3 original posting/approval workflow integration, starting from a complete source-kind/controller matrix and its existing tests; use existing inventory rather than creating another inventory milestone. C2 rehearsal can be prepared independently; request fresh target identity/authorization only when the exact guarded workflow is ready.

Owner action: NONE for current independent coding. Database activation/UAT/release will require explicit coordination when ready.
