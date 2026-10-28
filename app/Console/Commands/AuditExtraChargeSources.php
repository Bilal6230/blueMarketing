<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditExtraChargeSources extends Command
{
    protected $signature = 'finance:audit-ec-sources {project : Positive project ID}';
    protected $description = 'Read-only audit of typed EC source ownership, number reuse, balance and orphan ledger legs';

    public function handle(): int
    {
        $projectId = filter_var($this->argument('project'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($projectId === false) {
            $this->error('Positive project ID required.');
            return self::INVALID;
        }

        $sources = DB::table('customer_ledger')
            ->where('project_id', $projectId)
            ->where('transaction_type', 'EC')
            ->orderBy('id')
            ->get();
        $sourceIds = $sources->pluck('id')->all();
        $legs = DB::table('ledgers as l')
            ->leftJoin('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->where('l.type', 'EC')
            ->where(function ($q) use ($projectId, $sourceIds) {
                $q->where('a.project_id', $projectId);
                if ($sourceIds) {
                    $q->orWhereIn('l.customer_ledger_id', $sourceIds);
                }
            })
            ->select('l.id', 'l.customer_ledger_id', 'l.type', 'l.reference',
                'l.voucher_number', 'l.amount_in', 'l.amount_out',
                'l.project_head_subheads_id', 'a.project_id as ledger_project_id')
            ->orderBy('l.id')->get();

        $issues = [];
        $legsBySource = $legs->groupBy('customer_ledger_id');
        foreach ($sources as $source) {
            $rows = $legsBySource->get($source->id, collect());
            $flags = [];
            if ($rows->count() !== 2) {
                $flags[] = 'leg_count_not_two';
            }
            $debit = 0;
            $credit = 0;
            foreach ($rows as $leg) {
                if ((int) $leg->ledger_project_id !== (int) $projectId ||
                    (string) $leg->reference !== (string) $source->reference ||
                    (int) $leg->voucher_number !== (int) $source->type_id) {
                    $flags[] = 'ledger_source_identity_mismatch';
                }
                $debit += (int) round((float) $leg->amount_out * 100);
                $credit += (int) round((float) $leg->amount_in * 100);
            }
            if ($debit !== $credit || $debit <= 0 ||
                $debit !== (int) round((float) $source->amount_in * 100)) {
                $flags[] = 'amount_not_balanced';
            }
            if ($flags) {
                $issues[] = ['source_id' => $source->id, 'flags' => array_values(array_unique($flags))];
            }
        }
        foreach ($legs as $leg) {
            if (!$sources->contains('id', $leg->customer_ledger_id)) {
                $issues[] = ['ledger_id' => $leg->id, 'flags' => ['orphan_or_foreign_project_source']];
            }
        }
        $duplicates = $sources->groupBy('type_id')->filter(fn ($rows) =>
            (int) $rows->first()->type_id > 0 && $rows->count() > 1);
        foreach ($duplicates as $number => $rows) {
            $issues[] = ['voucher_number' => $number, 'source_ids' => $rows->pluck('id')->all(),
                'flags' => ['duplicate_source_voucher_number']];
        }

        $this->line(json_encode([
            'project_id' => (int) $projectId,
            'source_count' => $sources->count(),
            'ledger_count' => $legs->count(),
            'issues' => $issues,
            'read_only' => true,
        ], JSON_THROW_ON_ERROR));
        return $issues ? self::FAILURE : self::SUCCESS;
    }
}
