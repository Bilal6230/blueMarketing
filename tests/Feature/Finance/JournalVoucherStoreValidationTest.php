<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\JournalVoucherController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Invalid JV payloads must fail before any accounting/database posting.
 * These test cases intentionally do not create any tables or use MySQL.
 */
class JournalVoucherStoreValidationTest extends TestCase
{
    private function payload(): array
    {
        return [
            'voucher_number' => '1',
            'date' => '2026-10-26',
            'description' => 'Isolated balanced journal',
            'accounts' => [1, 2],
            'sub_accounts' => [11, 22],
            'debit' => ['100.00', '0.00'],
            'credit' => ['0.00', '100.00'],
        ];
    }

    public function test_mismatched_row_arrays_are_rejected_before_posting(): void
    {
        $payload = $this->payload();
        $payload['credit'] = ['0.00'];
        $this->assertInvalid($payload);
    }

    public function test_negative_amounts_are_rejected_before_posting(): void
    {
        $payload = $this->payload();
        $payload['debit'][0] = '-100.00';
        $this->assertInvalid($payload);
    }

    public function test_fractional_cents_are_rejected_before_posting(): void
    {
        $payload = $this->payload();
        $payload['debit'][0] = '100.001';
        $this->assertInvalid($payload);
    }

    public function test_nonnumeric_credit_is_rejected_before_posting(): void
    {
        $payload = $this->payload();
        $payload['credit'][1] = 'not-money';
        $this->assertInvalid($payload);
    }

    public function test_empty_line_arrays_are_rejected_before_posting(): void
    {
        $payload = $this->payload();
        $payload['accounts'] = [];
        $this->assertInvalid($payload);
    }

    private function assertInvalid(array $payload): void
    {
        try {
            app(JournalVoucherController::class)->store(Request::create('/test-jv', 'POST', $payload));
            $this->fail('Unsafe journal payload was accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
