<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class PostedVoucherLedgerGateContractTest extends TestCase
{
    public function test_every_core_posting_and_replay_path_requires_real_general_ledger_links(): void
    {
        $paths = [
            'CashPostingWriter.php',
            'JournalPostingWriter.php',
            'CommissionPostingWriter.php',
            'ApprovedSourcePostingWriter.php',
            'PostingReversalWriter.php',
            'BankTransferWriter.php',
            'PlotEntryWriter.php',
        ];

        foreach ($paths as $filename) {
            $source = file_get_contents(base_path('app/Services/Finance/'.$filename));
            self::assertIsString($source);
            self::assertGreaterThanOrEqual(
                2,
                substr_count($source, 'PostedVoucherLedgerGate::assertLinked('),
                $filename.' must check both fresh posting and replay'
            );
        }

        $gate = file_get_contents(base_path('app/Services/Finance/PostedVoucherLedgerGate.php'));
        self::assertIsString($gate);
        self::assertStringContainsString('POSTED_VOUCHER_LEDGER_LINK_MISSING', $gate);
        self::assertStringContainsString('POSTED_VOUCHER_LEDGER_MISMATCH', $gate);
        self::assertStringContainsString("'ledgers'", $gate);
        self::assertStringContainsString("'finance_posting_lines'", $gate);
        self::assertStringContainsString('where(\'a.project_id\', $projectId)', $gate);
        self::assertStringNotContainsString("Schema::create(", $gate);
    }
}
