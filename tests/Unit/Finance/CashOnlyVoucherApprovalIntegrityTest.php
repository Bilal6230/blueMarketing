<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\CashOnlyVoucherApprovalIntegrity;
use PHPUnit\Framework\TestCase;

final class CashOnlyVoucherApprovalIntegrityTest extends TestCase
{
    private function source(string $type, float $incoming, float $outgoing): object
    {
        return (object)[
            'transaction_type'=>$type,'payment_type'=>1,'type_id'=>14,
            'is_active'=>0,'amount_in'=>$incoming,'amount_out'=>$outgoing,
        ];
    }

    private function leg(string $type, float $incoming, float $outgoing): object
    {
        return (object)[
            'type'=>$type,'type_id'=>14,'voucher_number'=>14,
            'is_active'=>0,'amount_in'=>$incoming,'amount_out'=>$outgoing,
        ];
    }

    public function test_valid_ccr_and_cdr_initial_approvals(): void
    {
        CashOnlyVoucherApprovalIntegrity::assert(
            'CCR',$this->source('CCR',300,0),$this->leg('CCR',300,0));
        CashOnlyVoucherApprovalIntegrity::assert(
            'CDR',$this->source('CDR',0,225),$this->leg('CDR',0,225));
        $this->addToAssertionCount(2);
    }

    public function test_ccr_wrong_direction_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        CashOnlyVoucherApprovalIntegrity::assert(
            'CCR',$this->source('CCR',0,300),$this->leg('CCR',0,300));
    }

    public function test_cdr_wrong_direction_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        CashOnlyVoucherApprovalIntegrity::assert(
            'CDR',$this->source('CDR',225,0),$this->leg('CDR',225,0));
    }

    public function test_unbalanced_source_and_ledger_amount_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        CashOnlyVoucherApprovalIntegrity::assert(
            'CCR',$this->source('CCR',300,0),$this->leg('CCR',301,0));
    }

    public function test_both_amount_sides_set_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        CashOnlyVoucherApprovalIntegrity::assert(
            'CDR',$this->source('CDR',50,225),$this->leg('CDR',50,225));
    }

    public function test_bank_method_cannot_be_approved_as_cash_only(): void
    {
        $source=$this->source('CCR',300,0);
        $source->payment_type=2;
        $this->expectException(\DomainException::class);
        CashOnlyVoucherApprovalIntegrity::assert('CCR',$source,$this->leg('CCR',300,0));
    }

    public function test_mismatched_voucher_number_is_rejected(): void
    {
        $leg=$this->leg('CCR',300,0);
        $leg->voucher_number=15;
        $this->expectException(\DomainException::class);
        CashOnlyVoucherApprovalIntegrity::assert('CCR',$this->source('CCR',300,0),$leg);
    }

    public function test_already_active_source_cannot_be_approved_again(): void
    {
        $source=$this->source('CCR',300,0);
        $source->is_active=1;
        $this->expectException(\DomainException::class);
        CashOnlyVoucherApprovalIntegrity::assert('CCR',$source,$this->leg('CCR',300,0));
    }
}
