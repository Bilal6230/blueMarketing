<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class AllMigrationFilesSyntaxTest extends TestCase
{
    public function test_all_current_migrations_are_valid_php(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = glob($root.'/database/migrations/*.php');

        self::assertCount(148, $paths, 'Expected 146 table migrations + guard + FK migration');

        foreach ($paths as $path) {
            $process = new Process([PHP_BINARY, '-l', $path]);
            $process->run();

            self::assertTrue(
                $process->isSuccessful(),
                basename($path).': '.$process->getOutput().$process->getErrorOutput()
            );
        }
    }

    public function test_legacy_migrations_remain_archived(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertCount(110, glob($root.'/database/old_migration/*.php'));
    }
}
