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
            ->select('id', 'customer_id', 'plot_id', 'transaction_type', 'reference', 'type_id', 'amount_in', 'amount_out', 'payment_type', 'passing_status', 'is_active')
            ->orderBy('id')->get();
        $sourceIds = $sources->pluck('id')->all();
        $ledgerRows = DB::table('ledgers as l')
            ->leftJoin('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->where(function ($q) use ($project, $sourceIds) {
                $q->where('a.project_id', $project);
                if ($sourceIds) {
                    $q->orWhereIn('l.customer_ledger_id', $sourceIds);
                }
            })
            ->select('l.id', 'l.customer_ledger_id', 'l.type', 'l.type_id', 'l.reference',
                'l.voucher_number', 'l.project_head_subheads_id',
                'a.project_id as account_project_id', 'l.amount_in', 'l.amount_out', 'l.is_active')
            ->orderBy('l.id')->get();
        $bySource = $ledgerRows->filter(fn ($l) => $l->customer_ledger_id !== null)
            ->groupBy('customer_ledger_id');
        $issues = [];
        // Duplicate typed source identities must be surfaced before adding
        // any physical uniqueness constraint; legacy data is left untouched.
        foreach ($sources->whereIn('transaction_type', ['CR', 'CP', 'PPR', 'EC', 'ECP'])
            ->groupBy(fn ($row) => $row->transaction_type . ':' . $row->type_id) as $group) {
            if ($group->count() > 1) {
                $issues[] = ['source_ids' => $group->pluck('id')->values()->all(),
                    'reason' => 'duplicate_project_source_number'];
            }
        }
        foreach ($sources->whereIn('transaction_type', ['CR', 'CP', 'PPR', 'EC', 'ECP'])
            ->filter(fn ($row) => trim((string) $row->reference) !== '')
            ->groupBy(fn ($row) => $row->transaction_type . ':' . $row->reference) as $group) {
            if ($group->count() > 1) {
                $issues[] = ['source_ids' => $group->pluck('id')->values()->all(),
                    'reason' => 'duplicate_project_source_reference'];
            }
        }
        foreach ($sources as $source) {
            $legs = $bySource->get($source->id, collect());
            $ecFamily = in_array($source->transaction_type, ['EC', 'ECP'], true);
            $pendingBank = $source->transaction_type === 'ECP' &&
                in_array((int) $source->payment_type, [2, 3], true) &&
                (int) $source->passing_status !== 1;
            if ($legs->isEmpty() && !$pendingBank) {
                $issues[] = ['source_id' => $source->id, 'type' => $source->transaction_type,
                    'reason' => 'source_without_ledger'];
                continue;
            }
            if ($pendingBank && $legs->isNotEmpty()) {
                $issues[] = ['source_id' => $source->id,
                    'reason' => 'pending_or_rejected_ecp_has_ledger'];
            }
            foreach ($legs as $leg) {
                if ((string) $leg->type !== (string) $source->transaction_type ||
                    (int) $leg->account_project_id !== (int) $project ||
                    (string) $leg->reference !== (string) $source->reference) {
                    $issues[] = ['source_id' => $source->id, 'ledger_id' => $leg->id,
                        'reason' => 'source_ledger_identity_mismatch'];
                }
                if ($ecFamily && (
                    (int) $leg->type_id !== (int) $source->type_id ||
                    (int) $leg->voucher_number !== (int) $source->type_id ||
                    (int) $leg->is_active !== 1)) {
                    $issues[] = ['source_id' => $source->id, 'ledger_id' => $leg->id,
                        'reason' => 'ec_series_or_active_state_mismatch'];
                }
            }
            if ($ecFamily && !$pendingBank) {
                $incoming = $legs->sum(fn ($l) => (int) round((float) $l->amount_in * 100));
                $outgoing = $legs->sum(fn ($l) => (int) round((float) $l->amount_out * 100));
                if ($legs->count() !== 2 || $incoming !== $outgoing ||
                    $incoming !== (int) round((float) $source->amount_in * 100)) {
                    $issues[] = ['source_id' => $source->id, 'reason' => 'ec_or_ecp_legs_unbalanced'];
                }
            }
            if ($source->transaction_type === 'ECP') {
                if (!preg_match('/^EC_PAYMENT#([1-9][0-9]*)#[^#]+$/D', (string) $source->reference, $match)) {
                    $issues[] = ['source_id' => $source->id, 'reason' => 'ecp_invalid_source_reference'];
                } else {
                    $charge = $sources->first(fn ($row) =>
                        (int) $row->id === (int) $match[1] &&
                        $row->transaction_type === 'EC');
                    if (!$charge ||
                        (int) $charge->customer_id !== (int) $source->customer_id ||
                        (int) $charge->plot_id !== (int) $source->plot_id ||
                        (int) $charge->is_active !== 1) {
                        $issues[] = ['source_id' => $source->id, 'reason' => 'ecp_wrong_or_missing_charge'];
                    }
                }
            }
        }
        foreach ($ledgerRows as $leg) {
            if ($leg->customer_ledger_id === null && in_array($leg->type, ['EC', 'ECP'], true)) {
                $issues[] = ['ledger_id' => $leg->id, 'reason' => 'typed_ec_ledger_without_source_id'];
            }
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
