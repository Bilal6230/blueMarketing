# BlueMarketing — Global Action Menu (2026-11-12)

## Purpose

One compact animated + button per table row, instead of multiple inline actions that break table widths. On click the + morphs into − with a slower 320ms CSS transition; a floating menu opens with 230–320ms fade/scale motion. All menus are keyboard-accessible and close on Escape, outside press, scroll, resize, and AJAX table replacement.

## Shared assets

- `public/js/blue-action-menu.js` (first-party jQuery; no extra library)
- `public/css/blue-action-menu.css` (responsive fixed-position portal, prefers-reduced-motion)
- Globally included in `resources/views/admin/layouts/master.blade.php`, before `@stack('script')`.

## Reuse — JavaScript-built table rows

Create the original row controls as before, including their classes/data attributes and existing click handlers; DO NOT change their save/reject/void handlers or authority checks:

```javascript
const $cell=$('<td>').addClass('blue-actions-cell');
$('<button type="button">').addClass('btn btn-sm btn-outline-primary original-edit')
  .text('Edit').appendTo($cell);
$('<button type="button">').addClass('btn btn-sm btn-outline-danger original-delete')
  .text('Delete').appendTo($cell);
$cell.appendTo($row);
```

After the new rows have been appended to a table, run:

```javascript
$.blueActionMenu.close();
$.blueActionMenu.enhance($('#your-tbody > tr > td.blue-actions-cell'));
```

The helper wraps/moves original buttons into a hidden source area within the original `td`, preserving their classes, click listeners and delegated `tbody` handlers. Its floating menu uses safe text labels, no HTML rendering from row data. Clicking a menu item triggers the ORIGINAL action element; it doesn't invent a new endpoint or bypass authorization. If source control is disabled, corresponding menu entry is disabled too. For custom menu labels set `data-blue-action-label="Short label"` on the source button/link.

## Reuse — Server-rendered Blade rows

Render buttons/links directly inside `<td class="blue-actions-cell">` and after page ready:

```javascript
$(function(){
    $.blueActionMenu.enhance($('.my-table td.blue-actions-cell'));
});
```

The action cell should not contain a nested action-button wrapper. Content which is not an action (e.g. hidden detail template) remains in the `td`. Do not put plain-text-only controls in the action cell if a compact appearance is desired.

## Rollout completed in code

1. CR/DR/BOR/BOP/CCR/CDR daily voucher directory (shared dynamic listing).
2. Cheque Management register, original clearing/finalization/history actions.
3. Online Transaction Management register, original confirm/history actions.
4. Voucher Approval Dashboard pending and recent history actions.

No third-party table plugin migration. Financial route methods, CSRF, project isolation, allowed action conditions, modal workflow, and V2 ledger guards remain unchanged.

## Browser acceptance gate (owner PASS required)

- Plus button visible, compact, single width; open + becomes −, and panel smooth with no clipping even on first/last row.
- Every previous action opens its original modal/handler: voucher Details, Revise/Edit, Release/Void, Print; Cheque Send for Clearing/Finalize/History; Online Confirm/History; Approvals Approve/Reject/History.
- Disabled/non-authorized actions never become available. Destructive actions remain visually red and still require their existing confirmation/reason.
- Escape/outside click/scroll closes menu, keyboard Tab focuses menu items; small viewport does not render off-screen.
- Pagination/search/filter AJAX rebuilds buttons; no duplicate menus, no stale actions, no horizontal table stretching.
- Existing financial transactions and backend permissions remain unchanged. No migration or database backfill required for this UI batch.

**Status:** Code committed, owner browser test pending. Do not start next milestone without explicit owner PASS.
