<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Aggregate-only all-project collision inventory; never writes finance rows. */
final class AuditAllLedgerSourceUniqueness extends Command
{
    protected $signature = 'finance:audit-all-source-uniqueness';
    protected $description = 'Read-only all-project source voucher/reference collisions and NULL/blank index blockers';

    public function handle(): int
    {
        $families = ['CR', 'CP', 'PPR', 'EC', 'ECP'];
        $base = DB::table('customer_ledger')->whereIn('transaction_type', $families);
        $numberConflicts = (clone $base)->select('project_id','transaction_type','type_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('project_id','transaction_type','type_id')->havingRaw('COUNT(*) > 1')
            ->orderBy('project_id')->limit(100)->get();
        $referenceConflicts = (clone $base)->select('project_id','transaction_type','reference')
            ->selectRaw('COUNT(*) AS total')
            ->whereNotNull('reference')->where('reference','!=','')
            ->groupBy('project_id','transaction_type','reference')->havingRaw('COUNT(*) > 1')
            ->orderBy('project_id')->limit(100)->get();
        $incomplete = (clone $base)->where(function ($q) {
            $q->whereNull('project_id')->orWhereNull('transaction_type')->orWhereNull('type_id')
                ->orWhere('type_id','<=',0)->orWhereNull('reference')->orWhere('reference','');
        })->count();
        $numberGroups = DB::query()->fromSub(
            (clone $base)->select('project_id','transaction_type','type_id')
                ->groupBy('project_id','transaction_type','type_id')->havingRaw('COUNT(*) > 1'),
            'dupes')->count();
        $referenceGroups = DB::query()->fromSub(
            (clone $base)->select('project_id','transaction_type','reference')
                ->whereNotNull('reference')->where('reference','!=','')
                ->groupBy('project_id','transaction_type','reference')->havingRaw('COUNT(*) > 1'),
            'dupes')->count();
        $ok = $numberGroups === 0 && $referenceGroups === 0 && $incomplete === 0;
        $this->line(json_encode([
            'finance_source_count'=>(clone $base)->count(),
            'duplicate_number_group_count'=>$numberGroups,
            'duplicate_reference_group_count'=>$referenceGroups,
            'incomplete_identity_count'=>$incomplete,
            'number_examples'=>$numberConflicts->toArray(),
            'reference_examples'=>$referenceConflicts->toArray(),
            'candidate_unique_index_ready'=>$ok,
            'index_applied'=>false,'read_only'=>true,
        ],JSON_THROW_ON_ERROR));
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
