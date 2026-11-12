# BlueMarketing Global UI — Design System and Floating Actions
Updated: 2026-11-12 · branch `upgrade/laravel-framework`

## Principle: call/reuse, never copy visual definitions

The admin master layout globally loads:
- `public/css/blue-design-system.css`: **single source of truth** for typography, sizes, primary colors, card borders, filter control heights, table cells, spacing and action dimensions (CSS custom properties, `:root`).
- `public/css/blue-action-menu.css`: floating speed-dial presentation, reusing shared token values.
- `public/js/blue-action-menu.js`: first-party jQuery engine; original financial action controls, event handlers and authorizations remain unchanged.
- `public/css/blue-table-skeleton.css` and `public/js/blue-table-skeleton.js`: existing reusable skeleton.

**Never duplicate global component styles in new Blade views.** Add the reusable classes and override design tokens only for a documented variant. Page-local styles are reserved for real page-specific layout/behavior, not common sizes/colors.

## UI tokens

Modify `public/css/blue-design-system.css` once for shared changes:

| Token | Purpose |
|---|---|
| `--bm-font-xs/sm/md/lg` | Badge, label, body, heading sizes |
| `--bm-success`, `--bm-danger`, `--bm-info` | Color system |
| `--bm-panel-tint`, `--bm-border`, `--bm-shadow` | Cards and table accents |
| `--bm-radius`, `--bm-panel-padding` | Panel corners and spacing |
| `--bm-control-height`, `--bm-filter-gap` | Filter inputs and gaps |
| `--bm-table-cell-padding` | Table row density |
| `--bm-action-toggle`, `--bm-action-col`, `--bm-action-gap` | Floating actions dimensions |

**Reusable classes** on new finance pages: `bm-ui` on page root; `bm-page-heading` on heading; `bm-card` on cards; `bm-filter` on filters; `bm-table` on the data table; `bm-records-toolbar` for Search/Show controls; `bm-panel-heading` for the common heading variant.

Example:
```html
<div class="content-wrapper bm-ui">
  <div class="bm-page-heading"><h4>Records</h4></div>
  <section class="bm-card bm-filter">...</section>
  <section class="bm-card">
    <table class="table bm-table">...</table>
  </section>
</div>
```

## Animated global floating action component

`+` rotates into an animated **red ×**. Each permitted action appears separately as a floating, colored, rounded pill with staggered fade/slide and individual shadow. There is **no dropdown box**. Individual action colors: gray Details, slate History, violet Print, blue Edit, amber Revise, green Approve/Confirm, red Reject/Release/Void/Delete. Close restores red × to green + with reverse rotation. Repositions vertically and left/right depending on viewport. Outside click, Escape, scroll/resize and AJAX refresh close it. Reduced-motion users get instant changes.

Markup stays deliberately simple, using the original buttons/actions:
```html
<td class="blue-actions-cell">
  <button type="button" class="btn btn-outline-primary existing-edit">Edit</button>
  <button type="button" class="btn btn-outline-danger existing-delete">Delete</button>
</td>
```

Initialize **after the row is inserted**:
```javascript
$.blueActionMenu.enhance($('#records-body > tr > td.blue-actions-cell'));
```

On AJAX refresh, **close then re-enhance**:
```javascript
$.blueActionMenu.close();
$('#records-body').html(newRows);
$.blueActionMenu.enhance($('#records-body > tr > td.blue-actions-cell'));
```

Use `data-blue-action-label="Print"` and optional `data-blue-action-type="print"` on existing row buttons for explicit label and color. Original actions are **moved into an invisible container, not copied**, so existing delegated jQuery handlers and permission-gated markup continue to work. The floating buttons trigger the original click. The backend still authorizes and validates every action.

## Implementation scope and current gate

The common Action Menu + design tokens are adopted in:
1. CR/DR/BOR/BOP/CCR/CDR daily voucher directories.
2. Cheque Management.
3. Online Transaction Management.
4. Voucher Approval Dashboard (queue and history).

The foundation is loaded **globally**, so future views use the component classes with no new stylesheet or JavaScript. Other legacy pages are **not** silently auto-restyled: adopting their local layouts must be reviewed individually to avoid breaking unrelated forms or financial flows.

Browser gate **OPEN** until owner confirms: button transforms +↔red × and animates both directions, colors/individual floating pills, top/bottom row clipping, outside/Escape close, search/pagination refresh, proper authorized actions, prints, modals, Cheque/Online actions, approval actions, small screen and typography/card/filter consistency. No ledger/approval/DB mutation was introduced by this UI-only batch. GitHub static JavaScript syntax check PASS; PHP/Blade and browser runtime tests not run by assistant.
