<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\FinancialPostingFingerprint;
use PHPUnit\Framework\TestCase;

final class FinancialPostingFingerprintTest extends TestCase
{
    private function lines(): array
    {
        return [
            ['account_id' => 11, 'debit' => '100.25', 'credit' => '0.00'],
            ['account_id' => 22, 'debit' => '0.00', 'credit' => '100.25'],
        ];
    }

    public function test_identical_request_is_a_safe_replay_candidate(): void
    {
        $service = new FinancialPostingFingerprint();
        $first = $service->fingerprint('journal_voucher', 7, 'request_123456789012', $this->lines());
        $second = $service->fingerprint('journal_voucher', 7, 'request_123456789012', $this->lines());
        $this->assertSame('same_request', $service->classifyReplay($first, $second));
        $this->assertSame(10025, $first['amount_cents']);
    }

    public function test_same_key_with_changed_amount_is_conflict(): void
    {
        $service = new FinancialPostingFingerprint();
        $old = $service->fingerprint('journal_voucher', 7, 'request_123456789012', $this->lines());
        $lines = $this->lines();
        $lines[0]['debit'] = '80.00';
        $lines[1]['credit'] = '80.00';
        $new = $service->fingerprint('journal_voucher', 7, 'request_123456789012', $lines);
        $this->assertSame('conflicting_payload', $service->classifyReplay($old, $new));
    }

    public function test_other_project_and_type_get_distinct_scopes(): void
    {
        $service = new FinancialPostingFingerprint();
        $first = $service->fingerprint('journal_voucher', 7, 'request_123456789012', $this->lines());
        $otherProject = $service->fingerprint('journal_voucher', 8, 'request_123456789012', $this->lines());
        $otherType = $service->fingerprint('commission_voucher', 7, 'request_123456789012', $this->lines());
        $this->assertSame('new_request', $service->classifyReplay($first, $otherProject));
        $this->assertSame('new_request', $service->classifyReplay($first, $otherType));
    }

    public function test_equivalent_decimal_formats_hash_identically(): void
    {
        $service = new FinancialPostingFingerprint();
        $first = $service->fingerprint('journal_voucher', 7, 'request_123456789012', $this->lines());
        $lines = $this->lines();
        $lines[0]['debit'] = '100.25';
        $lines[1]['credit'] = '100.25';
        $this->assertSame($first['payload_hash'], $service->fingerprint('journal_voucher', 7, 'request_123456789012', $lines)['payload_hash']);
    }

    public function test_unbalanced_submission_is_rejected(): void
    {
        $lines = $this->lines();
        $lines[1]['credit'] = '100.24';
        $this->expectException(\InvalidArgumentException::class);
        (new FinancialPostingFingerprint())->fingerprint('journal_voucher', 7, 'request_123456789012', $lines);
    }

    public function test_invalid_request_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FinancialPostingFingerprint())->fingerprint('journal_voucher', 7, 'bad', $this->lines());
    }
}
