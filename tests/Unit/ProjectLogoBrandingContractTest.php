<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Support\ProjectBranding;
use Tests\TestCase;

final class ProjectLogoBrandingContractTest extends TestCase
{
    public function test_project_without_uploaded_logo_uses_neutral_placeholder(): void
    {
        $project = new Project(['project' => 'Green Sabzi Mandi', 'logo' => null]);

        $this->assertSame('images/logo/project-placeholder.svg', ProjectBranding::logoPath($project));
        $this->assertSame('Green Sabzi Mandi', ProjectBranding::title($project));
    }

    public function test_legacy_project_logo_paths_are_resolved_from_project_directory(): void
    {
        $project = new Project(['project' => 'Test Project', 'logo' => 'images/logo/project-placeholder.svg']);

        $this->assertSame('images/logo/project-placeholder.svg', ProjectBranding::logoPath($project));
    }

    public function test_unknown_project_logo_file_falls_back_without_using_global_brand(): void
    {
        $project = new Project(['project' => 'Test', 'logo' => 'missing-project-logo-xx.svg']);

        $this->assertSame('images/logo/project-placeholder.svg', ProjectBranding::logoPath($project));
    }

    public function test_no_admin_template_hardcodes_global_blue_marketing_logo(): void
    {
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/admin'))
        );
        foreach ($views as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString(
                'blue-marketing-logo.png',
                $contents,
                'Project-scoped admin page still hardcodes the global logo: '.$file->getPathname()
            );
        }
    }

    public function test_authenticated_shell_and_shared_receipt_branding_use_project_logo(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/layouts/sidebar.blade.php'));
        $helper = file_get_contents(app_path('Helpers/AppHelper.php'));
        $cheque = file_get_contents(resource_path('views/admin/finance/cheque/receiving_slip.blade.php'));

        $this->assertStringContainsString('ProjectBranding::logoUrl()', $sidebar);
        $this->assertStringContainsString('ProjectBranding::logoPath($project)', $helper);
        $this->assertStringContainsString('ProjectBranding::logoUrl($cheque->project_id', $cheque);
        $this->assertStringNotContainsString("asset('images/logo/blue-marketing-logo.png')", $sidebar);
    }
}
