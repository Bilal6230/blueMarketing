<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PlotReceiptPrintLayoutTest extends TestCase
{
    public function test_receipt_print_has_compact_a5_paper_rules_and_normal_flow(): void
    {
        $view = file_get_contents(resource_path('views/admin/finance/voucher/plot_receipt_print.blade.php'));
        $this->assertStringContainsString('@page{size:A5 portrait;margin:8mm}', $view);
        $this->assertStringContainsString('position:static!important;', $view);
        $this->assertStringContainsString('height:auto!important;', $view);
        $this->assertStringContainsString('padding:0!important;border:0!important;', $view);
        $this->assertStringContainsString('.actions{display:none!important}', $view);
        $this->assertStringNotContainsString('position:fixed!important', $view);
        $this->assertStringNotContainsString('height:210mm!important', $view);
        $this->assertNotFalse(Blade::compileString($view));
    }

    public function test_per_plot_print_still_displays_receipt_customer_booking_and_own_amount(): void
    {
        $view = file_get_contents(resource_path('views/admin/finance/voucher/plot_receipt_print.blade.php'));
        foreach ([
            '$receipt->receipt_number', '$receipt->receipt_date',
            '$receipt->narration', '$allocation->plot_name',
            '$allocation->booking_id', '$allocation->amount',
            '$allocation->first_name', '$allocation->last_name',
        ] as $value) {
            $this->assertStringContainsString($value, $view);
        }
    }
}
