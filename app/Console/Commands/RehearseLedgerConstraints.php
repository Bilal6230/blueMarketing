<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** MySQL-only disposable DDL rehearsal; never alters real financial tables. */
final class RehearseLedgerConstraints extends Command
{
    protected $signature = 'finance:rehearse-ledger-constraints';
    protected $description = 'Test source uniqueness and project-aware FKs on disposable tables only';

    public function handle(): int
    {
        $db = DB::connection();
        if (config('app.env') !== 'testing' ||
            getenv('FINANCE_ISOLATED_MYSQL_LOCK_TEST') !== 'YES' ||
            $db->getDriverName() !== 'mysql' ||
            !str_contains(strtolower((string) $db->getDatabaseName()), 'finance_lock_test')) {
            $this->error('Requires testing environment and explicitly isolated finance_lock_test MySQL DB.');
            return self::FAILURE;
        }
        $source = 'finance_constraint_sources_test';
        $account = 'finance_constraint_accounts_test';
        $leg = 'finance_constraint_legs_test';
        foreach ([$leg, $source, $account] as $table) {
            if ($db->getSchemaBuilder()->hasTable($table)) {
                $this->error('Refusing existing rehearsal table: ' . $table);
                return self::FAILURE;
            }
        }
        try {
            $db->statement("CREATE TABLE $source (
                id BIGINT UNSIGNED NOT NULL PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL,
                series VARCHAR(8) NOT NULL, number BIGINT UNSIGNED NOT NULL,
                reference VARCHAR(150) NOT NULL,
                UNIQUE KEY uq_rehearsal_num (project_id, series, number),
                UNIQUE KEY uq_rehearsal_ref (project_id, series, reference),
                UNIQUE KEY uq_rehearsal_source_project (id, project_id)
            ) ENGINE=InnoDB");
            $db->statement("CREATE TABLE $account (
                id BIGINT UNSIGNED NOT NULL PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL,
                UNIQUE KEY uq_rehearsal_account_project (id, project_id)
            ) ENGINE=InnoDB");
            $db->statement("CREATE TABLE $leg (
                id BIGINT UNSIGNED NOT NULL PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL,
                CONSTRAINT fk_rehearsal_source FOREIGN KEY (source_id, project_id)
                  REFERENCES $source (id, project_id),
                CONSTRAINT fk_rehearsal_account FOREIGN KEY (account_id, project_id)
                  REFERENCES $account (id, project_id)
            ) ENGINE=InnoDB");
            $db->table($source)->insert(['id'=>1,'project_id'=>1,'series'=>'CR','number'=>9,'reference'=>'TEST-CR-9']);
            $db->table($account)->insert([['id'=>1,'project_id'=>1],['id'=>2,'project_id'=>2]]);
            $db->table($leg)->insert(['id'=>1,'project_id'=>1,'source_id'=>1,'account_id'=>1]);
            $checks = [
                'duplicate_number_rejected' => fn () => $db->table($source)->insert(['id'=>2,'project_id'=>1,'series'=>'CR','number'=>9,'reference'=>'OTHER']),
                'duplicate_reference_rejected' => fn () => $db->table($source)->insert(['id'=>3,'project_id'=>1,'series'=>'CR','number'=>10,'reference'=>'TEST-CR-9']),
                'orphan_source_rejected' => fn () => $db->table($leg)->insert(['id'=>2,'project_id'=>1,'source_id'=>999,'account_id'=>1]),
                'foreign_account_rejected' => fn () => $db->table($leg)->insert(['id'=>3,'project_id'=>1,'source_id'=>1,'account_id'=>2]),
                'foreign_source_rejected' => fn () => $db->table($leg)->insert(['id'=>4,'project_id'=>2,'source_id'=>1,'account_id'=>2]),
            ];
            $results = [];
            foreach ($checks as $name => $attempt) {
                try {
                    $attempt();
                    $results[$name] = false;
                } catch (QueryException $error) {
                    $code = (int) ($error->errorInfo[1] ?? 0);
                    $results[$name] = str_contains($name, 'duplicate') ? $code === 1062 : $code === 1452;
                }
            }
            $passed = !in_array(false, $results, true);
            $this->line(json_encode(['database'=>$db->getDatabaseName(),
                'checks'=>$results,'all_passed'=>$passed,'application_tables_modified'=>false], JSON_THROW_ON_ERROR));
            return $passed ? self::SUCCESS : self::FAILURE;
        } finally {
            foreach ([$leg, $source, $account] as $table) {
                $db->statement("DROP TABLE IF EXISTS $table");
            }
        }
    }
}
