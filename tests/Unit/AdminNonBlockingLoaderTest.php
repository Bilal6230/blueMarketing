<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminNonBlockingLoaderTest extends TestCase
{
    public function test_admin_shell_does_not_render_a_full_page_preloader(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = file_get_contents($root.'/resources/views/admin/layouts/master.blade.php');

        self::assertIsString($layout);
        self::assertStringNotContainsString('id="load_screen"', $layout);
        self::assertStringNotContainsString('div#load_screen', $layout);
        self::assertStringContainsString("@yield('content')", $layout);
        self::assertStringContainsString("asset('js/blue-table-skeleton.js')", $layout);
        self::assertStringContainsString("asset('css/blue-table-skeleton.css')", $layout);
    }

    public function test_skeleton_is_scoped_to_requested_table_instead_of_covering_the_page(): void
    {
        $root = dirname(__DIR__, 2);
        $js = file_get_contents($root.'/public/js/blue-table-skeleton.js');
        $css = file_get_contents($root.'/public/css/blue-table-skeleton.css');

        self::assertIsString($js);
        self::assertIsString($css);
        self::assertStringContainsString('$.blueTableSkeleton', $js);
        self::assertStringContainsString("closest('.table-responsive')", $js);
        self::assertStringContainsString('bm-skeleton-row', $js);
        self::assertStringContainsString('bm-skeleton-pulse', $css);
        self::assertStringContainsString('prefers-reduced-motion', $css);
    }
}
