<?php

namespace Tests\Unit;

use Tests\TestCase;

final class SystemSettingS2ContractTest extends TestCase
{
    public function test_device_list_and_modal_replace_inline_configuration(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $this->assertStringContainsString('id="as-new-device"', $view);
        $this->assertStringContainsString('id="as-device-rows"', $view);
        $this->assertStringContainsString('id="as-device-modal"', $view);
        $this->assertStringContainsString('id="as-device-form"', $view);
        $this->assertStringContainsString('as-edit-device', $view);
        $this->assertStringContainsString('as-delete-device', $view);
        $this->assertStringContainsString("'#as-device-modal-title').text('Add Attendance Device'", $view);
        $this->assertStringContainsString("$('#as-device-modal-title').text('Edit Attendance Device')", $view);
    }

    public function test_scripts_load_after_jquery_and_global_form_interceptor_is_opted_out(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $this->assertStringContainsString("@push('script')", $view);
        $this->assertStringContainsString("@endpush", $view);
        $this->assertStringContainsString('data-ajax="off"', $view);
        $this->assertStringContainsString("e.preventDefault();e.stopImmediatePropagation();", $view);
        $this->assertStringContainsString("headers:{Accept:'application/json'},dataType:'json'", $view);
        $this->assertStringNotContainsString('this.form.submit()', $view);
        $this->assertStringNotContainsString("'new_device'=>1", $view);
        $this->assertStringNotContainsString('data-ajax-form', $view);
    }

    public function test_mappings_add_edit_delete_are_ajax_and_project_scoped(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AttendanceController.php'));
        $this->assertStringContainsString('id="as-mapping-rows"', $view);
        $this->assertStringContainsString('populateMappings(data.device.mappings||[],selectedId)', $view);
        $this->assertStringContainsString('as-edit-mapping', $view);
        $this->assertStringContainsString('as-delete-mapping', $view);
        $this->assertStringContainsString('AttendanceDeviceUserMapping::updateOrCreate(', $controller);
        $this->assertStringContainsString('public function deleteMapping(', $controller);
        $this->assertStringContainsString("where('project_id', $projectId)", $controller);
    }

    public function test_device_deletion_is_guarded_against_mapping_loss(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AttendanceController.php'));
        $this->assertStringContainsString('public function deleteDevice(', $controller);
        $this->assertStringContainsString('$device->mappings()->exists()', $controller);
        $this->assertStringContainsString('abort_if(', $controller);
        $this->assertStringContainsString('public function deleteMapping(', $controller);
        $this->assertStringContainsString('abort_unless((int) $device->project_id === $projectId, 404)', $controller);
    }

    public function test_device_credentials_remain_encrypted_and_off_the_wire(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AttendanceController.php'));
        $model = file_get_contents(app_path('Models/AttendanceDevice.php'));
        $this->assertStringContainsString("'has_password' =>", $controller);
        $this->assertStringNotContainsString("'password' => (string)", $controller);
        $this->assertStringContainsString("'password' => 'encrypted'", $model);
        $this->assertStringContainsString('Saving does not connect to or synchronize the device', $view);
    }

    public function test_scoped_routes_require_settings_permissions(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        foreach (['attendance.device.settings','attendance.device.delete','attendance.mapping.save','attendance.mapping.delete'] as $name) {
            $this->assertStringContainsString("->name('".$name."')", $routes);
        }
        $this->assertStringContainsString("->middleware('permission:update setting')->name('attendance.device.delete')", $routes);
        $this->assertStringContainsString("->middleware('permission:update setting')->name('attendance.mapping.delete')", $routes);
    }
}
