<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SectorDirectoryModernizationContractTest extends TestCase
{
    private function source(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents, $path);

        return $contents;
    }

    public function test_sectors_use_approved_full_height_column_filter_and_table_border_design(): void
    {
        $view = $this->source('resources/views/admin/zone/index.blade.php');
        foreach ([
            'bm-voucher-directory bm-sectors',
            'min-height:calc(100vh - 175px)',
            '.bm-sectors .sector-table thead th',
            'font-size:13px',
            'font-size:12px',
            'border-right:1px solid #d9e4f0!important',
            'border-right:1px solid #e2eaf4!important',
            '<tbody id="sectorRows" aria-busy="true">',
            'sector-col-trigger',
            '$.financeVoucherComponents.createFilter',
            'widget.dates(',
            'widget.search(',
            'sectorPageSize',
            'sectorPages',
            '$.blueVoucherPagination.render',
            '$.blueActionMenu.enhance',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringNotContainsString('id="example1"', $view);
        self::assertStringNotContainsString('class="sector-toolbar"', $view);
        self::assertStringNotContainsString("route('user.destroy')", $view);
        self::assertStringNotContainsString('id="modal-delete"', $view);
        self::assertStringNotContainsString('DataTable(', $view);
        self::assertStringNotContainsString('console.log(', $view);
    }

    public function test_sector_ajax_form_retains_crud_permissions_and_denies_unsafe_delete(): void
    {
        $view = $this->source('resources/views/admin/zone/index.blade.php');
        $controller = $this->source('app/Http/Controllers/ZoneController.php');
        $routes = $this->source('routes/web.php');

        self::assertStringContainsString('data-ajax="off"', $view);
        self::assertStringContainsString("payload.push({name:'_method',value:'PUT'})", $view);
        self::assertStringContainsString('X-Requested-With', $view);
        self::assertStringContainsString('public function index(Request $request)', $controller);
        self::assertStringContainsString('Zone::query()->findOrFail', $controller);
        self::assertStringContainsString('Rule::unique', $controller);
        self::assertStringContainsString('\'data\' => $rows', $controller);
        self::assertStringContainsString("SafeMasterRecordDeletion::assertUnreferenced('zones', $id, 'zone_id')", $controller);
        self::assertStringContainsString('sectorDeleteModal', $view);
        self::assertStringContainsString("can('delete sector')", $view);
        self::assertStringNotContainsString('DB::rollback()', $controller);
        self::assertStringContainsString('->delete()', $controller);

        foreach (['read sector','create sector','update sector','delete sector'] as $ability) {
            self::assertStringContainsString('permission:'.$ability, $routes);
        }
        self::assertStringContainsString("route('project.zone.destroy')", $view);
    }
}
