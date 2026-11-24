<?php

namespace App\Services\Finance;

use App\Models\CustomerLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct/archive an existing legacy cash EC or ECP with its exact linked
 * ledger pair. Typed financial batches and bank/cheque custody are independent
 * owners and deliberately cannot be rewritten through this service.
 */
final class ExtraChargeCashEditor
{
    private function cents(mixed $value): int
    {
        $s=trim((string)$value);
        if(!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',$s,$m))
            throw new \DomainException('Extra Charge amount format is invalid.');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    private function source(int $projectId,int $sourceId): array
    {
        $source=CustomerLedger::query()->where('id',$sourceId)
            ->where('project_id',$projectId)->whereIn('transaction_type',['EC','ECP'])
            ->where('is_active',1)->lockForUpdate()->first();
        if(!$source||(float)$source->amount_in<=0
            ||(float)$source->amount_out!==0.0
            ||($source->transaction_type==='ECP' && (int)$source->payment_type!==1)
            ||($source->transaction_type==='EC'
                &&!in_array((int)($source->payment_type??0),[0,1],true))) {
            throw new \DomainException('Extra Charge source is not an active cash EC/ECP voucher.');
        }
        $series=(string)$source->transaction_type;
        $typed=$series==='EC'?'finance_extra_charge_sources':'finance_extra_charge_payment_sources';
        if((Schema::hasTable($typed)
                && DB::table($typed)->where('customer_ledger_id',$sourceId)->exists())
            ||(Schema::hasTable('finance_posting_cash_sources')
                && DB::table('finance_posting_cash_sources')->where('customer_ledger_id',$sourceId)->exists())) {
            throw new \DomainException('Extra Charge is owned by a typed financial posting.');
        }
        if(Schema::hasTable('daily_voucher_approvals')
            &&DB::table('daily_voucher_approvals')->where('project_id',$projectId)
                ->where('source_table','customer_ledger')->where('source_id',$sourceId)
                ->where('status','pending')->exists()) {
            throw new \DomainException('Extra Charge has an outstanding approval.');
        }
        $legs=DB::table('ledgers as leg')
            ->join('project_head_subheads as account','account.id','=','leg.project_head_subheads_id')
            ->where('leg.customer_ledger_id',$sourceId)->where('account.project_id',$projectId)
            ->lockForUpdate()->get(['leg.id','leg.type','leg.type_id','leg.voucher_number',
                'leg.reference','leg.date','leg.amount_in','leg.amount_out','leg.is_active','leg.project_head_subheads_id','leg.detail']);
        if($legs->count()!==2 || DB::table('ledgers')->where('customer_ledger_id',$sourceId)->count()!==2
            ||$legs->contains(fn($leg)=>(string)$leg->type!==$series
                ||(int)$leg->type_id!==(int)$source->type_id
                ||(int)$leg->voucher_number!==(int)$source->type_id
                ||(string)$leg->reference!==(string)$source->reference
                ||(int)$leg->is_active!==1
                ||(string)$leg->date!==(string)$source->date)) {
            throw new \DomainException('Extra Charge source-to-ledger identity is invalid.');
        }
        $debit=$legs->filter(fn($leg)=>$this->cents($leg->amount_in)>0
            &&$this->cents($leg->amount_out)===0);
        $credit=$legs->filter(fn($leg)=>$this->cents($leg->amount_out)>0
            &&$this->cents($leg->amount_in)===0);
        $value=$this->cents($source->amount_in);
        if($value<1||$debit->count()!==1||$credit->count()!==1
            ||$this->cents($debit->first()->amount_in)!==$value
            ||$this->cents($credit->first()->amount_out)!==$value) {
            throw new \DomainException('Extra Charge debit/credit ledger totals do not match source.');
        }
        $charge=$source;
        if($series==='ECP'){
            if(!preg_match('/^EC_PAYMENT#([1-9][0-9]*)#[^#]+$/D',(string)$source->reference,$m))
                throw new \DomainException('Payment has no exact Extra Charge source identity.');
            $charge=CustomerLedger::query()->where('project_id',$projectId)
                ->where('id',(int)$m[1])->where('transaction_type','EC')
                ->where('is_active',1)->lockForUpdate()->first();
            if(!$charge||(int)$charge->customer_id!==(int)$source->customer_id
                ||(int)$charge->plot_id!==(int)$source->plot_id)
                throw new \DomainException('Payment belongs to another Extra Charge or project.');
        }
        return [$source,$legs,$charge];
    }

    public function update(int $projectId,int $sourceId,string $date,string $amount,
        string $description,?int $customerId=null,?int $plotId=null,
        ?int $chargeTypeId=null,?string $reference=null): void
    {
        FinanceConnectionGuard::assertAllowed();
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if($projectId<1||$sourceId<1||!$day||$day->format('Y-m-d')!==$date
            ||strlen($description)>1000)
            throw new \DomainException('Extra Charge correction fields are invalid.');
        $cents=$this->cents($amount);
        if($cents<1)throw new \DomainException('Extra Charge must be positive.');
        DB::transaction(function()use($projectId,$sourceId,$date,$cents,$description,
            $customerId,$plotId,$chargeTypeId,$reference):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            [$source,$legs,$charge]=$this->source($projectId,$sourceId);
            $balance=app(ExtraChargeBankSettlement::class)->balances($charge);
            if($source->transaction_type==='EC'){
                if($cents<$balance['paid_cents']+$balance['pending_cents'])
                    throw new \DomainException('Charge cannot be less than cleared or reserved payments.');
            }elseif($cents>$this->cents($source->amount_in)+$balance['available_cents']){
                throw new \DomainException('Payment correction exceeds the remaining charge balance.');
            }
            $oldChargeType=null;
            if($source->transaction_type==='EC'
                &&preg_match('/ChargeType:([1-9][0-9]*)/',(string)$legs->first()->detail,$m)){
                $oldChargeType=(int)$m[1];
            }
            $identityChange=$source->transaction_type==='EC' && (
                ($customerId!==null&&(int)$source->customer_id!==$customerId)
                ||($plotId!==null&&(int)$source->plot_id!==$plotId)
                ||($reference!==null&&(string)$source->reference!==$reference)
                ||($chargeTypeId!==null&&$chargeTypeId!==$oldChargeType)
            );
            $mapping=null;$bookingId=null;
            if($identityChange){
                if($balance['paid_cents']>0||$balance['pending_cents']>0)
                    throw new \DomainException('Charge has linked payment history; identity fields cannot be changed.');
                $customerId=$customerId??(int)$source->customer_id;
                $plotId=$plotId??(int)$source->plot_id;
                $reference=$reference??(string)$source->reference;
                if(!preg_match('/^.{1,100}$/D',$reference)
                    ||str_contains($reference,'#')
                    ||DB::table('customer_ledger')->where('project_id',$projectId)
                        ->where('transaction_type','EC')->where('reference',$reference)
                        ->where('id','!=',$sourceId)->exists())
                    throw new \DomainException('Extra Charge reference is empty or already in use.');
                $bookings=DB::table('bookings')->where('project_id',$projectId)
                    ->where('customer_id',$customerId)->where('plot_id',$plotId)
                    ->where('status','active')->where('cancel_status','0')
                    ->whereNull('deleted_at')->lockForUpdate()->get(['id']);
                if($bookings->count()!==1)
                    throw new \DomainException('Customer and Plot need one active booking in the selected project.');
                $bookingId=(int)$bookings->first()->id;
                if($chargeTypeId!==null&&!DB::table('charge_types')->where('id',$chargeTypeId)->exists())
                    throw new \DomainException('Extra Charge type was not found.');
                $mapping=app(ExtraChargeAccounts::class)->forProject($projectId,$customerId,$plotId);
            }
            $newAmount=$this->money($cents);
            if(!$identityChange&&(string)$source->date===$date
                &&number_format((float)$source->amount_in,2,'.','')===$newAmount
                &&(string)($source->description??'')===$description)return;
            $customerUpdate=['date'=>$date,'amount_in'=>$newAmount,
                'description'=>$description,'updated_at'=>now()];
            if($identityChange){
                $customerUpdate['customer_id']=$customerId;
                $customerUpdate['plot_id']=$plotId;
                $customerUpdate['reference']=$reference;
            }
            DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->where('is_active',1)
                ->update($customerUpdate);
            foreach($legs as $leg){
                $debit=$this->cents($leg->amount_in)>0;
                $legUpdate=['date'=>$date,
                    'amount_in'=>$debit?$newAmount:'0.00',
                    'amount_out'=>$debit?'0.00':$newAmount,'updated_at'=>now()];
                if($identityChange){
                    $type=$chargeTypeId;
                    if($type===null&&preg_match('/ChargeType:([1-9][0-9]*)/',(string)$leg->detail,$m))
                        $type=(int)$m[1];
                    $legUpdate['detail']='EC | ChargeType:'.($type??0).' | Booking:'.$bookingId.' | '.$description;
                    $legUpdate['reference']=$reference;
                    // The legacy EC receivable mapping is the credit leg.
                    $legUpdate['project_head_subheads_id']=$debit
                        ?(int)$mapping['credit_account_id']:(int)$mapping['debit_account_id'];
                }else{
                    if($source->transaction_type==='EC'
                        &&preg_match('/^EC \| ChargeType:[^|]+\| Booking:[^|]+\| /',(string)$leg->detail,$matches)){
                        $legUpdate['detail']=$matches[0].$description;
                    }else{
                        $legUpdate['detail']=$description;
                    }
                }
                DB::table('ledgers')->where('id',$leg->id)
                    ->where('customer_ledger_id',$sourceId)->where('is_active',1)
                    ->update($legUpdate);
            }
        },3);
    }

    public function delete(int $projectId,int $sourceId): void
    {
        FinanceConnectionGuard::assertAllowed();
        if($projectId<1||$sourceId<1)throw new \DomainException('Extra Charge deletion identity is invalid.');
        DB::transaction(function()use($projectId,$sourceId):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            [$source,$legs,$charge]=$this->source($projectId,$sourceId);
            if($source->transaction_type==='EC'){
                $balance=app(ExtraChargeBankSettlement::class)->balances($charge);
                if($balance['paid_cents']>0||$balance['pending_cents']>0)
                    throw new \DomainException('Charge has linked payments and cannot be deleted.');
            }
            $ids=$legs->pluck('id')->all();
            $count=DB::table('ledgers')->whereIn('id',$ids)
                ->where('customer_ledger_id',$sourceId)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
            if($count!==2)throw new \DomainException('Extra Charge ledger changed concurrently.');
            $changed=DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
            if($changed!==1)throw new \DomainException('Extra Charge source changed concurrently.');
        },3);
    }
}
