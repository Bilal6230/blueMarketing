<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Ledger mirror for explicitly approved booking/labour/stock document batches.
 * This uses only existing posting/source metadata and account/project FKs.
 */
final class ApprovedDocumentLedgerLinker
{
    private const SOURCES=[
        'booking'=>['finance_posting_booking_sources','booking_voucher_id','booking_vouchers',null],
        'labour'=>['finance_posting_labour_sources','labour_ledger_id','labour_ledgers',null],
        'stock_entry'=>['finance_posting_stock_entry_sources','stock_entry_id','stock_entries','finance_stock_entry_project_approvals'],
        'stock_bill'=>['finance_posting_stock_bill_sources','stock_bill_id','stock_bills','finance_stock_bill_project_approvals'],
    ];

    private function cents(mixed $raw): int
    {
        if(!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',
            trim((string)$raw),$m))throw new \DomainException('DOCUMENT_LEDGER_INVALID_AMOUNT');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    public function sync(int $projectId,int $batchId): int
    {
        return DB::transaction(function()use($projectId,$batchId):int{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $batch=DB::table('finance_posting_batches')->where('id',$batchId)
                ->where('project_id',$projectId)->whereNull('reversal_of_batch_id')
                ->lockForUpdate()->first();
            if(!$batch)throw new \DomainException('DOCUMENT_LEDGER_BATCH_INVALID');
            $claim=DB::table('finance_posting_source_claims')
                ->where('posting_batch_id',$batchId)->first();
            $kind=(string)($claim->source_kind??'');
            if(!isset(self::SOURCES[$kind]))throw new \DomainException('DOCUMENT_LEDGER_KIND_INVALID');
            [$linkTable,$fk,$sourceTable,$approvalTable]=self::SOURCES[$kind];
            $sourceId=DB::table($linkTable)->where('posting_batch_id',$batchId)->value($fk);
            if(!$sourceId||(int)$sourceId>2147483647)
                throw new \DomainException('DOCUMENT_LEDGER_SOURCE_LINK_INVALID');
            $source=DB::table($sourceTable)->where('id',$sourceId)->first();
            if(!$source)throw new \DomainException('DOCUMENT_LEDGER_SOURCE_MISSING');
            if($approvalTable){
                if(!DB::table($approvalTable)->where($fk,$sourceId)
                    ->where('project_id',$projectId)->exists()){
                    throw new \DomainException('DOCUMENT_LEDGER_PROJECT_APPROVAL_MISSING');
                }
            }elseif(!isset($source->project_id)||(int)$source->project_id!==$projectId){
                throw new \DomainException('DOCUMENT_LEDGER_FOREIGN_SOURCE');
            }
            $series=(string)$batch->voucher_series;
            $lines=DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
                ->orderBy('id')->lockForUpdate()->get();
            if($lines->count()<2)throw new \DomainException('DOCUMENT_LEDGER_LINES_MISSING');
            $debit=$credit=0;
            foreach($lines as $line){
                if(!DB::table('project_head_subheads')
                    ->where('project_id',$projectId)
                    ->where('id',$line->project_head_subhead_id)->exists())
                    throw new \DomainException('DOCUMENT_LEDGER_FOREIGN_ACCOUNT');
                $d=$this->cents($line->debit);$c=$this->cents($line->credit);
                if(($d>0)===($c>0)||$d>9999999999||$c>9999999999)
                    throw new \DomainException('DOCUMENT_LEDGER_SINGLE_SIDED_LINE_REQUIRED');
                if((int)($line->is_active??1)===1){$debit+=$d;$credit+=$c;}
            }
            if($debit!==$credit)throw new \DomainException('DOCUMENT_LEDGER_NOT_BALANCED');
            if(in_array($kind,['booking','labour'],true)){
                $amount=(string)$source->amount;
                if($debit!==$this->cents($amount))
                    throw new \DomainException('DOCUMENT_LEDGER_SOURCE_AMOUNT_MISMATCH');
            }
            $existing=DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('a.project_id',$projectId)->where('l.type',$series)
                ->where('l.type_id',$sourceId)->get(['l.*']);
            $claimed=[];$created=0;
            foreach($lines as $line){
                $matched=$line->legacy_ledger_id!==null
                    ?$existing->first(fn($l)=>(int)$l->id===(int)$line->legacy_ledger_id)
                    :$existing->filter(fn($l)=>
                        !isset($claimed[(int)$l->id])
                        &&(int)$l->project_head_subheads_id===(int)$line->project_head_subhead_id
                        &&$this->cents($l->amount_in)===$this->cents($line->debit)
                        &&$this->cents($l->amount_out)===$this->cents($line->credit)
                        &&(int)$l->is_active===(int)($line->is_active??1))->first();
                if($matched){
                    if(isset($claimed[(int)$matched->id]))
                        throw new \DomainException('DOCUMENT_LEDGER_DUPLICATE_LINK');
                    $ledgerId=(int)$matched->id;
                }else{
                    if($existing->count()>0)
                        throw new \DomainException('DOCUMENT_LEDGER_AMBIGUOUS_EXISTING_SOURCE');
                    $ledgerId=DB::table('ledgers')->insertGetId([
                        'type'=>$series,'type_id'=>(int)$sourceId,
                        'voucher_number'=>$batch->voucher_number,
                        'project_head_subheads_id'=>$line->project_head_subhead_id,
                        'reference'=>$series.'-'.($batch->voucher_number??$batchId),
                        'amount_in'=>$line->debit,'amount_out'=>$line->credit,
                        'detail'=>$line->description??'',
                        'date'=>$batch->effective_date,
                        'is_active'=>(int)($line->is_active??1),
                        'status'=>'0','create_by'=>$batch->posted_by,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $created++;
                }
                $claimed[$ledgerId]=true;
                if($line->legacy_ledger_id!==null
                    &&(int)$line->legacy_ledger_id!==$ledgerId)
                    throw new \DomainException('DOCUMENT_LEDGER_EXISTING_LINK_INVALID');
                if($line->legacy_ledger_id===null){
                    $count=DB::table('finance_posting_lines')->where('id',$line->id)
                        ->whereNull('legacy_ledger_id')->update([
                            'legacy_ledger_id'=>$ledgerId,'updated_at'=>now(),
                        ]);
                    if($count!==1)throw new \DomainException('DOCUMENT_LEDGER_CONCURRENT_LINK');
                }
                if(!DB::table('ledger_source_attributions')->where('ledger_id',$ledgerId)->exists()){
                    DB::table('ledger_source_attributions')->insert([
                        'ledger_id'=>$ledgerId,'project_id'=>$projectId,
                        'source_kind'=>$kind,'source_id'=>(int)$sourceId,
                        'evidence_kind'=>'verified_approved_document',
                        'evidence_reference'=>'batch:'.$batchId.':line:'.$line->id,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }
            return $created;
        },3);
    }
}
