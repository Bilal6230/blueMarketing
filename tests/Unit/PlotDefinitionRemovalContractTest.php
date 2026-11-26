<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PlotDefinitionRemovalContractTest extends TestCase
{
    public function test_plot_create_and_edit_use_three_static_types_without_master_definitions(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/PlotController.php'));
        $plot = file_get_contents(app_path('Models/Plot.php'));
        $view = file_get_contents(resource_path('views/admin/plots/index.blade.php'));

        $this->assertStringContainsString("Rule::in(['1','2','3'])", $controller);
        $this->assertSame(2, substr_count($controller, "Rule::in(['1','2','3'])"));
        $this->assertSame(2, substr_count($view, '<option value="3">Others</option>'));
        $this->assertStringContainsString("'3'=>'Others'", $view);
        $this->assertStringContainsString('<th>Plot Type</th>', $view);
        $this->assertStringContainsString('<th>Plot Number</th>', $view);
        $this->assertSame(2, substr_count($view, 'disabled aria-label="Selected Project"'));
        $this->assertSame(2, substr_count($view, 'type="hidden" name="project_id"'));
        $this->assertStringContainsString('<label class="fbox">Plot Size</label>', $view);
        $this->assertStringNotContainsString('plotDefinitionsReady', $controller);
        $this->assertStringNotContainsString('PlotDefinition', $controller);
        $this->assertStringNotContainsString('plot_definition', $view);
        $this->assertStringNotContainsString('categoryDefinition', $plot);
    }

    public function test_removed_pages_routes_and_plot_view_controls_are_not_referenced(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $view = file_get_contents(resource_path('views/admin/plots/index.blade.php'));

        $this->assertStringNotContainsString("Route::get('plot-definitions'", $routes);
        $this->assertStringNotContainsString("route('project.plot-definitions.index')", $view);
        $this->assertStringNotContainsString('plot-editor-modern.js', $view);
        $this->assertStringNotContainsString('plot_category_definition_id', $view);
        $this->assertStringNotContainsString('plot_size_definition_id', $view);
        $this->assertFileDoesNotExist(app_path('Http/Controllers/PlotDefinitionController.php'));
        $this->assertFileDoesNotExist(app_path('Models/PlotDefinition.php'));
        $this->assertFileDoesNotExist(resource_path('views/admin/plots/definitions.blade.php'));
    }

    public function test_cleanup_migration_only_drops_definition_data_and_link_columns(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_11_26_235000_retire_plot_definition_masters.php'));
        foreach (['plot_category_definition_id','plot_type_definition_id','plot_size_definition_id','plot_unit_definition_id',
                  'plot_definition_rate_revisions','plot_definition_audits','plot_definitions'] as $name) {
            $this->assertStringContainsString($name, $migration);
        }
        $this->assertStringContainsString("Schema::hasTable('plots')", $migration);
        $this->assertStringContainsString("Schema::dropIfExists('plot_definitions')", $migration);
        $this->assertStringNotContainsString("dropIfExists('plots')", $migration);
        $this->assertStringNotContainsString("dropIfExists('bookings')", $migration);
    }
}
