<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernUserDirectoryContractTest extends TestCase
{
    private function source(string $path): string
    {
        $text = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($text, $path);

        return $text;
    }

    public function test_user_directory_uses_shared_scroll_filters_and_export_toolbar(): void
    {
        $view = $this->source('resources/views/admin/user.blade.php');
        $js = $this->source('public/js/modern-account-directory.js');
        $css = $this->source('public/css/modern-user-directory.css');

        foreach ([
            'bm-modern-accounts bm-user-directory',
            'data-account-kind="user"',
            'bm-account-card', 'bm-account-table-wrap', 'bm-account-table',
            'id="bmAccountDirectory"', 'bm-account-loading',
            'modern-account-directory.js', 'modern-user-directory.css',
            'Employee Setup', "route('employee-definitions.index')",
            'status-toggle', 'btn-edit', 'btn-delete',
        ] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        self::assertStringNotContainsString('id="example1"', $view);
        self::assertStringContainsString('$.blueModernDirectoryTools.mount(', $js);
        self::assertStringContainsString('$.blueVoucherPagination', $js);
        self::assertStringContainsString('$.financeVoucherComponents.createFilter', $js);
        self::assertStringContainsString('thead th{position:sticky', $css);
        self::assertStringContainsString('.bm-user-modal .modal-dialog', $css);
    }

    public function test_user_role_labels_do_not_execute_one_query_per_row(): void
    {
        $controller = $this->source('app/Http/Controllers/UserController.php');
        $view = $this->source('resources/views/admin/user.blade.php');
        self::assertStringContainsString("User::query()->with('roles')->get()", $controller);
        self::assertStringContainsString("\$i->roles->pluck('name')->implode(', ')", $view);
        self::assertStringNotContainsString('getRoleNames()', $view);
    }

    public function test_permissions_and_existing_user_actions_are_preserved(): void
    {
        $view = $this->source('resources/views/admin/user.blade.php');
        foreach ([
            "can('create user')", "can('update user')", "can('delete user')",
            "route('user.store')", "route('user.update')", "route('user.destroy')",
            "route('user.show')", "route('user.status.toggle')",
            'id="modal-tambah"', 'id="modal-edit"', 'id="modal-delete"',
            'data-id=', 'data-name=', "toastr.error",
        ] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        self::assertStringNotContainsString('console.log(data)', $view);
    }
}
