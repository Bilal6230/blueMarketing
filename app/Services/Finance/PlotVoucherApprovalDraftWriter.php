<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pending plot vouchers are immutable payloads, not prematurely posted sources.
 * The full PlotEntryWriter revalidates current booking, account and plot inventory
 * custody during approval, and all writes remain in the approval transaction.
 */
final class PlotVoucherApprovalDraftWriter
{
    public function submit(int $projectId,string $type,string $key,array $data,int $actorId): array
    {
        FinanceConnectionGuard::assertAllowed();
        if (!Schema::hasTable('finance_plot_approval_drafts')) {
            throw new \DomainException('PLOT_APPROVAL_DRAFT_MIGRATION_REQUIRED');
        }
        if (!in_array($type,['PPR','PP'],true) || $projectId<1 || $actorId<1
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key)) {
            throw new \DomainException('PLOT_APPROVAL_DRAFT_INVALID');
        }
        $number=trim((string)($data[$type==='PPR'?'receipt_number':'purchase_number']??''));
        $date=(string)($data['date']??'');
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if($number===''||mb_strlen($number)>64||!$day||$day->format('Y-m-d')!==$date){
            throw new \DomainException('PLOT_APPROVAL_DRAFT_INVALID');
        }
        $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $fingerprint=hash('sha256',$type.'|'.$projectId.'|'.$json);
        return DB::transaction(function()use($projectId,$type,$key,$data,$actorId,
            $number,$date,$json,$fingerprint):array{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $prior=DB::table('finance_plot_approval_drafts')
                ->where('project_id',$projectId)->where('request_key',$key)
                ->lockForUpdate()->first();
            if($prior){
                if($prior->voucher_type!==$type
                    ||!hash_equals($prior->payload_sha256,$fingerprint)){
                    throw new \DomainException('PLOT_APPROVAL_DRAFT_REQUEST_KEY_CONFLICT');
                }
                return ['approval_draft_id'=>(int)$prior->id,'number'=>(string)$prior->source_number,
                    'status'=>(string)$prior->status,'pending'=>$prior->status==='pending',
                    'replayed'=>true];
            }
            // Reserve a series-specific number before saving the pending draft.
            // The same number is carried into the eventual approved posting.
            $expected=$type.'-'.app(ProjectVoucherAllocator::class)->next($projectId,$type);
            if($number!==$expected) {
                throw new \DomainException('PLOT_VOUCHER_NUMBER_CHANGED_REFRESH_FORM');
            }
            $source=$type==='PPR'?'finance_plot_receipts':'finance_plot_purchases';
            $sourceNumber=$type==='PPR'?'receipt_number':'purchase_number';
            if(DB::table($source)->where('project_id',$projectId)
                ->where($sourceNumber,$number)->exists()
                ||DB::table('finance_plot_approval_drafts')
                    ->where('project_id',$projectId)->where('voucher_type',$type)
                    ->where('source_number',$number)->exists()){
                throw new \DomainException('PLOT_APPROVAL_DRAFT_NUMBER_EXISTS');
            }
            if($type==='PPR'){
                $paymentType=(int)($data['payment_type']??1);
                $method=(string)($data['passed_source_method']??'');
                $intakeId=(int)($data['passed_source_id']??0);
                if(!in_array($paymentType,[1,2,3],true)
                    || ($paymentType===1 && ($method!=='' || $intakeId>0))
                    || ($paymentType!==1 &&
                        ($method!==($paymentType===2?'online':'cheque') || $intakeId<1))){
                    throw new \DomainException('PPR_TYPED_BANK_SOURCE_REQUIRED');
                }
                if($paymentType!==1){
                    if(!DB::table('project_bank_accounts')->where('project_id',$projectId)
                        ->where('is_active',1)
                        ->where('account_mapping_id',(int)($data['cash_account_id']??0))
                        ->exists()){
                        throw new \DomainException('PPR_TYPED_BANK_DEBIT_ACCOUNT_REQUIRED');
                    }
                    app(VerifiedBankInstrument::class)->inspectLocked(
                        $projectId,'PPR',$method,$intakeId,(string)$data['amount']);
                }
                $customerId=(int)($data['customer_id']??0);
                if(!DB::table('leads')->where('id',$customerId)
                    ->where('project_id',$projectId)->exists()
                    ||!DB::table('project_head_subheads')->where('project_id',$projectId)
                        ->where('id',(int)($data['cash_account_id']??0))->exists()){
                    throw new \DomainException('PLOT_APPROVAL_DRAFT_CUSTOMER_ACCOUNT_INVALID');
                }
                foreach($data['allocations']??[] as $allocation){
                    if(!DB::table('bookings')->where('project_id',$projectId)
                        ->where('customer_id',$customerId)
                        ->where('id',(int)($allocation['booking_id']??0))
                        ->where('plot_id',(int)($allocation['plot_id']??0))->exists()){
                        throw new \DomainException('PLOT_APPROVAL_DRAFT_BOOKING_INVALID');
                    }
                }
            }else{
                if(!DB::table('project_head_subheads')
                    ->where('project_id',$projectId)
                    ->where('id',(int)($data['seller_account_id']??0))->exists()){
                    throw new \DomainException('PLOT_APPROVAL_DRAFT_SELLER_INVALID');
                }
                foreach($data['items']??[] as $item){
                    if(!DB::table('project_head_subheads')
                        ->where('project_id',$projectId)
                        ->where('id',(int)($item['inventory_account_id']??0))->exists()){
                        throw new \DomainException('PLOT_APPROVAL_DRAFT_INVENTORY_ACCOUNT_INVALID');
                    }
                }
            }
            $id=DB::table('finance_plot_approval_drafts')->insertGetId([
                'project_id'=>$projectId,'voucher_type'=>$type,
                'source_number'=>$number,'effective_date'=>$date,
                'request_key'=>$key,'payload_sha256'=>$fingerprint,
                'payload_json'=>$json,'status'=>'pending','created_by'=>$actorId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            if(!app(DailyVoucherApprovalWorkflow::class)->submitIfRequired(
                $projectId,$type,'finance_plot_approval_drafts',(int)$id,$actorId)){
                throw new \DomainException('PLOT_APPROVAL_POLICY_CHANGED');
            }
            if($type==='PPR' && (int)($data['payment_type']??1)!==1){
                app(TypedPlotReceiptBankCustody::class)->reserveDraft(
                    $projectId,(int)$id,(string)$data['passed_source_method'],
                    (int)$data['passed_source_id'],(string)$data['amount'],$actorId);
            }
            return ['approval_draft_id'=>(int)$id,'number'=>$number,
                'status'=>'pending','pending'=>true,'replayed'=>false];
        },3);
    }

    public function finalizeApproved(int $projectId,int $draftId,string $type,int $actorId): array
    {
        // Called only inside an authorized DailyVoucherApprovalWorkflow decision.
        // The approval row is already locked there; lock draft next.
        $draft=DB::table('finance_plot_approval_drafts')
            ->where('id',$draftId)->where('project_id',$projectId)
            ->where('voucher_type',$type)->where('status','pending')
            ->lockForUpdate()->first();
        if(!$draft)throw new \DomainException('PLOT_APPROVAL_DRAFT_NOT_PENDING');
        $data=json_decode((string)$draft->payload_json,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($data)
            ||hash('sha256',$type.'|'.$projectId.'|'.$draft->payload_json)!==$draft->payload_sha256
            ||(string)($data[$type==='PPR'?'receipt_number':'purchase_number']??'')!==$draft->source_number
            ||(string)($data['date']??'')!==(string)$draft->effective_date){
            throw new \DomainException('PLOT_APPROVAL_DRAFT_TAMPERED');
        }
        $writer=app(PlotEntryWriter::class);
        $result=$type==='PPR'
            ?$writer->receive($projectId,(int)$data['customer_id'],
                (string)$draft->request_key,$data,$actorId,$draftId)
            :$writer->purchase($projectId,(string)$draft->request_key,$data,$actorId,$draftId);
        DB::table('finance_plot_approval_drafts')
            ->where('id',$draftId)->where('project_id',$projectId)
            ->update(['status'=>'approved','posted_source_id'=>$result['source_id'],
                'posting_batch_id'=>$result['posting_batch_id'],'updated_at'=>now()]);
        return $result;
    }

    public function reject(int $projectId,int $draftId,string $type,?int $actorId=null): void
    {
        $updated=DB::table('finance_plot_approval_drafts')
            ->where('id',$draftId)->where('project_id',$projectId)
            ->where('voucher_type',$type)->where('status','pending')
            ->whereNull('posted_source_id')->whereNull('posting_batch_id')
            ->update(['status'=>'rejected','updated_at'=>now()]);
        if($updated!==1)throw new \DomainException('PLOT_APPROVAL_DRAFT_NOT_PENDING');
        if($type==='PPR' && Schema::hasTable('finance_bank_voucher_allocations')
            && DB::table('finance_bank_voucher_allocations')
                ->where('project_id',$projectId)
                ->where('voucher_type',TypedPlotReceiptBankCustody::DRAFT)
                ->where('voucher_id',$draftId)->exists()){
            if(!$actorId)throw new \DomainException('Typed PPR bank rejection actor is required.');
            app(TypedPlotReceiptBankCustody::class)->release(
                $projectId,TypedPlotReceiptBankCustody::DRAFT,$draftId,
                $actorId,'Rejected typed multi-plot receipt',true);
        }
    }
}
