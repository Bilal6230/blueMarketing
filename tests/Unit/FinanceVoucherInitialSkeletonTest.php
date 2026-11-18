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
    public function test_shared_skeleton_takes_voucher_theme_color_and_preserves_credit_debit_tones(): void
    {
        $root = dirname(__DIR__, 2);
        $css = file_get_contents($root.'/public/css/blue-table-skeleton.css');
        self::assertIsString($css);
        self::assertStringContainsString('--voucher-accent', $css);
        self::assertStringContainsString('.bm-skeleton-neutral', $css);
        self::assertStringContainsString('.bm-skeleton-credit', $css);
        self::assertStringContainsString('.bm-skeleton-debit', $css);
    }

    public function test_draft_and_extra_initial_rows_stay_concealed_until_datatables_ready(): void
    {
        $root = dirname(__DIR__, 2);
        $draft = file_get_contents($root.'/resources/views/admin/finance/voucher/draft_voucher.blade.php');
        $extra = file_get_contents($root.'/resources/views/admin/booking/extra_charge.blade.php');
        $css = file_get_contents($root.'/public/css/finance-voucher-components.css');
        self::assertIsString($draft);
        self::assertIsString($extra);
        self::assertIsString($css);
        self::assertStringContainsString('draftVoucherInitialSkeleton', $draft);
        self::assertStringContainsString("settings.nTable.id==='bmDraftTable'", $draft);
        self::assertStringContainsString('extraChargeInitialSkeleton', $extra);
        self::assertStringContainsString("settings.nTable.id==='example1'", $extra);
        self::assertStringContainsString('bm-voucher-initial-pending', $css);
        self::assertStringContainsString('bm-voucher-initial-skeleton', $css);
    }

}
