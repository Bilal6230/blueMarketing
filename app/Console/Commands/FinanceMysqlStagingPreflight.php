<?php

namespace App\Console\Commands;

use App\Services\Finance\FinanceRolloutAssessment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explicit staging-only, strictly read-only MySQL finance acceptance.
 * Never creates schema or attempts to certify historical ledger ownership.
 */
final class FinanceMysqlStagingPreflight extends Command
{
    protected $signature = 'finance:mysql-staging-preflight
        {--limit=100 : Scan limit, 1 to 1000 projects}
        {--ack-staging : Confirm this is an authorized, isolated staging database}
        {--expected-host= : Required exact staging database host}
        {--expected-database= : Required exact staging database name}';
    protected $description = 'Read-only MySQL staging-only schema and project finance blockers';

    public function handle(FinanceRolloutAssessment $assessment): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Project scan limit must be between 1 and 1000.');
            return self::INVALID;
        }

        // Never query project/ledger records before checking the environment.
        $env = (string) config('app.env');
        if (!$this->option('ack-staging') || $env !== 'staging') {
            $this->error('Staging acknowledgment and APP_ENV=staging are required.');
            return self::FAILURE;
        }
        // Pin the intended target before any financial inspection. Setting
        // APP_ENV=staging must not silently authorize a different database.
        $expectedHost = trim((string) $this->option('expected-host'));
        $expectedDatabase = trim((string) $this->option('expected-database'));
        if ($expectedHost === '' || $expectedDatabase === '') {
            $this->error('Explicit --expected-host and --expected-database are required.');
            return self::FAILURE;
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('An authorized MySQL staging connection is required.');
            return self::FAILURE;
        }

        $connection = DB::connection();
        $configuredHost = (string) $connection->getConfig('host');
        $configuredDatabase = (string) $connection->getDatabaseName();
        if ($configuredHost !== $expectedHost || $configuredDatabase !== $expectedDatabase) {
            $this->error('MySQL target does not match the explicitly approved staging host/database.');
            return self::FAILURE;
        }

        $report = $assessment->inspect((int) $limit);
        $report['context'] = 'staging_read_only';
        $report['deployment_approved'] = false;
        $report['historical_ownership_certified'] = false;
        $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        $this->warn('Read-only staging inventory only: no migrations, ownership backfill, or production approval.');
        return $report['status'] === 'preliminary_ready' ? self::SUCCESS : self::FAILURE;
    }
}
