<?php

namespace Tests\Feature;

use App\Models\V2\FinancePostingBatch;
use App\Models\V2\FinancePostingLine;
use App\Models\V2\FinancePostingSourceClaim;
use App\Models\V2\ImportedHistoricalModel;
use App\Models\V2\ImportedJournalVoucher;
use App\Models\V2\ImportedJournalVoucherDetail;
use App\Models\V2\ImportedLedger;
use App\Models\V2\ImportedProjectAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseV2PostingModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');
        (require base_path('database/v2_migrations/2026_10_30_000010_create_v2_bank_lookup.php'))->up();
        (require base_path('database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php'))->up();
        (require base_path('database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_v2_finance_models_resolve_physical_fk_contracts_and_source_identity(): void
    {
        $batch = new FinancePostingBatch();
        $line = new FinancePostingLine();
        $claim = new FinancePostingSourceClaim();

        $this->assertSame('finance_posting_batches', $batch->getTable());
        $this->assertSame('posting_batch_id', $batch->lines()->getForeignKeyName());
        $this->assertSame('reversal_of_batch_id', $batch->originalBatch()->getForeignKeyName());
        $this->assertSame('reversal_of_batch_id', $batch->reversal()->getForeignKeyName());
        $this->assertSame('posting_batch_id', $batch->sourceClaim()->getForeignKeyName());
        $this->assertSame('posting_batch_id', $line->batch()->getForeignKeyName());
        $this->assertSame('project_head_subhead_id', $line->account()->getForeignKeyName());
        $this->assertSame('posting_batch_id', $claim->batch()->getForeignKeyName());
        $this->assertSame('posting_batch_id', $claim->getKeyName());
        $this->assertFalse($claim->getIncrementing());
        $this->assertFalse($claim->usesTimestamps());

        foreach ([
            [$batch, ['reversal_of_batch_id', 'effective_date', 'posted_at']],
            [$line, ['project_head_subhead_id', 'debit', 'credit']],
            [$claim, ['posting_batch_id', 'source_kind']],
        ] as [$model, $columns]) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($model->getTable(), $column));
            }
        }
    }

    public function test_v2_money_and_dates_are_cast_without_float_or_mutating_history(): void
    {
        $line = new FinancePostingLine();
        $line->setRawAttributes(['debit' => '125.50', 'credit' => '0.00']);
        $this->assertSame('125.50', $line->debit);
        $this->assertSame('0.00', $line->credit);
        $this->assertSame('decimal:2', $line->getCasts()['debit']);

        $batch = new FinancePostingBatch();
        $batch->setRawAttributes(['effective_date' => '2026-10-30', 'posted_at' => '2026-10-30 12:13:14']);
        $this->assertSame('2026-10-30', $batch->effective_date->format('Y-m-d'));
        $this->assertSame('2026-10-30 12:13:14', $batch->posted_at->format('Y-m-d H:i:s'));
        $this->assertSame('immutable_datetime', $batch->getCasts()['posted_at']);
    }
    public function test_imported_finance_models_follow_physical_source_columns_not_v1_model_drift(): void
    {
        $journal = new ImportedJournalVoucher();
        $detail = new ImportedJournalVoucherDetail();
        $ledger = new ImportedLedger();
        $account = new ImportedProjectAccount();

        $this->assertInstanceOf(ImportedHistoricalModel::class, $journal);
        $this->assertInstanceOf(ImportedHistoricalModel::class, $ledger);
        $this->assertSame('journal_vouchers', $journal->getTable());
        $this->assertSame('journal_voucher_details', $detail->getTable());
        $this->assertSame('ledgers', $ledger->getTable());
        $this->assertSame('project_head_subheads', $account->getTable());

        $this->assertFalse(Schema::hasColumn('journal_vouchers', 'site_id'));
        $this->assertFalse(Schema::hasColumn('ledgers', 'journal_voucher_detail_id'));
        $this->assertSame('journal_voucher_id', $journal->details()->getForeignKeyName());
        $this->assertSame('journal_voucher_id', $detail->journalVoucher()->getForeignKeyName());
        $this->assertSame('account_id', $detail->account()->getForeignKeyName());
        $this->assertSame('project_head_subheads_id', $ledger->projectAccount()->getForeignKeyName());
        $this->assertSame('project_head_subheads_id', $account->legacyLedgerRows()->getForeignKeyName());

        $detail->setRawAttributes(['debit' => '125.50', 'credit' => '0.00']);
        $ledger->setRawAttributes(['amount_in' => '25.50', 'amount_out' => '0.00']);
        $this->assertSame('125.50', $detail->debit);
        $this->assertSame('25.50', $ledger->amount_in);
        $this->assertSame('decimal:2', $ledger->getCasts()['amount_in']);
        $this->assertSame('immutable_date', $journal->getCasts()['date']);
    }

    public function test_imported_finance_model_rejects_eloquent_mutation(): void
    {
        $legacy = new ImportedLedger();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_IMPORTED_SOURCE_MODEL_READ_ONLY');
        $legacy->save();
    }

    public function test_imported_finance_model_rejects_nonisolated_query_connection(): void
    {
        config()->set('database.connections.sqlite.database', '/tmp/untrusted-v1.sqlite');
        DB::purge('sqlite');
        $this->expectException(\RuntimeException::class);
        (new ImportedJournalVoucher())->newQuery();
    }

}
