<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class DailyVoucherDrActionAndFilterContractTest extends TestCase
{
    public function test_dr_has_same_role_scoped_edit_delete_and_revision_paths_as_cash_payment(): void
    {
        $root=dirname(__DIR__,3);
        $directory=file_get_contents($root.'/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');
        $editor=file_get_contents($root.'/app/Http/Controllers/Finance/RejectedCashVoucherController.php');
        $destroy=file_get_contents($root.'/app/Http/Controllers/LedgerController.php');
        self::assertIsString($directory);
        self::assertIsString($editor);
        self::assertIsString($destroy);
        self::assertStringContainsString("['CR', 'DR', 'CP','CCR','CDR']", $directory);
        self::assertStringContainsString("['CR','DR','CP','CCR','CDR']", $directory);
        self::assertStringContainsString("['CR','DR','CP','CCR','CDR']", $editor);
        self::assertStringContainsString("whereIn('type', ['CR','CP','DR','CCR','CDR','BOR','BOP'])", $destroy);
        self::assertStringContainsString("assertNoPendingCorrection", $destroy);
        self::assertStringContainsString("where('voucher_id',\$source->id)->exists()", $destroy);
    }

    public function test_daily_directory_has_no_visible_initial_toolbar_filter_card(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/daily_directory.blade.php');
        self::assertIsString($view);
        self::assertStringNotContainsString('<x-finance.voucher-filter-card', $view);
        self::assertStringNotContainsString('id="dv-from"', $view);
        self::assertStringNotContainsString('id="dv-account"', $view);
        self::assertStringContainsString('id="dv-table-search"', $view);
        self::assertStringContainsString('id="dv-create-voucher"', $view);
        self::assertStringContainsString('id="daily-voucher-filters" class="d-none"', $view);
    }

    public function test_plot_purchase_and_receipt_top_filters_are_not_in_html(): void
    {
        $root=dirname(__DIR__,3);
        foreach ([
            'plot_purchase_workspace.blade.php'=>['ppQuickSearch','ppNewVoucher'],
            'plot_receipt_workspace.blade.php'=>['prQuickSearch','prNewVoucher'],
        ] as $name=>$required) {
            $view=file_get_contents($root.'/resources/views/admin/finance/voucher/'.$name);
            self::assertIsString($view);
            self::assertStringNotContainsString('<x-finance.voucher-filter-card',$view,$name);
            foreach($required as $id)self::assertStringContainsString('id="'.$id.'"',$view,$name);
        }
    }

    public function test_jv_sv_and_cv_registers_do_not_render_top_filter_cards(): void
    {
        $root=dirname(__DIR__,3);
        foreach([
            'index.blade.php'=>['jv-directory','jv-add'],
            'commision_index.blade.php'=>['journalTable','commision.voucher.create'],
        ] as $name=>$required){
            $view=file_get_contents($root.'/resources/views/admin/reports/vouchers/'.$name);
            self::assertIsString($view);
            self::assertStringNotContainsString('<x-finance.voucher-filter-card',$view,$name);
            foreach($required as $needle)self::assertStringContainsString($needle,$view,$name);
        }
    }

    public function test_bd_bw_workspaces_have_no_rendered_top_filter_toolbar(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/bank_transfer_workspace.blade.php');
        self::assertIsString($view);
        self::assertStringNotContainsString('<x-finance.voucher-filter-card', $view);
        self::assertStringNotContainsString('id="btFilterForm"', $view);
        self::assertStringNotContainsString('id="transferFrom"', $view);
        self::assertStringNotContainsString('id="transferTo"', $view);
        self::assertStringContainsString('id="btQuickSearch"', $view);
        self::assertStringContainsString('id="openTransferCreate"', $view);
    }
}
