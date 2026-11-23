<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typed BD/BW pending approval must retain an immutable reference to the
 * verified management instrument; the legacy linked_customer_ledger_id is
 * NOT the cheque-intake ID and must never be reused for that purpose.
 * No additional table is created.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_bank_transfer_approval_drafts')) {
            throw new RuntimeException('Bank transfer approval draft table must exist first.');
        }
        Schema::table('finance_bank_transfer_approval_drafts', function(Blueprint $table): void {
            if (!Schema::hasColumn('finance_bank_transfer_approval_drafts','passed_source_method')) {
                $table->string('passed_source_method',12)->nullable();
            }
            if (!Schema::hasColumn('finance_bank_transfer_approval_drafts','passed_source_id')) {
                $table->unsignedBigInteger('passed_source_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Preserve immutable pending / historical bank instrument identities.
    }
};
