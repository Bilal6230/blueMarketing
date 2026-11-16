<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class FinanceVoucherUnifiedComponentsTest extends TestCase
{
    public function test_single_shared_popup_renderer_is_consumed_by_bank_sources_commission_draft_and_extra(): void
    {
        $layout=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $factory=file_get_contents(public_path('js/finance-voucher-components.js'));
        $bank=file_get_contents(public_path('js/finance-voucher-heading-columns.js'));
        $dataTable=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $styles=file_get_contents(public_path('css/finance-voucher-components.css'));

        $this->assertStringContainsString('finance-voucher-components.js', $layout);
        $this->assertStringContainsString('finance-voucher-components.css', $layout);
        $this->assertStringContainsString('createFilter(options)', $factory);
        $this->assertStringContainsString('fvc-filter-popover', $factory);
        $this->assertStringContainsString('fvc-filter-title', $factory);
        $this->assertStringContainsString('fvc-sort-options', $factory);
        $this->assertStringContainsString('fvc-choices-list', $factory);
        $this->assertStringContainsString('fvc-date-input', $styles);
        $this->assertStringContainsString('fvc-page-btn', $styles);
        $this->assertStringContainsString("$.financeVoucherComponents.createFilter({kind:'heading'", $bank);
        $this->assertStringContainsString("$.financeVoucherComponents.createFilter({kind:'datatable'", $dataTable);
        $this->assertStringContainsString("adoptLegacyFilter('#jv-excel-pop'", $factory);
        $this->assertStringContainsString('finance-voucher-system.css', $layout);
    }

    public function test_cr_pagination_renderer_is_used_for_all_affected_registers(): void
    {
        $core=file_get_contents(public_path('js/finance-voucher-components.js'));
        $cheque=file_get_contents(resource_path('views/admin/finance/cheque/intake.blade.php'));
        $online=file_get_contents(resource_path('views/admin/finance/online/intake.blade.php'));
        $jv=file_get_contents(resource_path('views/admin/reports/vouchers/index.blade.php'));
        $cr=file_get_contents(resource_path('views/admin/finance/voucher/daily_directory.blade.php'));

        $this->assertStringContainsString('$.blueVoucherPagination.render(', $core);
        $this->assertStringContainsString('attachDataTablePager(selector)', $core);
        foreach (['#jv-directory','#journalTable','#bmDraftTable','#example1'] as $id) {
            $this->assertStringContainsString($id, $core);
        }
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $cheque);
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $online);
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $cr);
        $this->assertStringContainsString('id="jv-directory"', $jv);
        $this->assertStringContainsString('fvc-pagination-footer', $core);
        $this->assertStringContainsString('fvc-pagination-info', $core);
    }

    public function test_shared_filter_state_preserves_business_specific_date_range_and_project_filters(): void
    {
        $cheque=file_get_contents(app_path('Http/Controllers/Finance/ChequeIntakeController.php'));
        $online=file_get_contents(app_path('Http/Controllers/Finance/OnlineIntakeController.php'));
        $cv=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));
        $core=file_get_contents(public_path('js/finance-voucher-components.js'));

        foreach (['column_date_from','column_date_to'] as $field) {
            $this->assertStringContainsString($field,$cheque);
            $this->assertStringContainsString($field,$online);
        }
        foreach (['fvd_date_from','fvd_date_to'] as $field) {
            $this->assertStringContainsString($field,$cv);
        }
        $this->assertStringContainsString('where(\'c.project_id\', $project)', $cheque);
        $this->assertStringContainsString('where(\'project_id\',$project)', $online);
        $this->assertStringContainsString('where(\'project_id\', $selectedProjectId)', $cv);
        // Assert the actual DataTables paging operation, not a stale code comment.
        $this->assertStringContainsString("api.page(Math.floor(start/length)).draw('page')", $core);
        $this->assertStringContainsString('api.page.info()', $core);
    }

    public function test_pager_detaches_native_controls_and_mounts_at_voucher_card_bottom(): void
    {
        $script=file_get_contents(public_path('js/finance-voucher-components.js'));
        $css=file_get_contents(public_path('css/finance-voucher-components.css'));
        $this->assertStringContainsString("closest('.bm-voucher-table-card,.jv-card,.custom_card')", $script);
        $this->assertStringContainsString('$native.detach()', $script);
        $this->assertStringContainsString('$info.detach()', $script);
        $this->assertStringContainsString('$host.append($footer)', $script);
        $this->assertStringContainsString('$table.data(\'fvc-pager\',true)', $script);
        $this->assertStringContainsString('draw.dt.fvcPaginator length.dt.fvcPaginator', $script);
        $this->assertStringContainsString('.fvc-pagination-host>.fvc-pagination-footer', $css);
        $this->assertStringContainsString('bottom:0!important', $css);
        $this->assertStringContainsString('padding-bottom:var(--fvd-footer-height,40px)', $css);
    }


    public function test_table_remains_top_aligned_while_single_pager_is_pinned_to_card_bottom(): void
    {
        $css=file_get_contents(public_path('css/finance-voucher-components.css'));
        $layout=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));

        $this->assertStringContainsString('.fvc-pagination-host .fvd-datatable-wrapper>.fvd-datatable-scroll', $css);
        $this->assertStringContainsString('flex:0 1 auto!important', $css);
        $this->assertStringContainsString('justify-content:flex-start!important', $css);
        $this->assertStringContainsString('.fvd-datatable-wrapper::after', $css);
        $this->assertStringContainsString('flex:1 1 auto', $css);
        $this->assertStringContainsString('.fvc-pagination-host>.fvc-pagination-footer', $css);
        $this->assertStringContainsString('bottom:0!important', $css);
        $this->assertStringContainsString("asset('css/finance-voucher-components.css')", $layout);
    }

    public function test_extra_charge_has_one_visible_voucher_card_frame(): void
    {
        $extra=file_get_contents(resource_path('views/admin/booking/extra_charge.blade.php'));
        $css=file_get_contents(public_path('css/finance-voucher-components.css'));

        $this->assertStringContainsString('class="card bm-card-frame-reset"', $extra);
        $this->assertStringContainsString('<x-finance.voucher-table-card', $extra);
        $this->assertStringContainsString('.bm-card-frame-reset>.card-body', $css);
        $this->assertStringContainsString('background:transparent!important;border:0!important;', $css);
        $this->assertStringContainsString('.fvc-pagination-host>.fvc-pagination-footer', $css);
    }

    public function test_commission_directory_has_stable_columns_and_friendly_ajax_failure_state(): void
    {
        $view=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));

        $this->assertStringContainsString('<th>Actions</th>', $view);
        $this->assertStringNotContainsString("@canany(['edit jv', 'delete jv'])", $view);
        $this->assertStringContainsString("data: 'actions'", $view);
        $this->assertStringContainsString('id="cv-list-error"', $view);
        $this->assertStringContainsString('Session expired. Refresh the page', $view);
        $this->assertStringContainsString('Commission vouchers could not be loaded.', $view);
        $this->assertStringContainsString("roles->first()?->name ?? ''", $controller);
        $this->assertStringContainsString("\$x['vouchers'] = collect();", $controller);
    }


    public function test_commission_first_ajax_request_has_initialized_date_state(): void
    {
        $adapter=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $view=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));

        $this->assertStringContainsString("dates:{from:'',to:''}", $adapter);
        $this->assertStringContainsString('columnState.dates.from', $view);
        $this->assertStringContainsString('columnState.dates.to', $view);
    }

    public function test_commission_uses_correct_accounting_columns_and_ajax_draft_submission(): void
    {
        $view=file_get_contents(resource_path('views/admin/reports/vouchers/commision_voucher.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));

        $this->assertStringContainsString('name="debit[]"', $view);
        $this->assertStringContainsString('name="credit[]"', $view);
        $this->assertStringContainsString('id="totalDebit"', $view);
        $this->assertStringContainsString('id="totalCredit"', $view);
        $this->assertStringContainsString('fvc-entry-table', $view);
        $this->assertStringNotContainsString('background-color: black !important', $view);
        $this->assertStringContainsString("$('#journalTable .add-row').first().trigger('click')", $view);
        $this->assertStringContainsString("dataType:'json'", $view);
        $this->assertStringContainsString('finance:commission-saved', $view);
        $this->assertStringContainsString('V2IsolatedConnectionGuard::assertAllowed()', $controller);
        $this->assertStringContainsString('commissionPilotCreateAllowed', $view);
        $this->assertStringContainsString('V2OriginalWorkflowAccess::enabled()', $controller);
        $this->assertStringContainsString("'voucher_id' => " . '$id', $controller);
    }

    public function test_extra_charge_ajax_and_shared_modals_keep_original_posting_authority(): void
    {
        $script=file_get_contents(public_path('js/finance-voucher-header-filters.js'));
        $controller=file_get_contents(app_path('Http/Controllers/BookingController.php'));
        $css=file_get_contents(public_path('css/finance-voucher-components.css'));
        $actions=file_get_contents(public_path('js/blue-action-menu.js'));

        $this->assertStringContainsString('submitExtraAjax($form', $script);
        $this->assertStringContainsString('submitExtraAjax($(this)', $script);
        $this->assertStringContainsString('new FormData($entry[0])', $script);
        $this->assertStringContainsString('fvc-modern-modal fvc-modal-purple', $script);
        $this->assertStringContainsString('fvc-modern-modal fvc-modal-blue', $script);
        $this->assertStringContainsString('if ($request->expectsJson())', $controller);
        $this->assertStringContainsString('Charge type saved.', $controller);
        $extra=file_get_contents(resource_path('views/admin/booking/extra_charge.blade.php'));
        $this->assertStringContainsString('data-blue-action-label="View / Approve"', $extra);
        $this->assertStringContainsString('.fvc-modern-modal .modal-header', $css);
        foreach (['btn-edit','btn-view','btn-delete','btn-view-changes'] as $class) {
            $this->assertStringContainsString($class, $actions);
        }
    }


    public function test_commission_status_schema_repair_remains_explicit_and_guarded(): void
    {
        $legacy=file_get_contents(database_path('migrations/2025_09_25_142048_create_commision_vouchers_table.php'));
        $workflow=file_get_contents(app_path('Services/Finance/V2CommissionDraftWorkflow.php'));
        $repair=file_get_contents(app_path('Console/Commands/RepairV2CommissionDraftStatus.php'));
        $controller=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));

        $this->assertStringContainsString("'pending', 'approved', 'rejected'", $legacy);
        $this->assertStringContainsString("'status' => 'draft'", $workflow);
        $this->assertStringContainsString('V2IsolatedConnectionGuard::isAllowed()', $repair);
        $this->assertStringContainsString('finance:repair-v2-commission-status', $repair);
        $this->assertStringContainsString("option('apply')", $repair);
        $this->assertStringContainsString("ALTER TABLE", $repair);
        $this->assertStringContainsString("report(" . '$e' . ")", $controller);
        $this->assertStringContainsString("Commission status storage needs", $controller);
    }

    public function test_shared_voucher_form_feedback_is_loaded_from_master(): void
    {
        $master=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $feedback=file_get_contents(public_path('js/finance-voucher-feedback.js'));
        $view=file_get_contents(resource_path('views/admin/reports/vouchers/commision_voucher.blade.php'));
        $css=file_get_contents(public_path('css/finance-voucher-components.css'));

        $this->assertStringContainsString('finance-voucher-feedback.js', $master);
        $this->assertStringContainsString('financeVoucherFeedback', $feedback);
        $this->assertStringContainsString('enhanceSelects', $feedback);
        $this->assertStringContainsString('fvc-modal-skeleton', $css);
        $this->assertStringContainsString('fvc-tom-select', $view);
        $this->assertStringContainsString("feedback.error(xhr", $view);
        $this->assertStringNotContainsString("alert('Error:", $view);
    }

}
