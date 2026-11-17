<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Phase 08 Plot Purchase pilot, for the explicitly isolated local DB.
 * Does not touch the existing Phase 07 Plot Receipt source or legacy bookings.
 */
return new class extends Migration {
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();

        foreach (['projects', 'users', 'plots', 'project_head_subheads',
            'finance_posting_batches', 'finance_posting_source_claims'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('V2_PLOT_PURCHASE_BASELINE_MISSING: '.$table);
            }
        }

        $tables = [
            'finance_plot_purchases',
            'finance_plot_purchase_items',
            'finance_plot_purchase_postings',
        ];
        $present = array_values(array_filter($tables, static fn (string $t): bool => Schema::hasTable($t)));
        if (count($present) === count($tables)) {
            // A failed MySQL DDL ALTER can leave all three tables but no FK.
            if (DB::getDriverName() === 'mysql') {
                $columns = DB::table('information_schema.KEY_COLUMN_USAGE')
                    ->whereRaw('TABLE_SCHEMA = DATABASE()')
                    ->where('TABLE_NAME', 'finance_plot_purchase_postings')
                    ->where('REFERENCED_TABLE_NAME', 'finance_posting_source_claims')
                    ->whereNotNull('REFERENCED_COLUMN_NAME')
                    ->get(['CONSTRAINT_NAME', 'COLUMN_NAME', 'REFERENCED_COLUMN_NAME'])
                    ->groupBy('CONSTRAINT_NAME');
                $valid = $columns->contains(static function ($entries): bool {
                    $fk = $entries->mapWithKeys(static fn ($v): array =>
                        [$v->COLUMN_NAME => $v->REFERENCED_COLUMN_NAME])->all();
                    return count($fk) === 2
                        && ($fk['posting_batch_id'] ?? null) === 'posting_batch_id'
                        && ($fk['source_kind'] ?? null) === 'source_kind';
                });
                if (!$valid) {
                    throw new RuntimeException('V2_PLOT_PURCHASE_INCOMPLETE_FK: reviewed forward repair required');
                }
            }
            return; // Existing complete evidence is immutable.
        }
        if ($present !== []) {
            throw new RuntimeException('V2_PLOT_PURCHASE_PARTIAL_STORAGE: '.implode(', ', $present));
        }

        Schema::create('finance_plot_purchases', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $t->foreignId('seller_account_id')->constrained('project_head_subheads')->restrictOnDelete();
            $t->string('purchase_number', 64);
            $t->date('purchase_date');
            $t->decimal('amount', 18, 2);
            $t->text('narration');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'purchase_number'], 'v2_plot_purch_proj_number_uq');
            $t->foreign('created_by', 'v2_plot_purch_creator_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('finance_plot_purchase_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('purchase_id')->constrained('finance_plot_purchases')->restrictOnDelete();
            $t->string('plot_code', 64);
            $t->string('description', 255);
            $t->decimal('area', 18, 4);
            $t->string('unit', 24);
            $t->decimal('unit_rate', 18, 2);
            $t->decimal('amount', 18, 2);
            $t->foreignId('inventory_account_id')
                ->constrained('project_head_subheads')->restrictOnDelete();
            $t->unsignedBigInteger('legacy_plot_id')->nullable()->unique('v2_plot_purch_item_plot_uq');
            $t->timestamps();
            $t->unique(['purchase_id', 'plot_code'], 'v2_plot_purch_item_code_uq');
            $t->foreign('legacy_plot_id', 'v2_plot_purch_item_legacy_fk')
                ->references('id')->on('plots')->restrictOnDelete();
        });
        Schema::create('finance_plot_purchase_postings', function (Blueprint $t): void {
            $t->unsignedBigInteger('posting_batch_id')->primary();
            $t->foreign('posting_batch_id', 'v2_plot_purch_post_batch_fk')
                ->references('id')->on('finance_posting_batches')->restrictOnDelete();
            $t->foreignId('source_id')->unique('v2_plot_purch_post_src_uq')
                ->constrained('finance_plot_purchases', 'id', 'v2_plot_purch_post_src_fk')->restrictOnDelete();
            $t->string('source_kind', 24)->default('plot_purchase');
            $t->foreign(['posting_batch_id', 'source_kind'], 'v2_plot_purch_post_claim_fk')
                ->references(['posting_batch_id', 'source_kind'])
                ->on('finance_posting_source_claims')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Posted Plot Purchase evidence cannot be dropped by rollback.');
    }
};
