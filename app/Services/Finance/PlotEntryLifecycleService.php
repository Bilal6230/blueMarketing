<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Reverses immutable M6 sources without changing their original rows. */
final class PlotEntryLifecycleService
{
    public function reverse(int $projectId, string $kind, int $sourceId, string $requestKey,
        string $effectiveDate, string $reason, ?int $actorId): int
    {
        FinanceConnectionGuard::assertAllowed();
        $links = [
            'plot_receipt' => 'finance_plot_receipt_postings',
            'plot_purchase' => 'finance_plot_purchase_postings',
        ];
        if (!isset($links[$kind]) || $projectId < 1 || $sourceId < 1) {
            throw new \DomainException('V2_PLOT_VOID_INVALID_SOURCE');
        }

        return DB::transaction(function () use ($projectId, $kind, $sourceId, $requestKey,
            $effectiveDate, $reason, $actorId, $links): int {
            $sourceTable = $kind === 'plot_receipt' ? 'finance_plot_receipts' : 'finance_plot_purchases';
            $query = DB::table($sourceTable)->where('project_id', $projectId)
                ->where('id', $sourceId)->lockForUpdate();
            if (Schema::hasColumn($sourceTable, 'is_active')) $query->where('is_active', 1);
            if (Schema::hasColumn($sourceTable, 'deleted_at')) $query->whereNull('deleted_at');
            $source = $query->first();
            if (!$source) throw new \DomainException('PLOT_SOURCE_NOT_ACTIVE');

            $link = DB::table($links[$kind])->where('source_id', $sourceId)->first();
            if (!$link || $link->source_kind !== $kind) throw new \DomainException('V2_PLOT_VOID_POSTING_MISSING');

            $purchasePlots = [];
            if ($kind === 'plot_purchase') {
                $purchasePlots = DB::table('finance_plot_purchase_items as item')
                    ->join('plots as plot', 'plot.id', '=', 'item.legacy_plot_id')
                    ->where('item.purchase_id', $sourceId)
                    ->where('plot.project_id', $projectId)
                    ->lockForUpdate()->get(['plot.id', 'plot.sold', 'plot.is_active']);
                if ($purchasePlots->count() !== DB::table('finance_plot_purchase_items')->where('purchase_id', $sourceId)->count()
                    || $purchasePlots->contains(fn ($plot) => (int) $plot->sold !== 0
                        || DB::table('bookings')->where('plot_id', $plot->id)->exists())) {
                    throw new \DomainException('V2_PLOT_PURCHASE_INVENTORY_IN_USE');
                }
            }

            $batchId = (new PostingReversalWriter())->reverse(
                $projectId, (int) $link->posting_batch_id, $requestKey,
                $effectiveDate, $reason, $actorId
            );
            foreach ($purchasePlots as $plot) {
                DB::table('plots')->where('id', $plot->id)->where('project_id', $projectId)
                    ->where('sold', 0)->update(['is_active' => 0, 'updated_at' => now()]);
            }
            return $batchId;
        }, 3);
    }

    /**
     * Ordinary user-facing Delete: logically archives the original source and
     * neutralizes its posted balance in the same transaction. The compensating
     * posting is an accounting implementation detail; no Reverse permission is
     * required for a user granted the independent Delete action.
     */
    public function archive(int $projectId,string $kind,int $sourceId,string $requestKey,
        string $effectiveDate,string $reason,?int $actorId): int
    {
        $sourceTable=match($kind) {
            'plot_receipt'=>'finance_plot_receipts',
            'plot_purchase'=>'finance_plot_purchases',
            default=>throw new \DomainException('Unsupported plot voucher.'),
        };
        if (!Schema::hasColumn($sourceTable,'is_active') || !Schema::hasColumn($sourceTable,'deleted_at')) {
            throw new \DomainException('Voucher lifecycle columns are not installed.');
        }
        return DB::transaction(function()use($projectId,$kind,$sourceId,$requestKey,$effectiveDate,$reason,$actorId,$sourceTable){
            $source=DB::table($sourceTable)->where('project_id',$projectId)
                ->where('id',$sourceId)->where('is_active',1)->whereNull('deleted_at')
                ->lockForUpdate()->first();
            if (!$source) throw new \DomainException('Voucher already deleted or unavailable.');
            // A future-dated source can only be offset on or after its
            // original posting effective date.
            $linkTable=$kind==='plot_receipt'?'finance_plot_receipt_postings':'finance_plot_purchase_postings';
            $originalDate=DB::table($linkTable.' as link')
                ->join('finance_posting_batches as batch','batch.id','=','link.posting_batch_id')
                ->where('link.source_id',$sourceId)->where('batch.project_id',$projectId)
                ->value('batch.effective_date');
            if (!$originalDate) throw new \DomainException('Source posting missing.');
            $effectiveDate=max($effectiveDate,substr((string)$originalDate,0,10));
            // reverse() verifies exact source posting and unsold/unbooked
            // purchased plots before creating any compensating batch.
            $batch=$this->reverse($projectId,$kind,$sourceId,$requestKey,$effectiveDate,$reason,$actorId);
            $affected=DB::table($sourceTable)->where('id',$sourceId)
                ->where('project_id',$projectId)->where('is_active',1)->whereNull('deleted_at')
                ->update(['is_active'=>0,'deleted_at'=>now(),'updated_at'=>now()]);
            if ($affected!==1) throw new \DomainException('Concurrent voucher archive rejected.');
            return $batch;
        },3);
    }
}
