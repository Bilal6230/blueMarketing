<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SafeMasterDeletionContractTest extends TestCase
{
    public function test_master_deletion_checks_existing_mysql_dependencies_without_cascade(): void
    {
        $root = dirname(__DIR__, 2);
        $guard = file_get_contents($root.'/app/Support/SafeMasterRecordDeletion.php');
        self::assertIsString($guard);
        foreach ([
            'information_schema.COLUMNS',
            'information_schema.KEY_COLUMN_USAGE',
            "'projects', 'project_id'",
            "'zones', 'zone_id'",
            "'town_id'",
            "'sector_id'",
            'where($column, $id)->exists()',
            'where($referenceColumn, $id)->exists()',
            'Reference safety could not be established',
        ] as $needle) {
            self::assertStringContainsString($needle, $guard);
        }
        self::assertStringNotContainsString('CASCADE', $guard);
        self::assertStringNotContainsString('->delete(', $guard);
        self::assertStringNotContainsString('->detach(', $guard);
    }

    public function test_both_delete_endpoints_require_real_ids_and_check_dependencies(): void
    {
        $root = dirname(__DIR__, 2);
        $project = file_get_contents($root.'/app/Http/Controllers/ProjectController.php');
        $sector = file_get_contents($root.'/app/Http/Controllers/ZoneController.php');
        $projectView = file_get_contents($root.'/resources/views/admin/projects/index.blade.php');
        $sectorView = file_get_contents($root.'/resources/views/admin/zone/index.blade.php');
        $routes = file_get_contents($root.'/routes/web.php');

        foreach ([$project, $sector] as $controller) {
            self::assertStringContainsString('public function destroy(Request $request)', $controller);
            self::assertStringContainsString('lockForUpdate()->firstOrFail()', $controller);
            self::assertStringContainsString('SafeMasterRecordDeletion::assertUnreferenced(', $controller);
            self::assertStringContainsString('409', $controller);
            self::assertStringContainsString('->delete()', $controller);
        }

        foreach ([
            [$projectView, 'projectDeleteModal', 'projectDeleteConfirm', "route('project.destroy')", "can('delete project')"],
            [$sectorView, 'sectorDeleteModal', 'sectorDeleteConfirm', "route('project.zone.destroy')", "can('delete sector')"],
        ] as [$view, $modal, $confirm, $route, $permission]) {
            self::assertIsString($view);
            self::assertStringContainsString($modal, $view);
            self::assertStringContainsString($confirm, $view);
            self::assertStringContainsString($route, $view);
            self::assertStringContainsString($permission, $view);
            self::assertStringNotContainsString("route('user.destroy')", $view);
        }

        self::assertStringContainsString('permission:delete project', $routes);
        self::assertStringContainsString('permission:delete sector', $routes);
    }
}
