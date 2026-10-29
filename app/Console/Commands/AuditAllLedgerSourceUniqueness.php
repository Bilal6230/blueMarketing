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
        // Distinguish legacy empty/zero-number rows from positive-number
        // collisions that block a real voucher sequence UNIQUE index.
        $positive = (clone $base)->whereNotNull('project_id')
            ->whereNotNull('type_id')->where('type_id', '>', 0);
        $positiveDuplicateGroups = DB::query()->fromSub(
            (clone $positive)->select('project_id', 'transaction_type', 'type_id')
                ->groupBy('project_id', 'transaction_type', 'type_id')
                ->havingRaw('COUNT(*) > 1'), 'positive_duplicates')->count();
        $positiveExamples = (clone $positive)
            ->select('project_id', 'transaction_type', 'type_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('project_id', 'transaction_type', 'type_id')
            ->havingRaw('COUNT(*) > 1')->orderBy('project_id')
            ->limit(20)->get();
        $byType = (clone $base)->select('transaction_type')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN type_id IS NULL OR type_id <= 0 THEN 1 ELSE 0 END) AS missing_or_zero_number')
            ->selectRaw("SUM(CASE WHEN reference IS NULL OR reference = '' THEN 1 ELSE 0 END) AS missing_reference")
            ->groupBy('transaction_type')->orderBy('transaction_type')->get();
        // A reference may be a reused receipt/slip identifier, so this does
        // not itself establish a duplicate accounting voucher.
        // Blank historical references are not inherently invalid voucher
        // identities: legacy CR/CP commonly never stored a slip reference.
        $numberIdentityMissing = (clone $base)->where(function ($q) {
            $q->whereNull('project_id')->orWhereNull('type_id')->orWhere('type_id', '<=', 0);
        })->count();
        $missingReferences = (clone $base)->where(function ($q) {
            $q->whereNull('reference')->orWhere('reference', '');
        })->count();
        $numberKeyReady = $positiveDuplicateGroups === 0 && $numberIdentityMissing === 0;
        // References are business slip values and not eligible for blanket UNIQUE.
        // Report collision distribution without changing any historical row.
        // A reference is not necessarily a unique receipt identity.
        $collisionDistribution = (clone $positive)
            ->select('project_id', 'transaction_type')
            ->selectRaw('COUNT(*) AS colliding_groups')
            ->fromSub(
                (clone $positive)->select('project_id', 'transaction_type', 'type_id')
                    ->groupBy('project_id', 'transaction_type', 'type_id')
                    ->havingRaw('COUNT(*) > 1'), 'collisions'
            )->groupBy('project_id', 'transaction_type')
            ->orderBy('project_id')->orderBy('transaction_type')->get();
        $ok = $numberGroups === 0 && $referenceGroups === 0 && $incomplete === 0;
        $this->line(json_encode([
            'finance_source_count'=>(clone $base)->count(),
            'duplicate_number_group_count'=>$numberGroups,
            'duplicate_reference_group_count'=>$referenceGroups,
            'incomplete_identity_count'=>$incomplete,
            'missing_number_or_project_count'=>$numberIdentityMissing,
            'blank_reference_count'=>$missingReferences,
            'positive_number_unique_index_ready'=>$numberKeyReady,
            'reference_unique_index_recommended'=>false,
            'positive_number_duplicate_group_count'=>$positiveDuplicateGroups,
            'positive_number_examples'=>$positiveExamples->toArray(),
            'positive_number_collision_distribution'=>$collisionDistribution->toArray(),
            'by_type'=>$byType->toArray(),
            'number_examples'=>$numberConflicts->take(10)->values()->toArray(),
            'reference_examples'=>$referenceConflicts->take(10)->values()->toArray(),
            'reference_may_be_nonunique_business_slip'=>true,
            'candidate_unique_index_ready'=>$ok,
            'index_applied'=>false,'read_only'=>true,
        ],JSON_THROW_ON_ERROR));
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
