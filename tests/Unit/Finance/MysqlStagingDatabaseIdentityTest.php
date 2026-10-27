<?php

namespace Tests\Unit\Finance;

use App\Console\Commands\FinanceMysqlStagingPreflight;
use PHPUnit\Framework\TestCase;

final class MysqlStagingDatabaseIdentityTest extends TestCase
{
    public function test_exact_connected_database_name_matches(): void
    {
        $this->assertTrue(FinanceMysqlStagingPreflight::databaseIdentityMatches(
            'blue_marketing_staging','blue_marketing_staging'));
    }

    public function test_other_schema_cannot_pass_target_check(): void
    {
        $this->assertFalse(FinanceMysqlStagingPreflight::databaseIdentityMatches(
            'blue_marketing_staging','blue_marketing_production'));
    }

    public function test_missing_or_empty_session_database_fails_closed(): void
    {
        $this->assertFalse(FinanceMysqlStagingPreflight::databaseIdentityMatches('staging',null));
        $this->assertFalse(FinanceMysqlStagingPreflight::databaseIdentityMatches('staging',''));
        $this->assertFalse(FinanceMysqlStagingPreflight::databaseIdentityMatches('','staging'));
    }

    public function test_similar_but_not_identical_database_name_is_rejected(): void
    {
        $this->assertFalse(FinanceMysqlStagingPreflight::databaseIdentityMatches(
            'blue_marketing_staging', 'blue_marketing_staging_2'));
    }
}
