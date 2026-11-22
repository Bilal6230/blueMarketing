<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

/**
 * Static safety contracts for Milestone 04 Batch 03. Database integration
 * execution remains a separate owner-local acceptance gate.
 */
final class BankLinkedCorrectionContractTest extends TestCase
{
    public function test_same_id_bank_correction_requires_review_and_keeps_source_and_ledger_identity(): void
    {
        $source = file_get_contents(__DIR__.'/../../../app/Services/Finance/BankLinkedCorrection.php');
        self::assertIsString($source);
        self::assertStringContainsString('function preview(', $source);
        self::assertStringContainsString('function confirm(', $source);
        self::assertStringContainsString('hash_equals(', $source);
        self::assertStringContainsString('lockForUpdate()', $source);
        self::assertStringContainsString('DB::transaction(', $source);
        self::assertStringContainsString('BankVoucherLegIntegrity::assert(', $source);
        self::assertStringContainsString('assertNoPendingCorrection(', $source);
        self::assertStringContainsString('finance_bank_voucher_allocation_releases', $source);
        self::assertStringContainsString('daily_voucher_edit_snapshots', $source);
        self::assertStringContainsString("->where('id',$item['voucher_id'])", $source);
        self::assertStringContainsString("->where('id',$item['ledger_id'])", $source);
        self::assertStringNotContainsString('insertGetId(', $source);
        self::assertStringNotContainsString('->delete(', $source);
        self::assertStringNotContainsString('BankVoucherAllocationRelease::class', $source);
    }

    public function test_verified_replacement_stays_separate_from_same_id_correction(): void
    {
        $legacy = file_get_contents(__DIR__.'/../../../app/Services/Finance/BankManagementSourceMutation.php');
        self::assertIsString($legacy);
        self::assertStringContainsString('function replace(', $legacy);
        self::assertStringContainsString('function withdraw(', $legacy);
        self::assertStringNotContainsString('BankLinkedCorrection::class', $legacy);
    }
}
