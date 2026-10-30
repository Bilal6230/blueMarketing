<?php
/**
 * Aggregate-only, READ-ONLY JV candidate inventory on isolated local V2.
 * Candidate identity is NOT typed provenance. No source claims, repairs,
 * row-level payloads, customer data or voucher identifiers are emitted.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, 'V2_JV_CANDIDATE_AUDIT_REFUSED: ' . get_class($error) . PHP_EOL);
    exit(2);
});

$db = DB::connection();
if ($db->getDriverName() !== 'mysql'
    || $db->getDatabaseName() !== 'bluemarketing_v2_local_rehearsal'
    || !in_array((string) $db->getConfig('host'), ['127.0.0.1', 'localhost'], true)
    || (string) ($db->getConfig('url') ?? '') !== ''
    || config('app.env') !== 'local'
    || (int) $db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n !== 1) {
    throw new RuntimeException('Requires exact isolated local V2 database with FKs');
}
if ((int) $db->table('finance_posting_source_claims')->count() !== 0) {
    throw new RuntimeException('Typed claims exist: historic candidate-only audit no longer applicable');
}

/** Exact DECIMAL(15,2) cents, never floats. */
function cents(string $value): int
{
    if (!preg_match('/^-?\d+\.\d{2}$/D', $value)) {
        throw new RuntimeException('Unexpected monetary format');
    }
    $negative = str_starts_with($value, '-');
    $digits = ltrim($value, '-');
    [$whole, $fraction] = explode('.', $digits, 2);
    $amount = ((int) $whole * 100) + (int) $fraction;
    return $negative ? -$amount : $amount;
}
function legKey(string $account, string $debit, string $credit): string
{
    return $account . ':' . cents($debit) . ':' . cents($credit);
}
function addLeg(array &$map, string $key): void
{
    $map[$key] = ($map[$key] ?? 0) + 1;
}

// A consistent read-only transaction: never invokes DML or DDL.
$db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->beginTransaction();
try {
    $allJvHeaders = (int) $db->table('journal_vouchers')->where('type', 'JV')->count();
    $softDeletedJvHeaders = (int) $db->table('journal_vouchers')
        ->where('type', 'JV')->whereNotNull('deleted_at')->count();
    $headers = $db->table('journal_vouchers')
        ->where('type', 'JV')->whereNull('deleted_at')
        ->get(['id', 'project_id', 'total_debit', 'total_credit']);
    $details = $db->table('journal_voucher_details as d')
        ->join('journal_vouchers as j', 'j.id', '=', 'd.journal_voucher_id')
        ->where('j.type', 'JV')->whereNull('j.deleted_at')->whereNull('d.deleted_at')
        ->get(['d.journal_voucher_id', 'd.account_id', 'd.debit', 'd.credit']);
    $legs = $db->table('ledgers as l')
        ->join('project_head_subheads as p', 'p.id', '=', 'l.project_head_subheads_id')
        ->where('l.type', 'JV')
        ->get(['l.type_id', 'p.project_id', 'l.project_head_subheads_id', 'l.amount_in', 'l.amount_out']);

    $byHeader = [];
    foreach ($details as $detail) {
        $id = (string) $detail->journal_voucher_id;
        $key = legKey((string) $detail->account_id, (string) $detail->debit, (string) $detail->credit);
        $byHeader[$id] ??= ['legs' => [], 'debit' => 0, 'credit' => 0, 'count' => 0];
        addLeg($byHeader[$id]['legs'], $key);
        $byHeader[$id]['debit'] = ($byHeader[$id]['debit'] ?? 0) + cents((string) $detail->debit);
        $byHeader[$id]['credit'] = ($byHeader[$id]['credit'] ?? 0) + cents((string) $detail->credit);
        $byHeader[$id]['count'] = ($byHeader[$id]['count'] ?? 0) + 1;
    }
    $candidate = [];
    foreach ($legs as $leg) {
        if ($leg->type_id === null) {
            continue;
        }
        $index = (string) $leg->type_id . ':' . (string) $leg->project_id;
        $key = legKey((string) $leg->project_head_subheads_id, (string) $leg->amount_in, (string) $leg->amount_out);
        $candidate[$index] ??= [];
        addLeg($candidate[$index], $key);
    }
    $result = ['headers' => 0, 'candidate_exact' => 0, 'candidate_mismatch' => 0,
        'no_active_details' => 0, 'header_detail_amount_mismatch' => 0,
        'active_details_without_project_candidate' => 0];
    foreach ($headers as $header) {
        $result['headers']++;
        $id = (string) $header->id;
        $d = $byHeader[$id] ?? ['legs' => [], 'debit' => 0, 'credit' => 0, 'count' => 0];
        $index = $id . ':' . (string) $header->project_id;
        $c = $header->project_id === null ? [] : ($candidate[$index] ?? []);
        if ($d['count'] === 0) {
            $result['no_active_details']++;
        } elseif ($c === []) {
            $result['active_details_without_project_candidate']++;
        }
        if ($d['debit'] !== cents((string) $header->total_debit)
            || $d['credit'] !== cents((string) $header->total_credit)) {
            $result['header_detail_amount_mismatch']++;
        }
        ksort($d['legs']);
        ksort($c);
        $result[$d['legs'] === $c ? 'candidate_exact' : 'candidate_mismatch']++;
    }
    $result['all_jv_headers_including_deleted'] = $allJvHeaders;
    $result['soft_deleted_jv_headers_excluded'] = $softDeletedJvHeaders;
    if ($result['headers'] + $softDeletedJvHeaders !== $allJvHeaders
        || $result['candidate_exact'] + $result['candidate_mismatch'] !== $result['headers']) {
        throw new RuntimeException('JV audit aggregate partition invariant failed');
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
echo 'V2_JV_CANDIDATE_AUDIT_READ_ONLY: ' . json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
echo "LIMITATION: numeric type_id + same-project leg multisets are candidates, never verified source ownership.\n";
