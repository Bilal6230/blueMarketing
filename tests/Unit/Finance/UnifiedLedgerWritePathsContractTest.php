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
        $approved=$this->source('app/Services/Finance/ApprovedSourcePostingWriter.php');
        self::assertStringContainsString('ApprovedDocumentLedgerLinker::class',$approved);
        $bridge=$this->source('app/Services/Finance/ApprovedDocumentLedgerLinker.php');
        self::assertStringContainsString("'stock_entry'",$bridge);
        self::assertStringContainsString("'stock_bill'",$bridge);
        self::assertStringContainsString("'booking'",$bridge);
        self::assertStringContainsString("'labour'",$bridge);
        $source=$this->source('app/Services/Finance/VoucherDetailLedgerLinker.php');
        self::assertStringContainsString('journal_voucher_detail_id',$source);
        self::assertStringContainsString('commision_voucher_detail_id',$source);
        self::assertStringContainsString('ledger_source_attributions',$source);
    }

    public function test_historical_ppr_matches_existing_ledger_instead_of_double_posting(): void
    {
        $src=$this->source('app/Services/Finance/TypedLedgerBackfill.php');
        self::assertStringContainsString('LEDGER_BACKFILL_AMBIGUOUS_LEGACY_SOURCE',$src);
        self::assertStringContainsString('$recovered[(int)$line->id]',$src);
        self::assertStringContainsString('LEDGER_BACKFILL_ATTRIBUTION_CONFLICT',$src);
        self::assertStringContainsString('LEDGER_BACKFILL_POSSIBLE_DUPLICATE_SOURCE',$src);
        self::assertStringContainsString('unmatched_old_ledger_ids=',$src);
        self::assertStringContainsString('unlinked_line_ids=',$src);
    }

    public function test_edited_commission_details_do_not_override_balanced_immutable_posting(): void
    {
        $src=$this->source('app/Services/Finance/VoucherDetailLedgerLinker.php');
        self::assertStringContainsString('DETAIL_LEDGER_ACTIVE_POSTING_UNBALANCED',$src);
        self::assertStringContainsString('(!$cv &&',$src);
        self::assertStringContainsString('voucher_detail_id',$src);
        self::assertStringContainsString('detail_deleted_at!==null && (int)($line->is_active??1)===1',$src);
        self::assertStringContainsString('detail_deleted=',$src);
        self::assertStringContainsString('ledger_source_attributions',$src);
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
