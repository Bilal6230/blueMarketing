<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernDirectoryAjaxLifecycleContractTest extends TestCase
{
    private function source(string $path): string
    {
        $text = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($text, $path);
        return $text;
    }

    public function test_user_and_permission_writes_return_json_with_validation_errors(): void
    {
        foreach ([
            'app/Http/Controllers/UserController.php',
            'app/Http/Controllers/PermissionController.php',
        ] as $file) {
            $controller = $this->source($file);
            self::assertStringContainsString('$request->expectsJson()', $controller);
            self::assertStringContainsString("'errors' => \$validator->errors()", $controller);
        }
        $permission = $this->source('app/Http/Controllers/PermissionController.php');
        self::assertStringContainsString('public function reloadPermission(Request $request)', $permission);
        self::assertStringContainsString("'Permissions refreshed.'", $permission);
    }

    public function test_modern_users_and_permissions_intercept_forms_and_reload_without_navigation(): void
    {
        $js = $this->source('public/js/modern-account-directory.js');
        foreach ([
            "kind==='user'||kind==='permission'",
            "'#modal-tambah form,#modal-edit form,#modal-delete form'",
            "event.preventDefault()",
            'new FormData(this)',
            'processData:false,contentType:false',
            "'X-Requested-With':'XMLHttpRequest'",
            'reloadRows()',
            "kind==='permission'",
            "'click.bmDirectoryReload'",
            'bm-cell-skeleton-row',
            'bm-pending-directory',
        ] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['resources/views/admin/user.blade.php', 'resources/views/admin/permission.blade.php'] as $path) {
            $view = $this->source($path);
            self::assertStringContainsString('modern-account-directory.js', $view);
        }
    }

    public function test_ajax_modal_forms_contain_their_submit_footers(): void
    {
        foreach ([
            'resources/views/admin/user.blade.php',
            'resources/views/admin/permission.blade.php',
        ] as $path) {
            $view = $this->source($path);
            foreach (['modal-tambah', 'modal-edit', 'modal-delete'] as $id) {
                $start = strpos($view, 'id="'.$id.'"');
                self::assertNotFalse($start);
                $section = substr($view, $start);
                $form = strpos($section, '<form action=');
                $body = strpos($section, '<div class="modal-body">');
                $footer = strpos($section, '<div class="modal-footer');
                $close = strpos($section, '</form>');
                self::assertNotFalse($form);
                self::assertNotFalse($body);
                self::assertNotFalse($footer);
                self::assertNotFalse($close);
                self::assertLessThan($body, $form);
                self::assertLessThan($footer, $body);
                self::assertLessThan($close, $footer);
            }
        }
    }

    public function test_user_project_preselect_uses_resource_project_id(): void
    {
        $resource = $this->source('app/Http/Resources/UserResource.php');
        $view = $this->source('resources/views/admin/user.blade.php');
        self::assertStringContainsString("'project_id'        => \$this->project_id", $resource);
        self::assertStringContainsString('String(data.project_id)', $view);
        self::assertStringContainsString("trigger('change')", $view);
    }

    public function test_initial_and_refresh_skeletons_are_column_scoped_on_all_modern_pages(): void
    {
        $shared = $this->source('public/js/modern-account-directory.js');
        $tools = $this->source('public/js/blue-modern-directory-tools.js');
        foreach ([
            'resources/views/admin/finance/accounting/head_accounts.blade.php',
            'resources/views/admin/finance/accounting/child_accounts.blade.php',
            'resources/views/admin/finance/accounting/category_index.blade.php',
            'resources/views/admin/finance/accountant/index.blade.php',
            'resources/views/admin/user.blade.php',
            'resources/views/admin/permission.blade.php',
        ] as $path) {
            $view = $this->source($path);
            self::assertStringContainsString('bm-pending-directory', $view);
            self::assertStringContainsString('bm-cell-skeleton-row', $view);
            self::assertStringContainsString('bm-modern-export-toolbar bm-export-placeholder', $view);
            self::assertStringNotContainsString('style="visibility:hidden"', $view);
        }
        foreach ([
            'resources/views/admin/projects/index.blade.php',
            'resources/views/admin/zone/index.blade.php',
            'resources/views/admin/area/index.blade.php',
        ] as $path) {
            $view = $this->source($path);
            self::assertStringContainsString('bm-export-placeholder', $view);
        }
        self::assertStringContainsString("not('.bm-cell-skeleton-row')", $shared);
        self::assertStringContainsString("removeClass('bm-pending-directory')", $shared);
        self::assertStringContainsString('function skeleton(selector)', $tools);
        self::assertStringContainsString("var $old=$body.children().detach()", $tools);
        self::assertStringContainsString("var $row=$('<tr>',{'class':'bm-cell-skeleton-row'", $tools);
    }

    public function test_other_modern_directories_keep_ajax_and_show_refetch_skeleton(): void
    {
        foreach ([
            ['resources/views/admin/projects/index.blade.php', 'project'],
            ['resources/views/admin/zone/index.blade.php', 'sector'],
            ['resources/views/admin/area/index.blade.php', 'area'],
        ] as [$path, $kind]) {
            $view = $this->source($path);
            self::assertStringContainsString("event.preventDefault()", $view);
            self::assertStringContainsString("$.blueModernDirectoryTools.skeleton('.".$kind."-table-wrap')", $view);
            self::assertStringContainsString('stopSkeleton()', $view);
        }
        $joint = $this->source('resources/views/admin/finance/accounting/category_index.blade.php');
        self::assertStringContainsString("$(document).trigger('bm:account-refresh')", $joint);
        $accounting = $this->source('public/js/modern-account-directory.js');
        self::assertStringContainsString("if(kind==='head'||kind==='subhead')", $accounting);
        $accountant = $this->source('resources/views/admin/finance/accountant/index.blade.php');
        self::assertStringContainsString('ensureLedgerDataTable()', $accountant);
    }
}
