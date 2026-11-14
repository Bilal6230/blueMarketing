<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EXPLICIT LOCAL PILOT MIGRATION ONLY. Run with --path=database/local_pilot_migrations/...
 *
 * Installs Phase 07 plot-receipt storage without running the full Phase 07/08
 * V2 migration or modifying bookings, plots, ledgers or existing financial rows.
 */
return new class extends Migration {
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();

        foreach (['projects', 'users', 'leads', 'plots', 'bookings',
            'finance_posting_batches', 'finance_posting_source_claims'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('V2_PLOT_RECEIPT_BASELINE_MISSING: '.$table);
            }
        }

        $tables = [
            'finance_plot_receipts',
            'finance_plot_receipt_allocations',
            'finance_plot_receipt_postings',
        ];
        $present = array_filter($tables, static fn (string $table): bool => Schema::hasTable($table));
        if (count($present) === count($tables)) {
            // MySQL DDL is non-transactional. A previous failed foreign key
            // may have left all three tables present but incomplete.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $constraint = \Illuminate\Support\Facades\DB::table('information_schema.KEY_COLUMN_USAGE')
                    ->whereRaw('TABLE_SCHEMA = DATABASE()')
                    ->where('TABLE_NAME', 'finance_plot_receipt_postings')
                    ->where('CONSTRAINT_NAME', 'v2_plot_rcpt_post_claim_fk')
                    ->where('REFERENCED_TABLE_NAME', 'finance_posting_source_claims')
                    ->count();
                if ($constraint !== 2) {
                    throw new RuntimeException('V2_PLOT_RECEIPT_INCOMPLETE_FK: run reviewed Phase 07 FK repair migration first');
                }
            }
            return;
        }
        if ($present !== []) {
            throw new RuntimeException('V2_PLOT_RECEIPT_PARTIAL_STORAGE: '.implode(',', $present));
        }

        Schema::create('finance_plot_receipts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('leads')->restrictOnDelete();
            $t->string('receipt_number', 64);
            $t->date('receipt_date');
            $t->decimal('amount', 18, 2);
            $t->text('narration');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'receipt_number'], 'v2_plot_receipt_project_number_unique');
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('finance_plot_receipt_allocations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('receipt_id')->constrained('finance_plot_receipts')->restrictOnDelete();
            $t->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $t->foreignId('plot_id')->constrained('plots')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->timestamps();
            $t->unique(['receipt_id', 'booking_id'], 'v2_plot_receipt_booking_unique');
        });
        Schema::create('finance_plot_receipt_postings', function (Blueprint $t): void {
            $t->unsignedBigInteger('posting_batch_id')->primary();
            $t->foreign('posting_batch_id')->references('id')->on('finance_posting_batches')->restrictOnDelete();
            $t->foreignId('source_id')->unique()->constrained('finance_plot_receipts')->restrictOnDelete();
            $t->string('source_kind', 24)->default('plot_receipt');
            $t->foreign(['posting_batch_id', 'source_kind'], 'v2_plot_rcpt_post_claim_fk')
                ->references(['posting_batch_id', 'source_kind'])
                ->on('finance_posting_source_claims')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Plot receipt posting evidence cannot be deleted via rollback.');
    }
};
