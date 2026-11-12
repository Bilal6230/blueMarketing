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
**Current phase:** Point03 Phase 03 — ACTIVE. Phases 01 and 02 complete with owner browser PASS. Phase 03.1 and 03.2 complete with owner browser PASS. Phase 03.3 in progress: Batch 1 owner browser PASS; Batch 2 modern JV UI alignment owner browser PASS but latest font-size adjustment browser verification pending. Batch 3 (Pending/Rejected Voucher Lifecycle Safety) next after gate. Phase 04+ LOCKED.

**Browser test gate:** Latest Batch 2 typography change pending. Overall Phase 03.3 browser PASS pending. No phase may be marked complete without the owner's browser PASS.


## Active client requirement delivery
- [Point 03 Roman Urdu source](./BLUEMARKETING_POINT03_DAILY_ENTRIES_REQUIREMENTS_ROMAN_URDU.md)
- [Point 03 strict phase roadmap](./POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md)
- [Canonical active handoff for new chats](./ACTIVE_POINT03_HANDOFF.md)
- [Phase 01 Cheque worklog — COMPLETE](./POINT03_PHASE_01_CHEQUE_WORKLOG.md)
- [Phase 02 Online worklog — COMPLETE](./POINT03_PHASE_02_ONLINE_WORKLOG.md)
- [Active Phase 03 Voucher worklog](./POINT03_PHASE_03_VOUCHER_WORKLOG.md)
- Phase 04+ must not start before Phase 03 owner browser PASS.

## 2026-11-11 — CURRENT dependency-based order (supersedes previous Phase 04 lock)
Owner explicitly prioritized completing the functional six vouchers before full CR/CP browser approval/resubmission acceptance. **Authoritative sequence:** 01 Cheque PASS; 02 Online PASS; 03 completed voucher foundation preserved (remaining 03.3 tests deferred, not passed); **04 CR/CP/CCR/CDR/BOR/BOP forms, persisted types, separate listings, safe optional attachments and regression** (batch-by-batch owner browser PASS); 05 final approval/mutation/resubmission tests, including previously held 3B2A/B; 06 bank narration/link/withdrawal/deposit; 07 plot receive; 08 plot purchase; 09 dashboard; 10 end-to-end acceptance. Phase 04 is next implementation scope, not blocked by circular CR/CP acceptance dependence. No phase/batch bypasses per-batch browser PASS or ledger/V2 safety. See [authoritative active roadmap](./POINT03_DAILY_ENTRIES_PHASE_ROADMAP.md).


## 2026-11-12 — Global UI Design System + Floating Speed-Dial Actions (code committed; browser gate OPEN)
- Owner requested moving beyond dropdowns: click green `+` -> smoothly rotating red `×`, individual floating colored action pills with staggered animation, no dropdown panel. Also mandated reusable **global design foundation**, avoiding copied fonts/card/table/filter/action styles per page.
- Implemented centrally: `public/js/blue-action-menu.js` rewritten into a first-party jQuery floating speed dial with existing original row action callbacks, single-open state, outside/Escape/scroll/resize close and viewport-aware floating positioning. `public/css/blue-action-menu.css` now animates +/red ×, separate floating action pills colored by action (Details/History/Print/Edit/Revise/Approve/destructive) with reduced-motion support. No original finance actions, permissions, endpoints or audit decisions were rewritten.
- New **globally loaded** `public/css/blue-design-system.css` defines shared design tokens for fonts, colors, panel borders/radius/shadow, filter control height, table cell density and action button sizes. `resources/views/admin/layouts/master.blade.php` registers it, and includes versioned v2 action CSS/JS. New pages just add `bm-ui`, `bm-card`, `bm-filter`, `bm-table`, `bm-page-heading`, `blue-actions-cell` and call `$.blueActionMenu.enhance(...)`; no copied reusable CSS/JS.
- Adoption in **four finance areas**: (1) shared CR/DR/BOR/BOP/CCR/CDR voucher directory, (2) Cheque Management, (3) Online Management, (4) Voucher Approval Dashboard. Existing local CSS common font sizes/accent colors now reference shared variables. **Other historical pages not yet auto-migrated** to avoid global visual regressions; the reusable component is AVAILABLE globally for controlled opt-in rollout.
- Reusable component guide: `docs/GLOBAL_ACTION_MENU_COMPONENT.md`, documenting global token inventory, markup/initialization and AJAX refresh pattern. No DB migration, data backfill, finance code changes or new third-party packages. Static JavaScript parser check PASS; user local PHP/Blade compile and browser tests NOT run by assistant.
- Code commits include `0c383105`, `c58f00e0`, `01bcefd5`, `fa3898c2`, `5668d0e6`, `9aeb41b6`, `166db6a7`, `4b47c04c`, `3c911a70`, `0e79d20f`, `a19f2c56`, `6424dfcb`, `80d737ef`; guide `6969f7ac`.
- **Current browser gate OPEN:** owner pulls and hard-refreshes; verify smooth +↔red ×, staggered colored floating buttons, top/bottom position, scrolling/click-outside/Escape close, all original financial action modals/print links/cheque/online approvals, responsive size, row paging and component consistency. No next phase until owner PASS; no claim that all legacy pages are already redesigned.
