<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernDirectoryExportToolbarContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    public function test_shared_export_toolbar_has_all_six_controls_and_exports_filtered_data(): void
    {
        $script = $this->source('public/js/blue-modern-directory-tools.js');
        $style = $this->source('public/css/blue-modern-directory-tools.css');
        $layout = $this->source('resources/views/admin/layouts/master.blade.php');

        foreach ([
            "['copy','Copy']", "['csv','CSV']", "['excel','Excel']",
            "['pdf','PDF']", "['print','Print']", "text:'Column Visibility'",
            'options.getRows()', "exportOptions:{columns:':visible'",
            "page:'all'", "render:$.fn.dataTable.render.text()",
            "actions.indexOf(i)<0", "var enabled=titles.map(function(){return true;});",
        ] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
        self::assertStringContainsString('bm-modern-export-toolbar', $style);
        self::assertStringContainsString('blue-modern-directory-tools.js', $layout);
        self::assertStringContainsString('blue-modern-directory-tools.css', $layout);
    }

    public function test_projects_sectors_area_and_four_accounting_directories_retain_exports(): void
    {
        foreach ([
            ['resources/views/admin/projects/index.blade.php', 'activeRows()', 'project-card'],
            ['resources/views/admin/zone/index.blade.php', 'visibleRows()', 'sector-card'],
            ['resources/views/admin/area/index.blade.php', 'visibleRows()', 'area-card'],
        ] as [$path, $filter, $card]) {
            $view = $this->source($path);
            self::assertStringContainsString('$.blueModernDirectoryTools.mount(', $view);
            self::assertStringContainsString('getRows:function(){return '.$filter.'.map(', $view);
            self::assertStringContainsString($card, $view);
            self::assertStringContainsString('exportControls.refresh()', $view);
        }

        $js = $this->source('public/js/modern-account-directory.js');
        self::assertStringContainsString('$.blueModernDirectoryTools.mount(', $js);
        self::assertStringContainsString('return visibleRows().map(', $js);
        self::assertStringContainsString('if(exportControls)exportControls.refresh()', $js);
        foreach ([
            'resources/views/admin/finance/accounting/head_accounts.blade.php',
            'resources/views/admin/finance/accounting/child_accounts.blade.php',
            'resources/views/admin/finance/accounting/category_index.blade.php',
            'resources/views/admin/finance/accountant/index.blade.php',
        ] as $path) {
            $view = $this->source($path);
            self::assertStringContainsString('modern-account-directory.js', $view);
            self::assertStringContainsString('bm-account-card', $view);
        }
    }

    public function test_accountant_ledger_modal_export_buttons_are_unchanged(): void
    {
        $view = $this->source('resources/views/admin/finance/accountant/index.blade.php');
        self::assertStringContainsString('buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"]', $view);
        self::assertStringContainsString('function ensureLedgerDataTable()', $view);
    }
}
