<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class StockMovementExactSourceFkContractTest extends TestCase
{
    public function test_new_stock_entry_and_bill_movements_store_real_parent_line_fks(): void
    {
        $root=dirname(__DIR__,3);
        $migration=file_get_contents($root.'/database/migrations/2026_11_19_130000_link_stock_movements_to_physical_source_lines.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/StockController.php');
        self::assertIsString($migration);
        self::assertIsString($controller);
        foreach(['stock_entry_line_id','stock_bill_line_id'] as $field){
            self::assertStringContainsString("'".$field."'",$migration);
            self::assertStringContainsString("Schema::hasColumn('stock_movements','".$field."')",$controller);
            self::assertStringContainsString("'".$field."'=>\$lineRow->id",$controller);
        }
        self::assertStringContainsString("on('stock_entry_lines')->restrictOnDelete()",$migration);
        self::assertStringContainsString("on('stock_bill_lines')->restrictOnDelete()",$migration);
        self::assertStringNotContainsString("Schema::dropIfExists('stock_movements')",$migration);
        self::assertStringContainsString('No historical auto-backfill',$migration);
    }
}
