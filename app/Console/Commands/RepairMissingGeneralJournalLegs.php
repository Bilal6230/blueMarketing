<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insert-only JV recovery. Never deletes or disables historical JV/stock/labour rows.
 * Explicit database opt-in required; default is dry-run.
 */
final class RepairMissingGeneralJournalLegs extends Command
{
    protected $signature = 'finance:repair-missing-jv-legs
        {--database= : Required explicit database name, e.g. bluenew}
        {--apply : Commit verified insert-only changes}
        {--id= : Optional single JV header ID}';
    protected $description = 'Preview or restore strictly unposted general JV details without modifying existing ledger rows';

    public function handle(): int
    {
        $name = (string) $this->option('database');
        $actual = (string) DB::connection()->getDatabaseName();
        if ($name === '' || $name !== $actual || !preg_match('/^[A-Za-z0-9_]+$/D', $name)) {
            $this->error('Explicit --database must equal current connection database; current database: '.$actual);
            return self::INVALID;
        }
        $selected = $this->option('id');
        if ($selected !== null && filter_var($selected, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('--id must be a positive integer');
            return self::INVALID;
        }
        $hasDetailFk = Schema::hasColumn('ledgers', 'journal_voucher_detail_id');
        $apply = (bool) $this->option('apply');
        $summary = ['database' => $name, 'mode' => $apply ? 'apply' : 'dry_run',
            'eligible_headers' => 0, 'insertable_legs' => 0, 'blocked_headers' => [],
            'inserted_legs' => 0, 'debit_cents' => 0, 'credit_cents' => 0];

        // One transaction across all headers: on any error DB rolls back all work.
        DB::transaction(function () use ($selected, $apply, $hasDetailFk, &$summary) {
            $headers = DB::table('journal_vouchers')
                ->where('type', 'JV')->whereNull('deleted_at')
                ->when($selected !== null, fn ($q) => $q->where('id', (int) $selected))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($headers as $h) {
                $details = DB::table('journal_voucher_details as d')
                    ->leftJoin('project_head_subheads as a', 'a.id', '=', 'd.account_id')
                    ->where('d.journal_voucher_id', $h->id)->whereNull('d.deleted_at')
                    ->orderBy('d.id')->get(['d.*', 'a.project_id as account_project']);
                $reasons = [];
                if ($details->count() < 2) $reasons[] = 'insufficient_details';
                $debit = 0; $credit = 0; $missing = [];
                $detailSignatures = [];
                foreach ($details as $d) {
                    $in = (int) round((float) $d->debit * 100);
                    $out = (int) round((float) $d->credit * 100);
                    $debit += $in; $credit += $out;
                    $sig = $d->account_id . ':' . $in . ':' . $out;
                    if (isset($detailSignatures[$sig])) $reasons[] = 'duplicate_detail_signature_ambiguous';
                    $detailSignatures[$sig] = true;
                    if ($d->account_project === null || (int) $d->account_project !== (int) $h->project_id)
                        $reasons[] = 'account_project_mismatch';
                    if (($in <= 0 && $out <= 0) || ($in > 0 && $out > 0))
                        $reasons[] = 'invalid_detail';
                    // A complete accounting leg must agree on owner, number, account and monetary effect.
                    // type_id alone is polymorphic and cannot prove JV ownership.
                    $matches = DB::table('ledgers as l')
                        ->where('l.type', 'JV')->where('l.type_id', $h->id)
                        ->where('l.voucher_number', $h->voucher_number)
                        ->where('l.project_head_subheads_id', $d->account_id)
                        ->where('l.amount_in', $d->debit)->where('l.amount_out', $d->credit)
                        ->count();
                    if ($matches > 1) $reasons[] = 'duplicate_existing_exact_leg';
                    if ($matches === 0) $missing[] = $d;
                    if ($hasDetailFk && DB::table('ledgers')->where('journal_voucher_detail_id', $d->id)->exists()
                        && $matches === 0) $reasons[] = 'detail_provenance_collision';
                }
                if ($debit <= 0 || $debit !== $credit ||
                    (int) round((float) $h->total_debit * 100) !== $debit ||
                    (int) round((float) $h->total_credit * 100) !== $credit)
                    $reasons[] = 'header_detail_imbalance';
                // A header whose source identity is already used by unrelated JV rows cannot
                // safely be reconstructed until those rows are proven stock/labour.
                $otherOwners = DB::table('ledgers as l')
                    ->join('project_head_subheads as a', 'a.id','=','l.project_head_subheads_id')
                    ->where('a.project_id', $h->project_id)
                    ->where('l.type','JV')->where('l.type_id',$h->id)
                    ->whereNotIn('l.id', function ($q) use ($h) {
                        $q->from('ledgers as x')
                          ->join('journal_voucher_details as d', function ($join) use ($h) {
                              $join->on('d.account_id','=','x.project_head_subheads_id')
                                  ->on('d.debit','=','x.amount_in')
                                  ->on('d.credit','=','x.amount_out')
                                  ->where('d.journal_voucher_id','=',$h->id)
                                  ->whereNull('d.deleted_at');
                          })
                          ->where('x.type','JV')->where('x.type_id',$h->id)
                          ->where('x.voucher_number',$h->voucher_number)->select('x.id');
                    })->exists();
                if ($otherOwners) $reasons[] = 'polymorphic_id_conflict';
                if ($reasons) {
                    if ($missing) $summary['blocked_headers'][] = ['jv_id' => (int) $h->id,
                        'reasons' => array_values(array_unique($reasons))];
                    continue;
                }
                if (!$missing) continue;
                $summary['eligible_headers']++;
                $summary['insertable_legs'] += count($missing);
                foreach ($missing as $d) {
                    $summary['debit_cents'] += (int) round((float) $d->debit * 100);
                    $summary['credit_cents'] += (int) round((float) $d->credit * 100);
                    if (!$apply) continue;
                    $values = [
                        'type' => 'JV', 'type_id' => $h->id,
                        'voucher_number' => $h->voucher_number,
                        'project_head_subheads_id' => $d->account_id,
                        'reference' => $h->reference,
                        'amount_in' => $d->debit, 'amount_out' => $d->credit,
                        'detail' => $d->description ?? '',
                        'create_by' => $h->created_by,
                        'is_active' => 1, 'status' => '0', 'date' => $h->date,
                        'created_at' => $d->created_at ?: $h->created_at,
                        'updated_at' => $d->updated_at ?: $h->updated_at,
                    ];
                    if ($hasDetailFk) $values['journal_voucher_detail_id'] = $d->id;
                    DB::table('ledgers')->insert($values);
                    // Verify the inserted financial effect before transaction commit.
                    // Any discrepancy rolls back the entire batch.
                    $posted = DB::table('ledgers')
                        ->where('type', 'JV')->where('type_id', $h->id)
                        ->where('voucher_number', $h->voucher_number)
                        ->where('project_head_subheads_id', $d->account_id)
                        ->where('amount_in', $d->debit)->where('amount_out', $d->credit);
                    if ($hasDetailFk) {
                        $posted->where('journal_voucher_detail_id', $d->id);
                    }
                    if ($posted->count() !== 1) {
                        throw new \RuntimeException('JV ledger verification failed: rolled back journal '.$h->id.' detail '.$d->id);
                    }
                    $summary['inserted_legs']++;
                }
            }
        }, 3);
        $summary['transaction_committed'] = $apply;
        $summary['existing_ledger_rows_deleted_or_deactivated'] = 0;
        $this->line(json_encode($summary, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
