<?php

namespace App\Console\Commands;

use App\Services\Finance\ProjectVoucherAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Runs only on disposable MySQL with synthetic minimal financial tables. */
final class ProbeConcurrentVoucherPosting extends Command
{
    protected $signature = 'finance:probe-concurrent-voucher {project=1} {--seconds=3}';
    protected $description = 'MySQL isolated simultaneous voucher allocation and source/ledger insert probe';

    public function handle(): int
    {
        $db = DB::connection();
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $seconds = filter_var($this->option('seconds'), FILTER_VALIDATE_INT);
        if (config('app.env') !== 'testing' ||
            getenv('FINANCE_ISOLATED_MYSQL_LOCK_TEST') !== 'YES' ||
            $db->getDriverName() !== 'mysql' ||
            !str_contains(strtolower((string) $db->getDatabaseName()), 'finance_lock_test') ||
            $project === false || $seconds === false || $seconds < 1 || $seconds > 8) {
            $this->error('Only isolated testing MySQL finance_lock_test DB, positive project, 1..8 seconds.');
            return self::FAILURE;
        }
        // A fixed marker enforces that a prepared synthetic schema is used.
        // Never infer that a normal finance database is safe by table names alone.
        if (!$db->getSchemaBuilder()->hasTable('finance_concurrency_fixture') ||
            (int) $db->table('finance_concurrency_fixture')->where('id', 1)->value('approved') !== 1) {
            $this->error('Synthetic concurrency fixture not prepared.');
            return self::FAILURE;
        }
        $began = microtime(true);
        $number = $db->transaction(function () use ($project, $seconds, $db) {
            $next = app(ProjectVoucherAllocator::class)->next((int) $project, 'CR');
            sleep($seconds);
            $sourceId = $db->table('customer_ledger')->insertGetId([
                'project_id'=>(int) $project, 'transaction_type'=>'CR',
                'type_id'=>$next, 'reference'=>'SYNTHETIC-CR-' . $next,
            ]);
            $db->table('ledgers')->insert([
                'project_head_subheads_id'=>1, 'customer_ledger_id'=>$sourceId,
                'type'=>'CR', 'type_id'=>$next, 'voucher_number'=>$next,
                'reference'=>'SYNTHETIC-CR-' . $next,
            ]);
            return $next;
        }, 1);
        $this->line(json_encode([
            'database'=>$db->getDatabaseName(), 'voucher_number'=>$number,
            'elapsed_seconds'=>round(microtime(true)-$began,3), 'synthetic_only'=>true,
        ], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
