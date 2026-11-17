<?php

namespace App\Console\Commands;

use App\Services\Finance\UnifiedNormalLedgerReport;
use App\Services\Finance\V2PostingGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only local finance merge acceptance probe. Does not mutate sources,
 * ledger history, source claims, project accounts or voucher approvals.
 */
final class AuditNormalFinanceMerge extends Command
{
    protected $signature = 'finance:audit-normal-merge
        {project : Selected project ID (e.g. 3)}
        {--from= : Optional start date, YYYY-MM-DD}
        {--to= : Optional end date, YYYY-MM-DD}';

    protected $description = 'Read-only account/source coverage for the local normal ERP mixed V2 and legacy ledger';

    public function handle(): int
    {
        $projectId = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $service = new UnifiedNormalLedgerReport();
        if (!$projectId || !$service->available()) {
            $this->error('Unavailable: use the approved local normal ERP finance database.');
            return self::FAILURE;
        }
        if (!DB::table('projects')->where('id', $projectId)->exists()) {
            $this->error('Project not found. No data changed.');
            return self::FAILURE;
        }
        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;
        try {
            $mixed = $service->rows($projectId, $from, $to);
            $typed = (new V2PostingGateway())->postingLines($projectId, $from, $to);
        } catch (\Throwable $exception) {
            $this->error('FAIL: ledger/source integrity or date validation failed (read-only).');
            $this->line($exception->getMessage());
            return self::FAILURE;
        }

        $linked = 0;
        $unlinked = 0;
        $sourceKinds = [];
        $linkIds = [];
        foreach ($typed as $line) {
            $sourceKinds[(string) $line['source_kind']] = ($sourceKinds[(string) $line['source_kind']] ?? 0) + 1;
            if ($line['legacy_ledger_id'] === null) {
                $unlinked++;
                continue;
            }
            $linked++;
            $linkIds[] = (int) $line['legacy_ledger_id'];
        }

        // A V2 line with a stale, inactive or foreign-project legacy link
        // MUST NOT be silently skipped by the mixed report.
        if ($linkIds) {
            $existing = DB::table('ledgers as ledger')
                ->join('project_head_subheads as account', 'account.id', '=', 'ledger.project_head_subheads_id')
                ->where('account.project_id', $projectId)
                ->where('ledger.is_active', 1)
                ->whereIn('ledger.id', $linkIds)
                ->distinct()
                ->pluck('ledger.id')->map(fn ($id) => (int) $id)->all();
            if (count(array_diff(array_unique($linkIds), $existing)) > 0) {
                $this->error('FAIL: a typed posting refers to an absent, inactive or foreign-project legacy ledger.');
                return self::FAILURE;
            }
        }
        $legacyCount = count(array_filter($mixed, fn (array $row) => ($row['scope'] ?? '') === 'legacy'));
        $typedCount = count(array_filter($mixed, fn (array $row) => ($row['scope'] ?? '') === 'typed_without_legacy_link'));
        if ($typedCount !== $unlinked || count($mixed) !== $legacyCount + $unlinked) {
            $this->error('FAIL: typed/legacy row coverage mismatch.');
            return self::FAILURE;
        }

        // The typed reader has already verified each posting batch is balanced
        // and every reversal is an exact inverse. Independently reconcile
        // mixed detail rows with their same-account grouped report totals.
        $grouped = $service->totals($mixed, 'project_head_subheads_id');
        $moneyCents = static function (mixed $raw): int {
            $value = str_replace(',', '', (string) $raw);
            if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
                throw new \DomainException('AUDIT_NORMAL_MERGE_INVALID_AMOUNT');
            }
            $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
            return $m[1] === '-' ? -$cents : $cents;
        };
        try {
            $detailDebit = $detailCredit = $groupDebit = $groupCredit = 0;
            foreach ($mixed as $row) {
                $detailDebit += $moneyCents($row['amount_in']);
                $detailCredit += $moneyCents($row['amount_out']);
            }
            foreach ($grouped as $row) {
                $groupDebit += $moneyCents($row['total_amount_in']);
                $groupCredit += $moneyCents($row['total_amount_out']);
            }
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        if ($detailDebit !== $groupDebit || $detailCredit !== $groupCredit) {
            $this->error('FAIL: mixed ledger detail and grouped account totals differ.');
            return self::FAILURE;
        }
        $format = static function (int $amount): string {
            return ($amount < 0 ? '-' : '').intdiv(abs($amount), 100).'.'
                .str_pad((string) (abs($amount) % 100), 2, '0', STR_PAD_LEFT);
        };
        $this->info('PASS: mixed detail-to-account grouping and typed batch/reversal integrity (read-only).');
        $this->table(['Account reconciliation', 'Value'], [
            ['Account groups', (string) count($grouped)],
            ['Mixed debit / cash in', $format($detailDebit)],
            ['Mixed credit / cash out', $format($detailCredit)],
            ['Net mixed balance', $format($detailDebit - $detailCredit)],
        ]);
        $this->info('PASS: normal ERP mixed ledger source coverage (read-only).');
        $this->table(['Metric', 'Count'], [
            ['Project', (string) $projectId],
            ['Active legacy ledger rows', (string) $legacyCount],
            ['Typed posting lines (all)', (string) count($typed)],
            ['Typed lines linked to legacy', (string) $linked],
            ['Typed-only lines included', (string) $unlinked],
            ['Combined displayed ledger rows', (string) count($mixed)],
        ]);
        ksort($sourceKinds);
        $this->table(['Typed source kind', 'Posting lines'],
            array_map(fn ($kind, $count) => [$kind, (string) $count], array_keys($sourceKinds), array_values($sourceKinds)));
        $this->line('The audit verifies source inclusion, NOT independent accounting or browser acceptance.');
        return self::SUCCESS;
    }
}
