<?php

namespace Tests\Unit;

use Tests\TestCase;

final class SystemSettingS2ContractTest extends TestCase
{
    public function test_device_tab_uses_ajax_editor_not_page_navigation(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $this->assertStringContainsString('id="as-new-device"', $view);
        $this->assertStringContainsString('id="as-device-select"', $view);
        $this->assertStringContainsString('id="as-device-form"', $view);
        $this->assertStringContainsString("method:'GET',dataType:'json'", $view);
        $this->assertStringContainsString('$.ajax({url:$deviceForm.attr', $view);
        $this->assertStringContainsString("event.preventDefault();event.stopImmediatePropagation();", $view);
        $this->assertStringNotContainsString('this.form.submit()', $view);
        $this->assertStringNotContainsString("'new_device'=>1", $view);
        $this->assertStringNotContainsString('data-ajax-form', $view);
    }

    public function test_mappings_update_in_place_and_are_project_scoped(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AttendanceController.php'));
        $this->assertStringContainsString('id="as-mapping-rows"', $view);
        $this->assertStringContainsString('renderMappings(device.mappings||[])', $view);
        $this->assertStringContainsString('id="as-mapping-form"', $view);
        $this->assertStringContainsString("where('project_id', $projectId)", $controller);
        $this->assertStringContainsString("'device' => $this->devicePayload($device)", $controller);
        $this->assertStringContainsString('AttendanceDeviceUserMapping::updateOrCreate(', $controller);
    }

    public function test_sensitive_device_secret_is_not_exposed_and_sync_stays_disabled(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AttendanceController.php'));
        $model = file_get_contents(app_path('Models/AttendanceDevice.php'));
        $this->assertStringContainsString("'has_password' =>", $controller);
        $this->assertStringNotContainsString("'password' => (string)", $controller);
        $this->assertStringContainsString("'password' => 'encrypted'", $model);
        $this->assertStringContainsString('>Test Connection</button>', $view);
        $this->assertStringContainsString('>Sync Attendance</button>', $view);
        $this->assertStringContainsString('disabled title="A vendor integration must be verified first"', $view);
    }

    public function test_generic_settings_modalizer_does_not_capture_ajax_forms(): void
    {
        $view = file_get_contents(resource_path('views/admin/setting.blade.php'));
        $this->assertStringContainsString(':not(#as-device-form):not(#as-mapping-form)', $view);
    }

    public function test_attendance_device_json_route_is_authorized(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString("'attendance.device.settings'", $routes);
        $this->assertStringContainsString("->middleware('permission:update setting')->name('attendance.device.settings')", $routes);
    }
}
