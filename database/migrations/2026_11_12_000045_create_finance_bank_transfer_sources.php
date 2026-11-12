<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add only the missing V2 transfer source link table for an existing local
 * finance test database. Never alter or reconstruct existing finance rows.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_bank_transfer_sources')) {
            return;
        }

        Schema::create('finance_bank_transfer_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('posting_batch_id')->unique('bank_transfer_batch_unique');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->unsignedBigInteger('linked_customer_ledger_id')->nullable()
                ->unique('bank_transfer_cheque_source_unique');
            $table->string('transfer_kind', 16);
            $table->decimal('amount', 16, 2);
            $table->text('narration');
            $table->timestamps();

            $table->index(['project_id', 'transfer_kind', 'posting_batch_id'], 'bank_transfer_project_kind_idx');
            // Existing finance V2 DBs may have legacy orphan rows. Avoid
            // introducing cascading FKs or backfilling historical claims.
        });
    }

    public function down(): void
    {
        // Financial link evidence cannot be safely discarded with rollback.
        throw new \LogicException('Bank transfer source history requires a reviewed data-preserving rollback.');
    }
};
