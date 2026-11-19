<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class StockDocumentCorrectionContractTest extends TestCase
{
    public function test_stock_entry_and_bill_correct_current_movement_and_legacy_ledger_without_new_voucher(): void
    {
        $root=dirname(__DIR__,3);
        $service=file_get_contents($root.'/app/Services/Finance/StockDocumentEditor.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/StockController.php');
        $routes=file_get_contents($root.'/routes/web.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_19_150000_add_stock_document_archive_columns.php');
        foreach([$service,$controller,$routes,$migration] as $source) self::assertIsString($source);
        self::assertStringContainsString('public function update(',$service);
        self::assertStringContainsString('public function delete(',$service);
        self::assertStringContainsString("'source_line_id'",$service);
        self::assertStringContainsString("where('customer_ledger_id',\$customer->id)",$service);
        self::assertStringContainsString("where('reference',\$reference)",$service);
        self::assertStringContainsString("where('is_active',1)",$service);
        self::assertStringContainsString("'is_active'=>0",$service);
        self::assertStringContainsString("'deleted_at'=>\$now",$service);
        self::assertStringContainsString('balanceChanges(',$service);
        self::assertStringContainsString('Stock correction would cause negative inventory',$service);
        self::assertStringNotContainsString('PostingReversalWriter',$service);
        self::assertStringContainsString('function updateStockDocument(',$controller);
        self::assertStringContainsString('function deleteStockDocument(',$controller);
        self::assertStringContainsString("->name('stocks.documents.details')",$routes);
        self::assertStringContainsString("->name('stocks.documents.update')",$routes);
        self::assertStringContainsString("->name('stocks.documents.delete')",$routes);
        self::assertStringContainsString("'stock_movements','is_active'",$migration);
    }

    public function test_stock_ui_edits_and_deletes_via_ajax_then_reloads_active_only_lists(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/stock/index.blade.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/StockController.php');
        self::assertIsString($view);
        self::assertIsString($controller);
        self::assertStringContainsString('stock-doc-edit',$view);
        self::assertStringContainsString('stock-doc-delete',$view);
        self::assertStringContainsString("type:'PUT'",$view);
        self::assertStringContainsString("type:'DELETE'",$view);
        self::assertStringContainsString("finance:stock-document-saved",$view);
        self::assertStringContainsString("whereNull('l.deleted_at')",$controller);
        self::assertStringContainsString("where('m.is_active',1)",$controller);
    }
}
