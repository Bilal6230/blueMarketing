<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernPermissionDirectoryContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    public function test_permission_listing_uses_modern_scroll_and_all_six_export_controls(): void
    {
        $view = $this->source('resources/views/admin/permission.blade.php');
        $js = $this->source('public/js/modern-account-directory.js');
        $export = $this->source('public/js/blue-modern-directory-tools.js');
        $css = $this->source('public/css/modern-permission-directory.css');

        foreach ([
            'bm-modern-accounts bm-permission-directory',
            'data-account-kind="permission"',
            'bm-account-card', 'bm-account-table-wrap',
            'bm-account-table', 'id="bmAccountDirectory"',
            'bm-cell-skeleton-row', 'modern-account-directory.js',
            'modern-permission-directory.css',
        ] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        self::assertStringNotContainsString('class="table table-bordered table-hover datatable"', $view);
        self::assertStringContainsString('$.blueModernDirectoryTools.mount(', $js);
        self::assertStringContainsString("if(kind==='permission')", $js);
        self::assertStringContainsString('bm-permission-toolbar-actions', $js);
        foreach (["['copy','Copy']", "['csv','CSV']", "['excel','Excel']", "['pdf','PDF']", "['print','Print']", "text:'Column Visibility'"] as $control) {
            self::assertStringContainsString($control, $export);
        }
        self::assertStringContainsString('thead th{position:sticky', $css);
        self::assertStringContainsString('.bm-permission-toolbar-actions', $css);
    }

    public function test_permission_crud_routes_permissions_and_modals_are_preserved(): void
    {
        $view = $this->source('resources/views/admin/permission.blade.php');
        foreach ([
            "can('create permission')", "can('update permission')", "can('delete permission')",
            "route('permission.reload')", "route('permission.store')",
            "route('permission.show')", "route('permission.update')",
            "route('permission.destroy')", 'btn-edit', 'btn-delete',
            'id="modal-tambah"', 'id="modal-edit"', 'id="modal-delete"',
            'bm-permission-modal', 'bm-permission-toolbar-actions',
        ] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        self::assertStringContainsString("$('#modal-loading').modal('hide');", $view);
    }
}
