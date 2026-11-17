<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REQUIRED = [
        'projects', 'users', 'project_head_subheads',
        'journal_vouchers', 'journal_voucher_details',
        'commision_vouchers', 'stock_entries', 'stock_bills',
        'customer_ledger', 'booking_vouchers', 'labour_ledgers',
    ];

    private const SOURCE_TABLES = [
        'finance_posting_journal_sources' => ['journal_voucher_id', 'journal_vouchers'],
        'finance_posting_commission_sources' => ['commision_voucher_id', 'commision_vouchers'],
        'finance_posting_stock_entry_sources' => ['stock_entry_id', 'stock_entries'],
        'finance_posting_stock_bill_sources' => ['stock_bill_id', 'stock_bills'],
        'finance_posting_cash_sources' => ['customer_ledger_id', 'customer_ledger'],
        'finance_posting_booking_sources' => ['booking_voucher_id', 'booking_vouchers'],
        'finance_posting_labour_sources' => ['labour_ledger_id', 'labour_ledgers'],
    ];

    private const SOURCE_KINDS = [
        'finance_posting_journal_sources' => 'journal',
        'finance_posting_commission_sources' => 'commission',
        'finance_posting_stock_entry_sources' => 'stock_entry',
        'finance_posting_stock_bill_sources' => 'stock_bill',
        'finance_posting_cash_sources' => 'cash',
        'finance_posting_booking_sources' => 'booking',
        'finance_posting_labour_sources' => 'labour',
    ];

    private function assertIsolatedV2(): void
    {
        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();
        $driver = $connection->getDriverName();
        if (!str_starts_with($database, 'bluemarketing_v2_') &&
            !($driver === 'sqlite' && $database === ':memory:')) {
            throw new RuntimeException('V2 migrations require bluemarketing_v2_* isolated DB or disposable SQLite memory.');
        }

        foreach (self::REQUIRED as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('V2 baseline must exist first: missing ' . $table);
            }
        }

        // A V1 project account has unsigned INT ID. It cannot be referenced
        // by our BIGINT UNSIGNED foreign key: never coerce or silently retry.
        if ($driver === 'mysql') {
            foreach (self::REQUIRED as $table) {
                $row = DB::selectOne(
                    'SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS ' .
                    'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$table, 'id']
                );
                if (!$row || strtolower((string) $row->column_type) !== 'bigint(20) unsigned') {
                    // MySQL 8.4 may report "bigint unsigned" without display width.
                    if (!$row || !preg_match('/^bigint(?:\(\d+\))? unsigned$/i', (string) $row->column_type)) {
                        throw new RuntimeException('V2 needs BIGINT UNSIGNED primary key: ' . $table . '.id');
                    }
                }
            }
        }

        foreach (array_merge(['finance_posting_batches', 'finance_posting_lines', 'finance_posting_source_claims', 'finance_journal_line_sources'], array_keys(self::SOURCE_TABLES)) as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('V2 table exists already; no partial overwrite: ' . $table);
            }
        }
    }

    public function up(): void
    {
        $this->assertIsolatedV2();

        Schema::create('finance_posting_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('voucher_series', 24);
            $table->unsignedBigInteger('voucher_number')->nullable();
            $table->string('request_key', 128)->nullable();
            $table->char('payload_sha256', 64)->nullable();
            $table->date('effective_date');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversal_of_batch_id')->nullable()
                ->constrained('finance_posting_batches')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'request_key'], 'v2_posting_project_idempotency_unique');
            $table->unique('reversal_of_batch_id', 'v2_one_reversal_per_batch_unique');
            $table->index(['project_id', 'effective_date'], 'v2_posting_project_date_index');
        });

        Schema::create('finance_posting_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_batch_id')->constrained('finance_posting_batches')->restrictOnDelete();
            $table->foreignId('project_head_subhead_id')->constrained('project_head_subheads')->restrictOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('legacy_ledger_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['posting_batch_id', 'project_head_subhead_id'], 'v2_posting_line_account_index');
        });

        // One canonical claim per batch. Composite FKs require every typed
        // source table to agree with the claimed family (no type_id collisions).
        Schema::create('finance_posting_source_claims', function (Blueprint $table) {
            $table->foreignId('posting_batch_id')->primary()
                ->constrained('finance_posting_batches')->restrictOnDelete();
            $table->string('source_kind', 24);
            $table->unique(['posting_batch_id', 'source_kind'], 'v2_claim_batch_kind_unique');
        });

        foreach (self::SOURCE_TABLES as $tableName => [$sourceColumn, $sourceTable]) {
            $kind = self::SOURCE_KINDS[$tableName];
            Schema::create($tableName, function (Blueprint $table) use ($sourceColumn, $sourceTable, $kind, $tableName) {
                $table->unsignedBigInteger('posting_batch_id')->primary();
                $table->string('source_kind', 24)->default($kind);
                $table->foreign(['posting_batch_id', 'source_kind'], 'v2_' . $tableName . '_claim_fk')
                    ->references(['posting_batch_id', 'source_kind'])
                    ->on('finance_posting_source_claims')->restrictOnDelete();
                $table->foreignId($sourceColumn)->constrained($sourceTable)->restrictOnDelete();
                $table->unique($sourceColumn);
            });
            if (DB::connection()->getDriverName() === 'mysql') {
                // Even direct SQL cannot spoof source_kind for another family.
                $check = 'v2_' . $tableName . '_kind_ck';
                DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$check}` CHECK (`source_kind` = '{$kind}')");
            }
        }

        Schema::create('finance_journal_line_sources', function (Blueprint $table) {
            $table->foreignId('posting_line_id')->primary()
                ->constrained('finance_posting_lines')->restrictOnDelete();
            $table->foreignId('journal_voucher_detail_id')->unique()
                ->constrained('journal_voucher_details')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        $driver = DB::connection()->getDriverName();
        if (!str_starts_with($database, 'bluemarketing_v2_') &&
            !($driver === 'sqlite' && $database === ':memory:')) {
            throw new RuntimeException('Refusing V2 rollback outside isolated database.');
        }

        Schema::dropIfExists('finance_journal_line_sources');
        foreach (array_reverse(array_keys(self::SOURCE_TABLES)) as $name) {
            Schema::dropIfExists($name);
        }
        Schema::dropIfExists('finance_posting_source_claims');
        Schema::dropIfExists('finance_posting_lines');
        Schema::dropIfExists('finance_posting_batches');
    }
};
