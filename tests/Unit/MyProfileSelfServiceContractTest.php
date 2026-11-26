<?php

namespace Tests\Unit;

use Tests\TestCase;

final class MyProfileSelfServiceContractTest extends TestCase
{
    private function controller(): string
    {
        return file_get_contents(app_path('Http/Controllers/MyProfileController.php'));
    }

    public function test_profile_is_self_service_and_does_not_require_admin_user_permissions(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $navbar = file_get_contents(resource_path('views/admin/layouts/navbar.blade.php'));
        $controller = $this->controller();

        $this->assertStringContainsString("Route::get('my-profile'", $routes);
        $this->assertStringContainsString("Route::post('my-profile'", $routes);
        $this->assertStringContainsString("->name('my-profile.edit')", $routes);
        $this->assertStringContainsString("->name('my-profile.update')", $routes);
        $this->assertStringContainsString("route('my-profile.edit')", $navbar);
        $this->assertStringContainsString('$user = $request->user();', $controller);
        $this->assertStringNotContainsString('User::find(', $controller);
        $this->assertStringNotContainsString('syncRoles(', $controller);
        $this->assertStringNotContainsString('project_id =', $controller);
        $this->assertStringNotContainsString('status_id =', $controller);
    }

    public function test_editable_fields_and_sensitive_password_validation_are_restricted(): void
    {
        $controller = $this->controller();

        foreach (['name','gender','phone_number','avatar','current_password','password','password_confirmation'] as $field) {
            $this->assertStringContainsString("'".$field."'", $controller);
        }
        $this->assertStringContainsString("'current_password'", $controller);
        $this->assertStringContainsString('Password::min(8)', $controller);
        $this->assertStringContainsString("'confirmed'", $controller);
        $this->assertStringContainsString('Hash::make(', $controller);
        $this->assertStringContainsString("'mimes:jpg,jpeg,png,webp'", $controller);
        $this->assertStringContainsString("'max:2048'", $controller);
        $this->assertStringContainsString("->store('avatar', 'public')", $controller);
        $this->assertStringContainsString("Storage::disk('public')->delete($newAvatar)", $controller);
        $this->assertStringNotContainsString('$user->email =', $controller);
        $this->assertStringNotContainsString('$user->role =', $controller);
    }

    public function test_ui_uses_ajax_upload_without_global_form_interceptor_or_page_refresh(): void
    {
        $view = file_get_contents(resource_path('views/admin/my-profile.blade.php'));
        $script = file_get_contents(public_path('js/my-profile.js'));
        $this->assertStringContainsString('id="my-profile-form"', $view);
        $this->assertStringContainsString('data-ajax="off"', $view);
        $this->assertStringContainsString('enctype="multipart/form-data"', $view);
        $this->assertStringContainsString('autocomplete="current-password"', $view);
        $this->assertStringContainsString('autocomplete="new-password"', $view);
        $this->assertStringContainsString('id="my-profile-image-preview"', $view);
        $this->assertStringContainsString("new FormData(form)", $script);
        $this->assertStringContainsString('processData:false,contentType:false', $script);
        $this->assertStringContainsString('e.preventDefault()', $script);
        $this->assertStringContainsString('modal-dialog modal-dialog-centered', $script);
        $this->assertStringNotContainsString('window.location.reload()', $script);
    }
}
