<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\FinancialReversalPlanner;
use App\Services\Finance\VerifiedLedgerSourceResolver;
use PHPUnit\Framework\TestCase;

class TypedFinanceFoundationTest extends TestCase
{
    private function ledger(): array
    {
        return ['id' => 55, 'type' => 'JV', 'type_id' => 41, 'voucher_number' => '7001'];
    }

    public function test_unverified_collision_keeps_original_ledger_number(): void
    {
        $report = (new VerifiedLedgerSourceResolver())->resolve($this->ledger(), 7);
        $this->assertSame('JV-7001', $report['display_voucher_number']);
        $this->assertFalse($report['source_verified']);
    }

    public function test_independently_verified_jv_uses_jv_header_number(): void
    {
        $report = (new VerifiedLedgerSourceResolver())->resolve(
            $this->ledger(), 7,
            ['ledger_id'=>55,'project_id'=>7,'source_kind'=>'journal_voucher','source_id'=>41,'evidence_kind'=>'new_write_transaction'],
            ['id'=>41,'project_id'=>7,'voucher_number'=>'9001']
        );
        $this->assertTrue($report['source_verified']);
        $this->assertSame('JV-9001', $report['display_voucher_number']);
    }

    public function test_verified_commission_source_cannot_borrow_jv_header_number(): void
    {
        $report = (new VerifiedLedgerSourceResolver())->resolve(
            $this->ledger(), 7,
            ['ledger_id'=>55,'project_id'=>7,'source_kind'=>'commission_voucher','source_id'=>41,'evidence_kind'=>'manual_review'],
            ['id'=>41,'project_id'=>7,'voucher_number'=>'22']
        );
        $this->assertSame('CV-22', $report['display_voucher_number']);
        $this->assertTrue($report['source_verified']);
    }

    public function test_forged_cross_project_attribution_fails_closed(): void
    {
        $report = (new VerifiedLedgerSourceResolver())->resolve(
            $this->ledger(), 7,
            ['ledger_id'=>55,'project_id'=>8,'source_kind'=>'journal_voucher','source_id'=>41,'evidence_kind'=>'manual_review'],
            ['id'=>41,'project_id'=>8,'voucher_number'=>'9001']
        );
        $this->assertSame('JV-7001', $report['display_voucher_number']);
        $this->assertFalse($report['source_verified']);
    }

    public function test_reversal_plan_swaps_debit_credit_in_integer_cents(): void
    {
        $plan = (new FinancialReversalPlanner())->plan('journal_voucher', 7, 41, [
            ['account_id'=>11,'debit'=>'100.25','credit'=>'0.00'],
            ['account_id'=>22,'debit'=>'0.00','credit'=>'100.25'],
        ], true);
        $this->assertSame(10025, $plan['original_debit_cents']);
        $this->assertSame(10025, $plan['reversal_lines'][0]['credit_cents']);
        $this->assertSame(10025, $plan['reversal_lines'][1]['debit_cents']);
        $this->assertFalse($plan['posting_enabled']);
    }

    public function test_unverified_source_cannot_be_reversed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FinancialReversalPlanner())->plan('commission_voucher', 7, 1, [
            ['account_id'=>11,'debit'=>'10.00','credit'=>'0.00'],
            ['account_id'=>22,'debit'=>'0.00','credit'=>'10.00'],
        ], false);
    }

    public function test_unbalanced_original_cannot_be_reversed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FinancialReversalPlanner())->plan('journal_voucher', 7, 1, [
            ['account_id'=>11,'debit'=>'10.00','credit'=>'0.00'],
            ['account_id'=>22,'debit'=>'0.00','credit'=>'9.99'],
        ], true);
    }
}
