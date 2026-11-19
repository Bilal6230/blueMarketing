<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class StockMovementSourceLinkAuditContractTest extends TestCase
{
    public function test_read_only_audit_requires_all_exact_stock_source_relationships(): void
    {
        $root=dirname(__DIR__,3);
        $code=file_get_contents($root.'/app/Console/Commands/LinkStockMovementSources.php');
        self::assertIsString($code);
        self::assertStringContainsString('finance:link-stock-movement-sources',$code);
        self::assertStringContainsString('{--apply :',$code);
        self::assertStringContainsString('stock_entry_line_id',$code);
        self::assertStringContainsString('stock_bill_line_id',$code);
        self::assertStringContainsString("where('line.project_id',\$projectId)",$code);
        self::assertStringContainsString("where('header.project_id',\$projectId)",$code);
        self::assertStringContainsString("where('line.item_id',(int)\$movement->item_id)",$code);
        self::assertStringContainsString("line.\$parentFK",$code);
        self::assertStringContainsString("amount_minor",$code);
        self::assertStringContainsString("rate_minor",$code);
        self::assertStringContainsString("source_line_id",$code);
        self::assertStringContainsString("if(!$apply)continue",$code);
        self::assertStringContainsString("->whereNull(\$sourceLineColumn)",$code);
        self::assertStringNotContainsString('->delete(', $code);
        self::assertStringNotContainsString('->forceDelete(', $code);
    }
}
