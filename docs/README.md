# BlueMarketing Engineering Handover

**Last updated:** 2026-10-26  
**Working branch:** `upgrade/laravel-framework`  
**Production branch:** `master_live` — do not modify, merge or deploy without explicit approval.

## Start here (new chats and new developers)
Read in order:
1. [PROJECT_STATUS.md](PROJECT_STATUS.md) — verified state, environment differences, next actions.
2. [DATABASE_ARCHITECTURE_PLAN.md](DATABASE_ARCHITECTURE_PLAN.md) — database restructuring, financial integrity, foreign keys, soft delete policy, migration strategy.
3. [SECURITY_ACTIVITY_PLAN.md](SECURITY_ACTIVITY_PLAN.md) — audit trail, device registration, adaptive OTP.
4. [AUDIT_WORKLOG.md](AUDIT_WORKLOG.md) — findings, evidence, unanswered questions, decisions and changes.
5. [Historical upgrade audit](../LARAVEL_13_UPGRADE_AUDIT.md) — historical planning document (some version/status information is outdated).

## Non-negotiable guardrails
- Never modify `master_live` without explicit approval. No new branches without permission.
- Never run migrations, `migrate:fresh`, rollbacks, seeding, import, bulk update/delete or production deploy without explicit authorization and verified backups.
- Do not delete/reset/stash-pop uncommitted user work or financial data.
- Analyze provided dumps **read-only**. Never commit SQL dumps, secrets, credentials or personal data.
- Prefer additive, reviewed database changes for existing installations. Clean baseline migrations and in-place upgrades have different requirements.
- Report what was *verified*, what is a *proposal*, and what still needs testing.

## Maintenance protocol
At each material milestone update the relevant documentation in the existing working branch: date, decision, implementation, tests, risks, next action, commit link. Documentation is the handoff source, not proof that unexecuted changes exist. When beginning a new chat, read these files first, then check live Git state before any code edits.
