<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class LabourPaymentCorrectionContractTest extends TestCase
{
    public function test_labour_cp_route_writes_source_ledger_and_exact_workers_in_one_transaction(): void
    {
        $root=dirname(__DIR__,3);
        $svc=file_get_contents($root.'/app/Services/Finance/LabourPaymentEditor.php');
        $ctrl=file_get_contents($root.'/app/Http/Controllers/LabourController.php');
        $routes=file_get_contents($root.'/routes/web.php');
        $view=file_get_contents($root.'/resources/views/admin/labours/history.blade.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_19_160000_add_labour_payment_archive_columns.php');
        foreach([$svc,$ctrl,$routes,$view,$migration] as $code)self::assertIsString($code);

        self::assertStringContainsString('public function update(',$svc);
        self::assertStringContainsString('public function delete(',$svc);
        self::assertStringContainsString('public function details(',$svc);
        self::assertStringContainsString('DB::transaction(',$svc);
        self::assertStringContainsString("where('customer_ledger_id',\$sourceId)",$svc);
        self::assertStringContainsString('Typed payroll members have their own correction authority',$svc);
        self::assertStringContainsString("'is_active'=>0",$svc);
        self::assertStringContainsString('finance_labour_payment_members',$svc);
        self::assertStringNotContainsString('PostingReversalWriter',$svc);
        self::assertStringContainsString('function updatePayment(',$ctrl);
        self::assertStringContainsString('function deletePayment(',$ctrl);
        self::assertStringContainsString('labourPaymentSources',$ctrl);
        self::assertStringContainsString("->name('labours.payment.details')",$routes);
        self::assertStringContainsString("->name('labours.payment.update')",$routes);
        self::assertStringContainsString("->name('labours.payment.delete')",$routes);
        self::assertStringContainsString('labourCpEditForm',$view);
        self::assertStringContainsString('labourCpDeleteConfirm',$view);
        self::assertStringContainsString('refreshHistory()',$view);
        self::assertStringContainsString("type:'PUT'",$view);
        self::assertStringContainsString("type:'DELETE'",$view);
        self::assertStringContainsString('softDeletes()',$migration);
    }
}
