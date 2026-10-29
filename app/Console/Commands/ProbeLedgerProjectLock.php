<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Disposable MySQL-only lock probe. No financial rows are inserted.
 * Run two processes with the same project to observe serialization.
 */
final class ProbeLedgerProjectLock extends Command
{
    protected $signature = 'finance:probe-ledger-lock {project : Positive project ID} {--seconds=3}';
    protected $description = 'MySQL test-DB-only serialized project row lock check (no data writes)';

    public function handle(): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $seconds = filter_var($this->option('seconds'), FILTER_VALIDATE_INT);
        $connection = DB::connection();
        if ($project === false || $seconds === false || $seconds < 1 || $seconds > 10) {
            $this->error('A positive project ID and 1..10 seconds are required.');
            return self::INVALID;
        }
        // Explicit opt-in and separate disposable DB name avoid accidental
        // production lock tests; APP_ENV alone is not an adequate safeguard.
        if (config('app.env') !== 'testing' ||
            getenv('FINANCE_ISOLATED_MYSQL_LOCK_TEST') !== 'YES' ||
            $connection->getDriverName() !== 'mysql' ||
            !str_contains(strtolower((string) $connection->getDatabaseName()), 'finance_lock_test')) {
            $this->error('Requires APP_ENV=testing, FINANCE_ISOLATED_MYSQL_LOCK_TEST=YES, mysql and a finance_lock_test database.');
            return self::FAILURE;
        }
        $started = microtime(true);
        $connection->transaction(function () use ($project, $seconds, $connection): void {
            if (!$connection->table('projects')->where('id', $project)->lockForUpdate()->exists()) {
                throw new \DomainException('PROJECT_NOT_FOUND_IN_DISPOSABLE_TEST_DATABASE');
            }
            sleep($seconds);
        });
        $this->line(json_encode([
            'project_id' => (int) $project, 'elapsed_seconds' => round(microtime(true) - $started, 3),
            'read_only' => true, 'db_driver' => 'mysql',
        ], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
