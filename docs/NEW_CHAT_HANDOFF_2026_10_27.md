# BlueMarketing — New Chat Handoff (2026-10-27)

## Repository and environment
- GitHub: Bilal6230/blueMarketing; active branch: upgrade/laravel-framework; last observed branch head before handoff: cde6447345d3a9da88c21dc2b4fb68aa759f8420.
- Developer Windows: C:\laragon\www\blueMarketing (PowerShell); PHP/Laravel 9. Live-hosted app.
- Never touch master_live, production finance records, migrations, actual live database or deployments without explicit backup, staging and approval.
- Run focused SQLite :memory: tests; cannot claim PHP tests ran without user output. Avoid full suite each small batch.

## Working agreement
- After each successful user test log, immediately implement next substantial code+test batch without requiring another request. Prefer meaningful features over tiny fixes. Update docs/MASTER_ROADMAP.md and docs/AUDIT_WORKLOG.md in the SAME functional commit. Do not make documentation-only development commits unless user explicitly requests documentation (this handoff is such an exception).
- Show visual progress bar with honest estimated percentages; never inflate for trivial tests.
- Safety: no destructive rollback of finance metadata migration, no automated historical ledger attribution, no existing module collateral changes.

## Latest verified local results
- User verified FinancialSourceMetadataIntegrationTest **52 passed / 145 assertions** after commit a59369a8054870a5a17988d46d06e2f00449303f.
- User also ran `php artisan finance:audit-legacy-evidence 6 12179`. Response: ledger 12179, project 6, legacy type_id 1310, original voucher_number 249, project account ID 428; no matching JV/CV numeric header; source_verified false, requires_external_evidence. This is unresolved provenance, not proof of accounting error.
- A newer commit `cde6447345d3a9da88c21dc2b4fb68aa759f8420` exists on the active branch: "feat: safe consolidated legacy evidence batch CLI, four tests and roadmap". It is **not yet verified in the user's terminal**. Inspect current branch code before changing anything, and ask only for focused test run after meaningful implementation.

## Real database read-only findings
- MySQL metadata preflight: both ledger_source_attributions and financial_posting_requests tables MISSING. Do not migrate without staging/backup approval.
- 6 projects, 2,873 legacy JV-type ledger rows whose typed origins are unverified: project 1=2, 2=0, 3=692, 4=0, 5=749, 6=1430.
- Project 6 full legacy classification: 1430/1430 scanned, jv_only 1420, neither_header 10, cv_only 0, both_headers 0; ALL UNVERIFIED.
- Ten project 6 neither-header IDs: 12179, 12180, 13047, 13048, 13049, 13050, 13051, 13052, 13053, 13054.
- Manual review cannot infer JV ownership from numeric type_id or a matching header.

## Implemented code
- Atomic JV header/detail/ledger posting, fixed-point money, project account checks, fail-closed half-installed schema guard.
- DurableFinancialRequest transaction/project locking, key+payload replay prevention, committed voucher existence/project and JV soft-delete checks.
- Conditionally server-issued UUID idempotency key in real JV Blade create form when both metadata tables exist; JS one-click confirmation guard.
- LedgerSourceAttributionWriter checked typed ledger/header ownership and writer uniqueness; LedgerVoucherDisplayService avoids misleading legacy numeric collisions.
- Read-only inspect/audit commands: finance:audit-jv, finance:audit-voucher-collisions, finance:audit-source-attributions, finance:audit-readiness, finance:metadata-preflight, finance:rollout-assessment, finance:audit-legacy-cases, finance:audit-legacy-evidence. New unverified batch evidence command is on latest branch; inspect before using.
- docs/MASTER_ROADMAP.md and docs/AUDIT_WORKLOG.md contain detailed history.

## Progress baseline (estimated; not deployment approval)
- Overall 25% (as last user-visible estimate), Phase 1 Audit & Financial Safety 85%; Phase 2 Posting & Reconciliation 25%; Phase 3 Security 0%; Phase 4 Modules 0%; Phase 5 Staging & Deployment 10%. Do not increase for documentation; next big code milestone should be substantial and tested.

## Next real milestone
- Build an actionable *evidence review and reconciliation workflow* with safe audit exports/checkpoints, or isolated staging-only MySQL concurrency verification; do not just add another trivial CLI listing.
- Reconcile sources only based on independent verified evidence and approvals, not numeric collisions.
- Backend/local verification focused tests; production schema remains absent and migration forbidden pending approval.

## GitHub workflow
- Use functions.exec tools.mcp__GitHub__fetch_file({repository_full_name:repo,path,ref:"upgrade/laravel-framework"}). Verify current branch head via GitHub fetch API, never assume history.
- Single code commit: create_tree(base_tree_sha=head, tree_elements), create_commit(parent_sha=head), update_ref(branch_name,sha,expected_sha=head).
- User prefers Roman Urdu and PowerShell commands, practical progress UI.

## Working agreement — large milestones by default (user reiterated 2026-10-27)
- Each successful focused test result must trigger the **largest safe cohesive multi-feature development batch** feasible, not one tiny CLI/report addition. Combine implementation, end-to-end workflow integration, edge cases and regression tests. Prioritize product impact and genuine phase exit criteria over inflating percentages or accumulating scripts.
- Don't require user to repeat this; avoid unnecessary follow-up questions. Every functional commit updates MASTER_ROADMAP and AUDIT_WORKLOG together. Single consolidated PowerShell section with only vital pull/lint/focused tests; no full suite except major shared refactor/release.
- Distinguish verified percentage from provisional; never claim project complete or increment solely because a response was sent.
