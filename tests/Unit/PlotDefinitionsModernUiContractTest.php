<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PlotDefinitionsModernUiContractTest extends TestCase
{
    public function test_definitions_use_scrollable_modern_cards_and_keep_safe_definition_forms(): void
    {
        $view=file_get_contents(resource_path('views/admin/plots/definitions.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/PlotDefinitionController.php'));
        $this->assertStringContainsString('class="definition-grid"', $view);
        $this->assertStringContainsString('height:calc(100vh - 245px)', $view);
        $this->assertStringContainsString('.definition-page .table-wrap{flex:1;min-height:0;overflow:auto', $view);
        $this->assertStringContainsString('max-width:700px', $view);
        $this->assertStringContainsString("route('project.plot-definitions.store')", $view);
        $this->assertStringContainsString("route('project.plot-definitions.rate'", $view);
        $this->assertStringContainsString("where('project_id', $projectId)", $controller);
        $this->assertStringContainsString('plot_definition_rate_revisions', $controller);
    }

    public function test_add_edit_plot_forms_keep_backend_fields_but_have_clear_labels_and_searchable_definitions(): void
    {
        $view=file_get_contents(resource_path('views/admin/plots/index.blade.php'));
        $script=file_get_contents(public_path('js/plot-editor-modern.js'));
        $controller=file_get_contents(app_path('Http/Controllers/PlotController.php'));
        $this->assertStringContainsString('plot-list-page', $view);
        $this->assertStringContainsString('plot-list-card', $view);
        $this->assertStringContainsString('id="modal-tambah"', $view);
        $this->assertStringContainsString('id="modal-edit"', $view);
        $this->assertStringContainsString('plot-editor-modern.js', $view);
        $this->assertStringContainsString('.plot-editor .plot-definition-row', $view);
        $this->assertStringContainsString('Description / Notes', $view);
        $this->assertStringContainsString('Saved Plot Definitions (selected project)', $script);
        $this->assertStringContainsString('plot_category_definition_id', $script);
        $this->assertStringContainsString('plot_type_definition_id', $script);
        $this->assertStringContainsString('plot_size_definition_id', $script);
        $this->assertStringContainsString('plot_unit_definition_id', $script);
        $this->assertStringContainsString('new TomSelect(this', $script);
        $this->assertStringContainsString("name:'project_id'", $script);
        $this->assertStringContainsString("Rule::exists('plot_definitions'", $controller);
    }
}
