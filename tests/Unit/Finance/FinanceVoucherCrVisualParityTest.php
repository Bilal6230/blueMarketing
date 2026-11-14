<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class FinanceVoucherCrVisualParityTest extends TestCase
{
    public function test_global_tokens_control_all_directory_heights_popovers_entries_and_pagination(): void
    {
        $css=file_get_contents(public_path('css/finance-voucher-system.css'));
        $script=file_get_contents(public_path('js/finance-voucher-header-filters.js'));
        $layout=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $pager=file_get_contents(public_path('js/blue-voucher-pagination.js'));

        foreach ([
            '--fvd-row-height','--fvd-filter-width','--fvd-pagination-gap',
            '--fvd-page-width','--fvd-toolbar-height','--fvd-directory-height',
            '.dataTables_paginate .page-link','.fvd-fit-viewport',
            '.bm-heading-column-popover','.fvd-data-popover',
            '.jv-excel-pop','.dv-excel-popover',
            '.bm-voucher-directory table tbody td','.bm-voucher-directory table thead',
            '.fvd-date-range-label','.dataTables_length select',
            '.fvd-voucher-links','--fvd-footer-height'
        ] as $fragment) {
            $this->assertStringContainsString($fragment,$css);
        }
        $this->assertStringContainsString('finance-voucher-system.css',$layout);
        $this->assertStringContainsString('fitDirectories()', $script);
        $this->assertStringContainsString('alignActions($header)', $script);
        $core=file_get_contents(public_path('js/finance-voucher-components.js'));
        $this->assertStringContainsString('fvd-sort-options', $core);
        $this->assertStringContainsString('createFilter', $core);
        $this->assertStringContainsString('attachDataTablePager', $core);
        $this->assertStringContainsString('fvd-voucher-links', $script);
        $this->assertStringContainsString("option", $pager);
        $this->assertStringNotContainsString('addFilterModal(', $script);
    }

    public function test_compatibility_adapters_use_real_date_ranges_and_preserve_project_scoping(): void
    {
        $heading=file_get_contents(public_path('js/finance-voucher-heading-columns.js'));
        $dataTable=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $cheque=file_get_contents(app_path('Http/Controllers/Finance/ChequeIntakeController.php'));
        $online=file_get_contents(app_path('Http/Controllers/Finance/OnlineIntakeController.php'));
        $commission=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));

        foreach (['column_date_from','column_date_to'] as $key) {
            $this->assertStringContainsString($key,$heading);
            $this->assertStringContainsString($key,$cheque);
            $this->assertStringContainsString($key,$online);
        }
        foreach (['fvd_date_from','fvd_date_to'] as $key) {
            $this->assertStringContainsString($key,$commission);
        }
        $this->assertStringContainsString("settings.nTable!==\$table[0]", $dataTable);
        $this->assertStringContainsString("table:'#bmDraftTable'", $dataTable);
        $this->assertStringContainsString("table:'#example1'", $dataTable);
        $this->assertStringContainsString("where('project_id',\$project)", $online);
        $this->assertStringContainsString('where(\'project_id\', $selectedProjectId)', $commission);
    }
}
