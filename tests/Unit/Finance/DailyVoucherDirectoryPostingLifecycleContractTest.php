<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

/**
 * Prevent retired financial posting legs from leaking into the shared daily
 * register after same-ID Plot Receipt/Purchase edits.
 */
final class DailyVoucherDirectoryPostingLifecycleContractTest extends TestCase
{
    public function test_shared_directory_filters_archived_plot_sources_and_posting_legs(): void
    {
        $source=file_get_contents(dirname(__DIR__,3).
            '/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');
        self::assertIsString($source);

        $typedStart=strpos($source,"['table' => 'finance_plot_receipts'");
        $typedEnd=strpos($source,'private function postingLines(', $typedStart);
        self::assertNotFalse($typedStart);
        self::assertNotFalse($typedEnd);
        $typed=substr($source,$typedStart,$typedEnd-$typedStart);
        self::assertStringContainsString("Schema::hasColumn(\$typed['table'],'is_active')",$typed);
        self::assertStringContainsString("where('source.is_active',1)",$typed);
        self::assertStringContainsString("Schema::hasColumn(\$typed['table'],'deleted_at')",$typed);
        self::assertStringContainsString("whereNull('source.deleted_at')",$typed);
        self::assertStringContainsString("where('filtered_line.is_active',1)",$typed);

        $lines=substr($source,$typedEnd,strpos($source,'public function reverseTypedCashSource(', $typedEnd)-$typedEnd);
        self::assertStringContainsString("where('line.is_active',1)",$lines);
        self::assertStringContainsString("where('line.posting_batch_id', \$batchId)",$lines);
        self::assertStringContainsString("where('account.project_id', '=', \$projectId)",$lines);

        // Other typed transfers share the same ledger-leg filter.
        $transfers=substr($source,strpos($source,"finance_bank_transfer_sources as source"),
            $typedStart-strpos($source,"finance_bank_transfer_sources as source"));
        self::assertStringContainsString("where('filtered_line.is_active',1)",$transfers);
    }
}
