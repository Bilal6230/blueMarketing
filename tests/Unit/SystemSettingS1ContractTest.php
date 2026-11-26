<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Verifies the Settings S1 screen contract without relying on production data.
 * Browser verification is a separate release gate.
 */
final class SystemSettingS1ContractTest extends TestCase
{
    private function view(): string
    {
        return file_get_contents(resource_path('views/admin/setting.blade.php'));
    }

    public function test_information_and_contact_are_one_inline_tab(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('Information &amp; Contact', $view);
        $this->assertStringContainsString('id="settings-information-form"', $view);
        $this->assertStringContainsString("'contact'=>'Contact Details'", $view);
        $this->assertStringContainsString('id="information" data-settings-direct="1"', $view);
        $this->assertStringNotContainsString('href="#contact"', $view);
        $this->assertStringContainsString(':not(#settings-information-form)', $view);
    }

    public function test_receipt_header_settings_moved_to_voucher_display_with_ajax_confirmations(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('id="settings-receipt-header-form"', $view);
        $this->assertStringContainsString('Voucher Print Headers', $view);
        $this->assertStringContainsString("name=\"toggles[{{ \\$keyType }}]\"", $view);
        $this->assertStringContainsString("change','.settings-receipt-toggle'", $view);
        $this->assertStringContainsString('Update Print Header?', $view);
        $this->assertStringContainsString('putSettings($receipt,payload', $view);
        $this->assertStringContainsString(':not(#settings-receipt-header-form)', $view);
    }

    public function test_form_saving_is_ajax_without_full_page_redirect(): void
    {
        $view = $this->view();
        $controller = file_get_contents(app_path('Http/Controllers/SettingController.php'));

        $this->assertStringContainsString("type:'POST',data:formData,processData:false", $view);
        $this->assertStringContainsString("headers:{Accept:'application/json'", $view);
        $this->assertStringContainsString('e.stopImmediatePropagation()', $view);
        $this->assertStringContainsString('response()->json', $controller);
        $this->assertStringContainsString("if ($request->expectsJson())", $controller);
        $this->assertStringContainsString('DB::transaction(', $controller);
    }

    public function test_card_scrolling_is_isolated_from_page_and_footer(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('.settings-main-card .tab-content{flex:1;min-height:0;overflow:auto', $view);
        $this->assertStringContainsString('settings-s1-viewport', $view);
        $this->assertStringContainsString('body.settings-s1-viewport .main-footer{position:fixed', $view);
        $this->assertStringContainsString('fitSettingsCard()', $view);
    }
}
