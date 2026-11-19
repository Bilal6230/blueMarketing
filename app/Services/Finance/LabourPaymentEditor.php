<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Original cash CP labour payment correction.
 *
 * This routine does not touch typed payroll membership, cleared instruments,
 * attendance/source vouchers or unrelated CP records.
 */
final class LabourPaymentEditor
{
    private function cents(mixed $raw): int
    {
        $value=(string)$raw;
        if(!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',$value,$m))
            throw new \DomainException('Labour payment amount invalid.');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    private function state(int $projectId,int $sourceId): array
    {
        if(!Schema::hasTable('labour_ledgers')
            ||!Schema::hasColumn('labour_ledgers','customer_ledger_id')
            ||!Schema::hasColumn('labour_ledgers','is_active')
            ||!Schema::hasColumn('labour_ledgers','deleted_at')) {
            throw new \DomainException('Labour payment source and archive migrations are required.');
        }
        $source=DB::table('customer_ledger')
            ->where('id',$sourceId)->where('project_id',$projectId)
            ->where('transaction_type','CP')->where('payment_type',1)
            ->where('is_active',1)->lockForUpdate()->first();
        if(!$source||(float)$source->amount_in!==0.0
            ||(float)$source->amount_out<=0)
            throw new \DomainException('Only an active cash labour CP can be corrected.');
        if(Schema::hasTable('finance_posting_cash_sources')
            &&DB::table('finance_posting_cash_sources')
                ->where('customer_ledger_id',$sourceId)->exists())
            throw new \DomainException('This labour CP is controlled by a typed finance posting.');
        if(Schema::hasTable('finance_labour_payment_members')
            &&DB::table('finance_labour_payment_members')
                ->where('customer_ledger_id',$sourceId)->exists())
            throw new \DomainException('Typed payroll members have their own correction authority.');
        if(Schema::hasTable('daily_voucher_approvals')
            &&DB::table('daily_voucher_approvals')
                ->where('project_id',$projectId)->where('source_table','customer_ledger')
                ->where('source_id',$sourceId)->where('status','pending')->exists())
            throw new \DomainException('Labour CP has an unresolved approval.');
        $legs=DB::table('ledgers as l')
            ->join('project_head_subheads as account',
                'account.id','=','l.project_head_subheads_id')
            ->where('l.customer_ledger_id',$sourceId)->where('l.type','CP')
            ->where('l.is_active',1)->where('account.project_id',$projectId)
            ->lockForUpdate()->get(['l.id','l.amount_in','l.amount_out',
                'l.type_id','l.voucher_number','l.reference','l.date']);
        if($legs->count()!==1||DB::table('ledgers')
            ->where('customer_ledger_id',$sourceId)->count()!==1
            ||$this->cents($legs[0]->amount_in)!==0
            ||$this->cents($legs[0]->amount_out)!==$this->cents($source->amount_out)
            ||(string)$legs[0]->reference!==(string)$source->reference
            ||(string)$legs[0]->date!==(string)$source->date)
            throw new \DomainException('Labour CP ledger FK or posting amount mismatch.');
        $members=DB::table('labour_ledgers')->where('project_id',$projectId)
            ->where('customer_ledger_id',$sourceId)->where('is_active',1)
            ->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if($members->isEmpty()||DB::table('labour_ledgers')
            ->where('customer_ledger_id',$sourceId)->where('project_id','!=',$projectId)->exists()
            ||DB::table('labour_ledgers')->where('project_id',$projectId)
                ->where('customer_ledger_id',$sourceId)
                ->where('is_active',0)->whereNull('deleted_at')->exists()) {
            throw new \DomainException('Labour payment membership is missing or historically corrected.');
        }
        $total=0;
        foreach($members as $member){
            if(!DB::table('labours')->where('id',$member->labour_id)
                ->where('project_id',$projectId)->exists())
                throw new \DomainException('Labour payment member belongs to another project.');
            $total+=$this->cents($member->amount);
        }
        if($total!==$this->cents($source->amount_out))
            throw new \DomainException('Labour member total and original CP disagree.');
        return [$source,$legs[0],$members];
    }

    public function details(int $projectId,int $sourceId): array
    {
        FinanceConnectionGuard::assertAllowed();
        return DB::transaction(function()use($projectId,$sourceId):array{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            [$source,$leg,$members]=$this->state($projectId,$sourceId);
            return [
                'source_id'=>$sourceId,'date'=>(string)$source->date,
                'reference'=>(string)$source->reference,
                'description'=>(string)$source->description,
                'total'=>(string)$source->amount_out,
                'members'=>$members->values()->map(fn($row)=>[
                    'detail_id'=>(int)$row->id,'labour_id'=>(int)$row->labour_id,
                    'amount'=>(string)$row->amount,
                ])->all(),
            ];
        },3);
    }

    public function update(int $projectId,int $sourceId,string $date,
        string $reference,string $description,array $submitted): void
    {
        FinanceConnectionGuard::assertAllowed();
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if($projectId<1||$sourceId<1||!$parsed
            ||$parsed->format('Y-m-d')!==$date
            ||trim($reference)===''||strlen($reference)>200
            ||strlen($description)>2000
            ||count($submitted)<1||count($submitted)>100)
            throw new \DomainException('Labour payment correction metadata invalid.');
        DB::transaction(function()use($projectId,$sourceId,$date,$reference,$description,$submitted):void{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            [$source,$leg,$old]=$this->state($projectId,$sourceId);
            $used=[];$workers=[];$total=0;$all=[];
            foreach($submitted as $row) {
                $detailId=(int)($row['detail_id']??0);
                $labourId=(int)($row['labour_id']??0);
                $amount=$this->cents($row['amount']??'');
                if($labourId<1||$amount<1
                    ||isset($workers[$labourId])
                    ||($detailId&&(!$old->has($detailId)||isset($used[$detailId])))
                    ||!DB::table('labours')->where('id',$labourId)
                        ->where('project_id',$projectId)->exists()) {
                    throw new \DomainException('Labour payment row ID, worker, or amount invalid.');
                }
                if($detailId&&(int)$old[$detailId]->labour_id!==$labourId)
                    throw new \DomainException('Labour detail ID cannot be transferred to another worker.');
                $workers[$labourId]=true;
                if($detailId)$used[$detailId]=true;
                $total+=$amount;
                $all[]=['detail_id'=>$detailId,'labour_id'=>$labourId,
                    'amount'=>$this->money($amount)];
            }
            if($total<1)throw new \DomainException('Labour payment must be positive.');
            $unchanged=$total===$this->cents($source->amount_out)
                &&(string)$source->date===$date
                &&(string)$source->reference===$reference
                &&(string)$source->description===$description
                &&count($old)===count($submitted);
            if($unchanged)foreach($all as $row){
                if(!$row['detail_id']
                    ||$this->cents($old[$row['detail_id']]->amount)
                        !==$this->cents($row['amount'])){$unchanged=false;break;}
            }
            if($unchanged)return;
            $now=now();
            foreach($old as $id=>$member){
                if(isset($used[$id]))continue;
                DB::table('labour_ledgers')->where('id',$id)
                    ->where('project_id',$projectId)
                    ->where('customer_ledger_id',$sourceId)
                    ->where('is_active',1)->update([
                        'is_active'=>0,'deleted_at'=>$now,'updated_at'=>$now,
                    ]);
            }
            foreach($all as $row){
                if($row['detail_id']){
                    DB::table('labour_ledgers')
                        ->where('id',$row['detail_id'])
                        ->where('project_id',$projectId)
                        ->where('customer_ledger_id',$sourceId)
                        ->where('is_active',1)->update([
                            'amount'=>$row['amount'],'date'=>$date,'updated_at'=>$now,
                        ]);
                }else{
                    DB::table('labour_ledgers')->insert([
                        'project_id'=>$projectId,'customer_ledger_id'=>$sourceId,
                        'labour_id'=>$row['labour_id'],
                        'contractor_id'=>$old->first()->contractor_id,
                        'amount'=>$row['amount'],'date'=>$date,
                        'details'=>json_encode(['origin'=>'manual_correction'],JSON_THROW_ON_ERROR),
                        'is_active'=>1,'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
            $amount=$this->money($total);
            DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->where('is_active',1)->update([
                    'amount_out'=>$amount,'date'=>$date,
                    'reference'=>$reference,'description'=>$description,
                    'updated_at'=>$now,
                ]);
            DB::table('ledgers')->where('id',$leg->id)
                ->where('customer_ledger_id',$sourceId)->where('is_active',1)->update([
                    'amount_out'=>$amount,'date'=>$date,
                    'reference'=>$reference,'detail'=>$description,'updated_at'=>$now,
                ]);
        },3);
    }

    public function delete(int $projectId,int $sourceId): void
    {
        FinanceConnectionGuard::assertAllowed();
        DB::transaction(function()use($projectId,$sourceId):void{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            [$source,$leg,$members]=$this->state($projectId,$sourceId);
            $now=now();
            $count=DB::table('labour_ledgers')->where('project_id',$projectId)
                ->where('customer_ledger_id',$sourceId)
                ->whereIn('id',$members->keys()->all())->where('is_active',1)
                ->update(['is_active'=>0,'deleted_at'=>$now,'updated_at'=>$now]);
            if($count!==$members->count())
                throw new \DomainException('Labour members changed during deletion.');
            DB::table('ledgers')->where('id',$leg->id)
                ->where('customer_ledger_id',$sourceId)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>$now]);
            DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>$now]);
        },3);
    }
}
