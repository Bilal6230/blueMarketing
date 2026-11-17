<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class UnifiedFinanceNamingTest extends TestCase
{
    public function test_application_paths_do_not_expose_separate_finance_version(): void
    {
        $root = dirname(__DIR__, 3);

        foreach (['app', 'config', 'routes', 'resources'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                self::assertDoesNotMatchRegularExpression(
                    '/(?:^|[\\\\\/._-])v2(?:[\\\\\/._-]|[A-Z]|$)/i',
                    $relative,
                    'Finance must use normal application paths: '.$relative
                );
            }
        }
    }

    public function test_finance_routes_are_not_pilot_or_database_name_gated(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = file_get_contents($root.'/routes/api.php');
        $access = file_get_contents($root.'/app/Services/Finance/FinanceWorkflowAccess.php');
        $guard = file_get_contents($root.'/app/Services/Finance/FinanceConnectionGuard.php');

        self::assertIsString($routes);
        self::assertIsString($access);
        self::assertIsString($guard);

        self::assertStringContainsString("->prefix('finance/projects/{projectId}')", $routes);
        self::assertStringNotContainsString('pilot_api_enabled', $routes);
        self::assertStringNotContainsString('pilot_write_enabled', $routes);
        self::assertStringNotContainsString("config('app.env') === 'local'", $access);
        self::assertStringNotContainsString('bluenew_clean_final', $guard);
        self::assertStringNotContainsString('bluemarketing_v2_', $guard);
    }
}
