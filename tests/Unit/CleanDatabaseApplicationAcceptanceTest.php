<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CleanDatabaseApplicationAcceptanceTest extends TestCase
{
    public function test_read_only_acceptance_uses_protected_local_database(): void
    {
        $path=dirname(__DIR__,2).'/tools/schema/accept_clean_application.php';
        $source=file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString("bluenew_clean_final", $source);
        self::assertStringContainsString("app()->environment('local')", $source);
        self::assertStringContainsString("count(\$tables)===147", $source);
        self::assertStringContainsString("\$history===149", $source);
        self::assertStringContainsString("\$sum===120835", $source);
        self::assertStringContainsString("\$fk===250", $source);
        self::assertStringContainsString("NOT EXISTS", $source);
        self::assertStringNotContainsString("DB::table('migrations')->insert", $source);
        self::assertStringNotContainsString("Schema::create", $source);
        self::assertStringNotContainsString("->delete(", $source);
        self::assertStringNotContainsString("->update(", $source);
        self::assertStringNotContainsString("->insert(", $source);
    }
}
