<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only batch triage. A same voucher number is not proof of ownership:
 * stock/labour use polymorphic JV type IDs too.
 */
final class InspectAllMissingJournalPostings extends Command
{
    protected $signature = 'finance:inspect-all-missing-jvs {--project= : Optional project filter}';
    protected $description = 'Read-only summary of missing general JV ledger sets and voucher number collisions';

    public function handle(): int
    {
        $project = $this->option('project');
        if ($project !== null && filter_var($project, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('Project must be a positive integer');
            return self::INVALID;
        }
        $headers = DB::table('journal_vouchers as j')
            ->where('j.type', 'JV')->whereNull('j.deleted_at')
            ->when($project !== null, fn ($q) => $q->where('j.project_id', (int) $project))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('ledgers as l')
                    ->where('l.type', 'JV')->whereColumn('l.type_id', 'j.id');
            })->get(['j.id', 'j.project_id', 'j.voucher_number', 'j.status']);
        $results = [];
        foreach ($headers as $header) {
            $details = DB::table('journal_voucher_details as d')
                ->leftJoin('project_head_subheads as a', 'a.id', '=', 'd.account_id')
                ->where('d.journal_voucher_id', $header->id)->whereNull('d.deleted_at')
                ->get(['d.id', 'd.debit', 'd.credit', 'a.project_id as account_project']);
            $debit = $details->sum(fn ($d) => (int) round((float) $d->debit * 100));
            $credit = $details->sum(fn ($d) => (int) round((float) $d->credit * 100));
            $accountValid = $details->every(fn ($d) => $d->account_project !== null &&
                (int) $d->account_project === (int) $header->project_id);
            $collisions = DB::table('ledgers as l')
                ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
                ->where('a.project_id', $header->project_id)->where('l.type', 'JV')
                ->where('l.voucher_number', $header->voucher_number)
                ->get(['l.id', 'l.type_id']);
            $results[] = [
                'jv_id' => (int) $header->id,
                'project_id' => (int) $header->project_id,
                'voucher_number' => $header->voucher_number,
                'status' => $header->status,
                'detail_count' => $details->count(),
                'debit_cents' => $debit,
                'credit_cents' => $credit,
                'balanced' => $debit > 0 && $debit === $credit,
                'account_project_valid' => $accountValid,
                'same_series_number_collision' => $collisions->isNotEmpty(),
                'colliding_ledger_ids' => $collisions->pluck('id')->all(),
                'manual_review_required' => true,
            ];
        }
        $records = collect($results);
        $this->line(json_encode([
            'total_missing_header_candidates' => $records->count(),
            'detail_count' => $records->sum('detail_count'),
            'total_debit_cents' => $records->sum('debit_cents'),
            'total_credit_cents' => $records->sum('credit_cents'),
            'balanced_account_valid_without_number_collision' =>
                $records->filter(fn ($r) => $r['balanced'] && $r['account_project_valid'] &&
                    !$r['same_series_number_collision'] && $r['detail_count'] >= 2)->count(),
            'with_same_series_number_collision' => $records->where('same_series_number_collision', true)->count(),
            'cases' => $results,
            'automatically_authorized_to_repair' => false,
            'read_only' => true,
        ], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
