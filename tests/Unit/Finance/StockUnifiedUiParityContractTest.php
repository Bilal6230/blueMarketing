<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class StockUnifiedUiParityContractTest extends TestCase
{
    public function test_stock_tabs_have_modal_forms_cr_filters_and_internal_scroll(): void
    {
        $view = file_get_contents(resource_path('views/admin/stock/index.blade.php'));
        self::assertIsString($view);

        foreach (['entry', 'billing', 'stock', 'items', 'parties'] as $tab) {
            self::assertStringContainsString('id="view-'.$tab.'"', $view);
            $kind = match ($tab) {
                'billing' => 'bill',
                'items' => 'item',
                'parties' => 'party',
                default => $tab,
            };
            self::assertStringContainsString('data-stock-kind="'.$kind.'"', $view);
        }

        foreach (['stockEntryCreateModal', 'stockBillCreateModal', 'stockItemCreateModal', 'stockPartyCreateModal'] as $modal) {
            self::assertSame(1, substr_count($view, 'id="'.$modal.'"'));
        }
        foreach (['entryForm', 'billForm', 'itemForm', 'partyForm'] as $form) {
            self::assertSame(1, substr_count($view, 'id="'.$form.'"'));
        }

        self::assertStringContainsString('stock-excel-trigger', $view);
        self::assertStringContainsString('stockExcelPopover', $view);
        self::assertStringContainsString('stock-items-side-by-side', $view);
        self::assertStringContainsString('stock-kpi-row', $view);
        self::assertStringContainsString('height:calc(100dvh - var(--stock-page-top)', $view);
        self::assertStringContainsString("$('#stockFrom,#stockTo').on('change'", $view);
        self::assertStringContainsString('function stockModal(selector, action)', $view);

        self::assertStringNotContainsString('Legacy stock needs project reconciliation.', $view);
        self::assertStringNotContainsString('id="btnApplyStock"', $view);
        self::assertStringNotContainsString('class="stock-column-filters"', $view);
        self::assertStringNotContainsString('id="stockExcelApply"', $view);
        self::assertStringContainsString('function stockApplyLiveFilter()', $view);
        self::assertStringContainsString('new window.TomSelect(el', $view);
        self::assertStringContainsString('stockEnhanceDropdowns($modal)', $view);
        self::assertStringContainsString('class="stock-modal-close"', $view);
        self::assertStringContainsString('grid-template-columns:minmax(0,1fr) minmax(0,1fr)', $view);
        self::assertStringContainsString('width:185px!important', $view);
        self::assertStringContainsString('stock-entry-mode d-flex gap-3', $view);
        self::assertStringContainsString("$(document).on('click.stockDismiss','[data-stock-dismiss]'", $view);
        self::assertStringContainsString('stock-line-field', $view);
        self::assertStringContainsString('stock-line-caption', $view);
        self::assertStringContainsString('stock-line-remove', $view);
        self::assertStringContainsString("dropdownParent:$el.closest('.modal')[0] || 'body'", $view);
        self::assertStringContainsString('function stockSetSubheads(items,placeholder)', $view);
        self::assertStringContainsString('el.tomselect.clearOptions()', $view);
        self::assertStringContainsString('stockSetSubheads(rows,rows.length', $view);
        self::assertStringContainsString('function stockDismissNow(modal)', $view);
        self::assertStringContainsString("button.addEventListener('click'", $view);
        self::assertSame(5, substr_count($view, 'class="stock-modal-actions mt-2"'));
        self::assertStringNotContainsString('data-bs-dismiss="modal"', $view);
    }
}
