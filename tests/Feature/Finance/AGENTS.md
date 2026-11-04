
## TOKEN EFFICIENCY RULES

- Read only files relevant to the current task.
- Use targeted filename and symbol searches.
- Never scan the entire repository by default.
- Read relevant sections of source-of-truth documents.
- Avoid repeating file reads unless files have changed.
- Make small, focused changes.
- Keep responses concise.
- Do not generate unnecessary reports or documentation.
- Run focused tests first; expand when necessary.
- Do not repeatedly run failing commands without diagnosis.
- Preserve existing architecture and coding conventions.

## NON-NEGOTIABLE SAFETY

- Never trust client-supplied financial totals or tenant IDs.
- Preserve tenant isolation, RBAC, and ownership checks.
- Use ULIDs in public APIs.
- Recompute financial amounts server-side.
- Preserve posted financial record immutability.
- Preserve append-only stock movements.
- Never skip essential security or financial tests.

## TASK COMPLETION

- Summarize changed files.
- Report tests performed and their results.
- Mention blockers and unverified behavior.
- Avoid lengthy explanations unless requested.
