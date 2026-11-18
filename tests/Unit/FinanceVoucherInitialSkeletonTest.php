<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FinanceVoucherInitialSkeletonTest extends TestCase
{
    public function test_ajax_voucher_directories_have_shared_skeleton_lifecycle(): void
    {
        $root = dirname(__DIR__, 2);
        $views = [
            'bank_transfer_workspace.blade.php' => ['#transferRows', 'columns:9'],
            'plot_receipt_workspace.blade.php' => ['#prHistoryRows', 'columns:8'],
            'plot_purchase_workspace.blade.php' => ['#ppHistory', 'columns:8'],
        ];

        foreach ($views as $filename => [$target, $columns]) {
            $view = file_get_contents($root.'/resources/views/admin/finance/voucher/'.$filename);
            self::assertIsString($view);
            self::assertStringContainsString('$.blueTableSkeleton.show(', $view, $filename);
            self::assertStringContainsString('$.blueTableSkeleton.clear(', $view, $filename);
            self::assertStringContainsString($target, $view, $filename);
            self::assertStringContainsString($columns, $view, $filename);
        }
    }

    public function test_extra_charge_hides_raw_rows_until_datatables_initializes(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/booking/extra_charge.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString('bm-extra-list-pending', $view);
        self::assertStringContainsString('extraChargeInitialSkeleton', $view);
        self::assertStringContainsString("$(document).on('init.dt'", $view);
        self::assertStringContainsString("settings.nTable.id==='example1'", $view);
        self::assertStringContainsString("removeClass('bm-extra-list-pending')", $view);
    }
}
