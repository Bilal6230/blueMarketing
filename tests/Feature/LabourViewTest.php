<?php

namespace Tests\Feature;

use Tests\TestCase;

class LabourViewTest extends TestCase
{
    public function test_labour_view_uses_adminlte_content_wrapper_layout(): void
    {
        $html = file_get_contents(resource_path('views/admin/labours/index.blade.php'));

        $this->assertStringContainsString('content-wrapper labour-content-wrapper', $html);
        $this->assertStringContainsString('class="wrap labour-wrap"', $html);
        $this->assertStringNotContainsString('<div class="wrap">', $html);

        $wrapperPosition = strpos($html, 'content-wrapper labour-content-wrapper');
        $labourWrapPosition = strpos($html, 'class="wrap labour-wrap"');

        $this->assertNotFalse($wrapperPosition);
        $this->assertNotFalse($labourWrapPosition);
        $this->assertLessThan($labourWrapPosition, $wrapperPosition);
    }
}
