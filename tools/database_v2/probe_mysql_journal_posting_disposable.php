<?php
/**
 * CI-only MySQL 8.4 end-to-end financial posting acceptance probe.
 *
 * Seeds a disposable synthetic project, user, accounts and journal into
 * bluemarketing_v2_ci ONLY. Exercises the production V2 writer, replay,
 * conflicting request keys, exact journal-detail source FKs, reversal
 * invariants, reporting, and full cleanup. No historical/private rows.
 */
declare(strict_types=1);

use App\Services\Finance\V2HistoricalImportTargetGuard;
use App\Services\Finance\V2JournalPostingWriter;
use App\Services\Finance\V2PostingGateway;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = config('database.connections.' . config('database.default'));
if (!is_array($config)) {
    throw new RuntimeException('CI_V2_JV_MYSQL_CONFIG_MISSING');
}
V2HistoricalImportTargetGuard::assertAllowed(
    $config, (string) config('app.env'), true
);
$db = DB::connection();

// Independent worker mode is reachable only after the same fail-closed
// CI MySQL connection guard as the parent. It never seeds/cleans data.
if (($argv[1] ?? '') === '--ci-parallel-jv-worker') {
    if ($db->getDriverName() !== 'mysql'
        || (string) $db->getDatabaseName() !== 'bluemarketing_v2_ci'
        || (string) config('app.env') !== 'testing') {
        throw new RuntimeException('CI_V2_PARALLEL_WORKER_WRONG_DATABASE');
    }
    $id = (new V2PostingGateway())->postJournal(
        910001, 910001, 'MYSQL-CI-JV-ORIGINAL-0001', 910001
    );
    echo $id . PHP_EOL;
    exit(0);
}

