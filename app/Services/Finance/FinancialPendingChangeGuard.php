<?php

namespace App\Services\Finance;

/**
 * Shared fail-closed policy for legacy web/mobile approval payloads.
 * Does not post, backfill or alter historical finance evidence.
 *
 * A posted ledger requires a reversal/re-posting workflow for financial
 * changes. Pending approval can only change narrowly permitted narrative.
 */
final class FinancialPendingChangeGuard
{
    private const POSTED_FIELDS = [
        'ledgers' => ['detail'],
        'customer_ledger' => ['description', 'note'],
    ];

    private const FORBIDDEN_DRAFT_FIELDS = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'project_id', 'project_head_subheads_id', 'customer_ledger_id',
        'type', 'type_id', 'transaction_type', 'customer_id', 'plot_id',
        'create_by', 'update_by', 'status',
    ];

    public function assertAllowed(string $table, array $changes): void
    {
        if ($changes === [] || array_is_list($changes)) {
            throw new \DomainException('finance_pending_empty_or_malformed_payload');
        }

        if (isset(self::POSTED_FIELDS[$table])) {
            $allowed = self::POSTED_FIELDS[$table];
            foreach (array_keys($changes) as $column) {
                if (!is_string($column) || !in_array($column, $allowed, true)) {
                    throw new \DomainException('posted_financial_mutation_requires_reversal');
                }
            }
            foreach ($changes as $value) {
                if ($value !== null && (!is_string($value) || mb_strlen($value) > 10000)) {
                    throw new \DomainException('invalid_financial_description');
                }
            }
            return;
        }

        if ($table === 'draft_ledgers') {
            foreach (array_keys($changes) as $column) {
                if (!is_string($column) || in_array($column, self::FORBIDDEN_DRAFT_FIELDS, true)) {
                    throw new \DomainException('draft_identity_mutation_forbidden');
                }
            }
            return;
        }

        throw new \DomainException('invalid_financial_approval_table');
    }
}
