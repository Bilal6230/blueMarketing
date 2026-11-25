<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProjectDirectoryModernizationContractTest extends TestCase
{
    private function source(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents, $path);

        return $contents;
    }

    public function test_project_directory_uses_modern_shared_components_with_initial_skeleton(): void
    {
        $view = $this->source('resources/views/admin/projects/index.blade.php');

        foreach ([
            'bm-voucher-directory bm-projects',
            'project-col-trigger', 'data-field="{{ $field }}"',
            '$.financeVoucherComponents.createFilter', 'filterWidget.dates(',
            'filterWidget.search(', "filterWidget.$control", 'projectPageSize',
            'projectPages', 'project-skeleton', 'project-card', 'flex:1 1 auto',
            'min-height:calc(100vh - 175px)',
            '<tbody id="projectRows" aria-busy="true">',
            '$.blueActionMenu.enhance', '$.blueVoucherPagination.render',
            "attr('aria-busy','false')",
        ] as $required) {
            self::assertStringContainsString($required, $view);
        }

        self::assertStringNotContainsString('id="projectSearch"', $view);
        self::assertStringNotContainsString('id="projectStatusFilter"', $view);
        self::assertStringNotContainsString('class="project-toolbar"', $view);
        self::assertStringContainsString('project-footer-controls', $view);
        self::assertStringContainsString('statusSelection', $view);
        self::assertStringContainsString('dateRanges', $view);
        self::assertStringContainsString('columnSearch', $view);
        self::assertStringNotContainsString('id="example1"', $view);
        self::assertStringNotContainsString("route('user.destroy')", $view);
        self::assertStringNotContainsString('id="modal-delete"', $view);
        self::assertStringNotContainsString('DataTable(', $view);
        self::assertStringNotContainsString('console.log(', $view);
    }

    public function test_project_form_uses_ajax_validation_and_preserves_charge_date_rule(): void
    {
        $view = $this->source('resources/views/admin/projects/index.blade.php');
        $controller = $this->source('app/Http/Controllers/ProjectController.php');

        self::assertStringContainsString('data-ajax="off"', $view);
        self::assertStringContainsString("new FormData(", $view);
        self::assertStringContainsString("payload.append('_method','PUT')", $view);
        self::assertStringContainsString('charge_effective_from', $view);
        self::assertStringContainsString('chargeChanged()', $view);
        self::assertStringContainsString('X-Requested-With', $view);
        self::assertStringContainsString('Project::query()->findOrFail((int) $id)', $controller);
        self::assertStringContainsString("return response()->json(['status' => 'success', 'message' => 'Project updated successfully.'", $controller);
        self::assertStringContainsString('\'errors\' => $validator->errors()', $controller);
        self::assertStringContainsString('lockForUpdate()', $controller);
        self::assertStringContainsString('project_charge_revisions', $controller);
        self::assertStringNotContainsString('forceDelete(', $controller);
    }

    public function test_project_index_provides_authorized_read_only_json_refresh(): void
    {
        $controller = $this->source('app/Http/Controllers/ProjectController.php');
        $routes = $this->source('routes/web.php');

        self::assertStringContainsString('public function index(Request $request)', $controller);
        self::assertStringContainsString('return response()->json([\'status\' => \'success\', \'data\' => $rows])', $controller);
        self::assertStringContainsString('\'projectRows\' => $rows', $controller);
        foreach ([
            "permission:read project", "permission:create project",
            "permission:update project", "permission:delete project"
        ] as $permission) {
            self::assertStringContainsString($permission, $routes);
        }
        self::assertStringContainsString('public function destroy(Project $project)', $controller);
        self::assertStringNotContainsString("route('project.destroy')", $this->source('resources/views/admin/projects/index.blade.php'));
    }
}
