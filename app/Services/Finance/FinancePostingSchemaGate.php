<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Schema;

/** Finance schema gate shared by the live JV form and transactional store path. */
final class FinancePostingSchemaGate
{
    public function mode(): string
    {
        $attributions = Schema::hasTable('ledger_source_attributions');
        $requests = Schema::hasTable('financial_posting_requests');
        if (!$attributions && !$requests) {
            return 'legacy';
        }
        if (!$attributions || !$requests) {
            throw new \RuntimeException('Incomplete financial metadata schema: posting is blocked until schema is reconciled.');
        }
        $report = app(FinanceSchemaPreflight::class)->inspect();
        if ($report['status'] !== 'schema_verified') {
            throw new \RuntimeException('Unsafe financial metadata schema: '.implode(', ', $report['blockers']));
        }
        return 'typed';
    }

    public function isReady(): bool
    {
        // Form generation should fail closed on a broken/partial installation,
        // just like posting. A missing legacy schema remains supported.
        return $this->mode() === 'typed';
    }
}
