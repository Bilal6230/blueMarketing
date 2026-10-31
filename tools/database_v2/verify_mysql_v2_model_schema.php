<?php
/** READ ONLY, fail-closed V2 MySQL contract verification; no business rows read. */
declare(strict_types=1);

use App\Models\V2\FinancePostingBatch;
use App\Models\V2\FinancePostingLine;
use App\Models\V2\FinancePostingSourceClaim;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'V2_MYSQL_MODEL_CONTRACT_REFUSED: ' . get_class($e) . PHP_EOL);
    exit(2);
});

// Fail closed BEFORE connecting: disposable CI schema, loopback MySQL,
// no replication hosts, DB URL, unix socket, staging or production.
$settings = config('database.connections.' . config('database.default'));
if (!is_array($settings)) {
    throw new RuntimeException('V2_MYSQL_CI_CONFIG_REQUIRED');
}
\App\Services\Finance\V2HistoricalImportTargetGuard::assertAllowed(
    $settings, (string) config('app.env'), true
);

$db = DB::connection();
$name = (string) $db->getDatabaseName();
if ($db->getDriverName() !== 'mysql'
    || !in_array($name, ['bluemarketing_v2_ci', 'bluemarketing_v2_local_rehearsal'], true)
    || !in_array((string) $db->getConfig('host'), ['127.0.0.1', 'localhost'], true)
    || (string) ($db->getConfig('url') ?? '') !== ''
    || !in_array(config('app.env'), ['testing', 'local'], true)
    || (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1) {
    throw new RuntimeException('Disallowed V2 MySQL model verification target');
}

$models = [new FinancePostingBatch(), new FinancePostingLine(), new FinancePostingSourceClaim()];
foreach ($models as $model) {
    if (!Schema::hasTable($model->getTable())) {
        throw new RuntimeException('V2 typed finance model table missing');
    }
}
$relations = [
    [(new FinancePostingBatch())->lines(), 'posting_batch_id', 'finance_posting_lines'],
    [(new FinancePostingBatch())->reversal(), 'reversal_of_batch_id', 'finance_posting_batches'],
    [(new FinancePostingBatch())->originalBatch(), 'reversal_of_batch_id', 'finance_posting_batches'],
    [(new FinancePostingBatch())->sourceClaim(), 'posting_batch_id', 'finance_posting_source_claims'],
    [(new FinancePostingLine())->batch(), 'posting_batch_id', 'finance_posting_lines'],
    [(new FinancePostingLine())->account(), 'project_head_subhead_id', 'finance_posting_lines'],
    [(new FinancePostingSourceClaim())->batch(), 'posting_batch_id', 'finance_posting_source_claims'],
];
foreach ($relations as [$relation, $column, $table]) {
    if ($relation->getForeignKeyName() !== $column || !Schema::hasColumn($table, $column)) {
        throw new RuntimeException('V2 typed model relationship/column mismatch');
    }
}

$line = new FinancePostingLine();
foreach (['debit', 'credit'] as $nameColumn) {
    $column = $db->selectOne(
        'SELECT DATA_TYPE AS data_type, NUMERIC_SCALE AS scale FROM information_schema.COLUMNS'
        . ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
        ['finance_posting_lines', $nameColumn]
    );
    if (!$column || $column->data_type !== 'decimal'
        || (int) $column->scale !== 2 || $line->getCasts()[$nameColumn] !== 'decimal:2') {
        throw new RuntimeException('V2 monetary cast/database DECIMAL scale mismatch');
    }
}

$indexes = $db->select(
    'SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique FROM information_schema.STATISTICS'
    . ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
    ['finance_posting_batches']
);
$uniques = [];
foreach ($indexes as $index) {
    if ((int) $index->non_unique === 0) {
        $uniques[(string) $index->index_name] = true;
    }
}
foreach (['v2_posting_project_idempotency_unique', 'v2_one_reversal_per_batch_unique'] as $required) {
    if (!isset($uniques[$required])) {
        throw new RuntimeException('V2 posting idempotency/reversal uniqueness missing');
    }
}

echo 'V2_MYSQL_MODEL_CONTRACT_PASS: 3 typed models, 7 FK relations, '
    . '2 DECIMAL casts, 2 posting unique constraints; no rows altered.' . PHP_EOL;
