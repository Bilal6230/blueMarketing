<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class PlotFullVoucherEditContractTest extends TestCase
{
    public function test_full_edit_is_project_scoped_and_reuses_financial_tables_and_shared_snapshot(): void
    {
        $root=dirname(__DIR__,3);
        $service=file_get_contents($root.'/app/Services/Finance/PlotVoucherEditor.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $routes=file_get_contents($root.'/routes/web.php');
        self::assertIsString($service);
        foreach ([
            'DB::transaction(', 'lockForUpdate()', 'finance_plot_receipt_allocations',
            'finance_plot_purchase_items', 'finance_posting_lines', 'finance_posting_batches',
            'daily_voucher_edit_snapshots', 'plot_edit', 'where(', 'project_id',
            'reversal_of_batch_id', 'Unbalanced posting rejected.',
        ] as $entry) self::assertStringContainsString($entry,$service);
        self::assertStringNotContainsString('Schema::create(', $service);
        self::assertStringNotContainsString('->delete(', $service);
        self::assertStringContainsString("can('finance plot-receipt edit')",$controller);
        self::assertStringContainsString("can('finance plot-purchase edit')",$controller);
        self::assertStringContainsString('updatePlotReceipt(Request $request',$controller);
        self::assertStringContainsString('updatePlotPurchase(Request $request',$controller);
        self::assertStringContainsString("finance.voucher.plot-receipt.update",$routes);
        self::assertStringContainsString("finance.voucher.plot-purchase.update",$routes);
    }

    public function test_plot_voucher_delete_is_independent_of_reverse_and_keeps_original_source_evidence(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $lifecycle=file_get_contents($root.'/app/Services/Finance/PlotEntryLifecycleService.php');
        $routes=file_get_contents($root.'/routes/web.php');
        self::assertStringContainsString("'finance plot-receipt delete'",$controller);
        self::assertStringContainsString("'finance plot-purchase delete'",$controller);
        self::assertStringContainsString("function deletePlotReceipt(",$controller);
        self::assertStringContainsString("function deletePlotPurchase(",$controller);
        self::assertStringContainsString("Route::delete('finance/voucher/plot-receipt/", $routes);
        self::assertStringContainsString("Route::delete('finance/voucher/plot-purchase/", $routes);
        self::assertStringContainsString('function archive(', $lifecycle);
        self::assertStringContainsString('is_active', $lifecycle);
        self::assertStringContainsString('deleted_at', $lifecycle);
        self::assertStringContainsString('$this->reverse(', $lifecycle);
        self::assertStringNotContainsString('->delete(', $lifecycle);
        foreach([
            ['plot_receipt_workspace.blade.php','plot-receipt','pr'],
            ['plot_purchase_workspace.blade.php','plot-purchase','pp'],
        ] as [$file,$kind,$prefix]){
            $blade=file_get_contents($root.'/resources/views/admin/finance/voucher/'.$file);
            self::assertStringContainsString("@can('finance {$kind} delete')",$blade);
            self::assertStringContainsString("{$prefix}-delete",$blade);
            self::assertStringContainsString("id=\"{$prefix}DeleteModal\"",$blade);
            self::assertStringContainsString("type:'DELETE'",$blade);
            self::assertStringContainsString("{$prefix}DeleteReason",$blade);
            self::assertStringContainsString("@can('finance {$kind} reverse')",$blade);
        }
    }

    public function test_both_voucher_create_modals_are_reused_for_full_edit(): void
    {
        $root=dirname(__DIR__,3);
        foreach ([
            ['plot_receipt_workspace.blade.php','pr-edit','prNumber','prCustomer','prAccount','prPost'],
            ['plot_purchase_workspace.blade.php','pp-edit','ppNumber','ppSeller','ppItems','ppPost'],
        ] as [$file,$action,$number,$account,$details,$save]) {
            $blade=file_get_contents($root.'/resources/views/admin/finance/voucher/'.$file);
            self::assertIsString($blade);
            foreach ([$action,$number,$account,$details,$save,"'PATCH'"] as $needle) {
                self::assertStringContainsString($needle,$blade);
            }
            self::assertStringContainsString('Save voucher',$blade);
            self::assertStringContainsString('data-details-base',$blade);
        }
    }
}
