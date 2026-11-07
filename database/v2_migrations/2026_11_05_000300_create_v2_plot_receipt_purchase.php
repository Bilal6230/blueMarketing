<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['projects','users','plots','bookings','leads','project_head_subheads',
            'finance_posting_batches','finance_posting_source_claims'] as $table) {
            if (!Schema::hasTable($table)) throw new RuntimeException('V2 plot workflow baseline missing '.$table);
        }
        foreach (['finance_plot_receipts','finance_plot_receipt_allocations','finance_plot_receipt_postings',
            'finance_plot_purchases','finance_plot_purchase_items','finance_plot_purchase_postings'] as $table) {
            if (Schema::hasTable($table)) throw new RuntimeException('V2 plot workflow table exists already: '.$table);
        }
        Schema::create('finance_plot_receipts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('leads')->restrictOnDelete();
            $t->string('receipt_number', 64); $t->date('receipt_date'); $t->decimal('amount',18,2);
            $t->text('narration'); $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps(); $t->unique(['project_id','receipt_number']);
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('finance_plot_receipt_allocations', function (Blueprint $t): void {
            $t->id(); $t->foreignId('receipt_id')->constrained('finance_plot_receipts')->restrictOnDelete();
            $t->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $t->foreignId('plot_id')->constrained('plots')->restrictOnDelete();
            $t->decimal('amount',18,2); $t->timestamps();
            $t->unique(['receipt_id','booking_id']);
        });
        Schema::create('finance_plot_purchases', function (Blueprint $t): void {
            $t->id(); $t->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $t->foreignId('seller_account_id')->constrained('project_head_subheads')->restrictOnDelete();
            $t->string('purchase_number',64); $t->date('purchase_date'); $t->decimal('amount',18,2);
            $t->text('narration'); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
            $t->unique(['project_id','purchase_number']);
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('finance_plot_purchase_items', function (Blueprint $t): void {
            $t->id(); $t->foreignId('purchase_id')->constrained('finance_plot_purchases')->restrictOnDelete();
            $t->string('plot_code',64); $t->string('description',255); $t->decimal('area',18,4);
            $t->string('unit',24); $t->decimal('unit_rate',18,2); $t->decimal('amount',18,2);
            $t->foreignId('inventory_account_id')->constrained('project_head_subheads')->restrictOnDelete();
            $t->unsignedBigInteger('legacy_plot_id')->nullable()->unique();
            $t->timestamps(); $t->unique(['purchase_id','plot_code']);
            $t->foreign('legacy_plot_id')->references('id')->on('plots')->restrictOnDelete();
        });
        foreach ([['finance_plot_receipt_postings','finance_plot_receipts','plot_receipt'],
            ['finance_plot_purchase_postings','finance_plot_purchases','plot_purchase']] as [$table,$source,$kind]) {
            Schema::create($table, function (Blueprint $t) use ($source,$kind): void {
                $t->unsignedBigInteger('posting_batch_id')->primary();
                $t->foreign('posting_batch_id')->references('id')->on('finance_posting_batches')->restrictOnDelete();
                $t->foreignId('source_id')->unique()->constrained($source)->restrictOnDelete();
                $t->string('source_kind',24)->default($kind);
                $t->foreign(['posting_batch_id','source_kind'])->references(['posting_batch_id','source_kind'])
                    ->on('finance_posting_source_claims')->restrictOnDelete();
            });
        }
    }
    public function down(): void { throw new LogicException('Plot financial sources are immutable; use a reviewed forward migration.'); }
};
