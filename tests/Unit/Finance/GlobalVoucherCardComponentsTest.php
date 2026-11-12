<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class GlobalVoucherCardComponentsTest extends TestCase
{
    public function test_filter_card_renders_shared_markup_and_preserves_local_fields(): void
    {
        $html = Blade::render('<x-finance.voucher-filter-card class="daily-voucher-card"><form id="filters"><input name="date"></form></x-finance.voucher-filter-card>');
        $this->assertStringContainsString('bm-voucher-filter-card', $html);
        $this->assertStringContainsString('bm-filter', $html);
        $this->assertStringContainsString('daily-voucher-card', $html);
        $this->assertStringContainsString('class="card-body"', $html);
        $this->assertStringContainsString('id="filters"', $html);
    }

    public function test_table_card_renders_shared_markup_and_preserves_local_toolbar_table_and_footer(): void
    {
        $html = Blade::render('<x-finance.voucher-table-card class="daily-voucher-table-card"><header>Toolbar</header><table id="rows"></table><footer>Paging</footer></x-finance.voucher-table-card>');
        $this->assertStringContainsString('bm-voucher-table-card', $html);
        $this->assertStringContainsString('bm-card', $html);
        $this->assertStringContainsString('daily-voucher-table-card', $html);
        $this->assertStringContainsString('id="rows"', $html);
        $this->assertStringContainsString('Paging', $html);
    }

    public function test_all_preset_daily_directory_families_share_the_same_filter_and_table_components(): void
    {
        $directory = file_get_contents(resource_path('views/admin/finance/voucher/daily_directory.blade.php'));
        foreach (['CR','DR','CCR','CDR','BOR','BOP','CP','PPR','JV','CV','BR','BO','BD','BW','PR','PP'] as $series) {
            $this->assertStringContainsString("'".$series."'", $directory);
        }
        $this->assertStringContainsString('<x-finance.voucher-filter-card', $directory);
        $this->assertStringContainsString('</x-finance.voucher-filter-card>', $directory);
        $this->assertStringContainsString('<x-finance.voucher-table-card', $directory);
        $this->assertStringContainsString('</x-finance.voucher-table-card>', $directory);
    }

    public function test_dedicated_bank_transfer_screens_reuse_both_components_without_posting_changes(): void
    {
        $transfer = file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
        $this->assertStringContainsString('<x-finance.voucher-filter-card', $transfer);
        $this->assertStringContainsString('<x-finance.voucher-table-card', $transfer);
        $this->assertStringContainsString("route('finance.voucher.transfer.post'", $transfer);
        $this->assertStringContainsString('$.blueActionMenu.enhance', $transfer);
    }
}
