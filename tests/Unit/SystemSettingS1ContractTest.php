<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Verifies the Settings S1 screen contract without relying on production data.
 * Browser verification is a separate release gate.
 */
final class SystemSettingS1ContractTest extends TestCase
{
    private function settingsBladeSource(): string
    {
        return file_get_contents(resource_path('views/admin/setting.blade.php'));
    }

    public function test_information_and_contact_are_one_inline_tab(): void
    {
        $view = $this->settingsBladeSource();

        $this->assertStringContainsString('Information &amp; Contact', $view);
        $this->assertStringContainsString('id="settings-information-form"', $view);
        $this->assertStringContainsString("'contact'=>'Contact Details'", $view);
        $this->assertStringContainsString('id="information" data-settings-direct="1"', $view);
        $this->assertStringNotContainsString('href="#contact"', $view);
        $this->assertStringContainsString(':not(#settings-information-form)', $view);
    }

    public function test_receipt_header_settings_moved_to_voucher_display_with_ajax_confirmations(): void
    {
        $view = $this->settingsBladeSource();

        $this->assertStringContainsString('id="settings-receipt-header-form"', $view);
        $this->assertStringContainsString('Voucher Print Headers', $view);
        $this->assertStringContainsString('name="toggles[{{ $keyType }}]"', $view);
        $this->assertStringContainsString("change','.settings-receipt-toggle'", $view);
        $this->assertStringContainsString('Update Print Header?', $view);
        $this->assertStringContainsString('putSettings($receipt,payload', $view);
        $this->assertStringContainsString(':not(#settings-receipt-header-form)', $view);
    }

    public function test_form_saving_is_ajax_without_full_page_redirect(): void
    {
        $view = $this->settingsBladeSource();
        $controller = file_get_contents(app_path('Http/Controllers/SettingController.php'));

        $this->assertStringContainsString("type:'POST',data:formData,processData:false", $view);
        $this->assertStringContainsString("headers:{Accept:'application/json'", $view);
        $this->assertStringContainsString('e.stopImmediatePropagation()', $view);
        $this->assertStringContainsString('response()->json', $controller);
        $this->assertStringContainsString('if ($request->expectsJson())', $controller);
        $this->assertStringContainsString('DB::transaction(', $controller);
    }

    public function test_file_manager_binding_does_not_depend_on_removed_setts_variable(): void
    {
        $view = $this->settingsBladeSource();

        $this->assertStringNotContainsString('$setts', $view);
        $this->assertStringNotContainsString('$setid', $view);
        $this->assertStringContainsString('[data-settings-file="1"]', $view);
        $this->assertStringContainsString('window.fmSetLink = fmSetLink', $view);
    }

    public function test_information_fields_use_responsive_columns_and_image_preview_canvas(): void
    {
        $view = $this->settingsBladeSource();

        $this->assertStringContainsString('settings-info-grid', $view);
        $this->assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr))', $view);
        $this->assertStringContainsString('settings-info-field {{ $isWide', $view);
        $this->assertStringContainsString('settings-media-canvas', $view);
        $this->assertStringContainsString('settings-s1-busy-preview', $view);
        $this->assertStringContainsString('settings-s1-busy', $view);
    }

    public function test_information_save_uses_bootstrap_modal_instead_of_native_confirm(): void
    {
        $view = $this->settingsBladeSource();

        $this->assertStringContainsString('id="settingsS1Confirm"', $view);
        $this->assertStringContainsString("$('#settingsS1Confirm').modal('show')", $view);
        $this->assertStringContainsString("$('#settingsS1ConfirmYes').on('click'", $view);
        $this->assertStringContainsString('sendInformationAjax()', $view);
        $this->assertStringNotContainsString("window.confirm('Save information and contact?')", $view);
    }

    public function test_card_scrolling_is_isolated_from_page_and_footer(): void
    {
        $view = $this->settingsBladeSource();

        $this->assertStringContainsString('.settings-main-card .tab-content{flex:1;min-height:0;overflow:auto', $view);
        $this->assertStringContainsString('settings-s1-viewport', $view);
        $this->assertStringContainsString('body.settings-s1-viewport .main-footer{position:fixed', $view);
        $this->assertStringContainsString('fitSettingsCard()', $view);
    }
}
