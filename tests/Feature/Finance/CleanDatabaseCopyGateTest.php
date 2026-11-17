<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;

final class CleanDatabaseCopyGateTest extends TestCase
{
    public function test_disposable_copy_requires_local_database_and_preserves_zero_dates(): void
    {
        $script = file_get_contents(base_path('tools/schema/copy_clean_data.php'));

        $this->assertStringContainsString("'bluenew_clean'", $script);
        $this->assertStringContainsString("'bluenew'", $script);
        $this->assertStringContainsString("app()->environment('local')", $script);
        $this->assertStringContainsString('NO_AUTO_VALUE_ON_ZERO', $script);
        $this->assertStringContainsString('SET SESSION sql_mode', $script);
        $this->assertStringContainsString('SET SESSION FOREIGN_KEY_CHECKS=1', $script);
        $this->assertStringContainsString('Source-vs-destination row data mismatch', $script);
        $this->assertStringContainsString("'--resume'", $script);
        $this->assertStringContainsString("if ($table==='migrations') continue;", $script);
        $this->assertStringNotContainsString('DROP DATABASE', strtoupper($script));
        $this->assertStringNotContainsString('TRUNCATE TABLE', strtoupper($script));
    }
}
