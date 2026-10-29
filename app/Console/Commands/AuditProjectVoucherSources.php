<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditProjectVoucherSources extends Command
{
    protected $signature = 'finance:audit-project-vouchers {project : Positive project ID}';
    protected $description = 'Read-only typed source, ledger and journal linkage inventory; no automatic repair';

    public function handle(): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($project === false) {
            $this->error('Positive project ID required.');
            return self::INVALID;
        }
        $types = ['CR', 'CP', 'PPR', 'EC', 'ECP', 'JV', 'SV', 'BR'];
        $sources = DB::table('customer_ledger')
            ->where('project_id', $project)->whereIn('transaction_type', $types)
            ->select('id', 'transaction_type', 'reference', 'type_id', 'amount_in', 'amount_out')
            ->orderBy('id')->get();
        $sourceIds = $sources->pluck('id')->all();
        $ledgerRows = DB::table('ledgers as l')
            ->leftJoin('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->whereIn('l.type', $types)
            ->where(function ($q) use ($project, $sourceIds) {
                $q->where('a.project_id', $project);
                if ($sourceIds) {
                    $q->orWhereIn('l.customer_ledger_id', $sourceIds);
                }
            })
            ->select('l.id', 'l.customer_ledger_id', 'l.type', 'l.reference',
                'l.voucher_number', 'l.project_head_subheads_id',
                'a.project_id as account_project_id', 'l.amount_in', 'l.amount_out')
            ->orderBy('l.id')->get();
        $bySource = $ledgerRows->filter(fn ($l) => $l->customer_ledger_id !== null)
            ->groupBy('customer_ledger_id');
        $issues = [];
        foreach ($sources as $source) {
            $legs = $bySource->get($source->id, collect());
            if ($legs->isEmpty()) {
                $issues[] = ['source_id' => $source->id, 'type' => $source->transaction_type,
                    'reason' => 'source_without_ledger'];
                continue;
            }
            foreach ($legs as $leg) {
                if ((string) $leg->type !== (string) $source->transaction_type ||
                    (int) $leg->account_project_id !== (int) $project ||
                    (string) $leg->reference !== (string) $source->reference) {
                    $issues[] = ['source_id' => $source->id, 'ledger_id' => $leg->id,
                        'reason' => 'source_ledger_identity_mismatch'];
                }
            }
            // EC and its source-linked cash settlements are explicitly
            // balanced two-leg postings. Other families have distinct contracts.
            if (in_array($source->transaction_type, ['EC', 'ECP'], true)) {
                $incoming = $legs->sum(fn ($l) => (int) round((float) $l->amount_in * 100));
                $outgoing = $legs->sum(fn ($l) => (int) round((float) $l->amount_out * 100));
                if ($legs->count() !== 2 || $incoming !== $outgoing ||
                    $incoming !== (int) round((float) $source->amount_in * 100)) {
                    $issues[] = ['source_id' => $source->id, 'reason' => 'ec_or_ecp_legs_unbalanced'];
                }
            }
        }
        foreach ($ledgerRows as $leg) {
            if ($leg->customer_ledger_id !== null &&
                !$sources->contains('id', $leg->customer_ledger_id)) {
                $issues[] = ['ledger_id' => $leg->id, 'reason' => 'orphan_or_foreign_customer_source'];
            }
        }
        $journals = DB::table('journal_vouchers')
            ->where('project_id', $project)->where('type', 'JV')
            ->select('id', 'voucher_number')->get();
        foreach ($journals as $header) {
            if (!$ledgerRows->contains(fn ($leg) =>
                $leg->type === 'JV' && (int) $leg->voucher_number === (int) $header->voucher_number)) {
                $issues[] = ['journal_id' => $header->id, 'reason' => 'jv_header_without_ledger'];
            }
        }
        $this->line(json_encode([
            'project_id' => (int) $project,
            'source_count' => $sources->count(),
            'ledger_count' => $ledgerRows->count(),
            'journal_count' => $journals->count(),
            'issues' => $issues,
            'read_only' => true,
        ], JSON_THROW_ON_ERROR));
        return $issues ? self::FAILURE : self::SUCCESS;
    }
}
