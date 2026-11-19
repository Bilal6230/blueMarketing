<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct a posted JV/SV in its existing finance batch. Changes archive old
 * source details and posting legs, retain their FK evidence and insert only
 * revised active legs. No customer-visible REV batch is issued.
 */
final class JournalApprovedVoucherEditor
{
    private function cents(mixed $value): int
    {
        $v=(string)$value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D',$v,$m)) {
            throw new \DomainException('Journal amount is invalid.');
        }
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    private function fingerprint(int $projectId,object $header,string $date,array $legs): string
    {
        return hash('sha256',json_encode([
            'project_id'=>$projectId,'journal_id'=>(int)$header->id,
            'voucher_number'=>(string)$header->voucher_number,
            'effective_date'=>$date,'lines'=>$legs,
        ],JSON_THROW_ON_ERROR));
    }

    private function sourceLines($details): array
    {
        return $details->map(fn($row)=>[
            'detail_id'=>(int)$row->id,
            'account_id'=>(int)$row->account_id,
            'debit'=>$this->money($this->cents($row->debit)),
            'credit'=>$this->money($this->cents($row->credit)),
            'description'=>$row->description,
        ])->all();
    }

    public function update(int $projectId,int $voucherId,string $date,array $submitted,
        array $metadata=[],?int $actor=null): void
    {
        FinanceConnectionGuard::assertAllowed();
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if ($projectId<1||$voucherId<1||!$day||$day->format('Y-m-d')!==$date
            ||count($submitted)<2||count($submitted)>100) {
            throw new \DomainException('Journal correction input is invalid.');
        }
        foreach(['finance_posting_lines','finance_journal_line_sources',
                 'finance_posting_journal_sources'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \DomainException('Journal posting relationship migration is required.');
            }
        }
        if (!Schema::hasColumn('finance_posting_lines','is_active')) {
            throw new \DomainException('Journal posting lifecycle migration is required.');
        }
        DB::transaction(function()use($projectId,$voucherId,$date,$submitted,$metadata,$actor):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $header=DB::table('journal_vouchers')
                ->where('id',$voucherId)->where('project_id',$projectId)->whereNull('deleted_at')
                ->lockForUpdate()->first();
            if (!$header||!in_array((string)$header->type,['JV','SV'],true)) {
                throw new \DomainException('Journal source is not an active JV/SV voucher.');
            }
            if (Schema::hasTable('daily_voucher_approvals')
                && DB::table('daily_voucher_approvals')->where('project_id',$projectId)
                    ->where('source_table','journal_vouchers')->where('source_id',$voucherId)
                    ->where('status','pending')->exists()) {
                throw new \DomainException('Journal voucher has an approval decision pending.');
            }
            $sources=DB::table('finance_posting_journal_sources as src')
                ->join('finance_posting_batches as batch','batch.id','=','src.posting_batch_id')
                ->where('src.journal_voucher_id',$voucherId)
                ->where('batch.project_id',$projectId)
                ->whereNull('batch.reversal_of_batch_id')->get(['batch.id']);
            if ($sources->count()!==1) {
                throw new \DomainException('Journal voucher requires exactly one owned posting batch.');
            }
            $batchId=(int)$sources[0]->id;
            $batch=DB::table('finance_posting_batches')
                ->where('id',$batchId)->where('project_id',$projectId)
                ->lockForUpdate()->first();
            if (!$batch||!$batch->posted_at
                ||(string)$batch->voucher_series!==(string)$header->type
                ||(string)$batch->voucher_number!==(string)$header->voucher_number
                ||(string)$batch->effective_date!==(string)$header->date
                ||DB::table('finance_posting_batches')->where('reversal_of_batch_id',$batchId)->exists()
                ||!DB::table('finance_posting_source_claims')
                    ->where('posting_batch_id',$batchId)->where('source_kind','journal')->exists()) {
                throw new \DomainException('Journal posting is reversed or does not own this voucher.');
            }
            $old=DB::table('journal_voucher_details')
                ->where('journal_voucher_id',$voucherId)->whereNull('deleted_at')
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if($old->count()<2)throw new \DomainException('Journal requires at least two active details.');
            $expected=$this->sourceLines($old->values());
            if (!hash_equals((string)$batch->payload_sha256,
                $this->fingerprint($projectId,$header,(string)$header->date,$expected))) {
                throw new \DomainException('Journal original posting fingerprint mismatch.');
            }
            $posted=DB::table('finance_journal_line_sources as src')
                ->join('finance_posting_lines as line','line.id','=','src.posting_line_id')
                ->where('line.posting_batch_id',$batchId)->where('line.is_active',1)
                ->lockForUpdate()->get(['line.id','line.project_head_subhead_id',
                    'line.debit','line.credit','line.description','src.journal_voucher_detail_id'])
                ->groupBy('journal_voucher_detail_id');
            $activeCount=DB::table('finance_posting_lines')
                ->where('posting_batch_id',$batchId)->where('is_active',1)->count();
            if($activeCount!==$old->count()||$posted->flatten(1)->count()!==$old->count()) {
                throw new \DomainException('Journal posting source-to-detail cardinality mismatch.');
            }
            // Typed vouchers may have no legacy ledgers at all. Where they
            // exist, require 1:1 FK-linked ownership; never guess by type_id.
            $legacy=Schema::hasTable('ledgers')
                ? DB::table('ledgers')->where('type',(string)$header->type)
                    ->where('type_id',$voucherId)->where('is_active',1)
                    ->lockForUpdate()->get()->groupBy('journal_voucher_detail_id')
                : collect();
            if ($legacy->flatten(1)->isNotEmpty()
                && !Schema::hasColumn('ledgers','journal_voucher_detail_id')) {
                throw new \DomainException('Journal legacy detail FK column is required.');
            }
            if($legacy->flatten(1)->count()!==0 && $legacy->flatten(1)->count()!==$old->count()) {
                throw new \DomainException('Journal legacy ledger linkage is incomplete.');
            }
            $hasLegacy=$legacy->flatten(1)->count()===$old->count();
            foreach($old as $id=>$detail) {
                $post=$posted->get($id,collect());
                $leg=$legacy->get($id,collect());
                if($post->count()!==1
                    ||(int)$post[0]->project_head_subhead_id!==(int)$detail->account_id
                    ||$this->cents($post[0]->debit)!==$this->cents($detail->debit)
                    ||$this->cents($post[0]->credit)!==$this->cents($detail->credit)
                    ||(string)($post[0]->description??'')!==(string)($detail->description??'')) {
                    throw new \DomainException('Journal posting line FK/account/amount conflict.');
                }
                if($hasLegacy&&($leg->count()!==1
                    ||(int)$leg[0]->project_head_subheads_id!==(int)$detail->account_id
                    ||$this->cents($leg[0]->amount_in)!==$this->cents($detail->debit)
                    ||$this->cents($leg[0]->amount_out)!==$this->cents($detail->credit))) {
                    throw new \DomainException('Journal legacy ledger FK/account/amount conflict.');
                }
            }
            $desired=[];$used=[];$dr=0;$cr=0;
            foreach($submitted as $row) {
                $id=(int)($row['detail_id']??0);
                if($id&&(isset($used[$id])||!$old->has($id))) {
                    throw new \DomainException('Journal detail ID belongs to another voucher or is duplicated.');
                }
                if($id)$used[$id]=true;
                $account=(int)($row['account_id']??0);
                if(!\App\Models\ProjectHeadSubhead::availableForPosting()
                    ->where('project_id',$projectId)->whereKey($account)->exists()) {
                    throw new \DomainException('Journal account is outside this project or inactive.');
                }
                $debit=$this->cents($row['debit']??'0');
                $credit=$this->cents($row['credit']??'0');
                if(($debit>0)===($credit>0)) {
                    throw new \DomainException('Journal requires debit or credit, never both.');
                }
                $dr+=$debit;$cr+=$credit;
                $desired[]=['detail_id'=>$id,'account_id'=>$account,
                    'debit'=>$this->money($debit),'credit'=>$this->money($credit),
                    'description'=>$row['description']??null];
            }
            if($dr<1||$dr!==$cr)throw new \DomainException('Journal must be debit/credit balanced.');
            $reference=array_key_exists('reference',$metadata)?$metadata['reference']:$header->reference;
            $narration=array_key_exists('description',$metadata)?$metadata['description']:$header->description;
            $headerChanged=(string)$header->date!==$date
                ||(string)($header->reference??'')!==(string)($reference??'')
                ||(string)($header->description??'')!==(string)($narration??'');
            $replaced=[];
            foreach($desired as &$row) {
                $prior=$row['detail_id']?$old->get($row['detail_id']):null;
                $row['unchanged']=(bool)($prior&&!$headerChanged
                    &&(int)$prior->account_id===$row['account_id']
                    &&$this->cents($prior->debit)===$this->cents($row['debit'])
                    &&$this->cents($prior->credit)===$this->cents($row['credit'])
                    &&(string)($prior->description??'')===(string)($row['description']??''));
                if($prior)$replaced[(int)$prior->id]=true;
            }
            unset($row);
            if(!$headerChanged&&count($desired)===$old->count()
                &&collect($desired)->every(fn($row)=>$row['unchanged']))return;
            foreach($old as $id=>$detail) {
                $match=collect($desired)->first(fn($row)=>(int)$row['detail_id']===(int)$id);
                if($match&&$match['unchanged'])continue;
                DB::table('finance_posting_lines')->where('id',$posted[$id][0]->id)
                    ->where('posting_batch_id',$batchId)->where('is_active',1)
                    ->update(['is_active'=>0,'updated_at'=>now()]);
                if($hasLegacy)DB::table('ledgers')->where('id',$legacy[$id][0]->id)
                    ->where('journal_voucher_detail_id',$id)->where('is_active',1)
                    ->update(['is_active'=>0,'updated_at'=>now()]);
                DB::table('journal_voucher_details')->where('id',$id)
                    ->where('journal_voucher_id',$voucherId)->whereNull('deleted_at')
                    ->update(['deleted_at'=>now(),'updated_at'=>now()]);
            }
            foreach($desired as $row) {
                if($row['unchanged'])continue;
                $id=(int)DB::table('journal_voucher_details')->insertGetId([
                    'journal_voucher_id'=>$voucherId,'account_id'=>$row['account_id'],
                    'debit'=>$row['debit'],'credit'=>$row['credit'],
                    'description'=>$row['description'],
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $postId=(int)DB::table('finance_posting_lines')->insertGetId([
                    'posting_batch_id'=>$batchId,'project_head_subhead_id'=>$row['account_id'],
                    'debit'=>$row['debit'],'credit'=>$row['credit'],
                    'description'=>$row['description'],'is_active'=>1,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('finance_journal_line_sources')->insert([
                    'posting_line_id'=>$postId,'journal_voucher_detail_id'=>$id,
                ]);
                if($hasLegacy){
                    \App\Models\Ledger::create([
                        'voucher_number'=>$header->voucher_number,'type'=>(string)$header->type,
                        'type_id'=>$voucherId,'journal_voucher_detail_id'=>$id,
                        'project_head_subheads_id'=>$row['account_id'],
                        'reference'=>$reference,'date'=>$date,
                        'amount_in'=>$row['debit'],'amount_out'=>$row['credit'],
                        'detail'=>$row['description'],'is_active'=>1,
                        'status'=>'0','create_by'=>$actor,
                    ]);
                }
            }
            $current=DB::table('journal_voucher_details')
                ->where('journal_voucher_id',$voucherId)->whereNull('deleted_at')
                ->orderBy('id')->get();
            $newHash=$this->fingerprint($projectId,$header,$date,$this->sourceLines($current));
            DB::table('journal_vouchers')->where('id',$voucherId)
                ->where('project_id',$projectId)->update([
                    'date'=>$date,'reference'=>$reference,'description'=>$narration,
                    'total_debit'=>$this->money($dr),'total_credit'=>$this->money($cr),
                    'updated_by'=>$actor,'updated_at'=>now(),
                ]);
            DB::table('finance_posting_batches')->where('id',$batchId)
                ->where('project_id',$projectId)
                ->update(['effective_date'=>$date,'payload_sha256'=>$newHash,'updated_at'=>now()]);
        },3);
    }
}
