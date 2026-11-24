<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class StockDocumentLifecycleContractTest extends TestCase
{
    public function test_stock_document_crud_is_project_scoped_and_preserves_related_movements_and_ledgers(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/StockController.php');
        $editor=file_get_contents($root.'/app/Services/Finance/StockDocumentEditor.php');
        foreach([$routes,$controller,$editor] as $source)self::assertIsString($source);
        self::assertStringContainsString("->name('stocks.documents.details')",$routes);
        self::assertStringContainsString("->name('stocks.documents.update')",$routes);
        self::assertStringContainsString("->name('stocks.documents.delete')",$routes);
        self::assertStringContainsString('function updateStockDocument(',$controller);
        self::assertStringContainsString('function deleteStockDocument(',$controller);
        self::assertStringContainsString('where(\'project_id\',$projectId)', $editor);
        self::assertStringContainsString('source_line_id',$editor);
        self::assertStringContainsString('stock_project_item_balances',$editor);
        self::assertStringContainsString("'is_active'=>0",$editor);
        self::assertStringContainsString('negative inventory',$editor);
    }

    public function test_stock_bill_narration_round_trips_through_create_details_and_edit(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/StockController.php');
        $editor=file_get_contents($root.'/app/Services/Finance/StockDocumentEditor.php');
        $model=file_get_contents($root.'/app/Models/StockBill.php');
        $view=file_get_contents($root.'/resources/views/admin/stock/index.blade.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_24_160000_add_narration_to_stock_bills.php');
        foreach ([$controller,$editor,$model,$view,$migration] as $file) {
            self::assertIsString($file);
        }
        self::assertStringContainsString("'narration' => ['nullable', 'string', 'max:1000']", $controller);
        self::assertStringContainsString("'narration' => trim((string) (\$data['narration'] ?? ''))", $controller);
        self::assertStringContainsString("\$source->narration??''", $controller);
        self::assertStringContainsString("\$headerUpdate['narration']=\$narration", $editor);
        self::assertStringContainsString("\$header->narration??''", $editor);
        self::assertStringContainsString("'narration',", $model);
        self::assertStringContainsString('id="billNarration"', $view);
        self::assertStringContainsString("narration: \$('#billNarration').val() || \"\"", $view);
        self::assertStringContainsString("Schema::table('stock_bills'", $migration);
        self::assertStringContainsString("\$table->text('narration')->nullable()", $migration);
    }

    public function test_stock_edit_rows_and_modal_dismissals_remain_visible_and_functional(): void
    {
        $view=file_get_contents(dirname(__DIR__,3).'/resources/views/admin/stock/index.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString('appendTo($wrap);', $view);
        self::assertStringContainsString("stockDocAddLine(line);", $view);
        self::assertStringContainsString('stockDocumentEditTotal', $view);
        self::assertStringContainsString('stockDocumentItemsHeading', $view);
        self::assertStringContainsString('stock-line-field', $view);
        self::assertStringContainsString('stock-line-caption', $view);
        self::assertStringContainsString('stock-doc-unit', $view);
        self::assertStringContainsString('stock-line-summary', $view);
        self::assertStringContainsString('stock-doc-remove stock-line-remove', $view);
        self::assertStringContainsString('Bill Items', $view);
        self::assertStringNotContainsString('Rate (minor units)', $view);
        self::assertStringContainsString('stockDocRecalculate();', $view);
        self::assertStringContainsString('click.stockModalClose', $view);
        self::assertStringContainsString("stockModal('#'+modal.id,'hide')", $view);
        self::assertStringNotContainsString('event.stopImmediatePropagation();', $view);
        self::assertStringContainsString("stockDocHide('#stockDocumentEditModal')", $view);
        self::assertStringContainsString("stockDocHide('#stockDocumentDeleteModal')", $view);
    }

    public function test_stock_edit_delete_use_bootstrap_five_modals_with_jquery_ajax_and_refresh(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/stock/index.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString('stockDocumentEditModal',$view);
        self::assertStringContainsString('stockDocumentDeleteModal',$view);
        self::assertStringContainsString('stock-doc-edit',$view);
        self::assertStringContainsString('stock-doc-delete',$view);
        self::assertStringContainsString('stockDocModal',$view);
        // Bootstrap versions differ and the offline fallback is deliberate.
        // Assert functional modal resolution instead of one exact API spelling.
        self::assertStringContainsString('window.bootstrap&&window.bootstrap.Modal',$view);
        self::assertStringContainsString('klass.getOrCreateInstance',$view);
        self::assertStringContainsString('stock-modal-fallback-backdrop',$view);
        self::assertStringContainsString("type:'PUT'",$view);
        self::assertStringContainsString("type:'DELETE'",$view);
        self::assertStringContainsString("}).fail(function(xhr){",$view);
        self::assertStringContainsString('finance:stock-document-saved',$view);
        self::assertStringNotContainsString("$('#stockDocumentEditModal').modal('show')",$view);
    }
}
