<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class CompactVoucherDirectoryLayoutTest extends TestCase
{
    public function test_shared_compact_header_filter_layer_covers_existing_voucher_families(): void
    {
        $script=file_get_contents(public_path('js/finance-voucher-header-filters.js'));
        $styles=file_get_contents(public_path('css/finance-voucher-compact.css'));
        $master=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));

        $this->assertStringContainsString('finance-voucher-header-filters.js', $master);
        $this->assertStringContainsString('finance-voucher-compact.css', $master);
        foreach ([
            '#dv-page', '.jv-page', '.cv-directory', '#bankTransferApp',
            '#plotReceiptApp', '#plotPurchaseApp', '.cheque-register-page',
            '.online-register-page', '.bm-modern-draft', '.bm-modern-extra',
        ] as $selector) {
            $this->assertStringContainsString($selector, $script);
        }
        foreach (['bm-quick-filter-modal', 'bm-heading-filter','bm-header-new',
            'bm-inline-link-filter', 'bm-commission-entry-modal','bm-extra-entry-modal'] as $class) {
            $this->assertStringContainsString($class, $styles);
        }
        $this->assertStringContainsString('detach()', $script);
        $this->assertStringContainsString('link_status', $script);
        $this->assertStringContainsString('Vouchers', $script);
        $this->assertStringNotContainsString('document.createElement(', $script);
        $this->assertStringNotContainsString('document.createTextNode(', $script);
    }

    public function test_draft_commission_and_extra_charge_preserve_original_finance_forms(): void
    {
        $draft=file_get_contents(resource_path('views/admin/finance/voucher/draft_voucher.blade.php'));
        $commission=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));
        $commissionCreate=file_get_contents(resource_path('views/admin/reports/vouchers/commision_voucher.blade.php'));
        $commissionEdit=file_get_contents(resource_path('views/admin/reports/vouchers/edit_commision_voucher.blade.php'));
        $extra=file_get_contents(resource_path('views/admin/booking/extra_charge.blade.php'));

        $this->assertStringContainsString('bm-modern-draft', $draft);
        $this->assertStringContainsString('bm-draft-scroll', $draft);
        $this->assertStringContainsString('btn-edit', $draft);
        $this->assertStringContainsString('btn-delete', $draft);
        $this->assertStringContainsString('route(\'finance.voucher.approve\'', $draft);

        $this->assertStringContainsString('cv-directory', $commission);
        $this->assertStringContainsString('commision.voucher.create', $commission);
        $this->assertStringContainsString('journalTable', $commission);
        $this->assertStringContainsString("request()->boolean('embed')", $commissionCreate);
        $this->assertStringContainsString("request()->boolean('embed')", $commissionEdit);
        $this->assertStringContainsString("route('commision.voucher.store')", $commissionCreate);
        $this->assertStringContainsString("route('commision.voucher.update'", $commissionEdit);

        $this->assertStringContainsString('bm-modern-extra', $extra);
        $this->assertStringContainsString('bm-extra-heading', $extra);
        $this->assertStringContainsString('id="voucherForm"', $extra);
        $this->assertStringContainsString("route('booking.customer.deposit')", $extra);
        $this->assertStringContainsString('bm-extra-save', $extra);
        $this->assertStringNotContainsString('data-target="#confirmModal"', $extra);
    }
}
