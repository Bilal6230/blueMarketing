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
