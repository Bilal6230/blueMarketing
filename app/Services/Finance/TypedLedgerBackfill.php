<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile only source-verified BD/BW/plot PR/PP posting batches into ledgers.
 * No new table: persist exact 1:1 ownership in finance_posting_lines.legacy_ledger_id
 * and in the existing ledger_source_attributions table.
 */
final class TypedLedgerBackfill
{
    private const SOURCE_MAP=[
        'BD'=>['bank_transfer','finance_bank_transfer_sources',null,'BD','bank_transfer',null],
        'BW'=>['bank_transfer','finance_bank_transfer_sources',null,'BW','bank_transfer',null],
        'PR'=>['plot_receipt','finance_plot_receipt_postings','finance_plot_receipts','PPR','plot_receipt','receipt_number'],
        'PP'=>['plot_purchase','finance_plot_purchase_postings','finance_plot_purchases','PP','plot_purchase','purchase_number'],
    ];

    private function cents(mixed $raw): int
    {
        $s=(string)$raw;
        if(!preg_match('/^(0|[1-9][0-9]{0,14})(?:\.([0-9]{1,2}))?$/D',$s,$m)){
            throw new \DomainException('LEDGER_BACKFILL_INVALID_MONEY');
        }
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    /** @return array<string,mixed> */
    public function inspect(int $projectId,int $batchId,bool $apply=false): array
    {
        if($projectId<1||$batchId<1)throw new \DomainException('LEDGER_BACKFILL_INVALID_ID');
        return DB::transaction(function()use($projectId,$batchId,$apply):array{
            // Serializes direct voucher writers, approvals, and this backfill
            // against the same project-level finance lock.
            $project=DB::table('projects')->where('id',$projectId)->lockForUpdate()->first();
            if(!$project)throw new \DomainException('LEDGER_BACKFILL_PROJECT_MISSING');
            $batch=DB::table('finance_posting_batches')->where('id',$batchId)
                ->where('project_id',$projectId)->lockForUpdate()->first();
            if(!$batch)throw new \DomainException('LEDGER_BACKFILL_BATCH_MISSING');
            $series=(string)$batch->voucher_series;
            $map=self::SOURCE_MAP[$series]??null;
            if(!$map||$batch->reversal_of_batch_id!==null||$batch->posted_at===null){
                return ['batch'=>$batchId,'series'=>$series,'status'=>'unsupported_or_reversal'];
            }
            [$sourceKind,$sourceLink,$sourceTable,$ledgerType,$attributionKind,$numberColumn]=$map;
            $claim=DB::table('finance_posting_source_claims')
                ->where('posting_batch_id',$batchId)->first();
            if(!$claim||$claim->source_kind!==$sourceKind){
                throw new \DomainException('LEDGER_BACKFILL_SOURCE_CLAIM_MISMATCH');
            }
            $link=DB::table($sourceLink)
                ->where('posting_batch_id',$batchId)->lockForUpdate()->first();
            if(!$link)throw new \DomainException('LEDGER_BACKFILL_SOURCE_LINK_MISSING');
            if($sourceTable===null){
                if($link->transfer_kind!==($series==='BD'?'deposit':'withdrawal')
                    ||(int)$link->project_id!==$projectId){
                    throw new \DomainException('LEDGER_BACKFILL_SOURCE_PROJECT_MISMATCH');
                }
                // Bank-transfer source is keyed by posting_batch_id, not id.
                // Its FK is the immutable identity shared with posting claims.
                $sourceId=(int)$link->posting_batch_id;
                $isSourceActive=!isset($link->is_active)||(int)$link->is_active===1;
                if(isset($link->deleted_at)&&$link->deleted_at!==null)$isSourceActive=false;
                $voucherReference=$series.'-'.($batch->voucher_number?:$batch->id);
                $sourceAmount=$this->cents($link->amount);
                if($sourceAmount<1)throw new \DomainException('LEDGER_BACKFILL_SOURCE_AMOUNT_INVALID');
            }else{
                $sourceId=(int)$link->source_id;
                $source=DB::table($sourceTable)->where('id',$sourceId)
                    ->where('project_id',$projectId)->lockForUpdate()->first();
                if(!$source)throw new \DomainException('LEDGER_BACKFILL_SOURCE_PROJECT_MISMATCH');
                $isSourceActive=!isset($source->is_active)||(int)$source->is_active===1;
                if(isset($source->deleted_at)&&$source->deleted_at!==null)$isSourceActive=false;
                $voucherReference=(string)$source->{$numberColumn};
                $sourceAmount=$this->cents($source->amount);
                $sourceDate=(string)$source->{$series==='PR'?'receipt_date':'purchase_date'};
                if($sourceAmount<1 || $sourceDate!==(string)$batch->effective_date){
                    throw new \DomainException('LEDGER_BACKFILL_SOURCE_AMOUNT_OR_DATE_MISMATCH');
                }
            }
            if($sourceId<1||$sourceId>2147483647||$voucherReference===''){
                throw new \DomainException('LEDGER_BACKFILL_SOURCE_IDENTITY_INVALID');
            }
            $lines=DB::table('finance_posting_lines')
                ->where('posting_batch_id',$batchId)->orderBy('id')
                ->lockForUpdate()->get();
            if($lines->count()<2)throw new \DomainException('LEDGER_BACKFILL_INCOMPLETE_POSTING');
            $linked=$lines->filter(fn($l)=>$l->legacy_ledger_id!==null);
            if($linked->count()>0){
                foreach($linked as $line){
                    $ledger=DB::table('ledgers as l')
                        ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                        ->where('a.project_id',$projectId)->where('l.id',$line->legacy_ledger_id)
                        ->first(['l.*']);
                    if(!$ledger||(string)$ledger->type!==$ledgerType
                        ||(int)$ledger->type_id!==$sourceId
                        ||(int)$ledger->project_head_subheads_id!==(int)$line->project_head_subhead_id
                        ||$this->cents($ledger->amount_in)!==$this->cents($line->debit)
                        ||$this->cents($ledger->amount_out)!==$this->cents($line->credit)
                        ||(int)$ledger->is_active!==(int)($line->is_active??1)){
                        throw new \DomainException('LEDGER_BACKFILL_EXISTING_LINK_MISMATCH');
                    }
                }
                if($linked->count()===$lines->count()){
                    return ['batch'=>$batchId,'series'=>$ledgerType,'status'=>'already_linked',
                        'lines'=>$lines->count()];
                }
            }
            // When a posted voucher is corrected, superseded typed legs stay
            // linked and new unlinked legs are added. Any unrelated old ledger
            // for this source must still block unsafe duplicate insertion.
            $linkedIds=$linked->pluck('legacy_ledger_id')->all();
            $existing=DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('a.project_id',$projectId)
                ->where('l.type',$ledgerType)->where('l.type_id',$sourceId)
                // Legacy cash PPR uses the same numeric type_id space as plot
                // receipts. Its customer_ledger_id is the authoritative FK;
                // never mistake that cash row for a plot-receipt posting.
                ->when($series==='PR',fn($q)=>$q->whereNull('l.customer_ledger_id'))
                ->when($linkedIds,fn($q)=>$q->whereNotIn('l.id',$linkedIds))
                ->lockForUpdate()->get(['l.*']);

            // Old PPR ledgers may already exist from the original payment
            // flow. Claim them only when each original row matches one typed
            // line exactly; never insert another debit/credit for that source.
            $claimedLegacy=[];
            $recovered=[];
            foreach($lines as $line){
                if($line->legacy_ledger_id!==null)continue;
                $matches=$existing->filter(fn($ledger)=>
                    !isset($claimedLegacy[(int)$ledger->id])
                    &&(int)$ledger->project_head_subheads_id===(int)$line->project_head_subhead_id
                    &&$this->cents($ledger->amount_in)===$this->cents($line->debit)
                    &&$this->cents($ledger->amount_out)===$this->cents($line->credit)
                    &&(int)$ledger->is_active===(int)($line->is_active??1))->values();
                if($matches->count()>1){
                    throw new \DomainException('LEDGER_BACKFILL_AMBIGUOUS_LEGACY_SOURCE');
                }
                if($matches->count()===1){
                    $ledger=$matches->first();
                    $claimedLegacy[(int)$ledger->id]=true;
                    $recovered[(int)$line->id]=(int)$ledger->id;
                }
            }
            if($existing->count()>0 && count($claimedLegacy)!==$existing->count()){
                throw new \DomainException('LEDGER_BACKFILL_POSSIBLE_DUPLICATE_SOURCE: batch='.
                    $batchId.' source='.$sourceId.' old_rows='.$existing->count().
                    ' exactly_matched='.count($claimedLegacy).
                    ' unmatched_old_ledger_ids='.$existing->filter(fn($row)=>!isset($claimedLegacy[(int)$row->id]))
                        ->pluck('id')->implode(',').
                    ' unlinked_line_ids='.$lines->filter(fn($row)=>$row->legacy_ledger_id===null)
                        ->pluck('id')->implode(','));
            }
            $debit=$credit=0;
            foreach($lines as $line){
                if(!DB::table('project_head_subheads')
                    ->where('id',$line->project_head_subhead_id)
                    ->where('project_id',$projectId)->exists()){
                    throw new \DomainException('LEDGER_BACKFILL_FOREIGN_ACCOUNT');
                }
                $d=$this->cents($line->debit);$c=$this->cents($line->credit);
                if(($d>0)===($c>0)||$d>9999999999||$c>9999999999){
                    throw new \DomainException('LEDGER_BACKFILL_INVALID_LEG');
                }
                $debit+=$d;$credit+=$c;
                if(!$isSourceActive && (int)($line->is_active??1)===1){
                    throw new \DomainException('LEDGER_BACKFILL_ARCHIVED_SOURCE_ACTIVE_LINE');
                }
            }
            // Historical same-source edits retain inactive superseded lines,
            // so only current active legs must balance.
            $active=$lines->filter(fn($line)=>(int)($line->is_active??1)===1);
            $activeDebit=$activeCredit=0;
            foreach($active as $line){
                $activeDebit+=$this->cents($line->debit);
                $activeCredit+=$this->cents($line->credit);
            }
            if($active->count()>0&&($active->count()<2||$activeDebit<1
                ||$activeDebit!==$activeCredit||$activeDebit!==$sourceAmount)){
                throw new \DomainException('LEDGER_BACKFILL_ACTIVE_LINES_UNBALANCED');
            }
            if($active->count()===0&&$isSourceActive){
                throw new \DomainException('LEDGER_BACKFILL_ACTIVE_SOURCE_NO_LINES');
            }
            $summary=['batch'=>$batchId,'series'=>$ledgerType,'source_kind'=>$sourceKind,
                'source_id'=>$sourceId,'lines'=>$lines->count(),
                'active_lines'=>$active->count(),'status'=>$apply?'linked':'ready'];
            if(!$apply)return $summary;
            foreach($lines as $line){
                if($line->legacy_ledger_id!==null)continue;
                if(isset($recovered[(int)$line->id])){
                    $ledgerId=$recovered[(int)$line->id];
                }else{
                $amountIn=(string)$line->debit;$amountOut=(string)$line->credit;
                $voucherNumber=$batch->voucher_number;
                $ledgerId=DB::table('ledgers')->insertGetId([
                    'voucher_number'=>$voucherNumber,
                    'type'=>$ledgerType,'type_id'=>$sourceId,
                    'project_head_subheads_id'=>(int)$line->project_head_subhead_id,
                    'reference'=>$voucherReference,
                    'amount_in'=>$amountIn,'amount_out'=>$amountOut,
                    'detail'=>(string)($line->description??''),
                    'create_by'=>$batch->posted_by,'date'=>$batch->effective_date,
                    'is_active'=>(int)($line->is_active??1),'status'=>'0',
                    'created_at'=>$line->created_at??now(),'updated_at'=>now(),
                ]);
                }
                DB::table('finance_posting_lines')->where('id',$line->id)
                    ->whereNull('legacy_ledger_id')->update([
                        'legacy_ledger_id'=>$ledgerId,'updated_at'=>now(),
                    ]);
                $attributed=DB::table('ledger_source_attributions')
                    ->where('ledger_id',$ledgerId)->first();
                if($attributed && ((int)$attributed->project_id!==$projectId
                    ||(string)$attributed->source_kind!==$attributionKind
                    ||(int)$attributed->source_id!==$sourceId)){
                    throw new \DomainException('LEDGER_BACKFILL_ATTRIBUTION_CONFLICT');
                }
                if(!$attributed)DB::table('ledger_source_attributions')->insert([
                    'ledger_id'=>$ledgerId,'project_id'=>$projectId,
                    'source_kind'=>$attributionKind,'source_id'=>$sourceId,
                    'evidence_kind'=>'verified_typed_backfill',
                    'evidence_reference'=>$sourceKind.':batch:'.$batchId.':line:'.$line->id,
                    'attributed_by'=>null,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            return $summary;
        },3);
    }
}
