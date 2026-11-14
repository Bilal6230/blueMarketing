<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class VoucherModernHeaderFiltersContractTest extends TestCase
{
    public function test_cheque_and_online_excel_header_filters_reuse_validated_project_scoped_queries(): void
    {
        $master=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $css=file_get_contents(public_path('css/finance-voucher-compact.css'));
        $script=file_get_contents(public_path('js/finance-voucher-heading-columns.js'));

        $this->assertStringContainsString('finance-voucher-heading-columns.js', $master);
        $this->assertStringContainsString('bm-column-filter-storage', $css);
        $this->assertStringContainsString('bm-heading-column-popover', $css);
        $this->assertStringContainsString('sort_column', $script);
        $this->assertStringContainsString('sort_direction', $script);
        $this->assertStringContainsString('trigger(\'input\')', $script);
        $this->assertStringContainsString('sort_column', $script);
        $this->assertStringContainsString('reset:\'#cheque-reset\'', $script);
        $this->assertStringContainsString('reset:\'#online-reset\'', $script);
        $this->assertStringNotContainsString('document.createElement(', $script);

        $cheque=file_get_contents(app_path('Http/Controllers/Finance/ChequeIntakeController.php'));
        $online=file_get_contents(app_path('Http/Controllers/Finance/OnlineIntakeController.php'));
        $this->assertStringContainsString("Rule::in(['received_date','cheque_number'", $cheque);
        $this->assertStringContainsString("where('c.project_id', \$project)", $cheque);
        $this->assertStringContainsString("whereDate('c.received_date', \$filters['received_date'])", $cheque);
        $this->assertStringContainsString("Rule::in(['transaction_date','reference'", $online);
        $this->assertStringContainsString("where('project_id',\$project)", $online);
        $this->assertStringContainsString("if(\$sort!=='id')", $online);
        foreach (['sort_column','sort_direction'] as $field) {
            $this->assertStringContainsString("'".$field."'", $cheque);
            $this->assertStringContainsString("'".$field."'", $online);
        }

        $chequeView=file_get_contents(resource_path('views/admin/finance/cheque/intake.blade.php'));
        $onlineView=file_get_contents(resource_path('views/admin/finance/online/intake.blade.php'));
        foreach (['received_date','direction','amount','status','purpose'] as $column) {
            $this->assertStringContainsString('data-column="'.$column.'"', $chequeView);
        }
        foreach (['transaction_date','party','amount','status','type'] as $column) {
            $this->assertStringContainsString('data-column="'.$column.'"', $onlineView);
        }
    }
}
