<?php

namespace App\Console\Commands;

use App\Services\Finance\V2BankTransferReconciliation;
use Illuminate\Console\Command;

/** Read-only, explicit isolated project audit; never modifies account balances. */
final class AuditV2BankTransfers extends Command
{
    protected $signature = 'finance:audit-v2-bank-transfers {project : Isolated test project ID}';
    protected $description = 'Audit isolated BD/BW ledger directions, balance, source amount, and project ownership (read-only)';

    public function handle(V2BankTransferReconciliation $audit): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$project) {
            $this->error('Provide a positive project ID.');
            return self::FAILURE;
        }
        try {
            $rows = $audit->audit((int) $project);
        } catch (\Throwable $error) {
            $this->error('Audit unavailable: '.$error->getMessage());
            return self::FAILURE;
        }

        $this->table(['Batch', 'Voucher', 'Kind', 'Amount', 'Debit', 'Credit', 'Lines', 'Result'], array_map(
            static fn (array $row): array => [
                $row['posting_batch_id'], $row['voucher'], $row['kind'], $row['amount'],
                $row['debit'], $row['credit'], $row['line_count'],
                $row['ok'] ? 'PASS' : implode(', ', $row['issues']),
            ], $rows
        ));
        $failed = count(array_filter($rows, static fn (array $row): bool => !$row['ok']));
        $this->line('Project '.$project.': '.count($rows).' transfer(s), '.$failed.' exception(s).');
        if (!$rows) {
            $this->warn('No transfers found; this is not an accounting PASS.');
        }
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
