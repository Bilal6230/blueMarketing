<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\FinancialPendingChangeGuard;
use PHPUnit\Framework\TestCase;

final class FinancialPendingChangeGuardTest extends TestCase
{
    public function test_only_narrative_fields_can_change_posted_finance(): void
    {
        $guard = new FinancialPendingChangeGuard();
        $guard->assertAllowed('ledgers', ['detail' => 'Corrected explanatory description']);
        $guard->assertAllowed('customer_ledger', ['description' => 'Corrected note', 'note' => null]);
        $this->assertTrue(true);
    }

    public function test_posted_ledger_amount_identity_project_and_date_changes_are_rejected(): void
    {
        foreach (['amount_in', 'amount_out', 'type', 'type_id',
                  'customer_ledger_id', 'project_head_subheads_id',
                  'date', 'is_active', 'deleted_at'] as $field) {
            try {
                (new FinancialPendingChangeGuard())->assertAllowed('ledgers', [$field => 1]);
                $this->fail('Expected immutable financial mutation rejection for ' . $field);
            } catch (\DomainException $e) {
                $this->assertSame('posted_financial_mutation_requires_reversal', $e->getMessage());
            }
        }
    }

    public function test_customer_ledger_financial_rewrite_and_draft_source_reassignment_are_rejected(): void
    {
        foreach ([
            ['customer_ledger', ['amount_in' => 100]],
            ['customer_ledger', ['project_id' => 2]],
            ['customer_ledger', ['type_id' => 11]],
            ['draft_ledgers', ['project_id' => 2]],
            ['draft_ledgers', ['customer_id' => 21]],
            ['draft_ledgers', ['type_id' => 3]],
        ] as [$table, $values]) {
            $this->expectNoExceptionCompat($table, $values);
        }
    }

    private function expectNoExceptionCompat(string $table, array $values): void
    {
        try {
            (new FinancialPendingChangeGuard())->assertAllowed($table, $values);
            $this->fail('Finance mutation incorrectly permitted in ' . $table);
        } catch (\DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_empty_numeric_array_and_unrecognized_table_are_rejected(): void
    {
        foreach ([
            ['ledgers', []], ['customer_ledger', ['amount_in']],
            ['leads', ['first_name' => 'Changed']],
        ] as [$table, $changes]) {
            try {
                (new FinancialPendingChangeGuard())->assertAllowed($table, $changes);
                $this->fail('Expected fail-closed request payload');
            } catch (\DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }
}
