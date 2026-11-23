<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

/**
 * Real two-leg accounting for new BOR/BOP vouchers. Existing allocations
 * without bank_account_id retain their original historical single-leg shape.
 * Bank account is tied to the immutable allocation, not a browser-only field.
 */
final class BalancedBankVoucherIntegrity
{
    public static function bankAccount(int $projectId, int $bankId, int $partyMapping): object
    {
        if($projectId<1 || $bankId<1 || !Schema::hasTable('project_bank_accounts')){
            throw new \DomainException('BANK_VOUCHER_BANK_ACCOUNT_REQUIRED');
        }
        $bank=DB::table('project_bank_accounts')
            ->where('project_id',$projectId)->where('id',$bankId)
            ->where('is_active',1)->lockForUpdate()->first();
        if(!$bank || (int)$bank->account_mapping_id===$partyMapping
            || !\App\Models\ProjectHeadSubhead::availableForPosting()
                ->where('project_id',$projectId)
                ->where('id',$bank->account_mapping_id)->exists()){
            throw new \DomainException('BANK_VOUCHER_BANK_ACCOUNT_PROJECT_OR_MAPPING_INVALID');
        }
        return $bank;
    }

    public static function assert(int $projectId,string $series,object $source,
        object $allocation,Collection $legs): void
    {
        if(!in_array($series,['BOR','BOP'],true) || !$allocation->bank_account_id
            || $legs->count()!==2 || $projectId<1) {
            throw new \DomainException('BANK_VOUCHER_BALANCED_LEGS_REQUIRED');
        }
        $bank=DB::table('project_bank_accounts')
            ->where('project_id',$projectId)
            ->where('id',$allocation->bank_account_id)->first();
        if(!$bank)throw new \DomainException('BANK_VOUCHER_BANK_ACCOUNT_IDENTITY_INVALID');
        $amount=round((float)$allocation->amount,2);
        $incoming=$series==='BOR';
        $sourceIn=round((float)$source->amount_in,2);
        $sourceOut=round((float)$source->amount_out,2);
        if($amount<=0 || ($incoming && ($sourceIn!==$amount || $sourceOut!==0.0))
            || (!$incoming && ($sourceOut!==$amount || $sourceIn!==0.0))){
            throw new \DomainException('BANK_VOUCHER_SOURCE_DIRECTION_INVALID');
        }
        $bankLegs=$legs->filter(fn($leg)=>
            (int)$leg->project_head_subheads_id===(int)$bank->account_mapping_id);
        $otherLegs=$legs->reject(fn($leg)=>
            (int)$leg->project_head_subheads_id===(int)$bank->account_mapping_id);
        if($bankLegs->count()!==1 || $otherLegs->count()!==1) {
            throw new \DomainException('BANK_VOUCHER_COUNTER_ACCOUNT_INVALID');
        }
        $bankLeg=$bankLegs->first();
        $counter=$otherLegs->first();
        $bankIn=round((float)$bankLeg->amount_in,2);
        $bankOut=round((float)$bankLeg->amount_out,2);
        $otherIn=round((float)$counter->amount_in,2);
        $otherOut=round((float)$counter->amount_out,2);
        if(($incoming && ($bankIn!==$amount || $bankOut!==0.0 ||
                    $otherOut!==$amount || $otherIn!==0.0))
            || (!$incoming && ($bankOut!==$amount || $bankIn!==0.0 ||
                    $otherIn!==$amount || $otherOut!==0.0))) {
            throw new \DomainException('BANK_VOUCHER_DOUBLE_ENTRY_DIRECTION_INVALID');
        }
        $expected=in_array((string)$source->transaction_type,['CR','BOR'],true)
            ? 'BOR':'BOP';
        if($expected!==$series ||
            $legs->contains(fn($leg)=>!in_array((string)$leg->type,
                [$series,$series==='BOR'?'CR':'CP'],true))){
            throw new \DomainException('BANK_VOUCHER_DOUBLE_ENTRY_IDENTITY_INVALID');
        }
    }
}