if ($db->getDriverName() !== 'mysql'
    || (string) $db->getDatabaseName() !== 'bluemarketing_v2_ci'
    || (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS flag')->flag !== 1
    || (int) $db->table('migrations')->count() !== 5
    || (int) $db->table('banks')->count() !== 27
    || (int) $db->table('projects')->count() !== 0
    || (int) $db->table('finance_posting_batches')->count() !== 0) {
    throw new RuntimeException('CI_V2_JV_MYSQL_NOT_EMPTY_DISPOSABLE_BASELINE');
}

$project = 910001;
$user = 910001;
$head = 910001;
$subhead1 = 910001;
$subhead2 = 910002;
$account1 = 910001;
$account2 = 910002;
$journal = 910001;
$line1 = 910001;
$line2 = 910002;
$gateway = new V2PostingGateway();

try {
    $db->table('projects')->insert([
        'id' => $project, 'project' => 'Synthetic CI posting only',
        'address' => 'CI local', 'is_active' => 1, 'create_by' => 0,
    ]);
    $db->table('users')->insert([
        'id' => $user, 'name' => 'CI Synthetic User', 'email' => 'v2-ci-jv@example.invalid',
        'password' => 'ci-not-for-login', 'project_id' => $project,
    ]);
    $db->table('head_accountings')->insert([
        'id' => $head, 'name' => 'Synthetic CI head', 'create_by' => 0,
    ]);
    $db->table('subhead_accountings')->insert([
        ['id' => $subhead1, 'name' => 'Synthetic CI debit', 'is_active' => 1, 'create_by' => 0],
        ['id' => $subhead2, 'name' => 'Synthetic CI credit', 'is_active' => 1, 'create_by' => 0],
    ]);
    $db->table('project_head_subheads')->insert([
        ['id' => $account1, 'project_id' => $project,
            'head_accounting_id' => $head, 'subhead_accounting_id' => $subhead1],
        ['id' => $account2, 'project_id' => $project,
            'head_accounting_id' => $head, 'subhead_accounting_id' => $subhead2],
    ]);
    $db->table('journal_vouchers')->insert([
        'id' => $journal, 'voucher_number' => 77, 'type' => 'JV',
        'date' => '2026-10-30', 'project_id' => $project,
        'created_by' => $user, 'status' => 'approved',
        'total_debit' => '125.50', 'total_credit' => '125.50',
    ]);
    $db->table('journal_voucher_details')->insert([
        ['id' => $line1, 'journal_voucher_id' => $journal,
            'account_id' => $account1, 'debit' => '125.50',
            'credit' => '0.00', 'description' => 'CI source debit'],
        ['id' => $line2, 'journal_voucher_id' => $journal,
            'account_id' => $account2, 'debit' => '0.00',
            'credit' => '125.50', 'description' => 'CI source credit'],
    ]);

    // Two independent PHP application processes race the same voucher and
    // request key. The project row lock + unique source FK must serialize
    // them to exactly one posted batch with identical returned identities.
    $workers = [];
    for ($worker = 0; $worker < 2; $worker++) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, __FILE__, '--ci-parallel-jv-worker'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2)
        );
        if (!is_resource($process)) {
            throw new RuntimeException('CI_V2_PARALLEL_PROC_OPEN_FAILED');
        }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $postedIds = [];
    foreach ($workers as [$process, $pipes]) {
        $out = trim((string) stream_get_contents($pipes[1]));
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || !preg_match('/^[1-9][0-9]*$/D', $out)) {
            throw new RuntimeException('CI_V2_PARALLEL_POST_FAILED_' . $status
                . ': ' . substr(trim($err), 0, 400));
        }
        $postedIds[] = (int) $out;
    }
    [$original, $parallelReplay] = $postedIds;
    if ($original !== $parallelReplay) {
        throw new RuntimeException('CI_V2_PARALLEL_DUPLICATE_BATCH_CREATED');
    }
    $replay = $gateway->postJournal(
        $project, $journal, 'MYSQL-CI-JV-ORIGINAL-0001', $user
    );
    if ($original !== $replay
        || $original !== $parallelReplay
        || (int) $db->table('finance_posting_batches')->count() !== 1
        || (int) $db->table('finance_journal_line_sources')->count() !== 2
        || (int) $db->table('finance_posting_lines')->count() !== 2) {
        throw new RuntimeException('CI_V2_JV_POSTING_OR_REPLAY_FAILED');
    }

    $sourceIds = $db->table('finance_journal_line_sources')
        ->orderBy('journal_voucher_detail_id')
        ->pluck('journal_voucher_detail_id')
        ->map(static fn ($id): int => (int) $id)->all();
    if ($sourceIds !== [$line1, $line2]) {
        throw new RuntimeException('CI_V2_JV_EXACT_DETAIL_FKS_FAILED');
    }
    $anotherKeyBlocked = false;
    try {
        $gateway->postJournal(
            $project, $journal, 'MYSQL-CI-JV-OTHER-KEY-0001', $user
        );
    } catch (\Illuminate\Database\QueryException|\DomainException $error) {
        $anotherKeyBlocked = true;
    }
    if (!$anotherKeyBlocked
        || (int) $db->table('finance_posting_batches')->count() !== 1) {
        throw new RuntimeException('CI_V2_JV_DUPLICATE_SOURCE_NOT_BLOCKED');
    }

    $reversal = $gateway->reversePostedBatch(
        $project, $original, 'MYSQL-CI-JV-REVERSAL-0001',
        '2026-10-31', 'CI corrective reversal', $user
    );
    if ($reversal === $original
        || $gateway->reversePostedBatch(
            $project, $original, 'MYSQL-CI-JV-REVERSAL-0001',
            '2026-10-31', 'CI corrective reversal', $user
        ) !== $reversal
        || (int) $db->table('finance_posting_batches')->count() !== 2
        || (int) $db->table('finance_posting_lines')->count() !== 4
        || (int) $db->table('finance_posting_journal_sources')->count() !== 1) {
        throw new RuntimeException('CI_V2_JV_REVERSAL_FK_OR_REPLAY_FAILED');
    }

    $trial = $gateway->trialBalance($project);
    if (($trial['balanced'] ?? false) !== true
        || ($trial['total_period_debit'] ?? null) !== '251.00'
        || ($trial['total_period_credit'] ?? null) !== '251.00'
        || count($trial['accounts']) !== 2
        || ($trial['accounts'][0]['closing_balance'] ?? null) !== '0.00'
        || ($trial['accounts'][1]['closing_balance'] ?? null) !== '0.00') {
        throw new RuntimeException('CI_V2_JV_MYSQL_REPORT_RECONCILIATION_FAILED');
    }
    echo "CI_V2_MYSQL_PARALLEL_JV_POST_PASS: two independent workers, one source and identical batch identity\n";
    echo "CI_V2_MYSQL_POSTING_REVERSAL_PASS: exact detail FKs, replay, exclusive source, balanced correction and zero closing balances\n";
    // Sales/booking SV vouchers share the verified physical journal header
    // and detail tables, but MUST retain their explicit SV series.
    $db->table('journal_vouchers')->insert([
        'id' => 910002, 'voucher_number' => 78, 'type' => 'SV',
        'date' => '2026-10-30', 'project_id' => $project,
        'created_by' => $user, 'status' => 'approved',
        'total_debit' => '30.25', 'total_credit' => '30.25',
    ]);
    $db->table('journal_voucher_details')->insert([
        ['id' => 910003, 'journal_voucher_id' => 910002,
            'account_id' => $account1, 'debit' => '30.25',
            'credit' => '0.00', 'description' => 'Synthetic SV debit'],
        ['id' => 910004, 'journal_voucher_id' => 910002,
            'account_id' => $account2, 'debit' => '0.00',
            'credit' => '30.25', 'description' => 'Synthetic SV credit'],
    ]);
    $sales = $gateway->postSalesVoucher(
        $project, 910002, 'MYSQL-CI-SALES-SV-POST-0001', $user
    );
    if ($sales === $original || $sales === $reversal
        || $gateway->postSalesVoucher(
            $project, 910002, 'MYSQL-CI-SALES-SV-POST-0001', $user
        ) !== $sales
        || (string) $db->table('finance_posting_batches')
            ->where('id', $sales)->value('voucher_series') !== 'SV'
        || (int) $db->table('finance_posting_journal_sources')
            ->where('journal_voucher_id', 910002)->count() !== 1) {
        throw new RuntimeException('CI_V2_SALES_EXACT_JOURNAL_FK_FAILED');
    }
    try {
        $gateway->postJournal(
            $project, 910002, 'MYSQL-CI-SALES-WRONG-JV-0001', $user
        );
        throw new RuntimeException('CI_V2_SALES_IMPROPERLY_ACCEPTED_AS_JV');
    } catch (DomainException $expected) {
        if ($expected->getMessage() !== 'V2_JV_HEADER_NOT_ACTIVE') {
            throw $expected;
        }
    }
    echo "CI_V2_MYSQL_SALES_SV_TYPED_SOURCE_PASS: real header/detail IDs and wrong-kind guard\n";

} finally {
    // Runs exclusively against a fresh disposable CI database. Physical FK
    // constraints stay ENABLED; delete child rows before source parents.
    $db->table('finance_journal_line_sources')->delete();
    $db->table('finance_posting_lines')->delete();
    $db->table('finance_posting_journal_sources')->delete();
    $db->table('finance_posting_source_claims')->delete();
    $db->table('finance_posting_batches')->whereNotNull('reversal_of_batch_id')->delete();
    $db->table('finance_posting_batches')->delete();

    $db->table('journal_voucher_details')->whereIn('journal_voucher_id', [$journal, 910002])->delete();
    $db->table('journal_vouchers')->whereIn('id', [$journal, 910002])->delete();
    $db->table('project_head_subheads')->where('project_id', $project)->delete();
    $db->table('subhead_accountings')->whereIn('id', [$subhead1, $subhead2])->delete();
    $db->table('head_accountings')->where('id', $head)->delete();
    $db->table('users')->where('id', $user)->delete();
    $db->table('projects')->where('id', $project)->delete();
}
if ((int) $db->table('projects')->count() !== 0
    || (int) $db->table('journal_vouchers')->count() !== 0
    || (int) $db->table('finance_posting_batches')->count() !== 0) {
    throw new RuntimeException('CI_V2_JV_SYNTHETIC_CLEANUP_FAILED');
}
echo "CI_V2_MYSQL_POSTING_CLEAN_DISPOSABLE_BASELINE_PASS\n";
