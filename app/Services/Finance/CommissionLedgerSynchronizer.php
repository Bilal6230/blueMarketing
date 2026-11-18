<?php

namespace App\Services\Finance;

use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commission's legacy ledger projection of its exact source details.
 *
 * This is NOT a second source voucher. All CV ledger legs must carry the
 * existing RESTRICT foreign key commision_voucher_detail_id. Drafts are
 * inactive; posted approved vouchers become active. All writes take place
 * inside the caller's locked voucher transaction.
 */
final class CommissionLedgerSynchronizer
{
    private function schema(): void
    {
        if (!Schema::hasColumn('ledgers','commision_voucher_detail_id')) {
            throw new \DomainException('Commission ledger detail foreign key migration is required.');
        }
    }

    private function money(mixed $value): string
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D',(string)$value,$m)) {
            throw new \DomainException('Commission ledger amount is invalid.');
        }
        return $m[1].'.'.str_pad($m[2]??'',2,'0');
    }

    private function context(int $projectId, int $voucherId): array
    {
        $this->schema();
        $header=DB::table('commision_vouchers')->where('id',$voucherId)
            ->where('project_id',$projectId)->whereNull('deleted_at')->lockForUpdate()->first();
        if (!$header) throw new \DomainException('Commission source is not available in the selected project.');
        $details=DB::table('commision_voucher_details')
            ->where('commision_voucher_id',$voucherId)->whereNull('deleted_at')
            ->orderBy('id')->lockForUpdate()->get();
        if ($details->count()<2) throw new \DomainException('Commission voucher requires two active details.');

        $all=DB::table('ledgers')->where('type','CV')->where('type_id',$voucherId)
            ->orderBy('id')->lockForUpdate()->get();
        $linked=[];
        foreach ($all as $leg) {
            $detailId=(int)($leg->commision_voucher_detail_id??0);
            if (!$detailId) {
                // A polymorphic CV/type_id match alone proves no ownership.
                throw new \DomainException('Unlinked legacy Commission ledger exists; manual reconciliation required.');
            }
            if (isset($linked[$detailId])) {
                throw new \DomainException('Duplicate Commission ledger detail foreign key.');
            }
            $sourceDetail=DB::table('commision_voucher_details')->where('id',$detailId)
                ->where('commision_voucher_id',$voucherId)->first();
            if (!$sourceDetail) {
                throw new \DomainException('Commission ledger references another voucher detail.');
            }
            $this->assertAmounts($leg,$sourceDetail,$projectId);
            $linked[$detailId]=$leg;
        }
        foreach ($details as $detail) {
            if (!DB::table('project_head_subheads')->where('id',$detail->account_id)
                ->where('project_id',$projectId)->exists()) {
                throw new \DomainException('Commission detail account belongs to another project.');
            }
        }
        return [$header,$details,$linked];
    }

    private function assertAmounts(object $leg,object $detail,int $projectId): void
    {
        if ((int)$leg->project_head_subheads_id!==(int)$detail->account_id
            || $this->money($leg->amount_in)!==$this->money($detail->debit)
            || $this->money($leg->amount_out)!==$this->money($detail->credit)
            || !DB::table('project_head_subheads')->where('id',$leg->project_head_subheads_id)
                ->where('project_id',$projectId)->exists()) {
            throw new \DomainException('Commission detail-to-ledger account or amount mismatch.');
        }
    }

    /**
     * Creates only missing exact-FK rows, never guesses by amount or index.
     * Safe to call repeatedly for older drafts created before this feature.
     */
    public function ensureDraft(int $projectId,int $voucherId,?int $actorId=null): void
    {
        [$header,$details,$linked]=$this->context($projectId,$voucherId);
        if ((string)$header->status!=='draft') {
            throw new \DomainException('Only Commission drafts may create inactive ledger legs.');
        }
        foreach ($linked as $leg) {
            if ((int)$leg->is_active!==0) {
                throw new \DomainException('Unposted Commission draft already has active ledger rows.');
            }
        }
        foreach ($details as $detail) {
            if (isset($linked[(int)$detail->id])) continue;
            Ledger::create([
                'type'=>'CV','type_id'=>$voucherId,
                'commision_voucher_detail_id'=>(int)$detail->id,
                'project_head_subheads_id'=>(int)$detail->account_id,
                'voucher_number'=>$header->voucher_number,
                'reference'=>$header->reference,
                'amount_in'=>$this->money($detail->debit),
                'amount_out'=>$this->money($detail->credit),
                'detail'=>$detail->description??'',
                'date'=>$header->date,'status'=>'0','is_active'=>0,
                'create_by'=>$actorId,
            ]);
        }
    }

    /**
     * Once the typed batch is posted, activate only verified current detail
     * ledgers. Previously edited/archived detail rows always remain inactive.
     */
    public function activateApproved(int $projectId,int $voucherId,?int $actorId=null): void
    {
        [$header,$details,$linked]=$this->context($projectId,$voucherId);
        if ((string)$header->status!=='approved') {
            throw new \DomainException('Commission voucher is not approved for ledger activation.');
        }
        $posted=DB::table('finance_posting_commission_sources as source')
            ->join('finance_posting_batches as batch','batch.id','=','source.posting_batch_id')
            ->where('source.commision_voucher_id',$voucherId)->where('batch.project_id',$projectId)
            ->whereNull('batch.reversal_of_batch_id')->whereNotNull('batch.posted_at')
            ->exists();
        if (!$posted) throw new \DomainException('Commission ledger cannot activate without posted financial batch.');
        $ids=$details->pluck('id')->map(fn($id)=>(int)$id)->all();
        foreach ($linked as $detailId=>$leg) {
            if (!in_array((int)$detailId,$ids,true) && (int)$leg->is_active!==0) {
                throw new \DomainException('Archived Commission detail still has active ledger posting.');
            }
        }
        foreach ($details as $detail) {
            $leg=$linked[(int)$detail->id]??null;
            if (!$leg) {
                $newId=Ledger::create([
                    'type'=>'CV','type_id'=>$voucherId,
                    'commision_voucher_detail_id'=>(int)$detail->id,
                    'project_head_subheads_id'=>(int)$detail->account_id,
                    'voucher_number'=>$header->voucher_number,'reference'=>$header->reference,
                    'amount_in'=>$this->money($detail->debit),'amount_out'=>$this->money($detail->credit),
                    'detail'=>$detail->description??'','date'=>$header->date,
                    'status'=>'0','is_active'=>0,'create_by'=>$actorId,
                ])->id;
            } else {
                $newId=$leg->id;
            }
            DB::table('ledgers')->where('id',$newId)->where('type','CV')
                ->where('type_id',$voucherId)
                ->where('commision_voucher_detail_id',$detail->id)
                ->where('is_active',0)->update(['is_active'=>1,'updated_at'=>now()]);
        }
    }

    public function assertDraftCanReplace(int $projectId,int $voucherId): void
    {
        [$header,,$linked]=$this->context($projectId,$voucherId);
        if ((string)$header->status!=='draft') {
            throw new \DomainException('Posted Commission cannot be draft-edited.');
        }
        foreach ($linked as $leg) {
            if ((int)$leg->is_active!==0) {
                throw new \DomainException('Commission draft has unexpected active ledger rows.');
            }
        }
    }

    public function deactivate(int $projectId,int $voucherId): void
    {
        [$header,,$linked]=$this->context($projectId,$voucherId);
        foreach ($linked as $leg) {
            if ((int)$leg->is_active===1) {
                DB::table('ledgers')->where('id',$leg->id)->where('type','CV')
                    ->where('type_id',$voucherId)
                    ->where('commision_voucher_detail_id',$leg->commision_voucher_detail_id)
                    ->where('is_active',1)->update(['is_active'=>0,'updated_at'=>now()]);
            }
        }
    }
}
