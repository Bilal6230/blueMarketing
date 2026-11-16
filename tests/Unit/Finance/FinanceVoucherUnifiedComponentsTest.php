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

    public function test_cv_and_extra_use_cr_live_filters_and_real_shared_table_skeleton(): void
    {
        $adapter=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $feedback=file_get_contents(public_path('js/finance-voucher-feedback.js'));
        $styles=file_get_contents(public_path('css/finance-voucher-components.css'));
        $cv=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));

        $this->assertStringContainsString('function scheduleRefresh()', $adapter);
        $this->assertStringContainsString("widget.dates(state.dates", $adapter);
        $this->assertStringNotContainsString("text:'Apply'", $adapter);
        $this->assertStringNotContainsString('draftFilters=', $adapter);
        $this->assertStringContainsString('$.blueTableSkeleton.show(', $feedback);
        // The table loader must reuse CR rows, while the separate form-modal
        // loader is still allowed to use its own fvc-skeleton-shimmer markup.
        $tableStart=strpos($feedback, 'function tableSkeleton(');
        $tableEnd=strpos($feedback, 'function bindTableSkeleton(', $tableStart === false ? 0 : $tableStart);
        $this->assertNotFalse($tableStart);
        $this->assertNotFalse($tableEnd);
        $tableLoader=substr($feedback, $tableStart, $tableEnd-$tableStart);
        $this->assertStringContainsString('$.blueTableSkeleton.show(', $tableLoader);
        $this->assertStringNotContainsString("class':'fvc-skeleton-shimmer'", $tableLoader);
        $this->assertStringContainsString('fvc-skeleton-table', $styles);
        $this->assertStringContainsString('.cv-directory .cv-shell>.card-body', $styles);
        $this->assertStringContainsString("bindTableSkeleton('#journalTable')", $cv);
    }

    public function test_cv_visual_contract_keeps_headings_accent_and_single_modal_reload(): void
    {
        $feedback=file_get_contents(public_path('js/finance-voucher-feedback.js'));
        $css=file_get_contents(public_path('css/finance-voucher-components.css'));
        $header=file_get_contents(public_path('js/finance-voucher-header-filters.js'));
        $cv=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));

        $this->assertStringContainsString('$heading.outerHeight()-$wrap.offset().top', $feedback);
        $this->assertStringContainsString('fvc-table-skeleton', $feedback);
        $this->assertStringContainsString('color-mix(in srgb,var(--fvd-accent', $css);
        $this->assertStringContainsString('border-top:3px solid var(--fvd-accent', $css);
        $this->assertStringContainsString('.fvc-entry-table tbody td:has(> .ts-wrapper)', $css);
        $this->assertStringContainsString('var commissionNeedsRefresh=false;', $header);
        $this->assertStringContainsString('if(commissionNeedsRefresh)', $header);
        $this->assertStringContainsString('d.fvd_date_from = columnState.dates.from;', $cv);
        $this->assertStringContainsString('d.fvd_date_to = columnState.dates.to;', $cv);
        $this->assertStringContainsString("whereDate('date','>=',", $controller);
        $this->assertStringContainsString("whereDate('date','<=',", $controller);
    }

    public function test_commission_save_is_single_ajax_request_with_visible_validation_errors(): void
    {
        $view=file_get_contents(resource_path('views/admin/reports/vouchers/commision_voucher.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));
        $this->assertStringContainsString('<form id="journalForm" novalidate', $view);
        $this->assertStringContainsString("$('#journalForm').on('submit.fvcCv'", $view);
        $this->assertStringContainsString('Select Account and Sub-Account for every line.', $view);
        $this->assertStringContainsString('Debit and Credit must be greater than zero and equal.', $view);
        $this->assertStringContainsString("name:'date',value:date", $view);
        $this->assertStringContainsString('finance:commission-saved', $view);
        $this->assertStringNotContainsString("$('#confirmationModal').modal('show')", $view);
        $this->assertStringContainsString('V2IsolatedConnectionGuard::assertAllowed()', $controller);
    }

    public function test_extra_draft_and_sales_share_finance_controls_without_legacy_select2_conflicts(): void
    {
        $extra=file_get_contents(resource_path('views/admin/booking/extra_charge.blade.php'));
        $draft=file_get_contents(resource_path('views/admin/finance/voucher/draft_voucher.blade.php'));
        $sales=file_get_contents(resource_path('views/admin/reports/vouchers/index.blade.php'));
        $feedback=file_get_contents(public_path('js/finance-voucher-feedback.js'));
        $header=file_get_contents(public_path('js/finance-voucher-header-filters.js'));
        $css=file_get_contents(public_path('css/finance-voucher-components.css'));

        $this->assertStringContainsString('name="charge_type_id"', $extra);
        $this->assertStringContainsString('fvc-tom-select" name="customer_id"', $extra);
        $this->assertStringContainsString('fvc-tom-select" name="plot_id"', $extra);
        $this->assertStringNotContainsString('class="form-control select2"', $extra);
        $this->assertStringContainsString("maxItems:element.multiple?null:1", $feedback);
        $this->assertStringContainsString("hasClass('fvc-modal-purple')", $feedback);
        $this->assertStringContainsString('refreshExtraDirectory()', $header);
        $this->assertStringNotContainsString('window.location.reload()', $header);
        $this->assertStringContainsString('mountExtraPaymentActions()', $header);
        $this->assertStringContainsString("draw.dt.fvcExtraPayments", $header);
        $this->assertStringContainsString('skeleton($loadingBody,true)', $header);
        $this->assertStringContainsString('skeleton($loadingBody,false)', $header);
        $this->assertStringContainsString("enhanceSelects($('#filter_head,#filter_subhead'))", $draft);
        $this->assertStringNotContainsString("$('#filter_head, #filter_subhead').select2", $draft);
        $this->assertStringContainsString('fvc-draft-list-card', $header);
        $this->assertStringContainsString("enhanceSelects($('.js-tomselect'))", $draft);
        $this->assertStringNotContainsString('new TomSelect(el,', $draft);
        $this->assertStringContainsString('bindTableSkeleton($table)', $sales);
        $this->assertStringContainsString("route('sales.voucher.index')", $sales);
        $this->assertStringContainsString('.fvc-modern-modal .modal-body .input-group>.ts-wrapper.single', $css);
    }

    public function test_extra_dropdowns_filter_dates_and_columns_follow_shared_cr_contract(): void
    {
        $feedback=file_get_contents(public_path('js/finance-voucher-feedback.js'));
        $adapter=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $styles=file_get_contents(public_path('css/finance-voucher-components.css'));
        $header=file_get_contents(public_path('js/finance-voucher-header-filters.js'));

        $this->assertStringContainsString("dropdownParent:\$element.closest('.fvc-entry-table').length?'body':null", $feedback);
        $this->assertStringContainsString('var matched=source.match(', $adapter);
        $this->assertStringContainsString('clientSkeletonTimer=setTimeout', $adapter);
        $this->assertStringContainsString('.ts-wrapper.form-control', $styles);
        $this->assertStringContainsString('th:nth-child(5){width:9%', $styles);
        $this->assertStringContainsString('th:nth-child(7)', $styles);
        $this->assertStringContainsString('show.bs.modal.fvcExtraLoad', $header);
        $this->assertStringContainsString('shown.bs.modal.fvcExtraLoad', $header);
        $this->assertStringNotContainsString('window.location.reload()', $header);
    }

    public function test_client_side_extra_date_filter_has_real_digit_and_whitespace_regex(): void
    {
        $adapter=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $this->assertStringContainsString('replace(/\s+/g,', $adapter);
        $this->assertStringContainsString('match(/\d{4}-\d{2}-\d{2}/)', $adapter);
    }

    public function test_journal_and_draft_modal_ajax_success_contract_and_refresh(): void
    {
        $journalView=file_get_contents(resource_path('views/admin/reports/vouchers/journal_voucher.blade.php'));
        $journalList=file_get_contents(resource_path('views/admin/reports/vouchers/index.blade.php'));
        $journalController=file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $draftView=file_get_contents(resource_path('views/admin/finance/voucher/draft_voucher.blade.php'));
        $draftController=file_get_contents(app_path('Http/Controllers/LedgerController.php'));
        $shared=file_get_contents(public_path('js/finance-voucher-header-filters.js'));

        $this->assertStringContainsString("dataType:'json',headers:{Accept:'application/json'}", $journalView);
        $this->assertStringContainsString('finance:journal-saved', $journalView);
        $this->assertStringContainsString('finance:journal-saved.fvc', $journalList);
        $this->assertStringNotContainsString("$('#journalForm')[0].submit()", $journalView);
        $this->assertStringContainsString('private function voucherWriteResponse', $journalController);
        $this->assertStringContainsString("'status' => 'success'", $journalController);
        $this->assertStringContainsString('finance:draft-saved', $draftView);
        $this->assertStringContainsString('finance:draft-saved.fvc', $shared);
        $this->assertStringContainsString("data('fvc-post-as-voucher',true)", $draftView);
        $this->assertStringContainsString('return $request->expectsJson()', $draftController);
    }

    public function test_journal_posting_handles_optional_approval_schema_without_unconditional_lookup(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $this->assertStringContainsString(
            "Schema::hasTable('daily_voucher_approvals')",
            $controller
        );
        $this->assertStringContainsString(
            "->where('source_id',\$savedVoucherId)",
            $controller
        );
    }

    public function test_browser_closeout_actions_keep_finance_lifecycle_and_readable_modal_errors(): void
    {
        $draft=file_get_contents(resource_path('views/admin/finance/voucher/draft_voucher.blade.php'));
        $shared=file_get_contents(public_path('js/finance-voucher-header-filters.js'));
        $feedback=file_get_contents(public_path('js/finance-voucher-feedback.js'));
        $menu=file_get_contents(public_path('js/blue-action-menu.js'));
        $extra=file_get_contents(resource_path('views/admin/booking/extra_charge.blade.php'));
        $journal=file_get_contents(resource_path('views/admin/reports/vouchers/index.blade.php'));
        $journalEdit=file_get_contents(resource_path('views/admin/reports/vouchers/edit_journal_voucher.blade.php'));
        $actions=file_get_contents(resource_path('views/admin/reports/vouchers/actions.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));

        $this->assertStringContainsString('$.blueVoucherRegisterActions.enhance($draftTable)', $shared);
        $this->assertStringContainsString("dropdownParent:\$element.closest('.fvc-entry-table').length?'body':null", $feedback);
        $this->assertStringContainsString('$original[0].click()', $menu);
        $this->assertStringContainsString('id="fvc-extra-view-facts"', $extra);
        $this->assertStringNotContainsString('<span>Ali </span>', $extra);
        $this->assertStringContainsString("click.fvcExtraView", $extra);
        $this->assertStringContainsString("data.transaction_type==='EC'?data.amount_in:data.amount_out", $extra);
        $booking=file_get_contents(app_path('Http/Controllers/BookingController.php'));
        $this->assertStringContainsString("'amount_in' => \$dataList->amount_in", $booking);
        $this->assertStringContainsString("? 'Outstanding Charge'", $booking);
        $this->assertStringContainsString('Extra Charge ', $extra);
        $this->assertStringContainsString('fvcJournalEdit', $journalEdit);
        $this->assertStringNotContainsString("$('#journalForm')[0].submit()", $journalEdit);
        $this->assertStringContainsString("headers:{Accept:'application/json'}", $journal);
        $this->assertStringContainsString('jv-reversal-reason', $journal);
        $this->assertStringContainsString("submit.fvcJournalReverse", $journal);
        $this->assertMatchesRegularExpression('/voucherWriteResponse\\(\\s*request\\(\\),/', $controller);
        $this->assertStringContainsString("data('fvc-post-as-voucher',true)", $draft);
        $this->assertStringContainsString("voucher->type === 'SV'", $actions);
        $this->assertStringContainsString("voucher->type === 'SV') ? 'SV' : 'JV'", $controller);
    }

    public function test_jv_v2_edit_preflight_rejects_posted_sources_and_cv_reversal_has_proper_form_layout(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $cv=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));

        // GET and POST enforce the same V2 immutability boundary; never
        // present an editable modal that is guaranteed to fail on Save.
        $this->assertStringContainsString('A V2 edit form must not open if updateUnposted() will reject it.', $controller);
        $this->assertStringContainsString("finance_posting_journal_sources", $controller);
        $this->assertStringContainsString('where(\'journal_voucher_id\',$sourceId)', $controller);
        $this->assertStringContainsString("orWhereIn('journal_voucher_detail_id'", $controller);
        $this->assertStringContainsString('abort_if($posted || $attributed', $controller);
        $this->assertStringContainsString('V2JournalDraftWorkflow', $controller);

        // The CV reversal form must OWN the body and footer rather than
        // render its fields inline beside the Cancel button.
        $this->assertMatchesRegularExpression('/<form id="deleteForm"[^>]*>.*?<div class="modal-body">.*?id="reversalDate".*?id="reversalReason".*?<div class="modal-footer/s', $cv);
        $this->assertStringContainsString('fvc-modern-modal fvc-modal-blue', $cv);
        $this->assertStringContainsString("route('commision.voucher.delete', ':id')", $cv);
        $this->assertStringContainsString("@method('DELETE')", $cv);
    }

    public function test_extra_approval_and_pending_collections_use_ajax_and_real_bank_validation(): void
    {
        $extra=file_get_contents(resource_path('views/admin/booking/extra_charge.blade.php'));
        $shared=file_get_contents(public_path('js/finance-voucher-header-filters.js'));
        $booking=file_get_contents(app_path('Http/Controllers/BookingController.php'));
        $input=file_get_contents(app_path('Services/Finance/V2OriginalVoucherInput.php'));

        $this->assertStringContainsString('id="fvc-extra-approval-form"', $extra);
        $this->assertStringContainsString('fvc-approval-feedback', $extra);
        $this->assertStringContainsString('submit.fvcExtraApproval', $extra);
        $this->assertStringContainsString('Select valid Debit and Credit posting accounts before approval.', $extra);
        $this->assertStringContainsString('finance:extra-changed', $extra);
        $this->assertStringContainsString('finance:extra-changed.fvc', $shared);
        $this->assertStringContainsString('submit.fvcExtraCollection', $shared);
        $this->assertStringContainsString("attr('novalidate','novalidate')", $shared);
        $this->assertStringContainsString('Complete all required collection fields before saving.', $shared);
        $this->assertStringContainsString('syncChequeFields', $shared);
        $this->assertStringContainsString('Pending bank collection: Debit/Credit posting accounts are selected at clearance', $shared);
        $this->assertStringContainsString("toggleClass('d-none',pending)", $shared);
        $this->assertStringContainsString('fvc-cheque-purpose', $extra);
        $this->assertStringContainsString('receiptBanks', $extra);
        $this->assertStringNotContainsString('placeholder="Bank ID"', $extra);
        $this->assertStringContainsString("Rule::exists('banks','id')", $input);
        $this->assertStringContainsString("Schema::hasTable('banks')", $booking);
        $this->assertStringContainsString("'status' => 'success', 'message' => 'Voucher approved and posted.'", $booking);
        $this->assertStringContainsString('Approval cannot be repeated', $booking);
    }

    public function test_jv_editor_uses_shared_tomselect_and_declares_pilot_policy_before_save(): void
    {
        $view=file_get_contents(resource_path('views/admin/reports/vouchers/journal_voucher.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $actions=file_get_contents(resource_path('views/admin/reports/vouchers/actions.blade.php'));

        $this->assertStringContainsString('fvc-tom-select account-select', $view);
        $this->assertStringContainsString('<form id="journalForm" novalidate', $view);
        $this->assertStringContainsString('Select Account and Sub-Account on every voucher line.', $view);
        $this->assertStringContainsString('fvc-tom-select sub-account-select', $view);
        $this->assertStringContainsString('table table-bordered fvc-entry-table', $view);
        $this->assertStringContainsString("enhanceSelects(\$('#journalTable select.fvc-tom-select'))", $view);
        $this->assertStringContainsString("ts.clearOptions()", $view);
        $this->assertStringContainsString("ts.addOption(item)", $view);
        $this->assertStringNotContainsString("subAccountSelect.empty().append", $view);
        $this->assertStringContainsString('journalPilotApprovalBlocked', $view);
        $this->assertStringContainsString('journalPilotApprovalBlocked', $controller);
        $this->assertStringContainsString('Rejected JV revision is unavailable in V2 mode.', $controller);
        $this->assertStringContainsString('V2OriginalWorkflowAccess::selected()', $actions);
        $this->assertStringNotContainsString('class="form-control select2 account-select"', $view);
    }

    public function test_explicit_local_normal_erp_mode_preserves_jv_history_and_avoids_pilot_approval(): void
    {
        $access=file_get_contents(app_path('Services/Finance/V2OriginalWorkflowAccess.php'));
        $approval=file_get_contents(app_path('Services/Finance/DailyVoucherApprovalWorkflow.php'));
        $mutation=file_get_contents(app_path('Services/Finance/VoucherApprovalMutationGuard.php'));
        $actions=file_get_contents(resource_path('views/admin/reports/vouchers/actions.blade.php'));
        $config=file_get_contents(config_path('finance.php'));

        $this->assertStringContainsString("'normal_erp_mode' => env('FINANCE_NORMAL_ERP_MODE', false)", $config);
        $this->assertStringContainsString("!str_starts_with(\$database, 'bluemarketing_v2_')", $access);
        $this->assertStringContainsString('if (self::normalErpMode()) return false;', $access);
        $this->assertStringContainsString('V2OriginalWorkflowAccess::normalErpMode()', $approval);
        $this->assertStringContainsString('V2OriginalWorkflowAccess::normalErpMode()', $mutation);
        $this->assertStringContainsString('V2OriginalWorkflowAccess::normalErpMode()', $actions);
        $this->assertStringContainsString("Correction reversal:", file_get_contents(app_path('Http/Controllers/JournalVoucherController.php')));
    }

}
