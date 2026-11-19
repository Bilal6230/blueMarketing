<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class LabourPaymentSourceRelationContractTest extends TestCase
{
    public function test_all_new_labour_payment_rows_link_to_exact_customer_ledger_fk(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/LabourController.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_19_120000_link_labour_payment_rows_to_customer_source.php');
        $model=file_get_contents($root.'/app/Models/LabourLedger.php');
        foreach([$controller,$migration,$model] as $source)self::assertIsString($source);
        self::assertStringContainsString("'customer_ledger_id'",$migration);
        self::assertStringContainsString("->references('id')->on('customer_ledger')->restrictOnDelete()",$migration);
        self::assertStringContainsString('whereNull(\'customer_ledger_id\')',$controller);
        self::assertStringContainsString("'customer_ledger_id'=>(int)\$customerLedger->id",$controller);
        self::assertStringContainsString('$linked!==count($labourLedgerRowIds)',$controller);
        self::assertStringContainsString('function customerLedger()',$model);
        self::assertStringNotContainsString("'file' => \$e->getFile(),\n                'line' => \$e->getLine(),\n            ], 500);",$controller);
    }
}
