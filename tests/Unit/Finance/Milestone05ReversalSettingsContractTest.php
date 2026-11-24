<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class Milestone05ReversalSettingsContractTest extends TestCase
{
    public function test_reversal_switch_reuses_existing_policy_table_and_has_a_guarded_settings_route(): void
    {
        $root=dirname(__DIR__,3);
        $migration=file_get_contents($root.'/database/migrations/2026_11_24_170000_add_reversal_enabled_to_daily_voucher_policies.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherSettingsController.php');
        $routes=file_get_contents($root.'/routes/web.php');
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/settings.blade.php');
        foreach ([$migration,$controller,$routes,$view] as $source)self::assertIsString($source);
        self::assertStringContainsString("Schema::table('daily_voucher_approval_policies'", $migration);
        self::assertStringContainsString("boolean('reversal_enabled')->default(true)", $migration);
        self::assertStringContainsString('function saveReversal(Request $request)', $controller);
        self::assertStringContainsString("['required','boolean']", $controller);
        self::assertStringContainsString("firstOrCreate(", $controller);
        self::assertStringContainsString("in_array(\$type,['CHQ_CORR','ONL_CORR'],true)", $controller);
        self::assertStringContainsString("name('finance.voucher-settings.reversal.save')", $routes);
        self::assertStringContainsString("permission:update setting", $routes);
        self::assertStringContainsString('vs-reversal-form', $view);
        self::assertStringContainsString('vs-reversal-enabled', $view);
        self::assertStringContainsString('Save Reversal Setting', $view);
        self::assertStringContainsString("method:'POST'", $view);
    }

    public function test_reversal_action_visibility_respects_project_policy_without_hiding_edit_delete(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $directory=file_get_contents($root.'/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');
        $receipt=file_get_contents($root.'/resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php');
        $purchase=file_get_contents($root.'/resources/views/admin/finance/voucher/plot_purchase_workspace.blade.php');
        foreach([$controller,$directory,$receipt,$purchase] as $file)self::assertIsString($file);
        self::assertStringContainsString("enabled(\$projectId,'PPR')", $controller);
        self::assertStringContainsString("enabled(\$projectId,'PP')", $controller);
        self::assertStringContainsString("'can_reverse' => \$reversalEnabled &&", $controller);
        self::assertStringContainsString("'can_reverse' => (\$reversalBySeries[(string)\$r->voucher_series]??false)", $controller);
        self::assertStringContainsString("'can_edit' =>", $controller);
        self::assertStringContainsString('VoucherReversalPolicy::class', $directory);
        self::assertStringContainsString('array_key_exists($reversalSeries,$reversalBySeries)', $directory);
        self::assertStringContainsString('foreach($rows->pluck(\'voucher_series\')->unique() as $series)', $controller);
        self::assertStringContainsString('VOUCHER_REVERSAL_DISABLED', $directory);
        self::assertStringContainsString('if(r.can_reverse)', $receipt);
        self::assertStringContainsString('if(x.can_reverse)', $purchase);
        self::assertStringContainsString('pr-edit ml-1', $receipt);
        self::assertStringContainsString('pp-edit ml-1', $purchase);
    }

    public function test_every_http_reversal_route_flows_into_policy_guarded_writer(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $voucher=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $daily=file_get_contents($root.'/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');
        $bank=file_get_contents($root.'/app/Services/Finance/BankTransferLifecycleService.php');
        $cash=file_get_contents($root.'/app/Services/Finance/CashVoucherLifecycleService.php');
        $plot=file_get_contents($root.'/app/Services/Finance/PlotEntryLifecycleService.php');
        $gateway=file_get_contents($root.'/app/Services/Finance/PostingGateway.php');
        foreach ([$routes,$voucher,$daily,$bank,$cash,$plot,$gateway] as $source)self::assertIsString($source);

        self::assertStringContainsString("name('finance.voucher.transfer.reverse')", $routes);
        self::assertStringContainsString("name('finance.voucher.plot-receipt.reverse')", $routes);
        self::assertStringContainsString("name('finance.voucher.plot-purchase.reverse')", $routes);
        self::assertStringContainsString("name('finance.daily-vouchers.sources.reverse')", $routes);

        self::assertStringContainsString('function reverseTransferVoucher(', $voucher);
        self::assertStringContainsString('function reversePlotSource(', $voucher);
        self::assertStringContainsString('function reverseTypedCashSource(', $daily);
        self::assertStringContainsString('reversePostedBatch(', $bank);
        self::assertStringContainsString('reversePostedBatch(', $cash);
        self::assertStringContainsString('PostingReversalWriter())->reverse(', $plot);
        self::assertStringContainsString('PostingReversalWriter())->reverse(', $gateway);
        self::assertStringContainsString('VOUCHER_REVERSAL_DISABLED', $voucher);
        self::assertStringContainsString('VOUCHER_REVERSAL_DISABLED', $daily);
    }

    public function test_finance_api_reversal_and_void_routes_use_the_same_policy_guard(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/api.php');
        $api=file_get_contents($root.'/app/Http/Controllers/Api/Finance/FinanceApiController.php');
        $voucherLifecycle=file_get_contents($root.'/app/Services/Finance/VoucherLifecycleService.php');
        $plotLifecycle=file_get_contents($root.'/app/Services/Finance/PlotEntryLifecycleService.php');
        foreach ([$routes,$api,$voucherLifecycle,$plotLifecycle] as $source)self::assertIsString($source);
        self::assertStringContainsString("Route::post('reversals'", $routes);
        self::assertStringContainsString("Route::post('plot-vouchers/{kind}/{sourceId}/void'", $routes);
        self::assertStringContainsString("Route::post('vouchers/{kind}/{sourceId}/void'", $routes);
        self::assertStringContainsString('function voidPlotVoucher(', $api);
        self::assertStringContainsString('function voidVoucher(', $api);
        self::assertStringContainsString('function reverse(Request $request', $api);
        self::assertSame(3,substr_count($api,"'FINANCE_REVERSAL_DISABLED'"));
        self::assertStringContainsString('reversePostedBatch(', $voucherLifecycle);
        self::assertStringContainsString('PostingReversalWriter())->reverse(', $plotLifecycle);
        self::assertStringContainsString('$gateway->reversePostedBatch(', $api);
    }

    public function test_ec_ecp_get_reversal_switch_without_enabling_unsupported_approval(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherSettingsController.php');
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/settings.blade.php');
        self::assertStringContainsString("'EC' => ['label'=>'Extra Charge', 'supported'=>false]", $controller);
        self::assertStringContainsString("'ECP' => ['label'=>'Extra Charge Payment', 'supported'=>false]", $controller);
        self::assertStringContainsString('@if($definition[\'supported\'])', $view);
        self::assertStringContainsString("vs-reversal-form", $view);
        self::assertStringContainsString('Approval configuration is not supported', $view);
    }

    public function test_only_typed_reversal_writer_applies_the_policy_at_the_write_boundary(): void
    {
        $root=dirname(__DIR__,3);
        $writer=file_get_contents($root.'/app/Services/Finance/PostingReversalWriter.php');
        $policy=file_get_contents($root.'/app/Services/Finance/VoucherReversalPolicy.php');
        foreach ([$writer,$policy] as $source)self::assertIsString($source);
        self::assertStringContainsString('VoucherReversalPolicy::class', $writer);
        self::assertStringContainsString('(string) $original->voucher_series', $writer);
        self::assertStringContainsString('VOUCHER_REVERSAL_DISABLED', $policy);
        self::assertStringContainsString("where('project_id', \$projectId)", $policy);
        self::assertStringContainsString("where('voucher_type', strtoupper(\$series))", $policy);
        self::assertStringContainsString('return $configured === null || (bool) $configured;', $policy);
        self::assertStringNotContainsString('PostingReversalWriter', file_get_contents($root.'/app/Http/Controllers/Finance/VoucherSettingsController.php'));
    }
}
