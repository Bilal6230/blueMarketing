<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class PlotVoucherSeriesNumberingContractTest extends TestCase
{
    public function test_plot_receipt_and_purchase_use_independent_readonly_number_previews(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Finance/VoucherController.php'));
        $routes = file_get_contents(base_path('routes/web.php'));
        self::assertStringContainsString('function plotVoucherNextNumber(', $controller);
        self::assertStringContainsString("->preview($projectId,$series)", $controller);
        self::assertStringContainsString("where('series','PPR|PP')", $routes);

        foreach ([
            'plot_receipt_workspace.blade.php' => ['prNumber','PPR'],
            'plot_purchase_workspace.blade.php' => ['ppNumber','PP'],
        ] as $file => [$field, $series]) {
            $view = file_get_contents(base_path('resources/views/admin/finance/voucher/'.$file));
            self::assertStringContainsString('id="'.$field.'"', $view);
            self::assertStringContainsString('value="{{ $nextVoucherNumber }}" readonly', $view);
            self::assertStringContainsString("route('finance.voucher.plot-next-number','".$series."')", $view);
            self::assertStringContainsString("data.voucher_number", $view);
        }
    }

    public function test_plot_posting_reserves_the_source_number_and_preserves_it_in_batch(): void
    {
        $writer = file_get_contents(base_path('app/Services/Finance/PlotEntryWriter.php'));
        $draft = file_get_contents(base_path('app/Services/Finance/PlotVoucherApprovalDraftWriter.php'));
        self::assertStringContainsString("'voucher_number'=>$voucherNumber", $writer);
        self::assertStringContainsString("PLOT_VOUCHER_NUMBER_CHANGED_REFRESH_FORM", $writer);
        self::assertStringContainsString("PLOT_VOUCHER_NUMBER_INVALID", $writer);
        self::assertStringContainsString("->next($project,'PPR')", $writer);
        self::assertStringContainsString("->next($project,'PP')", $writer);
        self::assertStringContainsString("->next($projectId,$type)", $draft);
        self::assertStringContainsString("PLOT_VOUCHER_NUMBER_CHANGED_REFRESH_FORM", $draft);
    }

    public function test_sales_vouchers_are_list_and_print_only(): void
    {
        $register = file_get_contents(base_path('resources/views/admin/reports/vouchers/index.blade.php'));
        $actions = file_get_contents(base_path('resources/views/admin/reports/vouchers/actions.blade.php'));
        self::assertStringContainsString("\$type !== 'SV'", $register);
        self::assertSame(2, substr_count($actions, "\$voucher->type !== 'SV' && !(\$approvalPending"));
        self::assertStringContainsString("'.print'", $actions);
    }
}
