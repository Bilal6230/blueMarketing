<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernAccountingModalScrollContractTest extends TestCase
{
    private function source(string $path): string
    {
        $content = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($content, $path);

        return $content;
    }

    public function test_document_scroll_is_locked_and_table_scroll_stays_inside_card(): void
    {
        $css = $this->source('public/css/modern-account-directory.css');
        $js = $this->source('public/js/modern-account-directory.js');

        self::assertStringContainsString("addClass('bm-account-directory-page')", $js);
        self::assertStringContainsString('body.bm-account-directory-page{overflow:hidden!important}', $css);
        self::assertStringContainsString('height:calc(100dvh - 171px)!important', $css);
        self::assertStringContainsString('max-height:calc(100dvh - 171px)!important', $css);
        self::assertStringContainsString('body.bm-account-directory-page .bm-account-card>.bm-account-table-wrap', $css);
        self::assertStringContainsString('overflow-y:auto!important', $css);
        self::assertStringContainsString('body.bm-account-directory-page .bm-account-footer{flex-shrink:0!important}', $css);
        self::assertStringContainsString('.bm-modern-accounts .bm-account-table thead th{position:sticky', $css);
        self::assertStringNotContainsString('.bm-modern-accounts .content .row{flex:1 1 auto', $css);
    }

    public function test_new_account_modals_use_full_width_grid_fields_and_responsive_scroll(): void
    {
        $css = $this->source('public/css/modern-account-directory.css');
        $js = $this->source('public/js/modern-account-directory.js');

        self::assertStringContainsString('.bm-modern-accounts .bm-account-creation-form form>.row', $css);
        self::assertStringContainsString('.bm-modern-accounts .bm-account-create-modal form>.row', $css);
        self::assertStringContainsString('flex-wrap:wrap!important;width:100%', $css);
        self::assertStringContainsString('min-width:0!important;min-height:38px!important', $css);
        self::assertStringContainsString('.bm-modern-accounts .bm-account-create-modal .modal-body{padding:0!important;max-height:', $css);
        self::assertStringContainsString('.bm-account-finance-modal .modal-body{max-height:', $css);
        self::assertStringContainsString("dropdownParent:$modal,width:'100%'", $js);
        self::assertStringContainsString('.bm-account-create-modal', $js);
    }

    public function test_edit_account_and_mapping_modals_keep_fields_and_finance_status_reason(): void
    {
        foreach ([
            'resources/views/admin/finance/accounting/head_accounts.blade.php',
            'resources/views/admin/finance/accounting/child_accounts.blade.php',
            'resources/views/admin/finance/accounting/category_index.blade.php',
        ] as $path) {
            $view = $this->source($path);
            self::assertStringContainsString('bm-account-finance-modal', $view);
            self::assertStringContainsString('modal-edit', $view) || self::assertStringContainsString('editModal', $view);
        }
        $head = $this->source('resources/views/admin/finance/accounting/head_accounts.blade.php');
        $party = $this->source('resources/views/admin/finance/accounting/child_accounts.blade.php');
        $joint = $this->source('resources/views/admin/finance/accounting/category_index.blade.php');
        self::assertStringContainsString('Main Account Name', $head);
        self::assertStringContainsString('head_status_change_reason', $head);
        self::assertStringContainsString('subhead_status_change_reason', $party);
        self::assertStringContainsString('name="acct_type"', $head);
        self::assertStringContainsString('name="cnic"', $party);
        self::assertStringContainsString('name="phone"', $party);
        self::assertStringContainsString('Edit Joint Account Mapping', $joint);
        self::assertStringContainsString('id="edit_heads"', $joint);
    }

    public function test_delete_modal_forms_wrap_confirmation_and_submit_and_preserve_409(): void
    {
        foreach ([
            ['resources/views/admin/finance/accounting/head_accounts.blade.php', 'head_destroy'],
            ['resources/views/admin/finance/accounting/child_accounts.blade.php', 'subhead_destroy'],
        ] as [$path, $route]) {
            $view = $this->source($path);
            $start = strpos($view, 'id="modal-delete"');
            self::assertNotFalse($start);
            $tail = substr($view, $start);
            $form = strpos($tail, "route('accounting.".$route."')");
            $body = strpos($tail, '<div class="modal-body">');
            $footer = strpos($tail, '<div class="modal-footer');
            $end = strpos($tail, '</form>');
            self::assertNotFalse($form);
            self::assertNotFalse($body);
            self::assertNotFalse($footer);
            self::assertNotFalse($end);
            self::assertLessThan($body, $form);
            self::assertLessThan($footer, $body);
            self::assertLessThan($end, $footer);
        }
        $controller = $this->source('app/Http/Controllers/AccountingController.php');
        self::assertStringContainsString('Main account deletion is unavailable', $controller);
        self::assertStringContainsString('Party account deletion is unavailable', $controller);
    }
}
