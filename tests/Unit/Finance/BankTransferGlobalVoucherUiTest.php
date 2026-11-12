<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * BD/BW share one project-scoped list & modal view; posting stays on the
 * existing V2 finance writer, not on an additional UI-specific endpoint.
 */
final class BankTransferGlobalVoucherUiTest extends TestCase
{
    private function template(): string
    {
        return file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
    }

    public function test_blade_template_compiles_with_both_voucher_kinds(): void
    {
        $compiled = Blade::compileString($this->template());
        $this->assertStringContainsString('bankTransferCreateModal', $compiled);
        $this->assertStringContainsString('bankTransferViewModal', $compiled);
        $this->assertStringContainsString('transferTable', $compiled);
    }

    public function test_cr_dr_daily_directory_table_and_filter_layout_is_present(): void
    {
        $view = $this->template();
        foreach ([
            'id="btFilterForm"', 'id="transferFrom"', 'id="transferTo"',
            'id="transferBankFilter"', 'id="transferSearch"',
            'id="applyTransferFilters"', 'id="clearTransferFilters"',
            'id="openTransferCreate"', 'id="transferTable"',
            'id="btLength"', 'id="btQuickSearch"', 'id="btRecordSummary"',
            'id="btPagination"', 'id="transferRows"',
            'id="bankTransferCreateModal"', 'id="bankTransferViewModal"',
            'class="bt-page-btn"', 'blue-actions-cell', '$.blueActionMenu.enhance', 'bt-filter-card',
            'bt-table-card', 'bt-debit', 'bt-credit',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $view);
        }
        $this->assertStringNotContainsString('id="historyRows"', $view);
        $this->assertStringContainsString("@section('modal')", $view);
        $this->assertStringContainsString('class="container-fluid bm-ui bt-page', $view);
        $this->assertStringContainsString('class="card bm-card bm-filter bt-filter-card"', $view);
        $this->assertStringContainsString('class="table bm-table', $view);
        $this->assertStringNotContainsString('bt-plus bt-view', $view);
        $this->assertStringNotContainsString('$.fn.DataTable', $view);
        $this->assertStringNotContainsString('dom:\'Bfrtip\'', $view);
    }

    public function test_modal_reuses_existing_bank_transfer_v2_routes(): void
    {
        $view = $this->template();
        $this->assertStringContainsString("route('finance.voucher.transfer.post'", $view);
        $this->assertStringContainsString("route('finance.voucher.transfer.history'", $view);
        $this->assertStringContainsString("route('finance.voucher.transfer.sources'", $view);
        $this->assertStringContainsString('pendingRequestKey', $view);
        $this->assertStringContainsString('request_key:pendingRequestKey', $view);
        // A posted transfer cannot be edited or deleted through this UI.
        $this->assertStringNotContainsString('bt-delete', $view);
        $this->assertStringNotContainsString('bt-edit', $view);
    }

    public function test_history_and_print_use_stable_batch_fallback(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Finance/VoucherController.php'));
        $print = file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_print.blade.php'));
        $this->assertStringContainsString('$r->voucher_number ?: $r->posting_batch_id', $controller);
        $this->assertStringContainsString('$source->voucher_number ?: $source->posting_batch_id', $print);
    }
}
