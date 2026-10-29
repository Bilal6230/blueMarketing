<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only preflight: NEVER adds an index or rewrites historical rows.
 * Use it before considering source and ledger constraints on MySQL.
 */
final class VerifyLedgerConstraints extends Command
{
    protected $signature = 'finance:preflight-ledger-constraints {project : Positive project ID}';
    protected $description = 'Read-only duplicate source, missing owner, cross-project and missing account evidence';

    public function handle(): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($project === false) {
            $this->error('Positive project ID required.');
            return self::INVALID;
        }
        $families = ['CR', 'CP', 'PPR', 'EC', 'ECP'];
        $sources = DB::table('customer_ledger')->where('project_id', $project)
            ->whereIn('transaction_type', $families)
            ->get(['id', 'transaction_type', 'type_id', 'reference']);
        $issues = [];
        foreach (['type_id' => 'source_duplicate_number', 'reference' => 'source_duplicate_reference'] as $field => $reason) {
            foreach ($sources->filter(fn ($s) => $s->$field !== null && (string) $s->$field !== '')
                ->groupBy(fn ($s) => $s->transaction_type . ':' . $s->$field) as $group) {
                if ($group->count() > 1) {
                    $issues[] = ['reason' => $reason, 'source_ids' => $group->pluck('id')->all()];
                }
            }
        }
        $legs = DB::table('ledgers as l')
            ->leftJoin('customer_ledger as s', 's.id', '=', 'l.customer_ledger_id')
            ->leftJoin('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->where(function ($query) use ($project, $sources) {
                $query->where('a.project_id', $project);
                if ($sources->isNotEmpty()) {
                    $query->orWhereIn('l.customer_ledger_id', $sources->pluck('id'));
                }
                // A missing account would disappear from an account-scoped
                // query. Include it so it cannot evade the integrity inventory.
                $query->orWhereNull('a.id');
            })
            ->get(['l.id', 'l.type', 'l.customer_ledger_id', 'l.project_head_subheads_id',
                's.project_id as source_project', 's.transaction_type as source_type',
                'a.project_id as account_project']);
        foreach ($legs as $leg) {
            if ($leg->account_project === null) {
                $issues[] = ['reason' => 'ledger_missing_account', 'ledger_id' => $leg->id];
            } elseif ((int) $leg->account_project !== (int) $project) {
                $issues[] = ['reason' => 'ledger_foreign_account', 'ledger_id' => $leg->id];
            }
            if ($leg->customer_ledger_id !== null && $leg->source_project === null) {
                $issues[] = ['reason' => 'ledger_orphan_source', 'ledger_id' => $leg->id];
            } elseif ($leg->customer_ledger_id !== null &&
                (int) $leg->source_project !== (int) $leg->account_project) {
                $issues[] = ['reason' => 'ledger_cross_project_source', 'ledger_id' => $leg->id];
            }
        }
        $this->line(json_encode([
            'project_id' => (int) $project, 'source_count' => $sources->count(),
            'ledger_count' => $legs->count(), 'issues' => $issues,
            'constraint_applied' => false, 'read_only' => true,
        ], JSON_THROW_ON_ERROR));
        return $issues ? self::FAILURE : self::SUCCESS;
    }
}
