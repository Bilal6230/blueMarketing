<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BalancedOriginalPprIntegrity;
use PHPUnit\Framework\TestCase;

final class BalancedOriginalPprIntegrityTest extends TestCase
{
    private function receipt(): object
    {
        return (object)['transaction_type'=>'PPR','amount_in'=>'0.00','amount_out'=>'80.00'];
    }

    private function legs(): \Illuminate\Support\Collection
    {
        return collect([
            (object)['id'=>21,'type'=>'PPR','project_head_subheads_id'=>101,
                'amount_in'=>'80.00','amount_out'=>'0.00'],
            (object)['id'=>22,'type'=>'PPR','project_head_subheads_id'=>102,
                'amount_in'=>'0.00','amount_out'=>'80.00'],
        ]);
    }

    public function test_balanced_original_ppr_keeps_receivable_ledger_fk(): void
    {
        BalancedOriginalPprIntegrity::assert($this->receipt(),$this->legs(),21);
        $this->assertCount(2,$this->legs());
    }

    public function test_reversed_booking_fk_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('PPR_BALANCED_POSTING_MISMATCH');
        BalancedOriginalPprIntegrity::assert($this->receipt(),$this->legs(),22);
    }

    public function test_same_bank_and_receivable_account_rejected(): void
    {
        $legs=$this->legs();
        $legs[1]->project_head_subheads_id=101;
        $this->expectException(\DomainException::class);
        BalancedOriginalPprIntegrity::assert($this->receipt(),$legs,21);
    }

    public function test_unbalanced_cash_bank_counter_rejected(): void
    {
        $legs=$this->legs();
        $legs[1]->amount_out='79.00';
        $this->expectException(\DomainException::class);
        BalancedOriginalPprIntegrity::assert($this->receipt(),$legs,21);
    }
}
