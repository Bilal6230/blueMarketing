<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class BankTransferSourceIdentityContractTest extends TestCase
{
    public function test_bank_transfer_ledger_sync_uses_real_source_primary_key(): void
    {
        $migration=file_get_contents(__DIR__.'/../../../database/finance_legacy_migrations/2026_11_05_000200_create_finance_bank_transfer_sources.php');
        $backfill=file_get_contents(__DIR__.'/../../../app/Services/Finance/TypedLedgerBackfill.php');
        $editor=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankTransferEditor.php');

        self::assertIsString($migration);
        self::assertIsString($backfill);
        self::assertIsString($editor);
        self::assertStringContainsString("unsignedBigInteger('posting_batch_id')->primary()", $migration);
        self::assertStringContainsString('$sourceId=(int)$link->posting_batch_id;', $backfill);
        self::assertStringContainsString("->value('posting_batch_id')", $editor);
        self::assertStringNotContainsString("->value('id')", $editor);
        self::assertStringContainsString('->where(\'l.type_id\',$sourceId)', $editor);
        self::assertStringContainsString('BANK_LEDGER_RELATION_INVALID', $editor);
    }
}
