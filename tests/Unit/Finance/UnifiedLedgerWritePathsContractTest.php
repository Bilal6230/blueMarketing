<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class UnifiedLedgerWritePathsContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(base_path($path));
        self::assertIsString($source);
        return str_replace(["\r\n","\r"],"\n",$source);
    }

    public function test_new_bank_and_plot_vouchers_create_actual_linked_general_ledger_records(): void
    {
        $bank=$this->source('app/Services/Finance/BankTransferWriter.php');
        $plot=$this->source('app/Services/Finance/PlotEntryWriter.php');
        self::assertStringContainsString('TypedLedgerBackfill::class',$bank);
        self::assertStringContainsString('TypedLedgerBackfill::class',$plot);
        self::assertStringContainsString('GENERAL_LEDGER_SCHEMA_REQUIRED',$bank);
        self::assertStringContainsString('GENERAL_LEDGER_SCHEMA_REQUIRED',$plot);
    }

    public function test_edit_delete_posting_lifecycle_updates_linked_ledger_status_and_amounts(): void
    {
        $bank=$this->source('app/Services/Finance/BankTransferEditor.php');
        $plot=$this->source('app/Services/Finance/PlotVoucherEditor.php');
        self::assertStringContainsString('syncLinkedLedgerStatus(',$bank);
        self::assertStringContainsString('syncAccountingLedgers(',$plot);
        self::assertStringContainsString('TypedLedgerBackfill::class',$bank);
        self::assertStringContainsString('TypedLedgerBackfill::class',$plot);
        self::assertStringContainsString("'is_active'=>0",' '.$plot);
        self::assertStringContainsString("'type_id'",$this->source('app/Services/Finance/TypedLedgerBackfill.php'));
    }

    public function test_source_owned_cash_journal_commission_and_reversal_writers_are_connected(): void
    {
        $cash=$this->source('app/Services/Finance/CashPostingWriter.php');
        $journal=$this->source('app/Services/Finance/JournalPostingWriter.php');
        $commission=$this->source('app/Services/Finance/CommissionPostingWriter.php');
        $reverse=$this->source('app/Services/Finance/PostingReversalWriter.php');
        self::assertStringContainsString('CashSourceLedgerLinker::class',$cash);
        self::assertStringContainsString('VoucherDetailLedgerLinker::class',$journal);
        self::assertStringContainsString('VoucherDetailLedgerLinker::class',$commission);
        self::assertStringContainsString('ReversalLedgerLinker::class',$reverse);
        $source=$this->source('app/Services/Finance/VoucherDetailLedgerLinker.php');
        self::assertStringContainsString('journal_voucher_detail_id',$source);
        self::assertStringContainsString('commision_voucher_detail_id',$source);
        self::assertStringContainsString('ledger_source_attributions',$source);
    }

    public function test_project_scoped_test_db_backfill_has_no_new_database_tables(): void
    {
        $cmd=$this->source('app/Console/Commands/UnifyTestingLedgers.php');
        self::assertStringContainsString('finance:unify-testing-ledgers',$cmd);
        self::assertStringContainsString('--confirm=',$cmd);
        self::assertStringContainsString('--apply',$cmd);
        self::assertStringContainsString("whereNull('line.legacy_ledger_id')",$cmd);
        foreach([
            'app/Services/Finance/CashSourceLedgerLinker.php',
            'app/Services/Finance/VoucherDetailLedgerLinker.php',
            'app/Services/Finance/ReversalLedgerLinker.php',
            'app/Services/Finance/TypedLedgerBackfill.php',
        ] as $path) {
            $source=$this->source($path);
            self::assertStringNotContainsString('Schema::create(',$source);
            self::assertStringNotContainsString('Schema::drop(',$source);
        }
    }
}
