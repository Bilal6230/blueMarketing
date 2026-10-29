<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only evidence packet for one explicitly selected journal voucher.
 * Never assumes that shared JV polymorphic type_id establishes ownership.
 */
final class InspectMissingJournalPosting extends Command
{
    protected $signature = 'finance:inspect-missing-jv {id : Journal voucher header ID}';
    protected $description = 'Read-only source/detail/ledger evidence and recovery eligibility for one missing JV';

    public function handle(): int
    {
        $id = filter_var($this->argument('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->error('Positive journal voucher ID required');
            return self::INVALID;
        }
        $header = DB::table('journal_vouchers')->where('id', $id)->first();
        if (!$header) {
            $this->error('Journal voucher not found');
            return self::FAILURE;
        }
        $details = DB::table('journal_voucher_details as d')
            ->leftJoin('project_head_subheads as a', 'a.id', '=', 'd.account_id')
            ->where('d.journal_voucher_id', $id)->whereNull('d.deleted_at')
            ->orderBy('d.id')
            ->get(['d.id', 'd.account_id', 'd.debit', 'd.credit',
                'a.project_id as account_project_id']);
        $potentialLegs = DB::table('ledgers')->where('type', 'JV')->where('type_id', $id)
            ->get(['id', 'project_head_subheads_id', 'amount_in', 'amount_out',
                'voucher_number', 'date', 'reference']);
        $debit = $details->sum(fn ($r) => (int) round((float) $r->debit * 100));
        $credit = $details->sum(fn ($r) => (int) round((float) $r->credit * 100));
        $accountsValid = $details->every(fn ($r) =>
            $r->account_project_id !== null && (int) $r->account_project_id === (int) $header->project_id);
        $validLines = $details->every(fn ($r) =>
            ((float) $r->debit > 0 && (float) $r->credit == 0) ||
            ((float) $r->credit > 0 && (float) $r->debit == 0));
        $potentialCollision = DB::table('ledgers as l')
            ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
            ->where('l.type','JV')->where('a.project_id',$header->project_id)
            ->where('l.voucher_number',$header->voucher_number)
            ->count();
        $collidingLegs = DB::table('ledgers as l')
            ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->where('l.type', 'JV')->where('a.project_id', $header->project_id)
            ->where('l.voucher_number', $header->voucher_number)
            ->get(['l.id', 'l.type_id', 'l.project_head_subheads_id', 'l.amount_in', 'l.amount_out']);
        $candidate = (string) $header->type === 'JV' && $header->deleted_at === null &&
            $details->count() >= 2 && $debit > 0 && $debit === $credit &&
            $validLines && $accountsValid && $potentialLegs->isEmpty() && $potentialCollision === 0;
        $this->line(json_encode([
            'jv_id' => (int) $id,
            'project_id' => $header->project_id,
            'voucher_number' => $header->voucher_number,
            'header_status' => $header->status,
            'deleted' => $header->deleted_at !== null,
            'detail_count' => $details->count(),
            'debit_cents' => $debit, 'credit_cents' => $credit,
            'accounts_valid' => $accountsValid, 'lines_valid' => $validLines,
            'polymorphic_jv_legs_with_same_type_id' => $potentialLegs->count(),
            'same_project_voucher_number_ledger_legs' => $potentialCollision,
            'same_project_jv_number_collision_legs' => $collidingLegs->toArray(),
            'candidate_for_manual_recovery_review' => $candidate,
            'automatically_safe_to_post' => false,
            'details' => $details->map(fn ($r) => [
                'detail_id' => $r->id, 'account_id' => $r->account_id,
                'debit' => $r->debit, 'credit' => $r->credit,
            ])->all(),
            'read_only' => true,
        ], JSON_THROW_ON_ERROR));
        return $candidate ? self::SUCCESS : self::FAILURE;
    }
}
