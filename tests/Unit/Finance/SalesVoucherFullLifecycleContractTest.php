<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class SalesVoucherFullLifecycleContractTest extends TestCase
{
    public function test_sv_create_edit_and_delete_use_exact_journal_source_and_series_counter(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/JournalVoucherController.php');
        $workflow=file_get_contents($root.'/app/Services/Finance/OriginalJournalWorkflow.php');
        $view=file_get_contents($root.'/resources/views/admin/reports/vouchers/journal_voucher.blade.php');
        $listing=file_get_contents($root.'/resources/views/admin/reports/vouchers/index.blade.php');
        $permissions=file_get_contents($root.'/app/Support/Finance/FinanceActionPermission.php');
        foreach([$routes,$controller,$workflow,$view,$listing,$permissions] as $text)
            self::assertIsString($text);

        self::assertStringContainsString("->name('sales.voucher.create')",$routes);
        self::assertStringContainsString("->name('sales.voucher.store')",$routes);
        self::assertStringContainsString("request()->routeIs('sales.voucher.create')",$controller);
        self::assertStringContainsString("routeIs('sales.voucher.store')",$controller);
        self::assertStringContainsString("'finance sv create'",$controller);
        self::assertStringContainsString("createAndPost((int)\$selectedProjectId,\$key,\$validated,",$controller);
        self::assertStringContainsString("nextSalesVoucher(\$projectId)",$workflow);
        self::assertStringContainsString("postSalesVoucher(\$projectId,\$id,\$key,\$actorId)",$workflow);
        self::assertStringContainsString("where('type', \$series)",$workflow);
        self::assertStringContainsString("route('sales.voucher.store')",$view);
        self::assertStringContainsString("route('sales.voucher.create')",$listing);
        self::assertStringContainsString("'sales.voucher.store'=>['sv','create']",$permissions);
        self::assertStringContainsString("whereIn('voucher.type', ['JV','SV'])",$controller);

        $update=substr($controller,strpos($controller,'public function update(Request $request'));
        self::assertStringNotContainsString(
            "abort_if(\$this->isMergedPostedJournal(\$projectId, (int) \$id), 409",
            $update
        );
        self::assertStringContainsString('JournalApprovedVoucherEditor::class',$update);
        self::assertStringContainsString("PostedVoucherArchiver::class",$controller);
    }
}
