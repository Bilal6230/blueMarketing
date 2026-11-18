<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional non-financial edit. All ledger, posting, allocation and plot
 * inventory values remain untouched; every change has append-only evidence.
 */
final class PlotVoucherNarrationEditor
{
    public function update(int $projectId, string $kind, int $sourceId, string $narration, ?int $actorId): array
    {
        FinanceConnectionGuard::assertAllowed();
        $table=match($kind) {
            'plot_receipt'=>'finance_plot_receipts',
            'plot_purchase'=>'finance_plot_purchases',
            default=>throw new \DomainException('Unsupported plot voucher type.'),
        };
        $narration=trim($narration);
        if ($projectId<1 || $sourceId<1 || !$actorId || $actorId<1 || $narration==='' || mb_strlen($narration)>2000) {
            throw new \DomainException('Invalid voucher narration.');
        }
        if (!Schema::hasTable('daily_voucher_edit_snapshots')) {
            throw new \RuntimeException('Shared voucher edit history is not installed.');
        }

        return DB::transaction(function () use ($projectId,$kind,$sourceId,$narration,$actorId,$table) {
            $sourceQuery=DB::table($table)->where('project_id',$projectId)
                ->where('id',$sourceId)->lockForUpdate();
            if (Schema::hasColumn($table,'is_active')) $sourceQuery->where('is_active',1);
            if (Schema::hasColumn($table,'deleted_at')) $sourceQuery->whereNull('deleted_at');
            $source=$sourceQuery->first();
            if (!$source) throw new \DomainException('Active plot voucher not found in selected project.');

            $postingTable=$kind==='plot_receipt' ? 'finance_plot_receipt_postings' : 'finance_plot_purchase_postings';
            $link=DB::table($postingTable.' as link')
                ->join('finance_posting_batches as batch','batch.id','=','link.posting_batch_id')
                ->where('link.source_id',$sourceId)->where('link.source_kind',$kind)
                ->where('batch.project_id',$projectId)
                ->first(['batch.id as batch_id']);
            if (!$link) throw new \DomainException('Plot voucher posting linkage is missing or belongs to another project.');
            if (DB::table('finance_posting_batches')
                ->where('reversal_of_batch_id',$link->batch_id)->exists()) {
                throw new \DomainException('Already reversed voucher cannot be edited.');
            }
            $before=(string)$source->narration;
            if ($before===$narration) return ['changed'=>false,'source_id'=>$sourceId,'narration'=>$narration];
            $updated=DB::table($table)->where('id',$sourceId)->where('project_id',$projectId)
                ->update(['narration'=>$narration,'updated_at'=>now()]);
            if ($updated!==1) throw new \DomainException('Concurrent plot voucher modification.');
            // Shared voucher edit history; source kind is embedded in both
            // JSON snapshots to avoid collisions across source-table IDs.
            DB::table('daily_voucher_edit_snapshots')->insert([
                'project_id'=>$projectId,'source_id'=>$sourceId,'approval_id'=>null,
                'status'=>'plot_note',
                'before_snapshot'=>json_encode(['source_kind'=>$kind,'source_id'=>$sourceId,
                    'narration'=>$before],JSON_THROW_ON_ERROR),
                'proposed_snapshot'=>json_encode(['source_kind'=>$kind,'source_id'=>$sourceId,
                    'narration'=>$narration],JSON_THROW_ON_ERROR),
                'reason'=>'Plot voucher narration edited',
                'submitted_by'=>$actorId,'decided_by'=>$actorId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return ['changed'=>true,'source_id'=>$sourceId,'narration'=>$narration];
        },3);
    }
}
