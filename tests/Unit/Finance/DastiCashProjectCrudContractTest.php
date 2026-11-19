<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class DastiCashProjectCrudContractTest extends TestCase
{
    public function test_cash_crud_routes_are_permissioned_and_delete_cannot_escape_selected_project(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/DastiCashController.php');
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString("permission:delete dasticash",$routes);
        self::assertStringContainsString("permission:update dasticash",$routes);
        $method=substr($controller,strpos($controller,'public function destroy('));
        self::assertStringContainsString("where('project_id', getSelectedTown())",$method);
        self::assertStringNotContainsString('Dasticash::findOrFail($id)',$method);
        self::assertStringContainsString('$record->delete()',$method);
    }
}
