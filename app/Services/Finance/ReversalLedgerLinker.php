<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/** One reversal batch owns its exact inverse ledger entries. */
final class ReversalLedgerLinker
{
    private function money(mixed $raw): string
    {
        if(!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',trim((string)$raw),$m))
            throw new \DomainException('REVERSAL_LEDGER_INVALID_AMOUNT');
        return $m[1].'.'.str_pad($m[2]??'',2,'0');
    }

    public function sync(int $projectId,int $reversalBatchId): int
    {
        return DB::transaction(function()use($projectId,$reversalBatchId):int{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $batch=DB::table('finance_posting_batches')->where('project_id',$projectId)
                ->where('id',$reversalBatchId)->where('voucher_series','REV')
                ->whereNotNull('reversal_of_batch_id')->lockForUpdate()->first();
            if(!$batch)throw new \DomainException('REVERSAL_LEDGER_SOURCE_INVALID');
            $original=DB::table('finance_posting_batches')
                ->where('project_id',$projectId)
                ->where('id',$batch->reversal_of_batch_id)
                ->whereNull('reversal_of_batch_id')->first();
            if(!$original)throw new \DomainException('REVERSAL_LEDGER_ORIGINAL_INVALID');
            $previous=DB::table('finance_posting_lines')
                ->where('posting_batch_id',$original->id)
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('finance_posting_lines','is_active'),
                    fn($q)=>$q->where('is_active',1))->get();
            $rows=DB::table('finance_posting_lines')
                ->where('posting_batch_id',$reversalBatchId)->orderBy('id')
                ->lockForUpdate()->get();
            if($previous->count()<2||$rows->count()!==$previous->count())
                throw new \DomainException('REVERSAL_LEDGER_MISSING_LEGS');
            $canonical=static fn(int $account,string $d,string $c)=>
                $account.'|'.$d.'|'.$c;
            $expected=[];$actual=[];
            foreach($previous as $line){
                $expected[]=$canonical((int)$line->project_head_subhead_id,
                    $this->money($line->credit),$this->money($line->debit));
            }
            foreach($rows as $line){
                if(!DB::table('project_head_subheads')
                    ->where('project_id',$projectId)
                    ->where('id',$line->project_head_subhead_id)->exists())
                    throw new \DomainException('REVERSAL_LEDGER_FOREIGN_ACCOUNT');
                $actual[]=$canonical((int)$line->project_head_subhead_id,
                    $this->money($line->debit),$this->money($line->credit));
            }
            sort($expected);sort($actual);
            if($expected!==$actual)throw new \DomainException('REVERSAL_LEDGER_NOT_INVERSE');
            $existing=DB::table('ledgers as l')
                ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                ->where('a.project_id',$projectId)->where('l.type','REV')
                ->where('l.type_id',$reversalBatchId)->get(['l.*']);
            if($existing->count()>0&&$existing->count()!==$rows->count())
                throw new \DomainException('REVERSAL_LEDGER_PARTIAL_SOURCE');
            $new=0;$claimed=[];
            foreach($rows as $line){
                $ledger=$line->legacy_ledger_id!==null
                    ?$existing->first(fn($r)=>(int)$r->id===(int)$line->legacy_ledger_id)
                    :$existing->filter(fn($r)=>
                        !isset($claimed[(int)$r->id])
                        &&(int)$r->project_head_subheads_id===(int)$line->project_head_subhead_id
                        &&$this->money($r->amount_in)===$this->money($line->debit)
                        &&$this->money($r->amount_out)===$this->money($line->credit))->first();
                if($ledger){
                    if(isset($claimed[(int)$ledger->id]))
                        throw new \DomainException('REVERSAL_LEDGER_DUPLICATE_LINK');
                    $ledgerId=(int)$ledger->id;
                }else{
                    if($existing->count()>0)throw new \DomainException('REVERSAL_LEDGER_AMBIGUOUS');
                    $ledgerId=DB::table('ledgers')->insertGetId([
                        'type'=>'REV','type_id'=>$reversalBatchId,
                        'voucher_number'=>$batch->voucher_number,
                        'project_head_subheads_id'=>$line->project_head_subhead_id,
                        'reference'=>'REV-'.$original->voucher_series.'-'.$original->id,
                        'amount_in'=>$line->debit,'amount_out'=>$line->credit,
                        'detail'=>$line->description??'Reversal',
                        'date'=>$batch->effective_date,'is_active'=>(int)($line->is_active??1),
                        'status'=>'0','create_by'=>$batch->posted_by,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $new++;
                }
                $claimed[$ledgerId]=true;
                if($line->legacy_ledger_id===null){
                    $changed=DB::table('finance_posting_lines')
                        ->where('id',$line->id)->whereNull('legacy_ledger_id')
                        ->update(['legacy_ledger_id'=>$ledgerId,'updated_at'=>now()]);
                    if($changed!==1)throw new \DomainException('REVERSAL_LEDGER_LINK_CONFLICT');
                }
                if(!DB::table('ledger_source_attributions')->where('ledger_id',$ledgerId)->exists()){
                    DB::table('ledger_source_attributions')->insert([
                        'ledger_id'=>$ledgerId,'project_id'=>$projectId,
                        'source_kind'=>'posting_reversal','source_id'=>$reversalBatchId,
                        'evidence_kind'=>'verified_reversal_ledger',
                        'evidence_reference'=>'reversal:'.$original->id.':line:'.$line->id,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }
            return $new;
        },3);
    }
}
