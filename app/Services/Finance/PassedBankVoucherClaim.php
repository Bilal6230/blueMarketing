<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PassedBankVoucherClaim
{
    /**
     * Must be called inside the existing project-locked LedgerController
     * transaction. The database unique claim keys prevent concurrent reuse.
     */
    public function verify(int $projectId,string $kind,int $id,string $direction,
        int $paymentType,float $amount,string $number,int $accountMappingId,
        string $passingDate,int $bankId): object
    {
        if($id<=0 || !in_array($kind,['online','cheque'],true)
            || !in_array($direction,['receipt','payment'],true)
            || ($kind==='online' && $paymentType!==2)
            || ($kind==='cheque' && $paymentType!==3)) {
            throw new \DomainException('Invalid passed bank source or payment type.');
        }
        $table=$kind==='online'?'finance_online_voucher_claims':'finance_cheque_voucher_claims';
        $column=$kind==='online'?'online_intake_id':'cheque_intake_id';
        $intake=$kind==='online'?'finance_online_intakes':'finance_cheque_intakes';
        $events=$kind==='online'?'finance_online_confirmation_events':'finance_cheque_intake_actions';
        if(!Schema::hasTable($table)||!Schema::hasTable($intake)||!Schema::hasTable($events)
            ||!Schema::hasTable('finance_bank_voucher_allocations')){
            throw new \DomainException('Bank source and claim migrations must be applied first.');
        }
        $source=DB::table($intake)->where('project_id',$projectId)->where('id',$id)
            ->lockForUpdate()->first();
        if(!$source)throw new \DomainException('Bank source is not available in selected project.');
        // Legacy single-use claims stay fully reserved, never silently reallocated.
        if(DB::table($table)->where('project_id',$projectId)->where($column,$id)->exists())
            throw new \DomainException('This source was fully claimed by an earlier voucher.');
        if($kind==='online'){
            $evidence=DB::table($events)->where('project_id',$projectId)
                ->where('online_intake_id',$id)->where('from_status','pending')
                ->where('to_status','confirmed')->where('original_amount',$source->amount)
                ->where('transaction_type',$source->type)->first();
            $valid=$source->status==='confirmed' && $source->type===$direction && $evidence;
            $sourceNumber=(string)$source->reference;
        }else{
            $evidence=DB::table($events)->where('project_id',$projectId)
                ->where('cheque_intake_id',$id)->where('action','cleared')
                ->whereNotNull('verification_confirmed_at')->orderByDesc('id')->first();
            $valid=$source->status==='cleared'
                && $source->direction===($direction==='receipt'?'in':'out') && $evidence;
            $sourceNumber=(string)$source->cheque_number;
        }
        if(!$valid)throw new \DomainException('Selected bank source has not passed verified management.');
        $verifiedDate=(string)($kind==='online'
            ? ($evidence->verification_date??'')
            : ($evidence->action_date??''));
        if(substr($verifiedDate,0,10)!==substr($passingDate,0,10)){
            throw new \DomainException('Passing date must match the verified bank management decision.');
        }
        $sourceBank=$kind==='online'
            ? (string)($direction==='receipt'?$source->receiving_bank:$source->sending_bank)
            : (string)($evidence->bank_name?:$source->bank_name);
        $selectedBank=$bankId>0?(string)getBankNameById($bankId):'';
        $normalise=fn(string $value)=>strtolower((string)preg_replace('/[^a-z0-9]/i','',$value));
        $actual=$normalise($sourceBank);
        $provided=$normalise($selectedBank);
        $initials=strtolower(implode('',array_map(
            fn($word)=>substr($word,0,1),
            array_filter(preg_split('/[^a-z0-9]+/i',$selectedBank))
        )));
        if($actual===''||$provided==='' ||
            ($actual!==$provided && !str_contains($actual,$provided)
                && !str_contains($provided,$actual) && $actual!==$initials)) {
            throw new \DomainException('Selected bank does not match the verified management source.');
        }
        if($sourceNumber!==trim($number)) {
            throw new \DomainException('Voucher reference does not match the verified source.');
        }
        // Use exact decimal cents; no floating point budget comparisons.
        $totalCents=$this->cents((string)$source->amount);
        $requestCents=$this->cents(number_format($amount,2,'.',''));
        $allocated=DB::table('finance_bank_voucher_allocations')
            ->where('project_id',$projectId)->where('source_method',$kind)
            ->where('source_id',$id)->sum('amount');
        $remainingCents=$totalCents-$this->cents((string)$allocated);
        if($requestCents<=0 || $requestCents>$remainingCents) {
            throw new \DomainException('Amount exceeds remaining verified source balance ('
                .number_format(max(0,$remainingCents)/100,2,'.','').'). Refresh the source picker.');
        }
        // Voucher account is intentionally chosen independently for plot/party allocation.
        // LedgerController still enforces selected-project account ownership.
        return $source;
    }

    private function cents(string $amount): int
    {
        return (int) round(((float)str_replace(',','',$amount))*100);
    }

    public function claim(int $projectId,string $kind,int $intakeId,
        string $voucherType,int $voucherId,int $userId,float $amount): void
    {
        // Called after verify() while the source row remains locked in the
        // same project-locked voucher transaction; both writes roll back together.
        if (!Schema::hasTable('finance_bank_voucher_allocations')) {
            throw new \DomainException('Bank partial allocation migration is required.');
        }
        DB::table('finance_bank_voucher_allocations')->insert([
            'project_id'=>$projectId,
            'source_method'=>$kind,'source_id'=>$intakeId,
            'voucher_type'=>$voucherType,'voucher_id'=>$voucherId,
            'amount'=>number_format($amount,2,'.',''),
            'allocated_by'=>$userId,'allocated_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
