<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BalancedStandardVoucherIntegrity;
use PHPUnit\Framework\TestCase;

final class BalancedStandardVoucherIntegrityTest extends TestCase
{
    private function source(string $series): object
    {
        $incoming=in_array($series,['CR','CCR'],true);
        return (object)[
            'amount_in'=>$incoming?'120.00':'0.00',
            'amount_out'=>$incoming?'0.00':'120.00',
        ];
    }

    private function legs(string $series): \Illuminate\Support\Collection
    {
        $incoming=in_array($series,['CR','CCR'],true);
        return collect([
            (object)['id'=>10,'type'=>$series,'project_head_subheads_id'=>3,
                'amount_in'=>$incoming?'120.00':'0.00',
                'amount_out'=>$incoming?'0.00':'120.00'],
            (object)['id'=>11,'type'=>$series,'project_head_subheads_id'=>4,
                'amount_in'=>$incoming?'0.00':'120.00',
                'amount_out'=>$incoming?'120.00':'0.00'],
        ]);
    }

    public function test_each_standard_series_requires_exact_two_sided_posting(): void
    {
        foreach(['CR','DR','CP','CCR','CDR'] as $series){
            BalancedStandardVoucherIntegrity::assert($series,$this->source($series),$this->legs($series));
            $this->assertCount(2,$this->legs($series));
        }
    }

    public function test_same_account_on_both_legs_is_rejected(): void
    {
        $legs=$this->legs('CR');
        $legs[1]->project_head_subheads_id=3;
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('STANDARD_VOUCHER_ACCOUNT_OR_TYPE_INVALID');
        BalancedStandardVoucherIntegrity::assert('CR',$this->source('CR'),$legs);
    }

    public function test_unbalanced_counter_direction_is_rejected(): void
    {
        $legs=$this->legs('DR');
        $legs[1]->amount_in='0.00';
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('STANDARD_VOUCHER_UNBALANCED_POSTING');
        BalancedStandardVoucherIntegrity::assert('DR',$this->source('DR'),$legs);
    }

    public function test_single_leg_is_rejected_by_new_integrity_guard(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('STANDARD_VOUCHER_TWO_LEGS_REQUIRED');
        BalancedStandardVoucherIntegrity::assert('CCR',$this->source('CCR'),$this->legs('CCR')->take(1));
    }
}
