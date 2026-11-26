<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PlotModernDirectoryParityContractTest extends TestCase
{
    public function test_plots_list_reuses_the_same_directory_components_as_users(): void
    {
        $users=file_get_contents(resource_path('views/admin/user.blade.php'));
        $plots=file_get_contents(resource_path('views/admin/plots/index.blade.php'));

        foreach (['bm-modern-accounts','bm-account-card','bm-account-table-wrap',
                  'bmAccountDirectory','bm-account-table','bm-modern-export-toolbar',
                  'bm-account-footer-placeholder','modern-account-directory.js',
                  'modern-account-directory.css'] as $part) {
            $this->assertStringContainsString($part,$users);
            $this->assertStringContainsString($part,$plots);
        }
        $this->assertStringContainsString('data-account-kind="plot"',$plots);
        $this->assertStringContainsString('bm-plot-toolbar-actions',$plots);
        $this->assertStringContainsString('btn-add-plot',$plots);
        $this->assertStringContainsString("route('project.plot-definitions.index')",$plots);
        $this->assertStringContainsString('bm-cell-skeleton-row',$plots);
        $this->assertStringNotContainsString('id="example1"',$plots);
        $this->assertStringNotContainsString('plot-list-card',$plots);
    }

    public function test_directory_uses_shared_filters_exports_action_menu_and_pagination(): void
    {
        $script=file_get_contents(public_path('js/modern-account-directory.js'));
        $tools=file_get_contents(public_path('js/blue-modern-directory-tools.js'));

        $this->assertStringContainsString('financeVoucherComponents.createFilter',$script);
        $this->assertStringContainsString('blueActionMenu.enhance',$script);
        $this->assertStringContainsString('blueVoucherPagination.render',$script);
        $this->assertStringContainsString('blueModernDirectoryTools.mount',$script);
        $this->assertStringContainsString("kind==='plot'?'Plots List'",$script);
        $this->assertStringContainsString('bm-plot-toolbar-actions',$tools);
    }

    public function test_plots_keep_existing_edit_and_delete_routes_and_sold_guards(): void
    {
        $plots=file_get_contents(resource_path('views/admin/plots/index.blade.php'));
        $this->assertStringContainsString('@if ($i->sold == 0)',$plots);
        $this->assertStringContainsString('class="btn btn-sm btn-primary btn-edit"',$plots);
        $this->assertStringContainsString('class="btn btn-sm btn-danger btn-delete"',$plots);
        $this->assertStringContainsString("route('project.plot.store')",$plots);
        $this->assertStringContainsString("route('project.plot.update')",$plots);
    }
}
