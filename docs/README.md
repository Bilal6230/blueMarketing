# BlueMarketing — Fresh Phase-by-Phase Implementation

## Scope
This is a new documentation starting point for the `upgrade/laravel-framework` branch. Previous non-V2 working documents were intentionally cleared. Existing Database V2 documents and `docs/database_v2_scan/` are retained unchanged as historical technical evidence; retaining them does **not** mean V2 is production-ready.

## Mandatory phase gates
1. Start by inspecting existing application code, current behavior, and the client requirements for the selected phase. Record what is already implemented, partial, and missing.
2. Write a small, testable phase scope and explicit acceptance criteria before implementation.
3. Implement **only the active phase**; preserve unrelated modules, records, permissions, and accounting rules. First-party frontend DOM/AJAX work must use jQuery per root `AGENTS.md`.
4. Run relevant code-level tests and record results. Do not claim browser testing based on code inspection alone.
5. Require the owner's **actual browser testing and explicit PASS** for every phase. Record routes, actions, expected/observed behavior, failures, and evidence.
6. If any test fails, repair and repeat **the same phase**. **Do not start the next phase until the current phase passes browser testing and the owner confirms PASS.**
7. After PASS, update the active phase report with completed items, remaining items, evidence and commit reference; only then open the next phase.

## Safety
- Do not change or disable the isolated Database V2 safety guards as part of ordinary phases.
- Do not modify production financial data, migrate databases, or force deployments without explicit authorization.
- Do not infer phase completion from previous documents that have been cleared.
- This documentation reset does not delete any application code or historical Git commits.

## Phase tracking
**Current phase:** Not started. The first phase will be defined from the latest client requirements and a new code audit.

**Browser test gate:** Pending. No phase may be marked complete without the owner's browser PASS.
