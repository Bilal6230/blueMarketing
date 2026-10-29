<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only whole-batch JV evidence for accounting reconciliation.
 * A ledger voucher number alone NEVER establishes a journal's owner.
 */
final class BuildJournalRecoveryEvidence extends Command
{
    protected $signature = 'finance:jv-recovery-evidence {--project= : Optional positive project ID}';
    protected $description = 'Read-only missing-JV detail, account impacts and competing ledger provenance';

    public function handle(): int
    {
        $project = $this->option('project');
        if ($project !== null && filter_var($project, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('Positive project ID required');
            return self::INVALID;
        }
        $headers = DB::table('journal_vouchers as j')
            ->where('j.type', 'JV')->whereNull('j.deleted_at')
            ->when($project !== null, fn ($q) => $q->where('j.project_id', (int) $project))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('ledgers as l')
                    ->where('l.type', 'JV')->whereColumn('l.type_id', 'j.id');
            })->orderBy('j.id')->get(['j.id','j.project_id','j.voucher_number','j.date','j.status','j.total_debit','j.total_credit']);
        $cases = [];
        $impact = [];
        foreach ($headers as $h) {
            $details = DB::table('journal_voucher_details as d')
                ->leftJoin('project_head_subheads as a','a.id','=','d.account_id')
                ->where('d.journal_voucher_id',$h->id)->whereNull('d.deleted_at')
                ->orderBy('d.id')->get(['d.id','d.account_id','d.debit','d.credit','a.project_id as account_project']);
            $debit = 0;
            $credit = 0;
            $accountsValid = true;
            $linesValid = true;
            foreach ($details as $d) {
                $in = (int) round((float) $d->debit * 100);
                $out = (int) round((float) $d->credit * 100);
                $debit += $in;
                $credit += $out;
                if ($d->account_project === null || (int) $d->account_project !== (int) $h->project_id) {
                    $accountsValid = false;
                }
                if (($in <= 0 && $out <= 0) || ($in > 0 && $out > 0)) {
                    $linesValid = false;
                }
                $key = $h->project_id . ':' . $d->account_id;
                if (!isset($impact[$key])) {
                    $impact[$key] = ['project_id' => (int) $h->project_id, 'account_id' => $d->account_id,
                        'missing_debit_cents' => 0, 'missing_credit_cents' => 0];
                }
                $impact[$key]['missing_debit_cents'] += $in;
                $impact[$key]['missing_credit_cents'] += $out;
            }
            $competing = DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('a.project_id', $h->project_id)->where('l.type','JV')
                ->where('l.voucher_number',$h->voucher_number)
                ->orderBy('l.id')->get(['l.id','l.type_id','l.project_head_subheads_id',
                    'l.amount_in','l.amount_out','l.reference','l.date']);
            $cases[] = [
                'jv_id' => (int) $h->id, 'project_id' => (int) $h->project_id,
                'voucher_number' => $h->voucher_number, 'status' => $h->status,
                'date' => $h->date, 'header_total_debit' => $h->total_debit,
                'header_total_credit' => $h->total_credit,
                'details' => $details->map(fn ($d) => [
                    'id' => $d->id, 'account_id' => $d->account_id,
                    'debit' => $d->debit, 'credit' => $d->credit,
                ])->all(),
                'detail_debit_cents' => $debit, 'detail_credit_cents' => $credit,
                'account_project_valid' => $accountsValid, 'lines_valid' => $linesValid,
                'balanced' => $debit > 0 && $debit === $credit,
                'competing_same_number_jv_legs' => $competing->toArray(),
                'historical_provenance_resolved' => false,
                'safe_to_auto_repair' => false,
            ];
        }
        $this->line(json_encode([
            'missing_jv_count' => count($cases),
            'total_missing_debit_cents' => array_sum(array_column($cases, 'detail_debit_cents')),
            'total_missing_credit_cents' => array_sum(array_column($cases, 'detail_credit_cents')),
            'account_balance_impacts' => array_values($impact),
            'cases' => $cases, 'read_only' => true,
            'repair_performed' => false,
        ], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
