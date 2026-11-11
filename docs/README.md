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
