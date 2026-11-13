# Deferred Milestone — BlueMarketing Application-Wide Visual Consistency

**Status:** PARKED / DEFERRED (do not start until current active finance/voucher phases are completed and user explicitly resumes this milestone).
**Source:** Owner's direction in the BlueMarketing project conversation, 2026-11-13.
**Branch:** `upgrade/laravel-framework`.

## Owner's non-negotiable requirement

The complete BlueMarketing application must look and behave like **one application**, regardless of module or page. Common visuals include font family, typography scale, font sizes and weights, line height, spacing, sizing, cards, page headers, forms, tables, filters, buttons, action menus, modals, notifications, pagination, responsiveness, empty/loading states, and navigation chrome.

**Preserve every voucher/module's original color identity.** Example palettes: CR/BD green; DR/BW red; JV/CV blue; booking/plot legacy pages purple; other existing module-specific accents preserved after audit. Do not impose a single global accent on all pages. Preserve specialized fields and behaviors (plot allocation, commission, approval, correction, JV/CV, etc.).

## Architectural goal

Formalize **BlueMarketing Design System v1** on existing assets rather than starting from scratch.

- Central design tokens: font family, type sizes/weights/line heights, spacing, radii, shadows, control sizes, focus states, responsive breakpoints, density.
- Shared application shell and page-level layout primitives.
- Reusable Blade components: headers, filter cards, listing/table cards, form controls, buttons, action menus, modals, badges, tabs, alerts, pagination, loading/empty states.
- Shared jQuery-only DOM and AJAX interactions; no new native JavaScript DOM/AJAX handlers in authored frontend code (consistent with AGENTS.md).
- Page/module themes via per-screen variables (`--voucher-accent`, `--voucher-tint` and audited standardized tokens) while structural typography, sizing and layout remain uniform.
- Preserve existing permissions, finance posting semantics, validation, server-side filtering, approvals, authorized editing/reversal workflows, and historical records.

## Existing code foundation (reuse)

- `public/css/blue-design-system.css`
- `public/css/blue-action-menu.css`
- `public/js/blue-action-menu.js`
- `public/js/blue-voucher-register-actions.js`
- `public/js/blue-voucher-pagination.js`
- `public/js/blue-voucher-column-filter.js`
- `resources/views/components/finance/voucher-filter-card.blade.php`
- `resources/views/components/finance/voucher-table-card.blade.php`
- `resources/views/components/finance/voucher-column-filters.blade.php`

**Caution:** Previously reusing shared card wrappers did NOT automatically align full UI: JV/CV headers, plot workspace sidebar overlap, inconsistent tables/filters and differing per-page CSS remained. Audit and remove conflicting per-page overrides gradually rather than applying one broad global CSS override.

## Deferred delivery plan

1. **Whole-project visual inventory:** inspect active routes, Blade views, CSS/JS assets and UI patterns, grouped by Finance, Sales/Booking, CRM, HR/Attendance, Dashboard, Reports, Settings and remaining modules. Identify duplicated UI, unreachable/legacy routes and conflicting styles.
2. **Design token standardization:** define actual canonical sizes/weights/spacing/borders/shadows and color-independent typography; preserve current design style where possible.
3. **Reusable component library:** formalize global shell, headers, filters, listing cards, rows/actions, modals, form fields, alerts, badges, tabs and pagination with clear documented variants.
4. **Controlled module-by-module migration:** prioritize active screens, preserve original theme colors and module-specific business workflows; do not equate including a Blade card component with full parity.
5. **Visual/regression enforcement:** automated Blade/component tests, functional browser verification, responsive screenshots at agreed widths and shared lint/review rules for new UI code.

## Execution / acceptance rules

- **Do not resume this milestone now**. Finish the already active Point 03 / Phase 06 finance work and other current priority milestones first; resume only when user asks.
- Each future batch must produce actual code implementation, targeted regression tests, browser acceptance checklist, updated worklog/handoff, commit and Windows Laragon PowerShell pull/test instructions. Documentation-only batches are not sufficient.
- A batch is NOT browser PASS until user verifies it on the local `bluenew` test setup (or an equivalent authorized safe environment).
- Preserve current UI colors. Focus on one visual language for every screen. Changes must be safe and scoped; no financial data mutation or mass migration merely for styling.
- Defer changing the application's whole color scheme or rebuilding business forms unless the owner explicitly requests it.

## Resume instruction for future conversation

> Continue BlueMarketing Application-Wide Visual Consistency from `docs/FUTURE_APPLICATION_WIDE_DESIGN_SYSTEM_WORKFLOW.md`. First inspect current branch status and active finance/other milestones, then, **only once prior phases are complete and owner approves resuming**, execute the five milestones above with code/test/UI acceptance deliverables and preserve every existing page/voucher theme.
