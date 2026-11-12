<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\VerifiedBankVoucherNarration;
use PHPUnit\Framework\TestCase;

final class VerifiedBankVoucherNarrationTest extends TestCase
{
    public function test_online_receipt_includes_verified_source_and_preserves_user_note(): void
    {
        $value=VerifiedBankVoucherNarration::compose(
            'BOR','online',79,'BOR-5','HBL','TX-900','2026-11-10',
            '2026-11-12','R-44','Plot booking payment');
        $this->assertSame(
            'BOR-5 | Method: Online | Source: Online #79 | Bank: HBL | Txn: TX-900 | Pass: 2026-11-10 | Ref: R-44 | Date: 2026-11-12 | Note: Plot booking payment',
            $value);
    }

    public function test_cheque_payment_uses_bop_and_cheque_reference(): void
    {
        $value=VerifiedBankVoucherNarration::compose(
            'BOP','cheque',17,'BOP-2','Meezan','8768678','2026-11-11',
            '2026-11-12',null,'Vendor payment');
        $this->assertStringContainsString('BOP-2 | Method: Cheque | Source: Cheque #17',$value);
        $this->assertStringContainsString('Chq: 8768678',$value);
        $this->assertStringContainsString('Note: Vendor payment',$value);
        $this->assertStringNotContainsString('Ref: ',$value);
    }

    public function test_bank_backed_cr_and_dr_use_same_metadata_contract(): void
    {
        foreach (['CR','DR'] as $series) {
            $value=VerifiedBankVoucherNarration::compose(
                $series,'online',4,$series.'-11',null,'T4',null,'2026-11-12',null,'memo');
            $this->assertStringContainsString($series.'-11 | Method: Online | Source: Online #4',$value);
            $this->assertStringContainsString('Note: memo',$value);
        }
    }

    public function test_missing_optional_bank_reference_and_note_do_not_produce_empty_labels(): void
    {
        $value=VerifiedBankVoucherNarration::compose(
            'BOR','online',10,'BOR-10',null,null,null,null,null,null);
        $this->assertSame('BOR-10 | Method: Online | Source: Online #10',$value);
    }

    public function test_invalid_source_or_method_is_rejected(): void
    {
        foreach ([
            ['BOR','cash',12,'BOR-1'],
            ['BOP','online',0,'BOP-1'],
            ['JV','online',3,'JV-1'],
            ['BOR','cheque',3,''],
        ] as [$series,$method,$id,$number]) {
            try {
                VerifiedBankVoucherNarration::compose(
                    $series,$method,$id,$number,null,null,null,null,null,null);
                $this->fail('Invalid narration identity should be rejected: '.$series.'/'.$method);
            } catch (\DomainException $e) {
                $this->assertNotSame('',$e->getMessage());
            }
        }
    }
}
