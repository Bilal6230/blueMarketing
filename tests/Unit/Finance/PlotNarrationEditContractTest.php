<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class PlotNarrationEditContractTest extends TestCase
{
    public function test_independent_edit_permission_ajax_routes_and_project_scoped_atomic_service(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $service=file_get_contents($root.'/app/Services/Finance/PlotVoucherNarrationEditor.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_18_170000_create_plot_voucher_edit_audits_table.php');
        self::assertIsString($routes);self::assertIsString($controller);
        self::assertIsString($service);self::assertIsString($migration);
        self::assertStringContainsString('updatePlotReceiptNarration',$routes);
        self::assertStringContainsString('updatePlotPurchaseNarration',$routes);
        self::assertStringContainsString("'finance '.$module.' edit'",$controller);
        self::assertStringContainsString('FinanceWorkflowAccess::authorize($projectId)',$controller);
        self::assertStringContainsString('DB::transaction(', $service);
        self::assertStringContainsString('lockForUpdate()', $service);
        self::assertStringContainsString("->where('project_id',$projectId)", $service);
        self::assertStringContainsString('finance_plot_voucher_edit_audits',$service);
        self::assertStringContainsString('reversal_of_batch_id',$service);
        self::assertStringContainsString('previous_narration',$migration);
        self::assertStringContainsString('updated_narration',$migration);
        self::assertStringNotContainsString('->delete(', $service);
        self::assertStringNotContainsString("DB::table('finance_posting_lines')->update(", $service);
    }

    public function test_both_ajax_edit_actions_are_independent_of_reverse_permission(): void
    {
        $root=dirname(__DIR__,3);
        foreach ([
            ['plot-receipt','pr','resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php'],
            ['plot-purchase','pp','resources/views/admin/finance/voucher/plot_purchase_workspace.blade.php'],
        ] as [$kind,$prefix,$file]) {
            $blade=file_get_contents($root.'/'.$file);
            self::assertIsString($blade);
            $edit=strpos($blade,"@can('finance {$kind} edit')");
            $reverse=strpos($blade,"@can('finance {$kind} reverse')",$edit);
            self::assertNotFalse($edit);
            self::assertNotFalse($reverse);
            self::assertLessThan($reverse,$edit);
            self::assertStringContainsString("{$prefix}-narration",$blade);
            self::assertStringContainsString("id=\"{$prefix}NarrationModal\"",$blade);
            self::assertStringContainsString("type:'PATCH'",$blade);
            self::assertStringContainsString('$.ajax(',$blade);
        }
    }
}
