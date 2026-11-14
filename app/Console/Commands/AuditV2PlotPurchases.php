<?php

namespace App\Console\Commands;

use App\Services\Finance\V2PlotPurchaseReconciliation;
use Illuminate\Console\Command;

final class AuditV2PlotPurchases extends Command
{
    protected $signature = 'finance:audit-v2-plot-purchases {project : Isolated local project ID}';
    protected $description = 'Read-only PP purchase inventory, seller credit and posting integrity audit';

    public function handle(V2PlotPurchaseReconciliation $reconciler): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$project) {
            $this->error('A positive project ID is required.');
            return self::FAILURE;
        }
        try {
            $rows = $reconciler->audit((int)$project);
        } catch (\Throwable $error) {
            $this->error('Purchase audit unavailable: '.$error->getMessage());
            return self::FAILURE;
        }
        $this->table(['Voucher','Batch','Plots','Amount','Debit','Credit','Result'], array_map(
            static fn (array $r): array => [$r['voucher'],$r['batch'],$r['plots'],
                $r['amount'],$r['debit'],$r['credit'],$r['ok'] ? 'PASS' : implode(', ',$r['issues'])],
            $rows
        ));
        $failed = count(array_filter($rows, static fn (array $r): bool => !$r['ok']));
        $this->line('Project '.$project.': '.count($rows).' purchase(s), '.$failed.' exception(s).');
        if (!$rows) {
            $this->warn('No purchase rows found; this is not an accounting PASS.');
            return self::FAILURE;
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
