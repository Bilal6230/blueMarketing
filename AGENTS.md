```md
# BlueMarketing — Codex Agent Instructions

## 1. Project Context

- Project: BlueMarketing ERP
- Framework: Laravel / PHP / MySQL
- Active development branch: `upgrade/laravel-framework`
- Development environment: Windows, Laragon, PowerShell
- Project directory: `C:\laragon\www\blueMarketing`
- This is an existing ERP with finance, accounting, booking, employee, attendance, and reporting modules.
- Preserve existing business logic and established workflows unless explicitly requested.

## 2. Primary Execution Rules

- Focus strictly on the user's requested task.
- Prefer implementing a working solution over repeatedly investigating.
- Do not restart an investigation without new evidence.
- Do not repeatedly inspect the same unchanged files.
- For small tasks, begin with the most relevant files.
- Expand investigation only when the actual problem requires it.
- Avoid unnecessary explanations, progress messages, and repeated planning.
- Do not modify unrelated code.
- Do not replace functional code with placeholders or mock implementations.
- Never claim a task is completed without appropriate verification.
- If blocked, explain the exact reason and evidence rather than repeating the same steps.

## 3. Task Classification

### Small Tasks

Examples:
- Button behavior
- Dropdown issues
- Modal loading
- Form visibility
- CSS fixes
- Blade template fixes
- Minor JavaScript bugs

Execution:
1. Identify the directly relevant files.
2. Inspect the affected function, component, or template.
3. Trace the actual failure.
4. Apply the smallest working patch.
5. Run targeted verification.
6. Report the result.

Do not perform a complete project audit for a small UI issue.

### Medium Tasks

Examples:
- Controller fixes
- API integrations
- Form submission bugs
- Validation problems
- Module-level changes

Execution:
1. Inspect the affected module.
2. Trace related controllers, services, routes, and views.
3. Identify the root cause.
4. Implement changes without breaking existing behavior.
5. Run relevant tests.
6. Summarize completed work.

### Large Tasks

Examples:
- Laravel framework upgrades
- Finance V2 migration
- Ledger architecture changes
- Multiple milestone implementations
- Large database migrations

Execution:
1. Inspect existing architecture and dependencies.
2. Review relevant requirements and project documentation.
3. Plan implementation in logical milestones.
4. Implement the requested scope.
5. Run relevant automated checks.
6. Report completed, partial, pending, and blocked items.

Do not prematurely stop a large implementation merely because it involves multiple files.

## Frontend rule — jQuery only for application DOM and AJAX

- For all new or modified first-party frontend code, use jQuery `$(...)`, `.on(...)`, `.val()`, `.text()`, `.html()`, `.addClass()`, `.removeClass()`, `.toggleClass()`, and `$.ajax()` for DOM events, manipulation, and HTTP requests.
- Do not introduce raw `document.getElementById`, `document.querySelector`, `addEventListener`, `classList`, `innerHTML`, `createElement`, or native `fetch` in first-party Blade scripts and JS source.
- Plain JavaScript syntax for computation, arrays, objects, dates, and browser integrations without equivalent jQuery APIs (for example `postMessage`) may still be necessary. jQuery is a library built on JavaScript, not a different language.
- Do not rewrite vendor libraries, minified distributions, compiled bundles, bootstrap infrastructure, or third-party plugins. Edit their application-level integration instead.
- Preserve existing server-side validation, event behavior, modal lifecycle, accessibility, and finance posting safety; run regression and browser checks after each conversion batch.

## 4. Investigation and Performance

- Keep investigations proportional to task complexity.
- Prefer targeted searches using `rg` over opening entire large files.
- Avoid repeating searches that already returned sufficient results.
- Inspect only the necessary file ranges.
- Do not repeatedly run identical commands without a reason.
- Do not run full application test suites for minor changes.
- Do not repeatedly rebuild caches unless there is evidence of stale output.
- Avoid excessive context consumption.
- If context has been compacted, continue using confirmed findings rather than restarting from zero.
- For a small task, use a focused initial investigation budget of approximately 10 minutes. This is a guideline, not an automatic termination rule.
- If no progress is possible, stop the loop and provide a precise blocker report.

## 5. Existing Changes — Critical Safety

The working tree may contain important incomplete work from other milestones.

Before making changes:
- Run `git status --short`.
- Identify pre-existing staged, unstaged, and untracked files.
- Preserve all unrelated modifications.
- Do not overwrite another task's changes.
- Do not assume uncommitted files are disposable.
- Do not automatically stash changes.
- Do not reset or clean the working tree.

Never execute the following without explicit permission:
- `git reset --hard`
- `git clean -fd`
- `git restore .`
- Destructive database commands
- Force pushes
- Broad rollback commands

When editing an already-modified file, preserve unrelated edits.

## 6. Finance and Accounting Safety

BlueMarketing contains sensitive production accounting workflows.

Unless explicitly requested, do not modify:
- Ledger posting rules
- Voucher approval workflows
- Accounting balances
- Journal voucher relationships
- Transaction reversals
- Financial reconciliation logic
- V2 migration guards
- Financial database schemas

For finance-related tasks:
- Preserve debit/credit correctness.
- Preserve voucher identity and relationships.
- Respect existing approval and permission checks.
- Never bypass database readiness or V2 safety guards.
- Do not silently change transaction behavior.
- Avoid introducing duplicate ledger postings.
- Do not mutate production financial data during testing.
- Use disposable/test databases when database verification is necessary.

## 7. Database and Migration Safety

- Never run migrations against an unknown database.
- Confirm the configured database and environment before database modifications.
- Do not run `migrate:fresh`, `db:wipe`, `TRUNCATE`, or destructive SQL without explicit authorization.
- Do not modify production or staging data without explicit authorization.
- Prefer read-only queries for investigation.
- Preserve existing schema compatibility.

## 8. Laravel Development Standards

- Follow existing project architecture and naming conventions.
- Preserve routes, permissions, middleware, and authentication behavior.
- Prefer existing shared services over duplicate business logic.
- Keep controllers focused and changes minimal.
- Avoid unnecessary dependencies and package installations.
- Validate request input appropriately.
- Preserve existing Blade layouts and UI patterns.
- Respect legacy and V2 workflow boundaries.
- Do not introduce new architectural patterns for a small bug fix.

## 9. UI, Forms, and Modal Fixes

For a UI bug:
1. Verify the actual user interaction.
2. Trace the button event and related JavaScript.
3. Verify the correct route and response.
4. Check permission and rendering conditions.
5. Identify frontend or backend errors.
6. Apply the smallest correct fix.
7. Verify the successful behavior and failure handling.

For iframe/AJAX modals:
- Do not assume a timeout or hidden spinner fixes the root cause.
- Confirm the form actually renders.
- Ensure the loading indicator terminates correctly.
- Display a meaningful error when the form cannot load.
- Preserve security and authorization checks.

## 10. Testing and Verification

Match tests to the change.

For Blade/UI changes:
- Verify syntax.
- Run relevant view compilation when appropriate.
- Check relevant routes and JavaScript behavior.
- Perform browser verification if browser access is available.

For backend changes:
- Run targeted PHP syntax checks where useful.
- Run directly related feature or unit tests.

For financial changes:
- Include relevant regression checks.
- Never use production data for destructive tests.

Do not claim browser verification when only source inspection or syntax checks were performed.

## 11. Git and Delivery Rules

- Work on the current intended branch.
- Do not switch branches without a clear reason or instruction.
- Do not automatically commit, push, merge, rebase, or force push unless requested.
- Preserve staged and unstaged changes.
- Use `git diff --check` to detect whitespace errors.
- Report any unverified changes or failed tests.
- When requested, provide the correct Git pull command.

## 12. Communication Rules

- Respond in concise Roman Urdu unless the user requests another language.
- Avoid repetitive progress narration.
- Do not repeatedly promise that the same issue is being fixed.
- State concrete findings and changes.
- Distinguish verified results from assumptions.
- Do not claim success without evidence.
- Ask questions only when essential information is unavailable.
- Do not waste time on unrelated improvements.

## 13. Final Task Report

For small tasks, provide:

**Status:** Completed / Partial / Blocked

**Root Cause:** Brief explanation.

**Files Changed:** Exact file paths.

**Fix:** What was changed.

**Verification:** Passed checks and unverified behavior.

**Remaining:** Only actual pending items.

For large milestones, additionally include:
- Completed work
- Partially completed work
- Pending work
- Blocked items
- Progress percentages based on verifiable evidence
- Relevant Git commands when requested

Never invent progress percentages or claim untested features are working.

## 14. Highest Priority

The user's requested task takes priority over optional refactoring, cleanup, documentation, and unrelated investigation.

Work efficiently, protect existing BlueMarketing functionality, complete the requested implementation, verify it, and return a clear result.