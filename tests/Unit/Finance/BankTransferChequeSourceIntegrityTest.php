<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\BankTransferChequeSourceIntegrity;
use PHPUnit\Framework\TestCase;

final class BankTransferChequeSourceIntegrityTest extends TestCase
{
    private function cheque(array $changes = []): object
    {
        return (object) array_replace([
            'transaction_type' => 'CP', 'payment_type' => 3,
            'is_active' => 1, 'passing_status' => 1,
            'amount_in' => '0.00', 'amount_out' => '1250.50',
        ], $changes);
    }

    public function test_eligible_outgoing_cheque_is_accepted(): void
    {
        BankTransferChequeSourceIntegrity::assert($this->cheque(), false, false);
        $this->addToAssertionCount(1);
    }

    public function test_existing_ledger_posting_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankTransferChequeSourceIntegrity::assert($this->cheque(), true, false);
    }

    public function test_existing_bank_allocation_is_rejected_even_when_released(): void
    {
        $this->expectException(\DomainException::class);
        BankTransferChequeSourceIntegrity::assert($this->cheque(), false, true);
    }

    public function test_incoming_cp_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankTransferChequeSourceIntegrity::assert($this->cheque([
            'amount_in' => '1250.50', 'amount_out' => '0.00',
        ]), false, false);
    }

    public function test_double_sided_amount_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankTransferChequeSourceIntegrity::assert($this->cheque(['amount_in' => '1.00']), false, false);
    }

    public function test_zero_outgoing_amount_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankTransferChequeSourceIntegrity::assert($this->cheque(['amount_out' => '0.00']), false, false);
    }

    public function test_wrong_type_or_unverified_status_is_rejected(): void
    {
        foreach ([
            ['transaction_type' => 'CR'],
            ['payment_type' => 2],
            ['is_active' => 0],
            ['passing_status' => 0],
        ] as $change) {
            try {
                BankTransferChequeSourceIntegrity::assert($this->cheque($change), false, false);
                $this->fail('Ineligible cheque was accepted.');
            } catch (\DomainException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_invalid_precision_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankTransferChequeSourceIntegrity::assert($this->cheque(['amount_out' => '125.555']), false, false);
    }
}
