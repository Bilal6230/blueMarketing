<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditExtraChargeAccountEvidence extends Command
{
    protected $signature = 'finance:audit-ec-account-evidence {project : Positive project ID}';
    protected $description = 'Read-only evidence for legacy Extra Charge source and project account mapping; never approves accounts';

    public function handle(): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($project === false) {
            $this->error('Positive project ID required.');
            return self::INVALID;
        }

        $types = DB::table('charge_types')->orderBy('id')->get(['id', 'name']);
        $sources = DB::table('customer_ledger')
            ->where('project_id', $project)
            ->whereIn('transaction_type', ['null', 'EC'])
            ->orderBy('id')->get([
                'id', 'transaction_type', 'type_id', 'reference',
                'customer_id', 'plot_id', 'amount_in', 'amount_out',
                'date', 'payment_type',
            ]);
        $sourceIds = $sources->pluck('id')->all();
        $legs = $sourceIds
            ? DB::table('ledgers as l')
                ->leftJoin('project_head_subheads as ph', 'ph.id', '=', 'l.project_head_subheads_id')
                ->leftJoin('head_accountings as h', 'h.id', '=', 'ph.head_accounting_id')
                ->leftJoin('subhead_accountings as sh', 'sh.id', '=', 'ph.subhead_accounting_id')
                ->whereIn('l.customer_ledger_id', $sourceIds)
                ->orderBy('l.id')
                ->get([
                    'l.id', 'l.customer_ledger_id', 'l.type', 'l.voucher_number',
                    'l.reference', 'l.amount_in', 'l.amount_out',
                    'l.project_head_subheads_id', 'ph.project_id as account_project_id',
                    'h.name as head_name', 'sh.name as subhead_name',
                ]) : collect();

        $bySource = $legs->groupBy('customer_ledger_id');
        $records = $sources->map(function ($source) use ($bySource) {
            $linked = $bySource->get($source->id, collect());
            return [
                'source' => $source,
                'ledger_legs' => $linked->values()->all(),
                'legacy_charge_type_link_proven' => false,
                'has_balanced_two_legs' => $linked->count() === 2 &&
                    abs((float) $linked->sum('amount_in') - (float) $linked->sum('amount_out')) < 0.005,
            ];
        });

        $projectAccounts = DB::table('project_head_subheads as ph')
            ->join('head_accountings as h', 'h.id', '=', 'ph.head_accounting_id')
            ->join('subhead_accountings as sh', 'sh.id', '=', 'ph.subhead_accounting_id')
            ->where('ph.project_id', $project)
            ->orderBy('ph.id')
            ->get(['ph.id', 'h.name as head_name', 'sh.name as subhead_name']);

        $this->line(json_encode([
            'project_id' => (int) $project,
            'charge_types' => $types,
            'legacy_or_typed_sources' => $records,
            'project_accounts' => $projectAccounts,
            'mapping_approved' => false,
            'limitations' => [
                'legacy customer_ledger sources lack a trustworthy charge_type_id to project account mapping',
                'historic CR/CP ledger legs may not represent a balanced Extra Charge source',
                'account names and historical occurrences are evidence only, not finance approval',
            ],
            'read_only' => true,
        ], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
