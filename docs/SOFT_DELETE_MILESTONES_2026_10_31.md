# Soft-delete implementation batch — 2026-10-31

User direction: preserve application records; eliminate hard deletion and use soft deletion. This directive authorizes implementation, not deployment or modification of owner finance databases.

## Delivered engineering milestones

| Milestone | Code and automated checks | Runtime rollout |
|---|---|---|
| SD1: Business master archival | Implemented: 13 additional model families, archive migration, restore/query scopes, instance and bulk force-delete refusal, role/permission model configuration and controller imports | Pending reviewed isolated MySQL migration |
| SD2: Edit-history preservation | Implemented: JV/CV draft detail replacement archives original IDs; booking schedule replacement uses model soft deletion; active schedule totals exclude archived rows | JV/CV use existing detail archive columns; booking needs archive migration |
| SD3: Financial account identity protection | Implemented: history-linked mappings cannot be deleted/reassigned; unused mappings archive; selected-project filtering on these actions; delete permission required | Archive migration needed for unused mapping deletion |

SD1 coverage: users, plots, labours, settings, dasticash, draft_ledgers, booking_details, stock_items, stock_parties, project_head_subheads, roles, permissions, hold_plots. Existing Booking, JV/CV header/detail, StockEntry and PersonalAccessToken soft-delete models also refuse force deletion. Historical belongs-to links retain archived creator/approver/plot/labour/account identities; authentication and active lists retain normal filtering.

`2026_10_31_000010_add_business_record_archives.php` only adds missing nullable archive columns to existing named tables. It never fills guessed source links, deletes data, or changes FK ownership. Re-running `up()` is tested. `down()` deliberately refuses loss of archive history. Schema-aware read scopes preserve pre-migration read compatibility; deletion without the archive column fails and never falls back to a physical delete. This means the migration must be rehearsed and applied before the affected delete/edit features can be used successfully on a database without those columns.

## Remaining work: do not claim application-wide zero hard deletes

1. Assignment pivots still have physical detach semantics through `syncRoles`, `syncPermissions`, and lead-user `sync` calls. These need a dedicated archive-aware relationship implementation, composite-key reattachment policy and permission-cache/security tests. A token scanner alone does not prove no deletes.
2. Raw readers/joins and package behavior need exhaustive verification on an isolated full-copy dataset. Retained history must not inflate active totals or disappear from historical reports. ArchiveScope currently inspects column metadata on scoped reads for staged rollout; performance testing and post-rollout simplification remain necessary.
3. All FK actions, DB triggers, external writers, raw builder access and database-user DELETE privileges need separate review. Model force-delete refusal does not prevent arbitrary raw SQL or external database access.
4. User-visible restore authorization/UI, retention policies, MySQL FK/concurrency tests, browser/mobile UAT and production rollout remain open.

Stock source deletion remains blocked for used item/party records. Posted financial ledger deletion remains blocked; balance corrections require explicit reversal, not hiding posted ledger lines with a generic soft delete. Lead deletion already uses `is_active=0`; no new physical lead deletion was introduced. Accounting head/subhead delete methods are currently empty, not certified working archive workflows.

## Verification and progress

Tests run only against forced SQLite `:memory:`. Earlier broad run after initial archive changes: 789 tests / 3,478 assertions PASS. Latest full suite: **790 tests / 3,481 assertions PASS** (71.217 seconds), including archived historical relations. Subsequent selected-project mapping protection and its additional regression: **7 tests / 39 assertions PASS**; full suite was not repeated after that final small guard. Archive contract suite: **17 / 145 PASS**. PHP lint passed for all 66 changed/new PHP file entries. Archive contract tests include FK-enabled retention of ledger creator identity; role/permission deletion retains assignment rows while removing grants. Booking workflow tests were updated to assert active schedule behavior and preserved archived rows instead of physical absence. The initial full run exposed missing archive columns in old synthetic booking fixtures; those fixtures now represent the new schema and assert archived retention.

Engineering progress for SD1/SD2/SD3 is independent of formal 24-gate acceptance and does not certify migration rollout. Formal acceptance stays 10/24 (41.7%): P1 50%, P2 50%, P3 100% within existing isolated model scope, P4 25%, P5 25%, P6 0%. Gates 18–20 remain open. No .env changes, owner database connection, real migration/import/repair, commit, push or deployment performed.

Next independent task: assignment-pivot soft deletion with safe detach/reattach and permission-cache regression. After that, prepare a guarded MySQL rehearsal and request a fresh disposable target identity/authorization only when ready. Never run blanket `migrate`, `migrate:fresh`, rollback, or repair against the existing source, production, staging, or preserved historical rehearsal.

Owner action: NONE for independent coding at this checkpoint. Activation on an owner database remains pending and must be explicitly coordinated after an immutable backup and isolated rehearsal.

## Superseding checkpoint — assignment milestone now delivered

The previously open assignment-pivot implementation is now delivered in code: archive-aware detach/reattach, atomic sync and active relationship filters across User/Lead/Role/Permission; web/mobile lead approval paths use this layer. General/customer/labour ledger deletion is also centrally refused. Final full suite 801 tests / 3560 assertions PASS; changed PHP lint PASS. Both archive migrations still require isolated MySQL rehearsal/rollout. External/raw/package/trigger behavior is not globally certified. Use docs/ACTIVE_DELIVERY_ROADMAP.md for current progress and next implementation; old six-phase bars are superseded by explicit owner direction.
