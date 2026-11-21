<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reuse authoritative legacy detail foreign keys for typed JV/SV/CV rows.
 * Never infer the owned voucher from an overloaded numeric type_id alone.
 */
final class VoucherDetailLedgerLinker
{
    public function sync(int $projectId,int $batchId): int
    {
        return DB::transaction(function()use($projectId,$batchId):int{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $batch=DB::table('finance_posting_batches')
                ->where('project_id',$projectId)->where('id',$batchId)
                ->whereNull('reversal_of_batch_id')->lockForUpdate()->first();
            if(!$batch||!in_array($batch->voucher_series,['JV','SV','CV'],true))
                throw new \DomainException('DETAIL_LEDGER_UNSUPPORTED_BATCH');
            $cv=$batch->voucher_series==='CV';
            $type=$cv?'CV':(string)$batch->voucher_series;
            $headTable=$cv?'commision_vouchers':'journal_vouchers';
            $detailTable=$cv?'commision_voucher_details':'journal_voucher_details';
            $detailFk=$cv?'commision_voucher_detail_id':'journal_voucher_detail_id';
            $headerFk=$cv?'commision_voucher_id':'journal_voucher_id';
            $linkTable=$cv?'finance_posting_commission_sources':'finance_posting_journal_sources';
            $lineLink=$cv?'finance_commission_line_sources':'finance_journal_line_sources';
            $kind=$cv?'commission':'journal';
            $sourceColumn=$headerFk;
            $claim=DB::table('finance_posting_source_claims')
                ->where('posting_batch_id',$batchId)->where('source_kind',$kind)->exists();
            $sourceId=DB::table($linkTable)->where('posting_batch_id',$batchId)
                ->value($sourceColumn);
            if(!$claim||!$sourceId)throw new \DomainException('DETAIL_LEDGER_SOURCE_CLAIM_INVALID');
            $head=DB::table($headTable)->where('id',$sourceId)
                ->where('project_id',$projectId)->lockForUpdate()->first();
            if(!$head||(!$cv&&(string)$head->type!==$type)
                ||(isset($head->deleted_at)&&$head->deleted_at!==null))
                throw new \DomainException('DETAIL_LEDGER_HEADER_INVALID');

            $lines=DB::table('finance_posting_lines as line')
                ->join($lineLink.' as source','source.posting_line_id','=','line.id')
                ->join($detailTable.' as detail','detail.id','=','source.'.$detailFk)
                ->where('line.posting_batch_id',$batchId)
                ->where('detail.'.$headerFk,$sourceId)
                ->select(['line.*','detail.id as voucher_detail_id',
                    'detail.account_id as detail_account','detail.debit as detail_debit',
                    'detail.credit as detail_credit','detail.deleted_at as detail_deleted_at'])
                ->orderBy('line.id')->lockForUpdate()->get();
            $total=DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)->count();
            if($total<2||$lines->count()!==$total)
                throw new \DomainException('DETAIL_LEDGER_LINE_IDENTITY_INCOMPLETE');
            $created=0;
            foreach($lines as $line){
                if($line->detail_deleted_at!==null
                    ||(int)$line->detail_account!==(int)$line->project_head_subhead_id
                    ||bccomp((string)$line->detail_debit,(string)$line->debit,2)!==0
                    ||bccomp((string)$line->detail_credit,(string)$line->credit,2)!==0
                    ||!DB::table('project_head_subheads')
                        ->where('project_id',$projectId)
                        ->where('id',$line->project_head_subhead_id)->exists()){
                    throw new \DomainException('DETAIL_LEDGER_AMOUNT_ACCOUNT_MISMATCH');
                }
                $ledger=DB::table('ledgers')
                    ->where($detailFk,$line->voucher_detail_id)->lockForUpdate()->first();
                if($ledger){
                    if((string)$ledger->type!==$type||(int)$ledger->type_id!==(int)$sourceId
                        ||(int)$ledger->project_head_subheads_id!==(int)$line->project_head_subhead_id
                        ||bccomp((string)$ledger->amount_in,(string)$line->debit,2)!==0
                        ||bccomp((string)$ledger->amount_out,(string)$line->credit,2)!==0
                        ||(int)$ledger->is_active!==(int)($line->is_active??1)){
                        throw new \DomainException('DETAIL_LEDGER_EXISTING_LEDGER_MISMATCH');
                    }
                    $ledgerId=(int)$ledger->id;
                }else{
                    // The authentic detail FK is unique; no duplicate row can
                    // be inserted on a retry or a concurrent second posting.
                    $ledgerId=DB::table('ledgers')->insertGetId([
                        $detailFk=>(int)$line->voucher_detail_id,
                        'type'=>$type,'type_id'=>(int)$sourceId,
                        'voucher_number'=>$head->voucher_number,
                        'project_head_subheads_id'=>(int)$line->project_head_subhead_id,
                        'reference'=>$head->reference??null,'date'=>$head->date,
                        'amount_in'=>$line->debit,'amount_out'=>$line->credit,
                        'detail'=>$line->description,'is_active'=>(int)($line->is_active??1),
                        'status'=>'0','create_by'=>$batch->posted_by,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $created++;
                }
                if($line->legacy_ledger_id!==null&&(int)$line->legacy_ledger_id!==$ledgerId)
                    throw new \DomainException('DETAIL_LEDGER_LINK_CONFLICT');
                if($line->legacy_ledger_id===null){
                    $changed=DB::table('finance_posting_lines')
                        ->where('id',$line->id)->whereNull('legacy_ledger_id')
                        ->update(['legacy_ledger_id'=>$ledgerId,'updated_at'=>now()]);
                    if($changed!==1)throw new \DomainException('DETAIL_LEDGER_LINK_CONFLICT');
                }
                if(!DB::table('ledger_source_attributions')->where('ledger_id',$ledgerId)->exists()){
                    DB::table('ledger_source_attributions')->insert([
                        'ledger_id'=>$ledgerId,'project_id'=>$projectId,
                        'source_kind'=>$cv?'commission_voucher':'journal_voucher',
                        'source_id'=>(int)$sourceId,
                        'evidence_kind'=>'verified_detail_ledger_link',
                        'evidence_reference'=>$kind.':'.$batchId.':'.$line->voucher_detail_id,
                        'attributed_by'=>null,'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }
            return $created;
        },3);
    }
}
