<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['finance_posting_batches', 'finance_posting_source_claims', 'project_bank_accounts', 'projects', 'customer_ledger'] as $table) {
            if (!Schema::hasTable($table)) { throw new RuntimeException('V2 bank-transfer baseline missing ' . $table); }
        }
        if (Schema::hasTable('finance_bank_transfer_sources')) {
            throw new RuntimeException('V2 bank-transfer table exists already; refusing overwrite.');
        }
        Schema::create('finance_bank_transfer_sources', function (Blueprint $table): void {
            $table->unsignedBigInteger('posting_batch_id')->primary();
            $table->foreign('posting_batch_id')->references('id')->on('finance_posting_batches')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->unsignedBigInteger('bank_account_id');
            $table->foreign(['bank_account_id', 'project_id'], 'v2_bank_transfer_account_project_fk')
                ->references(['id', 'project_id'])->on('project_bank_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('linked_customer_ledger_id')->nullable()->unique('v2_bank_transfer_customer_source_uq');
            $table->foreign('linked_customer_ledger_id', 'v2_bank_transfer_customer_fk')
                ->references('id')->on('customer_ledger')->restrictOnDelete();
            $table->string('transfer_kind', 16);
            $table->decimal('amount', 18, 2);
            $table->text('narration');
            $table->timestamps();
            $table->index(['project_id', 'transfer_kind', 'created_at'], 'v2_bank_transfer_project_kind_idx');
        });
    }

    public function down(): void
    {
        throw new LogicException('Bank-transfer posting history is immutable; use a reviewed forward migration.');
    }
};
