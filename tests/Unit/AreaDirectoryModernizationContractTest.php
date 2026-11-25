<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AreaDirectoryModernizationContractTest extends TestCase
{
    private function source(string $path): string
    {
        $text = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($text, $path);

        return $text;
    }

    public function test_area_directory_has_approved_striped_full_height_cr_dr_design(): void
    {
        $view = $this->source('resources/views/admin/area/index.blade.php');
        foreach ([
            'bm-voucher-directory bm-areas',
            'min-height:calc(100vh - 175px)',
            'font-size:14px',
            'font-size:13px',
            'nth-child(even){background:#f1f5fa!important}',
            'nth-child(odd){background:#ffffff!important}',
            'border-right:1px solid #e2eaf4!important',
            '<tbody id="areaRows" aria-busy="true">',
            '$.financeVoucherComponents.createFilter',
            'area-col-trigger',
            'widget.dates(', 'widget.search(',
            '$.blueVoucherPagination.render',
            '$.blueActionMenu.enhance',
            'areaPages', 'areaPageSize',
        ] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        self::assertStringNotContainsString('id="example1"', $view);
        self::assertStringNotContainsString("route('user.destroy')", $view);
        self::assertStringNotContainsString('DataTable(', $view);
        self::assertStringNotContainsString('console.log(', $view);
    }

    public function test_area_ajax_crud_and_delete_are_permission_bound_and_dependency_safe(): void
    {
        $controller = $this->source('app/Http/Controllers/AreaController.php');
        $view = $this->source('resources/views/admin/area/index.blade.php');
        $guard = $this->source('app/Support/SafeMasterRecordDeletion.php');
        $routes = $this->source('routes/web.php');

        foreach ([
            'public function index(Request $request)',
            'public function update(Request $request)',
            'public function destroy(Request $request)',
            'Area::query()->findOrFail',
            'lockForUpdate()->firstOrFail()',
            'SafeMasterRecordDeletion::assertUnreferenced(',
            'Rule::unique',
            '->delete()',
            '409',
        ] as $needle) {
            self::assertStringContainsString($needle, $controller);
        }
        self::assertStringContainsString("'areas', 'area_id'", $guard);
        self::assertStringContainsString('areaDeleteModal', $view);
        self::assertStringContainsString('areaDeleteConfirm', $view);
        self::assertStringContainsString("can('delete area')", $view);
        self::assertStringContainsString("route('area.destroy')", $view);
        self::assertStringContainsString("payload.push({name:'_method',value:'PUT'})", $view);
        self::assertStringContainsString('X-Requested-With', $view);
        self::assertStringNotContainsString('DB::rollback()', $controller);
        foreach (['read area','create area','update area','delete area'] as $ability) {
            self::assertStringContainsString('permission:'.$ability, $routes);
        }
    }

    public function test_project_and_sector_listings_adopt_same_larger_striped_table_typography(): void
    {
        foreach ([
            ['resources/views/admin/projects/index.blade.php', 'bm-projects', 'project'],
            ['resources/views/admin/zone/index.blade.php', 'bm-sectors', 'sector'],
        ] as [$path, $class, $prefix]) {
            $view = $this->source($path);
            self::assertStringContainsString('.'.$class.' .'.$prefix.'-table{width:100%;margin:0;font-size:14px;', $view);
            self::assertStringContainsString('font-weight:750;font-size:13px;', $view);
            self::assertStringContainsString('nth-child(even){background:#f1f5fa!important}', $view);
            self::assertStringContainsString('nth-child(odd){background:#ffffff!important}', $view);
            self::assertStringContainsString('nth-child(odd)', $view);
        }
    }
}
