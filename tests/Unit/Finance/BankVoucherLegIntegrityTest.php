<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\BankVoucherLegIntegrity;
use PHPUnit\Framework\TestCase;

final class BankVoucherLegIntegrityTest extends TestCase
{
    private function posting(float $in, float $out): object
    {
        return (object)['amount_in'=>$in,'amount_out'=>$out];
    }

    public function test_valid_incoming_receipt_and_outgoing_payment(): void
    {
        BankVoucherLegIntegrity::assert('BOR',$this->posting(400,0),$this->posting(400,0),400);
        BankVoucherLegIntegrity::assert('CR',$this->posting(400,0),$this->posting(400,0),400);
        BankVoucherLegIntegrity::assert('BOP',$this->posting(0,250),$this->posting(0,250),250);
        BankVoucherLegIntegrity::assert('DR',$this->posting(0,250),$this->posting(0,250),250);
        $this->addToAssertionCount(4);
    }

    public function test_reversed_ledger_direction_is_rejected_even_when_amount_matches(): void
    {
        $this->expectException(\DomainException::class);
        BankVoucherLegIntegrity::assert('BOR',$this->posting(400,0),$this->posting(0,400),400);
    }

    public function test_outgoing_voucher_cannot_activate_incoming_source(): void
    {
        $this->expectException(\DomainException::class);
        BankVoucherLegIntegrity::assert('BOP',$this->posting(250,0),$this->posting(0,250),250);
    }

    public function test_both_debit_and_credit_on_one_leg_are_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankVoucherLegIntegrity::assert('CR',$this->posting(400,0),$this->posting(400,400),400);
    }

    public function test_zero_and_negative_allocation_cannot_be_approved(): void
    {
        foreach ([0,-10] as $amount) {
            try {
                BankVoucherLegIntegrity::assert('BOR',$this->posting(0,0),$this->posting(0,0),$amount);
                $this->fail('Non-positive allocation must be rejected.');
            } catch (\DomainException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_valid_proposed_bank_revision_keeps_the_original_direction(): void
    {
        foreach ([
            ['BOR', 400.50, 0.0, 400.50],
            ['BOP', 0.0, 315.25, 315.25],
        ] as [$series,$in,$out,$allocation]) {
            $proposed=(object)['amount_in'=>$in,'amount_out'=>$out];
            BankVoucherLegIntegrity::assert($series,$proposed,$proposed,$allocation);
            $this->addToAssertionCount(1);
        }
    }

    public function test_proposed_revision_with_both_sides_filled_is_rejected(): void
    {
        $proposal=(object)['amount_in'=>400.00,'amount_out'=>400.00];
        $this->expectException(\DomainException::class);
        BankVoucherLegIntegrity::assert('BOR',$proposal,$proposal,400.00);
    }

    public function test_proposed_revision_reversing_payment_direction_is_rejected(): void
    {
        $proposal=(object)['amount_in'=>315.25,'amount_out'=>0.0];
        $this->expectException(\DomainException::class);
        BankVoucherLegIntegrity::assert('BOP',$proposal,$proposal,315.25);
    }

    public function test_wrong_allocation_amount_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankVoucherLegIntegrity::assert('BOR',$this->posting(400,0),$this->posting(400,0),450);
    }
}
