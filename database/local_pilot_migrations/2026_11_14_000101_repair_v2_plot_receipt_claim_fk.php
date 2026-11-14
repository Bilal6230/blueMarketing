<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recover only the composite claim FK missing after MySQL error 1059.
 * Nothing is dropped, recreated, truncated or backfilled.
 */
return new class extends Migration {
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['finance_plot_receipts', 'finance_plot_receipt_allocations',
            'finance_plot_receipt_postings', 'finance_posting_source_claims'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('V2_PLOT_RECEIPT_FK_REPAIR_MISSING_TABLE: '.$table);
            }
        }
        if (DB::getDriverName() !== 'mysql') {
            // Original SQLite fixture migration already creates the FK.
            throw new RuntimeException('V2_PLOT_RECEIPT_FK_REPAIR_MYSQL_ONLY');
        }
        $existing = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'finance_plot_receipt_postings')
            ->where('REFERENCED_TABLE_NAME', 'finance_posting_source_claims')
            ->whereNotNull('REFERENCED_COLUMN_NAME')
            ->select('CONSTRAINT_NAME', 'COLUMN_NAME', 'REFERENCED_COLUMN_NAME')
            ->get()->groupBy('CONSTRAINT_NAME');
        foreach ($existing as $columns) {
            $actual = $columns->mapWithKeys(static fn ($item): array =>
                [$item->COLUMN_NAME => $item->REFERENCED_COLUMN_NAME])->all();
            if ($actual === [
                'posting_batch_id' => 'posting_batch_id',
                'source_kind' => 'source_kind',
            ]) {
                return; // Already protected; no duplicate constraint.
            }
        }
        // Data must not be silently repaired or re-associated.
        if (DB::table('finance_plot_receipt_postings')->exists()) {
            throw new RuntimeException('V2_PLOT_RECEIPT_FK_REPAIR_NONEMPTY_REVIEW_REQUIRED');
        }
        Schema::table('finance_plot_receipt_postings', function (Blueprint $t): void {
            $t->foreign(['posting_batch_id', 'source_kind'], 'v2_plot_rcpt_post_claim_fk')
                ->references(['posting_batch_id', 'source_kind'])
                ->on('finance_posting_source_claims')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Plot receipt claim constraints must never be removed by rollback.');
    }
};
