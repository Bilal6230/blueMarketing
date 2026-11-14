<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class FinanceVoucherCanonicalDesignTest extends TestCase
{
    public function test_one_canonical_design_sheet_and_header_component_drive_voucher_families(): void
    {
        $layout=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $styles=file_get_contents(public_path('css/finance-voucher-system.css'));
        $component=file_get_contents(resource_path('views/components/finance/voucher-directory-actions.blade.php'));
        $shared=file_get_contents(public_path('js/finance-voucher-header-filters.js'));

        $this->assertStringContainsString('finance-voucher-system.css', $layout);
        $this->assertStringContainsString('--fvd-font:', $styles);
        $this->assertStringContainsString('--fvd-row-height:', $styles);
        $this->assertStringContainsString('--fvd-accent:', $styles);
        $this->assertStringContainsString('.daily-voucher-page .daily-voucher-card.mb-3', $styles);
        $this->assertStringContainsString('.jv-page .jv-filters', $styles);
        $this->assertStringContainsString('.cv-directory #filtersSection', $styles);
        $this->assertStringContainsString('.cheque-register-page .cheque-filter-panel', $styles);
        $this->assertStringContainsString('.online-register-page .online-filter', $styles);
        $this->assertStringContainsString('display:none!important', $styles);
        $this->assertStringNotContainsString('addFilterModal(', $shared);
        $this->assertStringNotContainsString('bm-heading-filter', $shared);
        $this->assertStringContainsString('moveCreate(', $shared);
        $this->assertStringContainsString('bm-inline-link-filter', $shared);
        $this->assertStringContainsString("route('dashboard')", $component);
        $this->assertStringContainsString("canAccessFinanceApprovalsForUser", $component);
        foreach ([
            'admin/finance/voucher/daily_directory.blade.php',
            'admin/reports/vouchers/index.blade.php',
            'admin/reports/vouchers/commision_index.blade.php',
            'admin/finance/cheque/intake.blade.php',
            'admin/finance/online/intake.blade.php',
        ] as $path) {
            $this->assertStringContainsString(
                '<x-finance.voucher-directory-actions',
                file_get_contents(resource_path('views/'.$path))
            );
        }
    }

    public function test_dedicated_voucher_create_skips_generic_type_picker(): void
    {
        $daily=file_get_contents(resource_path('views/admin/finance/voucher/daily_directory.blade.php'));
        $this->assertStringContainsString('const selected=@json($presetType)', $daily);
        $this->assertStringContainsString('openVoucherType(selected)', $daily);
        $this->assertStringContainsString('dv-create-voucher', $daily);
        $this->assertStringContainsString("seriesMap={CR:'CR'", $daily);
    }

    public function test_commission_and_extra_share_heading_sort_filter_without_ledger_changes(): void
    {
        $layout=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $script=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $controller=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));
        $commission=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));

        $this->assertStringContainsString('finance-voucher-datatable-columns.js', $layout);
        $this->assertStringContainsString("table:'#journalTable'", $script);
        $this->assertStringContainsString("table:'#example1'", $script);
        $this->assertStringContainsString("off('click.DT keypress.DT')", $script);
        $this->assertStringContainsString('fvd_column_filters', $commission);
        $this->assertStringContainsString('fvd_sort_column', $controller);
        $this->assertStringContainsString('fvd_sort_direction', $controller);
        $this->assertStringContainsString('where(\'project_id\', $selectedProjectId)', $controller);
        $this->assertStringContainsString("Rule::in(['id','voucher_number'", $controller);
    }
}
