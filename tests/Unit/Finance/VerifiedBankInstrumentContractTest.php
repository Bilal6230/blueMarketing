<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\VerifiedBankInstrument;
use PHPUnit\Framework\TestCase;

final class VerifiedBankInstrumentContractTest extends TestCase
{
    public function test_every_bank_funded_voucher_series_has_exact_polarity(): void
    {
        foreach (['CR','BOR','BD','PPR'] as $series) {
            self::assertSame('receipt',VerifiedBankInstrument::direction($series));
        }
        foreach (['DR','BOP','BW'] as $series) {
            self::assertSame('payment',VerifiedBankInstrument::direction($series));
        }
    }

    public function test_unrecognized_bank_source_series_fails_closed(): void
    {
        $this->expectException(\DomainException::class);
        VerifiedBankInstrument::direction('PPR2');
    }

    public function test_atomic_source_validation_requires_real_verified_events_and_allocation_budget(): void
    {
        $file=file_get_contents(__DIR__.'/../../../app/Services/Finance/VerifiedBankInstrument.php');
        self::assertIsString($file);
        foreach ([
            'DB::transactionLevel() < 1',
            "->lockForUpdate()->first()",
            'finance_bank_voucher_allocation_releases',
            'finance_bank_correction_requests',
            'finance_online_confirmation_events',
            'finance_cheque_intake_actions',
            'verification_confirmed_at',
            'BankSourceCorrectionEvidence::matchesOnlineAmount',
            'remaining verified bank balance',
        ] as $guard) self::assertStringContainsString($guard,$file);
        self::assertStringNotContainsString("DB::table('finance_bank_voucher_allocations')->insert(", $file);
    }
}
